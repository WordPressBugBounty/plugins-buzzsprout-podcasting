<?php
/*
Plugin Name: Buzzsprout Podcasting
Plugin URI: https://www.buzzsprout.com/wordpress
Description: The official Buzzsprout plugin. Embed your podcast episodes with editor blocks that always stay up to date with your show.
Version: 2.0.2
Requires at least: 6.2
Requires PHP: 7.4
Author: Buzzsprout
Author URI: https://www.buzzsprout.com
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: buzzsprout-podcasting
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BUZZSPROUT_PODCASTING_VERSION', '2.0.2' );
define( 'BUZZSPROUT_PODCASTING_FILE', __FILE__ );
define( 'BUZZSPROUT_PODCASTING_DIR', plugin_dir_path( __FILE__ ) );

require_once BUZZSPROUT_PODCASTING_DIR . 'includes/class-buzzsprout-feed.php';
require_once BUZZSPROUT_PODCASTING_DIR . 'includes/class-buzzsprout-render.php';
require_once BUZZSPROUT_PODCASTING_DIR . 'includes/class-buzzsprout-rest.php';
require_once BUZZSPROUT_PODCASTING_DIR . 'includes/class-buzzsprout-blocks.php';
require_once BUZZSPROUT_PODCASTING_DIR . 'includes/class-buzzsprout-legacy.php';

add_action( 'init', array( 'Buzzsprout_Podcasting', 'initialize' ) );
add_action( 'init', array( 'Buzzsprout_Blocks', 'register' ) );
add_action( 'rest_api_init', array( 'Buzzsprout_REST', 'register_routes' ) );
