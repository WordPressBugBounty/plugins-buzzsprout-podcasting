/* global wp */
( function () {
	'use strict';

	var registerBlockType = wp.blocks.registerBlockType;
	var el = wp.element.createElement;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var BlockControls = wp.blockEditor.BlockControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var RadioControl = wp.components.RadioControl;
	var RangeControl = wp.components.RangeControl;
	var SelectControl = wp.components.SelectControl;
	var TextControl = wp.components.TextControl;
	var ToggleControl = wp.components.ToggleControl;
	var ToolbarGroup = wp.components.ToolbarGroup;
	var ToolbarButton = wp.components.ToolbarButton;
	var Disabled = wp.components.Disabled;
	var ServerSideRender = wp.serverSideRender;
	var __ = wp.i18n.__;

	// The Buzzsprout bolt-and-leaves mark alongside list lines.
	var brandListIcon = el(
		'svg',
		{ viewBox: '0 0 808.68 808.68', xmlns: 'http://www.w3.org/2000/svg', 'aria-hidden': true },
		el(
			'g',
			{ transform: 'translate(0 182) scale(0.55)' },
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
		),
		el( 'rect', { x: 490, y: 270, width: 280, height: 52, rx: 26, fill: 'currentColor' } ),
		el( 'rect', { x: 490, y: 396, width: 280, height: 52, rx: 26, fill: 'currentColor' } ),
		el( 'rect', { x: 490, y: 522, width: 280, height: 52, rx: 26, fill: 'currentColor' } )
	);

	registerBlockType( 'buzzsprout/episode-list', {
		icon: brandListIcon,
		transforms: {
			from: [
				{
					type: 'block',
					blocks: [ 'buzzsprout/player' ],
					transform: function () {
						return wp.blocks.createBlock( 'buzzsprout/episode-list', {} );
					},
				},
			],
		},
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var isList = attributes.display === 'list';

			var tagsControl = el( TextControl, {
				key: 'tags',
				label: __( 'Filter by tags', 'buzzsprout-podcasting' ),
				help: __( 'Comma-separated. Only episodes with at least one of these tags are shown. Leave empty for all episodes.', 'buzzsprout-podcasting' ),
				value: attributes.tags,
				onChange: function ( tags ) {
					setAttributes( { tags: tags } );
				},
			} );

			var controls = [
				el( RadioControl, {
					key: 'display',
					label: __( 'Display as', 'buzzsprout-podcasting' ),
					selected: attributes.display,
					options: [
						{ label: __( 'Playlist player (multi-episode embed)', 'buzzsprout-podcasting' ), value: 'playlist' },
						{ label: __( 'Simple list (click to play)', 'buzzsprout-podcasting' ), value: 'list' },
					],
					onChange: function ( display ) {
						setAttributes( { display: display } );
					},
				} ),
			];

			if ( isList ) {
				controls.push(
					el( RangeControl, {
						key: 'count',
						label: __( 'Number of episodes', 'buzzsprout-podcasting' ),
						min: 1,
						max: 20,
						value: attributes.count,
						onChange: function ( count ) {
							setAttributes( { count: count } );
						},
					} ),
					el( ToggleControl, {
						key: 'dates',
						label: __( 'Show publish dates', 'buzzsprout-podcasting' ),
						checked: attributes.showDates,
						onChange: function ( showDates ) {
							setAttributes( { showDates: showDates } );
						},
					} ),
					el( ToggleControl, {
						key: 'durations',
						label: __( 'Show durations', 'buzzsprout-podcasting' ),
						checked: attributes.showDurations,
						onChange: function ( showDurations ) {
							setAttributes( { showDurations: showDurations } );
						},
					} ),
					tagsControl
				);
			} else {
				controls.push(
					el( SelectControl, {
						key: 'playlistCount',
						label: __( 'Number of episodes', 'buzzsprout-podcasting' ),
						value: String( attributes.playlistCount || 0 ),
						options: [
							{ label: __( 'All', 'buzzsprout-podcasting' ), value: '0' },
							{ label: __( '5 most recent', 'buzzsprout-podcasting' ), value: '5' },
							{ label: __( '10 most recent', 'buzzsprout-podcasting' ), value: '10' },
							{ label: __( '20 most recent', 'buzzsprout-podcasting' ), value: '20' },
						],
						onChange: function ( value ) {
							setAttributes( { playlistCount: parseInt( value, 10 ) || 0 } );
						},
					} ),
					tagsControl
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
						el( ToolbarButton, {
							icon: 'playlist-audio',
							label: __( 'Playlist player', 'buzzsprout-podcasting' ),
							isPressed: ! isList,
							onClick: function () {
								setAttributes( { display: 'playlist' } );
							},
						} ),
						el( ToolbarButton, {
							icon: 'list-view',
							label: __( 'Simple list (click to play)', 'buzzsprout-podcasting' ),
							isPressed: isList,
							onClick: function () {
								setAttributes( { display: 'list' } );
							},
						} )
					)
				),
				el(
					InspectorControls,
					{},
					el( PanelBody, { title: __( 'Episodes', 'buzzsprout-podcasting' ), initialOpen: true }, controls )
				),
				// Disabled makes the iframe preview inert so clicks select
				// the block instead of vanishing into the iframe.
				el(
					Disabled,
					{},
					el( ServerSideRender, {
						block: 'buzzsprout/episode-list',
						attributes: attributes,
					} )
				)
			);
		},
		save: function () {
			return null;
		},
	} );
} )();
