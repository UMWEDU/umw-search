<?php
/**
 * Implements the UMW_Search_Engine class
 */
if ( ! defined( 'ABSPATH' ) ) {
  die( 'You should not access this file directly.' );
}

if ( ! class_exists( 'UMW_Search_Engine' ) ) {
  class UMW_Search_Engine {
    public $v = 0.1;
    public $use_buttons = false;
    private $cse_id = null;
    public $people_search = true;

    function __construct() {
      if ( class_exists( 'RA_Document_Post_Type' ) ) {
        /* If this is the document repository, bail out in order to avoid overriding the document search */
        return;
      }

      if ( empty( $this->cse_id ) ) {
        if ( file_exists( plugin_dir_path( dirname( __FILE__ ) ) . 'cse-id.php' ) ) {
          require_once( plugin_dir_path( dirname( __FILE__ ) ) . 'cse-id.php' );
          if ( isset( $GLOBALS['umw_cse_id'] ) ) {
            $this->cse_id = $GLOBALS['umw_cse_id'];
          }
        }
      }

      add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
      /* Override the default WordPress search & the default Genesis search */
      add_filter( 'get_search_form', array( $this, 'get_search_form' ) );
      add_filter( 'genesis_search_form', array( $this, 'get_search_form' ) );
      /* Override the default search results template */
      add_filter( 'search_template', array( $this, 'get_search_results' ) );

      /* Hook into the template_redirect action to perform theme-altering changes */
      add_action( 'template_redirect', array( $this, 'template_redirect' ) );
    }

    /**
     * Perform any theme changes that need to happen
     */
    function template_redirect() {
		remove_action( 'umw_header_content_full', 'umw_do_search_form', 12 );
		remove_action( 'umw_header_content_global', 'umw_do_search_form', 12 );
		add_action( 'genesis_before', array( $this, 'do_header_bar' ), 5 );
		add_action( 'umw-main-header-bar', array( $this, 'do_search_form' ), 5 );
    }

    /**
     * Enqueue any necessary JavaScript for this plugin
     */
    function enqueue_scripts() {
      /* Enqueue the script that displays and styles the search results */
      wp_enqueue_script( 'google-cse-iframe', '//www.google.com/cse/brand?form=cse-search-box&lang=en', array(), 1, true );
    }

    /**
     * Return the appropriate search results template
     */
    function get_search_results( $search_template ) {
      add_filter( 'adel_search_results', array( $this, 'get_adel_search_again' ) );
      // Avoide replacing the search results if we're searching the media library
      if ( isset( $_GET['media-library'] ) ) {
        return $search_template;
      }

      $this->set_default_get_vals();
      $this->replace_search_results_post_object();
      return $search_template;
    }

    /**
     * Set up the appropriate default GET variables
     */
    function set_default_get_vals() {
      /* Make sure we're searching something */
      if ( ! isset( $_GET['search-choice'] ) ) {
        $_GET['search-choice'] = 'google';
      }

      /* Implement single site search */
      if ( isset( $_GET['search-choice'] ) && ( 'wordpress' == $_GET['search-choice'] ) ) {
        if ( isset( $_GET['s'] ) && ! stristr( $_GET['s'], 'site:' ) ) {
          $_GET['s'] .= ' site:' . get_bloginfo( 'url' );
        }
      }

      if ( ! isset( $_GET['cx'] ) ) {
        $_GET['cx'] = $this->cse_id;
      }
      if ( ! isset( $_GET['cof'] ) ) {
        $_GET['cof'] = 'FORID:11';
      }
      if ( ! isset( $_GET['ie'] ) ) {
        $_GET['ie'] = 'UTF-8';
      }
    }

    /**
     * Modify the WP post object to display search results
     */
    function replace_search_results_post_object() {
      global $wp_query;
      if ( ! is_object( $wp_query ) ) {
        $wp_query = new WP_Query( 'p=1' );
      }
      $wp_query->posts = $this->get_google_search_post_object();
      $wp_query->is_404 = false;
      $wp_query->is_search = true;
      $wp_query->is_singular = true;
  		$wp_query->is_page = true;
  		$wp_query->is_paged = false;
  		$wp_query->is_home = false;
  		$wp_query->is_front_page = false;
  		$wp_query->post_count = 1;
  		$wp_query->found_posts = 1;
  		$wp_query->post = $wp_query->posts[0];
  		global $post;
  		$post = $wp_query->posts[0];
    }
    /**
     * Replace the standard WP loop with the search results
     */
    function search_results_loop( &$slug, &$name ) {
  		if( isset( $_GET['s'] ) ) {
  			$this->set_default_get_vals();
?>
<?php get_search_form(); ?>
<?php echo $this->get_search_results_html(); ?>
<?php
  			$slug = null;
  			$name = null;
  		}
  	}

    /**
     * Set up a WP post object with appropriate values for our search results
     */
    function get_google_search_post_object() {
  		return array( (object)array(
  			'post_author'		=> 1,
  			'post_date'			=> date( "Y-m-d h:i:s" ),
  			'post_date_gmt'		=> gmdate( "Y-m-d h:i:s" ),
  			'post_content'		=> $this->get_search_results_html(),
  			'post_excerpt'		=> $this->get_search_results_html(),
  			'post_title'		=> 'Search Results',
  			'post_status'		=> 'publish',
  			'comment_status'	=> 'closed',
  			'ping_status'		=> 'closed',
  			'post_type'			=> 'page',
  			'post_mime_type'	=> 'text/html',
  		) );
  	}

    function get_search_results_html() {
  		global $more, $active_directory_employee_list_object;
  		$more = 1;

  		if( isset( $_GET['search-choice'] ) && ( 'People Search' == $_GET['search-choice'] || 'people' == $_GET['search-choice'] ) ) {
  			$_REQUEST['adeq'] = $_GET['s'];
  			if( isset( $active_directory_employee_list_object ) && is_object( $active_directory_employee_list_object ) ) {
  				$adel = $active_directory_employee_list_object;
  				$gce_post_content = $adel->show_employees( /*$group=*/'All_UMW_Faculty_Staff;All_Active_Students_SG', /*$fields=*/$adel->fields_to_show, /*$formatting=*/array(), /*$echo=*/false, /*$show_title=*/false, /*$wrap_list=*/true, /*$include_search=*/false );
  			}
  		}

  		$r = '
  <div id="cse-search-results" class="umw-search-results">';

  		if( isset( $gce_post_content ) && !empty( $gce_post_content ) ) {
  			$r .= $gce_post_content;
  		} else {
  			$r .= '
  <iframe name="googleSearchFrame" src="//www.google.com/cse?cx=' . urlencode( $_GET['cx'] ) . '&cof=' . urlencode( $_GET['cof'] ) . '&ie=' . urlencode( $_GET['ie'] ) . '&q=' . urlencode( stripslashes( $_GET['s'] ) ) . '" width="100%" height="1650" marginwidth="0" marginheight="0" hspace="0" vspace="0" allowtransparency="true" scrolling="no" frameborder="0"></iframe>';
  		}

  		$r .= '
  </div>';

  		return $r;
  	}

    /**
     * Implement the "Search Again" box on the people search results
     */
    function get_adel_search_again( $content, $searchid = 's' ) {
   		if ( ! isset( $searchid ) )
   			$searchid = 's';
   		$rt = '<p>Would you like to <a href="#' . $searchid . '" class="click-to-focus">try a different search?</a></p>';
   		add_action( 'wp_print_footer_scripts', array( $this, 'focus_search_box' ) );
   		wp_register_script( 'jquery-ui-effects', get_bloginfo( 'stylesheet_directory' ) . '/lib/js/jquery.effects.core.min.js', array( 'jquery-ui-core' ), '1.8.16', true );
   		return $content . $rt;
   	}

    /**
     * Make the people search reset button work
     */
    function focus_search_box() {
   		wp_print_scripts( 'jquery-ui-effects' );
?>
<script type="text/javascript">
jQuery( function( $ ) {
	$( '.click-to-focus' ).click( function() {
		$( $(this).attr('href') ).css( { backgroundColor:'#ffff60' } ).animate( { backgroundColor:'#ffffff' }, 1500 );
		$( $(this).attr('href') ).focus();
		return false;
	} );
} );
</script>
<?php
   	}

    /**
     * Implement the Google search box
     */
    function get_google_search_form( $form, $searchtext=null ) {
   		/**
   		 * iFrame Search Form
   		 */
   		global $blog_id;

   		$searchtext = is_null( $searchtext ) ? ( isset( $_GET['s'] ) ? $_GET['s'] : '' ) : $searchtext;
   		$searchtext = trim( str_replace( 'site:' . get_bloginfo( 'url' ), '', $searchtext ) );
   		$searchtext = empty( $searchtext ) ? '' : $searchtext;

   		$search_choice = isset( $_GET['search-choice'] ) ? $_GET['search-choice'] : ( $this->use_buttons ? 'Search UMW' : 'google' );

   		$form = '';
		$searchbox = '<input type="search" autocomplete="off" name="s" id="s" size="31" value="' . stripslashes( esc_attr( $searchtext ) ) . '"/>';
		$searchbox = '<input autocomplete="off" type="search" size="10" class=" gsc-input " name="s" title="search" id="searchString" dir="ltr" spellcheck="false" style="outline: none; background: url(//www.google.com/cse/intl/en/images/google_custom_search_watermark.gif) 0% 50% no-repeat rgb(255, 255, 255);" data-cip-id="gsc-i-id1" value="' . stripslashes( esc_attr( $searchtext ) ) . '">';
		
		$choices = array();
		$choices['google'] = __( 'Search UMW' );
		if ( 1 != absint( $blog_id ) ) {
			$choices['wordpress'] = __( 'Search this Site' );
		}
		if ( $this->people_search ) {
			$choices['people'] = __( 'Search People' );
		}
		
		$form = '
<div id="portal-searchbox">
	<form action="' . get_bloginfo( 'url' ) . '" id="cse-search-box" class="umw-search-box">
		<ul id="menu" class="show">
			<li class="searchContainer">
			  %1$s
			  <div>
				  %2$s
				  <input type="submit" class="searchsubmit" value="' . __( 'Go' ) . '"/>
			  </div>
			</li>
		</ul>
	</form>
</div>';
		
		$tab = 1;
		if ( count( $choices ) > 1 ) {
			// Do a search with options
			$meat = '';
			foreach ( $choices as $k => $v ) {
				$meat .= sprintf( '<li><label for="%1$s" tabindex="%5$d"><input type="radio" name="search-choice" id="%1$s" value="%2$s"%3$s/><span>%4$s</span></label>', 'search-choice-' . $k, $k, checked( $search_choice, $k, false ), $v, $tab );
				$tab++;
			}
			$meat = sprintf( '<ul class="search-choices" id="search">%s</ul>', $meat );
		} else {
			// Do a plain search form
			$meat = '<input type="hidden" name="search-choice" value="google"/>';
		}
		
		wp_enqueue_script( 'jquery' );
		add_action( 'wp_print_footer_scripts', array( $this, 'do_search_choices_js' ) );
		return sprintf( $form, $meat, $searchbox );
     }
	 
	 function do_search_choices_js() {
?>
<script>
jQuery(document).ready(function(jq) {
	
	jq('input#searchString').css('background-image','none');

    var input_searchstring_focus = function() {
        jq('ul#search').addClass('show');
        /*jq('#portal-searchbox').addClass('topborderradius');*/
        jq('input#searchString').unbind('focus.searchstring');  // unbind ourself to avoid looping
        jq('ul#search.show li label input:checked').focus(); // allows the user to arrow through the options 
    };

    jq('ul#search li label input').keypress(function(event) {
        if (event.keyCode == 13) {  // If 'Enter' is clicked go to the searchbox input and not submit the form
            // Stop the default Enter. IE doesn't understand 'preventDefualt' but does 'returnValue'
            (event.preventDefault) ? event.preventDefault() : event.returnValue = false; 
            focus_to_searchbox();
        }
    });
    jq('ul#search li label').keyup(function(event) {
        if (event.keyCode == 32) {  // If the 'space bar' is pressed, go to the searchbox input
            focus_to_searchbox();
        }
    });
    jq('body').keydown(function(e) {
        var code = e.keyCode ? e.keyCode : e.which;
        if (code == 27) { // if the ESC key is pressed, close the searchbox
            close_searchbox();
        }
    });

    // initial bind
    jq('input#searchString').bind('focus.searchstring', input_searchstring_focus);
	
    jq('input#searchString').click(click_open_search_options);
    
    jq('div#portal-searchbox').click(function(e) {
        // keep click events from leaving the searchbox to avoid clicks triggering the close below if the clicks are also in the searchbox
        e.stopPropagation();
    });

    jq('html').click(close_searchbox); // allow the user to click anywhere else and close the search selections

    jq('ul#menu').removeClass('show'); // prove they can do javascript.. if not let the css option takes over
    
    // FUNCTIONS   
    function clear_searchString() {
        // Clear the box if it has the default 'Type To Search' only. Don't want to erase it if there is already a search term
        var searchValue = jq('input#searchString').val();
        if (searchValue == 'Type To Search...') {
            jq('input#searchString').val('');
        }  
    }

    function focus_to_searchbox() {
        jq('input#searchString').select();
        clear_searchString();
    }
    
    function close_searchbox() {
        jq('ul#search').removeClass('show');
        jq('#portal-searchbox').removeClass('topborderradius');
        jq('ul#search li label input').blur();
        // rebind so the box can re-open
        jq('input#searchString').bind('focus.searchstring', input_searchstring_focus);
    }
     
    function click_open_search_options() {
        jq('ul#search').addClass('show');
        //jq('#portal-searchbox').addClass('topborderradius');
        clear_searchString()
        jq('input#searchString').select();
    }

});
</script>
<?php
	 }

     /**
      * To be implemented at a later date.
      * Currently just returns the original Google search box
      */
     function get_search_form( $form, $search_text=null ) {
       return $this->get_google_search_form( $form, $search_text );
     }

     /**
      * Echo the search form
      */
    function do_search_form( $form=null, $search_text=null ) {
      echo $this->get_google_search_form( $form, $search_text );
    }
	
	/**
	 * Output the header bar
	 */
	function do_header_bar() {
		echo '
<aside class="umw-header-bar">
	<div class="wrap">';
		do_action( 'umw-main-header-bar' );
		echo '
	</div>
</aside>';
		$this->do_header_bar_styles();
	}
	
	/**
	 * Output some CSS for the header bar
	 */
	function do_header_bar_styles() {
?>
<style>
aside.umw-header-bar {
	position: relative;
	z-index: 250;
	width: 100%;
	background: rgb( 0, 48, 94 );
}

.umw-header-bar > .wrap {
	background: none;
	width: 100%;
	max-width: 960px;
	overflow: visible;
	min-height: 42px;
	clear: both;
}

#umw-custom-background {
	clear: both;
}

#portal-searchbox {
	float: right;
	width: 250px;
	background: transparent;
}

#portal-searchbox::after, 
.umw-header-bar > .wrap::after, 
.umw-search-box::after, 
.umw-header-bar::after {
	content: "";
	clear: both;
	width: 0;
	height: 0;
	line-height: 0;
	font-size: 0;
	overflow: hidden;
	margin: 0;
	padding: 0;
}

#portal-searchbox ul, 
#portal-searchbox ul > li, 
#portal-searchbox ol, 
#portal-searchbox ol > li {
	list-style: none;
}

li.searchContainer {
	position: relative;
	padding: 10px 20px;
}

ul.search-choices {
	margin-top: 42px;
	position: absolute;
	top: 0;
	left: -99999px;
	width: 100%;
	box-sizing: border-box;
	background: rgb( 0, 48, 94 );
	border-bottom: 1px solid #fff;
}

ul.search-choices li {
	padding: 8px 16px;
	border: 1px solid #fff;
	border-bottom: none;
	color: #fff;
}

ul.search-choices li input {
	margin-right: 12px;
}

ul.search-choices.show {
	left: 0;
}

.searchContainer .gsc-input {
	width: 80%;
}

.searchContainer .searchsubmit {
	width: 15%;
	float: right;
}

@media all and (max-width: 1023px) {
	aside.umw-header-bar {
		display: none;
	}
}
</style>
<?php
	}
  }
}
