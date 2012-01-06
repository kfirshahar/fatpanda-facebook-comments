<?php
/*
Plugin Name: Facebook Comments by Fat Panda
Description: Replace WordPress commenting with the Facebook Comments widget, quickly and easily.
Version: 1.0.3
Author: Aaron Collegeman, Fat Panda
Author URI: http://fatpandadev.com
Plugin URI: http://aaroncollegeman.com/facebook-comments-for-wordpress
*/

$__FB_COMMENT_EMBED = false;

class FatPandaFacebookComments {

  private static $plugin;
  static function load() {
    $class = __CLASS__; 
    return ( self::$plugin ? self::$plugin : ( self::$plugin = new $class() ) );
  }

  private function __construct() {
    add_action( 'init', array( $this, 'init' ) );
  }

  function init() {
  
    if (is_admin()) {
      add_action('admin_menu', array($this, 'admin_menu'));  
      add_action('admin_notices', array($this, 'admin_notices'));
    }
    
    add_filter('comments_template', array($this, 'comments_template'));

    add_action('wp_ajax_fb_create_comment', array($this, 'ajax_fb_create_comment'));
    add_action('wp_ajax_nopriv_fb_create_comment', array($this, 'ajax_fb_create_comment'));
    add_action('wp_ajax_fb_remove_comment', array($this, 'ajax_fb_remove_comment'));
    add_action('wp_ajax_nopriv_fb_remove_comment', array($this, 'ajax_fb_remove_comment'));
    add_action(sprintf('wp_ajax_%s_uncache', __CLASS__), array($this, 'uncache'));
    
    add_filter('pre_comment_approved', array($this, 'pre_comment_approved'), 10, 2);
    add_filter('comment_reply_link', array($this, 'comment_reply_link'), 10, 4); //add_filter('get_comments_number', array($this, 'get_comments_number'), 10, 2);
    
    add_filter('plugin_action_links_fatpanda-facebook-comments/plugin.php', array($this, 'plugin_action_links'), 10, 4);

    add_filter('post_row_actions', array($this, 'post_row_actions'), 10, 2);
    add_filter('page_row_actions', array($this, 'post_row_actions'), 10, 2);

    if ($this->setting('fix_notifications', 'on') == 'on') {
      add_filter('comment_notification_subject', array($this, 'comment_notification_subject'), 10, 2);
      add_filter('comment_notification_text', array($this, 'comment_notification_text'), 10, 2);
    }
  
    if (!is_admin()) {
      wp_enqueue_script('jquery');
    }

    if (is_admin()) {
      wp_enqueue_script(__CLASS__, plugins_url('script.js', __FILE__), 'jquery');
    }

    add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));

  }

  function admin_enqueue_scripts($hook) {
    if ($hook == 'settings_page_FatPandaFacebookComments') {
      wp_enqueue_script('jquery-ui-core');
      wp_enqueue_script('jquery-ui-custom-fatpanda', plugins_url('js/jquery-ui-1.8.16.custom.min.js', __FILE__), array('jquery'));
    }
  }

  /**
   * This function is used to assemble the same data that
   * wp_notify_postauthor() uses to build notification messages.
   */
  function get_comment_data($comment_id) {
    $comment = get_comment( $comment_id );
    $post    = get_post( $comment->comment_post_ID );
    $author  = get_userdata( $post->post_author );

    $comment_author_domain = @gethostbyaddr($comment->comment_author_IP);

    // The blogname option is escaped with esc_html on the way into the database in sanitize_option
    // we want to reverse this for the plain text arena of emails.
    $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

    if ( empty( $comment_type ) ) $comment_type = 'comment';
    
    return compact('comment', 'post', 'author', 'comment_author_domain', 'blogname', 'comment_type');
  }

  function comment_notification_subject($subject, $comment_id) {
    extract($this->get_comment_data($comment_id));
    
    $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

    if ($comment->comment_type == 'facebook') {
      $subject = sprintf(__('[%1$s] Comment: "%2$s"'), $blogname, $post->post_title);
    }

    return $subject;
  }
   
  /**
   * This filter controls the content of comment notification e-mails,
   * and ensures that the site admin is copied on all messages.
   */
  function comment_notification_text($notify_message, $comment_id) {
    extract($this->get_comment_data($comment_id));
    if ($comment->comment_type == 'facebook') {
      return $notify_message . "\n\n" . strip_tags($comment->comment_content);
    } else {
      return $notify_message;
    }
  }

  function uncache() {
    if (($post_id = $_POST['post_id']) && current_user_can('administrator')) {
      $this->refresh_comments_for_href(get_permalink($post_id));
      wp_update_comment_count($post_id);
      $counts = get_comment_count($post_id);
      echo $counts['approved'];
    }
    exit;
  }

  function post_row_actions($actions, $post) {
    if (current_user_can('administrator') && $post->post_status == 'publish') {
      $actions['refresh'] = '<span><a href="#" rel="'.$post->ID.'" class="fatpanda-facebook-comments-uncache">Refresh</a></span>';
    }
    return $actions;
  }

  function plugin_action_links($actions, $plugin_file, $plugin_data, $context) {
    $actions['settings'] = '<a href="options-general.php?page='.__CLASS__.'">Settings</a>';
    if (!class_exists('Sharepress')) {
      $actions['get-sharepress'] = '<a target="_blank" href="http://aaroncollegeman.com/sharepress?utm_source=fatpanda-facebook-comments&utm_medium=in-app-promo&utm_campaign=get-sharepress">Get SharePress</a>';
    }
    $actions['donate'] = '<a target="_blank" href="http://aaroncollegeman.com/facebook-comments-for-wordpress?utm_source=fatpanda-facebook-comments&utm_medium=in-app-promo&utm_campaign=donate">Donate</a>';
    return $actions;
  }

  function get_app_id() {
    if ($app_id = $this->setting('app_id')) {
      return $app_id;
    } else if (( $fbc_options = get_option('fbComments')) && ($app_id = $fbc_options['appId'])) {
      update_option($this->id('imported_settings', false), true);
      return $app_id;
    } else if (class_exists('SharePress')) {
      update_option($this->id('imported_settings', false), true);
      return get_option(SharePress::OPTION_API_KEY);
    } else {
      return '';
    }
  }

  function get_num_posts() {
    return (int) $this->setting('num_posts', 10);
  }

  function get_width() {
    return (int) $this->setting('width', 600); 
  }

  function is_enabled() {
    return $this->setting('comments_enabled', 'on') == 'on';
  }

  function is_import_enabled() {
    if ($setting = $this->setting('import_enabled')) {
      return $setting == 'on';
    } else if ($this->get_app_id()) {
      return true;
    }
  }

  function should_support_xid() {
    if ($setting = $this->setting('support_xid')) {
      return $setting == 'on';
    } else if ($this->get_xid()) {
      return true;
    }
  }

  function get_xid() {
    if ($xid = $this->setting('xid')) {
      return $xid;
    } else if  (( $fbc_options = get_option('fbComments')) && ($xid = $fbc_options['xid'])) {
      update_option($this->id('imported_settings', false), true);
      return $xid;
    } else {
      return '';
    }
  }

  function pre_comment_approved($approved, $commentdata) {
    if ($commentdata['comment_type'] == 'facebook') {
      return 1;
    } else {
      return $approved;
    }
  }

  function comment_reply_link($html, $args, $comment, $post) {
    if ( !$this->is_enabled() ) {
      return $html;
    } else {
      return '';
    }
  }

  function api($path, $params = null, $method = 'GET') {
    $http = _wp_http_get_object();

    $url = 'https://graph.facebook.com/'.trim($path, '/');

    $args = array();
    $args['method'] = $method;
    
    if ($method == 'POST') {
      $args['body'] = http_build_query($params, null, '&');
    } else { 
      $url .= '/?' . http_build_query($params, null, '&');
    }
    
    // disable the 'Expect: 100-continue' behaviour. This causes CURL to wait
    // for 2 seconds if the server does not support this header.
    $opts = array(
      CURLOPT_CONNECTTIMEOUT => 10,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT        => 60,
      CURLOPT_USERAGENT      => __CLASS__
    );

    if (isset($opts[CURLOPT_HTTPHEADER])) {
      $existing_headers = $opts[CURLOPT_HTTPHEADER];
      $existing_headers[] = 'Expect:';
      $opts[CURLOPT_HTTPHEADER] = $existing_headers;
    } else {
      $opts[CURLOPT_HTTPHEADER] = array('Expect:');
    }
    $args['headers'] = $opts[CURLOPT_HTTPHEADER];

    $args['sslverify'] = false;
    $args['timeout'] = $opts[CURLOPT_CONNECTTIMEOUT] * 1000;

    // echo "{$url}\n";

    $result = $http->request($url, $args);

    if (!is_wp_error($result)) {
      return json_decode($result['body']);
    } else {
      error_log($result->get_error_message());
      return false;
    }
    
  }

  function get_fb_comment_ids($href) {
    $ids = array();
    if ($comments = (array) $this->api('comments', array('ids' => $href))) {
      foreach($comments[$href]->data as $comment) {
        $ids[] = $comment->id;
      }
    } else {
      return false;
    }
    return $ids;
  }

  function ajax_fb_create_comment() {
    if ($response = $_POST['response']) {
      // print_r($response);
      $this->refresh_comments_for_href($response['href']);
    }  
    exit;  
  }

  private function refresh_comments_for_href($href) {
    if (( $post_id = url_to_postid($href) ) && ( $post = get_post($post_id) )) {
      
      // echo "Post: {$post_id}, {$href}\n";
      
      try {
        if ($comments = (array) $this->api('comments', array('ids' => $href))) {
          if (isset($comments['error'])) {
            echo sprintf("Failed to download comments - %s: %s\n", $comments['error']->type, $comments['error']->message);
            return false;
          }

          // echo sprintf("FB Comments: %d\n", count($comments[$href]->data));
          
          foreach($comments[$href]->data as $comment) {
            try {
              $this->update_fb_comment($post, $comment);
            } catch (Exception $e) {
              echo sprintf("Failed to update FB comment {$comment->id} - %s\n", $e->getMessage());
              // continue on
            }
          }
        } else {
          echo "Failed to download comments.\n";
        }
      } catch (Exception $e) {
        echo sprintf("Failed to download comments - %s\n", $e->getMessage());
      }
    }
  }

  private function get_wp_comment_for_fb($post_id, $fb_comment_id) {
    global $wpdb;

    $sql = $wpdb->prepare("
      SELECT C.comment_ID 
      FROM $wpdb->comments C JOIN $wpdb->commentmeta M ON (C.comment_ID = M.comment_id) 
      WHERE 
        C.comment_post_ID = %s 
        AND M.meta_key = 'fb_comment_id' 
        AND M.meta_value = %s
    ", 
      $post_id, 
      $fb_comment_id
    );

    // print_r($sql);

    return $wpdb->get_var($sql);
  }

  private function update_fb_comment($post, $comment, $parent_id = null) {
    $wp_comment_id = $this->get_wp_comment_for_fb($post->ID, $comment->id);

    if (!$wp_comment_id) {
      if (preg_match('/((\d\d\d\d)-(\d\d)-(\d\d))T((\d\d):(\d\d):(\d\d))/', $comment->created_time, $matches)) {
        $gmdate = "{$matches[1]} {$matches[5]}";
      } else {
        $gmdate = gmdate('Y-m-d H:i:s');
      }

      $comment_data = array(
        'comment_post_ID' => $post->ID,
        'comment_author' => $comment->from->name,
        'comment_content' => $comment->message,
        'comment_date' => get_date_from_gmt($gmdate),
        'comment_date_gmt' => $gmdate,
        'comment_approved' => '1',
        'comment_type' => 'facebook'
      );

      if (!is_null($parent_id)) {
        $comment_data['comment_parent'] = $parent_id;
      }

      // print_r($comment_data);

      $wp_comment_id = wp_new_comment($comment_data);

      if ($wp_comment_id && !is_wp_error($wp_comment_id)) {
        wp_update_comment_count($post->ID);
        update_comment_meta($wp_comment_id, 'fb_comment', $comment);
        update_comment_meta($wp_comment_id, 'fb_comment_id', $comment->id);
        update_comment_meta($wp_comment_id, 'fb_commenter_id', $comment->from->id);
      } else {
        // print_r($wp_comment_id);
      }
    }

    if ($comment->comments) {
      foreach($comment->comments->data as $reply) {
        $this->update_fb_comment($post, $reply, $wp_comment_id);
      }
    }
  }

  function ajax_fb_remove_comment() {
    if ($response = $_POST['response']) {
      
      // print_r($response);

      if (( $post_id = url_to_postid($response['href']) ) && ( $post = get_post($post_id) )) {
        $ids = $this->get_fb_comment_ids($response['href']);

        // print_r($ids);

        if ($ids !== false) {
          $comments = get_comments(array('post_id' => $post_id, 'type' => 'facebook'));

          // print_r($comments);

          foreach($comments as $comment) {
            if ($fb_comment_id = get_comment_meta($comment->comment_ID, 'fb_comment_id', true)) {
              if (!in_array($fb_comment_id, $ids)) {
                wp_delete_comment($comment->comment_ID);
              }
            }
          }
        }
      }
    }
    exit;  
  }

  function comments_template($template) {
    global $__FB_COMMENT_EMBED;
    global $post;
    global $comments;

    if ( !( is_singular() && ( have_comments() || 'open' == $post->comment_status ) ) ) {
      return '';
    }

    if ( !$this->is_enabled() ) {
      return $template;
    }

    $__FB_COMMENT_EMBED = true;
    return dirname(__FILE__).'/comments.php';
  }

  function admin_notices() {
    
  }
  
  function admin_menu() {
    
    add_options_page( 'Facebook Comments', 'Facebook Comments', 'administrator', __CLASS__, array( $this, 'settings' ) ); 
    
    register_setting( __CLASS__, sprintf('%s_settings', __CLASS__), array( $this, 'sanitize_settings' ) );

  } // END admin_menu

  function settings() {
    $app_id = $this->get_app_id();
    $xid = $this->get_xid();

    ?>  
      <?php if (get_option($this->id('imported_settings', false))) { ?>
        <div class="updated">
          <p>We <b>imported settings</b> from your other plugins. Make sure to look everything over closely!</p>
        </div>
      <?php } ?>
        
      <style>
        .wrap h2 span { font-size: 0.75em; padding-left: 20px; }
      </style>
      <div class="wrap">
        <div id="icon-general" class="icon32" style="background:url('<?php echo plugins_url('img/icon32.png', __FILE__) ?>') no-repeat;"><br /></div>
        <h2>
          Facebook Comments
          <span>a WordPress plugin from <a href="http://aaroncollegeman.com/fatpanda/" target="_blank">Fat Panda</a></span>
        </h2>
        

        <form action="<?php echo admin_url('options.php') ?>" method="post">
          <?php settings_fields( __CLASS__ ) ?>
          
          <br />
          <h3 class="title">Use Facebook Comments for commenting on your site?</h3>

          <table class="form-table">
            <tr>
              <td>
                <div style="margin-bottom:5px;">
                  <label>
                    <input type="radio" name="<?php $this->field('comments_enabled') ?>" value="on" <?php if ($this->is_enabled()) echo 'checked="checked"' ?> />
                    &nbsp;Yes, replace built-in commenting with the <a href="http://developers.facebook.com/docs/reference/plugins/comments/" target="_blank">Facebook Comments widget</a>.
                  </label>
                </div>
                <div>
                  <label>
                    <input type="radio" name="<?php $this->field('comments_enabled') ?>" value="off" <?php if (!$this->is_enabled()) echo 'checked="checked"' ?> />
                    &nbsp;No, please disable this plugin.
                  </label>
                </div>
              </td>
            </tr>
          </table>

          <br />
          <h3 class="title">Import Facebook comments and back them up in your database?</h3>

          <table class="form-table">
            <tr>
              <td>
                <div style="margin-bottom:5px;">
                  <label style="float:left; margin-top:2px;">
                    <input type="radio" name="<?php $this->field('import_enabled') ?>" value="on" <?php if ($this->is_import_enabled()) echo 'checked="checked"' ?> />
                    &nbsp;Yes, please import my comments.
                  </label>

                  <span style="float:left; margin-left:25px;">
                    <label for="<?php $this->id('app_id') ?>">
                      My <a href="http://developers.facebook.com/docs/appsonfacebook/tutorial/" target="_blank">Facebook Application</a> App ID:
                    </label>
                    <input type="text" class="regular-text" id="<?php $this->id('app_id') ?>" style="width:12em;" name="<?php $this->field('app_id') ?>" value="<?php echo esc_attr($app_id) ?>" />
                    <?php if (class_exists('Sharepress')) { ?>
                      &nbsp;<span class="description">If different from SharePress</span>
                    <?php } ?>
                  </span>

                  <div style="clear:both;"></div>
                </div>
                <div>
                  <label>
                    <input type="radio" name="<?php $this->field('import_enabled') ?>" value="off" <?php if (!$this->is_import_enabled()) echo 'checked="checked"' ?> />
                    &nbsp;No, do not import comments.
                  </label>
                </div>
              </td>
            </tr>
          </table>

          <br />
          <h3 class="title">Who are your moderators?</h3>

          <?php if ($app_id) { ?>
            <table class="form-table">
              <tr>
                <td>
                  <div style="margin-bottom:5px;">
                    <label>
                      <input type="radio" name="<?php $this->field('moderator_mode') ?>" value="app" <?php $this->checked($this->setting('moderator_mode', 'app') == 'app') ?> />
                      &nbsp;Only the administrators of my Facebook Application.
                    </label>
                  </div>
                  <div>
                    <label>
                      <input type="radio" name="<?php $this->field('moderator_mode') ?>" value="admin" <?php $this->checked($this->setting('moderator_mode', 'app') == 'admin') ?> />
                      &nbsp;Only the moderators I specify. 
                    </label>
                    &nbsp;<a href="#" id="<?php $this->id('connect') ?>" class="button" onclick="FB.login(); return false;" style="display:none;">Connect</a>
                  </div>
                  <p>
                    <ul id="<?php $this->id('moderators') ?>">
                      <li><span rel="me">me</span></li>
                      <li><input type="hidden" name="<?php $this->field('moderators[]') ?>" value="710757081" /> <span rel="710757081">710757081</span> &nbsp;&nbsp;<a href="#">remove</a></li>
                    </ul>
                  </p>
                </td>
              </tr>
            </table> 

            <div id="fb-root"></div>
            <script>
              (function($) {
                window.fbAsyncInit = function() {
                  FB.Event.subscribe('auth.statusChange', function(response) {
                    if (response.status == 'connected') {
                      $('#<?php $this->id('connect') ?>').hide();
                      $('#<?php $this->id('moderators') ?> li span').each(function(i, span) {
                        var $span = $(span);
                        FB.api('/'+$span.attr('rel'), function(user) {
                          $span.html('<img width="24" src="http://graph.facebook.com/'+user.id+'/picture?size=square" align="absmiddle" />&nbsp;&nbsp;'+user.name);
                        });
                      });
                      
                    } else {
                      $('#<?php $this->id('connect') ?>').show();
                    }
                  });

                  FB.init({
                    appId      : '<?php echo $app_id ?>', // App ID
                    cookie     : true, 
                    oauth      : true
                  });                

                  FB.getLoginStatus(function(response) {
                    if (response.status != 'connected') {
                      $('#<?php $this->id('connect') ?>').show();
                    }
                  });
                };

                // Load the SDK Asynchronously
                (function(d){
                   var js, id = 'facebook-jssdk'; if (d.getElementById(id)) {return;}
                   js = d.createElement('script'); js.id = id; js.async = true;
                   js.src = "//connect.facebook.net/en_US/all.js";
                   d.getElementsByTagName('head')[0].appendChild(js);
                 }(document));
                })(jQuery);
            </script>

          <?php } else { ?>
            <p>To setup Moderation, you must supply the ID for a Facebook Application, above.
          <?php } ?>

          <br />
          <h3 class="title">Have you been using an XID?</h3>

          <table class="form-table">
            <tr>
              <td>
                <div style="margin-bottom:5px;">
                  <label style="float:left; margin-top:2px;">
                    <input type="radio" name="<?php $this->field('support_xid') ?>" value="on" <?php if ($this->should_support_xid()) echo 'checked="checked"' ?> />
                    Yes, please include my legacy comments.
                  </label>

                  <span style="float:left; margin-left:25px;">
                    <label for="<?php $this->id('xid') ?>">XID:</label>
                    <input type="text" class="regular-text" style="width:12em;" id="<?php $this->id('xid') ?>" name="<?php $this->field('xid') ?>" value="<?php echo esc_attr($xid) ?>" />
                  </span>

                  <div style="clear:both;"></div>
                </div>
                <div>
                  <label>
                    <input type="radio" name="<?php $this->field('support_xid') ?>" value="off" <?php if (!$this->should_support_xid()) echo 'checked="checked"' ?> />
                    Nope, no legacy support needed!
                  </label>
                </div>
              </td>
            </tr>
          </table>

          <br />
          <h3 class="title">Display the non-facebook comments that are in your database?</h3>

          <table class="form-table">
            <tr>
              <td>
                <div style="margin-bottom:5px;">
                  <label>
                    <input type="radio" name="<?php $this->field('show_old_comments') ?>" value="on" <?php if ($this->setting('show_old_comments', 'on') == 'on') echo 'checked="checked"' ?> />
                    Yes, because I've got a lot of historical comments in there!
                  </label>
                </div>
                <div>
                  <label>
                    <input type="radio" name="<?php $this->field('show_old_comments') ?>" value="off" <?php if ($this->setting('show_old_comments', 'on') == 'off') echo 'checked="checked"' ?> />
                    No, not necessary, but hide them in a <code>&lt;noscript&gt;</code> tag to maximize SEO.
                  </label>
                </div>
              </td>
            </tr>
          </table>

          <br />
          <h3 class="title">Display Settings</h3>

          <table class="form-table">
            <tr>
              <th>
                <label for="<?php $this->id('num_posts') ?>">Number of posts</label>
              </th>
              <td>
                <input type="text" class="regular-text" style="width:5em;" id="<?php $this->id('num_posts') ?>" name="<?php $this->field('num_posts') ?>" value="<?php echo esc_attr($this->get_num_posts()) ?>" />
                &nbsp;<span class="description">The number of posts to display by default</span>
              </td>
            </tr>
            <tr>
              <th>
                <label for="<?php $this->id('width') ?>">Width</label>
              </th>
              <td>
                <input type="text" class="regular-text" style="width:5em;" id="<?php $this->id('width') ?>" name="<?php $this->field('width') ?>" value="<?php echo esc_attr($this->get_width()) ?>" />
                &nbsp;<span class="description">The width of the widget, in pixels</span>
              </td>
            </tr>
            <tr>
              <th>
                <label for="<?php $this->id('colorscheme') ?>">Color Scheme</label>
              </th>
              <td>
                <select id="<?php $this->id('colorscheme') ?>" name="<?php $this->field('colorscheme') ?>">
                  <?php foreach(array('light', 'dark') as $scheme) { ?>
                    <option value="<?php echo $scheme ?>" <?php $this->selected($scheme === $this->setting('colorscheme', 'light')) ?>><?php echo $scheme ?></option>
                  <?php } ?>
                </select>
              </td>
            </tr>
            <tr>
              <th>
                <label for="<?php $this->id('comment_form_title') ?>">Form Title</label>
              </th>
              <td>
                <input type="text" class="regular-text" id="<?php $this->id('comment_form_title') ?>" name="<?php $this->field('comment_form_title') ?>" value="<?php echo esc_attr($this->setting('comment_form_title', '')) ?>" />
                <br /><span class="description">Just in case you need to add a title above your comment form, e.g., &lt;h3&gt;Comments&lt;/h3&gt;</span>
              </td>
            </tr>
          </table>

          <br />
          <h3 class="title">Advanced</h3>

          <table class="form-table">
            <tr>
              <th>
                <labe>Fix Notifications E-mails</label>
              </th>
              <td>
                <label>
                  <input type="radio" name="<?php $this->field('fix_notifications') ?>" value="on" <?php $this->checked($this->setting('fix_notifications', 'on') == 'on') ?> />
                  Yes
                </label>
                &nbsp;&nbsp;&nbsp;
                <label>
                  <input type="radio" name="<?php $this->field('fix_notifications') ?>" value="off" <?php $this->checked($this->setting('fix_notifications', 'on') == 'off') ?> />
                  No
                </label>
                &nbsp;&nbsp;&nbsp;
                <span class="description">Make sure Facebook comment messages appear in e-mails from WordPress</span>
              </td>
            </tr>
          </table>
          
          <p class="submit">
            <input type="submit" class="button-primary" value="<?php esc_attr_e('Save Changes'); ?>" />
          </p>
        </form>
      </div>
    <?php
  }

  function sanitize_settings($settings) {

    // clear this flag:
    update_option($this->id('imported_settings', false), false);

    return $settings;
  }


  // ===========================================================================
  // Helper functions - Provided to your plugin, courtesy of wp-kitchensink
  // http://github.com/collegeman/wp-kitchensink
  // ===========================================================================
    
  /**
   * This function provides a convenient way to access your plugin's settings.
   * The settings are serialized and stored in a single WP option. This function
   * opens that serialized array, looks for $name, and if it's found, returns
   * the value stored there. Otherwise, $default is returned.
   * @param string $name
   * @param mixed $default
   * @return mixed
   */
  function setting($name, $default = null) {
    $settings = get_option(sprintf('%s_settings', __CLASS__), array());
    return isset($settings[$name]) ? $settings[$name] : $default;
  }

  /**
   * Use this function in conjunction with Settings pattern #3 to generate the
   * HTML ID attribute values for anything on the page. This will help
   * to ensure that your field IDs are unique and scoped to your plugin.
   *
   * @see settings.php
   */
  function id($name, $echo = true) {
    $id = sprintf('%s_settings_%s', __CLASS__, $name);
    if ($echo) {
      echo $id;
    }
    return $id;
  }

  /**
   * Use this function in conjunction with Settings pattern #3 to generate the
   * HTML NAME attribute values for form input fields. This will help
   * to ensure that your field names are unique and scoped to your plugin, and
   * named in compliance with the setting storage pattern defined above.
   * 
   * @see settings.php
   */
  function field($name, $echo = true) {
    $field = sprintf('%s_settings[%s]', __CLASS__, $name);
    if ($echo) {
      echo $field;
    }
    return $field;
  }
  
  /**
   * A helper function. Prints 'checked="checked"' under two conditions:
   * 1. $field is a string, and $this->setting( $field ) == $value
   * 2. $field evaluates to true
   */
  function checked($field, $value = null) {
    if ( is_string($field) ) {
      if ( $this->setting($field) == $value ) {
        echo 'checked="checked"';
      }
    } else if ( (bool) $field ) {
      echo 'checked="checked"';
    }
  }

  /**
   * A helper function. Prints 'selected="selected"' under two conditions:
   * 1. $field is a string, and $this->setting( $field ) == $value
   * 2. $field evaluates to true
   */
  function selected($field, $value = null) {
    if ( is_string($field) ) {
      if ( $this->setting($field) == $value ) {
        echo 'selected="selected"';
      }
    } else if ( (bool) $field ) {
      echo 'selected="selected"';
    }
  }
  
}

#
# Initialize our plugin
#
FatPandaFacebookComments::load();