<?php
/**
 * Title: Uusimmat löpinät
 * Slug: ketunkoti/latest-posts
 * Categories: query, posts
 * Viewport width: 1400
 * Description: The four newest articles as a text list: publish date in its own column, title and an arrow. A link to all articles sits next to the heading.
 *
 * @package Ketunkoti
 */

?>
<!-- wp:group {"align":"wide","style":{"spacing":{"margin":{"top":"var:preset|spacing|80"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide" style="margin-top:var(--wp--preset--spacing--80)">
	<!-- wp:query {"queryId":0,"query":{"perPage":4,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false},"align":"wide","layout":{"type":"default"}} -->
	<div class="wp-block-query alignwide">
		<!-- wp:group {"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|40"},"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
		<div class="wp-block-group" style="margin-bottom:var(--wp--preset--spacing--40)">
			<!-- wp:heading -->
			<h2 class="wp-block-heading"><?php esc_html_e( 'Uusimmat löpinät', 'ketunkoti' ); ?></h2>
			<!-- /wp:heading -->

			<!-- wp:paragraph -->
			<p><a href="<?php echo esc_url( home_url( '/lopinat/' ) ); ?>"><?php esc_html_e( 'Kaikki löpinät', 'ketunkoti' ); ?></a></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->

		<!-- wp:post-template {"style":{"spacing":{"blockGap":"0"},"border":{"top":{"color":"var:preset|color|contrast","style":"solid","width":"1px"}}},"layout":{"type":"default"}} -->
			<!-- wp:group {"className":"latest-posts__row","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40"},"blockGap":"var:preset|spacing|40"},"border":{"bottom":{"color":"var:preset|color|accent-6","style":"solid","width":"1px"}}},"layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"top"}} -->
				<div class="wp-block-group latest-posts__row" style="border-bottom-color:var(--wp--preset--color--accent-6);border-bottom-style:solid;border-bottom-width:1px;padding-top:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40)">
					<!-- wp:post-date {"format":"j.n.Y","textColor":"accent-4","fontSize":"small","style":{"layout":{"selfStretch":"fixed","flexSize":"7.5rem"}}} /-->

					<!-- wp:post-title {"level":3,"isLink":true,"fontSize":"large","fontFamily":"cormorant-garamond","style":{"layout":{"selfStretch":"fill","flexSize":null},"typography":{"lineHeight":"1.15"},"elements":{"link":{"color":{"text":"var:preset|color|contrast"},"typography":{"textDecoration":"none"}}}}} /-->

					<!-- wp:icon {"icon":"core/arrow-right","textColor":"accent-2","style":{"dimensions":{"width":"1.5rem"}}} /-->
				</div>
			<!-- /wp:group -->
		<!-- /wp:post-template -->
	</div>
	<!-- /wp:query -->
</div>
<!-- /wp:group -->
