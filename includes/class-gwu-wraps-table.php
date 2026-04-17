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
            'title'     => __( 'Title', 'gift-wrap' ),
            'surcharge' => __( 'Surcharge', 'gift-wrap' ),
            'is_active' => __( 'Active', 'gift-wrap' ),
            'expiry'    => __( 'Expiry', 'gift-wrap' ),
            'preview'   => __( 'Preview', 'gift-wrap' ),
        ];
    }
    protected function column_preview( $item ) {                                                                                                                 
      return '<div class="gwu-preview-container" id="gwu-preview-' . esc_attr( $item['id'] ) . '"></div>';
    }

    protected function column_title( $item ) {

        $post_id = $item['id'];
        
        // Points to the "View Wrap" submenu page with post_id.                                                                                                  
        // admin.php?page= routes through WP's admin page handler,
        // so the URL respects capability checks registered with add_submenu_page(). 
        $view_url = add_query_arg([
            'page' => 'gwu-wrap-view',
            'post_id' => $post_id
        ], admin_url('admin.php'));
        
        // get_edit_post_link() and get_delete_post_link() return URLs that                                                                                      
        // already contain a nonce — WP verifies by it on.
        $edit_url = get_edit_post_link( $post_id );

        $delete_url = get_delete_post_link( $post_id );

        $actions = [
            'preview' => '<a href="#" class="gwu-preview-btn" data-wrap-id="' . esc_attr( $post_id ) . '">'
                 . esc_html__( 'Preview', 'gift-wrap' ) . '</a>',  
            'view'   => '<a href="' . esc_url( $view_url ) . '">' . esc_html__( 'View', 'gift-wrap' ) . '</a>',
            'edit'   => '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'gift-wrap' ) . '</a>',
            'delete' => '<a href="' . esc_url( $delete_url ) . '">' . esc_html__( 'Delete', 'gift-wrap' ) . '</a>',
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


    public function prepare_items() {
        $per_page = 3;
        $current  = $this->get_pagenum();  
        // get_pagenum() reads $_GET['paged'] — WP_List_Table's built-in                                                                                         
        // pagination links set this parameter automatically.

        $query = new WP_Query([
            'post_type'      => 'gift_wrap_option',
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
            ];
        }
        

        // Three arrays: [visible columns, hidden columns, sortable columns].                                                                                    
        // Hidden columns (2nd array) are columns registered in get_columns()
        // but not displayed in the table — WP_List_Table lets users toggle                                                                                      
        // them via Screen Options. Empty array means all columns are visible.                                                                                   
        // Sortable columns (3rd array) would map column keys to orderby values —                                                                                
        // empty for now since we're not supporting click-to-sort yet. 
        $this->items = $data;

        $this->_column_headers = [ $this->get_columns(), [], [] ];

        $this->set_pagination_args([                                                                                                                             
          'total_items' => $query->found_posts,
          'per_page'    => $per_page,                                                                                                                          
        ]);
    }
      
}