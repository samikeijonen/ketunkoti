<?php
/**
 * Title: Avajaiset: millaisia kohteita
 * Slug: ketunkoti/launch-properties
 * Categories: ketunkoti_page
 * Viewport width: 1400
 * Description: Two columns: what the new-build investment apartments offer, as a checkmark list with a link to the signup form, and a photo.
 *
 * @package Ketunkoti
 */

$ketunkoti_benefits = array(
	__( 'positiivinen kassavirta', 'ketunkoti' ),
	__( 'noin 5&nbsp;% tuotto', 'ketunkoti' ),
	__( 'kilpailukykyinen taloyhtiölaina', 'ketunkoti' ),
	__( 'myyntihinnat alkaen 26&nbsp;000&nbsp;€', 'ketunkoti' ),
	__( 'loistava sijainti Lahden keskustan kupeessa', 'ketunkoti' ),
);

?>
<!-- wp:group {"align":"wide","style":{"spacing":{"margin":{"top":"var:preset|spacing|80"},"blockGap":"var:preset|spacing|60"}},"layout":{"type":"grid","columnCount":2,"minimumColumnWidth":"24rem"}} -->
<div class="wp-block-group alignwide" style="margin-top:var(--wp--preset--spacing--80)">
	<!-- wp:group {"layout":{"type":"default"}} -->
	<div class="wp-block-group">
		<!-- wp:heading -->
		<h2 class="wp-block-heading"><?php echo esc_html__( 'Millaisia kohteita kettu tuo?', 'ketunkoti' ); ?></h2>
		<!-- /wp:heading -->

		<!-- wp:paragraph -->
		<p><?php echo esc_html__( 'Etsitkö sijoitusasunnoksi uudiskohdetta, jossa on', 'ketunkoti' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:list {"className":"is-style-checkmark-list","style":{"spacing":{"padding":{"left":"1.25rem"}}}} -->
		<ul style="padding-left:1.25rem" class="wp-block-list is-style-checkmark-list">
			<?php foreach ( $ketunkoti_benefits as $ketunkoti_benefit ) : ?>
			<!-- wp:list-item -->
			<li><?php echo esc_html( $ketunkoti_benefit ); ?></li>
			<!-- /wp:list-item -->
			<?php endforeach; ?>
		</ul>
		<!-- /wp:list -->

		<!-- wp:buttons -->
		<div class="wp-block-buttons"><!-- wp:button -->
		<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#liity"><?php echo esc_html__( 'Tilaa Ketun kodit', 'ketunkoti' ); ?></a></div>
		<!-- /wp:button --></div>
		<!-- /wp:buttons -->
	</div>
	<!-- /wp:group -->

	<!-- wp:image {"id":152,"aspectRatio":"1","scale":"cover","sizeSlug":"large","linkDestination":"none"} -->
	<figure class="wp-block-image size-large"><img src="<?php echo esc_url( content_url( 'uploads/2026/09/A30-09-1024x576.webp' ) ); ?>" alt="" class="wp-image-152" style="aspect-ratio:1;object-fit:cover"/></figure>
	<!-- /wp:image -->
</div>
<!-- /wp:group -->
