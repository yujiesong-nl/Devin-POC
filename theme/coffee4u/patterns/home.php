<?php
/**
 * Title: Coffee4U Home
 * Slug: coffee4u/home
 * Categories: coffee4u
 * Inserter: no
 */

$c4u_img = fn( $file ) => esc_url( get_theme_file_uri( 'assets/images/' . $file ) );
?>
<!-- wp:heading {"level":1,"textAlign":"center","className":"c4u-home-title"} -->
<h1 class="wp-block-heading has-text-align-center c4u-home-title">WELCOME TO COFFEE4U</h1>
<!-- /wp:heading -->

<!-- wp:group {"className":"c4u-carousel","layout":{"type":"default"}} -->
<div class="wp-block-group c4u-carousel">
<!-- wp:image {"sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image size-full"><img src="<?php echo $c4u_img( 'hero-1.jpg' ); ?>" alt="Barista pouring latte art"/></figure>
<!-- /wp:image -->
<!-- wp:image {"sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image size-full"><img src="<?php echo $c4u_img( 'hero-2.jpg' ); ?>" alt="Freshly brewed coffee on a cafe table"/></figure>
<!-- /wp:image -->
<!-- wp:image {"sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image size-full"><img src="<?php echo $c4u_img( 'hero-3.jpg' ); ?>" alt="Roasted coffee beans"/></figure>
<!-- /wp:image -->
<!-- wp:image {"sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image size-full"><img src="<?php echo $c4u_img( 'hero-4.jpg' ); ?>" alt="Coffee4U cafe interior"/></figure>
<!-- /wp:image -->
</div>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","anchor":"locations","className":"c4u-home-section","layout":{"type":"default"}} -->
<section id="locations" class="wp-block-group c4u-home-section">
<!-- wp:heading {"className":"c4u-home-heading"} -->
<h2 class="wp-block-heading c4u-home-heading">Cafe Locations</h2>
<!-- /wp:heading -->
<!-- wp:coffee4u/location-list /-->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","anchor":"about","className":"c4u-home-section","layout":{"type":"default"}} -->
<section id="about" class="wp-block-group c4u-home-section">
<!-- wp:heading {"className":"c4u-home-heading"} -->
<h2 class="wp-block-heading c4u-home-heading">About Us</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Coffee4U started as a small espresso bar in downtown Calgary with one simple idea: great coffee should be easy to get. We roast our beans in small batches, bake fresh every morning and brew every cup to order. Order ahead online, skip the line and pick up your favourites on your way to work.</p>
<!-- /wp:paragraph -->
</section>
<!-- /wp:group -->

<!-- wp:group {"tagName":"section","anchor":"contact","className":"c4u-home-section c4u-contact","layout":{"type":"default"}} -->
<section id="contact" class="wp-block-group c4u-home-section c4u-contact">
<!-- wp:heading {"className":"c4u-home-heading"} -->
<h2 class="wp-block-heading c4u-home-heading">Contact Us</h2>
<!-- /wp:heading -->
<!-- wp:social-links {"iconColor":"espresso","iconColorValue":"#3e1f02","className":"is-style-logos-only","layout":{"type":"flex"}} -->
<ul class="wp-block-social-links has-icon-color is-style-logos-only"><!-- wp:social-link {"url":"https://www.facebook.com/","service":"facebook"} /--><!-- wp:social-link {"url":"https://www.instagram.com/","service":"instagram"} /--><!-- wp:social-link {"url":"https://www.linkedin.com/","service":"linkedin"} /--><!-- wp:social-link {"url":"https://www.pinterest.com/","service":"pinterest"} /--></ul>
<!-- /wp:social-links -->
<!-- wp:paragraph -->
<p><strong>Registered Address:</strong> 101 9 Ave SW, Calgary, AB T2P 1J9</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph {"className":"c4u-phone"} -->
<p class="c4u-phone"><a href="tel:4031234567">403-123-4567</a></p>
<!-- /wp:paragraph -->
</section>
<!-- /wp:group -->
