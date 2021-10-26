<?php

namespace App\Feeds\Vendors\IMS;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Parser\HtmlParser;
use App\Feeds\Utils\ParserCrawler;
use App\Helpers\StringHelper;

class Parser extends HtmlParser
{
    private string $description = '';
    private array $short_desc = [];
    private bool $is_group = false;
    private array $children = [];
    private array $images = [];
    private array $categories = [];

    public function beforeParse(): void
    {
        $this->description = $this->getHtml('div.description div.value');
        $this->short_desc = $this->getContent("div[id*='bulletdescription'] li");

        // удалить features из описания и сунуть в short_desc если есть
        if (preg_match('/<strong><p>features.*<ul>.*<\/ul>/isU', $this->description)) {
            $matches = [];
            preg_match('/<strong><p>features.*<ul>.*<\/ul>/isU', $this->description, $matches);

            $c = new ParserCrawler($matches[0]);
            // объеденить short_desc с наидёнными features
            $this->short_desc = array_merge($this->short_desc,$c->getContent('li'));

            // удалить features из описания
            $this->description = preg_replace('/<strong><p>features.*<ul>.*<\/ul>/isU', '', $this->description);
        }

        // парсирование json data с саита
        $child_matches = [];
        $gallery_matches = [];
        $breadcrumbs_matches = [];
        $this->filter("script[type='text/x-magento-init']")->each(static function (ParserCrawler $c) use (&$child_matches, &$gallery_matches,&$breadcrumbs_matches) {
            if (str_contains($c->html(), 'jsonConfig')) {
                preg_match('/{.*}/s', $c->html(), $child_matches);
            } else if (str_contains($c->html(), 'Xumulus_FastGalleryLoad/js/gallery/custom_gallery')) {
                preg_match('/{.*}/s', $c->html(), $gallery_matches);
            } else if(str_contains($c->html(), 'breadcrumbs')) {
                preg_match('/{.*}/s', $c->html(), $breadcrumbs_matches);
            }
        });

        // если json config сушествует, у продукта есть дочерные
        if (isset($child_matches[0])) {
            $data = json_decode($child_matches[0], true, 512, JSON_THROW_ON_ERROR);
            $this->is_group = true;

            $data = $data['[data-role=swatch-options]']['Magento_Swatches/js/swatch-renderer']['jsonConfig'];

            // имена
            foreach ($data['names'] as $id => $name) {
                $this->children[$id] = [];
                $this->children[$id]['product'] = $name;
            }

            // sku
            foreach ($data['skus'] as $id => $sku) {
                $this->children[$id]['sku'] = $sku;
            }

            // изображения
            foreach ($data['images'] as $id => $image_array) {
                $this->children[$id]['images'] = [];
                foreach ($image_array as $image) {
                    if ($image['type'] === 'image') {
                        $this->children[$id]['images'][] = $image['full'];
                    }
                }
            }

            // цены
            foreach ($data['optionPrices'] as $id => $prices) {
                $this->children[$id]['prices']['cost_to_us'] = $prices['finalPrice']['amount'];
                $this->children[$id]['prices']['msrp'] = $prices['basePrice']['amount'];
            }

            // stock
            foreach($data['stock_status'] as $id => $status) {
                $this->children[$id]['avail'] = stripos($status,"out of stock") === false ? self::DEFAULT_AVAIL_NUMBER : 0;
            }
        }

        // изображения для основного
        if(isset($gallery_matches[0])) {
            $data = json_decode($gallery_matches[0], true, 512, JSON_THROW_ON_ERROR);

            $data = $data['[data-gallery-role=gallery-placeholder]']['Xumulus_FastGalleryLoad/js/gallery/custom_gallery']['data'];

            foreach($data as $image) {
                $this->images[] = $image['full'];
            }
        }

        // категории
        if(isset($breadcrumbs_matches[0])) {
            $data = json_decode($breadcrumbs_matches[0], true, 512, JSON_THROW_ON_ERROR);

            $data = $data['.breadcrumbs']['breadcrumbs']['categoriesConfig']['default'] ?? [];

            foreach($data as $url) {
                $breadcrumbs_matches = [];
                preg_match('/>(.+)</s',$url,$breadcrumbs_matches);
                $this->categories[] = $breadcrumbs_matches[1];
                $breadcrumbs_matches = [];
            }
        }

    }

    public function getProduct(): string
    {
        return $this->getText('.page-title');
    }

    public function getMpn(): string
    {
        return $this->getText('.sku div.value');
    }

    public function getBrand(): ?string
    {
        return $this->getText('.amshopby-option-link a');
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getShortDescription(): array
    {
        return $this->short_desc;
    }

    public function getCostToUs(): float
    {
        return StringHelper::getMoney($this->getText("span[id*='product-price'] .price"));
    }

    public function getImages(): array
    {
        return $this->images;
    }

    public function getVideos(): array
    {
        $videos = [];
        $this->filter('div.description iframe')->each(function (ParserCrawler $c) use (&$videos) {
            $videos[] = [
                'name' => $this->getProduct(),
                'provider' => 'youtube',
                'video' => $c->attr('src')
            ];
        });
        return $videos;
    }

    public function getProductFiles(): array
    {
        return $this->filter('a.am-filelink')->each(static fn(ParserCrawler $c) => ['name' => $c->text(), "link" => $c->attr('href')]);
    }

    public function getCategories(): array
    {
        return $this->categories;
    }

    public function getAvail(): ?int
    {
        return (stripos($this->getText('.availability_message'),'out of stock') === false) ? self::DEFAULT_AVAIL_NUMBER : 0;
    }

    public function isGroup(): bool
    {
        return $this->is_group;
    }

    public function getChildProducts(FeedItem $parent_fi): array
    {
        $child = [];

        foreach($this->children as $id => $child_info) {
            if($id === 'default') {
                continue;
            }

            $child_fi = clone $parent_fi;
            $child_fi->setProduct($child_info['product']);
            $child_fi->setMpn($child_info['sku']);
            $child_fi->setImages($child_info['images'] ?? $parent_fi->getImages());
            $child_fi->setCostToUs($child_info['prices']['cost_to_us']);
            // если listPrice > costToUs ставить
            if($child_info['prices']['msrp'] > $child_fi->getCostToUs()) {
                $child_fi->setListPrice($child_info['prices']['msrp']);
            }
            $child_fi->setRAvail($child_info['avail'] ?? self::DEFAULT_AVAIL_NUMBER);
            $child[] = $child_fi;
        }

        return $child;
    }
}