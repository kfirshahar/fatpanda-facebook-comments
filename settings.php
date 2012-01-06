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
    <?php settings_fields( get_class($this) ) ?>
    
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

      <p>To setup Moderation, you must supply the ID for a Facebook Application, above.</p>

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