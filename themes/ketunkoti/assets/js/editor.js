/**
 * Block editor tweaks for the theme.
 *
 * Plain script without a build step, so it uses the `wp` globals instead of
 * package imports.
 */
( function ( wp ) {
	/**
	 * Enables separate row and column gap controls for the Group block.
	 *
	 * Core Group only declares `blockGap: true`, which gives its Row, Stack and
	 * Grid variations a single gap value used for both axes. Declaring both
	 * axes shows the same split control that Columns and Gallery have. Mirrors
	 * `ketunkoti_group_block_gap_sides()` in functions.php.
	 *
	 * @param {Object} settings Block type settings.
	 * @param {string} name     Block type name.
	 * @return {Object} Filtered block type settings.
	 */
	function addGroupBlockGapSides( settings, name ) {
		if ( 'core/group' !== name ) {
			return settings;
		}

		return {
			...settings,
			supports: {
				...settings.supports,
				spacing: {
					...settings.supports?.spacing,
					blockGap: [ 'horizontal', 'vertical' ],
				},
			},
		};
	}

	/**
	 * Enables margin support for the Navigation block.
	 *
	 * Core Navigation declares no margin support, so it has no margin control.
	 * Mirrors `ketunkoti_navigation_margin_support()` in functions.php.
	 *
	 * @param {Object} settings Block type settings.
	 * @param {string} name     Block type name.
	 * @return {Object} Filtered block type settings.
	 */
	function addNavigationMarginSupport( settings, name ) {
		if ( 'core/navigation' !== name ) {
			return settings;
		}

		return {
			...settings,
			supports: {
				...settings.supports,
				spacing: {
					...settings.supports?.spacing,
					margin: true,
				},
			},
		};
	}

	wp.hooks.addFilter(
		'blocks.registerBlockType',
		'ketunkoti/group-block-gap-sides',
		addGroupBlockGapSides
	);

	wp.hooks.addFilter(
		'blocks.registerBlockType',
		'ketunkoti/navigation-margin-support',
		addNavigationMarginSupport
	);
} )( window.wp );
