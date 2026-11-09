<?php
defined( 'ABSPATH' ) || exit;

class ADV_Listing_Admin {

    public static $price_ranges = [
        'cheap'     => 'Cheap (£)',
        'economy'   => 'Economy (££)',
        'moderate'  => 'Moderate (£££)',
        'ultrahigh' => 'Ultra High (££££)',
    ];

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_init', [ $this, 'handle_submit' ] );
        add_action( 'admin_init', [ $this, 'handle_edit_submit' ] );
    }

    public function register_menu() {
        // Menu items removed as requested
    }

    public function render_page() {
        wp_enqueue_style( 'adv-listing-form', ADV_URL . 'public/assets/listing-form.css', [], ADV_VERSION );
        wp_enqueue_style( 'adv-forms-theme', ADV_URL . 'public/assets/adv-forms-theme.css', [ 'adv-listing-form' ], ADV_VERSION );

        $error   = adv_get_flash( 'admin_listing_error' );
        $success = adv_get_flash( 'admin_listing_success' );
        $flashed_old = adv_get_flash( 'admin_listing_old' );

        if ( $flashed_old ) {
            $old = $flashed_old;
        } else {
            $old = [];
        }

        $categories = ADV_Categories::get_all();
        $users = get_users();

        include ADV_PATH . 'admin/views/add-listing.php';
    }

    public function render_edit_page() {
        wp_enqueue_style( 'adv-listing-form', ADV_URL . 'public/assets/listing-form.css', [], ADV_VERSION );
        wp_enqueue_style( 'adv-forms-theme', ADV_URL . 'public/assets/adv-forms-theme.css', [ 'adv-listing-form' ], ADV_VERSION );

        $listing_id = isset( $_GET['listing_id'] ) ? intval( $_GET['listing_id'] ) : 0;

        if ( ! $listing_id ) {
            echo '<div class="wrap"><h1>Edit Listing</h1><div class="notice notice-error"><p>No listing ID specified.</p></div></div>';
            return;
        }

        $listing = get_post( $listing_id );

        if ( ! $listing || $listing->post_type !== 'adv_listing' ) {
            echo '<div class="wrap"><h1>Edit Listing</h1><div class="notice notice-error"><p>Listing not found.</p></div></div>';
            return;
        }

        $error   = adv_get_flash( 'admin_listing_error' );
        $success = adv_get_flash( 'admin_listing_success' );
        $flashed_old = adv_get_flash( 'admin_listing_old' );

        if ( $flashed_old ) {
            $old = $flashed_old;
        } else {
            $old = [
                'listing_name'        => $listing->post_title,
                'listing_desc'        => $listing->post_content,
                'listing_category'    => wp_get_post_terms( $listing_id, 'adv_category', [ 'fields' => 'ids' ] )[0] ?? 0,
                'listing_author'      => $listing->post_author,
                'listing_features'    => get_post_meta( $listing_id, 'adv_features', true ),
                'listing_phone'       => get_post_meta( $listing_id, 'adv_phone', true ),
                'listing_email'       => get_post_meta( $listing_id, 'adv_email', true ),
                'listing_whatsapp'    => get_post_meta( $listing_id, 'adv_whatsapp', true ),
                'listing_facebook'    => get_post_meta( $listing_id, 'adv_facebook', true ),
                'listing_instagram'   => get_post_meta( $listing_id, 'adv_instagram', true ),
                'listing_linkedin'    => get_post_meta( $listing_id, 'adv_linkedin', true ),
                'listing_pricing_type'=> get_post_meta( $listing_id, 'adv_pricing_type', true ),
                'listing_price_custom'=> get_post_meta( $listing_id, 'adv_price_custom', true ),
                'listing_price_range' => get_post_meta( $listing_id, 'adv_price_range', true ),
                'listing_status'      => $listing->post_status,
            ];
        }

        $categories = ADV_Categories::get_all();
        $users = get_users();

        include ADV_PATH . 'admin/views/edit-listing.php';
    }

    public function handle_submit() {
        if ( ! isset( $_POST['adv_admin_listing_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['adv_admin_listing_nonce'], 'adv_admin_add_listing' ) ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;

        // Collect fields
        $title        = sanitize_text_field( $_POST['listing_name']        ?? '' );
        $description  = sanitize_textarea_field( $_POST['listing_desc']    ?? '' );
        $features     = sanitize_textarea_field( $_POST['listing_features']?? '' );
        $phone        = sanitize_text_field( $_POST['listing_phone']       ?? '' );
        $email        = sanitize_email( $_POST['listing_email']            ?? '' );
        $whatsapp     = sanitize_text_field( $_POST['listing_whatsapp']    ?? '' );
        $facebook     = esc_url_raw( $_POST['listing_facebook']            ?? '' );
        $instagram    = esc_url_raw( $_POST['listing_instagram']           ?? '' );
        $linkedin     = esc_url_raw( $_POST['listing_linkedin']            ?? '' );
        $category_id  = intval( $_POST['listing_category']                 ?? 0 );
        $author_id    = intval( $_POST['listing_author']                   ?? get_current_user_id() );
        $pricing_type = sanitize_text_field( $_POST['listing_pricing_type']?? 'custom' );
        $price_custom = sanitize_text_field( $_POST['listing_price_custom']?? '' );
        $price_range  = sanitize_text_field( $_POST['listing_price_range'] ?? '' );
        $post_status  = sanitize_text_field( $_POST['listing_status']      ?? 'publish' );

        // Validation
        if ( empty( $title ) ) {
            adv_set_flash( 'admin_listing_error', 'Business name is required.' );
            adv_set_flash( 'admin_listing_old', $_POST );
            return;
        }

        if ( empty( $description ) ) {
            adv_set_flash( 'admin_listing_error', 'Description is required.' );
            adv_set_flash( 'admin_listing_old', $_POST );
            return;
        }

        if ( empty( $phone ) && empty( $email ) ) {
            adv_set_flash( 'admin_listing_error', 'Please provide at least a phone number or email.' );
            adv_set_flash( 'admin_listing_old', $_POST );
            return;
        }

        // Create the listing post
        $post_id = wp_insert_post([
            'post_title'   => $title,
            'post_content' => $description,
            'post_status'  => $post_status,
            'post_type'    => 'adv_listing',
            'post_author'  => $author_id,
        ]);

        if ( is_wp_error( $post_id ) ) {
            adv_set_flash( 'admin_listing_error', 'Something went wrong. Please try again.' );
            return;
        }

        // Assign category
        if ( $category_id ) {
            wp_set_post_terms( $post_id, [ $category_id ], 'adv_category' );
        }

        // Save all meta fields
        $meta_fields = [
            'adv_features'     => $features,
            'adv_phone'        => $phone,
            'adv_email'        => $email,
            'adv_whatsapp'     => $whatsapp,
            'adv_facebook'     => $facebook,
            'adv_instagram'    => $instagram,
            'adv_linkedin'     => $linkedin,
            'adv_pricing_type' => $pricing_type,
            'adv_price_custom' => $price_custom,
            'adv_price_range'  => $price_range,
        ];

        foreach ( $meta_fields as $key => $value ) {
            update_post_meta( $post_id, $key, $value );
        }

        // Handle logo upload
        if ( ! empty( $_FILES['listing_logo']['name'] ) ) {
            $logo_id = $this->handle_upload( $_FILES['listing_logo'], $post_id );
            if ( $logo_id ) {
                set_post_thumbnail( $post_id, $logo_id );
            }
        }

        adv_set_flash( 'admin_listing_success', 'Listing created successfully!' );
        wp_safe_redirect( admin_url( 'admin.php?page=adv-add-listing' ) );
        exit;
    }

    public function handle_edit_submit() {
        if ( ! isset( $_POST['adv_admin_listing_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['adv_admin_listing_nonce'], 'adv_admin_edit_listing' ) ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;

        $listing_id = intval( $_POST['listing_id'] ?? 0 );

        if ( ! $listing_id ) {
            adv_set_flash( 'admin_listing_error', 'Invalid listing ID.' );
            return;
        }

        $listing = get_post( $listing_id );

        if ( ! $listing || $listing->post_type !== 'adv_listing' ) {
            adv_set_flash( 'admin_listing_error', 'Listing not found.' );
            return;
        }

        // Collect fields
        $title        = sanitize_text_field( $_POST['listing_name']        ?? '' );
        $description  = sanitize_textarea_field( $_POST['listing_desc']    ?? '' );
        $features     = sanitize_textarea_field( $_POST['listing_features']?? '' );
        $phone        = sanitize_text_field( $_POST['listing_phone']       ?? '' );
        $email        = sanitize_email( $_POST['listing_email']            ?? '' );
        $whatsapp     = sanitize_text_field( $_POST['listing_whatsapp']    ?? '' );
        $facebook     = esc_url_raw( $_POST['listing_facebook']            ?? '' );
        $instagram    = esc_url_raw( $_POST['listing_instagram']           ?? '' );
        $linkedin     = esc_url_raw( $_POST['listing_linkedin']            ?? '' );
        $category_id  = intval( $_POST['listing_category']                 ?? 0 );
        $author_id    = intval( $_POST['listing_author']                   ?? get_current_user_id() );
        $pricing_type = sanitize_text_field( $_POST['listing_pricing_type']?? 'custom' );
        $price_custom = sanitize_text_field( $_POST['listing_price_custom']?? '' );
        $price_range  = sanitize_text_field( $_POST['listing_price_range'] ?? '' );
        $post_status  = sanitize_text_field( $_POST['listing_status']      ?? 'publish' );

        // Validation
        if ( empty( $title ) ) {
            adv_set_flash( 'admin_listing_error', 'Business name is required.' );
            adv_set_flash( 'admin_listing_old', $_POST );
            return;
        }

        if ( empty( $description ) ) {
            adv_set_flash( 'admin_listing_error', 'Description is required.' );
            adv_set_flash( 'admin_listing_old', $_POST );
            return;
        }

        if ( empty( $phone ) && empty( $email ) ) {
            adv_set_flash( 'admin_listing_error', 'Please provide at least a phone number or email.' );
            adv_set_flash( 'admin_listing_old', $_POST );
            return;
        }

        // Update the listing post
        $updated = wp_update_post([
            'ID'           => $listing_id,
            'post_title'   => $title,
            'post_content' => $description,
            'post_status'  => $post_status,
            'post_author'  => $author_id,
        ]);

        if ( is_wp_error( $updated ) ) {
            adv_set_flash( 'admin_listing_error', 'Something went wrong. Please try again.' );
            return;
        }

        // Assign category
        if ( $category_id ) {
            wp_set_post_terms( $listing_id, [ $category_id ], 'adv_category' );
        }

        // Save all meta fields
        $meta_fields = [
            'adv_features'     => $features,
            'adv_phone'        => $phone,
            'adv_email'        => $email,
            'adv_whatsapp'     => $whatsapp,
            'adv_facebook'     => $facebook,
            'adv_instagram'    => $instagram,
            'adv_linkedin'     => $linkedin,
            'adv_pricing_type' => $pricing_type,
            'adv_price_custom' => $price_custom,
            'adv_price_range'  => $price_range,
        ];

        foreach ( $meta_fields as $key => $value ) {
            update_post_meta( $listing_id, $key, $value );
        }

        // Handle logo upload
        if ( ! empty( $_FILES['listing_logo']['name'] ) ) {
            $logo_id = $this->handle_upload( $_FILES['listing_logo'], $listing_id );
            if ( $logo_id ) {
                set_post_thumbnail( $listing_id, $logo_id );
            }
        }

        adv_set_flash( 'admin_listing_success', 'Listing updated successfully!' );
        wp_safe_redirect( admin_url( 'admin.php?page=adv-edit-listing&listing_id=' . $listing_id ) );
        exit;
    }

    private function handle_upload( $file, $post_id ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $allowed = [ 'image/jpeg', 'image/png', 'image/webp' ];
        if ( ! in_array( $file['type'], $allowed ) ) return false;

        $upload = media_handle_upload( 'listing_logo', $post_id );
        if ( is_wp_error( $upload ) ) return false;

        return $upload;
    }
}
