/* Expand/collapse inline players in the Buzzsprout Episode List block. */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		var toggle = event.target.closest( '.buzzsprout-episode-list__toggle' );
		if ( ! toggle ) {
			return;
		}

		var item = toggle.closest( '.buzzsprout-episode-list__item' );
		var panel = item && item.querySelector( '.buzzsprout-episode-list__player' );
		if ( ! panel ) {
			return;
		}

		var expanded = toggle.getAttribute( 'aria-expanded' ) === 'true';
		toggle.setAttribute( 'aria-expanded', expanded ? 'false' : 'true' );
		panel.hidden = expanded;

		// Load the Buzzsprout embed the first time this episode is expanded.
		if ( ! expanded && ! panel.dataset.loaded ) {
			panel.dataset.loaded = '1';
			var podcastId = panel.dataset.podcastId;
			var episodeId = panel.dataset.episodeId;
			if ( ! /^[0-9]+$/.test( podcastId ) || ! /^[0-9]+$/.test( episodeId ) ) {
				return;
			}
			// Unique per expansion: the same episode may already be embedded
			// elsewhere on the page (e.g. a Player block), and the embed
			// script targets its container by document.getElementById.
			var containerId =
				'buzzsprout-inline-' + episodeId + '-' + Math.random().toString( 36 ).slice( 2, 8 );
			var container = document.createElement( 'div' );
			container.id = containerId;
			panel.appendChild( container );

			var script = document.createElement( 'script' );
			script.src =
				'https://www.buzzsprout.com/' +
				podcastId +
				'/episodes/' +
				episodeId +
				'.js?container_id=' +
				containerId +
				'&player=small';
			script.charset = 'utf-8';
			panel.appendChild( script );
		}
	} );
} )();
