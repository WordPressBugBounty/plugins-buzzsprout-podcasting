<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders Buzzsprout players. Shared by the blocks, the [buzzsprout]
 * shortcode, and the legacy media tab.
 */
class Buzzsprout_Render {

	/**
	 * The standard Buzzsprout JS embed for one episode.
	 *
	 * On the front end the vendor script is enqueued (so it loads in the
	 * footer, after its container exists) and only the container is returned.
	 * Editor previews get the iframe form of the same player instead, since
	 * enqueued scripts never reach a REST response.
	 *
	 * @param string $episode_id  Numeric episode ID.
	 * @param bool   $show_player Render the audio player (vs. title link only).
	 * @return string HTML, or '' if not renderable.
	 */
	public static function player_embed( $episode_id, $show_player = true ) {
		$episode_id = sanitize_key( $episode_id );
		$podcast_id = Buzzsprout_Feed::get_podcast_id();
		if ( ! $episode_id || ! $podcast_id ) {
			return '';
		}

		if ( self::is_editor_preview() ) {
			return self::preview_iframe(
				sprintf(
					'https://www.buzzsprout.com/%s/episodes/%s?client_source=small_player&iframe=true',
					rawurlencode( $podcast_id ),
					rawurlencode( $episode_id )
				),
				200
			);
		}

		$container = self::unique_container( 'buzzsprout-player-' . $episode_id );
		$src       = sprintf(
			'https://www.buzzsprout.com/%s/episodes/%s.js?container_id=%s%s',
			rawurlencode( $podcast_id ),
			rawurlencode( $episode_id ),
			rawurlencode( $container ),
			$show_player ? '&player=small' : ''
		);

		self::enqueue_embed( $container, $src );
		return sprintf( '<div id="%s"></div>', esc_attr( $container ) );
	}

	/**
	 * The multi-episode "large player" embed for the whole podcast.
	 *
	 * @return string HTML, or '' if not renderable.
	 */
	public static function playlist_embed( $limit = 0, $tags = '' ) {
		$podcast_id = Buzzsprout_Feed::get_podcast_id();
		if ( ! $podcast_id ) {
			return '';
		}

		$params = '';
		if ( (int) $limit > 0 ) {
			$params .= '&limit=' . (int) $limit;
		}
		$tag_list = self::parse_tags( $tags );
		if ( $tag_list ) {
			// Match the official embed format: "tag, tag", URL-encoded.
			$params .= '&tags=' . rawurlencode( implode( ', ', $tag_list ) );
		}

		if ( self::is_editor_preview() ) {
			return self::preview_iframe(
				sprintf(
					'https://www.buzzsprout.com/%s?client_source=large_player&iframe=true%s',
					rawurlencode( $podcast_id ),
					$params
				),
				450
			);
		}

		$container = self::unique_container( 'buzzsprout-playlist' );
		$src       = sprintf(
			'https://www.buzzsprout.com/%s.js?container_id=%s&player=large%s',
			rawurlencode( $podcast_id ),
			rawurlencode( $container ),
			$params
		);

		self::enqueue_embed( $container, $src );
		return sprintf( '<div id="%s"></div>', esc_attr( $container ) );
	}

	/**
	 * Enqueues one Buzzsprout embed script in the footer, so it runs after its
	 * container element exists.
	 */
	private static function enqueue_embed( $container, $src ) {
		// No version is passed on purpose: this is Buzzsprout's own script and
		// they version and cache it themselves. Appending a plugin version
		// would tie their cache key to our release cycle.
		// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_enqueue_script( 'buzzsprout-embed-' . $container, $src, array(), null, true );
	}

	/**
	 * A DOM id that stays stable for the common single-embed case but never
	 * repeats when the same episode is embedded more than once on a page.
	 */
	private static function unique_container( $base ) {
		static $used = array();
		$id          = $base;
		$suffix      = 1;
		while ( isset( $used[ $id ] ) ) {
			++$suffix;
			$id = $base . '-' . $suffix;
		}
		$used[ $id ] = true;
		return $id;
	}

	/**
	 * A comma-separated tags string as a clean array.
	 */
	private static function parse_tags( $tags ) {
		return array_values( array_filter( array_map( 'trim', explode( ',', (string) $tags ) ) ) );
	}

	/**
	 * Whether we are rendering for the block editor's ServerSideRender
	 * preview rather than the front end.
	 */
	private static function is_editor_preview() {
		return defined( 'REST_REQUEST' ) && REST_REQUEST;
	}

	/**
	 * Editor previews use Buzzsprout's iframe player: enqueued scripts never
	 * reach a REST response, and this markup is never saved to a site.
	 */
	private static function preview_iframe( $src, $height = 200 ) {
		return sprintf(
			'<iframe class="buzzsprout-editor-preview" src="%s" width="100%%" height="%d" frameborder="0" scrolling="no" style="display:block;border:0;"></iframe>',
			esc_url( $src ),
			(int) $height
		);
	}

	private static function placeholder( $message ) {
		if ( ! self::is_editor_preview() ) {
			return '';
		}
		return sprintf(
			'<div style="padding:1em;border:1px dashed #949494;border-radius:2px;color:#757575;">%s</div>',
			esc_html( $message )
		);
	}

	/**
	 * "55:42" from feed values that may be raw seconds or already formatted.
	 */
	private static function format_duration( $raw ) {
		if ( '' === $raw ) {
			return '';
		}
		if ( false !== strpos( $raw, ':' ) ) {
			return $raw;
		}
		$seconds = (int) $raw;
		if ( $seconds <= 0 ) {
			return '';
		}
		if ( $seconds >= 3600 ) {
			return sprintf( '%d:%02d:%02d', floor( $seconds / 3600 ), floor( $seconds / 60 ) % 60, $seconds % 60 );
		}
		return sprintf( '%d:%02d', floor( $seconds / 60 ), $seconds % 60 );
	}

	/**
	 * Wraps block output in the standard block wrapper so supports like
	 * alignment and spacing actually apply.
	 */
	private static function block_wrap( $inner, $extra = array() ) {
		if ( function_exists( 'get_block_wrapper_attributes' ) ) {
			return sprintf( '<div %s>%s</div>', get_block_wrapper_attributes( $extra ), $inner );
		}
		$class = isset( $extra['class'] ) ? $extra['class'] : '';
		return sprintf( '<div class="%s">%s</div>', esc_attr( $class ), $inner );
	}

	/**
	 * Render callback for the buzzsprout/player block.
	 */
	public static function render_player_block( $attributes ) {
		$mode       = isset( $attributes['mode'] ) ? $attributes['mode'] : 'latest';
		$episode_id = isset( $attributes['episodeId'] ) ? $attributes['episodeId'] : '';

		if ( 'latest' === $mode ) {
			$episodes = Buzzsprout_Feed::get_episodes( 1 );
			if ( is_wp_error( $episodes ) ) {
				return self::placeholder( $episodes->get_error_message() );
			}
			if ( empty( $episodes ) ) {
				return self::placeholder( __( 'No episodes found in the Buzzsprout feed yet.', 'buzzsprout-podcasting' ) );
			}
			$episode_id = $episodes[0]['id'];
		}

		if ( ! $episode_id ) {
			return self::placeholder( __( 'Choose an episode in the block settings.', 'buzzsprout-podcasting' ) );
		}

		$embed = self::player_embed( $episode_id );
		if ( ! $embed ) {
			return self::placeholder( __( 'No valid Buzzsprout feed URL is configured.', 'buzzsprout-podcasting' ) );
		}

		return self::block_wrap( $embed );
	}

	/**
	 * Render callback for the buzzsprout/episode-list block.
	 */
	public static function render_episode_list_block( $attributes ) {
		$display = isset( $attributes['display'] ) ? $attributes['display'] : 'playlist';
		$tags    = isset( $attributes['tags'] ) ? (string) $attributes['tags'] : '';

		if ( 'playlist' === $display ) {
			$playlist_count = isset( $attributes['playlistCount'] ) ? (int) $attributes['playlistCount'] : 0;
			$embed          = self::playlist_embed( $playlist_count, $tags );
			if ( ! $embed ) {
				return self::placeholder( __( 'No valid Buzzsprout feed URL is configured.', 'buzzsprout-podcasting' ) );
			}
			return self::block_wrap( $embed, array( 'class' => 'buzzsprout-playlist' ) );
		}

		$count          = isset( $attributes['count'] ) ? max( 1, min( 20, (int) $attributes['count'] ) ) : 5;
		$show_dates     = ! isset( $attributes['showDates'] ) || $attributes['showDates'];
		$show_durations = ! isset( $attributes['showDurations'] ) || $attributes['showDurations'];

		$episodes = Buzzsprout_Feed::get_episodes( $count, self::parse_tags( $tags ) );
		if ( is_wp_error( $episodes ) ) {
			return self::placeholder( $episodes->get_error_message() );
		}
		if ( empty( $episodes ) ) {
			return self::placeholder( __( 'No episodes found in the Buzzsprout feed yet.', 'buzzsprout-podcasting' ) );
		}

		$podcast_id = Buzzsprout_Feed::get_podcast_id();
		$items      = '';
		foreach ( $episodes as $episode ) {
			$meta_parts = array();
			if ( $show_dates && $episode['date'] ) {
				$meta_parts[] = esc_html( date_i18n( get_option( 'date_format' ), strtotime( $episode['date'] ) ) );
			}
			if ( $show_durations ) {
				$duration = self::format_duration( $episode['duration'] );
				if ( $duration ) {
					$meta_parts[] = esc_html( $duration );
				}
			}
			$meta = $meta_parts
				? sprintf( '<span class="buzzsprout-episode-list__meta">%s</span>', implode( ' &middot; ', $meta_parts ) )
				: '';

			$items .= sprintf(
				'<li class="buzzsprout-episode-list__item">
					<button type="button" class="buzzsprout-episode-list__toggle" aria-expanded="false">
						<span class="buzzsprout-episode-list__name">%1$s</span>%2$s
					</button>
					<div class="buzzsprout-episode-list__player" hidden data-podcast-id="%3$s" data-episode-id="%4$s"></div>
				</li>',
				esc_html( $episode['title'] ),
				$meta,
				esc_attr( $podcast_id ),
				esc_attr( $episode['id'] )
			);
		}

		$list = sprintf( '<ul class="buzzsprout-episode-list__items">%s</ul>', $items );
		return self::block_wrap( $list, array( 'class' => 'buzzsprout-episode-list' ) );
	}
}
