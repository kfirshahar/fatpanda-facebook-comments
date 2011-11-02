<?php
/*
Plugin Name: Facebook Comments
Description: Replace WordPress comments with Facebook comments, quickly and easily.
Version: 0.2
Author: Aaron Collegeman, Fat Panda
Author URI: http://fatpandadev.com
Plugin URI: http://aaroncollegeman.com/wp-facebook-comments
*/

$__FB_COMMENT_EMBED = false;

class WpFacebookComments {

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

    if ($this->unlocked()) {
      add_action('wp_ajax_fb_create_comment', array($this, 'ajax_fb_create_comment'));
      add_action('wp_ajax_nopriv_fb_create_comment', array($this, 'ajax_fb_create_comment'));
      add_action('wp_ajax_fb_remove_comment', array($this, 'ajax_fb_remove_comment'));
      add_action('wp_ajax_nopriv_fb_remove_comment', array($this, 'ajax_fb_remove_comment'));
    }

    add_filter('pre_comment_approved', array($this, 'pre_comment_approved'), 10, 2);
    add_filter('comment_reply_link', array($this, 'comment_reply_link'), 10, 4); //add_filter('get_comments_number', array($this, 'get_comments_number'), 10, 2);
  
  }

  function get_app_id() {
    return $this->setting('app_id', class_exists('SharePress') ? get_option(SharePress::OPTION_API_KEY) : '');
  }

  function pre_comment_approved($approved, $commentdata) {
    if ($commentdata['comment_type'] == 'facebook') {
      return 1;
    } else {
      return $approved;
    }
  }

  function comment_reply_link($html, $args, $comment, $post) {
    if ( $this->setting('comments_enabled') != 'on' ) {
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

  function ajax_fb_create_comment() {
    if ($response = $_POST['response']) {
      $post = get_post($post_id);
      if (( $post_id = url_to_postid($response['href']) ) && ( $post = get_post($post_id) )) {
        try {
          if ($comments = (array) $this->api('comments/?ids='.$response['href'])) {
            foreach($comments[$response['href']]->data as $comment) {
              try {
                $this->update_fb_comment($post, $response['commentID'], $comment);
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

    return $wpdb->get_var(
      $wpdb->prepare("
        SELECT C.comment_ID 
        FROM $wpdb->comments C JOIN $wpdb->commentmeta M ON (C.comment_ID = M.comment_id) 
        WHERE 
          C.comment_post_ID = %s 
          AND M.meta_key = 'fb_comment_id' 
          AND M.meta_value = %s
      ", 
        $post_id, 
        $fb_comment_id
      )
    );
  }

  private function update_fb_comment($post, $comment_id, $comment) {
    $wp_comment_id = $this->get_wp_comment_for_fb($post->ID, $comment_id);

    if (!$wp_comment_id) {
      if (preg_match('/((\d\d\d\d)-(\d\d)-(\d\d))T((\d\d):(\d\d):(\d\d))/', $comment->created_time)) {
        $gmdate = "{$matches[1]} {$matches[5]}";
      } else {
        $gmdate = gmdate('Y-m-d H:i:s');
      }

      $wp_comment_id = wp_new_comment(array(
        'comment_post_ID' => $post->ID,
        'comment_author' => $comment->from->name,
        'comment_content' => $comment->message,
        'comment_date' => get_date_from_gmt($gmdate),
        'comment_date_gmt' => $gmdate,
        'comment_approved' => '1',
        'comment_type' => 'facebook'
      ));

      if ($wp_comment_id && !is_wp_error($wp_comment_id)) {
        wp_update_comment_count($post->ID);
        update_comment_meta($wp_comment_id, 'fb_comment', $comment);
        update_comment_meta($wp_comment_id, 'fb_comment_id', $comment_id);
        update_comment_meta($wp_comment_id, 'fb_commenter_id', $comment->from->id);
      }
    }
  }

  function ajax_fb_remove_comment() {
    if ($response = $_POST['response']) {
      if (( $post_id = url_to_postid($response['href']) ) && ( $post = get_post($post_id) )) {
        if ($wp_comment_id = $this->get_wp_comment_for_fb($post->ID, $response['commentID'])) {
          wp_delete_comment($wp_comment_id);
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

    if ( $this->setting('comments_enabled') != 'on' ) {
      return $template;
    }

    $__FB_COMMENT_EMBED = true;
    return dirname(__FILE__).'/comments.php';
  }

  function admin_notices() {
    if (current_user_can('administrator')) {
      if (@$_REQUEST['page'] == __CLASS__) {
        if ($this->setting('license_key') && strlen($this->setting('license_key')) != 32) {
          ?>
            <div class="error">
              <p>Hmm... looks like there's something wrong with your <a href="<?php echo get_admin_url() ?>options-general.php?page=<?php echo __CLASS__  ?>">Facebook Comments</a> license key.</p>
            </div>
          <?php
        } else if (!$this->unlocked()) {
          ?>
            <div class="updated">
              <p><b>Go pro!</b> This plugin can do more: a lot more. <a href="http://aaroncollegeman.com/wp-facebook-comments?utm_source=plugin&utm_medium=in-app-promo&utm_campaign=learn-more">Learn more</a>.</p>
            </div>
          <?php
        }
      }      
    }
  }
  
  function admin_menu() {
    
    add_options_page( 'Facebook Comments', 'Facebook Comments', 'administrator', __CLASS__, array( $this, 'settings' ) ); 
    
    register_setting( __CLASS__, sprintf('%s_settings', __CLASS__), array( $this, 'sanitize_settings' ) );

  } // END admin_menu

  function settings() {
    ?>  
      <div class="wrap">
        <div id="icon-general" class="icon32" style="background:url('<?php echo plugins_url('img/icon32.png', __FILE__) ?>') no-repeat;"><br /></div>
        <h2>Facebook Comments</h2>
        <form action="<?php echo admin_url('options.php') ?>" method="post">
          <?php settings_fields( __CLASS__ ) ?>
          
          <h3 class="title">Your License Key</h3>
          <?php 
            #
            # Don't be a dick. I like to eat, too.
            # http://aaroncollegeman/wp-facebook-comments/
            #
            if (!$this->unlocked()) { ?>
            <p>
              <a href="http://aaroncollegeman.com/wp-facebook-comments">Buy a license</a> key today.
              Unlock pro features, get access to documentation and support from the developer!
            </p>
          <?php } else { ?>
            <p>Awesome, tamales! Need support? <a href="http://aaroncollegeman.com/wp-facebook-comments/help/">Go here</a>.
          <?php } ?>

          <table class="form-table">
            <tr>
              <th><label for="license_key">License Key:</label></th>
              <td>
                <input type="text" style="width:25em;" class="regular-text" id="<?php $this->id('license_key') ?>" name="<?php $this->field('license_key') ?>" value="<?php echo esc_attr( $this->setting('license_key') ) ?>" />
              </td>
            </tr>
          </table>
          

          <br />
          <h3 class="title">Replace commenting with Facebook Comments widget?</h3>

          <table class="form-table">
            <tr>
              <td>
                <div style="margin-bottom:5px;">
                  <label>
                    <input type="radio" name="<?php $this->field('comments_enabled') ?>" value="on" <?php if ($this->setting('comments_enabled', 'on') == 'on') echo 'checked="checked"' ?> />
                    Yes, use the <a href="http://developers.facebook.com/docs/reference/plugins/comments/" target="_blank">Facebook Commenting widget</a> for commenting on my site
                  </label>
                </div>
                <div>
                  <label>
                    <input type="radio" name="<?php $this->field('comments_enabled') ?>" value="off" <?php if (self::setting('comments_enabled', 'on') == 'off') echo 'checked="checked"' ?> />
                    No, do not use Facebook Commenting
                  </label>
                </div>
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

    return $settings;
  }


  // ===========================================================================
  // Helper functions - Provided to your plugin, courtesy of wp-kitchensink
  // http://github.com/collegeman/wp-kitchensink
  // ===========================================================================
  
  #
  # Don't be a dick. I like to eat, too.
  # http://aaroncollegeman.com/wp-facebook-comments/
  #
  function unlocked() {
    return strlen($this->setting('license_key')) == 32;
  }
    
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
WpFacebookComments::load();