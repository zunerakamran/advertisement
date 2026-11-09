<?php
defined( 'ABSPATH' ) || exit;

class ADV_Listings_Shortcode {

    public function __construct() {
        add_shortcode( 'goss_listings',       [ $this, 'render_all_listings' ] );
        add_shortcode( 'goss_single_listing', [ $this, 'render_single_listing' ] );
        add_shortcode( 'goss_search_filter',  [ $this, 'render_search_filter' ] );

        // Automatically use custom single template for adv_listing CPT posts
        add_filter( 'single_template', [ $this, 'load_single_template' ] );
        // Automatically use custom category template for adv_category taxonomy archives
        add_filter( 'taxonomy_template', [ $this, 'load_category_template' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'pre_get_posts', [ $this, 'allow_non_published_single_queries' ] );
    }

    public function allow_non_published_single_queries( $query ) {
        if ( ! is_admin() && $query->is_main_query() ) {
            $post_type = $query->get( 'post_type' );
            if ( $query->is_single() || $query->is_singular( 'adv_listing' ) || $post_type === 'adv_listing' ) {
                if ( $post_type === 'adv_listing' || ( is_array( $post_type ) && in_array( 'adv_listing', $post_type, true ) ) ) {
                    $query->set( 'post_status', [ 'publish', 'pending', 'draft', 'suspended' ] );
                }
            }
        }
    }

    public function enqueue_assets() {
        global $post;
        if ( is_a( $post, 'WP_Post' ) ) {
            $has_shortcode = has_shortcode( $post->post_content, 'goss_listings' )
                          || has_shortcode( $post->post_content, 'goss_single_listing' );
            if ( $has_shortcode || is_singular( 'adv_listing' ) ) {
                wp_enqueue_style( 'adv-listings', ADV_URL . 'public/assets/listings.css', [], ADV_VERSION );
                wp_enqueue_script( 'adv-listings', ADV_URL . 'public/assets/listings.js', [], ADV_VERSION, true );
            }
        } elseif ( is_singular( 'adv_listing' ) ) {
            wp_enqueue_style( 'adv-listings', ADV_URL . 'public/assets/listings.css', [], ADV_VERSION );
            wp_enqueue_script( 'adv-listings', ADV_URL . 'public/assets/listings.js', [], ADV_VERSION, true );
        }
    }

    public function render_all_listings( $atts ) {
        wp_enqueue_style( 'adv-listings', ADV_URL . 'public/assets/listings.css', [], ADV_VERSION );
        wp_enqueue_script( 'adv-listings', ADV_URL . 'public/assets/listings.js', [], ADV_VERSION, true );
        ADV_Categories::init();
        ob_start();
        include ADV_PATH . 'public/views/listings/all-listings.php';
        return ob_get_clean();
    }

    public function render_single_listing( $atts ) {
        wp_enqueue_style( 'adv-listings', ADV_URL . 'public/assets/listings.css', [], ADV_VERSION );

        $post_id = intval( $_GET['id'] ?? get_the_ID() );
        if ( ! $post_id ) {
            return '<p style="text-align:center;padding:40px;">No listing specified.</p>';
        }

        $is_shortcode_render = true;
        ob_start();
        include ADV_PATH . 'public/views/listings/single-listing.php';
        return ob_get_clean();
    }

    public function render_search_filter( $atts ) {
        wp_enqueue_style( 'adv-listings', ADV_URL . 'public/assets/listings.css', [], ADV_VERSION );
        wp_enqueue_script( 'adv-listings', ADV_URL . 'public/assets/listings.js', [], ADV_VERSION, true );
        
        $atts = shortcode_atts( [
            'target_url' => '',
        ], $atts );

        $search_query = sanitize_text_field( $_GET['q'] ?? '' );
        $cat_filter   = intval( $_GET['cat'] ?? 0 );
        $categories   = ADV_Categories::get_all();
        $form_action  = ! empty( $atts['target_url'] ) ? esc_url( $atts['target_url'] ) : '';
        $reset_url    = ! empty( $atts['target_url'] ) ? esc_url( $atts['target_url'] ) : esc_url( strtok( $_SERVER['REQUEST_URI'], '?' ) );

        ob_start();
        ?>
        <div class="adv-directory-wrap">
            <div class="adv-filter-bar">
                <form method="get" action="<?php echo $form_action; ?>" class="adv-filter-form">
                <div class="adv-filter-field search-field">
                    <span class="adv-field-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                    </span>
                    <input type="text" name="q" value="<?php echo esc_attr( $search_query ); ?>" placeholder="Search business name or keyword...">
                </div>

                <div class="adv-filter-field select-field">
                    <span class="adv-field-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                        </svg>
                    </span>
                    <div class="adv-custom-dropdown">
                        <select name="cat" class="adv-native-select" style="display: none;">
                            <option value="">All Categories</option>
                            <?php foreach ( $categories as $c ) : ?>
                                <option value="<?php echo esc_attr( $c->term_id ); ?>" <?php selected( $cat_filter, $c->term_id ); ?>>
                                    <?php echo esc_html( $c->name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="adv-dropdown-trigger">
                            <span class="adv-dropdown-selected">All Categories</span>
                            <svg class="adv-dropdown-arrow" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#A07949" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </div>
                        <div class="adv-dropdown-options">
                            <div class="adv-dropdown-option" data-value="">All Categories</div>
                            <?php foreach ( $categories as $c ) : ?>
                                <div class="adv-dropdown-option<?php echo $cat_filter == $c->term_id ? ' active' : ''; ?>" data-value="<?php echo esc_attr( $c->term_id ); ?>">
                                    <?php echo esc_html( $c->name ); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <button type="submit" class="adv-filter-btn">Search Directory</button>

                <?php if ( $search_query || $cat_filter ) : ?>
                    <a href="<?php echo $reset_url; ?>" class="adv-reset-btn">Reset</a>
                <?php endif; ?>
            </form>
        </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function load_single_template( $single_template ) {
        global $post;

        if ( $post && $post->post_type === 'adv_listing' ) {
            wp_enqueue_style( 'adv-listings', ADV_URL . 'public/assets/listings.css', [], ADV_VERSION );
            return ADV_PATH . 'public/views/listings/single-listing.php';
        }

        return $single_template;
    }

    public function load_category_template( $template ) {
        if ( is_tax( 'adv_category' ) ) {
            wp_enqueue_style( 'adv-listings', ADV_URL . 'public/assets/listings.css', [], ADV_VERSION );
            return ADV_PATH . 'public/views/listings/category-archive.php';
        }
        return $template;
    }
}

