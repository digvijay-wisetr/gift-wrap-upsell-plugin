<?php
/**
 * Usage: wp eval-file tests/test-crash-resume.php
 * Demonstrates checkpoint-based resume.
 */

// 1. Create 20 test orders with wrap meta
$wrap_id = /* your test wrap post ID */;
$order_ids = [];
for ( $i = 0; $i < 20; $i++ ) {
    $order = wc_create_order();
    $order->update_meta_data( '_gwu_wrap_id', $wrap_id );
    $order->save();
    $order_ids[] = $order->get_id();
}

// 2. Simulate crash mid-batch: set checkpoint to order #10
update_option( 'gwu_picklist_checkpoint', $order_ids[9], false );

WP_CLI::log( 'Checkpoint set to order ID ' . $order_ids[9] );

// 3. Run the job — it should only process orders 11-20
gwu_run_picklist_job();

// 4. Verify CSV contains only 10 rows (not 20)
WP_CLI::success( 'If CSV has 10 rows, resume-from-checkpoint works.' );