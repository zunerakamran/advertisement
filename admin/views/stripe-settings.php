<?php defined( 'ABSPATH' ) || exit; ?>

<div class="wrap adv-admin">
    <h1>Stripe Settings</h1>

    <?php settings_errors( 'adv_stripe_settings' ); ?>

    <div class="adv-admin-card">

        <p style="color:#6b7280; margin-bottom:24px;">
            Get your API keys from
            <a href="https://dashboard.stripe.com/apikeys" target="_blank">Stripe Dashboard → Developers → API Keys</a>.
            Use <strong>Test</strong> keys while building, switch to <strong>Live</strong> when ready.
        </p>

        <form method="post" action="options.php">
            <?php settings_fields( 'adv_stripe_settings' ); ?>

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
            // Show connection status
            $secret = get_option('adv_stripe_secret_key');
            if ( $secret ) :
                $mode = get_option('adv_stripe_mode', 'test');
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