jQuery(document).ready(function ($) {
    var $dropdown = null;

    function buildDropdownHTML() {
        var html = '<div id="templateDropdown" class="template-dropdown">' +
            '<div class="template-dropdown-header">Insert Template</div>' +
            '<ul class="template-dropdown-list">';

        if (templateData.templates && templateData.templates.length > 0) {
            templateData.templates.forEach(function (t) {
                html += '<li><a href="#" class="template-dropdown-item" data-template="' +
                    t.file + '">' + t.title + '</a></li>';
            });
        } else {
            html += '<li class="template-dropdown-empty">No templates found</li>';
        }

        html += '</ul></div>';
        return html;
    }

    function openDropdown($button) {
        if ($dropdown) return; // Already open

        var $container = $button.closest('#wp-content-media-buttons');
        $dropdown = $(buildDropdownHTML());
        $container.after($dropdown);

        // Bind close handlers
        $(document).on('click.tplDropdown', handleClickOutside);
        $(document).on('keydown.tplDropdown', handleEscape);
    }

    function closeDropdown() {
        if (!$dropdown) return;

        $dropdown.remove();
        $dropdown = null;

        $(document).off('click.tplDropdown');
        $(document).off('keydown.tplDropdown');
    }

    function handleClickOutside(e) {
        if (!$(e.target).closest('#my_template_button, #templateDropdown').length) {
            closeDropdown();
        }
    }

    function handleEscape(e) {
        if (e.key === 'Escape') {
            closeDropdown();
        }
    }

    // Button click - toggle dropdown
    $(document).on('click', '#my_template_button', function (e) {
        e.preventDefault();
        e.stopPropagation();

        if ($dropdown) {
            closeDropdown();
        } else {
            openDropdown($(this));
        }
    });

    // Template selection
    $(document).on('click', '.template-dropdown-item', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var templateFile = $(this).data('template');
        var templateUrl = templateData.url + templateFile;

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
            alert('Error: Could not load template.');
            closeDropdown();
        });
    });
});
