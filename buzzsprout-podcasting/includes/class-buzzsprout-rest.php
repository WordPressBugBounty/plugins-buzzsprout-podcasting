<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST endpoint that feeds the block editor's episode picker.
 */
class Buzzsprout_REST {

	public static function register_routes() {
		register_rest_route(
			'buzzsprout/v1',
			'/episodes',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_episodes' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}

	public static function get_episodes() {
		// The editor's picker searches the whole catalog, so return every
		// episode in the feed.
		$episodes = Buzzsprout_Feed::get_episodes( PHP_INT_MAX );
		if ( is_wp_error( $episodes ) ) {
			$episodes->add_data( array( 'status' => 400 ) );
			return $episodes;
		}
		return rest_ensure_response( $episodes );
	}
}
