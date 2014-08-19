=== Plugin Name ===
Contributors: aaroncollegeman
Donate link: http://aaroncollegeman.com/facebook-comments-for-wordpress
Tags: facebook, comments, social
Requires at least: 3.0
Tested up to: 3.9.2
Stable tag: 1.0.11

Replace WordPress commenting with the Facebook Comments widget, quickly and easily.

== Description ==

This plugin is a drop-in solution for replacing the default WordPress commenting 
with the [Facebook Comments widget](http://developers.facebook.com/docs/reference/plugins/comments/).
It is far-and-away the easiest to use plugin available - just try it and see! Check it out:

* Zero configuration to get it working - just install and activate
* Imports your Facebook comments into your WordPress database for safe-keeping!
* Using [SharePress](http://wordpress.org/extend/plugins/sharepress)? Import settings automagically!
* All of your WordPress comments are retained, with option to display them below the Facebook box
* All comments are printed in hidden <noscript> blocks to maximize SEO

All you'll need is a [Facebook Application](http://aaroncollegeman.com/facebook-comments-for-wordpress/how-to-setup-facebook-comments/)
- don't worry: it's not that hard to setup, and it's free!

== Installation ==

Find installation and configuration instructions [here](http://aaroncollegeman.com/facebook-comments-for-wordpress/how-to-setup-facebook-comments/).

== Frequently Asked Questions ==

Learn more about this and other Fat Panda plugins on our [website](http://aaroncollegeman.com/facebook-comments-for-wordpress).

== Changelog ==

= 1.0.11 =
* Fixed: Was producing NOTICE errors when WP_DEBUG was set to true [Thomas Bartels](https://github.com/tbartels)

= 1.0.10 =
* Added: Support for fluid widths

= 1.0.9 = 
* Added: Filter for changing the comments markup: just add a filter (add_filter('fb_comments_template', 'fb_old_comments_template');) and function with the override into the functions and a function with the comment block override

= 1.0.8 =
* Fixed: Moderator setup

= 1.0.7 =
* Added: Filter for disabling plugin on pages: just put add_filter('fbc_disable_on_pages', '__return_true') in your functions.php
* Fixed: Bumped up compatability version

= 1.0.6 =
* Fixed: Installing this plugin was breaking the core commenting system.
* Fixed: When previewing schedule posts, the FB comments widget was loading, allow FB to cache invalid responses, which would subsequently result in the open graph image not appearing in Facebook shares of the post

= 1.0.5 =
* Added: Detect locale, and specify when loading Facebook JS SDK, affecting localization of Comments Widget
* Changed: Remaining XID options hidden or disabled
* Fixed: Make Facebook comment import notification respect WordPress' global comment notification settings

= 1.0.4 =
* Added: Global and per-page moderator management
* Added: OG meta data inserted on single post and page views, only when SharePress is not installed; action fbc_og_print and filter fbc_og_tags 
* Changed: XID support for legacy comments isn't working properly; hiding those settings
* Fixed: Facebook changed the structure of their API response to requests for comments, this resulted in incorrect comment counts and no importing of Facebook comments

= 1.0.3 =
* Fixed: Wasn't importing reply-to comments
* Change: Display old comments by default
* Change: By default, don't display comment template title
* Added: "Refresh" post/page action -- allows you to reset comment count for individual posts on demand (and WP Super Cache, too!)
* Added: Comment content now included in WP notification e-mails
* Added: Color scheme picker in display settings (light and dark)

= 1.0.2 =
* Fixed: Importing wasn't working
* Added: Customizeable comment template title

= 1.0.1 =
* Trying to get WordPress.org to flush description information

= 1.0 =
* First release.
