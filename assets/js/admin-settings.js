(function($){
  'use strict';

  function pickImage(targetInput, targetPreview){
    const frame = wp.media({
      title: 'Select Image',
      button: { text: 'Use this image' },
      multiple: false
    });

    frame.on('select', function(){
      const attachment = frame.state().get('selection').first().toJSON();
      $('#'+targetInput).val(attachment.id);
      if(targetPreview){
        $('#'+targetPreview).attr('src', attachment.url).show();
      }
    });

    frame.open();
  }

  function removeImage(targetInput, targetPreview){
    $('#'+targetInput).val(0);
    if(targetPreview){
      $('#'+targetPreview).attr('src','').hide();
    }
  }

  $(document).on('click', '.wcposm-media-pick', function(e){
    e.preventDefault();
    const input = $(this).data('target');
    const preview = $(this).data('preview');
    pickImage(input, preview);
  });

  $(document).on('click', '.wcposm-media-remove', function(e){
    e.preventDefault();
    const input = $(this).data('target');
    const preview = $(this).data('preview');
    removeImage(input, preview);
  });
  });

})(jQuery);
