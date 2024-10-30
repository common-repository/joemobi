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

class JOEMOBI_API_Category {
  
  var $id;          // Integer
  var $slug;        // String
  var $title;       // String
  var $description; // String
  var $parent;      // Integer
  var $post_count;  // Integer
  
  function JOEMOBI_API_Category($wp_category = null) {
    if ($wp_category) {
      $this->import_wp_object($wp_category);
    }
  }
  
  function import_wp_object($wp_category) {
    $this->id = (int) $wp_category->term_id;
    $this->slug = $wp_category->slug;
    $this->title = $wp_category->name;
    $this->description = $wp_category->description;
    $this->parent = (int) $wp_category->parent;
    $this->post_count = (int) $wp_category->count;
  }
  
}

?>
