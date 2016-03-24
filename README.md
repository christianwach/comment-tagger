Comment Tagger
==============

The *Comment Tagger* plugin lets logged-in readers tag comments. The plugin is intended to work with [*CommentPress Core*](https://wordpress.org/plugins/commentpress-core/) but can (with some effort) be used with other themes. Please bear in mind that this plugin is at an early stage of development.

If you visit a Comment Tag Archive page and get a "Page Not Found" message, visit your Permalinks Settings page to refresh the WordPress rewrite rules.

## Installation ##

### GitHub ###

There are two ways to install from GitHub:

#### ZIP Download ####

If you have downloaded *Comment Tagger* as a ZIP file from the GitHub repository, do the following to install and activate the plugin and theme:

1. Unzip the .zip file and, if needed, rename the enclosing folder so that the plugin's files are located directly inside `/wp-content/plugins/comment-tagger`
2. Activate the plugin
3. Refer to Setup instructions below
4. You are done!

#### git clone ####

If you have cloned the code from GitHub, it is assumed that you know what you're doing.

## Setup ##

The plugin will work "out of the box" with [*CommentPress Core*](https://wordpress.org/plugins/commentpress-core/). If you are using another theme, then you will need to make some additions to your theme.

You will need to create a taxonomy archive template to display tagged comments. A sample template for the TwentyTwelve theme template is provided with this plugin - you can find it in 'assets/templates/twentytwelve/taxonomy-comment_tags.php'. Copy this file to the top level of your theme's directory and amend it to match your theme's structure and markup. You will also need to style the output to match your theme.

If you visit a Comment Tag Archive page and get a "Page Not Found" message, visit your Permalinks Settings page to refresh the WordPress rewrite rules.
