<?php 

  $WPFBC = WpFacebookComments::load();

  if ($WPFBC->unlocked()) { 
    ?>
      <script>
        (function($) {
          window.WpFacebookComments = window.WpFacebookComments ? window.WpFacebookComments : {};
          WpFacebookComments.subscribeToCommentEvents = function() {
            FB.Event.subscribe('comment.create', function(response) {
              $.post('<?php echo admin_url('admin-ajax.php') ?>', { action: 'fb_create_comment', response: response });
            });
            FB.Event.subscribe('comment.remove', function(response) {
              $.post('<?php echo admin_url('admin-ajax.php') ?>', { action: 'fb_remove_comment', response: response });
            });
          }
        })(jQuery);
      </script>
    <?php 
  } 
?>

<?php 

  $insert_script = $WPFBC->setting('insert_script', 'all');

  if ($insert_script == 'all') { ?>

  <div id="fb-root"></div>
  <script>
    <?php if ($WPFBC->unlocked()) { ?>
      window.fbAsyncInit = function() {
        FB.init({
          appId:  '<?php echo $WPFBC->get_app_id() ?>',
          status: true,
          cookie: true,
          xfbml:  true
        });

        WpFacebookComments.subscribeToCommentEvents();     
      }
    <?php } ?>

    (function(d, s, id) {
      var js, fjs = d.getElementsByTagName(s)[0];
      if (d.getElementById(id)) {return;}
      js = d.createElement(s); js.id = id;
      js.src = "//connect.facebook.net/en_US/all.js#xfbml=1";
      fjs.parentNode.insertBefore(js, fjs);
    }(document, 'script', 'facebook-jssdk'));
  </script>

<?php } else if ($insert_script == 'hook_only' && $WPFBC->unlocked()) { ?>

  <script>
    (function() {
      var i = setInterval(function() {
        if ('FB' in window) {
          clearInterval(i);
          SharePress.subscribeToCommentEvents();
        }
      }, 100);
    })();
  </script>

<?php } else { ?>

  <?php do_action('fb_comments_script') ?>

<?php } ?>

<?php do_action('fb_before_comments') ?>

<fb:comments href="<?php the_permalink(); ?>" num_posts="<?php echo $WPFBC->setting('num_posts', 10) ?>" width="<?php echo $WPFBC->setting('width', 500) ?>"></fb:comments>

<?php do_action('fb_after_comments') ?>

<?php if ( $WPFBC->setting('show_old_comments', true) && have_comments() ) : ?>
  
  <div class="navigation">
    <div class="alignleft"><?php previous_comments_link() ?></div>
    <div class="alignright"><?php next_comments_link() ?></div>
  </div>

  <ol class="commentlist">
    <?php wp_list_comments(array('type' => 'comment'));?>
  </ol>

  <div class="navigation">
    <div class="alignleft"><?php previous_comments_link() ?></div>
    <div class="alignright"><?php next_comments_link() ?></div>
  </div>

<?php endif; ?>