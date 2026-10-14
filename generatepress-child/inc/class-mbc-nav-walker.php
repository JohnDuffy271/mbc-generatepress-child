<?php
/**
 * Nav walker: menu items with class "mbc-menu-toggle" become
 * <button aria-expanded aria-controls>, never links.
 */

defined( 'ABSPATH' ) || exit;

class MBC_Nav_Walker extends Walker_Nav_Menu {

    const TOGGLE_CLASS = 'mbc-menu-toggle';

    /** @var int ID of the item whose children start next. */
    private $parent_item_id = 0;

    private static function chevron() {
        return '<svg class="mbc-chevron" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
            . '<path d="M9 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    }

    public function start_lvl( &$output, $depth = 0, $args = null ) {
        $output .= '<ul class="sub-menu" id="mbc-sub-' . (int) $this->parent_item_id . '">';
    }

    public function end_lvl( &$output, $depth = 0, $args = null ) {
        $output .= '</ul>';
    }

    public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
        $classes   = array_filter( (array) $item->classes );
        $is_toggle = in_array( self::TOGGLE_CLASS, $classes, true );
        $li_class  = array( 'menu-item', 'menu-item-' . (int) $item->ID );
        if ( $this->has_children ) {
            $li_class[] = 'menu-item-has-children';
        }
        if ( ! empty( $item->current ) || in_array( 'current-menu-item', $classes, true ) ) {
            $li_class[] = 'current-menu-item';
        }
        $title = esc_html( apply_filters( 'the_title', $item->title, $item->ID ) );

        $output .= '<li class="' . esc_attr( implode( ' ', $li_class ) ) . '">';

        if ( $is_toggle ) {
            $this->parent_item_id = $item->ID;
            $controls = $this->has_children ? ' aria-controls="mbc-sub-' . (int) $item->ID . '"' : '';
            $output  .= '<button type="button" class="mbc-toggle" aria-expanded="false"' . $controls . '>'
                . '<span class="mbc-toggle__label">' . $title . '</span>' . self::chevron() . '</button>';
            return;
        }

        $this->parent_item_id = $item->ID;
        $attrs                = ' href="' . esc_url( $item->url ) . '"';
        if ( ! empty( $item->target ) ) {
            $attrs .= ' target="' . esc_attr( $item->target ) . '" rel="noopener"';
        }
        if ( in_array( 'current-menu-item', $classes, true ) ) {
            $attrs .= ' aria-current="page"';
        }
        $output .= '<a' . $attrs . '>' . $title . '</a>';
    }

    public function end_el( &$output, $item, $depth = 0, $args = null ) {
        $output .= '</li>';
    }
}
