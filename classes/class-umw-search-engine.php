<?php
/**
 * Implements the UMW_Search_Engine class
 */
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

      add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
      /* Override the default WordPress search & the default Genesis search */
      add_filter( 'get_search_form', array( $this, 'get_search_form' ) );
      add_filter( 'genesis_search_form', array( $this, 'get_search_form' ) );
      /* Override the default search results template */
      add_filter( 'search_template', array( $this, 'get_search_results' ) );
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
  <div id="cse-search-results">';

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
   		$form .= '
   <form action="' . get_bloginfo( 'url' ) . '" id="cse-search-box">
   	<div class="umw-google-search-form">
   		<p class="umw-google-search-box">
   			<label for="s" style="margin: 0; padding: 0; width: 0; height: 0; line-height: 0; font-size: 0;">' . __( 'Search' ) . '</label>
   			<input type="text" autocomplete="off" name="s" id="s" size="31" value="' . stripslashes( esc_attr( $searchtext ) ) . '" />';
   		if( $this->use_buttons ) {
   			$form .= '
   			<input type="submit" class="searchsubmit" name="search-choice" value="Search UMW" />';

   			if( 1 != $blog_id ) {
   				$form .= '
   			<input type="submit" class="searchsubmit" name="search-choice" value="Search This Site" />';
   			}

   			if( $this->people_search ) {
   				$form .= '
   			<input type="submit" class="searchsubmit" name="search-choice" value="People Search" />';
   			}
   		} else {
   			$form .= '
   			<input type="submit" class="searchsubmit" value="Search"/>
   		</p>
   		<p class="umw-google-search-options">
   			<!-- <strong class="umw-google-search-title">Search: </strong> -->
   			<label class="umw-google-search-radio global-search"><input type="radio" name="search-choice" value="google"' . checked( $search_choice, 'google', false ) . '/> UMW</label>';

   			if( 1 != $blog_id ) {
   				$form .= '
   			<label class="umw-google-search-radio site-search"><input type="radio" name="search-choice" value="wordpress"' . checked( $search_choice, 'wordpress', false ) . '/> This Site</label>';
   			}

   			if( $this->people_search ) {
   				$form .= '
   			<label class="umw-google-search-radio people-search"><input type="radio" name="search-choice" value="people"' . checked( $search_choice, 'people', false ) . '/> People</label>';
   			}
   		}
   		$form .= '
   		</p>
   	</div>
   </form>';

      return $form;
     }

     /**
      * To be implemented at a later date.
      * Currently just returns the original Google search box
      */
     function get_search_form( $form, $search_text=null ) {
       return $this->get_google_search_form( $form, $search_text );
     }
  }
}
