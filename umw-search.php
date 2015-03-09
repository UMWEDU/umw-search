<?php
/**
 * Implements the UMW search engine
 */
if ( ! class_exists( 'UMW_Search_Engine' ) ) {
  require_once( plugin_dir_path( __FILE__ ) . '/classes/class-umw-search-engine.php' );
  global $umw_search_engine_obj;
  $umw_search_engine_obj = new UMW_Search_Engine;
}
