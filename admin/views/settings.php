<?php defined( 'ABSPATH' ) || exit; ?>

<div class="wrap adv-admin">
    <h1>Settings</h1>

    <?php
    // Handle Stripe Settings save
    if ( isset( $_POST['adv_stripe_settings_nonce'] ) && wp_verify_nonce( $_POST['adv_stripe_settings_nonce'], 'adv_stripe_settings' ) ) {
        update_option( 'adv_stripe_mode', $_POST['adv_stripe_mode'] );
        update_option( 'adv_stripe_secret_key', $_POST['adv_stripe_secret_key'] );
        update_option( 'adv_stripe_publishable_key', $_POST['adv_stripe_publishable_key'] );
        echo '<div class="notice notice-success is-dismissible"><p>Stripe settings saved.</p></div>';
    }

    // Handle Email Settings save
    if ( isset( $_POST['adv_email_settings_nonce'] ) && wp_verify_nonce( $_POST['adv_email_settings_nonce'], 'adv_email_settings_save' ) && current_user_can( 'manage_options' ) ) {
        update_option( 'adv_admin_email', sanitize_email( $_POST['adv_admin_email'] ?? '' ) );
        
        // Handle logo upload
        if ( isset( $_POST['adv_email_logo_id'] ) ) {
            update_option( 'adv_email_logo_id', intval( $_POST['adv_email_logo_id'] ) );
        }
        
        echo '<div class="notice notice-success is-dismissible"><p>Email settings saved.</p></div>';
    }

    // Handle Security Settings save
    if ( isset( $_POST['adv_security_settings_nonce'] ) && wp_verify_nonce( $_POST['adv_security_settings_nonce'], 'adv_security_settings_save' ) && current_user_can( 'manage_options' ) ) {
        update_option( 'adv_turnstile_site_key', sanitize_text_field( wp_unslash( $_POST['adv_turnstile_site_key'] ?? '' ) ) );
        update_option( 'adv_turnstile_secret_key', sanitize_text_field( wp_unslash( $_POST['adv_turnstile_secret_key'] ?? '' ) ) );
        update_option( 'adv_require_email_verification', isset( $_POST['adv_require_email_verification'] ) ? '1' : '0' );
        update_option( 'adv_registration_rate_limit', max( 1, min( 50, absint( $_POST['adv_registration_rate_limit'] ?? 5 ) ) ) );

        if ( ! empty( $_POST['adv_cleanup_caps'] ) && class_exists( 'ADV_Security' ) ) {
            ADV_Security::cleanup_polluted_capabilities();
            update_option( 'adv_security_caps_cleaned_v1', 1, true );
            echo '<div class="notice notice-success is-dismissible"><p>Security settings saved. Advertiser capabilities cleaned.</p></div>';
        } else {
            echo '<div class="notice notice-success is-dismissible"><p>Security settings saved.</p></div>';
        }
    }

    // Handle Recipe Settings save
    if ( isset( $_POST['adv_recipe_settings_nonce'] ) && wp_verify_nonce( $_POST['adv_recipe_settings_nonce'], 'adv_recipe_settings_save' ) && current_user_can( 'manage_options' ) ) {
        ADV_Recipe_Settings::save( $_POST['adv_recipe_price'] ?? 35 );
        echo '<div class="notice notice-success is-dismissible"><p>Recipe settings saved.</p></div>';
    }

    // Handle Blog Settings save
    if ( isset( $_POST['adv_blog_settings_nonce'] ) && wp_verify_nonce( $_POST['adv_blog_settings_nonce'], 'adv_blog_settings_save' ) && current_user_can( 'manage_options' ) ) {
        ADV_Blog_Settings::save( $_POST['adv_blog_price'] ?? 50 );
        echo '<div class="notice notice-success is-dismissible"><p>Blog settings saved.</p></div>';
    }

    // Handle Magazine Settings save
    if ( isset( $_POST['adv_mag_settings_nonce'] ) && wp_verify_nonce( $_POST['adv_mag_settings_nonce'], 'adv_mag_settings_save' ) && current_user_can( 'manage_options' ) ) {
        ADV_Magazine_Settings::save_all( $_POST['prices'] ?? [] );
        echo '<div class="notice notice-success is-dismissible"><p>Magazine pricing saved.</p></div>';
    }

    // Handle Banner Settings save
    if ( isset( $_POST['adv_banner_settings_nonce'] ) && wp_verify_nonce( $_POST['adv_banner_settings_nonce'], 'adv_banner_settings_save' ) && current_user_can( 'manage_options' ) ) {
        ADV_Banner_Settings::save_all( $_POST['prices'] ?? [] );
        echo '<div class="notice notice-success is-dismissible"><p>Banner settings saved.</p></div>';
    }

    // Handle Coupon delete
    if ( isset( $_GET['delete_coupon'] ) && check_admin_referer( 'adv_delete_coupon' ) ) {
        ADV_Coupons::delete( intval( $_GET['delete_coupon'] ) );
        echo '<div class="notice notice-success is-dismissible"><p>Coupon deleted.</p></div>';
    }

    // Handle Coupon create
    if ( isset( $_POST['adv_save_coupon_nonce'] ) && wp_verify_nonce( $_POST['adv_save_coupon_nonce'], 'adv_save_coupon' ) ) {
        ADV_Coupons::create([
            'code'       => $_POST['coupon_code'],
            'trial_days' => $_POST['trial_days'],
            'max_uses'   => $_POST['max_uses'] ?? null,
            'expires_at' => $_POST['expires_at'] ?? null,
        ]);
        echo '<div class="notice notice-success is-dismissible"><p>Coupon created.</p></div>';
    }

    // Handle Plan delete
    if ( isset( $_GET['delete_plan'] ) && check_admin_referer( 'adv_delete_plan' ) ) {
        $result = ADV_Plans::delete( intval( $_GET['delete_plan'] ) );
        if ( $result['success'] ) {
            echo '<div class="notice notice-success is-dismissible"><p>Plan deleted successfully.</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $result['error'] ) . '</p></div>';
        }
    }

    // Handle migration
    if ( isset( $_POST['adv_migrate_plan_nonce'] ) && wp_verify_nonce( $_POST['adv_migrate_plan_nonce'], 'adv_migrate_plan' ) ) {
        $from_plan = intval( $_POST['from_plan_id'] );
        $to_plan = intval( $_POST['to_plan_id'] );
        $cancel_stripe = isset( $_POST['cancel_stripe'] );
        
        $result = ADV_Plans::migrate_bookings( $from_plan, $to_plan, $cancel_stripe );
        
        if ( $result['success'] ) {
            echo '<div class="notice notice-success is-dismissible"><p>Successfully migrated ' . intval( $result['migrated_count'] ) . ' booking(s) to the new plan.</p></div>';
            if ( ! empty( $result['errors'] ) ) {
                echo '<div class="notice notice-warning is-dismissible"><p>Some errors occurred: ' . esc_html( implode( ', ', $result['errors'] ) ) . '</p></div>';
            }
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>Migration failed: ' . esc_html( $result['error'] ) . '</p></div>';
        }
    }

    // Handle Plan sync
    if ( isset( $_GET['sync_plan'] ) && check_admin_referer( 'adv_sync_plan' ) ) {
        $sync_res = ADV_Plans::sync( intval( $_GET['sync_plan'] ) );
        if ( $sync_res['success'] ) {
            echo '<div class="notice notice-success is-dismissible"><p>Plan synced with Stripe successfully!</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>Failed to sync with Stripe: ' . esc_html( $sync_res['error'] ) . '</p></div>';
        }
    }

    // Handle Plan save
    if ( isset( $_POST['adv_save_plan_nonce'] ) && wp_verify_nonce( $_POST['adv_save_plan_nonce'], 'adv_save_plan' ) ) {
        $data = [
            'name'            => $_POST['plan_name'] ?? '',
            'description'     => $_POST['plan_description'] ?? '',
            'price'           => $_POST['plan_price'] ?? 0,
            'duration_days'   => $_POST['plan_duration'] ?? 30,
            'features'        => $_POST['plan_features'] ?? [],
            'stripe_price_id' => $_POST['stripe_price_id'] ?? '',
            'is_featured'     => isset( $_POST['is_featured'] ) ? 1 : 0,
            'is_active'       => isset( $_POST['is_active'] ) ? 1 : 0,
            'sort_order'      => $_POST['sort_order'] ?? 0,
        ];

        if ( ! empty( $_POST['plan_id'] ) ) {
            $migrate_subscriptions = isset( $_POST['migrate_subscriptions'] ) ? true : false;
            $updated = ADV_Plans::update( intval( $_POST['plan_id'] ), $data, $migrate_subscriptions );
            if ( $updated !== false ) {
                $message = 'Plan updated.';
                if ( $migrate_subscriptions ) {
                    $message .= ' Existing subscriptions will be migrated to the new price/billing cycle.';
                }
                echo '<div class="notice notice-success is-dismissible"><p>' . $message . '</p></div>';
            } else {
                global $wpdb;
                $db_err = $wpdb->last_error ? esc_html( $wpdb->last_error ) : 'Unknown database error.';
                echo '<div class="notice notice-error is-dismissible"><p>Failed to update plan. DB error: ' . $db_err . '</p></div>';
            }
        } else {
            $new_id = ADV_Plans::create( $data );
            if ( $new_id ) {
                echo '<div class="notice notice-success is-dismissible"><p>Plan created successfully (ID: ' . intval( $new_id ) . ').</p></div>';
            } else {
                global $wpdb;
                $db_err = $wpdb->last_error ? esc_html( $wpdb->last_error ) : 'Unknown database error.';
                echo '<div class="notice notice-error is-dismissible"><p>Failed to save plan. DB error: ' . $db_err . '</p></div>';
            }
        }
    }

    $coupons     = ADV_Coupons::get_all();
    $plans       = ADV_Plans::get_all( false );
    $edit_plan   = isset( $_GET['edit_plan'] ) ? ADV_Plans::get( intval( $_GET['edit_plan'] ) ) : null;
    ?>

    <style>
        .adv-settings-tabs {
            display: flex;
            border-bottom: 1px solid #ccc;
            margin-bottom: 20px;
        }
        .adv-settings-tab {
            padding: 10px 20px;
            cursor: pointer;
            border: 1px solid #ccc;
            border-bottom: none;
            margin-right: 5px;
            background: #f0f0f1;
            border-radius: 4px 4px 0 0;
        }
        .adv-settings-tab.active {
            background: #fff;
            border-bottom: 1px solid #fff;
            margin-bottom: -1px;
        }
        .adv-settings-panel {
            display: none;
        }
        .adv-settings-panel.active {
            display: block;
        }
    </style>

    <script>
        jQuery(document).ready(function($) {
            $('.adv-settings-tab').on('click', function() {
                $('.adv-settings-tab').removeClass('active');
                $('.adv-settings-panel').removeClass('active');
                $(this).addClass('active');
                $('#' + $(this).data('tab')).addClass('active');
            });
        });
    </script>

    <div class="adv-settings-tabs">
        <div class="adv-settings-tab active" data-tab="listing-panel">Listing Settings</div>
        <div class="adv-settings-tab" data-tab="stripe-panel">Stripe Settings</div>
        <div class="adv-settings-tab" data-tab="email-panel">Email Settings</div>
        <div class="adv-settings-tab" data-tab="security-panel">Security</div>
        <div class="adv-settings-tab" data-tab="recipe-panel">Recipe Settings</div>
        <div class="adv-settings-tab" data-tab="blog-panel">Blog Settings</div>
        <div class="adv-settings-tab" data-tab="magazine-panel">Magazine Pricing</div>
        <div class="adv-settings-tab" data-tab="banner-panel">Banner Pricing</div>
    </div>

    <!-- Listing Settings Panel -->
    <div id="listing-panel" class="adv-settings-panel active">
        <div class="adv-admin-card">
            <h2>Pricing Plans</h2>
            <p>Create and manage subscription plans for listings.</p>

            <!-- Plan Form -->
            <h3><?php echo $edit_plan ? 'Edit Plan' : 'Add New Plan'; ?></h3>
            <form method="post">
                <?php wp_nonce_field( 'adv_save_plan', 'adv_save_plan_nonce' ); ?>
                <?php if ( $edit_plan ) : ?>
                    <input type="hidden" name="plan_id" value="<?php echo esc_attr( $edit_plan->id ); ?>">
                <?php endif; ?>

                <table class="form-table">
                    <tr>
                        <th>Plan Name *</th>
                        <td><input type="text" name="plan_name" class="regular-text" required
                                   value="<?php echo esc_attr( $edit_plan->name ?? '' ); ?>"></td>
                    </tr>
                    <tr>
                        <th>Description</th>
                        <td><textarea name="plan_description" rows="3" class="regular-text"><?php
                            echo esc_textarea( $edit_plan->description ?? '' );
                        ?></textarea></td>
                    </tr>
                    <tr>
                        <th>Monthly Price (£) *</th>
                        <td><input type="number" name="plan_price" step="0.01" min="0" required
                                   value="<?php echo esc_attr( $edit_plan->price ?? '' ); ?>"></td>
                    </tr>
                    <tr>
                        <th>Billing Cycle (days) *</th>
                        <td>
                            <input type="number" name="plan_duration" min="1" required
                                   value="<?php echo esc_attr( $edit_plan->duration_days ?? 30 ); ?>">
                            <p class="description">30 = monthly, 365 = yearly</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Features</th>
                        <td>
                            <div id="adv-features-wrap">
                                <?php
                                $features = $edit_plan ? ADV_Plans::get_features( $edit_plan ) : [''];
                                foreach ( $features as $f ) : ?>
                                    <div class="adv-feature-row">
                                        <input type="text" name="plan_features[]"
                                               value="<?php echo esc_attr( $f ); ?>"
                                               placeholder="e.g. Featured in search results"
                                               class="regular-text">
                                        <button type="button" class="button adv-remove-feature">✕</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="button" id="adv-add-feature">+ Add Feature</button>
                        </td>
                    </tr>
                    <?php if ( $edit_plan && ! empty( $edit_plan->stripe_price_id ) ) : ?>
                    <tr>
                        <th>Stripe Status</th>
                        <td>
                            <span style="color:#166534; background:#f0fdf4; padding:4px 10px; border-radius:6px; font-size:0.85rem;">
                                ✅ Synced with Stripe
                            </span>
                            <p class="description">
                                Product ID: <code><?php echo esc_html( $edit_plan->stripe_product_id ); ?></code>
                            </p>
                        </td>
                    </tr>
                    <?php elseif ( $edit_plan ) : ?>
                    <tr>
                        <th>Stripe Status</th>
                        <td>
                            <span style="color:#92400e; background:#fffbeb; padding:4px 10px; border-radius:6px; font-size:0.85rem; display:inline-block; margin-bottom:6px;">
                                ⚠️ Not synced — make sure Stripe keys are saved in settings
                            </span>
                            <br>
                            <a href="<?php echo wp_nonce_url( '?page=adv-settings&sync_plan=' . $edit_plan->id, 'adv_sync_plan' ); ?>" class="button button-secondary button-small">
                                ⚡ Sync with Stripe Now
                            </a>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Sort Order</th>
                        <td><input type="number" name="sort_order" min="0"
                                   value="<?php echo esc_attr( $edit_plan->sort_order ?? 0 ); ?>"></td>
                    </tr>
                    <tr>
                        <th>Mark as Featured</th>
                        <td>
                            <label>
                                <input type="checkbox" name="is_featured" value="1"
                                       <?php checked( $edit_plan->is_featured ?? 0, 1 ); ?>>
                                Show "Most Popular" badge on this plan
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>Active Status</th>
                        <td>
                            <label>
                                <input type="checkbox" name="is_active" value="1"
                                       <?php checked( $edit_plan->is_active ?? 1, 1 ); ?>>
                                Active (visible on pricing page)
                            </label>
                        </td>
                    </tr>
                    <?php if ( $edit_plan ) : ?>
                    <tr>
                        <th>Subscription Migration</th>
                        <td>
                            <label>
                                <input type="checkbox" name="migrate_subscriptions" value="1">
                                <strong>Migrate existing subscriptions</strong> to new price/billing cycle
                            </label>
                            <p class="description">
                                Only applies when changing price or billing cycle. Existing subscribers will be moved to the new pricing automatically.
                            </p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php echo $edit_plan ? 'Update Plan' : 'Create Plan'; ?>
                    </button>
                    <?php if ( $edit_plan ) : ?>
                        <a href="?page=adv-settings" class="button">Cancel</a>
                    <?php endif; ?>
                </p>
            </form>
        </div>

        <!-- Plans Table -->
        <div class="adv-admin-card">
            <h2>All Plans</h2>
            <?php if ( empty( $plans ) ) : ?>
                <p>No plans yet. Create your first one above.</p>
            <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Price</th>
                        <th>Duration</th>
                        <th>Stripe Price ID</th>
                        <th>Featured</th>
                        <th>Active</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $plans as $plan ) : ?>
                    <tr>
                        <td><strong><?php echo esc_html( $plan->name ); ?></strong></td>
                        <td>£<?php echo number_format( $plan->price, 2 ); ?>/mo</td>
                        <td><?php echo esc_html( $plan->duration_days ); ?> days</td>
                        <td><code><?php echo esc_html( $plan->stripe_price_id ?: '—' ); ?></code></td>
                        <td><?php echo $plan->is_featured ? '⭐ Yes' : '—'; ?></td>
                        <td><?php echo $plan->is_active ? '✅' : '❌'; ?></td>
                        <td>
                            <?php $active_bookings = ADV_Plans::get_active_booking_count( $plan->id ); ?>
                            <?php if ( $active_bookings > 0 ) : ?>
                                <span style="color:#d97706; font-weight:600;"><?php echo $active_bookings; ?> active</span> |
                            <?php endif; ?>
                            <a href="?page=adv-settings&edit_plan=<?php echo $plan->id; ?>">Edit</a> |
                            <?php if ( empty( $plan->stripe_price_id ) ) : ?>
                                <a href="<?php echo wp_nonce_url( '?page=adv-settings&sync_plan=' . $plan->id, 'adv_sync_plan' ); ?>" style="color:#d97706; font-weight:600;">Sync Stripe</a> |
                            <?php endif; ?>
                            <?php if ( $active_bookings > 0 ) : ?>
                                <a href="#" onclick="showMigrationModal(<?php echo $plan->id; ?>, '<?php echo esc_js( $plan->name ); ?>'); return false;" style="color:#059669; font-weight:600;">Migrate</a> |
                            <?php endif; ?>
                            <a href="<?php echo wp_nonce_url( '?page=adv-settings&delete_plan=' . $plan->id, 'adv_delete_plan' ); ?>"
                               onclick="<?php echo $active_bookings > 0 ? 'alert(\'This plan has active bookings. Please migrate them first.\'); return false;' : 'return confirm(\'Delete this plan?\')'; ?>">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Coupons Section -->
        <div class="adv-admin-card">
            <h2>Coupons</h2>
            <p>Create discount coupons for free trials.</p>

            <h3>Create Coupon</h3>
            <form method="post">
                <?php wp_nonce_field( 'adv_save_coupon', 'adv_save_coupon_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th>Coupon Code *</th>
                        <td>
                            <input type="text" name="coupon_code" class="regular-text" required
                                   placeholder="e.g. FREEGOSS15" style="text-transform:uppercase;">
                            <p class="description">Uppercase letters and numbers only. Users enter this exactly.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Free Trial Days *</th>
                        <td>
                            <input type="number" name="trial_days" min="1" required value="15">
                            <p class="description">How many days free before Stripe starts charging.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Max Uses</th>
                        <td>
                            <input type="number" name="max_uses" min="1" placeholder="Leave blank for unlimited">
                        </td>
                    </tr>
                    <tr>
                        <th>Expiry Date</th>
                        <td>
                            <input type="datetime-local" name="expires_at">
                            <p class="description">Leave blank if coupon never expires.</p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary">Create Coupon</button>
                </p>
            </form>
        </div>

        <div class="adv-admin-card">
            <h2>All Coupons</h2>
            <?php if ( empty( $coupons ) ) : ?>
                <p>No coupons yet.</p>
            <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Free Days</th>
                        <th>Uses</th>
                        <th>Max Uses</th>
                        <th>Expires</th>
                        <th>Active</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $coupons as $c ) : ?>
                    <tr>
                        <td><strong><code><?php echo esc_html( $c->code ); ?></code></strong></td>
                        <td><?php echo esc_html( $c->trial_days ); ?> days</td>
                        <td><?php echo esc_html( $c->used_count ); ?></td>
                        <td><?php echo $c->max_uses ?? 'Unlimited'; ?></td>
                        <td><?php echo $c->expires_at ? esc_html( date( 'd M Y', strtotime( $c->expires_at ) ) ) : 'Never'; ?></td>
                        <td><?php echo $c->is_active ? '✅' : '❌'; ?></td>
                        <td>
                            <a href="<?php echo wp_nonce_url( '?page=adv-settings&delete_coupon=' . $c->id, 'adv_delete_coupon' ); ?>"
                               onclick="return confirm('Delete this coupon?')">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stripe Settings Panel -->
    <div id="stripe-panel" class="adv-settings-panel">
        <div class="adv-admin-card">
            <h2>Stripe Settings</h2>
            <p style="color:#6b7280; margin-bottom:24px;">
                Get your API keys from
                <a href="https://dashboard.stripe.com/apikeys" target="_blank">Stripe Dashboard → Developers → API Keys</a>.
                Use <strong>Test</strong> keys while building, switch to <strong>Live</strong> when ready.
            </p>
            <form method="post">
                <?php wp_nonce_field( 'adv_stripe_settings', 'adv_stripe_settings_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th>Mode</th>
                        <td>
                            <select name="adv_stripe_mode">
                                <option value="test" <?php selected( get_option('adv_stripe_mode', 'test'), 'test' ); ?>>
                                    🧪 Test Mode
                                </option>
                                <option value="live" <?php selected( get_option('adv_stripe_mode', 'test'), 'live' ); ?>>
                                    ✅ Live Mode
                                </option>
                            </select>
                            <p class="description">Always test with test keys before going live.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Secret Key *</th>
                        <td>
                            <input type="password"
                                   name="adv_stripe_secret_key"
                                   class="regular-text"
                                   value="<?php echo esc_attr( get_option('adv_stripe_secret_key') ); ?>"
                                   placeholder="sk_test_... or sk_live_...">
                            <p class="description">Never share this key. Starts with <code>sk_test_</code> or <code>sk_live_</code>.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Publishable Key *</th>
                        <td>
                            <input type="text"
                                   name="adv_stripe_publishable_key"
                                   class="regular-text"
                                   value="<?php echo esc_attr( get_option('adv_stripe_publishable_key') ); ?>"
                                   placeholder="pk_test_... or pk_live_...">
                            <p class="description">Safe to use on front-end. Starts with <code>pk_test_</code> or <code>pk_live_</code>.</p>
                        </td>
                    </tr>
                </table>
                <?php
                $secret = get_option('adv_stripe_secret_key');
                if ( $secret ) :
                    $is_live = strpos( $secret, 'sk_live_' ) === 0;
                ?>
                <div style="margin: 16px 0; padding: 12px 16px; border-radius:8px;
                            background: <?php echo $is_live ? '#f0fdf4' : '#fffbeb'; ?>;
                            border: 1px solid <?php echo $is_live ? '#86efac' : '#fcd34d'; ?>;">
                    <?php if ( $is_live ) : ?>
                        ✅ <strong>Live keys detected</strong> — real payments will be processed.
                    <?php else : ?>
                        🧪 <strong>Test keys detected</strong> — no real payments will be taken.
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php submit_button( 'Save Stripe Settings' ); ?>
            </form>
        </div>
    </div>

    <!-- Email Settings Panel -->
    <div id="email-panel" class="adv-settings-panel">
        <div class="adv-admin-card">
            <h2>Email Settings</h2>
            <p>Configure the admin email address for receiving notifications and upload your email logo.</p>
            <form method="post">
                <?php wp_nonce_field( 'adv_email_settings_save', 'adv_email_settings_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th>Admin Email *</th>
                        <td>
                            <input type="email"
                                   name="adv_admin_email"
                                   class="regular-text"
                                   value="<?php echo esc_attr( get_option( 'adv_admin_email', get_option( 'admin_email' ) ) ); ?>"
                                   placeholder="admin@example.com"
                                   required>
                            <p class="description">This email will receive notifications for new submissions, payments, and other important events.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Email Logo</th>
                        <td>
                            <div class="adv-logo-upload">
                                <input type="hidden" name="adv_email_logo_id" id="adv_email_logo_id" value="<?php echo esc_attr( get_option( 'adv_email_logo_id', 0 ) ); ?>">
                                <div id="adv-logo-preview">
                                    <?php 
                                    $logo_id = get_option( 'adv_email_logo_id', 0 );
                                    if ( $logo_id ) {
                                        $logo_url = wp_get_attachment_url( $logo_id );
                                        if ( $logo_url ) {
                                            echo '<img src="' . esc_url( $logo_url ) . '" alt="Email Logo" style="max-width: 200px; max-height: 80px; border: 1px solid #ddd; padding: 5px; background: #fff;">';
                                        }
                                    }
                                    ?>
                                </div>
                                <button type="button" class="button" id="adv-upload-logo-btn">Upload Logo</button>
                                <button type="button" class="button" id="adv-remove-logo-btn" style="display: <?php echo $logo_id ? 'inline-block' : 'none'; ?>;">Remove Logo</button>
                                <p class="description">Upload a logo to display in email headers. Recommended size: 200px wide by 80px high.</p>
                            </div>
                        </td>
                    </tr>
                </table>
                <?php submit_button( 'Save Email Settings' ); ?>
            </form>
        </div>
    </div>

    <!-- Security Settings Panel -->
    <div id="security-panel" class="adv-settings-panel">
        <div class="adv-admin-card">
            <h2>Registration &amp; Security</h2>
            <p>Protect advertiser registration and stop unauthorized WordPress post publishing.</p>
            <form method="post">
                <?php wp_nonce_field( 'adv_security_settings_save', 'adv_security_settings_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th>Email verification</th>
                        <td>
                            <label>
                                <input type="checkbox" name="adv_require_email_verification" value="1"
                                    <?php checked( get_option( 'adv_require_email_verification', '1' ), '1' ); ?>>
                                Require new advertisers to verify their email before login
                            </label>
                            <p class="description">Strongly recommended. Existing accounts are not locked out.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Registration rate limit</th>
                        <td>
                            <input type="number" name="adv_registration_rate_limit" min="1" max="50" class="small-text"
                                   value="<?php echo esc_attr( get_option( 'adv_registration_rate_limit', 5 ) ); ?>">
                            <p class="description">Max registration attempts per IP address per hour.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Cloudflare Turnstile site key</th>
                        <td>
                            <input type="text" name="adv_turnstile_site_key" class="regular-text"
                                   value="<?php echo esc_attr( get_option( 'adv_turnstile_site_key', '' ) ); ?>"
                                   placeholder="0x...">
                            <p class="description">
                                Free CAPTCHA from
                                <a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener noreferrer">Cloudflare Turnstile</a>.
                                Leave blank to disable CAPTCHA (email verification + rate limit still apply).
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th>Cloudflare Turnstile secret key</th>
                        <td>
                            <input type="password" name="adv_turnstile_secret_key" class="regular-text" autocomplete="new-password"
                                   value="<?php echo esc_attr( get_option( 'adv_turnstile_secret_key', '' ) ); ?>"
                                   placeholder="0x...">
                        </td>
                    </tr>
                    <tr>
                        <th>Cleanup capabilities</th>
                        <td>
                            <label>
                                <input type="checkbox" name="adv_cleanup_caps" value="1">
                                Re-run cleanup of elevated Advertiser capabilities (upload/edit/publish posts)
                            </label>
                            <p class="description">Use once after deploying this security update if scam posts were still possible.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button( 'Save Security Settings' ); ?>
            </form>
            <div style="margin-top:16px;padding:12px 16px;background:#f0f6fc;border:1px solid #c3d4e6;border-radius:6px;">
                <strong>What this plugin now blocks automatically:</strong>
                <ul style="margin:8px 0 0 18px;">
                    <li>Advertisers can no longer publish normal WordPress Posts via the REST API</li>
                    <li><code>edit_posts</code> / <code>publish_posts</code> are no longer granted to logged-in users</li>
                    <li>Disposable/temporary email domains are rejected at registration</li>
                    <li>Media upload is only allowed on plugin submission forms</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Recipe Settings Panel -->
    <div id="recipe-panel" class="adv-settings-panel">
        <div class="adv-admin-card">
            <h2>Featured Recipe Settings</h2>
            <form method="post">
                <?php wp_nonce_field( 'adv_recipe_settings_save', 'adv_recipe_settings_nonce' ); ?>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="adv_recipe_price">Submission Price (£)</label>
                            </th>
                            <td>
                                <input type="number" id="adv_recipe_price" name="adv_recipe_price"
                                       value="<?php echo esc_attr( ADV_Recipe_Settings::get_price() ); ?>"
                                       min="0" step="0.01" class="regular-text">
                                <p class="description">The one-time fee charged when a user submits a featured guest recipe.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <?php submit_button( 'Save Recipe Settings' ); ?>
            </form>
        </div>
    </div>

    <!-- Blog Settings Panel -->
    <div id="blog-panel" class="adv-settings-panel">
        <div class="adv-admin-card">
            <h2>Featured Blog Settings</h2>
            <form method="post">
                <?php wp_nonce_field( 'adv_blog_settings_save', 'adv_blog_settings_nonce' ); ?>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="adv_blog_price">Submission Price (£)</label></th>
                            <td>
                                <input type="number" id="adv_blog_price" name="adv_blog_price"
                                       value="<?php echo esc_attr( ADV_Blog_Settings::get_price() ); ?>"
                                       min="0" step="0.01" class="regular-text">
                                <p class="description">One-time fee charged when a user submits a featured guest blog.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <?php submit_button( 'Save Blog Settings' ); ?>
            </form>
        </div>
    </div>

    <!-- Magazine Pricing Panel -->
    <div id="magazine-panel" class="adv-settings-panel">
        <div class="adv-admin-card">
            <h2>Magazine Advert Pricing</h2>
            <form method="post">
                <?php wp_nonce_field( 'adv_mag_settings_save', 'adv_mag_settings_nonce' ); ?>
                <table class="form-table" role="presentation">
                    <tbody>
                        <?php foreach ( ADV_Magazine_Settings::PRICES as $key => $cfg ) : ?>
                        <tr>
                            <th scope="row">
                                <label for="adv_mag_<?php echo esc_attr( $key ); ?>">
                                    <?php echo esc_html( $cfg['label'] ); ?> (£)
                                </label>
                            </th>
                            <td>
                                <input type="number"
                                       id="adv_mag_<?php echo esc_attr( $key ); ?>"
                                       name="prices[<?php echo esc_attr( $key ); ?>]"
                                       value="<?php echo esc_attr( ADV_Magazine_Settings::get_price( $key ) ); ?>"
                                       min="0" step="0.01" class="regular-text">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php submit_button( 'Save Magazine Pricing' ); ?>
            </form>
        </div>
    </div>

    <!-- Banner Pricing Panel -->
    <div id="banner-panel" class="adv-settings-panel">
        <div class="adv-admin-card">
            <h2>Homepage Banner Settings & Pricing</h2>
            <p>Configure monthly pricing and required image dimensions (Width × Height in pixels) for homepage banners.</p>
            <form method="post">
                <?php wp_nonce_field( 'adv_banner_settings_save', 'adv_banner_settings_nonce' ); ?>
                <table class="widefat fixed striped" style="max-width: 900px; margin-top: 15px;">
                    <thead>
                        <tr>
                            <th style="width: 200px;">Banner Size</th>
                            <th style="width: 150px;">Monthly Price (£)</th>
                            <th style="width: 150px;">Required Width (px)</th>
                            <th style="width: 150px;">Required Height (px)</th>
                            <th style="width: 150px;">Available Slots</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( ADV_Banner_Settings::BANNERS as $key => $cfg ) : ?>
                        <tr>
                            <td><strong><?php echo esc_html( $cfg['label'] ); ?></strong></td>
                            <td>
                                <input type="number"
                                       id="adv_banner_<?php echo esc_attr( $key ); ?>"
                                       name="prices[<?php echo esc_attr( $key ); ?>]"
                                       value="<?php echo esc_attr( ADV_Banner_Settings::get_price( $key ) ); ?>"
                                       min="0" step="0.01" class="small-text" style="width: 100%;">
                            </td>
                            <td>
                                <input type="number"
                                       id="adv_banner_w_<?php echo esc_attr( $key ); ?>"
                                       name="prices[width_<?php echo esc_attr( $key ); ?>]"
                                       value="<?php echo esc_attr( ADV_Banner_Settings::get_width( $key ) ); ?>"
                                       min="50" step="1" class="small-text" style="width: 100%;">
                            </td>
                            <td>
                                <input type="number"
                                       id="adv_banner_h_<?php echo esc_attr( $key ); ?>"
                                       name="prices[height_<?php echo esc_attr( $key ); ?>]"
                                       value="<?php echo esc_attr( ADV_Banner_Settings::get_height( $key ) ); ?>"
                                       min="50" step="1" class="small-text" style="width: 100%;">
                            </td>
                            <td>
                                <input type="number"
                                       id="adv_banner_slots_<?php echo esc_attr( $key ); ?>"
                                       name="prices[slots_<?php echo esc_attr( $key ); ?>]"
                                       value="<?php echo esc_attr( ADV_Banner_Settings::get_slots( $key ) ); ?>"
                                       min="1" step="1" class="small-text" style="width: 100%;">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php submit_button( 'Save Banner Settings' ); ?>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('adv-add-feature').addEventListener('click', function() {
    const wrap = document.getElementById('adv-features-wrap');
    const row  = document.createElement('div');
    row.className = 'adv-feature-row';
    row.innerHTML = `<input type="text" name="plan_features[]" placeholder="e.g. Priority listing placement" class="regular-text">
                     <button type="button" class="button adv-remove-feature">✕</button>`;
    wrap.appendChild(row);
});

document.addEventListener('click', function(e) {
    if ( e.target.classList.contains('adv-remove-feature') ) {
        e.target.closest('.adv-feature-row').remove();
    }
});

// Migration modal functionality
function showMigrationModal(planId, planName) {
    const modal = document.getElementById('adv-migration-modal');
    const planNameDisplay = document.getElementById('migrate-plan-name');
    const fromPlanInput = document.getElementById('from_plan_id');
    
    planNameDisplay.textContent = planName;
    fromPlanInput.value = planId;
    modal.style.display = 'flex';
}

function closeMigrationModal() {
    document.getElementById('adv-migration-modal').style.display = 'none';
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    const modal = document.getElementById('adv-migration-modal');
    if ( modal && e.target === modal ) {
        closeMigrationModal();
    }
});

// Logo upload functionality
jQuery(document).ready(function($) {
    var mediaUploader;
    
    $('#adv-upload-logo-btn').on('click', function(e) {
        e.preventDefault();
        
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }
        
        mediaUploader = wp.media({
            title: 'Select Email Logo',
            button: {
                text: 'Use This Logo'
            },
            multiple: false
        });
        
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#adv_email_logo_id').val(attachment.id);
            $('#adv-logo-preview').html('<img src="' + attachment.url + '" alt="Email Logo" style="max-width: 200px; max-height: 80px; border: 1px solid #ddd; padding: 5px; background: #fff;">');
            $('#adv-remove-logo-btn').show();
        });
    });
    
    $('#adv-remove-logo-btn').on('click', function(e) {
        e.preventDefault();
        $('#adv_email_logo_id').val(0);
        $('#adv-logo-preview').html('');
        $(this).hide();
    });
});
</script>

<!-- Migration Modal -->
<div id="adv-migration-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:100000; align-items:center; justify-content:center;">
    <div style="background:#fff; padding:25px; border-radius:8px; max-width:500px; width:90%; box-shadow:0 4px 20px rgba(0,0,0,0.15);">
        <h2 style="margin-top:0; margin-bottom:15px;">Migrate Bookings</h2>
        <p style="margin-bottom:20px;">Migrate all active bookings from <strong id="migrate-plan-name"></strong> to another plan.</p>
        
        <form method="post">
            <?php wp_nonce_field( 'adv_migrate_plan', 'adv_migrate_plan_nonce' ); ?>
            <input type="hidden" name="from_plan_id" id="from_plan_id">
            
            <table class="form-table">
                <tr>
                    <th>Destination Plan *</th>
                    <td>
                        <select name="to_plan_id" required style="width:100%; max-width:300px;">
                            <option value="">Select a plan...</option>
                            <?php foreach ( $plans as $plan ) : ?>
                                <option value="<?php echo $plan->id; ?>"><?php echo esc_html( $plan->name ); ?> (£<?php echo number_format( $plan->price, 2 ); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Stripe Subscriptions</th>
                    <td>
                        <label>
                            <input type="checkbox" name="cancel_stripe" value="1">
                            Cancel existing Stripe subscriptions (recommended)
                        </label>
                        <p class="description">Users will need to subscribe to the new plan manually.</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit" style="margin-top:20px;">
                <button type="submit" class="button button-primary">Migrate Bookings</button>
                <button type="button" class="button" onclick="closeMigrationModal()">Cancel</button>
            </p>
        </form>
    </div>
</div>
