<?php defined( 'ABSPATH' ) || exit;
ADV_Plans::init();

// Handle delete
if ( isset( $_GET['delete_plan'] ) && check_admin_referer( 'adv_delete_plan' ) ) {
    $result = ADV_Plans::delete( intval( $_GET['delete_plan'] ) );
    if ( $result['success'] ) {
        echo '<div class="notice notice-success"><p>Plan deleted successfully.</p></div>';
    } else {
        echo '<div class="notice notice-error"><p>' . esc_html( $result['error'] ) . '</p></div>';
    }
}

// Handle sync
if ( isset( $_GET['sync_plan'] ) && check_admin_referer( 'adv_sync_plan' ) ) {
    $sync_res = ADV_Plans::sync( intval( $_GET['sync_plan'] ) );
    if ( $sync_res['success'] ) {
        echo '<div class="notice notice-success"><p>Plan synced with Stripe successfully!</p></div>';
    } else {
        echo '<div class="notice notice-error"><p>Failed to sync with Stripe: ' . esc_html( $sync_res['error'] ) . '</p></div>';
    }
}

// Handle migration
if ( isset( $_POST['adv_migrate_plan_nonce'] ) && wp_verify_nonce( $_POST['adv_migrate_plan_nonce'], 'adv_migrate_plan' ) ) {
    $from_plan = intval( $_POST['from_plan_id'] );
    $to_plan = intval( $_POST['to_plan_id'] );
    $cancel_stripe = isset( $_POST['cancel_stripe'] );
    
    $result = ADV_Plans::migrate_bookings( $from_plan, $to_plan, $cancel_stripe );
    
    if ( $result['success'] ) {
        echo '<div class="notice notice-success"><p>Successfully migrated ' . intval( $result['migrated_count'] ) . ' booking(s) to the new plan.</p></div>';
        if ( ! empty( $result['errors'] ) ) {
            echo '<div class="notice notice-warning"><p>Some errors occurred: ' . esc_html( implode( ', ', $result['errors'] ) ) . '</p></div>';
        }
    } else {
        echo '<div class="notice notice-error"><p>Migration failed: ' . esc_html( $result['error'] ) . '</p></div>';
    }
}

// Handle save
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
            echo '<div class="notice notice-success"><p>' . $message . '</p></div>';
        } else {
            global $wpdb;
            $db_err = $wpdb->last_error ? esc_html( $wpdb->last_error ) : 'Unknown database error.';
            echo '<div class="notice notice-error"><p>Failed to update plan. DB error: ' . $db_err . '</p></div>';
        }
    } else {
        $new_id = ADV_Plans::create( $data );
        if ( $new_id ) {
            echo '<div class="notice notice-success"><p>Plan created successfully (ID: ' . intval( $new_id ) . ').</p></div>';
        } else {
            global $wpdb;
            $db_err = $wpdb->last_error ? esc_html( $wpdb->last_error ) : 'Unknown database error.';
            echo '<div class="notice notice-error"><p>Failed to save plan. DB error: ' . $db_err . '</p></div>';
        }
    }
}

$plans      = ADV_Plans::get_all( false );
$edit_plan  = isset( $_GET['edit_plan'] ) ? ADV_Plans::get( intval( $_GET['edit_plan'] ) ) : null;
?>

<div class="wrap adv-admin">
    <h1>Pricing Plans</h1>

    <!-- ── Plan Form ───────────────────────────────── -->
    <div class="adv-admin-card">
        <h2><?php echo $edit_plan ? 'Edit Plan' : 'Add New Plan'; ?></h2>

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
                        <a href="<?php echo wp_nonce_url( '?page=adv-plans&sync_plan=' . $edit_plan->id, 'adv_sync_plan' ); ?>" class="button button-secondary button-small">
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
                        <br><br>
                        <label>
                            <input type="checkbox" name="migrate_subscriptions" value="1">
                            <strong>Migrate existing subscriptions</strong> to new price/billing cycle
                        </label>
                        <p class="description">
                            Only applies when changing price or billing cycle. Existing subscribers will be moved to the new pricing automatically.
                        </p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary">
                    <?php echo $edit_plan ? 'Update Plan' : 'Create Plan'; ?>
                </button>
                <?php if ( $edit_plan ) : ?>
                    <a href="?page=adv-plans" class="button">Cancel</a>
                <?php endif; ?>
            </p>
        </form>
    </div>

    <!-- ── Plans Table ─────────────────────────────── -->
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
                        <a href="?page=adv-plans&edit_plan=<?php echo $plan->id; ?>">Edit</a> |
                        <?php if ( empty( $plan->stripe_price_id ) ) : ?>
                            <a href="<?php echo wp_nonce_url( '?page=adv-plans&sync_plan=' . $plan->id, 'adv_sync_plan' ); ?>" style="color:#d97706; font-weight:600;">Sync Stripe</a> |
                        <?php endif; ?>
                        <?php if ( $active_bookings > 0 ) : ?>
                            <a href="#" onclick="showMigrationModal(<?php echo $plan->id; ?>, '<?php echo esc_js( $plan->name ); ?>'); return false;" style="color:#059669; font-weight:600;">Migrate</a> |
                        <?php endif; ?>
                        <a href="<?php echo wp_nonce_url( '?page=adv-plans&delete_plan=' . $plan->id, 'adv_delete_plan' ); ?>"
                           onclick="<?php echo $active_bookings > 0 ? 'alert(\'This plan has active bookings. Please migrate them first.\'); return false;' : 'return confirm(\'Delete this plan?\')'; ?>">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
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
document.getElementById('adv-migration-modal').addEventListener('click', function(e) {
    if ( e.target === this ) {
        closeMigrationModal();
    }
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