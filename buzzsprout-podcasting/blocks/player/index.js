/* global wp */
( function () {
	'use strict';

	var registerBlockType = wp.blocks.registerBlockType;
	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var BlockControls = wp.blockEditor.BlockControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var ToolbarGroup = wp.components.ToolbarGroup;
	var ToolbarButton = wp.components.ToolbarButton;
	var RadioControl = wp.components.RadioControl;
	var ComboboxControl = wp.components.ComboboxControl;
	var Spinner = wp.components.Spinner;
	var Notice = wp.components.Notice;
	var Disabled = wp.components.Disabled;
	var Placeholder = wp.components.Placeholder;
	var Button = wp.components.Button;
	var ServerSideRender = wp.serverSideRender;
	var apiFetch = wp.apiFetch;
	var __ = wp.i18n.__;

	// The Buzzsprout bolt-and-leaves mark.
	var brandIcon = el(
		'svg',
		{ viewBox: '0 0 808.68 808.68', xmlns: 'http://www.w3.org/2000/svg', 'aria-hidden': true },
		el( 'path', {
			fill: '#71e569',
			d: 'm404.27,226.27l.35-211.83h-5.24l-216.26,404.06h221.27l-.12,210.55h4.68l216.72-402.78h-221.39Z',
		} ),
		el( 'path', {
			fill: '#214538',
			d: 'm288.12,534.51c-67.85-67.87-158.1-105.28-254.04-105.4h-5.71l.23,5.71c4.08,94.08,43.03,182.69,109.83,249.38,66.68,66.68,155.28,105.73,249.36,109.81l5.71.23v-5.71c0-95.94-37.53-186.17-105.38-254.02Z',
		} ),
		el( 'path', {
			fill: '#214538',
			d: 'm774.6,429.11c-95.94,0-186.17,37.53-254.04,105.4-67.85,67.85-105.26,158.08-105.38,254.02v5.71l5.71-.23c94.08-4.08,182.69-43.01,249.36-109.81,66.69-66.69,105.75-155.3,109.83-249.38l.23-5.71h-5.71Z',
		} )
	);

	function useEpisodes() {
		var state = useState( { status: 'loading', episodes: [], error: '' } );
		var value = state[ 0 ];
		var setValue = state[ 1 ];
		var attemptState = useState( 0 );
		var attempt = attemptState[ 0 ];
		var setAttempt = attemptState[ 1 ];

		useEffect( function () {
			var cancelled = false;
			setValue( { status: 'loading', episodes: [], error: '' } );
			apiFetch( { path: '/buzzsprout/v1/episodes' } )
				.then( function ( episodes ) {
					if ( ! cancelled ) {
						setValue( { status: 'ready', episodes: episodes, error: '' } );
					}
				} )
				.catch( function ( err ) {
					if ( ! cancelled ) {
						setValue( {
							status: 'error',
							episodes: [],
							error: ( err && err.message ) || __( 'Could not load episodes.', 'buzzsprout-podcasting' ),
						} );
					}
				} );
			return function () {
				cancelled = true;
			};
		}, [ attempt ] );

		return {
			status: value.status,
			episodes: value.episodes,
			error: value.error,
			retry: function () {
				setAttempt( function ( n ) {
					return n + 1;
				} );
			},
		};
	}

	registerBlockType( 'buzzsprout/player', {
		icon: brandIcon,
		transforms: {
			from: [
				// Pasting a legacy [buzzsprout] shortcode becomes a Player block.
				{
					type: 'shortcode',
					tag: 'buzzsprout',
					attributes: {
						mode: {
							type: 'string',
							shortcode: function () {
								return 'episode';
							},
						},
						episodeId: {
							type: 'string',
							shortcode: function ( attrs ) {
								return String( attrs.named.episode || '' );
							},
						},
					},
				},
				{
					type: 'block',
					blocks: [ 'buzzsprout/episode-list' ],
					transform: function () {
						return wp.blocks.createBlock( 'buzzsprout/player', { mode: 'latest' } );
					},
				},
			],
		},
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var feed = useEpisodes();

			var episodeOptions = feed.episodes.map( function ( episode ) {
				return {
					value: episode.id,
					label: episode.title + ( episode.date ? ' (' + episode.date + ')' : '' ),
				};
			} );

			function episodePicker( key ) {
				if ( feed.status === 'loading' ) {
					return el( Spinner, { key: key } );
				}
				if ( feed.status === 'error' ) {
					return el(
						'div',
						{ key: key },
						el( Notice, { status: 'warning', isDismissible: false }, feed.error ),
						el(
							Button,
							{ variant: 'secondary', onClick: feed.retry, style: { marginTop: '8px' } },
							__( 'Try again', 'buzzsprout-podcasting' )
						)
					);
				}
				return el( ComboboxControl, {
					key: key,
					label: __( 'Search episodes', 'buzzsprout-podcasting' ),
					value: attributes.episodeId,
					options: episodeOptions,
					onChange: function ( episodeId ) {
						var chosen = feed.episodes.find( function ( episode ) {
							return episode.id === episodeId;
						} );
						setAttributes( {
							episodeId: episodeId || '',
							episodeTitle: chosen ? chosen.title : '',
						} );
					},
				} );
			}

			var controls = [
				el( RadioControl, {
					key: 'mode',
					label: __( 'Episode', 'buzzsprout-podcasting' ),
					selected: attributes.mode,
					options: [
						{ label: __( 'Always play the latest episode', 'buzzsprout-podcasting' ), value: 'latest' },
						{ label: __( 'A specific episode', 'buzzsprout-podcasting' ), value: 'episode' },
					],
					onChange: function ( mode ) {
						setAttributes( { mode: mode } );
					},
				} ),
			];

			if ( attributes.mode === 'episode' ) {
				controls.push( episodePicker( 'sidebar-picker' ) );
			}

			// With no episode chosen yet, show the picker in the block canvas
			// itself instead of a preview that points at the sidebar.
			var preview;
			if ( attributes.mode === 'episode' && ! attributes.episodeId ) {
				preview = el(
					Placeholder,
					{
						icon: brandIcon,
						label: __( 'Buzzsprout Player', 'buzzsprout-podcasting' ),
						instructions: __( 'Search for the episode to embed.', 'buzzsprout-podcasting' ),
					},
					el( 'div', { style: { width: '100%' } }, episodePicker( 'inline-picker' ) )
				);
			} else {
				preview = el(
					Disabled,
					{},
					el( ServerSideRender, {
						block: 'buzzsprout/player',
						attributes: attributes,
					} )
				);
			}

			return el(
				'div',
				useBlockProps(),
				el(
					BlockControls,
					{},
					el(
						ToolbarGroup,
						{},
						el(
							ToolbarButton,
							{
								label: __( 'Always play the latest episode', 'buzzsprout-podcasting' ),
								isPressed: attributes.mode === 'latest',
								onClick: function () {
									setAttributes( { mode: 'latest' } );
								},
							},
							__( 'Latest', 'buzzsprout-podcasting' )
						),
						el(
							ToolbarButton,
							{
								label: __( 'Choose a specific episode', 'buzzsprout-podcasting' ),
								isPressed: attributes.mode === 'episode',
								onClick: function () {
									setAttributes( { mode: 'episode' } );
								},
							},
							__( 'Specific', 'buzzsprout-podcasting' )
						)
					)
				),
				el(
					InspectorControls,
					{},
					el( PanelBody, { title: __( 'Episode', 'buzzsprout-podcasting' ), initialOpen: true }, controls )
				),
				preview
			);
		},
		save: function () {
			return null;
		},
	} );
} )();
