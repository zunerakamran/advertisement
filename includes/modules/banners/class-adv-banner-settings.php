<?php
defined( 'ABSPATH' ) || exit;

class ADV_Banner_Settings {

    const BANNERS = [
        'large'  => [
            'label'          => 'Large Banner',
            'option'         => 'adv_banner_price_large',
            'default'        => 75.00,
            'option_width'   => 'adv_banner_width_large',
            'default_width'  => 1200,
            'option_height'  => 'adv_banner_height_large',
            'default_height' => 400,
            'option_slots'   => 'adv_banner_slots_large',
            'default_slots'  => 1,
        ],
        'medium' => [
            'label'          => 'Medium Banner',
            'option'         => 'adv_banner_price_medium',
            'default'        => 50.00,
            'option_width'   => 'adv_banner_width_medium',
            'default_width'  => 800,
            'option_height'  => 'adv_banner_height_medium',
            'default_height' => 300,
            'option_slots'   => 'adv_banner_slots_medium',
            'default_slots'  => 1,
        ],
        'small'  => [
            'label'          => 'Small Banner',
            'option'         => 'adv_banner_price_small',
            'default'        => 30.00,
            'option_width'   => 'adv_banner_width_small',
            'default_width'  => 400,
            'option_height'  => 'adv_banner_height_small',
            'default_height' => 250,
            'option_slots'   => 'adv_banner_slots_small',
            'default_slots'  => 1,
        ],
    ];

    // Backward compatibility alias for PRICES
    const PRICES = self::BANNERS;

    public static function get_price( $size ) {
        if ( ! isset( self::BANNERS[ $size ] ) ) return 0;
        return (float) get_option( self::BANNERS[ $size ]['option'], self::BANNERS[ $size ]['default'] );
    }

    public static function get_width( $size ) {
        if ( ! isset( self::BANNERS[ $size ] ) ) return 0;
        return (int) get_option( self::BANNERS[ $size ]['option_width'], self::BANNERS[ $size ]['default_width'] );
    }

    public static function get_height( $size ) {
        if ( ! isset( self::BANNERS[ $size ] ) ) return 0;
        return (int) get_option( self::BANNERS[ $size ]['option_height'], self::BANNERS[ $size ]['default_height'] );
    }

    public static function get_slots( $size ) {
        if ( ! isset( self::BANNERS[ $size ] ) ) return 1;
        return (int) get_option( self::BANNERS[ $size ]['option_slots'], self::BANNERS[ $size ]['default_slots'] );
    }

    public static function get_dimensions( $size ) {
        return [
            'width'  => self::get_width( $size ),
            'height' => self::get_height( $size ),
        ];
    }

    public static function get_label( $size ) {
        return self::BANNERS[ $size ]['label'] ?? ucfirst( $size );
    }

    public static function all_prices() {
        $out = [];
        foreach ( self::BANNERS as $key => $cfg ) {
            $out[ $key ] = [
                'label'  => $cfg['label'],
                'price'  => self::get_price( $key ),
                'width'  => self::get_width( $key ),
                'height' => self::get_height( $key ),
            ];
        }
        return $out;
    }

    public static function save_all( $data ) {
        foreach ( self::BANNERS as $key => $cfg ) {
            if ( isset( $data[ $key ] ) ) {
                update_option( $cfg['option'], floatval( $data[ $key ] ) );
            }
            if ( isset( $data['width_' . $key] ) ) {
                update_option( $cfg['option_width'], intval( $data['width_' . $key] ) );
            }
            if ( isset( $data['height_' . $key] ) ) {
                update_option( $cfg['option_height'], intval( $data['height_' . $key] ) );
            }
            if ( isset( $data['slots_' . $key] ) ) {
                update_option( $cfg['option_slots'], intval( $data['slots_' . $key] ) );
            }
        }
    }
}