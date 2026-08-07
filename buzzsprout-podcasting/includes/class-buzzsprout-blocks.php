<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the editor blocks. The editor scripts are plain JS built on the
 * wp.* globals — no build step — so their dependencies are declared here
 * rather than via generated *.asset.php files.
 */
class Buzzsprout_Blocks {

	public static function register() {
		$deps = array(
			'wp-blocks',
			'wp-element',
			'wp-block-editor',
			'wp-components',
			'wp-server-side-render',
			'wp-api-fetch',
			'wp-i18n',
		);

		wp_register_script(
			'buzzsprout-player-editor',
			plugins_url( 'blocks/player/index.js', BUZZSPROUT_PODCASTING_FILE ),
			$deps,
			BUZZSPROUT_PODCASTING_VERSION,
			true
		);
		wp_register_script(
			'buzzsprout-episode-list-editor',
			plugins_url( 'blocks/episode-list/index.js', BUZZSPROUT_PODCASTING_FILE ),
			$deps,
			BUZZSPROUT_PODCASTING_VERSION,
			true
		);

		register_block_type(
			BUZZSPROUT_PODCASTING_DIR . 'blocks/player',
			array( 'render_callback' => array( 'Buzzsprout_Render', 'render_player_block' ) )
		);
		register_block_type(
			BUZZSPROUT_PODCASTING_DIR . 'blocks/episode-list',
			array( 'render_callback' => array( 'Buzzsprout_Render', 'render_episode_list_block' ) )
		);
	}
}
