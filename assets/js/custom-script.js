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

    function openDropdown() {
        if ($dropdown) return;

        // Insert after the editor-tools container (contains media buttons + visual/code tabs)
        var $editorTools = $('#wp-content-editor-tools');
        if (!$editorTools.length) {
            // Fallback to post body content
            $editorTools = $('#post-body-content');
        }

        $dropdown = $(buildDropdownHTML());

        // Insert as first child of the editor container for proper flow
        $('#wp-content-wrap').prepend($dropdown);

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
            openDropdown();
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
