<?php
/**
 * Plugin Name: UMW Custom Search
 * Plugin URI: https://github.com/UMWEDU/umw-search
 * Description: Implements the custom search functionality for the UMW website
 * Version: 0.2
 * Author: Curtiss Grymala
 * Author URI: http://www.umw.edu/
 * License: GPL2
 */
if ( ! defined( 'ABSPATH' ) ) {
  die( 'You should not access this file directly.' );
}

if ( ! class_exists( 'UMW_Search_Engine' ) ) {
  require_once( plugin_dir_path( __FILE__ ) . '/classes/class-umw-search-engine.php' );
  function init_umw_search_engine() {
	  global $umw_search_engine_obj;
	  $umw_search_engine_obj = new UMW_Search_Engine;
  }
  add_action( 'after_setup_theme', 'init_umw_search_engine' );
}

add_action( 'after_setup_theme', 'unhook_google_cse_plugin' );
function unhook_google_cse_plugin() {
  if ( has_action( 'plugins_loaded', 'init_google_custom_search' ) ) {
    remove_action( 'plugins_loaded', 'init_google_custom_search' );
  }
}
