<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

// we are using the wordpress default class for listing the table WP List Table

class GWU_Wraps_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'wrap',
            'plural'   => 'wraps',
            'ajax'     => false,
        ]);
    }
    
    // we Define our columns
    public function get_columns() {
        return [
            'title'     => __( 'Title', 'gift-wrap-upsell-plugin' ),
            'surcharge' => __( 'Surcharge', 'gift-wrap-upsell-plugin' ),
            'is_active' => __( 'Active', 'gift-wrap-upsell-plugin' ),
            'expiry'    => __( 'Expiry', 'gift-wrap-upsell-plugin' ),
            'preview'   => __( 'Preview', 'gift-wrap-upsell-plugin' ),
        ];
    }

    protected function column_preview( $item ) {                                                                                                                 
      return '<div class="gwu-preview-container" id="gwu-preview-' . esc_attr( $item['id'] ) . '"></div>';
    }

    protected function column_title( $item ) {

        $post_id = $item['id'];

        if ( isset( $item['status'] ) && $item['status'] === 'trash' ) {                                                                                         
                  
          // Trashed posts get Restore and Delete Permanently                                                                                                  
          $restore_url = wp_nonce_url(
              admin_url( 'post.php?action=untrash&post=' . $post_id ),                                                                                         
              'untrash-post_' . $post_id                                                                                                                       
          );                                                                                                                                                   
                                                                                                                                                               
          $delete_url = get_delete_post_link( $post_id, '', true ); // true = force delete                                                                     
                  
          $actions = [                                                                                                                                         
              'untrash' => '<a href="' . esc_url( $restore_url ) . '">'
                         . esc_html__( 'Restore', 'gift-wrap-upsell-plugin' ) . '</a>',                                                                                      
              'delete'  => '<a href="' . esc_url( $delete_url ) . '" style="color:#b32d2e;">'
                         . esc_html__( 'Delete Permanently', 'gift-wrap-upsell-plugin' ) . '</a>',                                                                           
          ];      
                                                                                                                                                               
          return esc_html( $item['title'] ) . $this->row_actions( $actions );                                                                                  
        }
        // Points to the "View Wrap" submenu page with post_id.                                                                                                  
        // admin.php?page= routes through WP's admin page handler,
        // so the URL respects capability checks registered with add_submenu_page(). 
        $view_url = add_query_arg([
            'page' => 'gwu-wrap-view',
            'post_id' => $post_id,
            '_wpnonce' => wp_create_nonce( 'gwu_view_wrap_' . $post_id ),
        ], admin_url('admin.php'));
        
        // get_edit_post_link() and get_delete_post_link() return URLs that                                                                                      
        // already contain a nonce — WP verifies by it on.
        $edit_url = get_edit_post_link( $post_id );

        $delete_url = get_delete_post_link( $post_id );

        $actions = [
            'preview' => '<a href="#" class="gwu-preview-btn" data-wrap-id="' . esc_attr( $post_id ) . '">'
                 . esc_html__( 'Preview', 'gift-wrap-upsell-plugin' ) . '</a>',  
            'view'   => '<a href="' . esc_url( $view_url ) . '">' . esc_html__( 'View', 'gift-wrap-upsell-plugin' ) . '</a>',
            'edit'   => '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'gift-wrap-upsell-plugin' ) . '</a>',
            'delete' => '<a href="' . esc_url( $delete_url ) . '">' . esc_html__( 'Delete', 'gift-wrap-upsell-plugin' ) . '</a>',
        ];

        // row_actions() renders the hover action links below the title,                                                                                         
        // matching the pattern used by WP core list tables (Posts, Pages, etc.).
        return esc_html( $item['title'] ) . $this->row_actions( $actions );
    }

    protected function column_surcharge( $item ) {
        return esc_html( number_format_i18n( $item['surcharge'], 2 ) );
    }

    protected function column_is_active( $item ) {
        return esc_html( $item['is_active'] ? '✓' : '—' );
    }

    protected function column_expiry( $item ) {
        return esc_html( $item['expiry'] );
    }
    
    public function get_views() {

        if ( isset( $_GET['_wpnonce'] ) &&
            ! wp_verify_nonce(
                sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ),
                'gwu_list_wraps'
            )
        ) {
            wp_die( esc_html__( 'Security check failed.', 'gift-wrap-upsell-plugin' ) );
        }

        $current  = isset( $_GET['post_status'] )
            ? sanitize_text_field( wp_unslash( $_GET['post_status'] ) )
            : 'all';

        $base_url = admin_url( 'admin.php?page=gwu-wraps' );
        $nonce    = wp_create_nonce( 'gwu_list_wraps' );

        $counts      = wp_count_posts( 'gift_wrap_option' );
        $all_count   = (int) $counts->publish + (int) $counts->draft;
        $pub_count   = (int) $counts->publish;
        $draft_count = (int) $counts->draft;
        $trash_count = (int) $counts->trash;

        $views = [];

        $views['all'] = sprintf(
            '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
            esc_url( add_query_arg( [ '_wpnonce' => $nonce ], $base_url ) ),
            $current === 'all' ? 'current' : '',
            esc_html__( 'All', 'gift-wrap-upsell-plugin' ),
            $all_count
        );

        $views['publish'] = sprintf(
            '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
            esc_url( add_query_arg( [ 'post_status' => 'publish', '_wpnonce' => $nonce ], $base_url ) ),
            $current === 'publish' ? 'current' : '',
            esc_html__( 'Published', 'gift-wrap-upsell-plugin' ),
            $pub_count
        );

        $views['draft'] = sprintf(
            '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
            esc_url( add_query_arg( [ 'post_status' => 'draft', '_wpnonce' => $nonce ], $base_url ) ),
            $current === 'draft' ? 'current' : '',
            esc_html__( 'Draft', 'gift-wrap-upsell-plugin' ),
            $draft_count
        );

        $views['trash'] = sprintf(
            '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
            esc_url( add_query_arg( [ 'post_status' => 'trash', '_wpnonce' => $nonce ], $base_url ) ),
            $current === 'trash' ? 'current' : '',
            esc_html__( 'Trash', 'gift-wrap-upsell-plugin' ),
            $trash_count
        );

        return $views;
    }

    public function prepare_items() {

            if ( isset( $_GET['_wpnonce'] ) &&
                ! wp_verify_nonce(
                    sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ),
                    'gwu_list_wraps'
                )
            ) {
                wp_die( esc_html__( 'Security check failed.', 'gift-wrap-upsell-plugin' ) );
            }

            $per_page         = 5;
            $current          = $this->get_pagenum();
            // get_pagenum() reads $_GET['paged'] — WP_List_Table's built-in                                                                                         
            // pagination links set this parameter automatically.
            $allowed_statuses = [ 'publish', 'draft', 'trash' ];

            $status = isset( $_GET['post_status'] )
                ? sanitize_text_field( wp_unslash( $_GET['post_status'] ) )
                : 'all';

            $post_status = in_array( $status, $allowed_statuses, true )
                ? $status
                : [ 'publish', 'draft' ];

            $query = new WP_Query([
                'post_type'      => 'gift_wrap_option',
                'post_status'    => $post_status,
                'posts_per_page' => $per_page,
                'paged'          => $current,
            ]);

            $data = [];

            foreach ( $query->posts as $post ) {
                $data[] = [
                    'id'        => $post->ID,
                    'title'     => get_the_title( $post ),
                    'surcharge' => (float) get_post_meta( $post->ID, 'surcharge', true ),
                    'is_active' => (bool) get_post_meta( $post->ID, 'is_active', true ),
                    'expiry'    => (string) get_post_meta( $post->ID, 'expiry_date', true ),
                    'status'    => $post->post_status,
                ];
            }

            // Three arrays: [visible columns, hidden columns, sortable columns].                                                                                    
            // Hidden columns (2nd array) are columns registered in get_columns()
            // but not displayed in the table — WP_List_Table lets users toggle                                                                                      
            // them via Screen Options. Empty array means all columns are visible.                                                                                   
            // Sortable columns (3rd array) would map column keys to orderby values —                                                                                
            // empty for now since we're not supporting click-to-sort yet. 

            $this->items           = $data;
            $this->_column_headers = [ $this->get_columns(), [], [] ];

            $this->set_pagination_args([
                'total_items' => $query->found_posts,
                'per_page'    => $per_page,
            ]);
    }
      
}