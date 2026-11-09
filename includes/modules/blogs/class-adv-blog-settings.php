<?php
defined( 'ABSPATH' ) || exit;

class ADV_Blog_Settings {

    const OPTION_KEY = 'adv_blog_price';

    public static function get_price() {
        return (float) get_option( self::OPTION_KEY, 50.00 );
    }

    public static function save( $price ) {
        update_option( self::OPTION_KEY, floatval( $price ) );
    }
}
