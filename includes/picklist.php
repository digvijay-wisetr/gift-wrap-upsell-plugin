<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'gwu_generate_picklist', 'gwu_run_picklist_job' );

function gwu_run_picklist_job() {
    $logger  = wc_get_logger();
    $context = [ 'source' => 'gift-wrap' ];

    // 1. Claim lock — prevent double-processing
    $lock_key = 'gwu_picklist_lock';
    $lock     = get_option( $lock_key );
    if ( $lock && ( time() - (int) $lock < HOUR_IN_SECONDS ) ) {
        $logger->warning( 'Picklist already running, skipping.', $context );
        return;
    }
    update_option( $lock_key, time(), false ); // false = don't autoload

    // 2. Read checkpoint — last processed order ID
    $checkpoint_key = 'gwu_picklist_checkpoint';
    $last_id        = (int) get_option( $checkpoint_key, 0 );

    // 3. Query orders placed yesterday with a gift wrap
    $yesterday_start = gmdate( 'Y-m-d 00:00:00', strtotime( '-1 day' ) );
    $yesterday_end   = gmdate( 'Y-m-d 23:59:59', strtotime( '-1 day' ) );

    $batch_size = 50;
    $rows       = [];

    wp_suspend_cache_addition( true );

    do {
        $orders = wc_get_orders( [
            'date_created' => $yesterday_start . '...' . $yesterday_end,
            'meta_key'     => '_gwu_wrap_id',
            'meta_compare' => 'EXISTS',
            'limit'        => $batch_size,
            'orderby'      => 'ID',
            'order'        => 'ASC',
            // Resume from checkpoint — NOT an offset
            'id'           => [ 'value' => $last_id, 'compare' => '>' ],
        ] );

        foreach ( $orders as $order ) {
            $wrap_id  = absint( $order->get_meta( '_gwu_wrap_id' ) );
            $rows[]   = [
                $order->get_id(),
                $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                get_the_title( $wrap_id ),
                $order->get_date_created()->date( 'Y-m-d' ),
            ];
            $last_id  = $order->get_id();
        }

        // Save checkpoint after each batch
        update_option( $checkpoint_key, $last_id, false );
        wp_cache_flush_runtime();

    } while ( count( $orders ) === $batch_size );

    wp_suspend_cache_addition( false );

    // 4. Write CSV via WP_Filesystem (never file_put_contents)
    if ( ! empty( $rows ) ) {
        gwu_write_picklist_csv( $rows, $logger, $context );
    }

    // 5. Clean up lock and checkpoint
    delete_option( $lock_key );
    delete_option( $checkpoint_key );

    $logger->info( sprintf( 'Picklist job done. %d orders processed.', count( $rows ) ), $context );
}

function gwu_write_picklist_csv( array $rows, $logger, array $context ) {
    global $wp_filesystem;

    if ( ! function_exists( 'WP_Filesystem' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    WP_Filesystem();

    $upload_dir = wp_upload_dir();
    $filename   = sanitize_file_name( 'gift-wrap-picklist-' . gmdate( 'Y-m-d' ) . '.csv' );
    $filepath   = trailingslashit( $upload_dir['basedir'] ) . $filename;

    // Build CSV content
    $header  = [ 'Order ID', 'Customer', 'Wrap', 'Date' ];
    $content = implode( ',', $header ) . "\n";
    foreach ( $rows as $row ) {
        $content .= implode( ',', array_map( 'esc_attr', $row ) ) . "\n";
    }

    if ( ! $wp_filesystem->put_contents( $filepath, $content, FS_CHMOD_FILE ) ) {
        $logger->error( 'Could not write picklist CSV.', $context );
        return;
    }

    // Email admin (log only in dev — don't actually send)
    $logger->info( 'Picklist CSV written: ' . $filepath, $context );
    // In prod: wp_mail( get_option('admin_email'), 'Daily Gift Wrap Picklist', '...', [], [ $filepath ] );
}