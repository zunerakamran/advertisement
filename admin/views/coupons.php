<?php defined( 'ABSPATH' ) || exit;
ADV_Coupons::init();

// Handle delete
if ( isset( $_GET['delete_coupon'] ) && check_admin_referer( 'adv_delete_coupon' ) ) {
    ADV_Coupons::delete( intval( $_GET['delete_coupon'] ) );
    echo '<div class="notice notice-success"><p>Coupon deleted.</p></div>';
}

// Handle create
if ( isset( $_POST['adv_save_coupon_nonce'] ) && wp_verify_nonce( $_POST['adv_save_coupon_nonce'], 'adv_save_coupon' ) ) {
    ADV_Coupons::create([
        'code'       => $_POST['coupon_code'],
        'trial_days' => $_POST['trial_days'],
        'max_uses'   => $_POST['max_uses'] ?? null,
        'expires_at' => $_POST['expires_at'] ?? null,
    ]);
    echo '<div class="notice notice-success"><p>Coupon created.</p></div>';
}

$coupons = ADV_Coupons::get_all();
?>

<div class="wrap adv-admin">
    <h1>Coupons</h1>

    <div class="adv-admin-card">
        <h2>Create Coupon</h2>
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
                        <a href="<?php echo wp_nonce_url( '?page=adv-coupons&delete_coupon=' . $c->id, 'adv_delete_coupon' ); ?>"
                           onclick="return confirm('Delete this coupon?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>