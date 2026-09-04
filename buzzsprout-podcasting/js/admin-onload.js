/**
 * Called from the legacy media tab (js/box.js, inside the media modal's
 * iframe) when an episode is clicked.
 */
function buzzsproutPickHandler( linkElement ) {
	var tag = jQuery( linkElement ).attr( 'data-short-tag' );
	if ( ! tag ) {
		return;
	}

	if ( typeof window.send_to_editor === 'function' ) {
		window.send_to_editor( tag );
	}

	// send_to_editor() only knows how to close a Thickbox. The legacy tab
	// lives inside the media modal, which otherwise stays open with no
	// feedback, so users assume nothing was inserted.
	if ( window.wp && wp.media && wp.media.frame && typeof wp.media.frame.close === 'function' ) {
		wp.media.frame.close();
	}
}
