<?php defined( 'ABSPATH' ) || exit; ?>

<div class="wrap">
    <h1>Homepage Banner Settings & Pricing</h1>
    <p>Configure monthly pricing and required image dimensions (Width × Height in pixels) for homepage banners.</p>

    <?php if ( isset( $_GET['saved'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p>Settings updated successfully.</p></div>
    <?php endif; ?>

    <form method="post" action="">
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