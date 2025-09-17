<?php
/**
 * Plans Admin Class
 *
 * Handles admin columns, quick edit for Plan CPT, and plugin settings.
 *
 * @package Plans
 * @since   1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Plans_Admin
 *
 * Manages admin interface for Plans plugin including columns, quick edit, and settings.
 */
class Plans_Admin {

    /**
     * Settings page slug
     *
     * @since 1.0.0
     * @var string
     */
    const SETTINGS_PAGE_SLUG = 'plans-settings';

    /**
     * Settings group name
     *
     * @since 1.0.0
     * @var string
     */
    const SETTINGS_GROUP = 'plans_settings_group';

    /**
     * Constructor
     *
     * Initializes admin hooks and functionality.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize WordPress admin hooks
     *
     * @since 1.0.0
     * @return void
     */
    private function init_hooks() {
        // Post columns and quick edit
        add_filter( 'manage_plan_posts_columns', array( $this, 'add_custom_columns' ) );
        add_action( 'manage_plan_posts_custom_column', array( $this, 'render_custom_columns' ), 10, 2 );
        add_action( 'quick_edit_custom_box', array( $this, 'quick_edit_fields' ), 10, 2 );
        add_action( 'save_post', array( $this, 'save_quick_edit' ) );

        // Settings page
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'wp_ajax_clear_plans_cache', array( $this, 'ajax_clear_cache' ) );
    }

    /**
     * Add custom columns to Plans post list
     *
     * @since 1.0.0
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public function add_custom_columns( $columns ) {
        $columns['is_starred'] = __( 'Starred', 'plans' );
        $columns['is_enabled'] = __( 'Enabled', 'plans' );
        return $columns;
    }

    /**
     * Render content for custom columns
     *
     * @since 1.0.0
     * @param string $column  Column name
     * @param int    $post_id Post ID
     * @return void
     */
    public function render_custom_columns( $column, $post_id ) {
        switch ( $column ) {
            case 'is_starred':
                $is_starred = get_post_meta( $post_id, '_plans_is_starred', true );
                echo $is_starred ? __( 'Yes', 'plans' ) : __( 'No', 'plans' );
                break;

            case 'is_enabled':
                $is_enabled = get_post_meta( $post_id, '_plans_is_enabled', true );
                echo $is_enabled ? __( 'Yes', 'plans' ) : __( 'No', 'plans' );
                break;
        }
    }

    /**
     * Add quick edit fields for Plan posts
     *
     * @since 1.0.0
     * @param string $column_name Column name
     * @param string $post_type   Post type
     * @return void
     */
    public function quick_edit_fields( $column_name, $post_type ) {
        if ( 'plan' !== $post_type ) {
            return;
        }

        switch ( $column_name ) {
            case 'is_starred':
            case 'is_enabled':
                wp_nonce_field( 'plans_quick_edit_nonce', 'plans_quick_edit_nonce' );
                ?>
                <fieldset class="inline-edit-col-right">
                    <div class="inline-edit-col">
                        <label>
                            <input type="checkbox" name="plan_<?php echo esc_attr( $column_name ); ?>" />
                            <span class="title">
								<?php echo esc_html( ucfirst( str_replace( 'is_', '', $column_name ) ) ); ?>
							</span>
                        </label>
                    </div>
                </fieldset>
                <?php
                break;
        }
    }

    /**
     * Save quick edit fields
     *
     * @since 1.0.0
     * @param int $post_id Post ID
     * @return void
     */
    public function save_quick_edit( $post_id ) {
        // Verify nonce
        if ( ! isset( $_POST['plans_quick_edit_nonce'] ) ||
            ! wp_verify_nonce( $_POST['plans_quick_edit_nonce'], 'plans_quick_edit_nonce' ) ) {
            return;
        }

        // Check post type
        if ( 'plan' !== get_post_type( $post_id ) ) {
            return;
        }

        // Update meta fields
        $is_starred = isset( $_POST['plan_is_starred'] ) ? 1 : 0;
        $is_enabled = isset( $_POST['plan_is_enabled'] ) ? 1 : 0;

        update_post_meta( $post_id, '_plans_is_starred', $is_starred );
        update_post_meta( $post_id, '_plans_is_enabled', $is_enabled );
    }

    /**
     * Add settings page to admin menu
     *
     * @since 1.0.0
     * @return void
     */
    public function add_settings_page() {
        add_options_page(
            __( 'Plans Settings', 'plans' ),           // Page title
            __( 'Plans', 'plans' ),                    // Menu title
            'manage_options',                          // Capability
            self::SETTINGS_PAGE_SLUG,                  // Menu slug
            array( $this, 'render_settings_page' )     // Callback
        );
    }

    /**
     * Register settings fields and sections
     *
     * @since 1.0.0
     * @return void
     */
    public function register_settings() {
        // Register setting
        register_setting(
            self::SETTINGS_GROUP,
            'plans_settings',
            array( $this, 'sanitize_settings' )
        );

        // Add settings section
        add_settings_section(
            'plans_display_section',
            __( 'Display Settings', 'plans' ),
            array( $this, 'display_section_callback' ),
            self::SETTINGS_PAGE_SLUG
        );

        // Add per page field
        add_settings_field(
            'posts_per_page',
            __( 'Plans per Page', 'plans' ),
            array( $this, 'posts_per_page_callback' ),
            self::SETTINGS_PAGE_SLUG,
            'plans_display_section'
        );

        // Add default limit field
        add_settings_field(
            'default_limit',
            __( 'Default Limit', 'plans' ),
            array( $this, 'default_limit_callback' ),
            self::SETTINGS_PAGE_SLUG,
            'plans_display_section'
        );

        // Add cache section
        add_settings_section(
            'plans_cache_section',
            __( 'Cache Settings', 'plans' ),
            array( $this, 'cache_section_callback' ),
            self::SETTINGS_PAGE_SLUG
        );

        // Add clear cache field
        add_settings_field(
            'clear_cache',
            __( 'Clear Cache', 'plans' ),
            array( $this, 'clear_cache_callback' ),
            self::SETTINGS_PAGE_SLUG,
            'plans_cache_section'
        );
    }

    /**
     * Sanitize settings input
     *
     * @since 1.0.0
     * @param array $input Raw input data
     * @return array Sanitized input data
     */
    public function sanitize_settings( $input ) {
        $sanitized = array();

        // Sanitize posts per page
        if ( isset( $input['posts_per_page'] ) ) {
            $sanitized['posts_per_page'] = max( 1, min( 50, intval( $input['posts_per_page'] ) ) );
        }

        // Sanitize default limit
        if ( isset( $input['default_limit'] ) ) {
            $limit = intval( $input['default_limit'] );
            $sanitized['default_limit'] = $limit <= 0 ? -1 : min( 100, $limit );
        }

        return $sanitized;
    }

    /**
     * Render settings page
     *
     * @since 1.0.0
     * @return void
     */
    public function render_settings_page() {
        // Check user capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Add settings updated message
        if ( isset( $_GET['settings-updated'] ) ) {
            add_settings_error(
                'plans_messages',
                'plans_message',
                __( 'Settings saved successfully!', 'plans' ),
                'success'
            );
        }

        // Show error/update messages
        settings_errors( 'plans_messages' );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <form action="options.php" method="post">
                <?php
                // Output security fields for the registered setting
                settings_fields( self::SETTINGS_GROUP );

                // Output setting sections and their fields
                do_settings_sections( self::SETTINGS_PAGE_SLUG );

                // Output save settings button
                submit_button( __( 'Save Settings', 'plans' ) );
                ?>
            </form>
        </div>

        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('#clear-cache-btn').on('click', function(e) {
                    e.preventDefault();

                    var button = $(this);
                    var originalText = button.text();

                    button.text('<?php echo esc_js( __( 'Clearing...', 'plans' ) ); ?>').prop('disabled', true);

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'clear_plans_cache',
                            nonce: '<?php echo wp_create_nonce( 'clear_plans_cache_nonce' ); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                button.text('<?php echo esc_js( __( 'Cache Cleared!', 'plans' ) ); ?>');
                                setTimeout(function() {
                                    button.text(originalText).prop('disabled', false);
                                }, 2000);
                            } else {
                                button.text('<?php echo esc_js( __( 'Error occurred', 'plans' ) ); ?>');
                                setTimeout(function() {
                                    button.text(originalText).prop('disabled', false);
                                }, 2000);
                            }
                        },
                        error: function() {
                            button.text('<?php echo esc_js( __( 'Error occurred', 'plans' ) ); ?>');
                            setTimeout(function() {
                                button.text(originalText).prop('disabled', false);
                            }, 2000);
                        }
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * Display section callback
     *
     * @since 1.0.0
     * @return void
     */
    public function display_section_callback() {
        echo '<p>' . esc_html__( 'Configure how plans are displayed on the frontend.', 'plans' ) . '</p>';
    }

    /**
     * Posts per page field callback
     *
     * @since 1.0.0
     * @return void
     */
    public function posts_per_page_callback() {
        $options = get_option( 'plans_settings', array() );
        $value = isset( $options['posts_per_page'] ) ? $options['posts_per_page'] : 6;
        ?>
        <input type="number"
               id="posts_per_page"
               name="plans_settings[posts_per_page]"
               value="<?php echo esc_attr( $value ); ?>"
               min="1"
               max="50"
               class="small-text" />
        <p class="description">
            <?php esc_html_e( 'Number of plans to display per page (1-50). Default: 6', 'plans' ); ?>
        </p>
        <?php
    }

    /**
     * Default limit field callback
     *
     * @since 1.0.0
     * @return void
     */
    public function default_limit_callback() {
        $options = get_option( 'plans_settings', array() );
        $value = isset( $options['default_limit'] ) ? $options['default_limit'] : 6;
        ?>
        <input type="number"
               id="default_limit"
               name="plans_settings[default_limit]"
               value="<?php echo esc_attr( $value ); ?>"
               min="-1"
               max="100"
               class="small-text" />
        <p class="description">
            <?php esc_html_e( 'Default total limit for plans. Use -1 for no limit, or 1-100 for specific limit. Default: 6', 'plans' ); ?>
        </p>
        <?php
    }

    /**
     * Cache section callback
     *
     * @since 1.0.0
     * @return void
     */
    public function cache_section_callback() {
        echo '<p>' . esc_html__( 'Manage plans cache to improve performance.', 'plans' ) . '</p>';
    }

    /**
     * Clear cache field callback
     *
     * @since 1.0.0
     * @return void
     */
    public function clear_cache_callback() {
        ?>
        <button type="button"
                id="clear-cache-btn"
                class="button button-secondary">
            <?php esc_html_e( 'Clear Plans Cache', 'plans' ); ?>
        </button>
        <p class="description">
            <?php esc_html_e( 'Clear all cached plans data. This will force fresh data loading on next request.', 'plans' ); ?>
        </p>
        <?php
    }

    /**
     * AJAX handler for clearing cache
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_clear_cache() {
        // Check nonce
        check_ajax_referer( 'clear_plans_cache_nonce', 'nonce' );

        // Check user capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Insufficient permissions.', 'plans' ) );
        }

        // Clear cache using the shortcode class method
        if ( class_exists( 'Plans_Shortcode' ) ) {
            Plans_Shortcode::clear_cache();
        }

        // Also clear any other transients that might be related
        delete_transient( 'plans_shortcode_output' );

        // Clear object cache if available
        if ( function_exists( 'wp_cache_flush' ) ) {
            wp_cache_flush();
        }

        wp_send_json_success( __( 'Cache cleared successfully!', 'plans' ) );
    }

    /**
     * Get default settings values
     *
     * @since 1.0.0
     * @return array Default settings
     */
    public static function get_default_settings() {
        return array(
            'posts_per_page' => 6,
            'default_limit'  => 6,
        );
    }

    /**
     * Get current settings with defaults
     *
     * @since 1.0.0
     * @return array Current settings
     */
    public static function get_settings() {
        $defaults = self::get_default_settings();
        $settings = get_option( 'plans_settings', array() );

        return wp_parse_args( $settings, $defaults );
    }

    /**
     * Get specific setting value
     *
     * @since 1.0.0
     * @param string $key     Setting key
     * @param mixed  $default Default value if setting not found
     * @return mixed Setting value
     */
    public static function get_setting( $key, $default = null ) {
        $settings = self::get_settings();

        return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
    }
}