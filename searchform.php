<?php
/**
 * The template for displaying search forms
 *
 * @package The_Artist
 */

declare(strict_types=1);
?>
<form role="search" method="get" class="search-form" action="<?php echo esc_url(home_url('/')); ?>">
    <label for="search-field-<?php echo uniqid(); ?>">
        <span class="screen-reader-text">
            <?php echo _x('Search for:', 'label', 'the-artist'); ?>
        </span>
    </label>
    <input type="search" id="search-field-<?php echo uniqid(); ?>" class="search-field"
        placeholder="<?php echo esc_attr_x('Search &hellip;', 'placeholder', 'the-artist'); ?>"
        value="<?php echo get_search_query(); ?>" name="s" />
    <button type="submit" class="search-submit">
        <?php echo _x('Search', 'submit button', 'the-artist'); ?>
    </button>
</form>