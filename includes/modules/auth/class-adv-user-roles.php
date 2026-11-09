<?php
defined( 'ABSPATH' ) || exit;

class ADV_User_Roles {

    public static function register() {
        add_role( 'advertiser', 'Advertiser', [
            'read' => true,
        ]);
    }

    public static function remove() {
        remove_role( 'advertiser' );
    }
}