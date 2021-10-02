<?php

namespace App\Feeds\Vendors\CMI;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Processor\SitemapHttpProcessor;
use App\Feeds\Utils\Data;
use App\Feeds\Utils\Link;
use App\Helpers\StringHelper;

class Vendor extends SitemapHttpProcessor
{

    protected const CHUNK_SIZE = 30;

    protected array $first = [ "https://www.blankstyle.com/sitemap.xml" ];

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
        $url = explode(".com/",$link->getUrl())[1];

        if(str_contains($url,"article")) {
            return false;
        }

        if(strlen($url) > 10 || preg_match("/\d+/",$url)) {
            return true;
        }
        print($url.PHP_EOL);
        return false;
    }

    public function isValidFeedItem(FeedItem $fi ): bool
    {
        return !($fi->getMpn() === "" && !$fi->isGroup());
    }
}