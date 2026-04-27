jQuery( function($) {
    
    $( document.body ).on( 'change', 'select[name="gwu_wrap_id"]', function() {
        alert( 'Wrap changed: ' + $(this).val() );
        if ( $(this).val() ) {
            $( document.body ).trigger( 'update_checkout' );
        }
    });
});