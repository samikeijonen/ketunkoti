<?php
/**
 * Title: Avajaiset: loppukehotus
 * Slug: ketunkoti/launch-cta
 * Categories: ketunkoti_page
 * Viewport width: 1400
 * Description: Link for home buyers, then a closing call to action that jumps back to the hero signup form.
 *
 * @package Ketunkoti
 */

?>
<!-- wp:group {"style":{"spacing":{"margin":{"top":"var:preset|spacing|70"}}},"layout":{"type":"constrained","contentSize":"44rem"}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--70)">
	<!-- wp:paragraph {"style":{"typography":{"textAlign":"center"}}} -->
	<p class="has-text-align-center"><?php echo esc_html__( 'Etsitkö omaa kotia etkä sijoitusta?', 'ketunkoti' ); ?> <a href="<?php echo esc_url( home_url( '/myytavat-kodit/' ) ); ?>"><?php echo esc_html__( 'Katso myytävät kodit', 'ketunkoti' ); ?></a></p>
	<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","className":"launch-cta","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|80"},"margin":{"top":"var:preset|spacing|70"}}},"backgroundColor":"accent-5","layout":{"type":"constrained","contentSize":"44rem"}} -->
<div class="wp-block-group alignfull launch-cta has-accent-5-background-color has-background" style="margin-top:var(--wp--preset--spacing--70);padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--80)">
	<!-- wp:heading {"style":{"typography":{"textAlign":"center"}}} -->
	<h2 class="wp-block-heading has-text-align-center"><?php echo esc_html__( 'Vieläkö mietit? Kettu odottaa kärsivällisesti.', 'ketunkoti' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
	<div class="wp-block-buttons"><!-- wp:button -->
	<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#liity"><?php echo esc_html__( 'Tilaa Ketun kodit', 'ketunkoti' ); ?></a></div>
	<!-- /wp:button --></div>
	<!-- /wp:buttons -->
</div>
<!-- /wp:group -->
