<?php 
$WPFBC = FatPandaFacebookComments::load();
$xid_meta_override = get_post_meta(get_the_ID(), 'xid', true);
?>

<script>
  (function($) {
    $(function() {
      if (!$('#fb-root').size()) {
        $('body').append('<div id="fb-root"></div>');
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
    });  
  })(jQuery);
</script>

<a name="comments"></a>

<?php echo $WPFBC->setting('comment_form_title', '') ?>

<?php do_action('fb_before_comments') ?>

<?php if ($WPFBC->should_support_xid()) { ?>

  <div id="<?php echo get_class($WPFBC) ?>">
    <fb:comments 
      <?php if ($xid = $WPFBC->get_xid()) { ?>
        xid="<?php echo $xid ?>_post<?php echo get_the_ID() ?>" 
      <?php } else if ($xid = $xid_meta_override) { ?>
        xid="<?php echo $xid ?>" 
      <?php } ?>
      migrated="1"
      num_posts="<?php echo esc_attr($WPFBC->get_num_posts()) ?>" 
      publish_feed="true"></fb:comments>
  </div>

<?php } else { ?>
    
  <div id="<?php echo get_class($WPFBC) ?>">
    <div 
      class="fb-comments" 
      data-colorscheme="<?php echo $WPFBC->setting('colorscheme', 'light') ?>" 
      data-href="<?php echo $WPFBC->get_permalink() ?>" 
      data-num-posts="<?php echo esc_attr($WPFBC->get_num_posts()) ?>" 
      data-publish_feed="true"
      data-width="<?php echo esc_attr($WPFBC->get_width()) ?>"></div>
  </div>

<?php } ?>

<?php do_action('fb_after_comments') ?>

<?php if ( $WPFBC->setting('show_old_comments', 'on') == 'on' && have_comments() ) { ?>
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
<?php } else { ?>
  <noscript>
    <?php wp_list_comments(array('style' => 'div', 'type' => 'comment', 'reverse_top_level' => 1)); ?>
  </noscript>
<?php } ?>

<noscript>
  <?php wp_list_comments(array('style' => 'div', 'type' => 'facebook', 'reverse_top_level' => 1)); ?>
</noscript>