<?php
/**
 * Section index: [mbc_section_index] shows a card for each page under a section in the Primary menu.
 *
 * The menu is the source, so the cards cannot drift from it. For the page being shown, the cards are the
 * submenu items under the menu item that points to it; if that item is itself a child (the "Overview" item
 * under a section toggle), they are its siblings, leaving the page's own item out. Card: title (the menu
 * label), one-line description (the page's excerpt, else its first words), featured image (else a neutral
 * placeholder). A link to another site gets an arrow and the hidden text "(opens YouTube)" or the host name.
 *
 * Cache: the cards for a whole menu in one transient per menu, cleared when a menu, menu item or page is
 * saved, trashed or deleted, or a page's featured image changes. Styled by css/patterns.css (tokens only).
 *
 * Attributes: page (ID of the page whose section to show; default the current page; for previewing a draft),
 * heading_text (an h2 above the cards, dropped with them), limit (maximum cards, 0 = all).
 */

defined( 'ABSPATH' ) || exit;

const MBC_SI_VERSION   = 1;
const MBC_SI_LOCATION  = 'primary';
const MBC_SI_WORDS     = 20;
const MBC_SI_CACHE_TTL = 12 * HOUR_IN_SECONDS; // Safety net only: the hooks below clear it sooner.

// Pages get a manual excerpt box in the editor (the card's description).
add_action( 'init', 'mbc_section_index_page_excerpts' );
function mbc_section_index_page_excerpts() {
    add_post_type_support( 'page', 'excerpt' );
}

add_shortcode( 'mbc_section_index', 'mbc_section_index_shortcode' );

/** ID of the menu assigned to the Primary location, or 0. */
function mbc_section_index_menu_id() {
    $locations = get_nav_menu_locations();
    return isset( $locations[ MBC_SI_LOCATION ] ) ? (int) $locations[ MBC_SI_LOCATION ] : 0;
}

function mbc_section_index_cache_key( $menu_id ) {
    return 'mbc_si_' . MBC_SI_VERSION . '_' . (int) $menu_id;
}

/** Host name for the "(opens ...)" text: YouTube by name, anything else by its host. */
function mbc_section_index_site_label( $url ) {
    $host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
    $host = preg_replace( '/^www\./', '', (string) $host );
    if ( 'youtube.com' === $host || 'youtu.be' === $host || str_ends_with( $host, '.youtube.com' ) ) {
        return 'YouTube';
    }
    return $host;
}

function mbc_section_index_is_external( $url ) {
    $host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
    $home = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
    return '' !== $host && $host !== $home;
}

/**
 * The placeholder patterns, from inc/placeholder-patterns.json (shared with bin/scan_placeholders.py), as ready-to-use
 * regular expressions. Empty if the file cannot be read; mbc_section_index_is_placeholder() then treats all text as
 * a placeholder, so nothing unchecked reaches a card.
 *
 * @return string[]
 */
function mbc_section_index_placeholder_patterns() {
    static $patterns = null;
    if ( null !== $patterns ) {
        return $patterns;
    }
    $patterns = array();
    $json     = is_readable( __DIR__ . '/placeholder-patterns.json' ) ? file_get_contents( __DIR__ . '/placeholder-patterns.json' ) : false;
    $data     = $json ? json_decode( $json, true ) : null;
    if ( is_array( $data ) && ! empty( $data['patterns'] ) ) {
        foreach ( $data['patterns'] as $item ) {
            if ( ! empty( $item['pattern'] ) ) {
                $patterns[] = '~' . str_replace( '~', '\~', $item['pattern'] ) . '~i';
            }
        }
    }
    if ( ! $patterns ) {
        error_log( 'MBC: inc/placeholder-patterns.json is missing or invalid; section card descriptions are switched off.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
    }
    return $patterns;
}

/**
 * Text that must never reach a card: the block patterns' prompts and the "needs confirming" markers (the shared list),
 * and any paragraph that is itself a prompt ("Add ...").
 */
function mbc_section_index_is_placeholder( $text ) {
    $patterns = mbc_section_index_placeholder_patterns();
    if ( ! $patterns ) {
        return true;
    }
    $patterns[] = '/^\s*Add\s/i';
    foreach ( $patterns as $pattern ) {
        if ( preg_match( $pattern, $text ) ) {
            return true;
        }
    }
    return false;
}

/**
 * First clean paragraph of body text from parsed blocks. Skips the key-facts block (class mbc-facts), call-to-action
 * boxes, shortcodes, paragraphs that open with a bold label ("When ...") and anything that is a placeholder.
 */
function mbc_section_index_first_paragraph( array $blocks ) {
    foreach ( $blocks as $block ) {
        $class = isset( $block['attrs']['className'] ) ? (string) $block['attrs']['className'] : '';
        if ( preg_match( '/\bmbc-(?:facts|cta)\b/', $class ) ) {
            continue;
        }
        if ( 'core/paragraph' === $block['blockName'] ) {
            $html = (string) $block['innerHTML'];
            if ( preg_match( '/^\s*<p[^>]*>\s*<(?:strong|b)>/i', $html ) ) {
                continue;
            }
            $text = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
            if ( str_word_count( $text ) >= 5 && ! mbc_section_index_is_placeholder( $text ) ) {
                return $text;
            }
            continue;
        }
        if ( ! empty( $block['innerBlocks'] ) && in_array( $block['blockName'], array( 'core/group', 'core/media-text', 'core/column', 'core/columns' ), true ) ) {
            $found = mbc_section_index_first_paragraph( $block['innerBlocks'] );
            if ( '' !== $found ) {
                return $found;
            }
        }
    }
    return '';
}

/**
 * One-line description of a page: its excerpt, else its first clean paragraph, else nothing. Never text that looks
 * like a placeholder or comes from the key-facts block.
 */
function mbc_section_index_description( $page_id ) {
    $post = get_post( $page_id );
    if ( ! $post ) {
        return '';
    }
    $manual = trim( wp_strip_all_tags( (string) $post->post_excerpt ) );
    if ( '' !== $manual ) {
        return mbc_section_index_is_placeholder( $manual ) ? '' : $manual;
    }
    $text = mbc_section_index_first_paragraph( parse_blocks( (string) $post->post_content ) );
    return '' === $text ? '' : wp_trim_words( $text, MBC_SI_WORDS, '…' );
}

/**
 * The cards for every page in the menu that has submenu items, as page ID => list of card arrays.
 *
 * @return array<int, array<int, array<string, mixed>>>
 */
function mbc_section_index_build( $menu_id ) {
    $items = $menu_id ? wp_get_nav_menu_items( $menu_id ) : array();
    if ( ! is_array( $items ) ) {
        return array();
    }
    $children = array();
    foreach ( $items as $item ) {
        $children[ (int) $item->menu_item_parent ][] = $item;
    }
    $make = static function ( $item ) {
        $external = 'custom' === $item->type && mbc_section_index_is_external( $item->url );
        $card     = array(
            'title'    => wp_strip_all_tags( $item->title ),
            'url'      => $item->url,
            'target'   => (string) $item->target,
            'external' => $external,
            'site'     => $external ? mbc_section_index_site_label( $item->url ) : '',
            'image'    => 0,
            'desc'     => '',
        );
        if ( 'post_type' === $item->type && (int) $item->object_id ) {
            $card['image'] = (int) get_post_thumbnail_id( (int) $item->object_id );
            $card['desc']  = mbc_section_index_description( (int) $item->object_id );
        } else {
            $card['desc'] = trim( wp_strip_all_tags( (string) $item->description ) );
        }
        return $card;
    };

    $sections = array();
    foreach ( $items as $item ) {
        if ( 'post_type' !== $item->type || 'page' !== $item->object ) {
            continue;
        }
        $page_id = (int) $item->object_id;
        if ( isset( $sections[ $page_id ] ) ) {
            continue;
        }
        $parent = (int) $item->menu_item_parent;
        $source = $parent ? ( $children[ $parent ] ?? array() ) : ( $children[ (int) $item->ID ] ?? array() );
        $cards  = array();
        foreach ( $source as $child ) {
            $points_here = 'post_type' === $child->type && (int) $child->object_id === $page_id;
            $is_toggle   = 'custom' === $child->type && '#' === trim( (string) $child->url );
            if ( $points_here || $is_toggle ) {
                continue;
            }
            $cards[] = $make( $child );
        }
        if ( $cards ) {
            $sections[ $page_id ] = $cards;
        }
    }
    return $sections;
}

/** Cards for a page, from the per-menu cache. */
function mbc_section_index_cards( $page_id ) {
    $menu_id = mbc_section_index_menu_id();
    if ( ! $menu_id ) {
        return array();
    }
    $key      = mbc_section_index_cache_key( $menu_id );
    $sections = get_transient( $key );
    if ( ! is_array( $sections ) ) {
        $sections = mbc_section_index_build( $menu_id );
        set_transient( $key, $sections, MBC_SI_CACHE_TTL );
    }
    return $sections[ (int) $page_id ] ?? array();
}

function mbc_section_index_flush() {
    $menu_id = mbc_section_index_menu_id();
    if ( $menu_id ) {
        delete_transient( mbc_section_index_cache_key( $menu_id ) );
    }
}

foreach ( array( 'wp_update_nav_menu', 'wp_update_nav_menu_item', 'wp_delete_nav_menu', 'after_switch_theme' ) as $mbc_si_hook ) {
    add_action( $mbc_si_hook, 'mbc_section_index_flush' );
}
unset( $mbc_si_hook );

foreach ( array( 'save_post', 'deleted_post', 'trashed_post', 'untrashed_post' ) as $mbc_si_hook ) {
    add_action( $mbc_si_hook, 'mbc_section_index_flush_for_post' );
}
unset( $mbc_si_hook );
function mbc_section_index_flush_for_post( $post_id ) {
    $type = get_post_type( $post_id );
    if ( in_array( $type, array( 'page', 'nav_menu_item', 'attachment' ), true ) ) {
        mbc_section_index_flush();
    }
}

// A featured image set or removed on a page changes its card.
foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $mbc_si_hook ) {
    add_action( $mbc_si_hook, 'mbc_section_index_flush_for_meta', 10, 3 );
}
unset( $mbc_si_hook );
function mbc_section_index_flush_for_meta( $meta_id, $post_id, $meta_key ) {
    if ( '_thumbnail_id' === $meta_key ) {
        mbc_section_index_flush();
    }
}

/** The arrow shown on a link that leaves the site (decorative: the hidden text carries the meaning). */
function mbc_section_index_external_icon() {
    return '<svg class="mbc-section-card__external" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
        . '<path d="M7 17L17 7M9 7h8v8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}

function mbc_section_index_card_html( array $card, $title_tag ) {
    $media = '<div class="mbc-section-card__media" aria-hidden="true">';
    if ( $card['image'] ) {
        $img = wp_get_attachment_image(
            $card['image'],
            'medium_large',
            false,
            array(
                'alt'      => '',
                'loading'  => 'lazy',
                'sizes'    => '(max-width: 600px) calc(100vw - 2rem), 360px',
                'decoding' => 'async',
            )
        );
        $media .= $img ? $img : '<span class="mbc-section-card__placeholder"></span>';
    } else {
        $media .= '<span class="mbc-section-card__placeholder"></span>';
    }
    $media .= '</div>';

    $attrs = ' href="' . esc_url( $card['url'] ) . '"';
    if ( '' !== $card['target'] ) {
        $attrs .= ' target="' . esc_attr( $card['target'] ) . '" rel="noopener noreferrer"';
    }
    $label = esc_html( $card['title'] );
    if ( $card['external'] ) {
        $label .= ' <span class="screen-reader-text">(opens ' . esc_html( $card['site'] ) . ')</span>' . mbc_section_index_external_icon();
    }
    $html  = '<li class="mbc-section-card' . ( $card['external'] ? ' mbc-section-card--external' : '' ) . '">' . $media;
    $html .= '<div class="mbc-section-card__body"><' . $title_tag . ' class="mbc-section-card__title"><a' . $attrs . '>' . $label . '</a></' . $title_tag . '>';
    if ( '' !== $card['desc'] ) {
        $html .= '<p class="mbc-section-card__desc">' . esc_html( $card['desc'] ) . '</p>';
    }
    return $html . '</div></li>';
}

/**
 * For previewing a hub's block draft ("<title> (block draft)", slug "<slug>-block-draft", made by
 * bin/elementor_exit.py): the ID of the page it will replace, which is the one the menu points to.
 * Only for an unpublished page; a published page is looked up as itself.
 */
function mbc_section_index_original_of_draft( $page_id ) {
    $post = get_post( $page_id );
    if ( ! $post || 'page' !== $post->post_type || 'publish' === $post->post_status ) {
        return 0;
    }
    if ( ! preg_match( '/^(.+)-block-draft(?:-\d+)?$/', $post->post_name, $m ) ) {
        return 0;
    }
    $original = get_page_by_path( ( $post->post_parent ? get_page_uri( $post->post_parent ) . '/' : '' ) . $m[1] );
    return $original ? (int) $original->ID : 0;
}

function mbc_section_index_shortcode( $atts ) {
    $atts = shortcode_atts(
        array(
            'page'         => '',
            'heading_text' => '',
            'limit'        => '0',
        ),
        $atts,
        'mbc_section_index'
    );
    $page_id = '' !== $atts['page'] ? absint( $atts['page'] ) : (int) get_the_ID();
    $cards   = $page_id ? mbc_section_index_cards( $page_id ) : array();
    if ( ! $cards && '' === $atts['page'] ) {
        $original = mbc_section_index_original_of_draft( $page_id );
        $cards    = $original ? mbc_section_index_cards( $original ) : array();
    }
    if ( (int) $atts['limit'] > 0 ) {
        $cards = array_slice( $cards, 0, (int) $atts['limit'] );
    }
    if ( ! $cards ) {
        // Editors see why nothing shows; visitors see nothing, not even the heading.
        if ( current_user_can( 'edit_pages' ) ) {
            return '<p class="mbc-section-index__notice">' . esc_html__( 'Section index: this page has no submenu items under it in the Primary menu, so there are no cards to show.', 'generatepress-child' ) . '</p>';
        }
        return '';
    }
    $heading   = trim( (string) $atts['heading_text'] );
    $title_tag = '' !== $heading ? 'h3' : 'h2';
    $html      = '<section class="mbc-section-index"' . ( '' !== $heading ? '' : ' aria-label="' . esc_attr__( 'In this section', 'generatepress-child' ) . '"' ) . '>';
    if ( '' !== $heading ) {
        $html .= '<h2 class="mbc-section-index__heading">' . esc_html( $heading ) . '</h2>';
    }
    $html .= '<ul class="mbc-section-cards">';
    foreach ( $cards as $card ) {
        $html .= mbc_section_index_card_html( $card, $title_tag );
    }
    return $html . '</ul></section>';
}
