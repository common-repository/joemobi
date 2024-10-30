<?php
/*
Controller name: Respond
Controller description: Comment/trackback submission methods

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

class JOEMOBI_API_Respond_Controller {
  
  function submit_comment() {
    global $joemobi_api;
    nocache_headers();
    if (empty($_REQUEST['post_id'])) {
      $joemobi_api->error("No post specified. Include 'post_id' var in your request.");
    } else if (empty($_REQUEST['name']) ||
               empty($_REQUEST['email']) ||
               empty($_REQUEST['content'])) {
      $joemobi_api->error("Please include all required arguments (name, email, content).");
    } else if (!is_email($_REQUEST['email'])) {
      $joemobi_api->error("Please enter a valid email address.");
    }
    $pending = new JOEMOBI_API_Comment();
    return $pending->handle_submission();
  }
  
}

?>
