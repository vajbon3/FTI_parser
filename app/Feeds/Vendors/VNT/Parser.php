<?php

namespace App\Feeds\Vendors\VNT;

use App\Feeds\Feed\FeedItem;
use App\Feeds\Parser\HtmlParser;
use App\Feeds\Utils\Data;
use App\Feeds\Utils\ParserCrawler;
use App\Helpers\StringHelper;

class Parser extends HtmlParser
{
    private array $variations;

    public function parseContent( Data $data, array $params = [] ): array
    {
        for ( $i = 0; $i < 5; $i++ ) {
            if ( !StringHelper::isNotEmpty( $data->getData() ) ) {
                $data = $this->getVendor()->getDownloader()->get( $params[ "url" ] );
            }
            else {
                break;
            }
        }

        if ( !StringHelper::isNotEmpty( $data->getData() ) ) {
            return [];
        }

        return parent::parseContent( $data, $params );
    }

    public function beforeParse(): void
    {
        $json = html_entity_decode( $this->getAttr( ".variations_form", "data-product_variations" ) );
        $this->variations = json_decode( $json, true, 512, JSON_THROW_ON_ERROR );
    }

    public function getProduct(): string
    {
        return $this->getText( '.product_title' );
    }

    public function getMpn(): string
    {
        return $this->getText( ".sku" );
    }

    public function getDescription(): string
    {
        return $this->getHtml( ".woocommerce-product-details__short-description" );
    }

    public function getImages(): array
    {
        return $this->getLinks( '.woocommerce-product-gallery__image a' );
    }

    public function getCategories(): array
    {
        return $this->getContent( '.posted_in a' );
    }

    public function isGroup(): bool
    {
        return true;
    }

    public function getAttributes(): ?array
    {
        $arr = [];
        $this->filter( ".woocommerce-product-attributes-item" )->each( function ( ParserCrawler $c ) use ( &$arr ) {
            $attr_name = $c->getText( ".woocommerce-product-attributes-item__label" );
            $attr_value = $c->getText( ".woocommerce-product-attributes-item__value" );

            $arr[ $attr_name ] = $attr_value;
        } );

        return $arr ?: null;
    }

    public function getChildProducts( FeedItem $parent_fi ): array
    {
        $child = [];
        if ( !$this->getVendor()->isValidFeedItem( $parent_fi ) ) {
            return $child;
        }

        foreach ( $this->variations as $variation ) {
            $fi = clone $parent_fi;

            $label = $this->getText( ".label > label" ) ?? "Size";
            $label_for = $this->getAttr( ".label > label", "for" ) ?? "pa_size";
            $attribute_key = "attribute_" . $label_for;
            $fi->setProduct( ucfirst( $label . ": " . trim( $this->getText( '[value="' . $variation[ "attributes" ][ $attribute_key ] . '"]' ), '$' ) ) );
            $fi->setMpn( $variation[ "sku" ] . "-" . $variation[ "variation_id" ] );
            $fi->setCostToUs( StringHelper::getMoney( $variation[ "display_price" ] ) );
            $fi->setListPrice( StringHelper::getMoney( $variation[ "display_regular_price" ] ) );
            $fi->setRAvail( $variation[ "is_in_stock" ] ? self::DEFAULT_AVAIL_NUMBER : 0 );
            $child[] = $fi;
        }

        return $child;
    }
}