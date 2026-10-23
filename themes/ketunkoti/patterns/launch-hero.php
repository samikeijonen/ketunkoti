<?php
/**
 * Title: Avajaiset: yläosa ja tilauslomake
 * Slug: ketunkoti/launch-hero
 * Categories: ketunkoti_page
 * Viewport width: 1400
 * Description: Centred launch hero with heading, lead and the signup form.
 *
 * @package Ketunkoti
 */

?>
<!-- wp:group {"anchor":"liity","align":"full","className":"launch-hero","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|70"}}},"layout":{"type":"constrained","contentSize":"44rem"}} -->
<div id="liity" class="wp-block-group alignfull launch-hero" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--70)">
	<!-- wp:heading {"level":1,"style":{"typography":{"textAlign":"center"}}} -->
	<h1 class="wp-block-heading has-text-align-center"><?php echo wp_kses( __( 'Uudet sijoitusasuntomme myydään <em>ensin</em> Ketun kodit -listalle', 'ketunkoti' ), array( 'em' => array() ) ); ?></h1>
	<!-- /wp:heading -->

	<!-- wp:paragraph {"style":{"typography":{"textAlign":"center"}},"fontSize":"medium"} -->
	<p class="has-text-align-center has-medium-font-size"><?php echo esc_html__( 'Etsitkö uudiskohteita positiivisella kassavirralla, noin 5&nbsp;% tuotolla ja hinnoilla alkaen 26&nbsp;000&nbsp;€ Lahden keskustan kupeesta? Laitappa Ketun kodit tilaukseen, niin ilmoitellaan, kun kohteet tulevat myyntiin.', 'ketunkoti' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:group {"className":"launch-signup is-centered","style":{"spacing":{"margin":{"top":"var:preset|spacing|60"}}},"layout":{"type":"default"}} -->
	<div class="wp-block-group launch-signup is-centered" style="margin-top:var(--wp--preset--spacing--60)">
		<!-- wp:block {"ref":46} /-->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
