<?php $WPFBC = FatPandaFacebookComments::load(); ?>

<script>
  (function($) {
    $(function() {
      if (!$('#fb-root').size()) {
        $('body').append('<div id="fb-root"></div>');
        (function(d, s, id) {
          var js, fjs = d.getElementsByTagName(s)[0];
          if (d.getElementById(id)) {return;}
          js = d.createElement(s); js.id = id;
          js.src = "//connect.facebook.net/<?php echo get_locale() ?>/all.js#xfbml=1";
          fjs.parentNode.insertBefore(js, fjs);
        }(document, 'script', 'facebook-jssdk')); 
      }
    });
    $.post('<?php echo admin_url('admin-ajax.php') ?>', {
      action: 'fbc_ping',
      post_id: '<?php echo get_the_ID() ?>',
      nonce: '<?php echo wp_create_nonce('fbc'.get_the_ID()) ?>'
    });  
  })(jQuery);
</script>

<a name="comments"></a>

<?php echo $WPFBC->setting('comment_form_title', '') ?>

<?php do_action('fb_before_comments') ?>

<div id="<?php echo get_class($WPFBC) ?>">
  <noscript>
    <?php wp_list_comments(array('style' => 'div', 'type' => 'facebook', 'reverse_top_level' => 1)); ?>
    <?php if ( $WPFBC->setting('show_old_comments', 'on') != 'on') { ?>
      <?php wp_list_comments(array('style' => 'div', 'type' => 'comment', 'reverse_top_level' => 1)); ?>
    <?php } ?>
  </noscript>
  <div 
    class="fb-comments" 
    data-colorscheme="<?php echo $WPFBC->setting('colorscheme', 'light') ?>" 
    data-href="<?php echo $WPFBC->get_permalink() ?>" 
    data-num-posts="<?php echo esc_attr($WPFBC->get_num_posts()) ?>" 
    data-publish_feed="true"
    data-width="<?php echo esc_attr($WPFBC->get_width()) ?>"></div>
</div>

<?php do_action('fb_after_fb_comments') ?>

<?php do_action('fb_before_old_comments') ?>

<?php if ( $WPFBC->setting('show_old_comments', 'on') == 'on' && have_comments() ) { ?>
  <div class="navigation">
    <div class="alignleft"><?php previous_comments_link() ?></div>
    <div class="alignright"><?php next_comments_link() ?></div>
  </div>

  <?php if ( has_filter('fb_comments_template') ) { ?>
    <?php echo apply_filters('fb_comments_template', true) ?>
  <?php } else { ?>
    <div class="commentlist">
      <?php wp_list_comments(array('style' => 'div', 'type' => 'comment', 'reverse_top_level' => 1)); ?>
    </div> 
  <?php } ?>

  <div class="navigation">
    <div class="alignleft"><?php previous_comments_link() ?></div>
    <div class="alignright"><?php next_comments_link() ?></div>
  </div>
<?php } ?>

<?php do_action('fb_after_comments') ?>