<?php
/**
 * Usage: wp eval-file tests/test-webhook-dedup.php
 */
$order = wc_get_order( /* your test order ID */ );

$payload = [
    'event_id' => 'test-event-abc-123',
    'order_id' => $order->get_id(),
    'wrap_id'  => $order->get_meta( '_gwu_wrap_id' ),
    'tracking' => 'TRACK-XYZ',
];

$request = new WP_REST_Request( 'POST', '/gift-wrap-upsell-plugin/v1/webhook/shipped' );
$request->set_body_params( $payload );

$response1 = rest_do_request( $request );
WP_CLI::log( 'First call status: ' . $response1->get_status() ); // expect 200

$response2 = rest_do_request( $request );
WP_CLI::log( 'Second call status: ' . $response2->get_status() ); // expect 409

$order = wc_get_order( $order->get_id() ); // re-fetch
WP_CLI::success( '_gwu_wrap_shipped = ' . $order->get_meta( '_gwu_wrap_shipped' ) ); // should be '1' once