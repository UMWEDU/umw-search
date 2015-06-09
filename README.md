# UMW Search Engine Implementation #
**Contributors:** cgrymala

**Tags:** search, google cse, umw

**Donate link:** http://giving.umw.edu/

**Requires at least:** 4.0

**Tested up to:** 4.2.2

**Stable tag:** 0.2.4

**License:** GPLv2 or later

**License URI:** http://www.gnu.org/licenses/gpl-2.0.html


This plugin implements the custom search used throughout the UMW website.

## Description ##
This plugin replaces the default WordPress search engine with a Google Custom Search Engine specifically for the use of the University of Mary Washington.

This plugin now hooks into the UMW Online Tools plugin to output the search engine as part of the global toolbar.

## Installation ##
1. Upload the umw-search folder to wp-content/plugins
1. Create a blank PHP file at wp-content/plugins/umw-search/cse-id.php and add your CSE ID (instructions in the FAQ section)
1. Populate the `$umw_cse_Id` variable with your CSE ID
1. Activate the plugin

## Frequently Asked Questions ##
## How do I specify my CSE ID? ##

Create a blank file at wp-content/plugins/umw-search/cse-id.php and paste in the following code:

```
		<?php
    	if ( ! defined( 'ABSPATH' ) ) {
      		die( 'You should not access this file directly.' );
    	}

    	global $umw_cse_id;
    	$umw_cse_id = '';
```

## Why don't things like auto-complete, etc. work? ##

For accessibility reasons, this plugin was originally designed to mimic the HTML output of Google's custom search engine. Google uses a single line of JavaScript code to render the normal search box, which would leave the search engine inoperable for any that don't use JS. Since this plugin doesn't use the native Google code, some features of the CSE are not available.

## Changelog ##
### 0.2 ###
* Integrate the plugin into the Online Tools plugin

### 0.1 ###
* Initial version
