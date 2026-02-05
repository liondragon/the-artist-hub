jQuery(document).ready(function ($) {
    // Guard: Exit if templateData not available (script loaded on wrong page)
    if (typeof templateData === 'undefined') {
        return;
    }

    var $dropdown = null;

    /**
     * Escape HTML to prevent XSS (defense in depth - PHP also escapes)
     */
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    /**
     * Build dropdown HTML with escaped content and ARIA attributes
     */
    function buildDropdownHTML() {
        var html = '<div id="templateDropdown" class="template-dropdown" role="menu" aria-label="Insert Template">' +
            '<div class="template-dropdown-header">Insert Template</div>' +
            '<ul class="template-dropdown-list" role="none">';

        if (templateData.templates && templateData.templates.length > 0) {
            templateData.templates.forEach(function (t, index) {
                html += '<li role="none"><a href="#" class="template-dropdown-item" role="menuitem" tabindex="' + (index === 0 ? '0' : '-1') + '" data-template="' +
                    escapeHtml(t.file) + '">' + escapeHtml(t.title) + '</a></li>';
            });
        } else {
            html += '<li class="template-dropdown-empty" role="none">No templates found</li>';
        }

        html += '</ul></div>';
        return html;
    }

    /**
     * Show inline error message (replaces alert)
     */
    function showError(message) {
        var $error = $('<div class="template-dropdown-error" role="alert">' + escapeHtml(message) + '</div>');
        var $list = $('#templateDropdown .template-dropdown-list');
        $list.after($error);
        setTimeout(function () {
            $error.fadeOut(function () { $error.remove(); });
        }, 3000);
    }

    /**
     * Open dropdown with container fallback
     */
    function openDropdown() {
        if ($dropdown) return;

        // Find container with fallback
        var $container = $('#wp-content-wrap');
        if (!$container.length) {
            $container = $('#postdivrich');
        }
        if (!$container.length) {
            console.warn('Template dropdown: Could not find editor container');
            return;
        }

        $dropdown = $(buildDropdownHTML());
        $container.prepend($dropdown);

        // Don't auto-focus first item - let user navigate with keyboard or click with mouse
        // This prevents dual highlight (focus + hover) issue

        // Bind close handlers
        $(document).on('click.tplDropdown', handleClickOutside);
        $(document).on('keydown.tplDropdown', handleKeydown);
    }

    /**
     * Close dropdown and return focus to button
     */
    function closeDropdown() {
        if (!$dropdown) return;

        $dropdown.remove();
        $dropdown = null;

        $(document).off('click.tplDropdown');
        $(document).off('keydown.tplDropdown');

        // Return focus to button for accessibility
        $('#my_template_button').focus();
    }

    /**
     * Handle clicks outside dropdown
     */
    function handleClickOutside(e) {
        if (!$(e.target).closest('#my_template_button, #templateDropdown').length) {
            closeDropdown();
        }
    }

    /**
     * Handle keyboard navigation (Escape, Arrow keys)
     */
    function handleKeydown(e) {
        if (e.key === 'Escape') {
            closeDropdown();
            return;
        }

        // Arrow key navigation within dropdown
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            var $items = $('.template-dropdown-item');
            if (!$items.length) return;

            var $focused = $items.filter(':focus');
            var currentIndex = $items.index($focused);
            var nextIndex;

            if (currentIndex === -1) {
                // No item focused yet - start from first (ArrowDown) or last (ArrowUp)
                nextIndex = e.key === 'ArrowDown' ? 0 : $items.length - 1;
            } else if (e.key === 'ArrowDown') {
                nextIndex = currentIndex < $items.length - 1 ? currentIndex + 1 : 0;
            } else {
                nextIndex = currentIndex > 0 ? currentIndex - 1 : $items.length - 1;
            }

            $items.attr('tabindex', '-1');
            $items.eq(nextIndex).attr('tabindex', '0').focus();
        }
    }

    // Button click - toggle dropdown
    $(document).on('click', '#my_template_button', function (e) {
        e.preventDefault();
        e.stopPropagation();

        if ($dropdown) {
            closeDropdown();
        } else {
            openDropdown();
        }
    });

    // Template selection with loading state
    $(document).on('click', '.template-dropdown-item', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var $item = $(this);
        var originalText = $item.text();
        var templateFile = $item.data('template');
        var templateUrl = templateData.url + templateFile;

        // Show loading state
        $item.addClass('is-loading').text('Loading...');

        $.get(templateUrl, function (content) {
            if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor && !tinyMCE.activeEditor.isHidden()) {
                tinyMCE.activeEditor.insertContent(content);
            } else {
                var $textarea = $('textarea#content');
                $textarea.val($textarea.val() + content);
                $textarea.trigger('change');
            }
            closeDropdown();
        }).fail(function () {
            $item.removeClass('is-loading').text(originalText);
            showError('Could not load template. Please try again.');
        });
    });

    // Enter key on dropdown items
    $(document).on('keydown', '.template-dropdown-item', function (e) {
        if (e.key === 'Enter') {
            $(this).trigger('click');
        }
    });
});
