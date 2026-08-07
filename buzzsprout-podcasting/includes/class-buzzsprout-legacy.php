<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings page, [buzzsprout] shortcode, and the classic-editor media tab.
 *
 * The class name predates 2.0 and is kept for backward compatibility.
 */
class Buzzsprout_Podcasting {

	const PLUGIN_NAME = 'Buzzsprout Podcasting';
	const PLUGIN_SLUG = 'buzzsprout-podcasting';

	public static function initialize() {
		add_filter( 'media_upload_tabs', array( __CLASS__, 'register_media_tab' ) );
		add_action( 'media_upload_buzzsprout_podcasting', array( __CLASS__, 'add_media_tab' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_options_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
		add_action( 'admin_print_styles-media-upload-popup', array( __CLASS__, 'enqueue_media_tab_style' ) );
		add_action( 'admin_notices', array( __CLASS__, 'buzzsprout_admin_notice' ) );
		add_shortcode( 'buzzsprout', array( __CLASS__, 'buzzsprout_shortcode_handler' ) );
		add_filter(
			'plugin_action_links_' . plugin_basename( BUZZSPROUT_PODCASTING_FILE ),
			array( __CLASS__, 'plugin_action_links' )
		);
	}

	public static function plugin_action_links( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=' . self::PLUGIN_SLUG ) ),
			esc_html__( 'Settings', 'buzzsprout-podcasting' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}

	public static function register_media_tab( $tabs ) {
		$new_tab = array( 'buzzsprout_podcasting' => __( 'Buzzsprout Podcasting', 'buzzsprout-podcasting' ) );
		return array_merge( $tabs, $new_tab );
	}

	public static function add_media_tab() {
		return wp_iframe( array( __CLASS__, 'media_tab_content' ) );
	}

	public static function enqueue_scripts() {
		wp_enqueue_style( 'buzzsprout-podcasting-admin', plugins_url( 'css/admin.css', BUZZSPROUT_PODCASTING_FILE ), false, BUZZSPROUT_PODCASTING_VERSION );
		wp_enqueue_script( 'buzzsprout-podcasting-admin', plugins_url( 'js/admin-onload.js', BUZZSPROUT_PODCASTING_FILE ), array( 'jquery', 'media-upload' ), BUZZSPROUT_PODCASTING_VERSION );
	}

	public static function enqueue_media_tab_style() {
		wp_enqueue_style( 'buzzsprout-podcasting-admin', plugins_url( 'css/admin.css', BUZZSPROUT_PODCASTING_FILE ), false, BUZZSPROUT_PODCASTING_VERSION );
		wp_enqueue_script( 'buzzsprout-podcasting-box', plugins_url( 'js/box.js', BUZZSPROUT_PODCASTING_FILE ), array( 'jquery' ), BUZZSPROUT_PODCASTING_VERSION );
	}

	/**
	 * Episode picker inside the classic editor's media popup.
	 */
	public static function media_tab_content() {
		media_upload_header();
		$settings = Buzzsprout_Feed::get_settings(); ?>
		<div class="box">
			<div style="float:right;">
				<p style="margin-top: 0;"><strong><?php esc_html_e( 'Enjoying the Buzzsprout Plugin?', 'buzzsprout-podcasting' ); ?></strong><br>
				<?php
				printf(
					/* translators: %s: link to the reviews page */
					esc_html__( 'Your %s are much appreciated!', 'buzzsprout-podcasting' ),
					'<a href="https://wordpress.org/support/plugin/buzzsprout-podcasting/reviews/" target="_blank">' . esc_html__( 'ratings and reviews', 'buzzsprout-podcasting' ) . '</a>'
				);
				?>
				</p>
			</div>
		<?php if ( ! Buzzsprout_Feed::is_feed_valid( $settings['feed-uri'] ) ) : ?>
			<p class="major-info error">
				<?php
				printf(
					/* translators: %s: link to the settings page */
					esc_html__( 'A valid Buzzsprout feed URL has not been configured yet. Please add one on the %s.', 'buzzsprout-podcasting' ),
					'<a href="' . esc_url( admin_url( 'options-general.php?page=buzzsprout-podcasting' ) ) . '" target="_blank">' . esc_html__( 'Settings page', 'buzzsprout-podcasting' ) . '</a>'
				);
				?>
			</p>
		<?php else :
			$episodes = Buzzsprout_Feed::get_episodes(); ?>
			<h2><?php esc_html_e( 'Select an Episode', 'buzzsprout-podcasting' ); ?></h2>
			<ul>
			<?php if ( is_wp_error( $episodes ) || empty( $episodes ) ) : ?>
				<li class="error"><?php esc_html_e( 'No feed items can be retrieved.', 'buzzsprout-podcasting' ); ?></li>
			<?php else : ?>
			<?php foreach ( $episodes as $episode ) : ?>
				<li>
					<a class="buzzp-item" href="#"
						title="<?php echo esc_attr__( 'Click to add this episode into the post', 'buzzsprout-podcasting' ); ?>"
						data-short-tag="<?php echo esc_attr( self::build_short_tag( $episode['id'], ! empty( $settings['include-flash'] ) ) ); ?>"><?php echo esc_html( $episode['title'] ); ?></a>
				</li>
			<?php endforeach; ?>
			<?php endif; ?>
			</ul>
		<?php endif; ?>
		</div>
		<?php
	}

	public static function add_options_page() {
		add_options_page( self::PLUGIN_NAME, self::PLUGIN_NAME, 'manage_options', self::PLUGIN_SLUG, array( __CLASS__, 'options_page_content' ) );
	}

	public static function register_settings() {
		register_setting( self::PLUGIN_SLUG, self::PLUGIN_SLUG, array( __CLASS__, 'buzzsprout_options_validate' ) );
		add_settings_section( 'buzzsprout_settings', __( 'Buzzsprout Settings', 'buzzsprout-podcasting' ), '__return_empty_string', self::PLUGIN_SLUG );
		add_settings_field( 'buzzsprout_feed_address', __( 'Buzzsprout feed address (URL)', 'buzzsprout-podcasting' ), array( __CLASS__, 'buzzsprout_feed_address_cb' ), self::PLUGIN_SLUG, 'buzzsprout_settings' );
		add_settings_field( 'buzzsprout_include_player', __( 'Show audio player by default?', 'buzzsprout-podcasting' ), array( __CLASS__, 'buzzsprout_include_player_cb' ), self::PLUGIN_SLUG, 'buzzsprout_settings' );
		add_settings_field( 'buzzsprout_number_episodes', __( 'Number of episodes to show', 'buzzsprout-podcasting' ), array( __CLASS__, 'buzzsprout_number_episodes_cb' ), self::PLUGIN_SLUG, 'buzzsprout_settings' );
	}

	public static function options_page_content() { ?>
	<div class="wrap buzzp">
		<h1><?php esc_html_e( 'Buzzsprout Podcasting', 'buzzsprout-podcasting' ); ?></h1>
		<p><?php esc_html_e( 'Connect your Buzzsprout show by pasting your RSS feed URL below. Once connected, use the Buzzsprout Player and Buzzsprout Episode List blocks to embed episodes anywhere on your site.', 'buzzsprout-podcasting' ); ?></p>
		<p>
			<?php
			printf(
				/* translators: %s: link to buzzsprout.com */
				esc_html__( 'New to Buzzsprout? Learn more and create your account at %s.', 'buzzsprout-podcasting' ),
				'<a href="https://www.buzzsprout.com" target="_blank">buzzsprout.com</a>'
			);
			?>
		</p>
		<form action="options.php" method="post">
			<?php settings_fields( self::PLUGIN_SLUG ); ?>
			<?php do_settings_sections( self::PLUGIN_SLUG ); ?>
			<?php submit_button(); ?>
		</form>

		<h2><?php esc_html_e( 'How it works', 'buzzsprout-podcasting' ); ?></h2>
		<p class="how-it-works">
			<?php
			printf(
				/* translators: %s: link to the Buzzsprout help section */
				esc_html__( 'Episodes are read from your feed and cached for 15 minutes. See the %s for instructions, screenshots, and videos.', 'buzzsprout-podcasting' ),
				'<a href="https://www.buzzsprout.com/help/27" target="_blank"><strong>' . esc_html__( 'WP Plugin Help Section', 'buzzsprout-podcasting' ) . '</strong></a>'
			);
			?>
		</p>
	</div>
	<?php
	}

	public static function buzzsprout_options_validate( $input ) {
		$new_input                    = array();
		$new_input['feed-uri']        = isset( $input['feed-uri'] ) ? esc_url_raw( trim( (string) $input['feed-uri'] ) ) : '';
		$new_input['include-flash']   = isset( $input['include-flash'] ) && 'on' === $input['include-flash'];
		$new_input['number-episodes'] = isset( $input['number-episodes'] ) ? absint( $input['number-episodes'] ) : 5;
		return $new_input;
	}

	public static function buzzsprout_feed_address_cb() {
		$settings = Buzzsprout_Feed::get_settings();
		?>
		<input style="width: 300px" type="url" name="<?php echo esc_attr( self::PLUGIN_SLUG . '[feed-uri]' ); ?>" value="<?php echo esc_attr( $settings['feed-uri'] ); ?>" placeholder="https://feeds.buzzsprout.com/123456.rss" />
		<span class="guide">
			<?php
			printf(
				/* translators: %s: link to the Buzzsprout login page */
				esc_html__( '%s, then find your RSS feed under Directories.', 'buzzsprout-podcasting' ),
				'<a href="https://www.buzzsprout.com/login" target="_blank">' . esc_html__( 'Log in to your account', 'buzzsprout-podcasting' ) . '</a>'
			);
			?>
		</span>
		<?php
	}

	public static function buzzsprout_include_player_cb() {
		$settings = Buzzsprout_Feed::get_settings(); ?>
		<input type="checkbox" name="<?php echo esc_attr( self::PLUGIN_SLUG . '[include-flash]' ); ?>" <?php checked( $settings['include-flash'] ); ?> /> <?php esc_html_e( 'Yes', 'buzzsprout-podcasting' ); ?>
		<?php
	}

	public static function buzzsprout_number_episodes_cb() {
		$settings = Buzzsprout_Feed::get_settings(); ?>
		<p>
			<select name="<?php echo esc_attr( self::PLUGIN_SLUG . '[number-episodes]' ); ?>">
			<?php
			for ( $i = 5; $i < 21; $i += 5 ) {
				printf( '<option value="%1$s"%2$s>%1$s</option>%3$s', (int) $i, selected( $settings['number-episodes'], $i, false ), PHP_EOL );
			}
			printf( '<option value="9999"%s>%s</option>%s', selected( $settings['number-episodes'], 9999, false ), esc_html__( 'All', 'buzzsprout-podcasting' ), PHP_EOL );
			?>
			</select>
			<br class="clear" />
		</p>
		<?php
	}

	/**
	 * Kept for backward compatibility with 1.x.
	 */
	public static function is_feed_valid( $url ) {
		return Buzzsprout_Feed::is_feed_valid( $url );
	}

	/**
	 * Kept for backward compatibility with 1.x.
	 */
	public static function get_subscription_id( $feed_uri = false ) {
		return Buzzsprout_Feed::get_podcast_id( $feed_uri );
	}

	/**
	 * Handles the [buzzsprout] shortcode.
	 */
	public static function buzzsprout_shortcode_handler( $atts ) {
		$atts = shortcode_atts(
			array(
				'episode' => 0,
				'player'  => 'true',
			),
			$atts
		);

		return Buzzsprout_Render::player_embed( $atts['episode'], 'false' !== $atts['player'] );
	}

	public static function build_short_tag( $episode_id, $player = true ) {
		return sprintf( "[buzzsprout episode='%s' player='%s']", sanitize_key( $episode_id ), $player ? 'true' : 'false' );
	}

	/**
	 * Kept for backward compatibility with 1.x: builds the shortcode from an
	 * episode media URL.
	 */
	public static function buzzsprout_item_create_short_tag( $buzz_item_link, $player = null ) {
		$episode_id = Buzzsprout_Feed::episode_id_from_media_url( $buzz_item_link );
		if ( ! $episode_id ) {
			return false;
		}
		return self::build_short_tag( $episode_id, null === $player ? true : (bool) $player );
	}

	/**
	 * Validates the feed after the settings form saves and surfaces the result.
	 */
	public static function buzzsprout_admin_notice() {
		global $pagenow;
		if ( 'options-general.php' !== $pagenow || ! isset( $_GET['page'] ) || self::PLUGIN_SLUG !== $_GET['page'] ) {
			return;
		}
		$updated = ( isset( $_GET['updated'] ) && 'true' === $_GET['updated'] )
			|| ( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] );
		if ( ! $updated ) {
			return;
		}

		$settings = Buzzsprout_Feed::get_settings();
		if ( $settings['feed-uri'] && ! Buzzsprout_Feed::is_feed_valid( $settings['feed-uri'] ) ) {
			$settings['feed-uri'] = '';
			update_option( self::PLUGIN_SLUG, $settings );
			add_settings_error( 'general', 'settings_updated', __( 'Invalid Buzzsprout Feed URL', 'buzzsprout-podcasting' ), 'error' );
		}
	}
}
