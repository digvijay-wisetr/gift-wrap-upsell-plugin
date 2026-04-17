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

function gwu_register_view_pages() {                                                                                                                       
      add_submenu_page(
          'edit.php?post_type=gift_wrap_option',
          __( 'All Wraps', 'gift-wrap' ),
          __( 'All Wraps', 'gift-wrap' ),                                                                                                                      
          'edit_posts',
          'gwu-wrap-view',                                                                                                                                     
          'gwu_render_view_page'                                                                                                                               
      );
}
add_action( 'admin_menu', 'gwu_register_view_pages' );



// add_filter( 'manage_gift_wrap_option_posts_columns', function ( $cols ) {                                                              
//       $cols['surcharge'] = __( 'Surcharge', 'gift-wrap' );                                                                               
//       $cols['is_active'] = __( 'Active',    'gift-wrap' );
//       $cols['expiry']    = __( 'Expires',   'gift-wrap' );                                                                               
//       return $cols;                                                                                                                      
//   } );       
  
  
                                                                                                                                         
// add_action( 'manage_gift_wrap_option_posts_custom_column', function ( $col, $post_id ) {                                               
//     switch ( $col ) {
//         case 'surcharge': echo esc_html( number_format_i18n( (float) get_post_meta( $post_id, 'surcharge', true ), 2 ) ); break;       
//         case 'is_active': echo esc_html( get_post_meta( $post_id, 'is_active', true ) ? '✓' : '—' ); break;                                        
//         case 'expiry':    echo esc_html( (string) get_post_meta( $post_id, 'expiry_date', true ) ); break;                             
//     }                                                                                                                                  
// }, 10, 2 ); 



function gwu_handle_form_submit() {

    // 1. Capability check
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_die( __( 'Unauthorized', 'gift-wrap' ) );
        exit;
    }

    // 2. Nonce check
    check_admin_referer( 'gwu_save_wrap_nonce' );

    // 3. Sanitize input
    $back_url = admin_url( 'admin.php?page=gwu-wraps' );
    $title   = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ));
    if ( $title === '' ) {
      wp_safe_redirect( add_query_arg( 'error', 'missing_title', $back_url ) );                                                          
      exit;                                                                                                                              
    }                                                                                                                                      
   
    $price   = gwu_sanitize_float( wp_unslash( $_POST['surcharge'] ?? 0 ));

    $active  = isset( $_POST['is_active'] ) ? 1 : 0;

    $expiry  = sanitize_text_field( wp_unslash( $_POST['expiry_date'] ?? '' ) );
    if ( $expiry !== '' ) {                                                                                                                
      $dt = DateTime::createFromFormat( 'Y-m-d', $expiry );
      if ( ! $dt || $dt->format( 'Y-m-d' ) !== $expiry ) {                                                                               
          $expiry = ''; // or wp_die with an error
      }                                                                                                                                  
    }

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
    wp_safe_redirect( admin_url( 'admin.php?page=gwu-wraps&success=1' ) );
    exit;
}

add_action( 'admin_post_gwu_save_wrap', 'gwu_handle_form_submit' );



// function for rendering the list of wraper inside view wraper sub menu
 function gwu_render_view_page() {
    $post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
                                                                                                                                                               
      if ( $post_id > 0 ) {
          gwu_render_single_wrap( $post_id );
          return;                                                                                                                                              
      }                                                                                                                                                    
      // No post_id — show the list table
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__( 'All Gift Wraps', 'gift-wrap' ); ?></h1>                                                                                  
        <?php
        $table = new GWU_Wraps_Table();                                                                                                                      
        $table->prepare_items();
        $table->views();  
        $table->display();   
                                                                                                                                      
        ?>      
    </div>
                                                                                                                                                       
<?php                                                                                                                                                    
} 

// Function for rendering the single wraper details
function gwu_render_single_wrap( $post_id ) {                                                                                                                
                  
      if ( ! current_user_can( 'edit_posts' ) ) {                                                                                                              
          wp_die( esc_html__( 'Unauthorized', 'gift-wrap' ) );
      }                                                                                                                                                        
                  
      $post = get_post( $post_id );                                                                                                                            
   
      if ( ! $post instanceof WP_Post || $post->post_type !== 'gift_wrap_option' ) {                                                                           
          wp_die( esc_html__( 'Wrap not found.', 'gift-wrap' ) );
      }                                                                                                                                                        
   
      $surcharge = get_post_meta( $post_id, 'surcharge', true );                                                                                               
      $is_active = get_post_meta( $post_id, 'is_active', true );
      $expiry    = get_post_meta( $post_id, 'expiry_date', true );                                                                                             
      $image     = get_the_post_thumbnail_url( $post_id, 'medium' );
                                                                                                                                                               
      $back_url = admin_url( 'admin.php?page=gwu-wrap-view' );
      ?>                                                                                                                                                       
      <div class="wrap">
          <a href="<?php echo esc_url( $back_url ); ?>" class="page-title-action">                                                                             
              &larr; <?php esc_html_e( 'Back to All Wraps', 'gift-wrap' ); ?>
          </a>                                                                                                                                                 
                  
          <h1><?php echo esc_html( get_the_title( $post ) ); ?></h1>                                                                                           
                                                                                                                                                               
          <?php if ( $image ) : ?>
              <img src="<?php echo esc_url( $image ); ?>" style="max-width:300px;">                                                                            
          <?php endif; ?>

          <table class="form-table">
              <tr>                                                                                                                                             
                  <th><?php esc_html_e( 'Surcharge', 'gift-wrap' ); ?></th>
                  <td><?php echo esc_html( number_format_i18n( (float) $surcharge, 2 ) ); ?></td>                                                              
              </tr>                                                                                                                                            
              <tr>
                  <th><?php esc_html_e( 'Active', 'gift-wrap' ); ?></th>                                                                                       
                  <td><?php echo esc_html( $is_active ? __( 'Yes', 'gift-wrap' ) : __( 'No', 'gift-wrap' )); ?></td>                                                                                
              </tr>
              <tr>                                                                                                                                             
                  <th><?php esc_html_e( 'Expiry', 'gift-wrap' ); ?></th>
                  <td><?php echo esc_html( $expiry ?: '—' ); ?></td>                                                                                           
              </tr>
          </table>                                                                                                                                             
                                                                                                                                                               
          <a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>" class="button button-primary">
              <?php esc_html_e( 'Edit', 'gift-wrap' ); ?>                                                                                                      
          </a>                                                                                                                                                 
      </div>
<?php                                                                                                                                                    
}   
 

// function for rending the form inside the admin settings
function gwu_render_admin_page() {
?>
    <div class="wrap">
        <h1><?php echo esc_html__( 'Gift Wrap Options', 'gift-wrap' ); ?></h1>

        <?php if ( isset( $_GET['success'] )  && $_GET['success'] === '1' ): ?>
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
                    <th><label for="gwu_title"><?php echo esc_html__( 'Title', 'gift-wrap' ); ?></label></th>
                    <td><input id="gwu_title" type="text" name="title"  value="" required></td>
                </tr>

                <tr>
                    <th><label for="gwu_surcharge"><?php echo esc_html__( 'Surcharge', 'gift-wrap' ); ?></label></th>
                    <td><input id="gwu_surcharge" type="number" name="surcharge" step="0.01"></td>
                </tr>

                <tr>
                    <th><label for="is_active"><?php echo esc_html__( 'Active', 'gift-wrap' ); ?></label></th>
                    <td><input id="is_active" type="checkbox" name="is_active"></td>
                </tr>

                <tr>
                    <th><label for="expiry_date"><?php echo esc_html__( 'Expiry Date', 'gift-wrap' ); ?></label></th>
                    <td><input id="expiry_date" type="date" name="expiry_date"></td>
                </tr>
                <tr>
                    <th><label for="gwu_image_id"><?php echo esc_html__( 'Image', 'gift-wrap' ); ?></label></th>
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