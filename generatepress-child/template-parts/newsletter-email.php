<?php
/**
 * Newsletter email document (standalone: no theme header, footer or wp_head).
 *
 * 600px table layout, inline styles, absolute https links, Mailchimp merge tags in the
 * footer. Colours, fonts and sizes come from css/tokens.css (inc/newsletter/email-style.php).
 *
 * @var array $args { 'issue' => WP_Post, 'posts' => WP_Post[] (optional: an unsaved issue passes its own list) }
 */

defined( 'ABSPATH' ) || exit;

$issue = $args['issue'];
$s     = mbc_nl_email_styles();
$c     = $s['c'];
$w     = $c['width'];
$inner = $w - 2 * $c['gutter'];
$home  = mbc_nl_abs_url( home_url( '/' ) );
$name  = get_bloginfo( 'name' );

$subtitle = trim( (string) get_post_meta( $issue->ID, 'mbc_nl_subtitle', true ) );
$intro    = mbc_nl_email_intro_html( $issue->post_content, $s );
$posts    = isset( $args['posts'] ) ? $args['posts'] : mbc_nl_issue_posts( $issue->ID );
$header   = mbc_nl_email_image( mbc_nl_header_id( $issue ), $w );
if ( ! $header ) {
    $header = mbc_nl_email_image( (int) get_theme_mod( 'custom_logo' ), 240 );
}
$web      = 'publish' === $issue->post_status ? mbc_nl_abs_url( get_permalink( $issue ) ) : '';
$preheader = '' !== $subtitle ? $subtitle : wp_trim_words( wp_strip_all_tags( (string) $issue->post_content ), 20, '…' );

$e = static function ( $text ) {
    return esc_html( $text );
};
?>
<!doctype html>
<html lang="en-GB" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<meta name="x-apple-disable-message-reformatting">
<meta name="format-detection" content="telephone=no,address=no,email=no,date=no">
<meta name="robots" content="noindex, nofollow">
<title><?php echo $e( get_the_title( $issue ) ); ?></title>
<!--[if mso]><xml><o:OfficeDocumentSettings><o:AllowPNG/><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml><![endif]-->
<style>
:root { color-scheme: light; supported-color-schemes: light; }
body { margin: 0; padding: 0; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
table { border-collapse: collapse; mso-table-lspace: 0; mso-table-rspace: 0; }
img { -ms-interpolation-mode: bicubic; }
@media only screen and (max-width: <?php echo (int) $w; ?>px) {
  .mbc-nl-wrap { width: 100% !important; }
  .mbc-nl-img { width: 100% !important; height: auto !important; }
}
</style>
</head>
<body style="<?php echo esc_attr( $s['body'] ); ?>">
<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:<?php echo esc_attr( $c['page'] ); ?>;"><?php echo $e( $preheader ); ?>&#8203;&nbsp;&#8203;&nbsp;&#8203;&nbsp;</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="<?php echo esc_attr( $s['wrap'] ); ?>">
<tr><td align="left" style="padding:16px 8px;">
<!--[if mso]><table role="presentation" align="left" width="<?php echo (int) $w; ?>" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->
<table role="presentation" class="mbc-nl-wrap" align="left" width="<?php echo (int) $w; ?>" cellpadding="0" cellspacing="0" border="0" style="width:<?php echo (int) $w; ?>px;max-width:100%;margin:0;<?php echo esc_attr( $s['card'] ); ?>">

<?php if ( $web ) : ?>
<tr><td align="center" style="padding:12px <?php echo (int) $c['gutter']; ?>px 0;<?php echo esc_attr( 'font-family:' . $c['font'] . ';font-size:' . $c['s_small'] . 'px;color:' . $c['muted'] . ';' ); ?>">Trouble reading this? <a href="<?php echo esc_url( $web ); ?>" style="<?php echo esc_attr( $s['a'] ); ?>">View it on our website</a>.</td></tr>
<?php endif; ?>

<tr><td align="center" style="padding:<?php echo (int) $c['gutter']; ?>px <?php echo (int) $c['gutter']; ?>px 0;">
<?php if ( $header ) : ?>
    <a href="<?php echo esc_url( $home ); ?>" style="text-decoration:none;"><img class="mbc-nl-img" src="<?php echo esc_url( $header['src'] ); ?>" width="<?php echo (int) $header['width']; ?>" height="<?php echo (int) $header['height']; ?>" alt="<?php echo esc_attr( $name ); ?>" style="<?php echo esc_attr( $s['img'] ); ?>"></a>
<?php else : ?>
    <a href="<?php echo esc_url( $home ); ?>" style="<?php echo esc_attr( $s['h1'] . 'text-decoration:none;' ); ?>"><?php echo $e( $name ); ?></a>
<?php endif; ?>
</td></tr>

<tr><td style="<?php echo esc_attr( $s['pad'] ); ?>">
    <h1 style="<?php echo esc_attr( $s['h1'] ); ?>"><?php echo $e( get_the_title( $issue ) ); ?></h1>
<?php if ( '' !== $subtitle ) : ?>
    <p style="<?php echo esc_attr( $s['subtitle'] ); ?>"><?php echo $e( $subtitle ); ?></p>
<?php endif; ?>
<?php if ( '' !== trim( wp_strip_all_tags( $intro ) ) ) : ?>
    <div style="padding-top:8px;"><?php echo $intro; // phpcs:ignore WordPress.Security.EscapeOutput -- built from kses-cleaned content by mbc_nl_email_intro_html(). ?></div>
<?php endif; ?>
</td></tr>

<?php foreach ( $posts as $post ) :
    $url   = mbc_nl_abs_url( get_permalink( $post ) );
    $thumb = mbc_nl_email_image( get_post_thumbnail_id( $post ), MBC_NL_THUMB_WIDTH, 'medium_large' ); // 180px wide, from the same size as the web version.
    $label = sprintf( /* translators: %s: post title */ __( 'Read more about %s', 'generatepress-child' ), get_the_title( $post ) );
    ?>
<tr><td style="padding:0 <?php echo (int) $c['gutter']; ?>px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td style="<?php echo esc_attr( $s['rule'] ); ?>">&nbsp;</td></tr></table></td></tr>
<tr><td style="<?php echo esc_attr( $s['pad'] ); ?>">
<?php if ( $thumb ) : ?>
    <a href="<?php echo esc_url( $url ); ?>" style="text-decoration:none;"><img class="mbc-nl-img" src="<?php echo esc_url( $thumb['src'] ); ?>" width="<?php echo (int) $thumb['width']; ?>" height="<?php echo (int) $thumb['height']; ?>" alt="" style="<?php echo esc_attr( $s['img'] . 'margin:0 0 12px 0;' ); ?>"></a>
<?php endif; ?>
    <p style="<?php echo esc_attr( $s['date'] ); ?>"><?php echo $e( get_the_date( '', $post ) ); ?></p>
    <h2 style="<?php echo esc_attr( $s['card_h'] ); ?>"><a href="<?php echo esc_url( $url ); ?>" style="<?php echo esc_attr( $s['a_title'] ); ?>"><?php echo $e( get_the_title( $post ) ); ?></a></h2>
    <p style="<?php echo esc_attr( $s['p'] ); ?>"><?php echo $e( mbc_nl_excerpt( $post ) ); ?></p>
    <!--[if mso]>
    <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="<?php echo esc_url( $url ); ?>" style="height:44px;v-text-anchor:middle;width:140px;" arcsize="10%" strokecolor="<?php echo esc_attr( $c['btn_line'] ); ?>" fillcolor="<?php echo esc_attr( $c['btn_bg'] ); ?>">
    <w:anchorlock/>
    <center style="color:<?php echo esc_attr( $c['btn_text'] ); ?>;font-family:Arial,sans-serif;font-size:<?php echo (int) $c['s_body']; ?>px;font-weight:bold;">Read more</center>
    </v:roundrect>
    <![endif]-->
    <!--[if !mso]><!-->
    <a href="<?php echo esc_url( $url ); ?>" aria-label="<?php echo esc_attr( $label ); ?>" style="<?php echo esc_attr( $s['btn'] ); ?>">Read more</a>
    <!--<![endif]-->
</td></tr>
<?php endforeach; ?>

<tr><td align="center" style="<?php echo esc_attr( $s['foot'] ); ?>">
    <p style="margin:0 0 8px 0;word-break:break-word;overflow-wrap:break-word;"><?php echo $e( $name ); ?></p>
    <p style="margin:0 0 8px 0;word-break:break-word;overflow-wrap:break-word;">*|LIST:ADDRESSLINE|*</p>
    <p style="margin:0 0 8px 0;word-break:break-word;overflow-wrap:break-word;"><a href="*|UPDATE_PROFILE|*" style="<?php echo esc_attr( $s['foot_a'] ); ?>">Update your details</a> &nbsp;|&nbsp; <a href="*|UNSUB|*" style="<?php echo esc_attr( $s['foot_a'] ); ?>">Unsubscribe</a></p>
    <p style="margin:0;word-break:break-word;overflow-wrap:break-word;">&copy; *|CURRENT_YEAR|* <?php echo $e( $name ); ?></p>
</td></tr>

</table>
<!--[if mso]></td></tr></table><![endif]-->
</td></tr>
</table>
</body>
</html>
