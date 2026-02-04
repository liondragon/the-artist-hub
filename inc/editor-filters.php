<?php
declare(strict_types=1);

/**
 * Editor Content Filters
 * 
 * Handles cleaning and formatting of content entered into the editor.
 */

/**
 * Table Class Stripping
 * 
 * Removes class attributes from tables in content.
 */
function the_artist_strip_table_class_attribute($content)
{
    $content = preg_replace('/<table(.*?)class=["\'](.+?)["\']([^>]*?)>/i', '<table$1$3>', $content);
    return $content;
}
add_filter('the_content', 'the_artist_strip_table_class_attribute', 10);
add_filter('widget_text_content', 'the_artist_strip_table_class_attribute', 10);

/**
 * OnlyOffice Paste Cleanup
 * 
 * These TinyMCE filters clean up content pasted from OnlyOffice.
 */
add_filter('tiny_mce_before_init', 'the_artist_tinymce_paste_cleanup');
function the_artist_tinymce_paste_cleanup($in)
{
    $in['paste_preprocess'] = "function(plugin, args) {
        var contentWrapper = jQuery('<div>' + args.content + '</div>');
        contentWrapper.find('*').removeAttr('class').removeAttr('style');
        args.content = contentWrapper.html();
    }";
    return $in;
}

add_filter('tiny_mce_before_init', 'the_artist_tinymce_wrap_totals');
function the_artist_tinymce_wrap_totals($in)
{
    $in['paste_postprocess'] = "function(plugin, args) {
        var contentWrapper = jQuery('<div>' + args.node.innerHTML + '</div>');
        contentWrapper.find('tr').each(function() {
            var row = jQuery(this);
            if (row.find('td:contains(\"Total\")').length > 0) {
                row.addClass('total-row');
                row.find('td').each(function() {
                    var cell = jQuery(this);
                    cell.html('<strong>' + cell.html() + '</strong>');
                });
            }
        });
        args.node.innerHTML = contentWrapper.html();
    }";
    return $in;
}

add_filter('tiny_mce_before_init', 'the_artist_tinymce_allow_nbsp');
function the_artist_tinymce_allow_nbsp($mceInit)
{
    $mceInit['entities'] = '160,nbsp,38,amp,60,lt,62,gt';
    $mceInit['entity_encoding'] = 'named';
    return $mceInit;
}
