<?php
/**
 * Plugin Name: Plans
 * Plugin URI: https://example.com/plans
 * Description: Плагін для створення Custom Post Type "Plan" та шорткоду для виведення планів у табах Monthly/Annual.
 * Version: 1.0.0
 * Author: Zaloha Denys
 * Author URI: https://github.com/ZalohaD
 * Text Domain: plans
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Define constants.
define( 'PLANS_VERSION', '1.0.0' );
define( 'PLANS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PLANS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load plugin textdomain.
add_action( 'init', 'plans_load_textdomain' );
function plans_load_textdomain() {
    load_plugin_textdomain( 'plans', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

// Include classes.
require_once PLANS_PLUGIN_DIR . 'includes/class-plans-cpt.php';
require_once PLANS_PLUGIN_DIR . 'includes/class-plans-metaboxes.php';
require_once PLANS_PLUGIN_DIR . 'includes/class-plans-shortcode.php';
require_once PLANS_PLUGIN_DIR . 'includes/class-plans-admin.php';

class Plans_Plugin {

    public function __construct() {
        add_action( 'init', array( $this, 'init_components' ) );
        add_action( 'save_post_plan', array( $this, 'clear_cache_on_save' ) );
        register_activation_hook( __FILE__, array( $this, 'activation_hook' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivation_hook' ) );
    }

    public function init_components() {
        new Plans_CPT();
        new Plans_Metaboxes();
        new Plans_Shortcode();
        new Plans_Admin();
    }

    public function clear_cache_on_save( $post_id ) {
        Plans_Shortcode::clear_cache();
    }

    public function activation_hook() {
        flush_rewrite_rules();
    }

    public function deactivation_hook() {
        flush_rewrite_rules();
    }
}

new Plans_Plugin();