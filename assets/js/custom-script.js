jQuery(document).ready(function ($) {
    $(document).on('click', '#my_template_button', function (e) {
        e.preventDefault();

        // Remove existing dropdown to prevent duplicates
        $('#templateDropdown').remove();

        // Create the dropdown HTML
        var dropdownHTML = '<ul id="templateDropdown" class="templates-dropdown">' +
            '<li><strong>Insert Template</strong></li>' +
            '<li><a href="#" class="template-item" data-template="hardwood.html">Hardwood</a></li>' +
            '<li><a href="#" class="template-item" data-template="garage.html">Garage</a></li>' +
            '<li><a href="#" class="template-item" data-template="subfloor_prep.html">Subfloor Leveling</a></li>' +
            // Add more templates as needed
            '</ul>';

        $(this).after(dropdownHTML);

        // Calculate dropdown position
        var buttonPosition = $(this).position();
        $('#templateDropdown').css({
            top: buttonPosition.top + $(this).outerHeight(),
            left: buttonPosition.left
        });

        // Close dropdown when clicking outside
        $(document).on('click', function (e) {
            if (!$(e.target).closest('#my_template_button, #templateDropdown').length) {
                $('#templateDropdown').remove();
            }
        });

        // Template item click logic
        $('.template-item').on('click', function (e) {
            e.preventDefault();
            var selectedTemplate = $(this).data('template');
            var templateUrl = templateData.url + selectedTemplate;

            // Fetch and insert the template content
            $.get(templateUrl, function (content) {
                if (typeof tinyMCE != 'undefined' && tinyMCE.activeEditor && !tinyMCE.activeEditor.isHidden()) {
                    tinyMCE.activeEditor.insertContent(content);
                } else {
                    var textArea = $('textarea#content');
                    textArea.val(textArea.val() + content);
                    // Trigger change event for textarea to acknowledge the update in Gutenberg's Text mode.
                    textArea.trigger('change');
                }
                $('#templateDropdown').remove();
            }).fail(function () {
                alert('Error: Could not load template.');
            });
        });
    });
});
