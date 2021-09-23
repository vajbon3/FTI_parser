<?php

namespace App\Feeds\Vendors\VNT;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Processor\SitemapHttpProcessor;
use App\Feeds\Utils\Data;
use App\Feeds\Utils\Link;
use App\Helpers\StringHelper;

class Vendor extends SitemapHttpProcessor
{
    protected const CHUNK_SIZE = 1;
    protected const DELAY_S = 0.7;

    protected array $first = [ "https://vanatisanes.com/product-sitemap.xml" ];

    protected array $headers = [
        "Accept" => "*/*",
        "Host" => "vanatisanes.com"
    ];

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
        return str_contains( $link->getUrl(), "product" );
    }

    public function isValidFeedItem( FeedItem $fi ): bool
    {
        return !in_array( 'Bundles', $fi->getCategories(), true ) && !in_array( 'Gifts', $fi->getCategories(), true );
    }
}