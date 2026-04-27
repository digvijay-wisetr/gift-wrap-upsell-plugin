jQuery(function ($) {

    let frame;

    $('#gwu_upload_btn').on('click', function (e) {
        e.preventDefault();

        if (frame) {
            frame.open();
            return;
        }

        frame = wp.media({
            title: gwuAdmin.mediaTitle,
            button: { text: gwuAdmin.mediaButton },
            multiple: false,
            library: {
                type: 'image'
            }
        });

        frame.on('select', function () {
            const attachment = frame.state().get('selection').first().toJSON();

            // Extra safety (optional)
            if (!attachment.type || attachment.type !== 'image') {
                alert(gwuAdmin.invalidImage);
                return;
            }

            $('#gwu_image_id').val(attachment.id);

            $('#gwu_image_preview').html(
                `<img src="${attachment.url}" style="max-width:150px;" />`
            );

            $('#gwu_remove_btn').show();
        });

        frame.open();
    });

    $('#gwu_remove_btn').on('click', function () {
        $('#gwu_image_id').val('');
        $('#gwu_image_preview').html('');
        $(this).hide();
        frame = null; // force fresh frame next open 
    });

                                                                                                                                 
    // --- AJAX Preview ---                                                                                                                                      
  $('.gwu-preview-btn').on('click', function (e) {
      e.preventDefault();

      var $btn    = $(this);
      var wrapId  = $btn.data('wrap-id');
      var $target = $btn.closest('tr').find('.gwu-preview-container');                                                                                         
   
      // Close all other open previews first                                                                                                                   
      $('.gwu-preview-container').not($target).html('');
                                                                                                                                                               
      // Toggle — if this one is already showing, close it
      if ($target.html().trim() !== '') {
          $target.html('');                                                                                                                                    
          return;
      }                                                                                                                                                        
                  
      $target.html('<em>' + gwuAjax.loading + '</em>');

      $.post(gwuAjax.ajax_url, {
          action:  'gift_wrap_preview',
          nonce:   gwuAjax.nonce,                                                                                                                              
          wrap_id: wrapId
      }, function (response) {                                                                                                                                 
          if (response.success) {
              $target.html(response.data.html);
          } else {
              $target.html('<p style="color:red;">' + response.data + '</p>');
          }                                                                                                                                                    
      }).fail(function () {
          $target.html('<p style="color:red;">' + gwuAjax.requestFailed + '</p>');                                                                                         
      });         
  });

});