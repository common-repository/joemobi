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

class JOEMOBI_API_Response {
  
  function setup() {
    global $joemobi_api;
    $this->include_values = array();
    $this->exclude_values = array();
    if ($joemobi_api->query->include) {
      $this->include_values = explode(',', $joemobi_api->query->include);
    }
    // Props to ikesyo for submitting a fix!
    if ($joemobi_api->query->exclude) {
      $this->exclude_values = explode(',', $joemobi_api->query->exclude);
      $this->include_values = array_diff($this->include_values, $this->exclude_values);
    }
    
    // Compatibility with Disqus plugin
    remove_action('loop_end', 'dsq_loop_end');
  }
  
  function get_json($data, $status = 'ok') {
    // Include a status value with the response
    if (is_array($data)) {
      $data = array_merge(array('status' => $status), $data);
    } else if (is_object($data)) {
      $data = get_object_vars($data);
      $data = array_merge(array('status' => $status), $data);
    }
    
    $data = apply_filters('joemobi_api_encode', $data);
    
    if (function_exists('json_encode')) {
    	
      // Use the built-in json_encode function if it's available
      $encoded = json_encode($data);
      // http://www.php.net/manual/en/function.json-encode.php#100679
      // https://bugs.php.net/bug.php?id=49323
      
      // This line breaks the encoding
      // $encoded = str_replace('<br \/>', '<br \>', $encoded);
      
      // Test
      
      $filtered = $encoded;
      
      // Fix for parsing errors.
      // Removes optional escaped /
			while (stripos($filtered, '\\/') !== false) {
				$filtered = str_replace('\\/', '/', $filtered);
			}
      
      // After the filter test to ensure we can still decode the value.
      $decoded = json_decode($filtered);
      if ($decoded && $decoded != null) {
      	// Decode was ok. return filtered values.  
 				return $filtered;
 			}
      
      // Filter failed so return as it was.  
      return $encoded;
      
    } else {
      // Use PEAR's Services_JSON encoder otherwise
      if (!class_exists('Services_JSON')) {
        $dir = joemobi_api_dir();
        require_once "$dir/library/JSON.php";
      }
      $json = new Services_JSON();
      return $json->encode($data);
    }
  }
  
  function is_value_included($key) {
    // Props to ikesyo for submitting a fix!
    if (empty($this->include_values) && empty($this->exclude_values)) {
      return true;
    } else {
      if (empty($this->exclude_values)) {
        return in_array($key, $this->include_values);
      } else {
        return !in_array($key, $this->exclude_values);
      }
    }
  }
  
  function respond($result, $status = 'ok') {
  
	  $buffers = ob_list_handlers();
	  if (in_array('transposh_plugin::process_page',$buffers)) {
	  	// http://transposh.org/ plugin creates a buffer and modifies 
	  	// the output in a BAD way. We don't want is to alter the content.  
	  	@ob_end_clean(); // hide error incase the plugin changes.
	  }
  
    global $joemobi_api;
    $json = $this->get_json($result, $status);
    $status_redirect = "redirect_$status";
    if ($joemobi_api->query->dev || !empty($_REQUEST['dev'])) {
      // Output the result in a human-redable format
      if (!headers_sent()) {
        header('HTTP/1.1 200 OK');
        header('Content-Type: text/plain; charset: UTF-8', true);
      } else {
        echo '<pre>';
      }
      echo $this->prettify($json);
    } else if (!empty($_REQUEST[$status_redirect])) {
      wp_redirect($_REQUEST[$status_redirect]);
    } else if ($joemobi_api->query->redirect) {
      $url = $this->add_status_query_var($joemobi_api->query->redirect, $status);
      wp_redirect($url);
    } else if ($joemobi_api->query->callback) {
      // Run a JSONP-style callback with the result
      $this->callback($joemobi_api->query->callback, $json);
    } else {
      // Output the result
      $this->output($json);
    }
    exit;
  }
  
  function output($result) {
    $charset = get_option('blog_charset');
    if (!headers_sent()) {
      header('HTTP/1.1 200 OK', true);
      header("Content-Type: application/json; charset=$charset", true);
    }
    echo $result;
  }
  
  function callback($callback, $result) {
    $charset = get_option('blog_charset');
    if (!headers_sent()) {
      header('HTTP/1.1 200 OK', true);
      header("Content-Type: application/javascript; charset=$charset", true);
    }
    echo "$callback($result)";
  }
  
  function add_status_query_var($url, $status) {
    if (strpos($url, '#')) {
      // Remove the anchor hash for now
      $pos = strpos($url, '#');
      $anchor = substr($url, $pos);
      $url = substr($url, 0, $pos);
    }
    if (strpos($url, '?')) {
      $url .= "&status=$status";
    } else {
      $url .= "?status=$status";
    }
    if (!empty($anchor)) {
      // Add the anchor hash back in
      $url .= $anchor;
    }
    return $url;
  }
  
  function prettify($ugly) {
    $pretty = "";
    $indent = "";
    $last = '';
    $pos = 0;
    $level = 0;
    $string = false;
    while ($pos < strlen($ugly)) {
      $char = substr($ugly, $pos++, 1);
      if (!$string) {
        if ($char == '{' || $char == '[') {
          if ($char == '[' && substr($ugly, $pos, 1) == ']') {
            $pretty .= "[]";
            $pos++;
          } else if ($char == '{' && substr($ugly, $pos, 1) == '}') {
            $pretty .= "{}";
            $pos++;
          } else {
            $pretty .= "$char\n";
            $indent = str_repeat('  ', ++$level);
            $pretty .= "$indent";
          }
        } else if ($char == '}' || $char == ']') {
          $indent = str_repeat('  ', --$level);
          if ($last != '}' && $last != ']') {
            $pretty .= "\n$indent";
          } else if (substr($pretty, -2, 2) == '  ') {
            $pretty = substr($pretty, 0, -2);
          }
          $pretty .= $char;
          if (substr($ugly, $pos, 1) == ',') {
            $pretty .= ",";
            $last = ',';
            $pos++;
          }
          $pretty .= "\n$indent";
        } else if ($char == ':') {
          $pretty .= ": ";
        } else if ($char == ',') {
          $pretty .= ",\n$indent";
        } else if ($char == '"') {
          $pretty .= '"';
          $string = true;
        } else {
          $pretty .= $char;
        }
      } else {
        if ($last != '\\' && $char == '"') {
          $string = false;
        }
        $pretty .= $char;
      }
      $last = $char;
    }
    return $pretty;
  }
  
}

?>
