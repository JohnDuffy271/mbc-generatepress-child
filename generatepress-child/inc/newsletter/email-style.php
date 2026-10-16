<?php
/**
 * Newsletter email: design values, read from css/tokens.css so a rebrand changes the email
 * too. Email clients cannot use CSS variables, so the :root custom properties are parsed,
 * resolved and turned into inline style strings. Cached per file modification time.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolved custom properties from the first :root block of tokens.css.
 *
 * @return array<string,string> Name without the leading dashes (e.g. "mbc-text") => value.
 */
function mbc_nl_email_tokens() {
    $file = get_stylesheet_directory() . '/css/tokens.css';
    $mtime = (int) @filemtime( $file );
    $key   = 'mbc_nl_email_tokens_' . $mtime;
    $found = get_transient( $key );
    if ( is_array( $found ) ) {
        return $found;
    }

    $css = (string) @file_get_contents( $file );
    $raw = array();
    if ( preg_match( '/:root\s*\{(.*?)\n\}/s', $css, $block ) ) {
        $body = preg_replace( '#/\*.*?\*/#s', '', $block[1] );
        if ( preg_match_all( '/--([\w-]+)\s*:\s*([^;]+);/', (string) $body, $m, PREG_SET_ORDER ) ) {
            foreach ( $m as $row ) {
                $raw[ $row[1] ] = trim( $row[2] );
            }
        }
    }

    $resolve = static function ( $value, $depth = 0 ) use ( &$resolve, $raw ) {
        if ( $depth > 8 ) {
            return $value;
        }
        return preg_replace_callback(
            '/var\(\s*--([\w-]+)\s*(?:,\s*([^)]+))?\)/',
            static function ( $hit ) use ( $raw, $resolve, $depth ) {
                if ( isset( $raw[ $hit[1] ] ) ) {
                    return $resolve( $raw[ $hit[1] ], $depth + 1 );
                }
                return isset( $hit[2] ) ? trim( $hit[2] ) : '';
            },
            $value
        );
    };

    $tokens = array();
    foreach ( $raw as $name => $value ) {
        $tokens[ $name ] = trim( (string) $resolve( $value ) );
    }
    set_transient( $key, $tokens, DAY_IN_SECONDS );
    return $tokens;
}

/**
 * Inline style strings for the email, keyed by role.
 *
 * @return array<string,string|array<string,string>> 'c' holds colours and sizes, the rest are style="" values.
 */
function mbc_nl_email_styles() {
    $t = mbc_nl_email_tokens();
    $g = static function ( $name, $fallback ) use ( $t ) {
        return isset( $t[ $name ] ) && '' !== $t[ $name ] ? $t[ $name ] : $fallback;
    };

    $c = array(
        'page'      => $g( 'mbc-surface-alt', '#f7f7f8' ),
        'surface'   => $g( 'mbc-surface', '#ffffff' ),
        'text'      => $g( 'mbc-text', '#18181b' ),
        'muted'     => $g( 'mbc-text-muted', '#3f3f46' ),
        'border'    => $g( 'mbc-border', '#d9d9dd' ),
        'link'      => $g( 'mbc-link', '#2f5fb3' ),
        'btn_bg'    => $g( 'mbc-button-bg', '#2f5fb3' ),
        'btn_text'  => $g( 'mbc-button-text', '#ffffff' ),
        'btn_line'  => $g( 'mbc-button-border', '#2f5fb3' ),
        'foot_bg'   => $g( 'mbc-footer-bg', '#18181b' ),
        'foot_text' => $g( 'mbc-footer-text', '#ffffff' ),
        'font'      => $g( 'mbc-email-font', 'Arial, Helvetica, sans-serif' ),
        'width'     => (int) $g( 'mbc-email-width', '600px' ),
        'gutter'    => (int) $g( 'mbc-email-gutter', '24px' ),
        'radius'    => (int) $g( 'mbc-email-radius', '4px' ),
        's_body'    => (int) $g( 'mbc-email-size-body', '16px' ),
        's_small'   => (int) $g( 'mbc-email-size-small', '13px' ),
        's_card'    => (int) $g( 'mbc-email-size-card-title', '20px' ),
        's_title'   => (int) $g( 'mbc-email-size-title', '28px' ),
        's_sub'     => (int) $g( 'mbc-email-size-subtitle', '18px' ),
    );

    $font = 'font-family:' . $c['font'] . ';word-break:break-word;overflow-wrap:break-word;';
    return array(
        'c'         => $c,
        'body'      => 'margin:0;padding:0;background-color:' . $c['page'] . ';' . $font . 'color:' . $c['text'] . ';',
        'wrap'      => 'background-color:' . $c['page'] . ';',
        'card'      => 'background-color:' . $c['surface'] . ';',
        'pad'       => 'padding:' . $c['gutter'] . 'px;',
        'h1'        => 'margin:0 0 8px 0;' . $font . 'font-size:' . $c['s_title'] . 'px;line-height:1.2;font-weight:bold;color:' . $c['text'] . ';',
        'subtitle'  => 'margin:0 0 8px 0;' . $font . 'font-size:' . $c['s_sub'] . 'px;line-height:1.4;color:' . $c['muted'] . ';',
        'date'      => 'margin:0 0 6px 0;' . $font . 'font-size:' . $c['s_small'] . 'px;line-height:1.4;color:' . $c['muted'] . ';',
        'p'         => 'margin:0 0 16px 0;' . $font . 'font-size:' . $c['s_body'] . 'px;line-height:1.5;color:' . $c['text'] . ';',
        'li'        => 'margin:0 0 6px 0;' . $font . 'font-size:' . $c['s_body'] . 'px;line-height:1.5;color:' . $c['text'] . ';',
        'list'      => 'margin:0 0 16px 0;padding:0 0 0 24px;',
        'card_h'    => 'margin:0 0 8px 0;' . $font . 'font-size:' . $c['s_card'] . 'px;line-height:1.3;font-weight:bold;color:' . $c['text'] . ';',
        'a'         => 'color:' . $c['link'] . ';text-decoration:underline;',
        'a_title'   => 'color:' . $c['text'] . ';text-decoration:none;',
        'rule'      => 'border-top:1px solid ' . $c['border'] . ';font-size:0;line-height:0;',
        'btn'       => 'display:inline-block;padding:12px 24px;' . $font . 'font-size:' . $c['s_body'] . 'px;line-height:1.2;font-weight:bold;color:' . $c['btn_text'] . ';background-color:' . $c['btn_bg'] . ';border:1px solid ' . $c['btn_line'] . ';border-radius:' . $c['radius'] . 'px;text-decoration:none;',
        'img'       => 'display:block;border:0;outline:none;text-decoration:none;height:auto;max-width:100%;',
        'foot'      => 'background-color:' . $c['foot_bg'] . ';padding:' . $c['gutter'] . 'px;' . $font . 'font-size:' . $c['s_small'] . 'px;line-height:1.5;color:' . $c['foot_text'] . ';',
        'foot_a'    => 'color:' . $c['foot_text'] . ';text-decoration:underline;',
    );
}
