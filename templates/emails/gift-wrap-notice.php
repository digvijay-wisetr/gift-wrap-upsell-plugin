<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// $wrap_title, $wrap_surcharge, $wrap_image are passed in via wc_get_template()
?>
<div style="margin: 20px 0; padding: 15px; border: 1px solid #e0e0e0; border-radius: 4px;">
    <h3 style="margin:0 0 10px;"><?php esc_html_e( 'Your Gift Wrap', 'gift-wrap-upsell-plugin' ); ?></h3>
    <?php if ( $wrap_image ) : ?>
        <img src="<?php echo esc_url( $wrap_image ); ?>"
             alt="<?php echo esc_attr( $wrap_title ); ?>"
             style="max-width:100px; margin-bottom:10px; border-radius:4px;">
    <?php endif; ?>
    <p style="margin:0;">
        <strong><?php esc_html_e( 'Wrap:', 'gift-wrap-upsell-plugin' ); ?></strong>
        <?php echo esc_html( $wrap_title ); ?>
    </p>
    <p style="margin:5px 0 0;">
        <strong><?php esc_html_e( 'Surcharge:', 'gift-wrap-upsell-plugin' ); ?></strong>
        <?php echo wp_kses_post( wc_price( $wrap_surcharge ) ); ?>
    </p>
</div>