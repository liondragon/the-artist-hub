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

/**
 * Collapsible Sections in Editor
 * 
 * Enables collapsible section toggle functionality within TinyMCE editor.
 * Sections start expanded in editor (via CSS), toggle adds/removes 'collapsed' class.
 */
add_filter('tiny_mce_before_init', 'tah_tinymce_collapsible_sections');
function tah_tinymce_collapsible_sections($mceInit)
{
    $mceInit['setup'] = "function(editor) {
        editor.on('init', function() {
            var body = editor.getBody();
            var lastToggle = 0;
            
            function findTrigger(el) {
                while (el && el !== body) {
                    if (el.classList && el.classList.contains('collapsible-trigger')) {
                        return el;
                    }
                    el = el.parentElement;
                }
                return null;
            }
            
            body.addEventListener('mouseup', function(e) {
                var now = Date.now();
                if (now - lastToggle < 300) return;
                
                var trigger = findTrigger(e.target);
                if (!trigger) return;
                
                var content = trigger.nextElementSibling;
                if (!content) return;
                if (!content.classList || !content.classList.contains('collapsible-content')) return;
                
                lastToggle = now;
                
                // In editor: toggle 'collapsed' class (sections start expanded)
                var isCollapsed = content.classList.contains('collapsed');
                if (isCollapsed) {
                    content.classList.remove('collapsed');
                    trigger.setAttribute('aria-expanded', 'true');
                } else {
                    content.classList.add('collapsed');
                    trigger.setAttribute('aria-expanded', 'false');
                }
                
                e.stopPropagation();
            });
        });
    }";
    return $mceInit;
}
