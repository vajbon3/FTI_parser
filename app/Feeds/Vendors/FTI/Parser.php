<?php

namespace App\Feeds\Vendors\FTI;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Parser\HtmlParser;
use App\Feeds\Utils\Data;
use App\Feeds\Utils\ParserCrawler;
use App\Helpers\FeedHelper;
use App\Helpers\StringHelper;

class Parser extends HtmlParser
{


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
            $matches = [];
            $lower_feature = strtolower($feature);
            if (preg_match("/\d*((\.\d+)|(\s+\d+\/\d+)*)(\"|')+[a-zA-z\s]*x[a-zA-z\s]*\d*((\.\d+)|(\s+\d+\/\d+)*)(\"|')+/",$lower_feature,$matches)) {
                $dims = FeedHelper::getDimsInString($matches[0],"x");
            }
            else if (str_contains($lower_feature, "weight") || str_contains($lower_feature, "wt")) {
                if (str_contains($lower_feature, "shipping")) {
                    $matches = [];
                    preg_match("/\d+/", $lower_feature,$matches);
                    $shipping_weight = $matches[0];
                    if(isset($matches[0])) {
                        $shipping_weight = $matches[0];
                    } else {
                        $short_desc[] = $feature;
                    }
                } else {
                    $matches = [];
                    preg_match("/\d+/", $lower_feature,$matches);
                    if(isset($matches[0])) {
                        $weight = $matches[0];
                    } else {
                        $short_desc[] = $feature;
                    }
                }
            }
            else if (str_contains($lower_feature, ":")) {
                $arr = explode(":", $lower_feature);
                $key = trim($arr[0]);
                $value = trim($arr[1]);

                if($value !== "") {
                    $attributes[$key] = $value;
                }
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
        return $this->filter(".store-product-thumb")->each(static fn(ParserCrawler $c) => $c->attr("href"));
    }

    public function getCategories(): array
    {
        return $this->getContent(".breadcrumb-trail .breadcrumb-trail-sub a");
    }

    public function getAvail(): ?int
    {
        $avail = 0;
        if($this->getText(".in-stock") === "In Stock" || str_contains($this->getAttr(".store-product-description p > img","alt"),"Stock")) {
            $avail = self::DEFAULT_AVAIL_NUMBER;
        }

        return $avail;
    }

    public function getDescription(): string
    {
        $description = "";
        // если описание в низу сушествует
        if($this->exists("#desc")) {
            $description = $this->getHtml("#desc");
        }

        // поискать описание в features
        else {
            $this->filter(".store-product-description > *:nth-child(2)")->each(static function(ParserCrawler $c) use(&$description){
            if($c->nodeName() === "p") {
                $description = $c->outerHtml();
            }
        });
        }


        // если описание дублировка short_desc, нужно убрать.
        if(str_contains($description,"Features")) {
            $description = "";
        }

        // если описание пустое, ставить в место него название дочернего.
        if(($description === "") && $this->exists(".product-options h3")) {
            $description = $this->getText(".product-options h3");
        }

        return $description;
    }

    public function getVideos(): array
    {
        $videos = [];
        $this->filter(".reveal")->each(function (ParserCrawler $c) use (&$videos) {
            $id = $c->attr("id");
            $name = $this->filter("a[data-open='$id']")->previousAll("h3")->text();
            $videos[] = [
                "name" => $name,
                "provider" => "youtube",
                "video" => $c->getAttr("iframe", "src")
            ];
        });
        return $videos;
    }

    public function getProductFiles(): array
    {
        $files = [];
        $this->filter(".technical-docs a")->each(static function (ParserCrawler $c) use (&$files) {
            # если не валидная ссылка, не добавлять
            if($c->attr("href")[0] === "#") {
                return;
            }
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
        $i = 0;
        $this->filter("#package-options + .row-wrapper > *")->each(function (ParserCrawler $c) use (&$child, $parent_fi,&$i) {

            $fi = clone $parent_fi;
            $i++;
            $array = $this->parseFeatures($c->getContent(".product-options-description li"));

            $fi->setShortdescr(array_merge($this->getShortDescription(),$array["short_description"]));
            $fi->setAttributes($array["attributes"] !== [] ? $array["attributes"] : null);
            $fi->setDimX($array["dims"]["x"] ?: $this->getDimX());
            $fi->setDimY($array["dims"]["y"] ?: $this->getDimY());
            $fi->setDimZ($array["dims"]["z"] ?: $this->getDimZ());
            $fi->setShippingWeight($array["shipping_weight"]);
            $fi->setWeight($array["weight"]);

            $fi->setProduct($c->getText("h3"));
            $fi->setMpn($parent_fi->getMpn()."-".$i);
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