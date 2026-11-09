<?php
defined( 'ABSPATH' ) || exit;

class ADV_Stripe_Settings {

    public function __construct() {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function register_settings() {
        register_setting( 'adv_stripe_settings', 'adv_stripe_secret_key',      [ 'sanitize_callback' => 'sanitize_text_field' ] );
        register_setting( 'adv_stripe_settings', 'adv_stripe_publishable_key', [ 'sanitize_callback' => 'sanitize_text_field' ] );
        register_setting( 'adv_stripe_settings', 'adv_stripe_mode',            [ 'sanitize_callback' => 'sanitize_text_field' ] );
    }

    public static function get( $key ) {
        return get_option( 'adv_stripe_' . $key, '' );
    }

    public static function is_live_mode() {
        return get_option( 'adv_stripe_mode', 'test' ) === 'live';
    }

    public static function is_configured() {
        return ! empty( self::get( 'secret_key' ) ) && ! empty( self::get( 'publishable_key' ) );
    }
}