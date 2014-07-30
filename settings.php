<style>
  .wrap h2 span { position: relative; font-size: 0.75em; padding-left: 20px; }
  ul.ui-menu { background-color: #efefef; width: 300px; }
  ul.ui-menu > li { padding: 4px 8px 0; cursor: pointer; }
  ul.ui-menu > li:last-child { padding-bottom: 4px; }
  .form-table { width: auto; }
  .ad { position: absolute; top: 15px; right: 20px; border: 1px solid #ccc; padding: 4px; width:300px; }
  @media (max-width: 1024px) {
    .ad { width: 200px; }
    .ad img { width: 200px; }
  }
</style>


<?php if (get_option($this->id('imported_settings', false))) { ?>
  <div class="updated">
    <p>We <b>imported settings</b> from your other plugins. Make sure to look everything over closely!</p>
  </div>
<?php } ?>
  
<div class="wrap">
  <div id="icon-general" class="icon32" style="background:url('<?php echo plugins_url('icon32.png', __FILE__) ?>') no-repeat;"><br /></div>
  <h2>
    Facebook Comments
    <span>a plugin from <a href="http://fatpandadev.com" target="_blank">Fat Panda</a></span>
  </h2>

  <a target="_blank" class="ad" href="http://aaroncollegeman.com/sharepress/?utm_source=facebook-comments-plugin&utm_medium=in-app-promo&utm_campaign=sharepress-sleep-more">
    <img style="display:block;" src="<?php echo plugins_url('sharepress-ad.png', __FILE__) ?>" />
  </a>

  <form action="<?php echo admin_url('options.php') ?>" method="post" style="float:left;">
    
    <?php settings_fields( get_class($this) ) ?>
    
    <br />
    <h3 class="title" style="margin-top:0;">Use Facebook Comments for commenting on your site?</h3>

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
      <div id="<?php $this->id('moderator-picker') ?>" style="display:none;">
        <p class="description">You can set moderators on a post-by-post and page-by-page basis. Set your global defaults below.</p>
      
        <table  class="form-table">
          <tr>
            <td>
              <div style="margin-bottom: 5px;">
                <label style="float:left; margin-top:3px;">
                  <input type="checkbox" name="<?php $this->field('app_moderator_mode') ?>" value="on" <?php $this->checked($this->setting('app_moderator_mode', 'on') == 'on') ?> />
                  &nbsp;The administrators of my <a href="http://developers.facebook.com/docs/appsonfacebook/tutorial/" target="_blank">Facebook Application</a> are moderators
                </label>
                <span style="margin-left:25px;">
                  <label for="<?php $this->id('app_id') ?>">
                    App ID:
                  </label>
                  <input type="text" class="regular-text" id="<?php $this->id('app_id') ?>" style="width:12em;" name="<?php $this->field('app_id') ?>" value="<?php echo esc_attr($app_id) ?>" />
                </span>
                <div style="clear:both;"></div>
              </div>

              <div id="<?php $this->id('connected') ?>">

                <div>
                  <label>
                    <input type="checkbox" name="<?php $this->field('admin_moderator_mode') ?>" value="on" <?php $this->checked($this->setting('admin_moderator_mode', 'on') == 'on') ?> />
                    &nbsp;These Facebook people are also my moderators:
                  </label>
                </div>
                <p>
                  <ul id="<?php $this->id('moderators') ?>">
                    <?php foreach(array_unique($this->setting('moderators', array())) as $moderator) { ?>
                      <li><input type="hidden" name="<?php $this->field('moderators[]') ?>" value="<?php echo $moderator ?>" /> <span rel="<?php echo $moderator ?>"><?php echo $moderator ?></span> &nbsp;&nbsp;<a href="#" onclick="remove_moderator(this); return false;">remove</a></li>
                    <?php } ?>
                  </ul>
                  <div id="<?php $this->id('finder') ?>" style="display:none;">
                    <label for="<?php $this->id('add_another_friend') ?>">Search by name:</label>
                    &nbsp;<input id="<?php $this->id('add_another_friend') ?>" type="text" name="add_another_friend" class="regular-text" />
                    <span id="<?php $this->id('searching') ?>" style="display:none;">Searching...</span>
                    <a id="<?php $this->id('add-moderator') ?>" class="button" onclick="add_moderator(this); return false;" style="display:none;">Add Moderator</a>
                  </div>
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

                        $('#<?php $this->id('add_another_friend') ?>').autocomplete({
                          html: true,
                          source: function(request, response) {
                            $('#<?php $this->id('searching') ?>').show();
                            $('#<?php $this->id('add-moderator') ?>').hide();
                            FB.api('/search?q='+encodeURI(request.term)+'&type=user&limit=10', function(R) {
                              $('#<?php $this->id('searching') ?>').hide();
                              response($.map(R.data, function(user) {
                                return {
                                  label: '<img width="24" height="24" src="http://graph.facebook.com/'+user.id+'/picture?size=square" align="absmiddle" />&nbsp;&nbsp;'+user.name,
                                  value: user.id
                                }
                              }));
                            });
                          },
                          focus: false,
                          select: function(ui, e) {
                            $('#<?php $this->id('add-moderator') ?>').show();
                            console.log(arguments);
                          }
                        });

                        $('#<?php $this->id('finder') ?>').show();                            
                      });
                    })(jQuery);
                  </script>
                </p>

              </div>
            </td>
          </tr>
        </table> 
      </div>

      <div id="<?php $this->id('facebook-login') ?>">
        <a class="button-primary" onclick="FB.login(); return false;">Login to Facebook</a>
        &nbsp;<a class="button" href="<?php echo admin_url('?action=fatpanda-facebook-comments-reset-app') ?>">Change App ID</a>
      </div>  


      <div id="fb-root"></div>
      <script src="//connect.facebook.net/en_US/all.js"></script>
      <script>
        FB.init({
          appId: '<?php echo $app_id ?>'
        });
          
        (function($) {
          
          FB.getLoginStatus(function(response) {
            if (response.status === 'connected') {
              revealModeration();
            } else {
                // Subscribe to the event 'auth.authResponseChange' and wait for the user to autenticate
                FB.Event.subscribe('auth.authResponseChange', function(response) {
                    // nothing more needed than to reload the page
                   if (response.status === 'connected') {
                    revealModeration();
                   }
                },true);      
              $('#<?php $this->id('facebook-login') ?>').show();  
            }
          });

          function revealModeration() {
            $('#<?php $this->id('moderator-picker') ?>').show();
            $('#<?php $this->id('facebook-login') ?>').hide();

            var batch = $.map($('#<?php $this->id('moderators') ?> li span'), function(span,  i) {
              return { method: 'GET', relative_url: $(span).attr('rel') };
            });
            
            FB.api('/', 'POST', {
              'batch': batch
            }, function(response) {
              for (i in response) {
                var user = eval('('+response[i].body+')');
                $('span[rel="'+user.id+'"]').html('<img width="24" src="http://graph.facebook.com/'+user.id+'/picture?size=square" align="absmiddle" />&nbsp;&nbsp;<a href="http://facebook.com/profile.php?id='+user.id+'" target="_blank">'+user.name+'</a>');       
              }
            });
          }

          window.remove_moderator = function(a) {
            $(a).closest('li').remove();
          }

          window.add_moderator = function(a) {
            $('#<?php $this->id('add-moderator') ?>').hide();
            var $moderator = $('#<?php $this->id('add_another_friend') ?>');
            var $list = $('#<?php $this->id('moderators') ?>');
            var id = $moderator.val();
            $moderator.val('');
            var $placeholder = $('<li>Loading...</li>');
            $list.append($placeholder);

            FB.api(id, function(user) {
              $placeholder.replaceWith('<li><input type="hidden" name="<?php $this->field('moderators[]') ?>" value="'+user.id+'" /><img width="24" src="http://graph.facebook.com/'+user.id+'/picture?size=square" align="absmiddle" />&nbsp;&nbsp;<a href="http://facebook.com/profile.php?id='+user.id+'" target="_blank">'+user.name+'</a> &nbsp;&nbsp;<a href="#" onclick="remove_moderator(this); return false;">remove</a></li>');
            });
          }
        })(jQuery);
      </script>

    <?php } else { ?>

      <input type="hidden" name="<?php $this->field('app_moderator_mode') ?>" value="<?php echo esc_attr($this->setting('app_moderator_mode', 'on')) ?>" />
      <input type="hidden" name="<?php $this->field('admin_moderator_mode') ?>" value="<?php echo esc_attr($this->setting('admin_moderator_mode', 'on')) ?>" />
      <?php foreach($this->setting('moderators', array()) as $id) { ?>
        <input type="hidden" name="<?php $this->field('moderators[]') ?>" value="<?php echo esc_attr($id) ?>" />
      <?php } ?>

      <p>To setup Moderation, enter your <a href="http://developers.facebook.com/docs/appsonfacebook/tutorial/" target="_blank">Facebook Application ID</a> below.</p>

      <table class="form-table">
        <tr>
          <th>
            <label for="<?php $this->id('app_id') ?>">App ID:</label>
          </th>
          <td>
            <input type="text" name="<?php $this->field('app_id') ?>" value="<?php echo esc_attr($this->setting('app_id')) ?>" class="regular-text" />
            <input type="submit" class="button-primary" value="Save" />
          </td>
        </tr>
      </table>

    <?php } ?>

    <input type="hidden" name="<?php $this->field('xid') ?>" value="<?php esc_attr($xid) ?>" />
    <input type="hidden" name="<?php $this->field('support_xid') ?>" value="<?php echo $this->should_support_xid() ? 'on' : 'off' ?>" />

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
          &nbsp;<span class="description">The width of the widget, in pixels (e.g., "100") or percentage (e.g., "100%")</span>
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
      <input type="submit" class="button-primary" value="<?php esc_attr_e('Save All Changes'); ?>" />
    </p>
  </form>

  <div style="clear:both;"></div>
</div>