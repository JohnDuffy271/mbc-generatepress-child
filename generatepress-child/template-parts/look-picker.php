<?php
/**
 * Look picker: shown to logged-in users only (see inc/looks.php).
 */

defined( 'ABSPATH' ) || exit;

$mbc_current = mbc_current_look();
?>
<form class="mbc-picker" method="get" action="">
    <label class="mbc-picker__label" for="mbc-look-select"><?php esc_html_e( 'Look', 'generatepress-child' ); ?></label>
    <select class="mbc-picker__select" id="mbc-look-select" name="look">
        <?php foreach ( mbc_available_looks() as $mbc_look ) : ?>
            <option value="<?php echo esc_attr( $mbc_look ); ?>"<?php selected( $mbc_look, $mbc_current ); ?>><?php echo esc_html( ucwords( str_replace( '-', ' ', $mbc_look ) ) ); ?></option>
        <?php endforeach; ?>
    </select>
    <button class="mbc-picker__button" type="submit"><?php esc_html_e( 'Set', 'generatepress-child' ); ?></button>
</form>
