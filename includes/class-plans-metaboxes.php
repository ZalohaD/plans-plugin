<?php
/**
 * Plans Metaboxes Class
 *
 * Handles metaboxes for Plan CPT.
 *
 * @package Plans
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Plans_Metaboxes {

    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post_plan', array( $this, 'save_meta' ) );
    }

    public function add_meta_boxes() {
        add_meta_box(
            'plans_details',
            __( 'Plan Details', 'plans' ),
            array( $this, 'render_details_meta_box' ),
            'plan',
            'normal',
            'high'
        );
    }

    public function render_details_meta_box( $post ) {
        wp_nonce_field( 'plans_meta_nonce', 'plans_meta_nonce' );

        $price              = get_post_meta( $post->ID, '_plans_price', true );
        $custom_price_label = get_post_meta( $post->ID, '_plans_custom_price_label', true );
        $is_annual          = get_post_meta( $post->ID, '_plans_is_annual', true );
        $button_text        = get_post_meta( $post->ID, '_plans_button_text', true );
        $button_link        = get_post_meta( $post->ID, '_plans_button_link', true );
        $features           = get_post_meta( $post->ID, '_plans_features', true );
        $is_starred         = get_post_meta( $post->ID, '_plans_is_starred', true );
        $is_enabled         = get_post_meta( $post->ID, '_plans_is_enabled', true );

        $features = is_array( $features ) ? $features : [''];

        ?>
        <style>
            .plans-toggle {
                position: relative;
                display: inline-block;
                width: 40px;
                height: 20px;
                vertical-align: middle;
                margin-left: 10px;
            }

            .plans-toggle input {
                opacity: 0;
                width: 0;
                height: 0;
            }

            .plans-slider {
                position: absolute;
                cursor: pointer;
                top: 0; left: 0; right: 0; bottom: 0;
                background-color: #ccc;
                transition: .3s;
                border-radius: 20px;
            }

            .plans-slider:before {
                position: absolute;
                content: "";
                height: 14px;
                width: 14px;
                left: 3px;
                bottom: 3px;
                background-color: white;
                transition: .3s;
                border-radius: 50%;
            }

            .plans-toggle input:checked + .plans-slider {
                background-color: #0073aa; /* синій WP */
            }

            .plans-toggle input:checked + .plans-slider:before {
                transform: translateX(20px);
            }

            .feature-row {
                display: flex;
                align-items: center;
                margin-bottom: 5px;
            }

            .feature-row input[type="text"] {
                flex: 1;
                padding: 4px 6px;
                font-size: 14px;
                margin-right: 5px;
            }

            .remove-feature {
                background: #0073aa;
                color: #fff;
                border: none;
                padding: 5px 8px;
                cursor: pointer;
                border-radius: 3px;
                font-size: 14px;
                line-height: 1;
                display: flex;
                justify-content: center;
                align-items: center;
            }

            .add-feature {
                background: #0073aa;
                color: #fff;
                border: none;
                padding: 4px 8px;
                cursor: pointer;
                border-radius: 3px;
                margin-top: 5px;
            }
        </style>

        <p>
            <label for="plans_price"><?php esc_html_e( 'Price', 'plans' ); ?></label><br>
            <input type="number" id="plans_price" name="plans_price" value="<?php echo esc_attr( $price ); ?>" step="0.01" style="width:150px;" />
        </p>

        <p>
            <label for="plans_custom_price_label"><?php esc_html_e( 'Custom Price Label', 'plans' ); ?></label><br>
            <input type="text" id="plans_custom_price_label" name="plans_custom_price_label" value="<?php echo esc_attr( $custom_price_label ); ?>" style="width:300px;" />
        </p>

        <p>
            <label><?php esc_html_e( 'Is Annual?', 'plans' ); ?></label>
            <label class="plans-toggle">
                <input type="checkbox" id="plans_is_annual" name="plans_is_annual" <?php checked( $is_annual, true ); ?> />
                <span class="plans-slider"></span>
            </label>
        </p>

        <p>
            <label for="plans_button_text"><?php esc_html_e( 'Button Text', 'plans' ); ?></label><br>
            <input type="text" id="plans_button_text" name="plans_button_text" value="<?php echo esc_attr( $button_text ); ?>" style="width:300px;" />
        </p>

        <p>
            <label for="plans_button_link"><?php esc_html_e( 'Button Link', 'plans' ); ?></label><br>
            <input type="url" id="plans_button_link" name="plans_button_link" value="<?php echo esc_attr( $button_link ); ?>" style="width:300px;" />
        </p>

        <div id="features-wrapper">
            <label><?php esc_html_e( 'Features', 'plans' ); ?></label>
            <?php foreach ( $features as $feature ) : ?>
                <div class="feature-row">
                    <input type="text" name="plans_features[]" value="<?php echo esc_attr( $feature ); ?>" />
                    <button type="button" class="remove-feature">×</button>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="add-feature">+ Add Feature</button>

        <p>
            <label><?php esc_html_e( 'Is Starred?', 'plans' ); ?></label>
            <label class="plans-toggle">
                <input type="checkbox" id="plans_is_starred" name="plans_is_starred" <?php checked( $is_starred, true ); ?> />
                <span class="plans-slider"></span>
            </label>
        </p>

        <p>
            <label><?php esc_html_e( 'Is Enabled?', 'plans' ); ?></label>
            <label class="plans-toggle">
                <input type="checkbox" id="plans_is_enabled" name="plans_is_enabled" <?php checked( $is_enabled, true ); ?> />
                <span class="plans-slider"></span>
            </label>
        </p>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const wrapper = document.getElementById('features-wrapper');
                const addBtn = document.querySelector('.add-feature');

                addBtn.addEventListener('click', function() {
                    const row = document.createElement('div');
                    row.className = 'feature-row';
                    row.innerHTML = '<input type="text" name="plans_features[]" value="" />' +
                        '<button type="button" class="remove-feature">×</button>';
                    wrapper.appendChild(row);
                });

                wrapper.addEventListener('click', function(e) {
                    if(e.target.classList.contains('remove-feature')) {
                        e.target.parentNode.remove();
                    }
                });
            });
        </script>
        <?php
    }


    public function save_meta( $post_id ) {
        if ( ! isset( $_POST['plans_meta_nonce'] ) || ! wp_verify_nonce( $_POST['plans_meta_nonce'], 'plans_meta_nonce' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Sanitization and update.
        update_post_meta( $post_id, '_plans_price', floatval( $_POST['plans_price'] ) );
        update_post_meta( $post_id, '_plans_custom_price_label', sanitize_text_field( $_POST['plans_custom_price_label'] ) );
        update_post_meta( $post_id, '_plans_is_annual', isset( $_POST['plans_is_annual'] ) ? true : false );
        update_post_meta( $post_id, '_plans_button_text', sanitize_text_field( $_POST['plans_button_text'] ) );
        update_post_meta( $post_id, '_plans_button_link', esc_url_raw( $_POST['plans_button_link'] ) );

        $features = isset( $_POST['plans_features'] ) ? array_map( 'sanitize_text_field', $_POST['plans_features'] ) : array();
        update_post_meta( $post_id, '_plans_features', $features );

        update_post_meta( $post_id, '_plans_is_starred', isset( $_POST['plans_is_starred'] ) ? true : false );
        update_post_meta( $post_id, '_plans_is_enabled', isset( $_POST['plans_is_enabled'] ) ? true : false );
    }
}
?>
