<?php
/*
Plugin Name: Facebook Comments by Fat Panda
Description: Replace WordPress commenting with the Facebook Comments widget, quickly and easily.
Version: 1.0.11
Author: Aaron Collegeman, Fat Panda
Author URI: http://fatpandadev.com
Plugin URI: http://aaroncollegeman.com/facebook-comments-for-wordpress
*/

$__FB_COMMENT_EMBED = false;

@define('FatPandaFacebookComments_DEBUG', true);

class FatPandaFacebookComments {

  const META_PING = '_fbc_pinged';
  const META_LAST_PING = '_fbc_last_ping';

  private static $plugin;
  static function load() {
    $class = __CLASS__; 
    return ( self::$plugin ? self::$plugin : ( self::$plugin = new $class() ) );
  }

  private function __construct() {
    add_action( 'init', array( $this, 'init' ) );
    add_action('admin_init', array($this, 'admin_init'));
  }

  function admin_init() {
    if ( !empty( $_REQUEST['action'] ) && $_REQUEST['action'] == 'fatpanda-facebook-comments-reset-app' && current_user_can( 'edit_plugins' ) ) {
      $settings = get_option(sprintf('%s_settings', __CLASS__), array());
      $settings['app_id'] = '';
      update_option(sprintf('%s_settings', __CLASS__), $settings);
      wp_redirect( admin_url('options-general.php?page='.__CLASS__) );
    }
  }

  function init() {  
    if (is_admin()) {
      add_action('admin_menu', array($this, 'admin_menu'));  
    }

    add_filter('comments_template', array($this, 'comments_template'));

    add_action(sprintf('wp_ajax_%s_uncache', __CLASS__), array($this, 'uncache'));

    add_action('wp_ajax_fbc_ping', array($this, 'ping'));
    add_action('wp_ajax_nopriv_fbc_ping', array($this, 'ping'));
    
    // add_filter('pre_comment_approved', array($this, 'pre_comment_approved'), 10, 2);
    add_filter('comment_reply_link', array($this, 'comment_reply_link'), 10, 4);
    
    add_filter('plugin_action_links_fatpanda-facebook-comments/plugin.php', array($this, 'plugin_action_links'), 10, 4);

    add_filter('post_row_actions', array($this, 'post_row_actions'), 10, 2);
    add_filter('page_row_actions', array($this, 'post_row_actions'), 10, 2);

    add_filter('sharepress_og_tags', array($this, 'sharepress_og_tags'), 10, 2);

    if (apply_filters('fbc_can_fix_notifications', true)) {
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

    add_action('wp_head', array($this, 'wp_head'));

    // add_action('template_redirect', array($this, 'template_redirect'));

    add_filter('cron_schedules', array($this, 'cron_schedules'));

    if (!wp_next_scheduled('fbc_fifteenminute_cron')) {
      wp_schedule_event(time(), 'fifteenminute', 'fbc_fifteenminute_cron');
    }

    add_action('fbc_fifteenminute_cron', array($this, 'fifteenminute_cron'));
  }

  function get_permalink($ref = null) {
    $permalink = get_permalink($ref);
    return apply_filters('fbc_get_permalink', $permalink, $ref);
  }

  function fifteenminute_cron() {
    $this->download_new_comments();
  }

  function cron_schedules($schedules) {
    $schedules['fifteenminute'] = array(
      'interval' => 900,
      'display' => __('Every Fifteen Minutes')
    );
    
    return $schedules;
  }

  // function template_redirect() {
  //   if (!current_user_can('administrator')) {
  //     return false;
  //   }

  //   if (preg_match('#fbc(/.*)/?#', $_SERVER['REQUEST_URI'], $matches)) {
  //     $slugs = array_filter(explode('/', $matches[1]));
  //     $callback = array($this, array_shift($slugs));
  //     ob_start();
  //     try {
  //       call_user_func_array($callback, $slugs);
  //     } catch (Exception $e) {
  //       status_header(500);
  //       echo $e->getMessage();
  //     }
  //     status_header(200);
  //     $content = ob_get_clean();
  //     echo '<pre>';
  //     echo $content;
  //     exit;
  //   }
  // }

  /**
   * Try to download as many new comments as possible.
   */
  function download_new_comments() {
    global $wpdb;

    // data set #1: all posts with a ping flag
    $flag = self::META_PING;
    $pinged = $wpdb->get_results("
      SELECT `post_ID` AS `ID`, `meta_value` as `permalink`
      FROM {$wpdb->postmeta}
      WHERE 
        `meta_key` = '{$flag}'
    ");

    // data set #2: any post touched in the last fifteen days,
    // but not refreshed for at least 15 minutes
    $oldest = date('Y-m-d 00:00:00', time() - 86400 * 15);
    $last_ping = self::META_LAST_PING;
    $fifteen_mins_ago = time() - 900;
    $newish = $wpdb->get_results($sql = "
      SELECT ID, `meta_value` AS `last_ping`
      FROM {$wpdb->posts}
      LEFT OUTER JOIN {$wpdb->postmeta} ON (
        {$wpdb->postmeta}.`post_ID` = {$wpdb->posts}.`ID`
        AND `meta_key` = '{$last_ping}'
        AND `meta_value` IS NOT NULL
      )
      WHERE
        `post_modified_gmt` > '{$oldest}'
        AND `post_status` = 'publish'
        AND `comment_status` <> 'closed'
        AND (
          `meta_value` IS NULL
          OR `meta_value` < {$fifteen_mins_ago}
        )
    ");

    // create a collection that includes both
    $posts = array();

    foreach($pinged as $P) {
      $posts[$P->ID] = (object) array(
        'permalink' => $P->permalink
      );
    }

    foreach($newish as $P) {
      if (!isset($posts[$P->ID])) {
        $posts[$P->ID] = (object) array(
          'permalink' => null
        );
      }      
    }

    // fill-in missing permalink
    foreach($posts as $post_id => $P) {
      if (empty($P->permalink)) {
        $posts[$post_id]->permalink = $this->get_permalink($post_id);
      }
    }

    $href_index = array();
    foreach($posts as $post_id => $P) {
      $href_index[$P->permalink] = $post_id;
    }

    // chunk it
    $chunks = array_chunk($posts, 10, true);

    // download it
    $comments = array();
    
    foreach($chunks as $posts) {
      $hrefs = array_map( create_function('$P', 'return $P->permalink;'), $posts );
      try {
        if ($response = (array) $this->api('comments', array('limit' => 1000, 'ids' => implode(',', $hrefs)))) {
          if (isset($response['error'])) {
            // TODO: log
            continue;
          }

          foreach($response as $href => $packet) {
            if ($packet->comments) {
              $post = get_post($href_index[$href]);
              foreach($packet->comments->data as $comment) {
                try {
                  $this->update_fb_comment($post, $comment);
                } catch (Exception $e) {
                  // TODO: log
                  // soldier on...
                }
              }

              // update the milestone
              update_post_meta($post->ID, self::META_LAST_PING, time());
              // delete the ping flag
              delete_post_meta($post->ID, self::META_PING);
            }
          }
        } else {
          // TODO: log
        }
      } catch (Exception $e) {
        // TODO: log
      }
    }
  }

  function ping() {
    if (wp_verify_nonce($_POST['nonce'], 'fbc'.$_POST['post_id'])) {
      $post = get_post($_POST['post_id']);
      if ($post->ID && $post->comment_status != 'closed') {
        update_post_meta($_POST['post_id'], self::META_PING, $this->get_permalink($_POST['post_id']));
        echo 'pinged:'.$_POST['post_id'];
      }

    } else {
      status_header(500);
      echo 'invalid';
      
    }

    exit;
  }

  function get_first_image_for($post_id) {
    #
    # try the DB first...
    #
    $images = array_values( get_children(array( 
      'post_type' => 'attachment',
      'post_mime_type' => 'image',
      'post_parent' => $post_id,
      'orderby' => 'menu_order',
      'order'  => 'ASC',
      'numberposts' => 1,
    )) );

    if ($images && ( $src = wp_get_attachment_image_src($images[0]->ID, 'thumbnail') )) {
      return $src[0];
    
    #
    # fall back on sniffing out <img /> tags from post content
    #
    } else {
      $post = get_post($post_id);
      if ($content = do_shortcode($post->post_content)) {
        preg_match_all('/<img[^>]+>/i', $post->post_content, $matches);
        foreach($matches[0] as $img) {
          if (preg_match('#src="([^"]+)"#i', $img, $src)) {
            return $src[1];
          } else if (preg_match("#src='([^']+)'#i", $img, $src)) {
            return $src[1];
          }
        }
      }

    }
  }

  private function sharepressHandlesOg() {
    return class_exists('SharePress') && ( self::setting('page_og_tags', 'on') == 'on' || self::setting('page_og_tags', 'on') == 'imageonly' );
  }

  function sharepress_og_tags($og, $post) {
    if ($meta = get_post_meta($post->ID, 'fb:app_id', true)) {
      $og['fb:app_id'] = $meta;
    } else if (( $this->setting('app_moderator_mode') == 'on' ) && ( $app_id = $this->get_app_id() )) {
      $og['fb:app_id'] = $app_id;
    }

    if ($meta = get_post_meta($post->ID, 'fb:admins', true)) {
      $og['fb:admins'] = $meta;
    } else if (( $this->setting('admin_moderator_mode') == 'on' ) && ( $moderators = $this->setting('moderators') )) {
      $og['fb:admins'] = implode(',', $moderators);
    }

    return $og;
  }

  function wp_head() {
    if (!$this->sharepressHandlesOg() && apply_filters('fbc_can_print_og_tags', true)) {
      $og = array(
        'og:type' => 'article',
        'og:url' => $this->get_permalink(),
        'og:title' => get_bloginfo('name'),
        'og:site_name' => get_bloginfo('name'),
        'og:locale' => 'en_US'
      );

      if (is_single() || ( is_page() && !is_front_page() && !is_home() )) {
        global $post;

        if (!($excerpt = $post->post_excerpt)) {
          $excerpt = preg_match('/^.{1,256}\b/s', preg_replace("/\s+/", ' ', strip_tags($post->post_content)), $matches) ? trim($matches[0]).'...' : get_bloginfo('descrption');
        }

        $og['og:title'] = get_the_title();

        $og['og:description'] = $this->strip_shortcodes($excerpt);

        if ($picture = $this->get_first_image_for($post->ID)) {
          $og['og:image'] = $picture;
        }

        if ($meta = get_post_meta($post->ID, 'fb:app_id', true)) {
          $og['fb:app_id'] = $meta;
        } else if (( $this->setting('app_moderator_mode') == 'on' ) && ( $app_id = $this->get_app_id() )) {
          $og['fb:app_id'] = $app_id;
        }

        if ($meta = get_post_meta($post->ID, 'fb:admins', true)) {
          $og['fb:admins'] = $meta;
        } else if (( $this->setting('admin_moderator_mode') == 'on' ) && ( $moderators = $this->setting('moderators') )) {
          $og['fb:admins'] = implode(',', $moderators);
        }
      }

      $og = apply_filters('fbc_og_tags', $og);

      if ($og) {
        if (is_single() || ( is_page() && !is_front_page() && !is_home() )) {
          foreach($og as $property => $content) {
            echo sprintf("<meta property=\"{$property}\" content=\"%s\" />\n", str_replace(
              array('"', '<', '>'), 
              array('&quot;', '&lt;', '&gt;'), 
              $this->strip_shortcodes($content)
            ));
          }   
        }
      }
      
      // allow other plugins to insert og tags on our hook
      // this is for adding og to pages and what-not
      do_action( 'fbc_og_print' );
    } 
  }

  function admin_enqueue_scripts($hook) {
    if ($hook == 'settings_page_FatPandaFacebookComments') {
      wp_enqueue_script('jquery-ui-core');
      wp_enqueue_script('jquery-ui-autocomplete');
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
   * This filter controls the content of comment notification e-mails
   * triggered by new Facebook comments.
   */
  function comment_notification_text($notify_message, $comment_id) {
    extract($this->get_comment_data($comment_id));
    if ($comment->comment_type == 'facebook') {
      ob_start();
      $content = strip_tags($comment->comment_content);
      ?>
New Facebook Comment on your post "<?php echo $post->post_title ?>"

Author : <?php echo $comment->comment_author ?> 
URL : <?php echo $comment->comment_author_url ?> 

Comment:

<?php echo $content ?> 

Participate in the conversation here:
<?php echo $this->get_permalink($post->ID) ?> 

      <?php
      return ob_get_clean();
    } else {
      return $notify_message;
    }
  }

  function uncache() {
    if (($post_id = $_POST['post_id'])) {
      $this->refresh_comments_for_href(get_permalink($post_id));
      wp_update_comment_count($post_id);
      $counts = get_comment_count($post_id);
      echo $counts['approved'];
    }
    exit;
  }

  function post_row_actions($actions, $post) {
    if ($post->post_status == 'publish') {
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
    if (($app_id = $this->setting('app_id')) !== false) {
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
    $stored = str_replace('"', '', trim($this->setting('width', 600)));
    if (strpos($stored, '%') !== false) {
      return $stored;
    } else {
      return (int) $stored;
    }
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

  function strip_shortcodes($text) {
    // the WordPress way:
    $text = strip_shortcodes($text);
    // the manual way:
    return preg_replace('#\[/[^\]]+\]#', '', $text);

  }

  function get_xid() {
    if ($xid = $this->setting('xid')) {
      return $xid;
    } else if (($fbc_options = get_option('fbComments')) && ($xid = $fbc_options['xid'])) {
      update_option($this->id('imported_settings', false), true);
      return $xid;
    } else {
      return '';
    }
  }

  function comment_reply_link($html, $args, $comment, $post) {
    if ( !$this->is_enabled() ) {
      return $html;
    } else {
      return '';
    }
  }

  /**
   * Send a request to the Graph API.
   * @param $path
   * @param $params
   * @param $method
   * @return The API response or a WP_Error object
   */
  function api($path, $params = null, $method = 'GET') {
    $http = _wp_http_get_object();

    $url = 'https://graph.facebook.com/'.trim($path, '/');

    $args = array();
    $args['method'] = $method;
    
    if ($method == 'POST') {
      $args['body'] = http_build_query($params, null, '&');
    } else if ($params) { 
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
      if ($comments[$href]->comments) {
        foreach($comments[$href]->comments->data as $comment) {
          $ids[] = $comment->id;
        }
      }
    } else {
      return false;
    }
    return $ids;
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

          if ($comments[$href]->comments) {
            foreach($comments[$href]->comments->data as $comment) {
              try {
                $this->update_fb_comment($post, $comment);
              } catch (Exception $e) {
                echo sprintf("Failed to update FB comment {$comment->id} - %s\n", $e->getMessage());
                // continue on
              }
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

  /**
   * This function borrows functionality from the core wp_new_comment(),
   * but eliminates all the tests for duplicate content, spamming, or flooding,
   * relying on the Facebook platform to guard against those things.
   * @param array $commentdata Comment data to be saved.
   * @return int The new comment's primary key
   */
  private function wp_new_comment( $commentdata ) {
    $commentdata = apply_filters('preprocess_comment', $commentdata);

    $commentdata['comment_post_ID'] = (int) $commentdata['comment_post_ID'];
    if ( isset($commentdata['user_ID']) ) {
      $commentdata['user_id'] = $commentdata['user_ID'] = (int) $commentdata['user_ID'];
    } elseif ( isset($commentdata['user_id']) ) {
      $commentdata['user_id'] = (int) $commentdata['user_id'];
    }

    $commentdata['comment_date']     = current_time('mysql');
    $commentdata['comment_date_gmt'] = current_time('mysql', 1);

    $commentdata = wp_filter_comment($commentdata);

    $commentdata['comment_approved'] = 1;

    $comment_ID = wp_insert_comment($commentdata);

    do_action('comment_post', $comment_ID, $commentdata['comment_approved']);
    
    if (get_option('comments_notify')) {
      wp_notify_postauthor($comment_ID);
    }
    
    return $comment_ID;
  }

  /**
   * Given a comment construct from the Graph API, make sure that any
   * comments that we haven't seen before get written into the WP database.
   * @param stdClass $post A WP post object 
   * @param stdClass $comment A comment construct, sometimes containing replies construct
   * @param string $parent_id When called recursively (for writing replies to the DB),t his
   * argument is used to relate the reply to its parent comment.
   */
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
        'comment_type' => 'facebook',
        'comment_author_url' => 'http://facebook.com/profile.php?id='.$comment->from->id
      );

      if (!is_null($parent_id)) {
        $comment_data['comment_parent'] = $parent_id;
      }

      $wp_comment_id = $this->wp_new_comment($comment_data);

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

  /**
   * Determine whether or not to load our overriding comments template part.
   */
  function comments_template($template) {
    global $__FB_COMMENT_EMBED;
    global $post;
    global $comments;

    if (!apply_filters('fbc_force_enabled', false, $post)) {

      if ( is_page() && apply_filters('fbc_disable_on_pages', false) ) {
        return '';
      }

      if ( !( is_singular() && ( have_comments() || 'open' == $post->comment_status ) ) ) {
        return '';
      }

      if ( $post->post_status != 'publish' || !empty($_REQUEST['preview'])) {
        return '';
      }

      if ( !$this->is_enabled() ) {
        return $template;
      }

    }

    $__FB_COMMENT_EMBED = true;
    return dirname(__FILE__).'/comments.php';
  }

  function admin_menu() {  
    add_options_page( 'Facebook Comments', 'Facebook Comments', 'administrator', __CLASS__, array( $this, 'settings' ) ); 
    register_setting( __CLASS__, sprintf('%s_settings', __CLASS__), array( $this, 'sanitize_settings' ) );
  }

  function settings() {
    $app_id = $this->get_app_id();
    $xid = $this->get_xid();
    require(dirname(__FILE__).'/settings.php');
  }

  function sanitize_settings($settings) {
    $data = $_POST[__CLASS__.'_settings'];

    if (empty($data['app_moderator_mode'])) {
      $settings['app_moderator_mode'] = 'off';
    }

    if (empty($data['admin_moderator_mode'])) {
      $settings['admin_moderator_mode'] = 'off'; 
    }

    // clear this flag:
    update_option($this->id('imported_settings', false), false);

    return $settings;
  }

  static function err($message) {
    self::log($message, 'ERROR');
  }
  
  static function log($message, $level = 'INFO') {
    if (defined(__CLASS__.'_DEBUG') && constant(__CLASS__.'_DEBUG')) {
      global $thread_id;
      if (is_null($thread_id)) {
        $thread_id = substr(md5(uniqid()), 0, 6);
      }
      $dir = dirname(__FILE__);
      $filename = $dir.'/'.__CLASS__.'-'.gmdate('Ymd').'.log';
      $message = sprintf("%s %s %-5s %s\n", $thread_id, get_date_from_gmt(gmdate('Y-m-d H:i:s'), 'H:i:s'), $level, $message);
      if (!@file_put_contents($filename, $message, FILE_APPEND)) {
        error_log("Failed to access ".__CLASS__." log file [$filename] for writing: add write permissions to directory [$dir]?");
      }
    }
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
    } else {
      return $id;
    }
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
    if (($at = strpos($name, '[]')) !== false) {
      $field = sprintf('%s_settings[%s][]', __CLASS__, substr($name, 0, $at));
    } else {
      $field = sprintf('%s_settings[%s]', __CLASS__, $name);
    }
    if ($echo) {
      echo $field;
    } else {
      return $field;
    }
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
