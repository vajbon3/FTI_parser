<?php

namespace App\Feeds\Vendors\FTI;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Parser\HtmlParser;
use App\Feeds\Utils\Data;
use App\Feeds\Utils\ParserCrawler;
use App\Helpers\FeedHelper;
use App\Helpers\StringHelper;
use phpDocumentor\Reflection\PseudoTypes\LowercaseString;

class Parser extends HtmlParser
{

    private const DOMAIN = "https://www.firstteaminc.com";

    private array $short_description = [];
    private ?array $attributes = null;
    private array $dims = [];
    private ?float $shipping_weight = null;
    private ?float $weight = null;

    private function parseFeatures(array $features): array
    {
        $short_desc = [];
        $attributes = [];
        $dims = [
            "x" => null,
            "y" => null,
            "z" => null
        ];
        $shipping_weight = null;
        $weight = null;

        foreach ($features as $feature) {
            $lower_feature = strtolower($feature);
            if (str_contains($lower_feature, "dimensions")) {
                $dimension_string = explode(" ", $lower_feature);
                $dims = FeedHelper::getDimsInString($dimension_string[1], "x");
            }
            else if (str_contains($lower_feature, "weight")) {
                if (str_contains($lower_feature, "shipping weight")) {
                    $shipping_weight = preg_match("!\d+!", $lower_feature);
                } else {
                    $weight = preg_match("!\d+!", $lower_feature);
                }
            }
            else if (str_contains($lower_feature, ":")) {
                $arr = explode(":", $lower_feature);
                $key = trim($arr[0]);
                $value = trim($arr[1]);

                $attributes[$key] = $value;
            } else {
                $short_desc[] = $lower_feature;
            }
        }

        return [
            "short_description" => $short_desc,
            "attributes" => $attributes,
            "dims" => $dims,
            "shipping_weight" => $shipping_weight,
            "weight" => $weight
        ];
    }

    public function beforeParse(): void
    {
        $features = $this->getContent(".store-product-description li");
        $array = $this->parseFeatures($features);

        $this->short_description = $array["short_description"];
        $this->attributes = $array["attributes"];
        $this->dims = $array["dims"];
        $this->shipping_weight = $array["shipping_weight"];
        $this->weight = $array["weight"];
    }

    public function parseContent(Data $data, array $params = []): array
    {
        for ($i = 0; $i < 5; $i++) {
            if (!StringHelper::isNotEmpty($data->getData())) {
                $data = $this->getVendor()->getDownloader()->get($params["url"]);
            } else {
                break;
            }
        }

        if (!StringHelper::isNotEmpty($data->getData())) {
            return [];
        }

        return parent::parseContent($data, $params);
    }

    public function getProduct(): string
    {
        return $this->getText(".store-product-name");
    }

    public function getMpn(): string
    {
        return $this->getAttr("meta[itemprop='sku']", "content");
    }

    public function getShortDescription(): array
    {
        return $this->short_description;
    }

    public function getAttributes(): ?array
    {
        return $this->attributes !== [] ? $this->attributes : null;
    }

    public function getDimX(): ?float
    {
        return $this->dims["x"];
    }

    public function getDimY(): ?float
    {
        return $this->dims["y"];
    }

    public function getDimZ(): ?float
    {
        return $this->dims["z"];
    }

    public function getShippingWeight(): ?float
    {
        return $this->shipping_weight;
    }

    public function getWeight(): ?float
    {
        return $this->weight;
    }

    public function getImages(): array
    {
        $image_sources = [];

        // relative ссылки
        $image_sources[] =  $this->getAttr(".store-product-primary-image > a","href");

        // конвертирование в абсолютный
        foreach ($image_sources as &$image_source) {
            $image_source = self::DOMAIN . $image_source;
        }

        return $image_sources;
    }

    public function getCategories(): array
    {
        return $this->getContent(".breadcrumb-trail .breadcrumb-trail-sub a");
    }

    public function getAvail(): ?int
    {
        if($this->getText(".in-stock") === "In Stock")
            return self::DEFAULT_AVAIL_NUMBER;
        else if(str_contains($this->getAttr(".store-product-description p > img","alt"),"Stock")) {
            return self::DEFAULT_AVAIL_NUMBER;
        }
        return 0;
    }

    public function getDescription(): string
    {
        return $this->getHtml("#desc");
    }

    public function getVideos(): array
    {
        $videos = [];
        $this->filter(".reveal")->each(static function (ParserCrawler $c) use (&$videos) {
            $videos[] = [
                "name" => $c->attr("id"),
                "provider" => "youtube",
                "video" => $c->getAttr("iframe", "src")
            ];
        });
        return $videos;
    }

    public function getCostToUs(): float
    {
        return 0.1;
    }

    public function getProductFiles(): array
    {
        $files = [];
        $this->filter(".technical-docs a")->each(static function (ParserCrawler $c) use (&$files) {
            $files[] = [
                "name" => $c->text(),
                "link" => $c->attr("href")
            ];
        });
        return $files;
    }

    public function isGroup(): bool
    {
        return $this->exists(".product-options");
    }

    public function getChildProducts(FeedItem $parent_fi): array
    {
        $child = [];
        $this->filter(".product-options")->each(function (ParserCrawler $c) use (&$child, $parent_fi) {
            $fi = clone $parent_fi;

            $array = $this->parseFeatures($c->getContent(".product-options-description li"));

            $fi->setShortdescr($array["short_description"]);
            $fi->setAttributes($array["attributes"] !== [] ? $array["attributes"] : null);
            $fi->setDimX($array["dims"]["x"]);
            $fi->setDimY($array["dims"]["y"]);
            $fi->setDimZ($array["dims"]["z"]);
            $fi->setShippingWeight($array["shipping_weight"]);
            $fi->setWeight($array["weight"]);

            $fi->setProduct($c->getText("h3"));
            $fi->setMpn($parent_fi->getMpn()."-".$c->attr("data-mutate"));
            $fi->setImages([ $c->getAttr("img","src") ]);
            $fi->setRAvail($this->getAvail());

            $files = [];
            $c->filter(".product-options-buttons > p > a")->each(static function (ParserCrawler $pc) use (&$files) {
                $files[] = [
                    "name" => $pc->text(),
                    "link" => $pc->attr("href")
                ];
            });
            $fi->setProductFiles($files);

            $child[] = $fi;
        });

        return $child;
    }
}