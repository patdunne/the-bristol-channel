<?php
/**
 * Title: Header
 * Slug: bristol-channel/header
 * Inserter: no
 */
?>
<!-- wp:group {"className":"bc-header bc-wrap","layout":{"type":"default"}} -->
<div class="wp-block-group bc-header bc-wrap">
<!-- wp:html -->
<p class="bc-date"><?php echo esc_html( wp_date( 'l, j F Y' ) ); ?></p>
<!-- /wp:html -->
<!-- wp:html -->
<div class="bc-masthead"><a class="bc-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/bristol-channel-logo-black.svg' ) ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" width="680" height="52"></a><?php if ( get_bloginfo( 'description' ) ) : ?><p class="bc-tagline"><?php echo esc_html( get_bloginfo( 'description' ) ); ?></p><?php endif; ?></div>
<!-- /wp:html -->
<!-- wp:group {"className":"bc-nav-bar","layout":{"type":"default"}} -->
<div class="wp-block-group bc-nav-bar">
<!-- wp:navigation {"overlayMenu":"mobile","icon":"menu","layout":{"type":"flex","justifyContent":"center"}} -->
<!-- wp:navigation-link {"label":"Bristol","type":"custom","url":"<?php echo esc_url( home_url( '/tag/bristol/' ) ); ?>","kind":"custom","isTopLevelLink":true} /-->
<!-- wp:navigation-link {"label":"Bath","type":"custom","url":"<?php echo esc_url( home_url( '/tag/bath/' ) ); ?>","kind":"custom","isTopLevelLink":true} /-->
<!-- wp:navigation-link {"label":"News","type":"custom","url":"<?php echo esc_url( home_url( '/tag/news/' ) ); ?>","kind":"custom","isTopLevelLink":true} /-->
<!-- wp:navigation-link {"label":"Politics","type":"custom","url":"<?php echo esc_url( home_url( '/tag/politics/' ) ); ?>","kind":"custom","isTopLevelLink":true} /-->
<!-- wp:navigation-link {"label":"Business","type":"custom","url":"<?php echo esc_url( home_url( '/tag/business/' ) ); ?>","kind":"custom","isTopLevelLink":true} /-->
<!-- wp:navigation-link {"label":"Opinion","type":"custom","url":"<?php echo esc_url( home_url( '/tag/opinion/' ) ); ?>","kind":"custom","isTopLevelLink":true} /-->
<!-- /wp:navigation -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:group -->
