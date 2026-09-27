<?php
/**
 * Title: Fox Ventures - Etusivu
 * Slug: foxventures/landing-page
 * Categories: ketunkoti_page
 * Keywords: etusivu, landing, catering, baarimestari
 * Post Types: page
 * Viewport width: 1400
 * Description: Landing page for Fox Ventures catering and bartending services: hero, services, why us, gallery, testimonials and contact.
 *
 * Images are left empty on purpose: add photos to the hero and quote band
 * covers, the two service cards and the gallery.
 *
 * @package Foxventures
 */

?>
<!-- wp:cover {"url":"<?php echo esc_url( get_theme_file_uri( 'assets/images/cocktail.webp' ) ); ?>","dimRatio":80,"overlayColor":"base","isUserOverlayColor":true,"minHeight":85,"minHeightUnit":"vh","contentPosition":"bottom center","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|70","left":"var:preset|spacing|l-2xl","right":"var:preset|spacing|l-2xl"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-cover alignfull has-custom-content-position is-position-bottom-center" style="padding-top:var(--wp--preset--spacing--80);padding-right:var(--wp--preset--spacing--l-2-xl);padding-bottom:var(--wp--preset--spacing--70);padding-left:var(--wp--preset--spacing--l-2-xl);min-height:85vh"><img class="wp-block-cover__image-background" alt="" src="<?php echo esc_url( get_theme_file_uri( 'assets/images/cocktail.webp' ) ); ?>" data-object-fit="cover"/><span aria-hidden="true" class="wp-block-cover__background has-base-background-color has-background-dim-80 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:columns {"verticalAlignment":"bottom","align":"wide","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|70"}}}} -->
<div class="wp-block-columns alignwide are-vertically-aligned-bottom"><!-- wp:column {"verticalAlignment":"bottom","width":"60%"} -->
<div class="wp-block-column is-vertically-aligned-bottom" style="flex-basis:60%"><!-- wp:heading {"level":1,"fontSize":"xx-large"} -->
<h1 class="wp-block-heading has-xx-large-font-size">Catering- ja baarimestaripalvelut tapahtumiin</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"typography":{"fontWeight":"600"}},"fontSize":"large"} -->
<p class="has-large-font-size" style="font-weight:600">Onnistunut tapahtuma syntyy yksityiskohdista.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Fox Ventures tarjoaa catering- ja ammattitaitoiset baarimestaripalvelut yritystilaisuuksiin ja tapahtumiin pääasiassa Itä-Suomen alueella.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"var:preset|spacing|50"}}}} -->
<div class="wp-block-buttons" style="margin-top:var(--wp--preset--spacing--50)"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#yhteystiedot">Pyydä tarjous</a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="#palvelut">Tutustu palveluihin</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->

<!-- wp:paragraph {"style":{"spacing":{"margin":{"top":"var:preset|spacing|50"}}},"textColor":"accent-3","fontSize":"small"} -->
<p class="has-accent-3-color has-text-color has-small-font-size" style="margin-top:var(--wp--preset--spacing--50)">Catering  •  Baarimestarit  •  Yritystilaisuudet  •  Tapahtumat</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"bottom","width":"40%"} -->
<div class="wp-block-column is-vertically-aligned-bottom" style="flex-basis:40%"><!-- wp:image {"id":191,"sizeSlug":"large","linkDestination":"none","className":"is-style-default"} -->
<figure class="wp-block-image size-large is-style-default"><img src="http://localhost:8888/wp-content/uploads/2026/09/fox_ventures_vaaka2_pun_svg.svg" alt="" class="wp-image-191"/></figure>
<!-- /wp:image -->

<!-- wp:paragraph {"style":{"typography":{"letterSpacing":"0.15em","lineHeight":"1.6","textAlign":"left","textTransform":"uppercase"}},"fontSize":"medium"} -->
<p class="has-text-align-left has-medium-font-size" style="letter-spacing:0.15em;line-height:1.6;text-transform:uppercase">Hyvä tapahtuma maistuu pidempään.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div></div>
<!-- /wp:cover -->

<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"},"margin":{"top":"0"}}},"layout":{"type":"constrained"},"anchor":"palvelut"} -->
<div class="wp-block-group alignfull" id="palvelut" style="margin-top:0;padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)"><!-- wp:group {"align":"wide","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|60"}}},"layout":{"type":"grid","minimumColumnWidth":"30rem"}} -->
<div class="wp-block-group alignwide"><!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
<div class="wp-block-group"><!-- wp:separator {"style":{"layout":{"selfStretch":"fixed","flexSize":"2.5rem"},"spacing":{"margin":{"top":"0","bottom":"0"}}},"backgroundColor":"accent-1"} -->
<hr class="wp-block-separator has-text-color has-accent-1-color has-alpha-channel-opacity has-accent-1-background-color has-background" style="margin-top:0;margin-bottom:0"/>
<!-- /wp:separator -->

<!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.2em"}},"textColor":"accent-4","fontSize":"small"} -->
<p class="has-accent-4-color has-text-color has-small-font-size" style="letter-spacing:0.2em;text-transform:uppercase">Palvelumme</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:heading {"fontSize":"x-large"} -->
<h2 class="wp-block-heading has-x-large-font-size">Me hoidamme tarjoilun. Sinä voit keskittyä tapahtumaasi.</h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:group {"layout":{"type":"flex","orientation":"vertical","verticalAlignment":"bottom"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"textColor":"accent-4"} -->
<p class="has-accent-4-color has-text-color">Laadukasta ruokaa, ammattitaitoista juomatarjoilua ja osaavia tekijöitä – juuri sinun tapahtumaasi.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|60"}}},"layout":{"type":"grid","minimumColumnWidth":"30rem"}} -->
<div class="wp-block-group alignwide"><!-- wp:media-text {"linkDestination":"none","mediaType":"image","mediaWidth":45,"verticalAlignment":"center","imageFill":true,"style":{"dimensions":{"minHeight":"100%"}},"backgroundColor":"accent-5"} -->
<div class="wp-block-media-text is-stacked-on-mobile is-vertically-aligned-center is-image-fill-element has-accent-5-background-color has-background" style="min-height:100%;grid-template-columns:45% auto"><figure class="wp-block-media-text__media"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/catering-table.webp' ) ); ?>" alt="" style="object-position:50% 50%"/></figure><div class="wp-block-media-text__content"><!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"},"blockGap":{"top":"var:preset|spacing|30"}}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)"><!-- wp:heading {"level":3,"fontSize":"medium"} -->
<h3 class="wp-block-heading has-medium-font-size">Catering-palvelut</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"accent-4","fontSize":"small"} -->
<p class="has-accent-4-color has-text-color has-small-font-size">Monipuoliset ja laadukkaat catering-ratkaisut yritystilaisuuksiin, juhliin ja muihin tapahtumiin. Menut suunnitellaan tilaisuuden luonteen ja vierasmäärän mukaan.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="#yhteystiedot">Kysy cateringista</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div></div>
<!-- /wp:media-text -->

<!-- wp:media-text {"linkDestination":"none","mediaType":"image","mediaWidth":45,"verticalAlignment":"center","imageFill":true,"style":{"dimensions":{"minHeight":"100%"}},"backgroundColor":"accent-5"} -->
<div class="wp-block-media-text is-stacked-on-mobile is-vertically-aligned-center is-image-fill-element has-accent-5-background-color has-background" style="min-height:100%;grid-template-columns:45% auto"><figure class="wp-block-media-text__media"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/cocktail.webp' ) ); ?>" alt="" style="object-position:50% 50%"/></figure><div class="wp-block-media-text__content"><!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"},"blockGap":{"top":"var:preset|spacing|30"}}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)"><!-- wp:heading {"level":3,"fontSize":"medium"} -->
<h3 class="wp-block-heading has-medium-font-size">Baarimestaripalvelut</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"accent-4","fontSize":"small"} -->
<p class="has-accent-4-color has-text-color has-small-font-size">Ammattitaitoiset baarimestarit tapahtumiin, yritystilaisuuksiin sekä baareihin ja ravintoloihin. Voit tilata baarimestarin yksittäiseen tapahtumaan tai työvuoroon – joustavasti tarpeesi mukaan.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="#yhteystiedot">Tarvitsen baarimestarin</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div></div>
<!-- /wp:media-text --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:cover {"url":"<?php echo esc_url( get_theme_file_uri( 'assets/images/catering-table.webp' ) ); ?>","dimRatio":70,"overlayColor":"base","isUserOverlayColor":true,"minHeight":360,"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"},"margin":{"top":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-cover alignfull" style="margin-top:0;padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80);min-height:360px"><img class="wp-block-cover__image-background" alt="" src="<?php echo esc_url( get_theme_file_uri( 'assets/images/catering-table.webp' ) ); ?>" data-object-fit="cover"/><span aria-hidden="true" class="wp-block-cover__background has-base-background-color has-background-dim-70 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:heading {"style":{"typography":{"textAlign":"center"}}} -->
<h2 class="wp-block-heading has-text-align-center">Hyvä palvelu huomataan. Erinomainen palvelu muistetaan.</h2>
<!-- /wp:heading --></div>
<!-- /wp:group --></div></div>
<!-- /wp:cover -->

<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"},"margin":{"top":"0"}}},"backgroundColor":"accent-5","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-accent-5-background-color has-background" style="margin-top:0;padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)"><!-- wp:group {"align":"wide","layout":{"type":"grid","minimumColumnWidth":"15rem"}} -->
<div class="wp-block-group alignwide"><!-- wp:group {"style":{"layout":{"columnSpan":2}},"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
<div class="wp-block-group"><!-- wp:separator {"style":{"layout":{"selfStretch":"fixed","flexSize":"2.5rem"},"spacing":{"margin":{"top":"0","bottom":"0"}}},"backgroundColor":"accent-1"} -->
<hr class="wp-block-separator has-text-color has-accent-1-color has-alpha-channel-opacity has-accent-1-background-color has-background" style="margin-top:0;margin-bottom:0"/>
<!-- /wp:separator -->

<!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.2em"}},"textColor":"accent-4","fontSize":"small"} -->
<p class="has-accent-4-color has-text-color has-small-font-size" style="letter-spacing:0.2em;text-transform:uppercase">Miksi Fox Ventures?</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:heading {"fontSize":"x-large"} -->
<h2 class="wp-block-heading has-x-large-font-size">Palvelua, joka tekee tapahtumastasi helpomman.</h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":{"top":"var:preset|spacing|20"}}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group"><!-- wp:icon {"icon":"core/star-filled","style":{"dimensions":{"width":"32px"}},"textColor":"accent-1"} /-->

<!-- wp:heading {"level":3,"fontSize":"medium"} -->
<h3 class="wp-block-heading has-medium-font-size">Ammattimainen palvelu</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"accent-4","fontSize":"small"} -->
<p class="has-accent-4-color has-text-color has-small-font-size">Huolehdimme omasta osuudestamme, jotta sinun ei tarvitse.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":{"top":"var:preset|spacing|20"}}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group"><!-- wp:icon {"icon":"core/people","style":{"dimensions":{"width":"32px"}},"textColor":"accent-1"} /-->

<!-- wp:heading {"level":3,"fontSize":"medium"} -->
<h3 class="wp-block-heading has-medium-font-size">Tapahtumasi mukaan</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"accent-4","fontSize":"small"} -->
<p class="has-accent-4-color has-text-color has-small-font-size">Palvelu suunnitellaan tapahtuman koon ja luonteen mukaan.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":{"top":"var:preset|spacing|20"}}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group"><!-- wp:icon {"icon":"core/map-marker","style":{"dimensions":{"width":"32px"}},"textColor":"accent-1"} /-->

<!-- wp:heading {"level":3,"fontSize":"medium"} -->
<h3 class="wp-block-heading has-medium-font-size">Itä-Suomessa</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"accent-4","fontSize":"small"} -->
<p class="has-accent-4-color has-text-color has-small-font-size">Toimimme pääasiassa Itä-Suomen alueella ja palvelemme niin yrityksiä kuin tapahtumajärjestäjiä.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|70"},"margin":{"top":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="margin-top:0;padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--70)"><!-- wp:group {"align":"wide","layout":{"type":"constrained","justifyContent":"left"}} -->
<div class="wp-block-group alignwide"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
<div class="wp-block-group"><!-- wp:separator {"style":{"layout":{"selfStretch":"fixed","flexSize":"2.5rem"},"spacing":{"margin":{"top":"0","bottom":"0"}}},"backgroundColor":"accent-1"} -->
<hr class="wp-block-separator has-text-color has-accent-1-color has-alpha-channel-opacity has-accent-1-background-color has-background" style="margin-top:0;margin-bottom:0"/>
<!-- /wp:separator -->

<!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.2em"}},"textColor":"accent-4","fontSize":"small"} -->
<p class="has-accent-4-color has-text-color has-small-font-size" style="letter-spacing:0.2em;text-transform:uppercase">Tunnelmia tapahtumista</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:heading {"fontSize":"x-large"} -->
<h2 class="wp-block-heading has-x-large-font-size">Kuvia, jotka kertovat enemmän kuin tuhat sanaa.</h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:gallery {"columns":3,"linkTo":"lightbox","aspectRatio":"1","align":"wide","style":{"spacing":{"margin":{"top":"var:preset|spacing|60"},"blockGap":{"top":"var:preset|spacing|30","left":"var:preset|spacing|30"}}}} -->
<figure class="wp-block-gallery alignwide has-nested-images columns-3 is-cropped" style="margin-top:var(--wp--preset--spacing--60)"><!-- wp:image {"lightbox":{"enabled":true},"aspectRatio":"1","sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/catering-table.webp' ) ); ?>" alt="<?php esc_attr_e( 'Burgereita, salaatteja ja lisukkeita tarjoiltuna pitkälle pöydälle', 'foxventures' ); ?>" style="aspect-ratio:1"/></figure>
<!-- /wp:image -->

<!-- wp:image {"lightbox":{"enabled":true},"aspectRatio":"1","sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/cocktail.webp' ) ); ?>" alt="<?php esc_attr_e( 'Cocktail appelsiininkuorella baaritiskillä', 'foxventures' ); ?>" style="aspect-ratio:1"/></figure>
<!-- /wp:image --></figure>
<!-- /wp:gallery --></div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|80"},"margin":{"top":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="margin-top:0;padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--80)"><!-- wp:group {"align":"wide","layout":{"type":"constrained","justifyContent":"left"}} -->
<div class="wp-block-group alignwide"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
<div class="wp-block-group"><!-- wp:separator {"style":{"layout":{"selfStretch":"fixed","flexSize":"2.5rem"},"spacing":{"margin":{"top":"0","bottom":"0"}}},"backgroundColor":"accent-1"} -->
<hr class="wp-block-separator has-text-color has-accent-1-color has-alpha-channel-opacity has-accent-1-background-color has-background" style="margin-top:0;margin-bottom:0"/>
<!-- /wp:separator -->

<!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.2em"}},"textColor":"accent-4","fontSize":"small"} -->
<p class="has-accent-4-color has-text-color has-small-font-size" style="letter-spacing:0.2em;text-transform:uppercase">Asiakkaiden sanoin</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:heading {"fontSize":"x-large"} -->
<h2 class="wp-block-heading has-x-large-font-size">Tapahtumia, joissa olemme olleet mukana.</h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","style":{"spacing":{"margin":{"top":"var:preset|spacing|60"},"blockGap":"var:preset|spacing|50"}},"layout":{"type":"grid","minimumColumnWidth":"18rem"}} -->
<div class="wp-block-group alignwide" style="margin-top:var(--wp--preset--spacing--60)"><!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","right":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50"},"blockGap":{"top":"var:preset|spacing|40"}}},"backgroundColor":"accent-5","layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group has-accent-5-background-color has-background" style="padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)"><!-- wp:icon {"icon":"core/quote","style":{"dimensions":{"width":"32px"}},"textColor":"accent-1"} /-->

<!-- wp:paragraph {"fontSize":"medium"} -->
<p class="has-medium-font-size">[Lisää tähän asiakkaan palaute. Käytä vain oikeita, luvan kanssa julkaistavia palautteita.]</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"textColor":"accent-4","fontSize":"small"} -->
<p class="has-accent-4-color has-text-color has-small-font-size">— [Asiakas, paikkakunta]</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","right":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50"},"blockGap":{"top":"var:preset|spacing|40"}}},"backgroundColor":"accent-5","layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group has-accent-5-background-color has-background" style="padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)"><!-- wp:icon {"icon":"core/quote","style":{"dimensions":{"width":"32px"}},"textColor":"accent-1"} /-->

<!-- wp:paragraph {"fontSize":"medium"} -->
<p class="has-medium-font-size">[Lisää tähän asiakkaan palaute. Käytä vain oikeita, luvan kanssa julkaistavia palautteita.]</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"textColor":"accent-4","fontSize":"small"} -->
<p class="has-accent-4-color has-text-color has-small-font-size">— [Asiakas, paikkakunta]</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","right":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50"},"blockGap":{"top":"var:preset|spacing|40"}}},"backgroundColor":"accent-5","layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group has-accent-5-background-color has-background" style="padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)"><!-- wp:icon {"icon":"core/quote","style":{"dimensions":{"width":"32px"}},"textColor":"accent-1"} /-->

<!-- wp:paragraph {"fontSize":"medium"} -->
<p class="has-medium-font-size">[Lisää tähän asiakkaan palaute. Käytä vain oikeita, luvan kanssa julkaistavia palautteita.]</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"textColor":"accent-4","fontSize":"small"} -->
<p class="has-accent-4-color has-text-color has-small-font-size">— [Asiakas, paikkakunta]</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"},"margin":{"top":"0"}},"elements":{"link":{"color":{"text":"var:preset|color|contrast"}}}},"backgroundColor":"accent-1","textColor":"contrast","layout":{"type":"constrained"},"anchor":"yhteystiedot"} -->
<div class="wp-block-group alignfull has-contrast-color has-accent-1-background-color has-text-color has-background has-link-color" id="yhteystiedot" style="margin-top:0;padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)"><!-- wp:group {"align":"wide","layout":{"type":"constrained","justifyContent":"left"}} -->
<div class="wp-block-group alignwide"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
<div class="wp-block-group"><!-- wp:separator {"style":{"layout":{"selfStretch":"fixed","flexSize":"2.5rem"},"spacing":{"margin":{"top":"0","bottom":"0"}}},"backgroundColor":"base"} -->
<hr class="wp-block-separator has-text-color has-base-color has-alpha-channel-opacity has-base-background-color has-background" style="margin-top:0;margin-bottom:0"/>
<!-- /wp:separator -->

<!-- wp:paragraph {"style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.2em"}},"textColor":"contrast","fontSize":"small"} -->
<p class="has-contrast-color has-text-color has-small-font-size" style="letter-spacing:0.2em;text-transform:uppercase">Ota yhteyttä</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:heading {"fontSize":"x-large"} -->
<h2 class="wp-block-heading has-x-large-font-size">Suunnitteletko tapahtumaa?</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"fontSize":"medium"} -->
<p class="has-medium-font-size">Kerro meille hieman tilaisuudestasi: päivämäärä, paikkakunta, arvioitu henkilömäärä ja tarvitsetko cateringia, baarimestarin vai molemmat. Olemme sinuun yhteydessä mahdollisimman pian.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}}},"backgroundColor":"base","layout":{"type":"grid","minimumColumnWidth":"22rem"}} -->
<div class="wp-block-group alignwide has-base-background-color has-background" style="padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)"><!-- wp:group {"style":{"layout":{"columnSpan":2}},"layout":{"type":"constrained","justifyContent":"left"}} -->
<div class="wp-block-group"><!-- wp:shortcode -->
[contact-form-7 id="7806df8" title="Contact form 1"]
<!-- /wp:shortcode --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"textColor":"contrast","layout":{"type":"default"}} -->
<div class="wp-block-group has-contrast-color has-text-color"><!-- wp:heading {"level":3,"fontSize":"large"} -->
<h3 class="wp-block-heading has-large-font-size">Jani Keijonen</h3>
<!-- /wp:heading -->

<!-- wp:group {"style":{"spacing":{"blockGap":{"top":"var:preset|spacing|20","left":"var:preset|spacing|10"}}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group"><!-- wp:icon {"icon":"core/mobile","style":{"elements":{"link":{"color":{"text":"var:preset|color|accent-1"}}}},"textColor":"accent-1"} /-->

<!-- wp:paragraph -->
<p><a href="tel:+358453198504">045 319 8504</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"blockGap":{"top":"var:preset|spacing|20","left":"var:preset|spacing|10"}}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group"><!-- wp:icon {"icon":"core/pencil","style":{"elements":{"link":{"color":{"text":"var:preset|color|accent-1"}}}},"textColor":"accent-1"} /-->

<!-- wp:paragraph -->
<p><a href="mailto:jani@foxventures.fi">jani@foxventures.fi</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:paragraph {"textColor":"accent-4"} -->
<p class="has-accent-4-color has-text-color">Itä-Suomi ja sopimuksen mukaan koko Suomi.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
