<?php

namespace App\Feeds\Vendors\CMI;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Parser\HtmlParser;
use App\Feeds\Utils\Data;
use App\Feeds\Utils\Link;
use App\Feeds\Utils\ParserCrawler;
use App\Helpers\FeedHelper;
use App\Helpers\StringHelper;

class Parser extends HtmlParser
{

    private array $dims = [
        "x" => null,
        "y" => null,
        "z" => null
    ];

    // помошники
    public function getNumbers(string $uc_matrix_string) : array {
        $matches = [];
        preg_match_all("/\d+/",$uc_matrix_string,$matches);
        return $matches;
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
        $name = $this->getText("h1.title");
        if($name !== "") {
            return preg_replace( '/[^a-z0-9\s]/i', '', $name);
        }
        return "";
    }

    public function getMpn(): string
    {
        try {
            return trim(explode(":",$this->getText("h1.title > small"))[1]);
        } catch (\Exception $exception) {
            return "";
        }
    }

    public function getShortDescription(): array
    {
        return $this->getContent(".product-body li");
    }

    public function getDescription(): string
    {
        // если в место features описание, прибовляем
        $description = $this->getHtml(".product-body p");

        $description .= $this->getHtml("#tbmore_info");

        // ишем стринг размеров
        $matches = [];
        if (preg_match("/\d*((\.\d+)|(\s+\d+\/\d+)*)(\"|')+[a-zA-z\s]*x[a-zA-z\s]*\d*((\.\d+)|(\s+\d+\/\d+)*)(\"|')+([a-zA-z\s]*x[a-zA-z\s]*\d*((\.\d+)|(\s+\d+\/\d+)*)(\"|')+)*/",$description,$matches)) {
            $this->dims = FeedHelper::getDimsInString($matches[0],"x");
        }

        return $description;
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

    public function getCategories(): array
    {
        $categories = $this->filter("div.breadcrumb a")->each(static fn(ParserCrawler $c) => $c->text());
        return array_slice($categories,0,count($categories)/2);
    }

    public function getAvail(): ?int
    {
        return $this->exists("div.product-out-of-stock") ? 0 : self::DEFAULT_AVAIL_NUMBER;
    }

    public function getImages(): array
    {
        return [$this->getAttr(".thumb-view a","href")];
    }

    public function isGroup(): bool
    {
        return $this->getAvail() !== 0 && $this->getMpn() !== "";
    }

    public function getChildProducts(FeedItem $parent_fi): array
    {
        $child = [];

        // id продукта
        $nid = $this->getAttr("input[name='nid']","value");

        // ключ атрибута
        $attribute_key = $this->getAttr("select.color-select-picker","name");

        // id цвет
        $colors = $this->filter("select.color-select-picker option")->each(static fn(ParserCrawler $c) => $c->attr("value"));

        foreach($colors as $color) {
            $params = [
                $attribute_key => $color,
                "nid" => $nid,
                "qty" => 1,
            ];

            $links = [new Link("https://www.blankstyle.com/cart/add-to-cart-form/$nid","POST",$params, "form-data")];

            foreach ($this->getVendor()->getDownloader()->fetch($links) as $data) {
                $product_data = $data->getJSON();

                foreach($product_data as $size_id => $info) {
                    if($size_id === "otherDetail") {
                        continue;
                    }

                    $fi = clone $parent_fi;
                    $fi->setMpn($parent_fi->getMpn() . "-" . $color . "-" . $size_id);
                    $fi->setCostToUs(StringHelper::getMoney($info["price"]));
                    $fi->setListPrice(StringHelper::getMoney($info["MSRP"]));
                    $fi->setRAvail((int)$info["inventory"]);
                    $fi->setAttributes(["prime" => $info["prime"], "finalSale" => $info["finalSale"]]);

                    // изображения
                    $fi->setImages($this->filter(".thumb-view a[data-oid='$color']")
                                ->each(static fn(ParserCrawler $c) => $c->attr("href")));

                    $child[] = $fi;
                }
            }
        }


        return $child;
    }
}