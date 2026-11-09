<?php
defined( 'ABSPATH' ) || exit;

class ADV_Plans {

    private static $table;

    public static function get_table() {
        global $wpdb;
        if ( empty( self::$table ) ) {
            self::$table = $wpdb->prefix . 'adv_plans';
        }
        return self::$table;
    }

    public static function init() {
        global $wpdb;
        $table = self::get_table();

        if ( is_admin() ) {
            $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) === $table;
            $has_stripe_col = $table_exists && ! empty( $wpdb->get_results( "SHOW COLUMNS FROM {$table} LIKE 'stripe_product_id'" ) );

            if ( ! $table_exists || ! $has_stripe_col ) {
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';
                $charset = $wpdb->get_charset_collate();
                $sql = "CREATE TABLE {$table} (
                    id bigint(20) NOT NULL AUTO_INCREMENT,
                    name varchar(255) NOT NULL,
                    description text,
                    price decimal(10,2) NOT NULL,
                    currency varchar(10) DEFAULT 'gbp',
                    duration_days int(11) NOT NULL DEFAULT 30,
                    features longtext,
                    stripe_product_id varchar(255),
                    stripe_price_id varchar(255),
                    is_featured tinyint(1) DEFAULT 0,
                    is_active tinyint(1) DEFAULT 1,
                    sort_order int(11) DEFAULT 0,
                    created_at datetime DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id)
                ) $charset;";
                dbDelta( $sql );
            }
        }
    }

    public static function get_all( $active_only = true ) {
        global $wpdb;
        $table = self::get_table();
        $where = $active_only ? 'WHERE is_active = 1' : '';
        return $wpdb->get_results(
            "SELECT * FROM {$table} {$where} ORDER BY sort_order ASC, price ASC"
        );
    }

    public static function get( $id ) {
        global $wpdb;
        $table = self::get_table();
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d", $id
        ));
    }

    /**
     * Create plan in DB + automatically create Product & Price in Stripe
     */
    public static function create( $data ) {
        global $wpdb;

        // Step 1 — Create in Stripe first
        $stripe_ids = self::create_in_stripe( $data );

        // Step 2 — Save to DB with Stripe IDs
        $wpdb->insert( self::get_table(), [
            'name'               => sanitize_text_field( $data['name'] ),
            'description'        => sanitize_textarea_field( $data['description'] ),
            'price'              => floatval( $data['price'] ),
            'currency'           => 'gbp',
            'duration_days'      => intval( $data['duration_days'] ?? 30 ),
            'features'           => json_encode( array_values( array_filter( array_map( 'sanitize_text_field', $data['features'] ?? [] ) ) ) ),
            'stripe_product_id'  => $stripe_ids['product_id'] ?? '',
            'stripe_price_id'    => $stripe_ids['price_id']   ?? '',
            'is_featured'        => intval( $data['is_featured'] ?? 0 ),
            'is_active'          => intval( $data['is_active'] ?? 1 ),
            'sort_order'         => intval( $data['sort_order'] ?? 0 ),
        ]);

        return $wpdb->insert_id;
    }

    /**
     * Update plan in DB + update Product in Stripe
     * Note: Stripe Prices are immutable — if price changes, archive old and create new
     * 
     * @param int $id Plan ID
     * @param array $data Plan data
     * @param bool $migrate_subscriptions Whether to migrate existing subscriptions to new price
     * @return bool|int Number of rows affected or false on failure
     */
    public static function update( $id, $data, $migrate_subscriptions = false ) {
        global $wpdb;

        $existing = self::get( $id );
        if ( ! $existing ) {
            return false;
        }

        $stripe_ids = [
            'product_id' => $existing->stripe_product_id ?? '',
            'price_id'   => $existing->stripe_price_id   ?? '',
        ];

        $price_changed = floatval( $existing->price ) !== floatval( $data['price'] );
        $billing_cycle_changed = intval( $existing->duration_days ) !== intval( $data['duration_days'] ?? 30 );

        if ( ADV_Stripe_Settings::is_configured() && self::load_stripe() ) {

            // If Stripe product or price is missing for this plan, create them in Stripe now!
            if ( empty( $stripe_ids['product_id'] ) || empty( $stripe_ids['price_id'] ) ) {
                $created = self::create_in_stripe( $data );
                if ( ! empty( $created['product_id'] ) ) {
                    $stripe_ids['product_id'] = $created['product_id'];
                }
                if ( ! empty( $created['price_id'] ) ) {
                    $stripe_ids['price_id'] = $created['price_id'];
                }
            } else {
                // Update product name/description in Stripe
                try {
                    \Stripe\Product::update( $stripe_ids['product_id'], [
                        'name'        => sanitize_text_field( $data['name'] ),
                        'description' => sanitize_textarea_field( $data['description'] ),
                    ]);
                } catch ( \Exception $e ) {
                    error_log( 'ADV Stripe product update error: ' . $e->getMessage() );
                }

                // Determine billing interval based on duration_days
                $billing_interval = self::get_billing_interval( intval( $data['duration_days'] ?? 30 ) );

                // If price or billing cycle changed — archive old price, create new one
                if ( $price_changed || $billing_cycle_changed ) {
                    // Archive old price in Stripe
                    if ( ! empty( $stripe_ids['price_id'] ) ) {
                        try {
                            \Stripe\Price::update( $stripe_ids['price_id'], [ 'active' => false ] );
                        } catch ( \Exception $e ) {
                            error_log( 'ADV Stripe price archive error: ' . $e->getMessage() );
                        }
                    }

                    // Create new price in Stripe with updated billing interval
                    try {
                        $new_price = \Stripe\Price::create([
                            'product'     => $stripe_ids['product_id'],
                            'unit_amount' => intval( floatval( $data['price'] ) * 100 ),
                            'currency'    => 'gbp',
                            'recurring'   => [ 'interval' => $billing_interval ],
                        ]);
                        $stripe_ids['price_id'] = $new_price->id;
                    } catch ( \Exception $e ) {
                        error_log( 'ADV Stripe new price error: ' . $e->getMessage() );
                    }
                }
            }
        }

        $updated = $wpdb->update( self::get_table(), [
            'name'              => sanitize_text_field( $data['name'] ),
            'description'       => sanitize_textarea_field( $data['description'] ),
            'price'             => floatval( $data['price'] ),
            'duration_days'     => intval( $data['duration_days'] ?? 30 ),
            'features'          => json_encode( array_values( array_filter( array_map( 'sanitize_text_field', $data['features'] ?? [] ) ) ) ),
            'stripe_product_id' => $stripe_ids['product_id'],
            'stripe_price_id'   => $stripe_ids['price_id'],
            'is_featured'       => intval( $data['is_featured'] ?? 0 ),
            'is_active'         => intval( $data['is_active'] ?? 1 ),
            'sort_order'        => intval( $data['sort_order'] ?? 0 ),
        ], [ 'id' => intval( $id ) ] );

        // Migrate existing subscriptions if requested and price/billing cycle changed
        if ( $updated && $migrate_subscriptions && ( $price_changed || $billing_cycle_changed ) ) {
            $migration_result = self::migrate_subscriptions_to_new_price( $id, $stripe_ids['price_id'] );
            error_log( 'ADV Migration Result: ' . print_r( $migration_result, true ) );
        }

        return $updated;
    }

    /**
     * Get Stripe billing interval based on duration days
     */
    private static function get_billing_interval( $duration_days ) {
        if ( $duration_days >= 365 ) {
            return 'year';
        } elseif ( $duration_days >= 90 ) {
            return 'quarter'; // Note: Stripe doesn't support quarter, this would need custom handling
        } elseif ( $duration_days >= 30 ) {
            return 'month';
        } elseif ( $duration_days >= 7 ) {
            return 'week';
        } else {
            return 'day';
        }
    }

    /**
     * Explicitly sync an unsynced plan with Stripe
     */
    public static function sync( $id ) {
        global $wpdb;

        $plan = self::get( $id );
        if ( ! $plan ) {
            return [ 'success' => false, 'error' => 'Plan not found.' ];
        }

        if ( ! ADV_Stripe_Settings::is_configured() ) {
            return [ 'success' => false, 'error' => 'Stripe keys are not configured in settings.' ];
        }

        if ( ! self::load_stripe() ) {
            return [ 'success' => false, 'error' => 'Stripe PHP SDK could not be loaded or Secret Key is empty.' ];
        }

        $data = [
            'name'        => $plan->name,
            'description' => $plan->description,
            'price'       => $plan->price,
        ];

        $product_id = $plan->stripe_product_id ?? '';
        $price_id   = $plan->stripe_price_id ?? '';

        // If product already exists in Stripe, try creating price under existing product
        if ( ! empty( $product_id ) && empty( $price_id ) ) {
            try {
                $unit_amount = intval( round( floatval( $plan->price ) * 100 ) );
                $price = \Stripe\Price::create([
                    'product'     => $product_id,
                    'unit_amount' => $unit_amount,
                    'currency'    => 'gbp',
                    'recurring'   => [ 'interval' => 'month' ],
                ]);
                $price_id = $price->id;
            } catch ( \Exception $e ) {
                // Stored product ID might not exist in current Stripe account/mode -> recreate both
                $product_id = '';
            }
        }

        // If product or price still missing, create product + price in Stripe
        if ( empty( $product_id ) || empty( $price_id ) ) {
            $stripe_ids = self::create_in_stripe( $data );

            if ( ! empty( $stripe_ids['error'] ) ) {
                return [ 'success' => false, 'error' => $stripe_ids['error'] ];
            }

            if ( empty( $stripe_ids['product_id'] ) || empty( $stripe_ids['price_id'] ) ) {
                return [ 'success' => false, 'error' => 'Failed to create product or price in Stripe.' ];
            }

            $product_id = $stripe_ids['product_id'];
            $price_id   = $stripe_ids['price_id'];
        }

        $wpdb->update( self::get_table(), [
            'stripe_product_id' => $product_id,
            'stripe_price_id'   => $price_id,
        ], [ 'id' => intval( $id ) ] );

        return [ 'success' => true ];
    }

    /**
     * Delete plan — also archive product in Stripe so it's not billable
     * Returns false if plan has active bookings
     */
    public static function delete( $id ) {
        global $wpdb;

        $plan = self::get( $id );
        if ( ! $plan ) {
            return false;
        }

        // Check for active bookings before deletion
        if ( self::has_active_bookings( $id ) ) {
            return [
                'success' => false,
                'error' => sprintf(
                    'Cannot delete plan "%s". It has %d active booking(s). Please migrate or cancel these bookings first.',
                    $plan->name,
                    self::get_active_booking_count( $id )
                )
            ];
        }

        if ( ADV_Stripe_Settings::is_configured() && self::load_stripe() ) {

            // Archive the Stripe product (Stripe doesn't allow deleting products with prices)
            if ( ! empty( $plan->stripe_product_id ) ) {
                try {
                    \Stripe\Product::update( $plan->stripe_product_id, [ 'active' => false ] );
                } catch ( \Exception $e ) {
                    error_log( 'ADV Stripe product archive error: ' . $e->getMessage() );
                }
            }
        }

        // Archive plan data before deletion
        self::archive_plan( $plan );

        $deleted = $wpdb->delete( self::get_table(), [ 'id' => intval( $id ) ] );
        
        return $deleted ? [ 'success' => true ] : [ 'success' => false, 'error' => 'Failed to delete plan from database.' ];
    }

    public static function get_features( $plan ) {
        return json_decode( $plan->features ?? '[]', true ) ?? [];
    }

    /**
     * Check if a plan has active bookings
     */
    public static function has_active_bookings( $plan_id ) {
        global $wpdb;
        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}adv_bookings WHERE plan_id = %d AND status IN ('active', 'pending')",
            intval( $plan_id )
        ));
        return intval( $count ) > 0;
    }

    /**
     * Get count of active bookings for a plan
     */
    public static function get_active_booking_count( $plan_id ) {
        global $wpdb;
        return intval( $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}adv_bookings WHERE plan_id = %d AND status IN ('active', 'pending')",
            intval( $plan_id )
        )));
    }

    /**
     * Archive plan data to history table before deletion
     */
    private static function archive_plan( $plan ) {
        global $wpdb;
        
        $wpdb->insert( $wpdb->prefix . 'adv_plan_history', [
            'original_plan_id'  => intval( $plan->id ),
            'name'              => sanitize_text_field( $plan->name ),
            'description'       => sanitize_textarea_field( $plan->description ),
            'price'             => floatval( $plan->price ),
            'currency'          => sanitize_text_field( $plan->currency ?? 'gbp' ),
            'duration_days'     => intval( $plan->duration_days ),
            'features'          => $plan->features,
            'stripe_product_id' => sanitize_text_field( $plan->stripe_product_id ?? '' ),
            'stripe_price_id'   => sanitize_text_field( $plan->stripe_price_id ?? '' ),
            'is_featured'       => intval( $plan->is_featured ),
            'is_active'         => intval( $plan->is_active ),
            'sort_order'        => intval( $plan->sort_order ),
            'deleted_by'        => get_current_user_id(),
        ]);
    }

    /**
     * Migrate bookings from one plan to another
     */
    public static function migrate_bookings( $from_plan_id, $to_plan_id, $cancel_stripe = false ) {
        global $wpdb;

        $from_plan = self::get( $from_plan_id );
        $to_plan = self::get( $to_plan_id );

        if ( ! $from_plan || ! $to_plan ) {
            return [ 'success' => false, 'error' => 'Source or destination plan not found.' ];
        }

        if ( $from_plan_id == $to_plan_id ) {
            return [ 'success' => false, 'error' => 'Cannot migrate to the same plan.' ];
        }

        // Get all active/pending bookings for the source plan
        $bookings = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}adv_bookings WHERE plan_id = %d AND status IN ('active', 'pending')",
            intval( $from_plan_id )
        ));

        if ( empty( $bookings ) ) {
            return [ 'success' => false, 'error' => 'No active bookings found for this plan.' ];
        }

        $migrated_count = 0;
        $errors = [];

        foreach ( $bookings as $booking ) {
            // Cancel Stripe subscription if requested
            if ( $cancel_stripe && ! empty( $booking->stripe_subscription_id ) && ADV_Stripe_Settings::is_configured() && self::load_stripe() ) {
                try {
                    $subscription = \Stripe\Subscription::retrieve( $booking->stripe_subscription_id );
                    $subscription->cancel();
                } catch ( \Exception $e ) {
                    $errors[] = "Failed to cancel Stripe subscription for booking #{$booking->id}: " . $e->getMessage();
                }
            }

            // Update booking to new plan
            $updated = $wpdb->update(
                $wpdb->prefix . 'adv_bookings',
                [ 'plan_id' => intval( $to_plan_id ) ],
                [ 'id' => intval( $booking->id ) ]
            );

            if ( $updated ) {
                $migrated_count++;
                // Sync user listing quota with new plan
                adv_sync_user_listing_quota( $booking->user_id );
            } else {
                $errors[] = "Failed to update booking #{$booking->id}";
            }
        }

        return [
            'success' => $migrated_count > 0,
            'migrated_count' => $migrated_count,
            'errors' => $errors
        ];
    }

    /**
     * Migrate existing Stripe subscriptions to new price when plan is updated
     */
    public static function migrate_subscriptions_to_new_price( $plan_id, $new_stripe_price_id ) {
        global $wpdb;

        error_log( 'ADV Migration: Starting migration for plan ' . $plan_id . ' to price ' . $new_stripe_price_id );

        if ( ! ADV_Stripe_Settings::is_configured() || ! self::load_stripe() ) {
            error_log( 'ADV Migration: Stripe not configured' );
            return [ 'success' => false, 'error' => 'Stripe not configured.' ];
        }

        // Get all active bookings for this plan with Stripe subscriptions
        $bookings = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}adv_bookings WHERE plan_id = %d AND status = 'active' AND stripe_subscription_id IS NOT NULL AND stripe_subscription_id != ''",
            intval( $plan_id )
        ));

        error_log( 'ADV Migration: Found ' . count( $bookings ) . ' active subscriptions to migrate' );

        if ( empty( $bookings ) ) {
            return [ 'success' => true, 'migrated_count' => 0, 'message' => 'No active subscriptions to migrate.' ];
        }

        $migrated_count = 0;
        $errors = [];

        foreach ( $bookings as $booking ) {
            try {
                error_log( 'ADV Migration: Processing booking #' . $booking->id . ' with subscription ' . $booking->stripe_subscription_id );
                
                $subscription = \Stripe\Subscription::retrieve( $booking->stripe_subscription_id );
                
                // Get the subscription item ID
                $subscription_item_id = $subscription->items->data[0]->id ?? null;
                
                if ( ! $subscription_item_id ) {
                    $errors[] = "No subscription item found for booking #{$booking->id}";
                    error_log( 'ADV Migration: No subscription item found for booking #' . $booking->id );
                    continue;
                }

                error_log( 'ADV Migration: Updating subscription item ' . $subscription_item_id . ' to price ' . $new_stripe_price_id );

                // Update the subscription item to use the new price
                \Stripe\SubscriptionItem::update( $subscription_item_id, [
                    'price' => $new_stripe_price_id
                ]);

                // Update booking with new price ID
                $wpdb->update(
                    $wpdb->prefix . 'adv_bookings',
                    [ 'stripe_price_id' => $new_stripe_price_id ],
                    [ 'id' => $booking->id ]
                );

                $migrated_count++;
                error_log( 'ADV Migration: Successfully migrated booking #' . $booking->id );

            } catch ( \Exception $e ) {
                $errors[] = "Failed to migrate subscription for booking #{$booking->id}: " . $e->getMessage();
                error_log( 'ADV Migration: Error migrating booking #' . $booking->id . ': ' . $e->getMessage() );
            }
        }

        error_log( 'ADV Migration: Completed. Migrated ' . $migrated_count . ' subscriptions' );

        return [
            'success' => $migrated_count > 0,
            'migrated_count' => $migrated_count,
            'errors' => $errors
        ];
    }

    // ─── Private Helpers ─────────────────────────────────────

    /**
     * Create a Product + recurring Price in Stripe
     * Returns ['product_id' => ..., 'price_id' => ..., 'error' => '']
     */
    private static function create_in_stripe( $data ) {
        if ( ! ADV_Stripe_Settings::is_configured() || ! self::load_stripe() ) {
            return [ 'product_id' => '', 'price_id' => '', 'error' => 'Stripe keys are not configured or SDK is missing.' ];
        }

        try {
            $product_params = [
                'name'     => sanitize_text_field( $data['name'] ),
                'metadata' => [
                    'source' => 'advertisement_plugin',
                ],
            ];

            $desc = sanitize_textarea_field( $data['description'] ?? '' );
            if ( ! empty( $desc ) ) {
                $product_params['description'] = substr( $desc, 0, 500 );
            }

            // Create Stripe Product
            $product = \Stripe\Product::create( $product_params );

            $unit_amount = intval( round( floatval( $data['price'] ?? 0 ) * 100 ) );

            // Create recurring monthly Price under that Product
            $price = \Stripe\Price::create([
                'product'     => $product->id,
                'unit_amount' => $unit_amount,
                'currency'    => 'gbp',
                'recurring'   => [
                    'interval' => 'month',
                ],
            ]);

            return [
                'product_id' => $product->id,
                'price_id'   => $price->id,
                'error'      => '',
            ];

        } catch ( \Stripe\Exception\ApiErrorException $e ) {
            error_log( 'ADV Stripe create error: ' . $e->getMessage() );
            return [
                'product_id' => '',
                'price_id'   => '',
                'error'      => $e->getMessage(),
            ];
        } catch ( \Exception $e ) {
            error_log( 'ADV Stripe create error: ' . $e->getMessage() );
            return [
                'product_id' => '',
                'price_id'   => '',
                'error'      => $e->getMessage(),
            ];
        }
    }

    private static function load_stripe() {
        $autoloader = ADV_PATH . 'vendor/autoload.php';
        if ( ! file_exists( $autoloader ) ) {
            return false; // Stripe SDK not installed — skip silently
        }
        require_once $autoloader;
        $key = trim( ADV_Stripe_Settings::get( 'secret_key' ) );
        if ( empty( $key ) ) {
            return false;
        }
        \Stripe\Stripe::setApiKey( $key );
        return true;
    }
}