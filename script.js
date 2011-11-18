(function($) {
  $('.fatpanda-facebook-comments-uncache').live('click', function() {
    $this = $(this);
    var post_id = $this.attr('rel');
    var clone = $this.clone();
    var span = $this.parent();
    span.html("Doin'&nbsp;it...");
    $.post(ajaxurl, { action: 'FatPandaFacebookComments_uncache', post_id: post_id }, function(count) {
      setTimeout(function() {
        span.html('Done!');
        $('tr#post-'+post_id+' .comment-count').text(count);
        setTimeout(function() {
          span.html(clone);
        }, 1500);
      }, 1500);
    });
    return false;
  });
})(jQuery);