<?php
/*
Controller name: Posts
Controller description: Data manipulation methods for posts

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

class JOEMOBI_API_Posts_Controller {

  public function create_post() {
    global $joemobi_api;
    if (!current_user_can('edit_posts')) {
      $joemobi_api->error("You need to login with a user capable of creating posts.");
    }
    if (!$joemobi_api->query->nonce) {
      $joemobi_api->error("You must include a 'nonce' value to create posts. Use the `get_nonce` Core API method.");
    }
    $nonce_id = $joemobi_api->get_nonce_id('posts', 'create_post');
    if (!wp_verify_nonce($joemobi_api->query->nonce, $nonce_id)) {
      $joemobi_api->error("Your 'nonce' value was incorrect. Use the 'get_nonce' API method.");
    }
    nocache_headers();
    $post = new JOEMOBI_API_Post();
    $id = $post->create($_REQUEST);
    if (empty($id)) {
      $joemobi_api->error("Could not create post.");
    }
    return array(
      'post' => $post
    );
  }
  
}

?>
