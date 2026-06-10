/* SEO Outreach Pro — Admin JS */
(function($){
  'use strict';

  // Auto-dismiss success notices
  $(document).ready(function(){
    setTimeout(function(){
      $('.notice-success, .seo-notice-success').fadeOut(500);
    }, 4000);

    // Toggle password reveal buttons
    $(document).on('click', '.seo-reveal-btn', function(){
      const $input = $(this).siblings('input[type="password"], input[type="text"]');
      const $icon  = $(this).find('.dashicons');
      if($input.attr('type') === 'password'){
        $input.attr('type','text');
        $icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
      } else {
        $input.attr('type','password');
        $icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
      }
    });
  });

})(jQuery);
