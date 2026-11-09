<?php defined( 'ABSPATH' ) || exit; ?>

<div class="wrap">
    <h1>Magazine Advert Pricing</h1>

    <?php if ( isset( $_GET['saved'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p>Prices updated.</p></div>
    <?php endif; ?>

    <form method="post" action="">
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

        <?php submit_button( 'Save Prices' ); ?>
    </form>
</div>