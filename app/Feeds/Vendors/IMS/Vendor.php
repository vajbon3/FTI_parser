<?php

namespace App\Feeds\Vendors\IMS;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Processor\SitemapHttpProcessor;
use App\Feeds\Utils\Data;
use App\Feeds\Utils\Link;
use App\Helpers\StringHelper;

class Vendor extends SitemapHttpProcessor
{

    protected const CHUNK_SIZE = 10;

    protected array $first = [ 'https://www.allegromedical.com/pub/sitemap/allegromedical/products.xml' ];

    public array $custom_products = [
        "https://www.allegromedical.com/products/drive-cruiser-iii-w-removable-height-adj-desk-arms/",
        "https://www.allegromedical.com/products/triple-pull-elastic-belt/",
        "https://www.allegromedical.com/products/dbc-spring-singles-acupuncture-needles-box-of-100/",
        "https://www.allegromedical.com/products/trapeze-for-hospital-bed-chain-and-triangle-set-only/",
        "https://www.allegromedical.com/products/molicare-premium-elastic-8d-heavy-absorbency-brief/",
        "https://www.allegromedical.com/products/epillyss-sensor-depilatory-gel-lukewarm-wax-20oz/",
        "https://www.allegromedical.com/products/comfort-cool-thumb-cmc-restriction-splint/"
    ];

    public function filterProductLinks( Link $link ): bool
    {
        return str_contains($link->getUrl(),'/products/');
    }

    public function isValidFeedItem(FeedItem $fi ): bool
    {
        return ($fi->getProduct() !== "" && $fi->getProduct() !== 'Dummy') &&
            (($fi->mpn !== null && $fi->mpn !== '') || $fi->isGroup());
    }
}