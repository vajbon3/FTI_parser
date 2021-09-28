<?php

namespace App\Feeds\Vendors\FTI;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Processor\SitemapHttpProcessor;
use App\Feeds\Utils\Data;
use App\Feeds\Utils\Link;
use App\Helpers\StringHelper;

class Vendor extends SitemapHttpProcessor
{

    protected const CHUNK_SIZE = 10;

    protected array $first = [ "https://www.firstteaminc.com/sitemap.xml" ];

    public function getProductsLinks( Data $data, string $url ): array
    {
        for ( $i = 0; $i < 5; $i++ ) {
            if ( !StringHelper::isNotEmpty( $data->getData() ) ) {
                $data = $this->getDownloader()->get( $url );
            }
            else {
                break;
            }
        }

        return parent::getProductsLinks( $data, $url );
    }

    public function filterProductLinks( Link $link ): bool
    {
        $url = $link->getUrl();
        return str_contains($url,"equipment/") || str_contains($url,"seating/")
            || str_contains($url,"other-sports/");
    }

    public function isValidFeedItem(FeedItem $fi ): bool
    {
        return ($fi->getProduct() !== "" && $fi->getProduct() !== "Dummy") &&
            (($fi->mpn !== null && $fi->mpn !== "") || $fi->isGroup());
    }
}