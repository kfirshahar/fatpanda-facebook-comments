<?php $WPFBC = WpFacebookComments::load(); ?>

<script>
  (function($) {
    var subscribe = function() {
      FB.Event.subscribe('comment.create', function(response) {
        $.post('<?php echo admin_url('admin-ajax.php') ?>', { action: 'fb_create_comment', response: response });
      });
      FB.Event.subscribe('comment.remove', function(response) {
        $.post('<?php echo admin_url('admin-ajax.php') ?>', { action: 'fb_remove_comment', response: response });
      });
    }

    $(function() {
      if (!$('#fb-root').size()) {
        $('body').append('<div id="fb-root"></div>');
        window.fbAsyncInit = function() {
          FB.init({
            appId:  '<?php echo $WPFBC->get_app_id() ?>',
            status: true,
            cookie: true,
            xfbml:  true
          });
          subscribe();
        };
        (function(d, s, id) {
          var js, fjs = d.getElementsByTagName(s)[0];
          if (d.getElementById(id)) {return;}
          js = d.createElement(s); js.id = id;
          js.src = "//connect.facebook.net/en_US/all.js#xfbml=1";
          fjs.parentNode.insertBefore(js, fjs);
        }(document, 'script', 'facebook-jssdk')); 
      } else {
        var i = setInterval(function() {
          if ('FB' in window) {
            clearInterval(i);
            subscribe();
          }
        }, 100);
      }
    })  
  })(jQuery);
</script>

<?php do_action('fb_before_comments') ?>

<fb:comments href="<?php the_permalink(); ?>" num_posts="<?php echo $WPFBC->setting('num_posts', 10) ?>" width="<?php echo $WPFBC->setting('width', 590) ?>"></fb:comments>

<?php do_action('fb_after_comments') ?>

<?php if ( $WPFBC->setting('show_old_comments', true) && have_comments() ) : ?>
  
  <div class="navigation">
    <div class="alignleft"><?php previous_comments_link() ?></div>
    <div class="alignright"><?php next_comments_link() ?></div>
  </div>

  <div class="commentlist">
    <?php wp_list_comments(array('style' => 'div', 'type' => 'comment', 'reverse_top_level' => 1)); ?>
  </div>

  <div class="navigation">
    <div class="alignleft"><?php previous_comments_link() ?></div>
    <div class="alignright"><?php next_comments_link() ?></div>
  </div>

<?php endif; ?>

<noscript>
  <?php wp_list_comments(array('style' => 'div', 'type' => 'facebook', 'reverse_top_level' => 1)); ?>
</noscript>