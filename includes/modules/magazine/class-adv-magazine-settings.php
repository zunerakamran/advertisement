<?php
defined( 'ABSPATH' ) || exit;

class ADV_Magazine_Settings {

    const PRICES = [
        'full'    => [ 'label' => 'Full Page',    'option' => 'adv_mag_price_full',    'default' => 200.00 ],
        'half'    => [ 'label' => 'Half Page',    'option' => 'adv_mag_price_half',    'default' => 120.00 ],
        'quarter' => [ 'label' => 'Quarter Page', 'option' => 'adv_mag_price_quarter', 'default' =>  70.00 ],
    ];

    public static function get_price( $type ) {
        if ( ! isset( self::PRICES[ $type ] ) ) return 0;
        return (float) get_option( self::PRICES[ $type ]['option'], self::PRICES[ $type ]['default'] );
    }

    public static function get_label( $type ) {
        return self::PRICES[ $type ]['label'] ?? ucfirst( $type );
    }

    public static function all_prices() {
        $out = [];
        foreach ( self::PRICES as $key => $cfg ) {
            $out[ $key ] = [
                'label' => $cfg['label'],
                'price' => self::get_price( $key ),
            ];
        }
        return $out;
    }

    public static function save_all( $data ) {
        foreach ( self::PRICES as $key => $cfg ) {
            if ( isset( $data[ $key ] ) ) {
                update_option( $cfg['option'], floatval( $data[ $key ] ) );
            }
        }
    }
}