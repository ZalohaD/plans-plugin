<?php
/**
 * Plans Shortcode Class
 *
 * Handles the display of pricing plans via shortcode with dynamic loading and pagination.
 * Provides AJAX functionality for loading plan data and supports both monthly and annual plans.
 *
 * @package Plans
 * @since   1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Plans_Shortcode
 *
 * Main shortcode handler for displaying pricing plans with pagination and AJAX functionality.
 */
class Plans_Shortcode {

    /**
     * Transient key for caching shortcode output
     *
     * @since 1.0.0
     * @var string
     */
    const TRANSIENT_KEY = 'plans_shortcode_output';

    /**
     * Constructor
     *
     * Initializes the shortcode and hooks into WordPress.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     *
     * @since 1.0.0
     * @return void
     */
    private function init_hooks() {
        add_shortcode( 'plans', array( $this, 'render_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_load_all_plans', array( $this, 'ajax_load_all_plans' ) );
        add_action( 'wp_ajax_nopriv_load_all_plans', array( $this, 'ajax_load_all_plans' ) );
    }

    /**
     * Enqueue required CSS and JavaScript assets
     *
     * @since 1.0.0
     * @return void
     */
    public function enqueue_assets() {
        // Enqueue styles
        wp_enqueue_style(
            'plans-css',
            PLANS_PLUGIN_URL . 'assets/styles/style.css',
            array(),
            PLANS_VERSION
        );

        // Enqueue scripts
        wp_enqueue_script(
            'plans-js',
            PLANS_PLUGIN_URL . 'assets/scripts/main.js',
            array(),
            PLANS_VERSION,
            true
        );

        // Localize script with AJAX data
        wp_localize_script(
            'plans-js',
            'plans_ajax',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'plans_nonce' ),
                'loading'  => __( 'Loading...', 'plans' ),
                'error'    => __( 'Error loading plans. Please try again.', 'plans' ),
                'retry'    => __( 'Try Again', 'plans' ),
            )
        );
    }

    /**
     * Render the plans shortcode
     *
     * @since 1.0.0
     * @param array $atts Shortcode attributes
     * @return string HTML output of the shortcode
     */
    public function render_shortcode( $atts ) {
        // Get settings from admin panel
        $admin_settings = Plans_Admin::get_settings();

        // Parse shortcode attributes with defaults from admin settings
        $atts = shortcode_atts(
            array(
                'cache'    => 'true',
                'limit'    => $admin_settings['default_limit'],
                'per_page' => $admin_settings['posts_per_page'],
            ),
            $atts,
            'plans'
        );

        // Get initial data for monthly plans
        $initial_data = $this->get_plans_data(
            array(
                'plan_type' => 'monthly',
                'page'      => 1,
                'limit'     => intval( $atts['limit'] ),
                'per_page'  => intval( $atts['per_page'] ),
            )
        );

        return $this->render_plans_html( $initial_data, $atts );
    }

    /**
     * AJAX handler for loading all plans at once (for frontend caching)
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_load_all_plans() {
        // Verify nonce for security
        check_ajax_referer( 'plans_nonce', 'nonce' );

        // Sanitize input parameters
        $per_page = intval( $_POST['per_page'] ?? 6 );
        $limit    = intval( $_POST['limit'] ?? -1 );

        // Get all monthly plans
        $monthly_data = $this->get_all_plans_data(
            array(
                'plan_type' => 'monthly',
                'per_page'  => $per_page,
                'limit'     => $limit,
            )
        );

        // Get all annual plans
        $annual_data = $this->get_all_plans_data(
            array(
                'plan_type' => 'annual',
                'per_page'  => $per_page,
                'limit'     => $limit,
            )
        );

        // Send success response with both plan types
        wp_send_json_success(
            array(
                'monthly' => $monthly_data,
                'annual'  => $annual_data,
            )
        );
    }

    /**
     * Get all plans for a specific type with pagination
     *
     * @since 1.0.0
     * @param array $args Query arguments
     * @return array Paginated plans data
     */
    private function get_all_plans_data( $args = array() ) {
        // Parse arguments with defaults
        $plan_type = $args['plan_type'] ?? 'monthly';
        $per_page  = intval( $args['per_page'] ?? 6 );
        $limit     = intval( $args['limit'] ?? -1 );

        // Build query arguments for counting total posts
        $count_query_args = array(
            'post_type'      => 'plan',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'meta_query'     => array(
                'relation' => 'AND',
                array(
                    'key'     => '_plans_is_enabled',
                    'value'   => 1,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ),
                array(
                    'key'     => '_plans_is_annual',
                    'value'   => 'annual' === $plan_type ? 1 : 0,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ),
            ),
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
        );

        // Execute count query
        $count_query = new WP_Query( $count_query_args );
        $total_posts = $count_query->found_posts;
        $total_pages = ceil( $total_posts / $per_page );

        // Build query for all plans
        $all_query_args                     = $count_query_args;
        $all_query_args['fields']           = '';
        $all_query_args['posts_per_page']   = -1 === $limit ? -1 : $limit;

        // Execute query for all plans
        $all_query = new WP_Query( $all_query_args );
        $all_plans = array_map(
            function( $post_id ) {
                return $this->get_single_plan_data( $post_id );
            },
            wp_list_pluck( $all_query->posts, 'ID' )
        );

        // Build paginated data structure
        $pages = array();
        for ( $page = 1; $page <= $total_pages; $page++ ) {
            $offset = ( $page - 1 ) * $per_page;

            // Build query arguments for this specific page
            $paged_query_args                   = $all_query_args;
            $paged_query_args['posts_per_page'] = $per_page;
            $paged_query_args['offset']         = $offset;

            // Execute paged query
            $paged_query = new WP_Query( $paged_query_args );

            // Get plan data for this page
            $page_plans = array_map(
                function( $post_id ) {
                    return $this->get_single_plan_data( $post_id );
                },
                wp_list_pluck( $paged_query->posts, 'ID' )
            );

            // Add page data if plans exist
            if ( ! empty( $page_plans ) ) {
                $pages[ $page ] = array(
                    'plans'      => $page_plans,
                    'pagination' => array(
                        'current_page' => $page,
                        'per_page'     => $per_page,
                        'total'        => $total_posts,
                        'pages'        => $total_pages,
                        'has_prev'     => $page > 1,
                        'has_next'     => $page < $total_pages,
                        'plan_type'    => $plan_type,
                    ),
                );
            }
        }

        return $pages;
    }

    /**
     * Get plans data with pagination
     *
     * @since 1.0.0
     * @param array $args Query arguments
     * @return array Plans data with pagination info
     */
    private function get_plans_data( $args = array() ) {
        // Parse arguments with defaults
        $plan_type = $args['plan_type'] ?? 'monthly';
        $page      = max( 1, intval( $args['page'] ?? 1 ) );
        $per_page  = intval( $args['per_page'] ?? 6 );
        $limit     = intval( $args['limit'] ?? -1 );
        $offset    = ( $page - 1 ) * $per_page;

        // Build WP_Query arguments
        $query_args = array(
            'post_type'      => 'plan',
            'posts_per_page' => -1 === $limit ? $per_page : min( $per_page, $limit ),
            'offset'         => $offset,
            'post_status'    => 'publish',
            'meta_query'     => array(
                'relation' => 'AND',
                array(
                    'key'     => '_plans_is_enabled',
                    'value'   => 1,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ),
                array(
                    'key'     => '_plans_is_annual',
                    'value'   => 'annual' === $plan_type ? 1 : 0,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ),
            ),
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
        );

        // Execute query
        $query = new WP_Query( $query_args );

        // Calculate pagination data
        $total_posts = $query->found_posts;
        $total_pages = ceil( $total_posts / $per_page );

        // Get plan data for each post
        $plans = array_map(
            function( $post_id ) {
                return $this->get_single_plan_data( $post_id );
            },
            wp_list_pluck( $query->posts, 'ID' )
        );

        // Return structured data
        return array(
            'plans'      => $plans,
            'pagination' => array(
                'current_page' => $page,
                'per_page'     => $per_page,
                'total'        => $total_posts,
                'pages'        => $total_pages,
                'has_prev'     => $page > 1,
                'has_next'     => $page < $total_pages,
                'plan_type'    => $plan_type,
            ),
        );
    }

    /**
     * Get data for a single plan
     *
     * @since 1.0.0
     * @param int $post_id Plan post ID
     * @return array Plan data
     */
    private function get_single_plan_data( $post_id ) {
        // Get custom fields
        $price_label = get_post_meta( $post_id, '_plans_custom_price_label', true );
        $price       = get_post_meta( $post_id, '_plans_price', true );

        // Build and return plan data array
        return array(
            'id'          => $post_id,
            'title'       => get_the_title( $post_id ),
            'price'       => $this->format_price( $price, $price_label ),
            'button_text' => get_post_meta( $post_id, '_plans_button_text', true ),
            'button_link' => get_post_meta( $post_id, '_plans_button_link', true ),
            'features'    => array_filter( (array) get_post_meta( $post_id, '_plans_features', true ) ),
            'is_starred'  => (bool) get_post_meta( $post_id, '_plans_is_starred', true ),
            'is_annual'   => (bool) get_post_meta( $post_id, '_plans_is_annual', true ),
        );
    }

    /**
     * Format price with custom label or default formatting
     *
     * @since 1.0.0
     * @param string|int $price        Raw price value
     * @param string     $custom_label Custom price label
     * @return string Formatted price string
     */
    private function format_price( $price, $custom_label ) {
        // Return custom label if provided
        if ( ! empty( $custom_label ) ) {
            return esc_html( $custom_label );
        }

        // Return "Free" for empty or non-numeric prices
        if ( empty( $price ) || ! is_numeric( $price ) ) {
            return esc_html__( 'Free', 'plans' );
        }

        // Format numeric price
        return '$' . number_format( floatval( $price ), 2 );
    }

    /**
     * Render the main plans HTML structure
     *
     * @since 1.0.0
     * @param array $plans_data Plans and pagination data
     * @param array $atts       Shortcode attributes
     * @return string HTML output
     */
    private function render_plans_html( $plans_data, $atts ) {
        ob_start();
        ?>
        <div class="plans-container"
             data-per-page="<?php echo esc_attr( $atts['per_page'] ); ?>"
             data-limit="<?php echo esc_attr( $atts['limit'] ); ?>"
             data-cache-enabled="<?php echo esc_attr( $atts['cache'] ); ?>">
            <?php echo $this->render_tabs(); ?>
            <?php echo $this->render_plans_content( $plans_data ); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the tab navigation for monthly/annual plans
     *
     * @since 1.0.0
     * @return string HTML output for tabs
     */
    private function render_tabs() {
        ob_start();
        ?>
        <div class="plans-tabs" role="tablist">
            <button class="plans-tab-button active"
                    data-tab="monthly"
                    role="tab"
                    aria-selected="true">
                <?php esc_html_e( 'Monthly', 'plans' ); ?>
            </button>
            <button class="plans-tab-button"
                    data-tab="annual"
                    role="tab"
                    aria-selected="false">
                <?php esc_html_e( 'Annual', 'plans' ); ?>
            </button>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the plans content wrapper with loading state
     *
     * @since 1.0.0
     * @param array $plans_data Plans and pagination data
     * @return string HTML output for plans content
     */
    private function render_plans_content( $plans_data ) {
        ob_start();
        ?>
        <div class="plans-content-wrapper">
            <div class="plans-loading" style="display: none;">
                <?php esc_html_e( 'Loading...', 'plans' ); ?>
            </div>
            <div id="plans-content" class="plans-content active">
                <?php echo $this->render_plan_cards( $plans_data['plans'] ); ?>
            </div>
            <div class="plans-pagination-wrapper">
                <?php echo $this->render_pagination( $plans_data['pagination'] ); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render individual plan cards
     *
     * @since 1.0.0
     * @param array $plans Array of plan data
     * @return string HTML output for plan cards
     */
    private function render_plan_cards( $plans ) {
        // Return empty message if no plans
        if ( empty( $plans ) ) {
            return '<p class="plans-empty-message">' .
                esc_html__( 'No plans available for this period.', 'plans' ) .
                '</p>';
        }

        ob_start();
        echo '<div class="plans-grid">';

        foreach ( $plans as $plan ) {
            // Build CSS classes for plan card
            $classes = array( 'plan-card' );
            if ( $plan['is_starred'] ) {
                $classes[] = 'plan-card--starred';
            }
            ?>
            <div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
                <?php if ( $plan['is_starred'] ) : ?>
                    <div class="plan-badge">
                        <?php esc_html_e( 'Recommended', 'plans' ); ?>
                    </div>
                <?php endif; ?>

                <h3 class="plan-title">
                    <?php echo esc_html( $plan['title'] ); ?>
                </h3>

                <div class="plan-price">
                    <?php echo esc_html( $plan['price'] ); ?>
                </div>

                <?php if ( ! empty( $plan['features'] ) ) : ?>
                    <ul class="plan-features">
                        <?php foreach ( $plan['features'] as $feature ) : ?>
                            <li class="plan-feature">
                                <?php echo esc_html( $feature ); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if ( ! empty( $plan['button_text'] ) && ! empty( $plan['button_link'] ) ) : ?>
                    <a href="<?php echo esc_url( $plan['button_link'] ); ?>"
                       class="plan-button">
                        <?php echo esc_html( $plan['button_text'] ); ?>
                    </a>
                <?php endif; ?>
            </div>
            <?php
        }

        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Render pagination controls
     *
     * @since 1.0.0
     * @param array $pagination Pagination data
     * @return string HTML output for pagination
     */
    private function render_pagination( $pagination ) {
        // Return empty string if only one page
        if ( $pagination['pages'] <= 1 ) {
            return '';
        }

        ob_start();
        ?>
        <div class="plans-pagination"
             data-current="<?php echo esc_attr( $pagination['current_page'] ); ?>"
             data-total="<?php echo esc_attr( $pagination['pages'] ); ?>"
             data-plan-type="<?php echo esc_attr( $pagination['plan_type'] ); ?>">

            <?php if ( $pagination['has_prev'] ) : ?>
                <button class="plans-page-btn plans-prev-btn"
                        data-page="<?php echo esc_attr( $pagination['current_page'] - 1 ); ?>">
                    <?php esc_html_e( '← Previous', 'plans' ); ?>
                </button>
            <?php endif; ?>

            <div class="plans-page-numbers">
                <?php
                // Calculate page number range to display
                $start = max( 1, $pagination['current_page'] - 2 );
                $end   = min( $pagination['pages'], $pagination['current_page'] + 2 );

                // Show first page and ellipsis if needed
                if ( $start > 1 ) {
                    echo '<button class="plans-page-btn" data-page="1">1</button>';
                    if ( $start > 2 ) {
                        echo '<span class="plans-page-dots">...</span>';
                    }
                }

                // Show page numbers in range
                for ( $i = $start; $i <= $end; $i++ ) {
                    $active_class = $i === $pagination['current_page'] ? ' active' : '';
                    printf(
                        '<button class="plans-page-btn%s" data-page="%d">%d</button>',
                        esc_attr( $active_class ),
                        esc_attr( $i ),
                        esc_html( $i )
                    );
                }

                // Show ellipsis and last page if needed
                if ( $end < $pagination['pages'] ) {
                    if ( $end < $pagination['pages'] - 1 ) {
                        echo '<span class="plans-page-dots">...</span>';
                    }
                    printf(
                        '<button class="plans-page-btn" data-page="%d">%d</button>',
                        esc_attr( $pagination['pages'] ),
                        esc_html( $pagination['pages'] )
                    );
                }
                ?>
            </div>

            <?php if ( $pagination['has_next'] ) : ?>
                <button class="plans-page-btn plans-next-btn"
                        data-page="<?php echo esc_attr( $pagination['current_page'] + 1 ); ?>">
                    <?php esc_html_e( 'Next →', 'plans' ); ?>
                </button>
            <?php endif; ?>
        </div>

        <div class="plans-pagination-info">
            <?php
            printf(
                esc_html__( 'Showing %d of %d plans', 'plans' ),
                min( $pagination['current_page'] * $pagination['per_page'], $pagination['total'] ),
                $pagination['total']
            );
            ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Clear cached shortcode output
     *
     * @since 1.0.0
     * @return void
     */
    public static function clear_cache() {
        delete_transient( self::TRANSIENT_KEY );
    }
}
