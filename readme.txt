=== Comment Tagger ===
Contributors: needle
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=8MZNB9D3PF48S
Tags: comments, tagging, taxonomy, commentpress
Requires at least: 4.4
Tested up to: 5.2
Stable tag: 0.1.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Enables logged-in readers to add tags to comments.



== Description ==

The *Comment Tagger* plugin lets logged-in readers add tags to comments. The plugin works out-of-the-box with [*CommentPress Core*](https://wordpress.org/plugins/commentpress-core/) but can also be used with other themes. Please see 'Installation' for details.

### Plugin Development

This plugin is in active development. For feature requests and bug reports (or if you're a plugin author and want to contribute) please visit the plugin's [GitHub repository](https://github.com/christianwach/comment-tagger).



== Installation ==

1. Extract the plugin archive
1. Upload plugin files to your `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress

You will need to create a taxonomy archive template to display tagged comments. A sample template for the *Twenty Twelve* theme is provided with this plugin - you can find it in 'assets/templates/twentytwelve/taxonomy-comment_tags.php'. Copy this file to the top level of your theme's (or child theme's) directory and amend it to match your theme's structure and markup. You will also need to style the output to match your theme.

If you visit a Comment Tag Archive page and get a "Page Not Found" message, visit your Permalinks Settings page to refresh the WordPress rewrite rules.



== Changelog ==

= 0.1.2 =

Allow comment authors to assign terms in WordPress Admin.

= 0.1.1 =

Switch to local version of Select2 library.

= 0.1 =

Initial commit.
