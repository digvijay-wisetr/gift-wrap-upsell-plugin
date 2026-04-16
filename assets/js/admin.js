jQuery(function ($) {

    let frame;

    $('#gwu_upload_btn').on('click', function (e) {
        e.preventDefault();

        if (frame) {
            frame.open();
            return;
        }

        frame = wp.media({
            title: 'Select Image',
            button: { text: 'Use this image' },
            multiple: false,
            library: {
                type: 'image'
            }
        });

        frame.on('select', function () {
            const attachment = frame.state().get('selection').first().toJSON();

            // Extra safety (optional)
            if (!attachment.type || attachment.type !== 'image') {
                alert('Please select a valid image.');
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
    });

});