<?php

namespace App\Feeds\Vendors\FTI;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Processor\SitemapHttpProcessor;
use App\Feeds\Utils\Data;
use App\Feeds\Utils\Link;
use App\Helpers\StringHelper;

class Vendor extends SitemapHttpProcessor
{

    protected const CHUNK_SIZE = 1;
    protected const DELAY_S = 0.7;

    protected array $first = [ "https://www.firstteaminc.com/sitemap.xml" ];

    public array $custom_products = [
        "https://www.firstteaminc.com/soccer-equipment/portable-soccer-goals/golden-goal",
        "https://www.firstteaminc.com/basketball-equipment/backboards/playground-backboards/ft216"
    ];

    protected array $headers = [
        "Accept" => "*/*",
        "Host" => "firstteaminc.com"
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

    /*
    public function filterProductLinks( Link $link ): bool
    {
        // todo
    }

    public function isValidFeedItem( FeedItem $fi ): bool
    {
        // todo
    } */
}