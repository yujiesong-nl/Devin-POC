<?php
/**
 * Title: Coffee4U Header
 * Slug: coffee4u/header
 * Categories: coffee4u
 * Block Types: core/template-part/header
 * Inserter: no
 */
?>
<!-- wp:group {"tagName":"div","className":"c4u-header","layout":{"type":"default"}} -->
<div class="wp-block-group c4u-header">
<!-- wp:html -->
<a class="c4u-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/logo.png' ) ); ?>" alt="" width="40" height="40"><span>Coffee4U</span></a>
<!-- /wp:html -->
<!-- wp:coffee4u/site-nav /-->
</div>
<!-- /wp:group -->
