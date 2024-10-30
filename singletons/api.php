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

class JOEMOBI_API {
  
  function __construct() {
    $this->query = new JOEMOBI_API_Query();
    $this->introspector = new JOEMOBI_API_Introspector();
    $this->response = new JOEMOBI_API_Response();
    add_action('template_redirect', array(&$this, 'template_redirect'));
    add_action('admin_menu', array(&$this, 'admin_menu'));
    add_action('update_option_joemobi_api_base', array(&$this, 'flush_rewrite_rules'));
    add_action('pre_update_option_joemobi_api_controllers', array(&$this, 'update_controllers'));
  }
  
  function template_redirect() {
  	//ob_start();
    // Check to see if there's an appropriate API controller + method    
    $controller = strtolower($this->query->get_controller());
    $available_controllers = $this->get_controllers();
    $enabled_controllers = explode(',', get_option('joemobi_api_controllers', 'core'));
    $enabled_controllers[] = 'respond';
    $active_controllers = array_intersect($available_controllers, $enabled_controllers);
    
    if ($controller) {
      
      if (!in_array($controller, $active_controllers)) {
        $this->error("Unknown controller '$controller'.");
      }
      
      $controller_path = $this->controller_path($controller);
      if (file_exists($controller_path)) {
        require_once $controller_path;
      }
      $controller_class = $this->controller_class($controller);
      
      if (!class_exists($controller_class)) {
        $this->error("Unknown controller '$controller_class'.");
      }
      
      $this->controller = new $controller_class();
      $method = $this->query->get_method($controller);
      
      if ($method) {
      
        ob_start();
        
        $this->response->setup();
        
        // Run action hooks for method
        do_action("joemobi_api-{$controller}-$method");
        
        // Error out if nothing is found
        if ($method == '404') {
          $this->error('Not found');
        }

        
        // Run the method
        $result = $this->controller->$method();
        
        ob_end_clean();
        
        // Handle the result
        $this->response->respond($result);
        
        // Done!
        exit;
      }
    }
    
    //ob_end_clean()
  }
  
  function admin_menu() {
    add_options_page('JoeMobi Admin', 'JoeMobi', 'manage_options', 'joemobi-api', array(&$this, 'admin_options'));
  }
  
  function admin_options() {

    if (!current_user_can('manage_options'))  {
      wp_die( __('You do not have sufficient permissions to access this page.') );
    }

    $available_post_types = get_post_types(null,'objects');
    
    // fix for wordpress 2.9
    if (!array_key_exists('post',$available_post_types)) {
    	$old_types = $available_post_types;
    	$available_post_types = array();
    	foreach($old_types as $post) {
    		$obj = new StdClass();
    		$obj->labels = new StdClass();
    		$obj->labels->name = ucfirst($post->name);
    		$available_post_types[$post->name] = $obj;
    	}
    }
    
    
    $active_post_types = explode(',', get_option('joemobi_api_post_types', 'post'));
    if (count($active_post_types) == 1 && empty($active_post_types[0])) {
      $active_post_types = array();
    }
    
    foreach( $active_post_types as $key=>$value ) {
    	if (!array_key_exists($value, $available_post_types)) {
    		unset($active_post_types[$key]);
    	}
    }
            
    if (!empty($_REQUEST['_wpnonce']) && wp_verify_nonce($_REQUEST['_wpnonce'], "update-options")) {
      if ((!empty($_REQUEST['action']) || !empty($_REQUEST['action2'])) &&
          (!empty($_REQUEST['pt']) || !empty($_REQUEST['post_types']))) {
        if (!empty($_REQUEST['action'])) {
          $action = $_REQUEST['action'];
        } else {
          $action = $_REQUEST['action2'];
        }
        
        if (!empty($_REQUEST['post_types'])) {
          $post_types = $_REQUEST['post_types'];
        } else {
          $post_types = array($_REQUEST['pt']);
        }

        foreach ($post_types as $post_type) {
          if (in_array($post_type, array_keys($available_post_types))) {
            if ($action == 'activate' && !in_array($post_type, $active_post_types)) {
              $active_post_types[] = $post_type;
            } else if ($action == 'deactivate') {
              $index = array_search($post_type, $active_post_types);
              if ($index !== false) {
                unset($active_post_types[$index]);
              }
            }
          }
        }
        $this->save_option('joemobi_api_post_types', implode(',', $active_post_types));
      }
      if (isset($_REQUEST['joemobi_api_base'])) {
        $this->save_option('joemobi_api_base', $_REQUEST['joemobi_api_base']);
      }
    }
    
    ?>
<div class="wrap">
  <div id="icon-options-general" class="icon32"><br /></div>
  <h2>JoeMobi Settings</h2>
  <form action="options-general.php?page=joemobi-api" method="post">
    <?php wp_nonce_field('update-options'); ?>
    <h3>Post Types</h3>
    <p>Select the post types you would like to include in your JoeMobi app. If no types are selected, "Post" will be used by default. Note, in most cases you won't need to change this.</p>
    <?php $this->print_post_type_actions(); ?>
    <table id="all-post-types" class="widefat">
      <thead>
        <tr>
          <th class="manage-column check-column" scope="col"><input type="checkbox" /></th>
          <th class="manage-column" scope="col">Post Type</th>
          <th class="manage-column" scope="col">Slug</th>
        </tr>
      </thead>
      <tfoot>
        <tr>
          <th class="manage-column check-column" scope="col"><input type="checkbox" /></th>
          <th class="manage-column" scope="col">Post Type</th>
          <th class="manage-column" scope="col">Slug</th>
        </tr>
      </tfoot>
      <tbody class="plugins">
        <?php
        	
          //var_dump($available_post_types);

        foreach ($available_post_types as $post_type=>$info) {
          
          
          $error = false;
          $active = in_array($post_type, $active_post_types);
          
          if (is_string($info)) {
            $active = false;
            $error = true;
            $info = new StdClass();
            $info->name = $post_type;
          }
          
          ?>
          <tr class="<?php echo ($active ? 'active' : 'inactive'); ?>">
            <th class="check-column" scope="row">
              <input type="checkbox" name="post_types[]" value="<?php echo $post_type; ?>" />
            </th>
            <td class="plugin-title">
              <strong><?php echo $info->labels->name; ?></strong>
              <div class="row-actions-visible">
                <?php
                
                if ($active) {
                  echo '<a href="' . wp_nonce_url('options-general.php?page=joemobi-api&amp;action=deactivate&amp;pt=' . $post_type, 'update-options') . '" title="' . __('Exclude this post type') . '" class="edit">' . __('Exclude') . '</a>';
                } else if (!$error) {
                  echo '<a href="' . wp_nonce_url('options-general.php?page=joemobi-api&amp;action=activate&amp;pt=' . $post_type, 'update-options') . '" title="' . __('Include this post type') . '" class="edit">' . __('Include') . '</a>';
                  
                  if ($post_type == 'post') {
                  	echo '<strong>CAUTION! No "post" types are included in the output.</srong>';
                  }
                }
                  
                ?>
                </div>
            </td>
            <td class="desc">
              <p><?php echo $post_type; ?></p>
            </td>
          </tr>
        <?php } ?>
      </tbody>
    </table>
      <!--
        <p class="submit">
      <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
    </p>
    -->
  </form>
</div>
<?php
  }
  
  function print_post_type_actions($name = 'action') {
    ?>
    <div class="tablenav">
      <div class="alignleft actions">
        <select name="<?php echo $name; ?>">
          <option selected="selected" value="-1">Bulk Actions</option>
          <option value="activate">Include</option>
          <option value="deactivate">Exclude</option>
        </select>
        <input type="submit" class="button-secondary action" id="doaction" name="doaction" value="Apply">
      </div>
      <div class="clear"></div>
    </div>
    <div class="clear"></div>
    <?php
  }
  
  function get_post_types() {
  	$list = explode(',',get_option('joemobi_api_post_types', 'post'));
  	if (count($list) == 0 || (count($list) == 1 && !$list[0]) ) {
  		$list = array(
  			'post'
  		);
  	
  	}
  	return $list;
  }
  
  
  function get_method_url($controller, $method, $options = '') {
    $url = get_bloginfo('url');
    $base = get_option('joemobi_api_base', 'api');
    $permalink_structure = get_option('permalink_structure', '');
    if (!empty($options) && is_array($options)) {
      $args = array();
      foreach ($options as $key => $value) {
        $args[] = urlencode($key) . '=' . urlencode($value);
      }
      $args = implode('&', $args);
    } else {
      $args = $options;
    }
    if ($controller != 'core') {
      $method = "$controller/$method";
    }
    if (!empty($base) && !empty($permalink_structure)) {
      if (!empty($args)) {
        $args = "?$args";
      }
      return "$url/$base/$method/$args";
    } else {
      return "$url?joemobi=$method&$args";
    }
  }
  
  function save_option($id, $value) {
    $option_exists = (get_option($id, null) !== null);
    if ($option_exists) {
      update_option($id, $value);
    } else {
      add_option($id, $value);
    }
  }
  
  function get_controllers() {
    $controllers = array();
    $dir = joemobi_api_dir();
    $dh = opendir("$dir/controllers");
    while ($file = readdir($dh)) {
      if (preg_match('/(.+)\.php$/', $file, $matches)) {
        $controllers[] = $matches[1];
      }
    }
    $controllers = apply_filters('joemobi_api_controllers', $controllers);
    $controllers[] = 'respond';
    return array_map('strtolower', $controllers);
  }
  
  function controller_is_active($controller) {
    if (defined('JOEMOBI_API_CONTROLLERS')) {
      $default = JOEMOBI_API_CONTROLLERS;
    } else {
      $default = 'core';
    }
    $active_controllers = explode(',', get_option('joemobi_api_controllers', $default));
    $active_controllers[] = 'respond';
    return (in_array($controller, $active_controllers));
  }
  
  function update_controllers($controllers) {
    if (is_array($controllers)) {
      return implode(',', $controllers);
    } else {
      return $controllers;
    }
  }
  
  function controller_info($controller) {
    $path = $this->controller_path($controller);
    $class = $this->controller_class($controller);
    $response = array(
      'name' => $controller,
      'description' => '(No description available)',
      'methods' => array()
    );
    if (file_exists($path)) {
      $source = file_get_contents($path);
      if (preg_match('/^\s*Controller name:(.+)$/im', $source, $matches)) {
        $response['name'] = trim($matches[1]);
      }
      if (preg_match('/^\s*Controller description:(.+)$/im', $source, $matches)) {
        $response['description'] = trim($matches[1]);
      }
      if (preg_match('/^\s*Controller URI:(.+)$/im', $source, $matches)) {
        $response['docs'] = trim($matches[1]);
      }
      if (!class_exists($class)) {
        require_once($path);
      }
      $response['methods'] = get_class_methods($class);
      return $response;
    } else if (is_admin()) {
      return "Cannot find controller class '$class' (filtered path: $path).";
    } else {
      $this->error("Unknown controller '$controller'.");
    }
    return $response;
  }
  
  function controller_class($controller) {
    return "joemobi_api_{$controller}_controller";
  }
  
  function controller_path($controller) {
    $dir = joemobi_api_dir();
    $controller_class = $this->controller_class($controller);
    return apply_filters("{$controller_class}_path", "$dir/controllers/$controller.php");
  }
  
  function get_nonce_id($controller, $method) {
    $controller = strtolower($controller);
    $method = strtolower($method);
    return "joemobi_api-$controller-$method";
  }
  
  function flush_rewrite_rules() {
    global $wp_rewrite;
    $wp_rewrite->flush_rules();
  }
  
  function error($message = 'Unknown error', $status = 'error') {
    $this->response->respond(array(
      'error' => $message
    ), $status);
  }
  
  function include_value($key) {
    return $this->response->is_value_included($key);
  }
  
}

?>
