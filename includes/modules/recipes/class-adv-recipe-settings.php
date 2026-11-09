<?php
defined( 'ABSPATH' ) || exit;

class ADV_Recipe_Settings {

    const OPTION_KEY = 'adv_recipe_price';

    public static function get_price() {
        return (float) get_option( self::OPTION_KEY, 35.00 );
    }

    public static function save( $price ) {
        update_option( self::OPTION_KEY, floatval( $price ) );
    }
}