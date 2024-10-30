<?php
/*

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

// Alter query vars to include JoMobi v1 vars
function joemobi_api_joemobi1_output_queryvars( $qvars ) {
	// http://codex.wordpress.org/WordPress_Query_Vars
  $qvars[] = 'jo_show'; /* posts per page */
  $qvars[] = 'jo_post'; /* post id */
  $qvars[] = 'jo_page'; /* show page number */
  $qvars[] = 'jo_date'; /* last date checked for new posts */
  $qvars[] = 'jo_show_comments'; /* show comments for a post */
  $qvars[] = 'jo_options'; /* post id */
  $qvars[] = 'jo_comment'; /* post comment body */
  $qvars[] = 'jo_comment_name'; /* post comment name/author */
  $qvars[] = 'jo_comment_email'; /* post comment email */
  $qvars[] = 'jo_comment_url'; /* post comment url */
  $qvars[] = 'jo_comment_parent'; /* post comment parent - optional */
  return $qvars;
}
add_filter('query_vars', 'joemobi_api_joemobi1_output_queryvars' );

// Hook in the deprecated rewrites and use Joemobi instead.
function joemobi_api_joemobi1_rewrite( $q ) {
	// http://codex.wordpress.org/Class_Reference/WP_Query

	// Tricky! Use explicit mode joemobi_api calls!
	if (array_key_exists ('jo_comment', $q->query_vars)) {
			// 6) Post comment to a blog post
			// http://example.com/?jo_post=56&jo_comment=This Blog is cool&jo_comment_name=AuthorOfComment&jo_comment_email=email@email.com&jo_comment_url=http://www.url.com&jo_comment_parent=0
			//$q->set('joemobi','get_recent_posts');

			$q->set('joemobi','respond.submit_comment');
			$q->set('p',$q->query_vars['jo_post']);
			$q->set('post_id',$q->query_vars['jo_post']);
			$q->set('name',$q->query_vars['jo_comment_name']);
			$q->set('email',$q->query_vars['jo_comment_email']);
			$q->set('content',$q->query_vars['jo_comment']);

			$_REQUEST['post_id'] = $q->query_vars['jo_post'];
			$_REQUEST['name'] = $q->query_vars['jo_comment_name'];
			$_REQUEST['email'] = $q->query_vars['jo_comment_email'];
			$_REQUEST['content'] = $q->query_vars['jo_comment'];

			// TODO url and parent are not in Joemobi model.
			//$q->set('comment',$q->query_vars['jo_comment_url']);
			//$q->set('comment',$q->query_vars['jo_comment_parent']);

	}	else if (array_key_exists ('jo_show', $q->query_vars) || array_key_exists ('jo_page', $q->query_vars)) {

		// 1) Output list of blog posts paged
		// http://example.com/?jo_show=5&jo_page=1 (grabs 5 blogs, and makes it on one page)
		$q->set('joemobi','get_recent_posts');
		if (array_key_exists ('jo_show', $q->query_vars)) {
			$q->set('count',$q->query_vars['jo_show']);
		} else {
			$q->set('count',get_option('posts_per_page', 10));
		}
		$q->set('posts_per_page',$q->get('count'));

		// TODO, I don't think page is working.
		if (array_key_exists ('jo_page', $q->query_vars)) {
			$q->set('page',$q->query_vars['jo_page']);
		} else {
			$q->set('page',1);
		}
		$q->set('paged',$q->get('page'));

	} else if (array_key_exists ('jo_post', $q->query_vars)) {

		// 2) Output of entire blog post and all comments for that post
		// http://example.com/?jo_post=40 (Grabs post with id 40)
		//$_REQUEST['post_id'] = $q->query_vars['jo_post'];
		// @see query.php wp_query_var
		$q->set('p',$q->query_vars['jo_post']);
		$q->set('joemobi','get_post');

	} else if (array_key_exists ('jo_options', $q->query_vars)) {

		// 3) This will return the current version of the joemobi plugin
		// and the current version of wordpress on the users blog
		// http://example.com/?jo_options=0
		$q->set('joemobi','info');

	} else if (array_key_exists ('jo_date', $q->query_vars)) {

		// 4) Checks if there are new posts after a given date and time
		// (in GMT) and returns the amount of posts, if there are no posts
		// after a given date and time (in GMT) then return 0.
		// - Post times are converted to GMT within the plug-in
		// http://example.com/?jo_date=mm-dd-yyyy%20HH:mm:ss (24 hour time)
		//$q->set('joemobi','get_recent_posts');

		// This isn't really a WP thing so short circuit with an immediate result.
		// http://codex.wordpress.org/Function_Reference/get_posts
		// http://codex.wordpress.org/Function_Reference/query_posts

		// We can't query by date so grab the list ordered by date.
		$args = array (
			'numberposts' 	=> -1,
			'posts_per_page'=> -1,
			'orderby'		=> 'post_date',
			'order'			=> 'ASC',
			'post_status'	=> 'publish'
		);

		// set $more to 0 in order to only get the first part of the post, 1 otherwise
		global $more;
		$more = 1;
		// Query the posts
		query_posts( $args );

		$num = 0;
		$stop = strtotime($q->query_vars['jo_date']);

		// Start The Loop
		while ( have_posts() ) {
			the_post();

			$posted = strtotime(get_post_time('Y-m-d H:i:s', true));

			if ($stop < $posted) $num++;
		}

		$output[] = array (
			'number_of_posts'		=> $num
		);

		// TODO, use the Joemobi to output the result.
		header('Content-Type: application/json; charset=' . get_option('blog_charset'), true);
		echo json_encode($output);
		die('');

	} else if (array_key_exists ('jo_show_comments', $q->query_vars)) {

		// 5) Get all the comments for a post
		// http://example.com/?jo_show_comments=40
		$q->set('p',$q->query_vars['jo_show_comments']);
		$q->set('joemobi','get_post');

		// Comments are parsed out below.

	}

}
add_filter( 'parse_query', 'joemobi_api_joemobi1_rewrite' );


function joemobi_api_jomobie1_reencode_post($post) {

	$post->permalink = $post->url;
	unset($post->url);
	unset($post->type);
	unset($post->slug);
	unset($post->status);
	unset($post->title_plain);
	$post->author = $post->author->name;

	$text = strip_tags($post->content,'');
	// Remove short codes?
	$text = preg_replace("/\[.*\]/","",$text);
	$post->text_only = $text;

	// I'm not sure what this is for, copied from legacy.
	/*
	$content =  wp_kses($post->content,array(
		'strong' => array(),
		'br' => array(),
		'img' => array(
			'src' => array()
		),
		'a' => array(
			'href' => array()
		)
	));
  $content = str_replace("\r\n","<br/>",$content);
  $content = str_replace("<br/><br/><br/><br/>","<br/><br/>",$content);
  $content = str_replace("<br/><br/><br/>","<br/><br/>",$content);
	$post->content = $content;
	*/

	$post->date = date((get_query_var('date_format')?get_query_var('date_format'):'F j, Y H:i'),strtotime($post->date));

	$post->formatteddate = date('D, d M Y H:i:s \G\M\T', strtotime($post->date));

	$post->comments_enabled = ($post->comment_status == "open");
	unset($post->comment_status);
	$post->number_of_comments = $post->comment_count;
	unset($post->comment_count);

	if ($post->comments) {
		while(list($k,$v) = each($post->comments)) {
			$post->comments[$k] = joemobi_api_joemobi_reencode_comment($v, $post);
		}
	}

	return $post;
}

function joemobi_api_joemobi_reencode_comment($comment, $post) {

	return array(
		'comment_ID'           => $comment->id,
		'comment_post_ID'      => $post->id,
		'comment_author'       => $comment->name,
		//'comment_author_email' => null, // security issue!
		//'comment_author_IP'    => null,
		'comment_date'         => date(
			(get_query_var('date_format')?get_query_var('date_format'):'F j, Y H:i'),
			strtotime($post->date)
		),
		'comment_date_gmt'     => date(
			'D, d M Y H:i:s \G\M\T',
			strtotime($post->date)
		),
		'comment_content' 		=> $comment->content,
		//'comment_karma' 		  => null,
		'comment_approved' 		=> true,
		//'comment_agent'       => null,
		//'comment_type'        => null,
		'comment_parent'      => $comment->parent,
		'comment_author_url'  => $comment->url,
		//'user_id'             => null,
		//'same_as_post_author' => null,
		'gravatar'            => stripslashes(
		  'http://www.gravatar.com/avatar/'
		  . $comment->emailMD5
		  . '?s=50')
		);
}

/* If the query was using jo_* then munge the output into the deprecated format. */
function joemobi_api_joemobi1_reencode($response) {
	global $wp_query;
	$q = $wp_query;

	// Tricky! Use explicit mode joemobi_api calls!
	if (array_key_exists ('jo_comment', $q->query_vars)) {

			// 6) Post comment to a blog post
			// http://example.com/?jo_post=56&jo_comment=This Blog is cool&jo_comment_name=AuthorOfComment&jo_comment_email=email@email.com&jo_comment_url=http://www.url.com&jo_comment_parent=0

			return $response;

	} else if (array_key_exists ('jo_show', $q->query_vars)) {

		// 1) Output list of blog posts paged
		// http://example.com/?jo_show=5&jo_page=1 (grabs 5 blogs, and makes it on one page)
		$posts = $response['posts'];
		while (list($k,$v) = each($posts)) {
			$posts[$k] = joemobi_api_jomobie1_reencode_post($v);
		}
		return $posts;

	} else if (array_key_exists ('jo_post', $q->query_vars)) {

		// 2) Output of entire blog post and all comments for that post
		// http://example.com/?jo_post=40 (Grabs post with id 40)

		return array(joemobi_api_jomobie1_reencode_post($response['post']));


	} else if (array_key_exists ('jo_options', $q->query_vars)) {

		// 3) This will return the current version of the joemobi plugin
		// and the current version of wordpress on the users blog
		// http://example.com/?jo_options=0

		$r = new stdClass();
		$r->joemobiversion = '2.0';
		$r->wordpressversion = get_bloginfo('version');
		return array($r);

	} else if (array_key_exists ('jo_date', $q->query_vars)) {

		// 4) Checks if there are new posts after a given date and time
		// (in GMT) and returns the amount of posts, if there are no posts
		// after a given date and time (in GMT) then return 0.
		// - Post times are converted to GMT within the plug-in
		// http://example.com/?jo_date=mm-dd-yyyy%20HH:mm:ss (24 hour time)

		// NOTE: This output is already done in the script above.
		return $response;

	} else if (array_key_exists ('jo_show_comments', $q->query_vars)) {

		// 5) Get all the comments for a post
		// http://example.com/?jo_show_comments=40

		$r = new stdClass();
		$r->number_of_comments = count($response['post']->comments);
		$r->comments = (array)$response['post']->comments;
		$post = new stdClass();
		$post->id = $q->query_vars['jo_show_comments'];
		while(list($k,$v) = each($r->comments)) {
			$r->comments[$k] = joemobi_api_joemobi_reencode_comment($v, $post);
		}

		return array($r);

	}

	return $response;

}
add_filter('joemobi_api_encode', 'joemobi_api_joemobi1_reencode');

