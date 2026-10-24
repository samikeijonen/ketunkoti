/**
 * Pause/play toggle for Cover blocks using the "Video text" style.
 *
 * The button markup is added by `ketunkoti_videotext_toggle()` in
 * functions.php. Visitors who prefer reduced motion get the video paused on
 * load, and can still start it with the button.
 */
( function () {
	const reducedMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' );

	document.querySelectorAll( '.js-videotext-toggle' ).forEach( ( button ) => {
		const video = button
			.closest( '.wp-block-cover' )
			?.querySelector( '.wp-block-cover__video-background' );

		if ( ! video ) {
			return;
		}

		/**
		 * Syncs the button icon and label with the video state.
		 */
		function updateButton() {
			button.classList.toggle( 'is-paused', video.paused );
			button.setAttribute(
				'aria-label',
				video.paused ? button.dataset.labelPlay : button.dataset.labelPause
			);
		}

		button.addEventListener( 'click', () => {
			if ( video.paused ) {
				video.play().catch( updateButton );
			} else {
				video.pause();
			}
		} );

		video.addEventListener( 'play', updateButton );
		video.addEventListener( 'pause', updateButton );

		if ( reducedMotion.matches ) {
			video.removeAttribute( 'autoplay' );
			video.pause();
		}

		updateButton();
	} );
} )();
