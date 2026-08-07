<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetches and caches episode data from the configured Buzzsprout RSS feed.
 */
class Buzzsprout_Feed {

	const OPTION_KEY     = 'buzzsprout-podcasting';
	const CACHE_LIFETIME = 15 * MINUTE_IN_SECONDS;

	public static function get_settings() {
		$defaults = array(
			'feed-uri'        => '',
			'include-flash'   => true,
			'number-episodes' => 5,
		);
		$settings = get_option( self::OPTION_KEY );
		return is_array( $settings ) ? array_merge( $defaults, $settings ) : $defaults;
	}

	public static function get_feed_url() {
		$settings = self::get_settings();
		return $settings['feed-uri'];
	}

	public static function is_feed_valid( $url ) {
		if ( ! is_string( $url ) || ! trim( $url ) ) {
			return false;
		}
		return (bool) preg_match( '#^http(s)?://((feeds|rss|www)\.)?buzzsprout\.com/[0-9]+\.rss$#i', $url );
	}

	/**
	 * The podcast ID, extracted from the feed URL.
	 *
	 * @return string|false
	 */
	public static function get_podcast_id( $feed_uri = false ) {
		if ( ! $feed_uri ) {
			$feed_uri = self::get_feed_url();
		}
		if ( ! preg_match( '#^https?://((feeds|rss|www)\.)?buzzsprout\.com/([0-9]+)\.rss$#i', (string) $feed_uri, $matches ) ) {
			return false;
		}
		return $matches[3];
	}

	/**
	 * Episodes from the feed, newest first.
	 *
	 * @param int $limit Max episodes to return; 0 for the configured setting.
	 * @return array[]|WP_Error Arrays with id, title, date, duration, link.
	 */
	public static function get_episodes( $limit = 0, $tags = array() ) {
		$settings = self::get_settings();
		if ( ! self::is_feed_valid( $settings['feed-uri'] ) ) {
			return new WP_Error(
				'buzzsprout_no_feed',
				__( 'No valid Buzzsprout feed URL is configured.', 'buzzsprout-podcasting' )
			);
		}

		if ( $limit < 1 ) {
			$limit = (int) $settings['number-episodes'];
		}

		$episodes = self::fetch_all_episodes( $settings['feed-uri'] );
		if ( is_wp_error( $episodes ) ) {
			return $episodes;
		}

		if ( $tags ) {
			$wanted   = array_map( 'strtolower', $tags );
			$episodes = array_values(
				array_filter(
					$episodes,
					function ( $episode ) use ( $wanted ) {
						$have = array_map( 'strtolower', $episode['tags'] );
						return (bool) array_intersect( $wanted, $have );
					}
				)
			);
		}

		return array_slice( $episodes, 0, $limit );
	}

	private static function fetch_all_episodes( $feed_uri ) {
		// v2 suffix: cache shape gained the per-episode tags array.
		$cache_key = 'buzzsprout_episodes_v2_' . md5( $feed_uri );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		if ( ! function_exists( 'fetch_feed' ) ) {
			include_once ABSPATH . WPINC . '/feed.php';
		}

		$shorten_lifetime = function () {
			return self::CACHE_LIFETIME;
		};
		add_filter( 'wp_feed_cache_transient_lifetime', $shorten_lifetime );
		$rss = fetch_feed( $feed_uri );
		remove_filter( 'wp_feed_cache_transient_lifetime', $shorten_lifetime );

		// MIME type can throw errors inside fetch_feed; parse directly as a fallback.
		if ( is_wp_error( $rss ) ) {
			$response = wp_remote_get( $feed_uri, array( 'timeout' => 10 ) );
			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				return new WP_Error(
					'buzzsprout_feed_unreachable',
					__( 'The Buzzsprout feed could not be fetched.', 'buzzsprout-podcasting' )
				);
			}
			$rss = new SimplePie();
			$rss->set_raw_data( wp_remote_retrieve_body( $response ) );
			$rss->force_feed( true );
			$rss->init();
			$rss->handle_content_type();
		}

		$podcast_id = self::get_podcast_id( $feed_uri );
		$episodes   = array();
		foreach ( $rss->get_items() as $item ) {
			$enclosure = $item->get_enclosure();
			$id        = $enclosure ? self::episode_id_from_media_url( $enclosure->get_link() ) : false;
			if ( ! $id ) {
				continue;
			}
			$duration_tags = $item->get_item_tags( SIMPLEPIE_NAMESPACE_ITUNES, 'duration' );
			$keyword_tags  = $item->get_item_tags( SIMPLEPIE_NAMESPACE_ITUNES, 'keywords' );
			$keywords      = isset( $keyword_tags[0]['data'] ) ? (string) $keyword_tags[0]['data'] : '';
			$episodes[]    = array(
				'id'       => $id,
				'title'    => html_entity_decode( (string) $item->get_title(), ENT_QUOTES ),
				'date'     => (string) $item->get_date( 'Y-m-d' ),
				'duration' => isset( $duration_tags[0]['data'] ) ? (string) $duration_tags[0]['data'] : '',
				'tags'     => array_values( array_filter( array_map( 'trim', explode( ',', $keywords ) ) ) ),
				// The feed item's own link is often the raw media/tracking
				// URL, so build the episode page URL instead.
				'link'     => sprintf( 'https://www.buzzsprout.com/%s/episodes/%s', $podcast_id, $id ),
			);
		}

		set_transient( $cache_key, $episodes, self::CACHE_LIFETIME );
		return $episodes;
	}

	/**
	 * Extracts the episode ID from an enclosure/media URL, e.g.
	 * https://www.buzzsprout.com/96/episodes/1917-some-title.mp3
	 *
	 * @return string|false
	 */
	public static function episode_id_from_media_url( $media_url ) {
		if ( ! preg_match( '|buzzsprout\.com/[0-9]+/episodes/([0-9]+)|i', (string) $media_url, $matches ) ) {
			return false;
		}
		return $matches[1];
	}
}
