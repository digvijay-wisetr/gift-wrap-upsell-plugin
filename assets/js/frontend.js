jQuery( function ( $ ) {

    var $select = $( 'select[name="gwu_wrap_id"]' );

    if ( ! $select.length ) return;

    // Read wrap data passed from PHP via data attribute
    var wraps = [];
    try {
        wraps = JSON.parse( $select.attr( 'data-gwu-wraps' ) || '[]' );
    } catch (e) {
        return; // malformed data — fall back to native select
    }

    var savedId = $select.val();

    // Build the card UI
    var $container = $( '<div class="gwu-cards"></div>' );

    // "No wrap" option
    var $none = $(
        '<div class="gwu-card gwu-card--none" data-id="">' +
            '<div class="gwu-card__label">' + gwuI18n.noWrap + '</div>' +
        '</div>'
    );
    $container.append( $none );

    // One card per wrap
    $.each( wraps, function ( i, wrap ) {
        var img = wrap.image
            ? '<img class="gwu-card__image" src="' + wrap.image + '" alt="' + wrap.title + '">'
            : '<div class="gwu-card__image gwu-card__image--placeholder"></div>';

        var price = wrap.surcharge > 0
            ? '<span class="gwu-card__price">+' + wrap.price + '</span>'
            : '';

        var $card = $(
            '<div class="gwu-card" data-id="' + wrap.id + '">' +
                img +
                '<div class="gwu-card__body">' +
                    '<span class="gwu-card__title">' + wrap.title + '</span>' +
                    price +
                '</div>' +
            '</div>'
        );

        $container.append( $card );
    } );

    // Hide native select, insert cards after it
    $select.closest( '.form-row' ).addClass( 'gwu-field' );
    $select.hide().after( $container );

    // Mark initially selected card
    $container.find( '[data-id="' + savedId + '"]' ).addClass( 'gwu-card--selected' );
    if ( ! savedId ) {
        $none.addClass( 'gwu-card--selected' );
    }

    // On card click — update hidden select and trigger WC recalc
    $container.on( 'click', '.gwu-card', function () {
        var id = $( this ).data( 'id' ).toString();
        $container.find( '.gwu-card' ).removeClass( 'gwu-card--selected' );
        $( this ).addClass( 'gwu-card--selected' );
        $select.val( id ).trigger( 'change' );
        $( document.body ).trigger( 'update_checkout' );
    } );
} );