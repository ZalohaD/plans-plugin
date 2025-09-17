<?php
/**
 * Plans CPT Class
 *
 * Registers the Custom Post Type for Plans.
 *
 * @package Plans
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Plans_CPT {

    public function __construct() {
        $this->register_cpt();
    }

    public function register_cpt() {
        $labels = array(
            'name'                  => __( 'Plans', 'plans' ),
            'singular_name'         => __( 'Plan', 'plans' ),
            'menu_name'             => __( 'Plans', 'plans' ),
            'name_admin_bar'        => __( 'Plan', 'plans' ),
            'add_new'               => __( 'Add New', 'plans' ),
            'add_new_item'          => __( 'Add New Plan', 'plans' ),
            'new_item'              => __( 'New Plan', 'plans' ),
            'edit_item'             => __( 'Edit Plan', 'plans' ),
            'view_item'             => __( 'View Plan', 'plans' ),
            'all_items'             => __( 'All Plans', 'plans' ),
            'search_items'          => __( 'Search Plans', 'plans' ),
            'not_found'             => __( 'No plans found.', 'plans' ),
            'not_found_in_trash'    => __( 'No plans found in Trash.', 'plans' ),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => 2,
            'menu_icon'          => 'dashicons-portfolio',
            'supports'           => array( 'title' ),
        );

        register_post_type( 'plan', $args );
    }
}