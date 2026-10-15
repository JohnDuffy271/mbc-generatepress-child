<?php
/**
 * Title: News listing
 * Slug: mbc/news-listing
 * Categories: mbc
 * Description: Latest posts, newest first: featured image, date, title and excerpt, six per page with pagination.
 * Keywords: news, posts, query loop, listing
 * Viewport Width: 1280
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:query {"queryId":1,"query":{"perPage":6,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false},"className":"mbc-news"} -->
<div class="wp-block-query mbc-news"><!-- wp:post-template -->
<!-- wp:group {"className":"mbc-card"} -->
<div class="wp-block-group mbc-card"><!-- wp:post-featured-image {"isLink":true,"aspectRatio":"3/2","className":"mbc-card__image"} /-->

<!-- wp:post-date {"className":"mbc-card__date"} /-->

<!-- wp:post-title {"level":2,"isLink":true,"className":"mbc-card__title"} /-->

<!-- wp:post-excerpt {"moreText":"Read more","excerptLength":30,"className":"mbc-card__excerpt"} /--></div>
<!-- /wp:group -->
<!-- /wp:post-template -->

<!-- wp:query-pagination {"className":"mbc-pagination"} -->
<!-- wp:query-pagination-previous /-->

<!-- wp:query-pagination-numbers /-->

<!-- wp:query-pagination-next /-->
<!-- /wp:query-pagination -->

<!-- wp:query-no-results -->
<!-- wp:paragraph -->
<p>There is no news yet. Please check back soon.</p>
<!-- /wp:paragraph -->
<!-- /wp:query-no-results --></div>
<!-- /wp:query -->
