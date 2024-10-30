<?php
/*
Plugin Name: Joemobi
Plugin URI: http://wordpress.org/extend/plugins/joemobi/
Description: The Joemobi plugin allows you to make your blog go mobile. Once this plugin is installed all you have to do is create an app at http://www.joemobi.com and publish it. Then sit back and reap the benefits of having your blog on the mobile scene!
Version: 2.10
Author: The Joemobi Dev Team
Author URI: http://www.joemobi.com/

Copyright 2011 SpeakFeel Corp - Jeffrey Sambells  (email : jsambells@speakfeel.ca)

This file is based on the JSON API plugin:
http://wordpress.org/extend/plugins/json-api/

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

*/

$dir = joemobi_api_dir();
@include_once "$dir/singletons/api.php";
@include_once "$dir/singletons/query.php";
@include_once "$dir/singletons/introspector.php";
@include_once "$dir/singletons/response.php";
@include_once "$dir/models/post.php";
@include_once "$dir/models/comment.php";
@include_once "$dir/models/category.php";
@include_once "$dir/models/tag.php";
@include_once "$dir/models/author.php";
@include_once "$dir/models/attachment.php";

function joemobi_get_version() {
	$php = file_get_contents(__FILE__);
	if (preg_match('/^\s*Version:\s*(.+)$/m', $php, $matches)) {
	  $version = $matches[1];
	} else {
	  $version = '(Unknown)';
	}
	return $version;
}

function joemobi_api_init() {
  global $joemobi_api;
  if (phpversion() < 5) {
    add_action('admin_notices', 'joemobi_api_php_version_warning');
    return;
  }
  if (!class_exists('JOEMOBI_API')) {
    add_action('admin_notices', 'joemobi_api_class_warning');
    return;
  }
  add_filter('rewrite_rules_array', 'joemobi_api_rewrites');
  $joemobi_api = new JOEMOBI_API();
}

function joemobi_wp_head() {
	echo '<!-- JoeMobi (http://joemobi.com) v:' .  joemobi_get_version() . ' -->';
}

function joemobi_api_php_version_warning() {
  echo "<div id=\"joemobi-api-warning\" class=\"updated fade\"><p>Sorry, Joemobi requires PHP version 5.0 or greater.</p></div>";
}

function joemobi_api_class_warning() {
  echo "<div id=\"joemobi-api-warning\" class=\"updated fade\"><p>Oops, JOEMOBI_API class not found. If you've defined a JOEMOBI_API_DIR constant, double check that the path is correct.</p></div>";
}

function joemobi_api_activation() {
  // Add the rewrite rule on activation
  global $wp_rewrite;
  add_filter('rewrite_rules_array', 'joemobi_api_rewrites');
  $wp_rewrite->flush_rules();
}

function joemobi_api_deactivation() {
  // Remove the rewrite rule on deactivation
  global $wp_rewrite;
  $wp_rewrite->flush_rules();
}

function joemobi_api_rewrites($wp_rules) {
  $base = get_option('joemobi_api_base', 'api');
  if (empty($base)) {
    return $wp_rules;
  }
  $joemobi_api_rules = array(
    "$base\$" => 'index.php?joemobi=info',
    "$base/(.+)\$" => 'index.php?joemobi=$matches[1]'
  );
  return array_merge($joemobi_api_rules, $wp_rules);
}

function joemobi_api_dir() {
  if (defined('JOEMOBI_API_DIR') && file_exists(JOEMOBI_API_DIR)) {
    return JOEMOBI_API_DIR;
  } else {
    return dirname(__FILE__);
  }
}

function joemobi_post_notification( $post ) {
	$p = get_post($post);

	// Don't notify on edits.
	// We're assuming here an "edit" is when the modification date is > the published date.
	if (@strtotime($p->post_date) >= @strtotime($p->post_modified)) {
		$context = stream_context_create(array(
	      'http' => array(
	          'header'=>'Connection: close',
	          'timeout' => 3
	      )
	  ));
		@file_get_contents('http://api.joemobi.com/notify/?id=' . $post . '&url=' . urlencode(get_bloginfo('url')), false, $context);
	}

}

function joemobi_comment_duplicate_trigger( $comment ) {
	global $joemobi_api;
	$joemobi_api->error("Duplicate comment detected; it looks as though you’ve already said that!");
}


// Add initialization and activation hooks
add_action('init', 'joemobi_api_init');
register_activation_hook("$dir/joemobi-api.php", 'joemobi_api_activation');
register_deactivation_hook("$dir/joemobi-api.php", 'joemobi_api_deactivation');
add_action( 'publish_post', 'joemobi_post_notification' );
add_action( 'comment_duplicate_trigger', 'joemobi_comment_duplicate_trigger' );
add_action('wp_head', 'joemobi_wp_head');



require_once 'joemobi_v1.php';


/* DEBUGGING */

/*
add_action( 'init', 'debug_create_post_type' );
function debug_create_post_type() {

	register_post_type( 'acme_product',
		array(
			'labels' => array(
				'name' => __( 'Acme Products' ),
				'singular_name' => __( 'Acme Product' )
			),
		'public' => true,
		'has_archive' => true,
		)
	);
}
*/


