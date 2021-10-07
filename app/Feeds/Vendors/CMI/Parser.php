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

    private string $description;
    private array $dims = [
        'x' => null,
        'y' => null,
        'z' => null
    ];

    // помошники
    public function getNumbers(string $uc_matrix_string) : array {
        $matches = [];
        preg_match_all("/\d+/",$uc_matrix_string,$matches);
        return $matches;
    }

    public function beforeParse(): void
    {
        $this->description = $this->getHtml('#tbmore_info');

        // ишем стринг размеров
        $matches = [];
        if (preg_match("/\d*((\.\d+)|(\s+\d+\/\d+)*)(\"|')+[a-zA-z\s]*x[a-zA-z\s]*\d*((\.\d+)|(\s+\d+\/\d+)*)(\"|')+([a-zA-z\s]*x[a-zA-z\s]*\d*((\.\d+)|(\s+\d+\/\d+)*)(\"|')+)*/",$this->description,$matches)) {
            $this->dims = FeedHelper::getDimsInString($matches[0],'x');
        }
    }

    public function getProduct(): string
    {
        return preg_replace( '/[^a-z0-9\s]/i', '', $this->getText('h1.title'));
    }

    public function getMpn(): string
    {
        try {
            return trim(explode(':',$this->getText('h1.title > small'))[1]);
        } catch (\Exception) {
            return '';
        }
    }

    public function getShortDescription(): array
    {
        $features = [];

        $this->filter('.product-body div')->each(static function(ParserCrawler $c) use(&$features) {
            if(stripos($c->getText('span'), 'feature') !== false) {
               $features = $c->nextAll()->getContent('li');
           }
        });

        return $features;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getDimX(): ?float
    {
        return $this->dims['x'];
    }

    public function getDimY(): ?float
    {
        return $this->dims['y'];
    }

    public function getDimZ(): ?float
    {
        return $this->dims['z'];
    }

    public function getCategories(): array
    {
          return array_slice(array_values( array_unique($this->getContent('div.breadcrumb a'))),0,5);
    }

    public function getAvail(): ?int
    {
        return $this->exists('div.product-out-of-stock') ? 0 : self::DEFAULT_AVAIL_NUMBER;
    }

    public function getImages(): array
    {
        $exampleImage = $this->getAttr('.thumb-view a','href');
        return $exampleImage !== '' ? [$exampleImage] : [$this->getAttr('.main-product-image a','href')];
    }

    public function isGroup(): bool
    {
        return $this->getAvail() !== 0 && $this->getMpn() !== '';
    }

    public function getChildProducts(FeedItem $parent_fi): array
    {
        $child = [];

        // id продукта
        $nid = $this->getAttr("input[name='nid']",'value');

        // взять контеинер изображении
        $product_data = [];
        $links = ["blankstyle.com/magiczoom-thumbnails-lazyload/$nid"];
        foreach ($this->getVendor()->getDownloader()->fetch($links) as $data) {
            $matches = [];
            preg_match('/{"status".*}}/',$data,$matches);
            $json = json_decode($matches[0], true, 512, JSON_THROW_ON_ERROR);

        }
        $c = new ParserCrawler($json["data"]["content"]);

        // ключ атрибута
        $attribute_key = $this->getAttr('select.color-select-picker','name');

        // id цвет и имена
        $colors = $this->filter('select.color-select-picker option')->each(static fn(ParserCrawler $c) => ['id' => $c->attr('value'), 'name' => $c->text()]);

        // размеры
        $sizes = [];
        $this->filter('.input-qty-cell')->each(static function(ParserCrawler $c) use(&$sizes) {
            $sizes[$c->attr('data-oid')] = $c->closest('.size-field')->getText('.size-label');
        });

        foreach($colors as $color) {
            $params = [
                $attribute_key => $color['id'],
                'nid' => $nid,
                'qty' => 1,
            ];

            $links = [new Link("https://www.blankstyle.com/cart/add-to-cart-form/$nid",'POST',$params, 'form-data')];

            foreach ($this->getVendor()->getDownloader()->fetch($links) as $data) {
                $product_data = $data->getJSON();

                foreach($product_data as $size_id => $info) {
                    if($size_id === 'otherDetail') {
                        continue;
                    }

                    $fi = clone $parent_fi;

                    $color_name = $color['name'];
                    $color_id = $color['id'];
                    $size_name = $sizes[$size_id];

                    $fi->setProduct("Color: $color_name, Size: $size_name");
                    $fi->setMpn($this->getMpn() . '-' . $color_id . '-' . $size_id);
                    $fi->setCostToUs(StringHelper::getMoney($info['price']));
                    $fi->setListPrice(StringHelper::getMoney($info['MSRP']));
                    $fi->setRAvail((int)$info['inventory']);

                    // изображения
                    $fi->setImages($c->filter(".thumb-view a[data-oid='$color_id']")
                                ->each(static fn(ParserCrawler $c) => $c->attr('href')));

                    $child[] = $fi;
                }
            }
        }


        return $child;
    }
}