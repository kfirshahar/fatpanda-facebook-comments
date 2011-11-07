<?php
/*
Plugin Name: Facebook Comments by Fat Panda
Description: Replace WordPress commenting with the Facebook Comments widget, quickly and easily.
Version: 1.0.1
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
    
    add_filter('pre_comment_approved', array($this, 'pre_comment_approved'), 10, 2);
    add_filter('comment_reply_link', array($this, 'comment_reply_link'), 10, 4); //add_filter('get_comments_number', array($this, 'get_comments_number'), 10, 2);
    
    add_filter('plugin_action_links_fatpanda-facebook-comments/plugin.php', array($this, 'plugin_action_links'), 10, 4);

    if (!is_admin()) {
      wp_enqueue_script('jquery');
    }

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

  function api($path, $method = 'GET', $args = null) {
    $url = 'https://graph.facebook.com/'.$path;
    
    $response = wp_remote_get($url, array(
      'body' => $args,
      'sslverify' => false,
      'verifypeer' => false
    ));

    if (!is_wp_error($response)) {
      return json_decode($response['body']);
    } else {
      error_log($response->get_error_message());
      return false;
    }
  }

  function get_fb_comment_ids($href) {
    $ids = array();
    if ($comments = (array) $this->api('comments/?ids='.$href)) {
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
      
      $post = get_post($post_id);
      if (( $post_id = url_to_postid($response['href']) ) && ( $post = get_post($post_id) )) {
        try {
          if ($comments = (array) $this->api('comments/?ids='.$response['href'])) {
      
            // print_r($comments);
      
            foreach($comments[$response['href']]->data as $comment) {
              try {
                $this->update_fb_comment($post, $comment);
              } catch (Exception $e) {
                // continue on
              }
            }
          } else {
            echo 'fail';
          }
        } catch (Exception $e) {
          print_r($e);
        }
      }
    }  
    exit;  
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

  private function update_fb_comment($post, $comment) {
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

      $wp_comment_id = wp_new_comment($comment_data);

      if ($wp_comment_id && !is_wp_error($wp_comment_id)) {
        wp_update_comment_count($post->ID);
        update_comment_meta($wp_comment_id, 'fb_comment', $comment);
        update_comment_meta($wp_comment_id, 'fb_comment_id', $comment->id);
        update_comment_meta($wp_comment_id, 'fb_commenter_id', $comment->from->id);
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
      return;
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
                    Yes, replace built-in commenting with the <a href="http://developers.facebook.com/docs/reference/plugins/comments/" target="_blank">Facebook Comments widget</a>.
                  </label>
                </div>
                <div>
                  <label>
                    <input type="radio" name="<?php $this->field('comments_enabled') ?>" value="off" <?php if (!$this->is_enabled()) echo 'checked="checked"' ?> />
                    No, please disable this plugin.
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
                    Yes, please import my comments.
                  </label>

                  <span style="float:left; margin-left:25px;">
                    <label for="<?php $this->id('app_id') ?>">
                      <a href="http://developers.facebook.com/docs/appsonfacebook/tutorial/" target="_blank">Facebook Application</a> App ID:
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
                    No, do not import comments.
                  </label>
                </div>
              </td>
            </tr>
          </table>

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
                    <input type="radio" name="<?php $this->field('show_old_comments') ?>" value="on" <?php if ($this->setting('show_old_comments', 'off') == 'on') echo 'checked="checked"' ?> />
                    Yes, because I've got a lot of historical comments in there!
                  </label>
                </div>
                <div>
                  <label>
                    <input type="radio" name="<?php $this->field('show_old_comments') ?>" value="off" <?php if ($this->setting('show_old_comments', 'off') == 'off') echo 'checked="checked"' ?> />
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