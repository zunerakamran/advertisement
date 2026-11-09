<?php
defined( 'ABSPATH' ) || exit;

/**
 * Store a value in the session-like transient tied to this browser.
 */
function adv_set_flash( $key, $value ) {
    $token = adv_flash_token();
    set_transient( 'adv_flash_' . $token . '_' . $key, $value, 60 );
}

function adv_get_flash( $key ) {
    $token = adv_flash_token();
    $value = get_transient( 'adv_flash_' . $token . '_' . $key );
    delete_transient( 'adv_flash_' . $token . '_' . $key );
    return $value;
}

function adv_flash_token() {
    if ( ! isset( $_COOKIE['adv_flash_token'] ) ) {
        $token = wp_generate_password( 20, false );
        setcookie( 'adv_flash_token', $token, time() + 3600, COOKIEPATH, COOKIE_DOMAIN );
        $_COOKIE['adv_flash_token'] = $token;
    }
    return sanitize_text_field( $_COOKIE['adv_flash_token'] );
}

function adv_logout_url() {
    return add_query_arg( 'adv_logout', wp_create_nonce( 'adv_logout' ), home_url() );
}

function adv_delete_listing_url() {
    return add_query_arg( 'adv_delete_listing', wp_create_nonce( 'adv_delete_listing' ), home_url( '/dashboard/' ) );
}

function adv_get_listing_preview_url( $post ) {
    if ( is_numeric( $post ) ) {
        $post = get_post( $post );
    }
    if ( ! $post ) {
        return home_url( '/all-listings/' );
    }

    return get_permalink( $post->ID );
}

/**
 * Sync user listing statuses based on active plan quota.
 * Ensures active published/pending listings never exceed the user's active plan quota.
 */
function adv_sync_user_listing_quota( $user_id ) {
    error_log( 'ADV Sync Quota - FUNCTION CALLED for user ID: ' . $user_id );
    
    if ( ! $user_id ) {
        error_log( 'ADV Sync Quota - ABORTED: No user ID provided' );
        return;
    }

    if ( ! class_exists( 'ADV_Listing_Form' ) ) {
        require_once ADV_PATH . 'includes/modules/listings/class-adv-listing-form.php';
    }

    $quota = ADV_Listing_Form::get_user_listing_quota( $user_id );
    error_log( 'ADV Sync Quota - Calculated quota: ' . $quota );

    // Use WP_Query with 'any' status to include custom 'suspended' status
    $query = new WP_Query([
        'post_type'   => 'adv_listing',
        'author'      => $user_id,
        'post_status' => 'any',
        'posts_per_page' => -1,
        'orderby'     => 'ID',
        'order'       => 'ASC',
        'fields'      => 'ids',
    ]);

    $listings = [];
    foreach ( $query->posts as $post_id ) {
        $listings[] = get_post( $post_id );
    }

    error_log( 'ADV Sync Quota - Total listings found: ' . count( $listings ) );

    if ( empty( $listings ) ) {
        error_log( 'ADV Sync Quota - No listings found, exiting' );
        return;
    }

    if ( $quota <= 0 ) {
        error_log( 'ADV Sync Quota - Quota is 0, suspending all listings' );
        foreach ( $listings as $listing ) {
            if ( $listing->post_status !== 'suspended' ) {
                // Save original status so it can be restored if the user later upgrades again
                update_post_meta( $listing->ID, '_adv_pre_suspension_status', $listing->post_status );
                wp_update_post([
                    'ID'          => $listing->ID,
                    'post_status' => 'suspended',
                ]);
            }
        }
        return;
    }

    $published_or_pending = [];
    $suspended            = [];

    foreach ( $listings as $listing ) {
        if ( in_array( $listing->post_status, [ 'publish', 'pending' ], true ) ) {
            $published_or_pending[] = $listing;
        } elseif ( $listing->post_status === 'suspended' ) {
            $suspended[] = $listing;
        }
    }

    $active_count = count( $published_or_pending );
    error_log( 'ADV Sync Quota - Active listings: ' . $active_count . ', Suspended listings: ' . count( $suspended ) );

    if ( $active_count > $quota ) {
        // Exceeds quota (e.g., plan downgrade) — suspend excess listings
        for ( $i = $quota; $i < $active_count; $i++ ) {
            $listing_to_suspend = $published_or_pending[$i];
            // Save original status so it can be restored if the user later upgrades again
            update_post_meta( $listing_to_suspend->ID, '_adv_pre_suspension_status', $listing_to_suspend->post_status );
            wp_update_post([
                'ID'          => $listing_to_suspend->ID,
                'post_status' => 'suspended',
            ]);
        }
    } elseif ( $active_count < $quota && ! empty( $suspended ) ) {
        // Below quota (e.g., plan upgrade or new subscription) — reactivate latest suspended listings up to quota
        $needed      = $quota - $active_count;
        error_log( 'ADV Sync Quota - Restoration condition met. Needed: ' . $needed . ', Suspended available: ' . count( $suspended ) );
        // Reverse array to get latest listings first (since they were fetched in ASC order)
        $suspended_reversed = array_reverse( $suspended );
        $to_activate = array_slice( $suspended_reversed, 0, $needed );

        error_log( 'ADV Sync Quota - Attempting to restore ' . count( $to_activate ) . ' listings (needed: ' . $needed . ')' );

        $restored_count = 0;
        foreach ( $to_activate as $listing ) {
            // Restore to original pre-suspension status (pending or publish)
            $restore_status = get_post_meta( $listing->ID, '_adv_pre_suspension_status', true );
            error_log( 'ADV Sync Quota - Listing ID ' . $listing->ID . ' pre-suspension status: ' . ( $restore_status ?: 'NOT SET' ) );
            
            if ( ! in_array( $restore_status, [ 'publish', 'pending' ], true ) ) {
                $restore_status = 'publish';
            }
            delete_post_meta( $listing->ID, '_adv_pre_suspension_status' );
            
            $result = wp_update_post([
                'ID'          => $listing->ID,
                'post_status' => $restore_status,
            ]);
            
            if ( ! is_wp_error( $result ) ) {
                $restored_count++;
                error_log( 'ADV Sync Quota - Successfully restored listing ID ' . $listing->ID . ' to ' . $restore_status );
            } else {
                error_log( 'ADV Sync Quota - Failed to restore listing ID ' . $listing->ID . ': ' . $result->get_error_message() );
            }
        }

        // Set flash message if listings were restored (use user meta for webhook context)
        if ( $restored_count > 0 ) {
            update_user_meta( $user_id, 'adv_listings_restored_count', $restored_count );
            update_user_meta( $user_id, 'adv_listings_restored_time', time() );
        }
    } else {
        error_log( 'ADV Sync Quota - Restoration condition not met. active_count: ' . $active_count . ', quota: ' . $quota . ', suspended_count: ' . count( $suspended ) );
    }
    
    // Debug logging to help troubleshoot
    error_log( 'ADV Sync Quota - User ID: ' . $user_id . ', Quota: ' . $quota . ', Active: ' . $active_count . ', Suspended: ' . count( $suspended ) . ', Restored: ' . ( $restored_count ?? 0 ) );
}

