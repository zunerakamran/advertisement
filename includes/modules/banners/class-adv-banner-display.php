<?php
defined( 'ABSPATH' ) || exit;

/**
 * [goss_homepage_banner]                       -> outputs slider of all live homepage banners
 * [goss_homepage_banner size="large"]          -> outputs slider of only large banners
 * [goss_homepage_banner autoplay="true" interval="5000"]
 */
class ADV_Banner_Display {

    public function __construct() {
        add_shortcode( 'goss_homepage_banner', [ $this, 'render' ] );
    }

    public function render( $atts ) {
        $atts = shortcode_atts( [
            'size'     => '',     // large | medium | small | '' (all)
            'autoplay' => 'true', // true | false
            'interval' => '5000', // milliseconds per slide
            'slider'   => 'true', // true | false
        ], $atts, 'goss_homepage_banner' );

        wp_enqueue_style(  'adv-banner-display', ADV_URL . 'public/assets/banner-display.css', [], ADV_VERSION );
        wp_enqueue_script( 'adv-banner-display', ADV_URL . 'public/assets/banner-display.js',  [], ADV_VERSION, true );

        $banners = self::get_live_banners( $atts['size'] );
        if ( empty( $banners ) ) {
            return '';
        }

        $total_slides = count( $banners );
        $autoplay_attr = ( $atts['autoplay'] === 'true' && $atts['slider'] === 'true' && $total_slides > 1 )
            ? intval( $atts['interval'] )
            : 0;

        $output  = '<div class="adv-banner-slider-wrap" data-autoplay="' . esc_attr( $autoplay_attr ) . '" data-slider="' . esc_attr( $atts['slider'] ) . '">';
        $output .= '<div class="adv-banner-slider-track">';

        foreach ( $banners as $banner ) {
            $image_id = get_post_meta( $banner->ID, '_adv_banner_image_id', true );
            // Get original image URL to avoid WordPress resizing
            $img_url  = $image_id ? wp_get_original_image_url( $image_id ) : '';
            if ( ! $img_url ) {
                $img_url = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : '';
            }
            $link_url = get_post_meta( $banner->ID, '_adv_banner_url', true );
            $business = get_post_meta( $banner->ID, '_adv_banner_business', true ) ?: $banner->post_title;
            $size     = get_post_meta( $banner->ID, '_adv_banner_size', true ) ?: 'large';

            if ( ! $img_url ) continue;

            $output .= sprintf(
                '<div class="adv-banner-slide adv-banner-%1$s">
                    <a href="%2$s" target="_blank" rel="noopener nofollow sponsored" title="%3$s">
                        <img src="%4$s" alt="%3$s" loading="lazy" width="100%%" height="auto">
                    </a>
                </div>',
                esc_attr( $size ),
                esc_url( $link_url ),
                esc_attr( $business ),
                esc_url( $img_url )
            );
        }

        $output .= '</div>'; // end track

        // Render controls if more than 1 slide and slider is enabled
        if ( $total_slides > 1 && $atts['slider'] !== 'false' ) {
            $output .= '<button type="button" class="adv-slider-prev" aria-label="Previous Slide">‹</button>';
            $output .= '<button type="button" class="adv-slider-next" aria-label="Next Slide">›</button>';
            $output .= '<div class="adv-slider-dots">';
            for ( $i = 0; $i < $total_slides; $i++ ) {
                $output .= '<span class="adv-slider-dot' . ( $i === 0 ? ' active' : '' ) . '" data-slide="' . $i . '"></span>';
            }
            $output .= '</div>';
        }

        $output .= '</div>'; // end wrap

        return $output;
    }

    /**
     * Get live (published and non-expired) banners for display.
     */
    public static function get_live_banners( $size = '' ) {
        $meta_query = [];
        if ( $size && isset( ADV_Banner_Settings::BANNERS[ $size ] ) ) {
            $meta_query[] = [
                'key'   => '_adv_banner_size',
                'value' => $size,
            ];
        }

        $args = [
            'post_type'   => 'adv_banner',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby'     => 'date',
            'order'       => 'DESC',
        ];

        if ( $meta_query ) {
            $args['meta_query'] = $meta_query;
        }

        $posts = get_posts( $args );
        $now   = current_time( 'mysql' );
        $valid = [];

        foreach ( $posts as $p ) {
            $exp = get_post_meta( $p->ID, '_adv_banner_expires_at', true );
            if ( $exp && $exp < $now ) {
                continue; // Skip expired banners
            }
            $valid[] = $p;
        }

        return $valid;
    }

    /**
     * Backward compatibility single banner fetch.
     */
    public static function get_live_banner( $size ) {
        $banners = self::get_live_banners( $size );
        return $banners ? $banners[0] : null;
    }
}