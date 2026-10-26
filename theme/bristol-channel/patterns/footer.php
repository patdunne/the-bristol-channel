<?php
/**
 * Title: Footer
 * Slug: bristol-channel/footer
 * Inserter: no
 */
?>
<!-- wp:group {"className":"bc-footer","layout":{"type":"default"}} -->
<div class="wp-block-group bc-footer">
<!-- wp:group {"className":"bc-wrap","layout":{"type":"default"}} -->
<div class="wp-block-group bc-wrap">
<!-- wp:html -->
<div class="bc-footer__row"><a class="bc-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/bristol-channel-logo-white.svg' ) ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" width="320" height="24"></a></div>
<!-- /wp:html -->
<!-- wp:group {"className":"bc-footer__row","layout":{"type":"default"}} -->
<div class="wp-block-group bc-footer__row">
<!-- wp:navigation {"overlayMenu":"never","layout":{"type":"flex","justifyContent":"center"}} -->
<!-- wp:navigation-link {"label":"Bristol","type":"custom","url":"<?php echo esc_url( home_url( '/tag/bristol/' ) ); ?>","kind":"custom","isTopLevelLink":true} /-->
<!-- wp:navigation-link {"label":"Bath","type":"custom","url":"<?php echo esc_url( home_url( '/tag/bath/' ) ); ?>","kind":"custom","isTopLevelLink":true} /-->
<!-- wp:navigation-link {"label":"News","type":"custom","url":"<?php echo esc_url( home_url( '/tag/news/' ) ); ?>","kind":"custom","isTopLevelLink":true} /-->
<!-- wp:navigation-link {"label":"Politics","type":"custom","url":"<?php echo esc_url( home_url( '/tag/politics/' ) ); ?>","kind":"custom","isTopLevelLink":true} /-->
<!-- wp:navigation-link {"label":"Business","type":"custom","url":"<?php echo esc_url( home_url( '/tag/business/' ) ); ?>","kind":"custom","isTopLevelLink":true} /-->
<!-- wp:navigation-link {"label":"Opinion","type":"custom","url":"<?php echo esc_url( home_url( '/tag/opinion/' ) ); ?>","kind":"custom","isTopLevelLink":true} /-->
<!-- /wp:navigation -->
</div>
<!-- /wp:group -->
<!-- wp:html -->
<small>&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php echo esc_html( get_bloginfo( 'name' ) ); ?></small>
<!-- /wp:html -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:group -->
