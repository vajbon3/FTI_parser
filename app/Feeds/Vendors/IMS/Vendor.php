<?php

namespace App\Feeds\Vendors\IMS;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Processor\SitemapHttpProcessor;
use App\Feeds\Utils\Link;

class Vendor extends SitemapHttpProcessor
{

    protected const CHUNK_SIZE = 10;

    protected array $first = [ 'https://www.allegromedical.com/pub/sitemap/allegromedical/products.xml' ];

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