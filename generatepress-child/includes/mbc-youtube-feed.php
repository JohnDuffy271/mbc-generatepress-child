<?php
/**
 * MBC YouTube Feed shortcode.
 * Usage: [mbc_youtube_feed channel_id="UCu1CPwAaahNoX3sjDXNdzbA" count="9"]
 *
 * Pulls the channel's public RSS feed (no API key, no quota) and renders
 * a responsive grid matching the site's dark/gold design tokens.
 * Add this file via an mu-plugin, or paste the function + shortcode
 * registration into functions.php.
 */

function mbc_youtube_feed_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'channel_id' => 'UCoWpfG51baXnRz0Szh_VRRg', // MBC channel
			'count'      => 9,
		),
		$atts,
		'mbc_youtube_feed'
	);

	$cache_key = 'mbc_yt_feed_' . md5( $atts['channel_id'] );
	$videos    = get_transient( $cache_key );

	if ( false === $videos ) {
		$feed_url = 'https://www.youtube.com/feeds/videos.xml?channel_id=' . urlencode( $atts['channel_id'] );
		$response = wp_remote_get( $feed_url, array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return '<p>Unable to load videos at the moment.</p>';
		}

		$body = wp_remote_retrieve_body( $response );
		$xml  = simplexml_load_string( $body, 'SimpleXMLElement', LIBXML_NOCDATA );

		if ( ! $xml ) {
			return '<p>Unable to load videos at the moment.</p>';
		}

		$xml->registerXPathNamespace( 'yt', 'http://www.youtube.com/xml/schemas/2015' );
		$xml->registerXPathNamespace( 'media', 'http://search.yahoo.com/mrss/' );

		$videos = array();
		foreach ( $xml->entry as $entry ) {
			$media    = $entry->children( 'http://search.yahoo.com/mrss/' );
			$video_id = (string) $entry->children( 'http://www.youtube.com/xml/schemas/2015' )->videoId;

			$videos[] = array(
				'id'        => $video_id,
				'title'     => (string) $entry->title,
				'published' => (string) $entry->published,
				'thumb'     => "https://i.ytimg.com/vi/{$video_id}/hqdefault.jpg",
				'url'       => "https://www.youtube.com/watch?v={$video_id}",
			);
		}

		// Cache for 1 hour — plenty fresh for a weekly upload schedule.
		set_transient( $cache_key, $videos, HOUR_IN_SECONDS );
	}

	$videos = array_slice( $videos, 0, (int) $atts['count'] );

	if ( empty( $videos ) ) {
		return '<p>No videos found.</p>';
	}

	ob_start();
	?>
	<div class="mbc-yt-grid">
		<?php foreach ( $videos as $video ) : ?>
			<a class="mbc-yt-card" href="<?php echo esc_url( $video['url'] ); ?>" target="_blank" rel="noopener">
				<div class="mbc-yt-thumb">
					<img src="<?php echo esc_url( $video['thumb'] ); ?>" alt="<?php echo esc_attr( $video['title'] ); ?>" loading="lazy">
					<span class="mbc-yt-play">&#9658;</span>
				</div>
				<div class="mbc-yt-meta">
					<h3><?php echo esc_html( $video['title'] ); ?></h3>
					<time><?php echo esc_html( date_i18n( 'j F Y', strtotime( $video['published'] ) ) ); ?></time>
				</div>
			</a>
		<?php endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'mbc_youtube_feed', 'mbc_youtube_feed_shortcode' );
