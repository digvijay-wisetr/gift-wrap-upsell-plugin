<?php
if ( ! defined( 'ABSPATH' ) ) exit;


function gwu_register_admin_page() {
   add_submenu_page(
        'edit.php?post_type=gift_wrap_option', // attach to CPT
        __( 'Manage Wraps', 'gift-wrap' ),
        __( 'Manage Wraps', 'gift-wrap' ),
        'manage_options',
        'gwu-wraps',
        'gwu_render_admin_page'
    );
}

add_action( 'admin_menu', 'gwu_register_admin_page' );


add_action( 'admin_post_gwu_save_wrap', 'gwu_handle_form_submit' );

function gwu_handle_form_submit() {

    // 1. Capability check
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'Unauthorized', 'gift-wrap' ) );
    }

    // 2. Nonce check
    check_admin_referer( 'gwu_save_wrap_nonce' );

    // 3. Sanitize input
    $title   = sanitize_text_field( $_POST['title'] ?? '' );
    $price   = floatval( $_POST['surcharge'] ?? 0 );
    $active  = isset( $_POST['is_active'] ) ? 1 : 0;
    $expiry  = sanitize_text_field( $_POST['expiry_date'] ?? '' );

    // 4. Insert post
    $post_id = wp_insert_post([
        'post_type'   => 'gift_wrap_option',
        'post_title'  => $title,
        'post_status' => 'publish',
    ]);

    if ( is_wp_error( $post_id ) ) {
        wp_die( __( 'Error creating wrap', 'gift-wrap' ) );
    }
    
    
    // 5. Save meta
    update_post_meta( $post_id, 'surcharge', $price );
    update_post_meta( $post_id, 'is_active', $active );
    update_post_meta( $post_id, 'expiry_date', $expiry );

    $image_id = isset( $_POST['image_id'] ) ? absint( $_POST['image_id'] ) : 0;
    if ( $image_id ) {

        // Validate it's an image
        if ( wp_attachment_is_image( $image_id ) ) {
            set_post_thumbnail( $post_id, $image_id );
        }
    }

    // 6. Redirect
    wp_redirect( admin_url( 'admin.php?page=gwu-wraps&success=1' ) );
    exit;
}


function gwu_render_admin_page() {

    ?>
    <div class="wrap">
        <h1><?php echo esc_html__( 'Gift Wrap Options', 'gift-wrap' ); ?></h1>

        <?php if ( isset( $_GET['success'] ) ): ?>
            <div class="notice notice-success">
                <p><?php echo esc_html__( 'Wrap added successfully.', 'gift-wrap' ); ?></p>
            </div>
        <?php endif; ?>

        <h2><?php echo esc_html__( 'Add New Wrap', 'gift-wrap' ); ?></h2>

        <form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            
            <?php wp_nonce_field( 'gwu_save_wrap_nonce' ); ?>
            <input type="hidden" name="action" value="gwu_save_wrap">

            <table class="form-table">
                <tr>
                    <th><label><?php echo esc_html__( 'Title', 'gift-wrap' ); ?></label></th>
                    <td><input type="text" name="title" required></td>
                </tr>

                <tr>
                    <th><label><?php echo esc_html__( 'Surcharge', 'gift-wrap' ); ?></label></th>
                    <td><input type="number" name="surcharge" step="0.01"></td>
                </tr>

                <tr>
                    <th><label><?php echo esc_html__( 'Active', 'gift-wrap' ); ?></label></th>
                    <td><input type="checkbox" name="is_active"></td>
                </tr>

                <tr>
                    <th><label><?php echo esc_html__( 'Expiry Date', 'gift-wrap' ); ?></label></th>
                    <td><input type="date" name="expiry_date"></td>
                </tr>
                <tr>
                    <th><label><?php echo esc_html__( 'Image', 'gift-wrap' ); ?></label></th>
                    <td>
                        <input type="hidden" name="image_id" id="gwu_image_id">

                        <button type="button" class="button" id="gwu_upload_btn">
                            <?php echo esc_html__( 'Select Image', 'gift-wrap' ); ?>
                        </button>

                        <button type="button" class="button" id="gwu_remove_btn" style="display:none;">
                            <?php echo esc_html__( 'Remove Image', 'gift-wrap' ); ?>
                        </button>

                        <div id="gwu_image_preview" style="margin-top:10px;"></div>
                    </td>
                </tr>
            </table>

            <?php submit_button( __( 'Save Wrap', 'gift-wrap' ) ); ?>

        </form>
    </div>
    <?php
}