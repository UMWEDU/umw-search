<?php
/**
 * Implements the UMW_Search_Engine class
 */
if ( ! defined( 'ABSPATH' ) ) {
  die( 'You should not access this file directly.' );
}

if ( ! class_exists( 'UMW_Search_Engine' ) ) {
  class UMW_Search_Engine {
    public $v = '0.2.7';
    public $use_buttons = false;
    private $cse_id = null;
    public $people_search = true;
	public $use_search = false;
	public $toolbar = null;

    function __construct() {
		global $umw_online_tools_obj;
		if ( isset( $umw_online_tools_obj ) && is_a( $umw_online_tools_obj, 'UMW_Online_Tools' ) ) {
			$this->toolbar = $umw_online_tools_obj;
		} else {
			$this->toolbar = new UMW_Online_Tools_Placeholder;
		}

		if ( class_exists( 'RA_Document_Post_Type' ) ) {
			/* If this is the document repository, bail out in order to avoid overriding the document search */
			return;
		}

		add_action( 'init', array( $this, 'init' ) );
		add_action( 'wp', array( $this, 'redirect_student_search' ) );

		add_filter( 'validate-umw-global-toolbar-settings', array( $this, 'sanitize_settings' ), 10, 2 );
		add_filter( 'umw-global-toolbar-settings-checkbox-fields', array( $this, 'register_settings_field' ) );
		add_filter( 'umw-toolbar-default-settings-main', array( $this, 'default_settings_main' ) );
    }

	function redirect_student_search() {
		if ( isset( $_REQUEST['search-choice'] ) && 'students' == $_REQUEST['search-choice'] ) {
			$url = 'http://students.umw.edu/directory/';
			$url = add_query_arg( 'adeq', stripslashes( $_REQUEST['s'] ), $url );
			header( "Location: $url" );
			die();
		}
	}
	
	/**
	 * Set things up for our plugin
	 */
	function init() {
		/**
		 * We need to try to short-circuit any Directory searches & point them to a WP search on the Directory site
		 */
		if ( isset( $_GET['s'] ) && isset( $_GET['search-choice'] ) && 'people' == $_GET['search-choice'] ) {
			if ( defined( 'UMW_EMPLOYEE_DIRECTORY' ) ) {
				$url = null;
				$_GET['s'] = stripslashes_deep( $_GET['s'] );
				if ( is_numeric( UMW_EMPLOYEE_DIRECTORY ) && $GLOBALS['blog_id'] == UMW_EMPLOYEE_DIRECTORY ) {
					$url = add_query_arg( 's', $_GET['s'], trailingslashit( esc_url( get_bloginfo( 'url' ) ) ) );
				} else if ( is_numeric( UMW_EMPLOYEE_DIRECTORY ) && $GLOBALS['blog_id'] != UMW_EMPLOYEE_DIRECTORY ) {
					$url = add_query_arg( 's', $_GET['s'], trailingslashit( esc_url( get_blog_option( UMW_EMPLOYEE_DIRECTORY, 'home' ) ) ) );
				} else if ( esc_url( UMW_EMPLOYEE_DIRECTORY ) ) {
					$url = add_query_arg( 's', $_GET['s'], trailingslashit( esc_url( UMW_EMPLOYEE_DIRECTORY ) ) );
				}
				if ( ! empty( $url ) ) {
					/*if ( is_user_logged_in() && current_user_can( 'delete_users' ) ) {
						wp_die( 'Should be redirecting to ' . $url );
					}*/
					header( "Location: " . $url );
					die();
				}
			}
		}
	
		/**
		 * Bail out if this site isn't supposed to show the toolbar
		 */
		if ( false !== $this->toolbar->is_main_umw_theme() ) {
			$this->use_search = true;
		}
		if ( false !== $this->toolbar->options['global-bar'] && false !== $this->toolbar->options['search'] ) {
			$this->use_search = true;
		}

		if ( false === $this->use_search ) {
			return;
		}

		if ( empty( $this->cse_id ) ) {
			if ( isset( $GLOBALS['umw_cse_id'] ) ) {
				$this->cse_id = $GLOBALS['umw_cse_id'];
			} else if ( defined( 'UMW_CSE_ID' ) ) {
				$this->cse_id = UMW_CSE_ID;
			} else if ( file_exists( plugin_dir_path( dirname( __FILE__ ) ) . 'cse-id.php' ) ) {
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
		add_action( 'template_redirect', array( $this, 'template_redirect' ), 0 );
	}

	/**
	 * Add the settings field for this plugin to the array of fields being registered through the online tools plugin
	 */
	function register_settings_field( $fields=array() ) {
		$fields['search'] = array(
			'id'    => 'umw-enable-global-search',
			'title' => __( 'Global Search' ),
			'label' => __( 'Enable the global search area?' ),
		);
		return $fields;
	}

	/**
	 * Sanitize our settings for this plugin
	 */
	function sanitize_settings( $output, $input=array() ) {
		$output['search'] = isset( $input['search'] );
		return $output;
	}

	/**
	 * Add the default option(s) for this plugin if this is the main UMW theme
	 */
	function default_settings_main( $options=array() ) {
		$options['search'] = true;
		return $options;
	}

    /**
     * Perform any theme changes that need to happen
     */
    function template_redirect() {
		remove_action( 'umw_header_content_full', 'umw_do_search_form', 12 );
		remove_action( 'umw_header_content_global', 'umw_do_search_form', 12 );
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

	  if ( defined( 'UMW_EMPLOYEE_DIRECTORY' ) ) {
		  if ( is_numeric( UMW_EMPLOYEE_DIRECTORY ) ) {
			  $url = get_blog_option( UMW_EMPLOYEE_DIRECTORY, 'home', false );
		  } else {
			  $url = esc_url( UMW_EMPLOYEE_DIRECTORY );
		  }
		  if ( isset( $_GET['search-choice'] ) && 'people' == $_GET['search-choice'] ) {
			  if  ( isset( $_GET['s'] ) && ! stristr( $_GET['s'], 'site:' ) ) {
				  $_GET['s'] .= ' site:' . $url;
			  }
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

    function get_search_results_html( $native=false ) {
  		global $more, $active_directory_employee_list_object;
  		$more = 1;

        if ( get_option( 'native-cse', false ) ) {
            $gce_post_content = '<script>
  (function() {
    var cx = \'' . $this->cse_id . '\';
    var gcse = document.createElement(\'script\');
    gcse.type = \'text/javascript\';
    gcse.async = true;
    gcse.src = (document.location.protocol == \'https:\' ? \'https:\' : \'http:\') +
        \'//cse.google.com/cse.js?cx=\' + cx;
    var s = document.getElementsByTagName(\'script\')[0];
    s.parentNode.insertBefore(gcse, s);
  })();
</script>
<gcse:searchresults-only></gcse:searchresults-only>';
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
     * Implement the JS-based native Google CSE form
     */
    function get_native_google_search_form( $form, $searchtext=null ) {
        ob_start();
?>
<div class="umw-google-cse">
<script>
  (function() {
    var cx = '<?php echo json_encode( $this->cse_id ) ?>';
    var gcse = document.createElement('script');
    gcse.type = 'text/javascript';
    gcse.async = true;
    gcse.src = (document.location.protocol == 'https:' ? 'https:' : 'http:') +
        '//cse.google.com/cse.js?cx=' + cx;
    var s = document.getElementsByTagName('script')[0];
    s.parentNode.insertBefore(gcse, s);
  })();
</script>
<gcse:searchbox-only></gcse:searchbox-only>
</div>
<?php
        return ob_get_clean();
    }

    /**
     * Implement the Google search box
     */
    function get_google_search_form( $form, $searchtext=null, $native=false ) {
        if ( $native ) {
            return $this->get_native_google_search_form( $form, $searchtext );
        }

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
		$searchbox = '<label class="hidden" for="searchString">' . __( 'Search:' ) . '</label>' . $searchbox;

		$choices = array();
		$choices['google'] = __( 'Search UMW' );
		if ( 1 != absint( $blog_id ) ) {
			$choices['wordpress'] = __( 'Search this Site' );
		}
		if ( $this->people_search ) {
			$choices['people'] = __( 'Search Faculty &amp; Staff' );
			$choices['students'] = __( 'Search Students' );
		}

		$form = '
<div class="umw-search-wrapper">
	<form action="' . get_bloginfo( 'url' ) . '" id="cse-search-box" class="umw-search-box">
		<ul class="umw-search-container-wrapper">
			<li class="umw-search-container">
			  %1$s
			  <div>
				  %2$s
				  <input type="submit" class="searchsubmit" value="' . __( 'Go' ) . '"/>
			  </div>
				<br class="mobile-clear"/>
			</li>
		</ul>
	</form>
</div>%3$s';

		$tab = 1;
		if ( count( $choices ) > 1 ) {
			// Do a search with options
			$meat = '';
			foreach ( $choices as $k => $v ) {
				$meat .= sprintf( '<li><label for="%1$s" tabindex="%5$d"><input type="radio" name="search-choice" id="%1$s" value="%2$s"%3$s/><span>%4$s</span></label>', 'search-choice-' . $k, $k, checked( $search_choice, $k, false ), $v, $tab );
				$tab++;
			}
			$meat = sprintf( '<ul class="umw-search-choices show">%s</ul>', $meat );
		} else {
			// Do a plain search form
			$meat = '<input type="hidden" name="search-choice" value="google"/>';
		}

		wp_enqueue_script( 'jquery' );
		$s = "document.querySelectorAll('.umw-search-choices')[0].className = 'umw-search-choices';";
		$s = sprintf( '<script type="text/javascript">%s</script>', $s );
		add_action( 'wp_print_footer_scripts', array( $this, 'do_search_choices_js' ), 1 );
		return sprintf( $form, $meat, $searchbox, $s );
     }

	 function do_search_choices_js() {
?>
<script>
var UMWSearchJS = UMWSearchJS || {
	'input' : jQuery( 'input.gsc-input' ),
	'form' : jQuery( 'form.umw-search-box' ),
	'container' : jQuery( '.umw-search-wrapper' ),
	'menu' : jQuery( '.umw-search-choices' ),
	'do_focus' : function() {
		jQuery( 'ul.umw-search-choices' ).addClass( 'show' );
		UMWSearchJS.input.off( 'focus', UMWSearchJS.do_focus );
		jQuery( 'ul.umw-search-choices input:checked' ).focus(); // allows the user to arrow through the options
	},
	'do_keypress' : function( event ) {
		if ( event.keyCode == 13 ) { // If 'Enter' is clicked, go to the searchbox input and not submit the form
			// Stop the default Enter. IE doesn't understand 'preventDefualt' but does 'returnValue'
			UMWSearchJS.log( 'You hit the enter key' );
			( event.preventDefault ) ? event.preventDefault() : event.returnValue = false;
			UMWSearchJS.focus_to_searchbox();
		}
	},
	'do_keyup' : function( event ) {
		if ( event.keyCode == 32 ) { // If the 'space bar' is pressed, go to the searchbox input
			UMWSearchJS.log( 'You pressed the space bar' );
			UMWSearchJS.focus_to_searchbox();
		}
	},
	'do_keydown' : function( event ) {
		var code = event.keyCode ? event.keyCode : event.which;
		if ( code == 27 ) { // if the ESC key is pressed, close the searchbox
			UMWSearchJS.log( 'You hit the ESC key' );
			UMWSearchJS.close();
		}
	},
	'close' : function() {
		UMWSearchJS.log( 'Attempting to close the box' );
		if ( UMWSearchJS.menu.hasClass( 'mobile' ) ) {
			return;
		}
		UMWSearchJS.menu.removeClass( 'show' );
		UMWSearchJS.container.find( 'label input' ).blur();
		// rebind so the box can re-open
		UMWSearchJS.input.on( 'focus', UMWSearchJS.do_focus );
	},
	'open' : function() {
		UMWSearchJS.menu.addClass( 'show' );
		UMWSearchJS.clear();
		UMWSearchJS.input.select();
	},
	'clear' : function() {
		var searchValue = UMWSearchJS.input.val();
		if ( searchValue == 'Type To Search...' ) {
			UMWSearchJS.input.val( '' );
		}
	},
	'focus_to_searchbox' : function() {
		UMWSearchJS.input.select();
		UMWSearchJS.clear();
	},
	'log' : function( m ) {
		return;

		if ( typeof( console ) !== 'undefined' ) {
			console.log( m );
		} else if ( typeof( m ) == 'string' ) {
			alert( m );
		} else {
			return;
		}
	}
};

jQuery( function( $ ) {
	/* Remove the Google Branding image */
	UMWSearchJS.input.css( 'background-image', 'none' );

	UMWSearchJS.menu.find( 'label input' ).on( 'keypress', function(e) { return UMWSearchJS.do_keypress(e); } );
	UMWSearchJS.menu.find( 'label' ).on( 'keyup', function(e) { return UMWSearchJS.do_keyup(e); } );
	jQuery( 'body' ).on( 'keydown', function(e) { return UMWSearchJS.do_keydown(e); } );
	UMWSearchJS.input.on( 'focus', UMWSearchJS.do_focus );
	UMWSearchJS.input.on( 'click', function() { return UMWSearchJS.open(); } );
	UMWSearchJS.container.on( 'click', function( e ) {
        // keep click events from leaving the searchbox to avoid clicks triggering the close below if the clicks are also in the searchbox
		e.stopPropagation();
	} );
	jQuery( 'html' ).on( 'click', function() { UMWSearchJS.log( 'You clicked somewhere in the HTML element' ); return UMWSearchJS.close(); } );
	UMWSearchJS.menu.removeClass( 'show' );
} );
</script>
<?php
	 }

	/**
	 * To be implemented at a later date.
	 * Currently just returns the original Google search box
	 */
	function get_search_form( $form, $search_text=null ) {
        $native = get_option( 'native-cse', false );
		return $this->get_google_search_form( $form, $search_text, $native );
	}

     /**
      * Echo the search form
      */
    function do_search_form( $form=null, $search_text=null ) {
      echo $this->get_google_search_form( $form, $search_text );
    }

  }
}

class UMW_Online_Tools_Placeholder {
	function __construct() {
		$this->options = array();
	}
	function is_main_umw_theme() {
		return false;
	}
};