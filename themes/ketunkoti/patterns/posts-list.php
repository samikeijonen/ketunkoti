<?php
/**
 * Title: Artikkelilistaus
 * Slug: ketunkoti/posts-list
 * Categories: query, posts
 * Viewport width: 1400
 * Description: The ten newest articles as a text list with pagination. Same row design as the latest posts pattern, for pages like /lopinat/.
 *
 * @package Ketunkoti
 */

?>
<!-- wp:group {"align":"wide","style":{"spacing":{"margin":{"top":"var:preset|spacing|50"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignwide" style="margin-top:var(--wp--preset--spacing--50)">
	<!-- wp:query {"queryId":0,"query":{"perPage":10,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false},"layout":{"type":"default"}} -->
	<div class="wp-block-query">
		<!-- wp:post-template {"style":{"spacing":{"blockGap":"0"},"border":{"top":{"color":"var:preset|color|contrast","style":"solid","width":"1px"}}},"layout":{"type":"default"}} -->
			<!-- wp:group {"className":"latest-posts__row","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40"},"blockGap":"var:preset|spacing|40"},"border":{"bottom":{"color":"var:preset|color|accent-6","style":"solid","width":"1px"}}},"layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"top"}} -->
				<div class="wp-block-group latest-posts__row" style="border-bottom-color:var(--wp--preset--color--accent-6);border-bottom-style:solid;border-bottom-width:1px;padding-top:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40)">
					<!-- wp:post-date {"format":"j.n.Y","textColor":"accent-4","fontSize":"small","style":{"layout":{"selfStretch":"fixed","flexSize":"7.5rem"}}} /-->

					<!-- wp:post-title {"level":2,"isLink":true,"fontSize":"large","fontFamily":"cormorant-garamond","style":{"layout":{"selfStretch":"fill","flexSize":null},"typography":{"lineHeight":"1.15"},"elements":{"link":{"color":{"text":"var:preset|color|contrast"},"typography":{"textDecoration":"none"}}}}} /-->

					<!-- wp:icon {"icon":"core/arrow-right","textColor":"accent-2","style":{"dimensions":{"width":"1.5rem"}}} /-->
				</div>
			<!-- /wp:group -->
		<!-- /wp:post-template -->

		<!-- wp:query-pagination {"paginationArrow":"arrow","style":{"spacing":{"margin":{"top":"var:preset|spacing|50"}}},"layout":{"type":"flex","justifyContent":"left"}} -->
			<!-- wp:query-pagination-previous /-->
			<!-- wp:query-pagination-numbers /-->
			<!-- wp:query-pagination-next /-->
		<!-- /wp:query-pagination -->

		<!-- wp:query-no-results -->
			<!-- wp:paragraph -->
			<p><?php esc_html_e( 'Täältä ei löytynyt artikkeleita.', 'ketunkoti' ); ?></p>
			<!-- /wp:paragraph -->
		<!-- /wp:query-no-results -->
	</div>
	<!-- /wp:query -->
</div>
<!-- /wp:group -->
