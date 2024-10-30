<?php
/*

Copyright 2011  Jeffrey Sambells  (email : jsambells@speakfeel.ca)

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

class JOEMOBI_API_Comment {
  
  var $id;      // Integer
  var $name;    // String
  var $url;     // String
  var $date;    // String
  var $content; // String
  var $parent;  // Integer
  var $emailMD5;  // Integer
  var $author;  // Object (only if the user was registered & logged in)
  
  function JOEMOBI_API_Comment($wp_comment = null) {
    if ($wp_comment) {
      $this->import_wp_object($wp_comment);
    }
  }
  
  function import_wp_object($wp_comment) {
    global $joemobi_api;
    
    $date_format = $joemobi_api->query->date_format;
    $content = apply_filters('comment_text', $wp_comment->comment_content);
    
    
    $this->id = (int) $wp_comment->comment_ID;
    $this->name = $wp_comment->comment_author;
    $this->url = $wp_comment->comment_author_url;
    $this->date = date($date_format, strtotime($wp_comment->comment_date));
    $this->content = $content;
    $this->parent = (int) $wp_comment->comment_parent;
    //$this->raw = $wp_comment;
    $this->emailMD5 = md5(strtolower(trim($wp_comment->comment_author_email)));    
    
    if (!empty($wp_comment->user_id)) {
      $this->author = new JOEMOBI_API_Author($wp_comment->user_id);
    } else {
      unset($this->author);
    }
  }
  
  function handle_submission() {
    global $comment, $wpdb;
    add_action('comment_id_not_found', array(&$this, 'comment_id_not_found'));
    add_action('comment_closed', array(&$this, 'comment_closed'));
    add_action('comment_on_draft', array(&$this, 'comment_on_draft'));
    add_filter('comment_post_redirect', array(&$this, 'comment_post_redirect'));
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['comment_post_ID'] = $_REQUEST['post_id'];
    $_POST['author'] = $_REQUEST['name'];
    $_POST['email'] = $_REQUEST['email'];
    $_POST['url'] = empty($_REQUEST['url']) ? '' : $_REQUEST['url'];
    $_POST['comment'] = $_REQUEST['content'];
    $_POST['parent'] = $_REQUEST['parent'];
    include ABSPATH . 'wp-comments-post.php';
  }
  
  function comment_id_not_found() {
    global $joemobi_api;
    $joemobi_api->error("Post ID '{$_REQUEST['post_id']}' not found.");
  }
  
  function comment_closed() {
    global $joemobi_api;
    $joemobi_api->error("Post is closed for comments.");
  }
  
  function comment_on_draft() {
    global $joemobi_api;
    $joemobi_api->error("You cannot comment on unpublished posts.");
  }
  
	function comment_post_redirect() {
    global $dsq_api, $comment, $joemobi_api;
    
    if ($dsq_api != null && function_exists('dsq_is_installed')) {
    	//header("content-type:text/plain");
    	//error_reporting(E_ALL);
    	//ini_set('display_errors','On');
    	
    	// Post the comment to disqus instead.
			//var_dump($comment);
			
			$post = get_post($comment->comment_post_ID);

			//var_dump($post);

    	$thread_id = get_post_meta($post->ID, 'dsq_thread_id', true);

			if ($thread_id > 0) {
				// TODO the post has to be seen on the public side to generate the id from disqus.
				// How do we force this for the edge case where it hasn't been seen yet on the web?
			
				//var_dump($thread_id);

    	
	    	//http://disqus.com/api/docs/posts/create/
	    	//var_dump($dsq_api);
	    	
	    	$response = $dsq_api->api->call(
	    		'create_post',
	    		array(
	    			'thread_id'    => $thread_id,
	    			'message'      => $comment->comment_content,
	    			'author_name'  => $comment->comment_author,
	    			'author_email' => $comment->comment_author_email,
	    			'author_url'   => $comment->comment_author_url,
	    			'ip_address'   => $comment->comment_author_IP
	    		),
	    		true
	    	);
	    	
	    	//var_dump( 'end' );
	    	//var_dump( $dsq_api->api->get_last_error() );
	    	if ( @$response->status == "approved" ) {
	    		// Remove the comment from the local wordpress db. It wil be re-added
	    		// when disqus syncs in the new comments from the API. 
	    		wp_delete_comment( $comment->comment_ID );
	    	}
	    	
	    		    	
    	}
    	
    	/*
    	// Handle as export function instead.
    	$exportScript = WP_PLUGIN_DIR . '/disqus-comment-system/export.php';
    	if( dsq_is_installed() && DISQUS_CAN_EXPORT && file_exists($exportScript)) {
    		require_once($exportScript);
        $wxr = dsq_export_wp($post,array($comment));
        $response = $dsq_api->import_wordpress_comments($wxr, time(), $eof);
        if (!($response['group_id'] > 0)) {
            $result = 'fail';
            $response = $dsq_api->get_last_error();
        } else {
            $result = 'success';
        }
    	}
    	*/
    	
    }
    
    
    $status = ($comment->comment_approved) ? 'ok' : 'pending';
    $new_comment = new JOEMOBI_API_Comment($comment);
    $joemobi_api->response->respond($new_comment, $status);
  }

  
}

?>
