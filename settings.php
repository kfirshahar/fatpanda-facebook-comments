<?php if (get_option($this->id('imported_settings', false))) { ?>
  <div class="updated">
    <p>We <b>imported settings</b> from your other plugins. Make sure to look everything over closely!</p>
  </div>
<?php } ?>
  
<style>
  .wrap h2 span { font-size: 0.75em; padding-left: 20px; }
</style>
<div class="wrap">
  <div id="icon-general" class="icon32" style="background:url('<?php echo plugins_url('icon32.png', __FILE__) ?>') no-repeat;"><br /></div>
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

    <p class="description">You can set moderators on a post-by-post and page-by-page basis. Set your global defaults below.</p>

    <?php if ($app_id) { ?>
      <table class="form-table">
        <tr>
          <td>
            <div style="margin-bottom: 5px;">
              <label style="float:left; margin-top:3px;">
                <input type="checkbox" name="<?php $this->field('app_moderator_mode') ?>" value="on" <?php $this->checked($this->setting('app_moderator_mode', 'on') == 'on') ?> />
                &nbsp;Include the administrators of my <a href="http://developers.facebook.com/docs/appsonfacebook/tutorial/" target="_blank">Facebook Application</a>
              </label>
              <span style="margin-left:25px;">
                <label for="<?php $this->id('app_id') ?>">
                  App ID:
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
                <input type="checkbox" name="<?php $this->field('admin_moderator_mode') ?>" value="on" <?php $this->checked($this->setting('admin_moderator_mode', 'on') == 'on') ?> />
                &nbsp;Include the moderators I specify below: 
              </label>
              <span class="description" style="margin-left:25px;">If you don't use the application option above, remember to specify yourself as a moderator</span>
            </div>
            <p>
              <ul id="<?php $this->id('moderators') ?>">
                <li>
                  <label for="<?php echo $this->id('add_another_friend') ?>">Search:</label>
                  <input id="<?php echo $this->id('add_another_friend') ?>" type="text" name="add_another_friend" class="regular-text" />
                  &nbsp;<a href="#" class="button">Add Moderator</a>
                  <script>
                    (function($) {
                      $(function() {
                        /*
                         * jQuery UI Autocomplete HTML Extension
                         *
                         * Copyright 2010, Scott González (http://scottgonzalez.com)
                         * Dual licensed under the MIT or GPL Version 2 licenses.
                         *
                         * http://github.com/scottgonzalez/jquery-ui-extensions
                         */
                        var proto = $.ui.autocomplete.prototype,
                          initSource = proto._initSource;

                        function filter( array, term ) {
                          var matcher = new RegExp( $.ui.autocomplete.escapeRegex(term), "i" );
                          return $.grep( array, function(value) {
                            return matcher.test( $( "<div>" ).html( value.label || value.value || value ).text() );
                          });
                        }

                        $.extend( proto, {
                          _initSource: function() {
                            if ( this.options.html && $.isArray(this.options.source) ) {
                              this.source = function( request, response ) {
                                response( filter( this.options.source, request.term ) );
                              };
                            } else {
                              initSource.call( this );
                            }
                          },

                          _renderItem: function( ul, item) {
                            return $( "<li></li>" )
                              .data( "item.autocomplete", item )
                              .append( $( "<a></a>" ).addClass(item.value == null ? 'null-item' : '')[ this.options.html ? "html" : "text" ]( item.label ) )
                              .appendTo( ul );
                          }
                        });

                        $('#<?php echo $this->id('add_another_friend') ?>').autocomplete({
                          html: true,
                          source: function(request, response) {
                            $.post(ajaxurl, { action: 'fbc_search_fb_users', q: request.term }, function(list) {
                              response(list.result);
                            });
                          },
                          focus: false,
                          select: function(ui, e) {
                            console.log(arguments);
                          }
                        });
                      });
                    })(jQuery);
                  </script>
                </li>
                <li><input type="hidden" name="<?php $this->field('moderators[]') ?>" value="710757081" /> <span rel="710757081">710757081</span> &nbsp;&nbsp;<a href="#">remove</a></li>
                <li><input type="hidden" name="<?php $this->field('moderators[]') ?>" value="25515241" /> <span rel="25515241">25515241</span> &nbsp;&nbsp;<a href="#">remove</a></li>
              </ul>
            </p>
          </td>
        </tr>
      </table> 


      <div id="fb-root"></div>
      <script>
        (function($) {
          var ids = $.map($('#<?php $this->id('moderators') ?> li span'), function(span,  i) {
            return $(span).attr('rel');
          });

          $.post(ajaxurl, { action: 'fbc_get_fb_user', ids: ids.join(',') }, function(users) {
            users = eval('('+users+')');
            $.each(users, function(i, user) {
              $('span[rel="'+user.id+'"]').html('<img width="24" src="http://graph.facebook.com/'+user.id+'/picture?size=square" align="absmiddle" />&nbsp;&nbsp;'+user.name);       
            });
          });
        })(jQuery);
      </script>

    <?php } else { ?>

      <p>To setup Moderation, you must supply the ID for a Facebook Application, above.</p>

      <table class="form-table">
        <tr>
          <td>
            <div style="margin-bottom:5px;">
              <label style="float:left; margin-top:3px;">
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
    
    <p class="submit">
      <input type="submit" class="button-primary" value="<?php esc_attr_e('Save Changes'); ?>" />
    </p>
  </form>
</div>