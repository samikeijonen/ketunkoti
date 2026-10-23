<?php
/**
 * Title: Avajaiset: miksi listalle
 * Slug: ketunkoti/launch-reasons
 * Categories: ketunkoti_page
 * Viewport width: 1400
 * Description: Heading and three reason cards.
 *
 * @package Ketunkoti
 */

$ketunkoti_reasons = array(
	array(
		'title' => __( 'Ensimmäisenä apajilla', 'ketunkoti' ),
		'text'  => __( 'Uudet kohteet tulevat listalle ennen kuin kukaan muu kuulee niistä.', 'ketunkoti' ),
	),
	array(
		'title' => __( 'Ei nettiin, ei hälinää', 'ketunkoti' ),
		'text'  => __( 'Ei hintakilpailuja eikä satojen katsojien näyttöjä, vain sinä ja hyvä kohde.', 'ketunkoti' ),
	),
	array(
		'title' => __( 'Harvoin mutta hyvää', 'ketunkoti' ),
		'text'  => __( 'Postia tulee vain, kun on oikeasti asiaa. Kettu ei jaarittele.', 'ketunkoti' ),
	),
);

?>
<!-- wp:group {"align":"wide","style":{"spacing":{"margin":{"top":"var:preset|spacing|80"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignwide" style="margin-top:var(--wp--preset--spacing--80)">
	<!-- wp:heading {"style":{"typography":{"textAlign":"center"}}} -->
	<h2 class="wp-block-heading has-text-align-center"><?php echo esc_html__( 'Miksi listalle?', 'ketunkoti' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|50","margin":{"top":"var:preset|spacing|60"}}},"layout":{"type":"grid","columnCount":3,"minimumColumnWidth":"16rem"}} -->
	<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--60)">
		<?php foreach ( $ketunkoti_reasons as $ketunkoti_reason ) : ?>
		<!-- wp:group {"className":"launch-card","layout":{"type":"default"}} -->
		<div class="wp-block-group launch-card">
			<!-- wp:heading {"level":3} -->
			<h3 class="wp-block-heading"><?php echo esc_html( $ketunkoti_reason['title'] ); ?></h3>
			<!-- /wp:heading -->

			<!-- wp:paragraph -->
			<p><?php echo esc_html( $ketunkoti_reason['text'] ); ?></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->
		<?php endforeach; ?>
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
