<?php
/*
Controller name: Core
Controller description: Basic introspection methods

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
class JOEMOBI_API_Core_Controller {
  
  
  public function get_menus() {
    global $joemobi_api;
  
  	$menus = wp_get_nav_menus();
  	//var_dump($menus); die();
  	
  	if (count($menus) == 0) return array();
  	
  	$menu = $menus[0]->term_id;
  	/*
  	$args = array(
  	        'order'                  => 'ASC',
  	        'orderby'                => 'menu_order',
  	        'post_type'              => 'nav_menu_item',
  	        'post_status'            => 'publish',
  	        'output'                 => ARRAY_A,
  	        'output_key'             => 'menu_order',
  	        'nopaging'               => true,
  	        'update_post_term_cache' => false );
  	*/
	  return wp_get_nav_menu_items($menu, $args);  
    
  }
  
  public function info() {
    global $joemobi_api;
    $php = '';
    if (!empty($joemobi_api->query->controller)) {
      return $joemobi_api->controller_info($joemobi_api->query->controller);
    } else {
      $active_controllers = explode(',', get_option('joemobi_api_controllers', 'core'));
      $controllers = array_intersect($joemobi_api->get_controllers(), $active_controllers);
      return array(
        'joemobi_api_version' => joemobi_get_version(),
        'controllers' => array_values($controllers),
        'post_types' => $joemobi_api->get_post_types()
      );
    }
  }
  
  public function get_recent_posts() {
    global $joemobi_api;
    $posts = $joemobi_api->introspector->get_posts(array('post_type' => $joemobi_api->get_post_types()));
    return $this->posts_result($posts);
  }
  
  public function get_post() {
    global $joemobi_api, $post;
    extract($joemobi_api->query->get(array('id', 'slug', 'post_id', 'post_slug')));
    if ($id || $post_id) {
      if (!$id) {
        $id = $post_id;
      }
      $posts = $joemobi_api->introspector->get_posts(array(
        'p' => $id
      ), true);
    } else if ($slug || $post_slug) {
      if (!$slug) {
        $slug = $post_slug;
      }
      $posts = $joemobi_api->introspector->get_posts(array(
        'name' => $slug
      ), true);
    } else {
      $joemobi_api->error("Include 'id' or 'slug' var in your request.");
    }
    if (count($posts) == 1) {
      $post = $posts[0];
      $previous = get_adjacent_post(false, '', true);
      $next = get_adjacent_post(false, '', false);
      $post = new JOEMOBI_API_Post($post);
      $response = array(
        'post' => $post
      );
      if ($previous) {
        $response['previous_url'] = get_permalink($previous->ID);
      }
      if ($next) {
        $response['next_url'] = get_permalink($next->ID);
      }
      return $response;
    } else {
      $joemobi_api->error("Not found.");
    }
  }

  public function get_page() {
    global $joemobi_api;
    extract($joemobi_api->query->get(array('id', 'slug', 'page_id', 'page_slug', 'children')));
    if ($id || $page_id) {
      if (!$id) {
        $id = $page_id;
      }
      $posts = $joemobi_api->introspector->get_posts(array(
        'page_id' => $id
      ));
    } else if ($slug || $page_slug) {
      if (!$slug) {
        $slug = $page_slug;
      }
      $posts = $joemobi_api->introspector->get_posts(array(
        'pagename' => $slug
      ));
    } else {
      $joemobi_api->error("Include 'id' or 'slug' var in your request.");
    }
    
    // Workaround for https://core.trac.wordpress.org/ticket/12647
    if (empty($posts)) {
      $url = $_SERVER['REQUEST_URI'];
      $parsed_url = parse_url($url);
      $path = $parsed_url['path'];
      if (preg_match('#^http://[^/]+(/.+)$#', get_bloginfo('url'), $matches)) {
        $blog_root = $matches[1];
        $path = preg_replace("#^$blog_root#", '', $path);
      }
      if (substr($path, 0, 1) == '/') {
        $path = substr($path, 1);
      }
      $posts = $joemobi_api->introspector->get_posts(array('pagename' => $path));
    }
    
    if (count($posts) == 1) {
      if (!empty($children)) {
        $joemobi_api->introspector->attach_child_posts($posts[0]);
      }
      return array(
        'page' => $posts[0]
      );
    } else {
      $joemobi_api->error("Not found.");
    }
  }
  
  public function get_date_posts() {
    global $joemobi_api;
    if ($joemobi_api->query->date) {
      $date = preg_replace('/\D/', '', $joemobi_api->query->date);
      if (!preg_match('/^\d{4}(\d{2})?(\d{2})?$/', $date)) {
        $joemobi_api->error("Specify a date var in one of 'YYYY' or 'YYYY-MM' or 'YYYY-MM-DD' formats.");
      }
      $request = array('year' => substr($date, 0, 4));
      if (strlen($date) > 4) {
        $request['monthnum'] = (int) substr($date, 4, 2);
      }
      if (strlen($date) > 6) {
        $request['day'] = (int) substr($date, 6, 2);
      }
      $request['post_type'] = $joemobi_api->get_post_types();
      $posts = $joemobi_api->introspector->get_posts($request);
    } else {
      $joemobi_api->error("Include 'date' var in your request.");
    }
    return $this->posts_result($posts);
  }
  
  public function get_category_posts() {
    global $joemobi_api;
    $category = $joemobi_api->introspector->get_current_category();
    if (!$category) {
      $joemobi_api->error("Not found.");
    }
    $posts = $joemobi_api->introspector->get_posts(array(
    	'post_type' => $joemobi_api->get_post_types(),
      'cat' => $category->id,
    ));
    return $this->posts_object_result($posts, $category);
  }
  
  public function get_tag_posts() {
    global $joemobi_api;
    $tag = $joemobi_api->introspector->get_current_tag();
    if (!$tag) {
      $joemobi_api->error("Not found.");
    }
    $posts = $joemobi_api->introspector->get_posts(array(
    	'post_type' => $joemobi_api->get_post_types(),
      'tag' => $tag->slug
    ));
    return $this->posts_object_result($posts, $tag);
  }
  
  public function get_author_posts() {
    global $joemobi_api;
    $author = $joemobi_api->introspector->get_current_author();
    if (!$author) {
      $joemobi_api->error("Not found.");
    }
    $posts = $joemobi_api->introspector->get_posts(array(
    	'post_type' => $joemobi_api->get_post_types(),
      'author' => $author->id
    ));
    return $this->posts_object_result($posts, $author);
  }
  
  public function get_search_results() {
    global $joemobi_api;
    if ($joemobi_api->query->search) {
      $posts = $joemobi_api->introspector->get_posts(array(
	    	'post_type' => $joemobi_api->get_post_types(),
        's' => $joemobi_api->query->search
      ));
    } else {
      $joemobi_api->error("Include 'search' var in your request.");
    }
    return $this->posts_result($posts);
  }
  
  public function get_date_index() {
    global $joemobi_api;
    $permalinks = $joemobi_api->introspector->get_date_archive_permalinks();
    $tree = $joemobi_api->introspector->get_date_archive_tree($permalinks);
    return array(
      'permalinks' => $permalinks,
      'tree' => $tree
    );
  }
  
  public function get_category_index() {
    global $joemobi_api;
    $categories = $joemobi_api->introspector->get_categories();
    return array(
      'count' => count($categories),
      'categories' => $categories
    );
  }
  
  public function get_tag_index() {
    global $joemobi_api;
    $tags = $joemobi_api->introspector->get_tags();
    return array(
      'count' => count($tags),
      'tags' => $tags
    );
  }
  
  public function get_author_index() {
    global $joemobi_api;
    $authors = $joemobi_api->introspector->get_authors();
    return array(
      'count' => count($authors),
      'authors' => array_values($authors)
    );
  }
  
  public function get_page_index() {
    global $joemobi_api;
    $pages = array();
    // Thanks to blinder for the fix!
    $numberposts = empty($joemobi_api->query->count) ? -1 : $joemobi_api->query->count;
    $wp_posts = get_posts(array(
      'post_type' => 'page',
      'post_parent' => 0,
      'order' => 'ASC',
      'orderby' => 'menu_order',
      'numberposts' => $numberposts
    ));
    foreach ($wp_posts as $wp_post) {
      $pages[] = new JOEMOBI_API_Post($wp_post);
    }
    foreach ($pages as $page) {
      $joemobi_api->introspector->attach_child_posts($page);
    }
    return array(
      'pages' => $pages
    );
  }
  
  public function get_nonce() {
    global $joemobi_api;
    extract($joemobi_api->query->get(array('controller', 'method')));
    if ($controller && $method) {
      $controller = strtolower($controller);
      if (!in_array($controller, $joemobi_api->get_controllers())) {
        $joemobi_api->error("Unknown controller '$controller'.");
      }
      require_once $joemobi_api->controller_path($controller);
      if (!method_exists($joemobi_api->controller_class($controller), $method)) {
        $joemobi_api->error("Unknown method '$method'.");
      }
      $nonce_id = $joemobi_api->get_nonce_id($controller, $method);
      return array(
        'controller' => $controller,
        'method' => $method,
        'nonce' => wp_create_nonce($nonce_id)
      );
    } else {
      $joemobi_api->error("Include 'controller' and 'method' vars in your request.");
    }
  }
  
  protected function get_object_posts($object, $id_var, $slug_var) {
    global $joemobi_api;
    $object_id = "{$type}_id";
    $object_slug = "{$type}_slug";
    extract($joemobi_api->query->get(array('id', 'slug', $object_id, $object_slug)));
    if ($id || $$object_id) {
      if (!$id) {
        $id = $$object_id;
      }
      $posts = $joemobi_api->introspector->get_posts(array(
  	  	'post_type' => $joemobi_api->get_post_types(),
        $id_var => $id
      ));
    } else if ($slug || $$object_slug) {
      if (!$slug) {
        $slug = $$object_slug;
      }
      $posts = $joemobi_api->introspector->get_posts(array(
	    	'post_type' => $joemobi_api->get_post_types(),
        $slug_var => $slug
      ));
    } else {
      $joemobi_api->error("No $type specified. Include 'id' or 'slug' var in your request.");
    }
    return $posts;
  }
  
  protected function posts_result($posts) {
    global $wp_query;
    return array(
      'count' => count($posts),
      'count_total' => (int) $wp_query->found_posts,
      'pages' => $wp_query->max_num_pages,
      'posts' => $posts
    );
  }
  
  protected function posts_object_result($posts, $object) {
    global $wp_query;
    // Convert something like "JOEMOBI_API_Category" into "category"
    $object_key = strtolower(substr(get_class($object), 9));
    return array(
      'count' => count($posts),
      'pages' => (int) $wp_query->max_num_pages,
      $object_key => $object,
      'posts' => $posts
    );
  }
  
}

?>
