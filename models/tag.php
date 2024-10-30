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

class JOEMOBI_API_Tag {
  
  var $id;          // Integer
  var $slug;        // String
  var $title;       // String
  var $description; // String
  
  function JOEMOBI_API_Tag($wp_tag = null) {
    if ($wp_tag) {
      $this->import_wp_object($wp_tag);
    }
  }
  
  function import_wp_object($wp_tag) {
    $this->id = (int) $wp_tag->term_id;
    $this->slug = $wp_tag->slug;
    $this->title = $wp_tag->name;
    $this->description = $wp_tag->description;
    $this->post_count = (int) $wp_tag->count;
  }
  
}

?>
