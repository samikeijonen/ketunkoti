<?php
/**
 * Title: Kodit-ruudukko
 * Slug: ketunkoti/homes-grid
 * Categories: query, posts
 * Block Types: core/query
 * Viewport width: 1400
 * Description: A grid of home cards showing the featured image, location, title, living area and selling price.
 *
 * @package Ketunkoti
 */

?>
<!-- wp:query {"queryId":0,"query":{"perPage":12,"pages":0,"offset":0,"postType":"home","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false},"align":"wide","layout":{"type":"default"}} -->
<div class="wp-block-query alignwide">
	<!-- wp:post-template {"style":{"spacing":{"blockGap":"var:preset|spacing|50"}},"layout":{"type":"grid","minimumColumnWidth":"22rem"}} -->
		<!-- wp:group {"className":"home-card","style":{"spacing":{"blockGap":"0"},"border":{"width":"1px","radius":"6px"},"dimensions":{"minHeight":"100%"}},"borderColor":"accent-6","backgroundColor":"accent-5","layout":{"type":"flex","orientation":"vertical","justifyContent":"stretch"}} -->
		<div class="wp-block-group home-card has-border-color has-accent-6-border-color has-accent-5-background-color has-background" style="border-width:1px;border-radius:6px;min-height:100%">
			<!-- wp:post-featured-image {"isLink":false,"aspectRatio":"4/3","className":"home-card__image","style":{"spacing":{"margin":{"top":"0","bottom":"0"}}}} /-->

			<!-- wp:group {"className":"home-card__body","style":{"layout":{"selfStretch":"fill"},"spacing":{"blockGap":"var:preset|spacing|20","padding":{"top":"var:preset|spacing|40","right":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40"}}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"stretch"}} -->
			<div class="wp-block-group home-card__body" style="padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)">
				<!-- wp:paragraph {"className":"home-card__city","fontSize":"small","textColor":"accent-4","style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.08em"}},"metadata":{"bindings":{"content":{"source":"ketunkoti/home-details","args":{"key":"home_city"}}}}} -->
				<p class="home-card__city has-accent-4-color has-text-color has-small-font-size" style="text-transform:uppercase;letter-spacing:0.08em">&#8212;</p>
				<!-- /wp:paragraph -->

				<!-- wp:post-title {"isLink":true,"className":"home-card__title","fontSize":"large","fontFamily":"cormorant-garamond","style":{"typography":{"lineHeight":"1.15"},"elements":{"link":{"color":{"text":"var:preset|color|contrast"},"typography":{"textDecoration":"none"}}}}} /-->

				<!-- wp:group {"className":"home-card__meta","style":{"spacing":{"blockGap":"var:preset|spacing|30","margin":{"top":"auto"},"padding":{"top":"var:preset|spacing|20"}},"border":{"top":{"color":"var:preset|color|accent-6","width":"1px"}}},"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
				<div class="wp-block-group home-card__meta" style="border-top-color:var(--wp--preset--color--accent-6);border-top-width:1px;margin-top:auto;padding-top:var(--wp--preset--spacing--20)">
					<!-- wp:paragraph {"className":"home-card__area","fontSize":"small","textColor":"accent-4","metadata":{"bindings":{"content":{"source":"ketunkoti/home-details","args":{"key":"home_area"}}}}} -->
					<p class="home-card__area has-accent-4-color has-text-color has-small-font-size">&#8212;</p>
					<!-- /wp:paragraph -->

					<!-- wp:paragraph {"className":"home-card__price","fontSize":"medium","fontFamily":"cormorant-garamond","style":{"typography":{"fontWeight":"600"}},"metadata":{"bindings":{"content":{"source":"ketunkoti/home-details","args":{"key":"home_selling_price"}}}}} -->
					<p class="home-card__price has-cormorant-garamond-font-family has-medium-font-size" style="font-weight:600">&#8212;</p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->
	<!-- /wp:post-template -->

	<!-- wp:query-no-results -->
		<!-- wp:paragraph -->
		<p><?php esc_html_e( 'Ei koteja näytettäväksi.', 'ketunkoti' ); ?></p>
		<!-- /wp:paragraph -->
	<!-- /wp:query-no-results -->

	<!-- wp:query-pagination {"align":"wide","layout":{"type":"flex","justifyContent":"space-between"},"style":{"spacing":{"margin":{"top":"var:preset|spacing|60"}}}} -->
		<!-- wp:query-pagination-previous /-->
		<!-- wp:query-pagination-numbers /-->
		<!-- wp:query-pagination-next /-->
	<!-- /wp:query-pagination -->
</div>
<!-- /wp:query -->
