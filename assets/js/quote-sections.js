(function ($) {
    function escHtml(text) {
        var div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function readOrder($list) {
        var keys = [];
        $list.find('.tah-quote-section-item').each(function () {
            var key = $(this).data('key');
            if (key) {
                keys.push(String(key));
            }
        });
        return keys;
    }

    function syncOrderInput() {
        var $list = $('#tah-quote-sections-list');
        var $order = $('#tah-quote-sections-order');
        if (!$list.length || !$order.length) {
            return;
        }
        $order.val(readOrder($list).join(','));
    }

    function buildQuoteSectionItem(key, title, labels, isLocal) {
        var safeKey = escHtml(key);
        var safeTitle = escHtml(title || key);
        var titleNode = isLocal
            ? '<input type="text" class="tah-local-title-input" name="tah_quote_section_title[' + safeKey + ']" value="' + safeTitle + '" placeholder="' + escHtml(labels.customSectionTitlePlaceholder || 'Custom Section Title') + '">'
            : '<span class="tah-quote-section-title">' + safeTitle + '</span>';

        return '' +
            '<li class="tah-quote-section-item" data-key="' + safeKey + '">' +
                '<div class="tah-quote-section-title-row">' +
                    '<span class="dashicons dashicons-move tah-drag-handle" aria-hidden="true"></span>' +
                    '<label class="tah-inline-enable">' +
                        '<input type="hidden" name="tah_quote_section_enabled[' + safeKey + ']" value="0">' +
                        '<input type="checkbox" name="tah_quote_section_enabled[' + safeKey + ']" value="1" checked> ' +
                        titleNode +
                    '</label>' +
                    '<input type="hidden" class="tah-section-mode-input" name="tah_quote_section_mode[' + safeKey + ']" value="default">' +
                    '<span class="tah-mode-badge">' + escHtml(labels.default) + '</span>' +
                    '<button type="button" class="button-link tah-edit-section">' + escHtml(labels.edit) + '</button>' +
                    '<button type="button" class="button-link tah-reset-section" style="display:none">' + escHtml(labels.resetToDefault) + '</button>' +
                '</div>' +
                '<div class="tah-section-custom-content" style="display:none">' +
                    '<p><label>' + escHtml(labels.customHtml) + '</label></p>' +
                    '<textarea class="widefat" rows="5" name="tah_quote_section_content[' + safeKey + ']"></textarea>' +
                '</div>' +
            '</li>';
    }

    function generateLocalSectionKey() {
        var stamp = Date.now().toString(36);
        var rand = Math.floor(Math.random() * 1e6).toString(36);
        return 'local_' + stamp + '_' + rand;
    }

    function setSectionMode($item, mode, labels) {
        var isCustom = mode === 'custom';
        $item.find('.tah-section-mode-input').val(isCustom ? 'custom' : 'default');

        var $badge = $item.find('.tah-mode-badge');
        $badge.text(isCustom ? ((labels && labels.custom) || 'Custom') : ((labels && labels.default) || 'Default')).show();
    }

    function openEditor($item) {
        $item.find('.tah-section-custom-content').slideDown(120);
        var isCustom = $item.find('.tah-section-mode-input').val() === 'custom';
        $item.find('.tah-reset-section').toggle(isCustom);
        var labels = (typeof tahQuoteSectionsConfig !== 'undefined' && tahQuoteSectionsConfig.labels) ? tahQuoteSectionsConfig.labels : {};
        $item.find('.tah-edit-section').text((labels && labels.collapse) || 'Collapse');
    }

    function closeEditor($item) {
        $item.find('.tah-section-custom-content').slideUp(120);
        $item.find('.tah-reset-section').hide();
        var labels = (typeof tahQuoteSectionsConfig !== 'undefined' && tahQuoteSectionsConfig.labels) ? tahQuoteSectionsConfig.labels : {};
        $item.find('.tah-edit-section').text((labels && labels.edit) || 'Edit');
    }

    function refreshToolsAndHeader() {
        if (typeof tahQuoteSectionsConfig === 'undefined') {
            return;
        }

        var labels = tahQuoteSectionsConfig.labels || {};
        var selectedTrade = $('input[name="tah_trade_term_id"]:checked');
        var tradeName = $.trim(selectedTrade.parent().text()) || (labels.none || 'None');
        var hasTrade = parseInt(selectedTrade.val(), 10) > 0;

        $('.tah-quote-sections-header strong').text((labels.activeRecipePrefix || 'Active Recipe: ') + tradeName);
        $('.tah-quote-sections-tools button').prop('disabled', !hasTrade);
    }

    function refreshEmptyState() {
        if (typeof tahQuoteSectionsConfig === 'undefined') {
            return;
        }

        var labels = tahQuoteSectionsConfig.labels || {};
        var $message = $('#tah-quote-sections-empty-message');
        var isEmpty = readOrder($('#tah-quote-sections-list')).length === 0;

        if (!$message.length) {
            return;
        }
        if (isEmpty) {
            $message.text(labels.emptyState || '').show();
            return;
        }
        $message.hide();
    }

    function toggleCreateControls($container) {
        var $input = $container.find('#tah-create-section-input');
        var value = $.trim($input.val());
        var hasValue = value.length > 0;
        $container.find('#tah-create-section-save').prop('disabled', !hasValue);
        $container.find('#tah-create-section-discard').toggle(hasValue);
    }

    function addLocalSectionFromTitle($list, title, labels) {
        var normalizedTitle = $.trim(title);
        if (!normalizedTitle) {
            return;
        }

        var key = generateLocalSectionKey();
        var html = buildQuoteSectionItem(key, normalizedTitle, labels, true);
        var $item = $(html);
        var $createRow = $list.find('.tah-create-section-item');

        if ($createRow.length) {
            $item.insertBefore($createRow.first());
        } else {
            $list.append($item);
        }

        setSectionMode($item, 'default', labels);
        syncOrderInput();
        refreshEmptyState();
        $item.find('.tah-local-title-input').trigger('focus');
    }

    $(function () {
        var $list = $('#tah-quote-sections-list');
        var $createRowTemplate = $list.find('.tah-create-section-item').first().clone(false, false);

        if ($list.length) {
            $list.sortable({
                axis: 'y',
                handle: '.tah-drag-handle',
                placeholder: 'tah-section-placeholder',
                stop: syncOrderInput
            });
            syncOrderInput();
        }

        if (typeof tahQuoteSectionsConfig !== 'undefined' && $list.length) {
            $(document).on('change', 'input[name="tah_trade_term_id"]', function () {
                var tradeId = String(parseInt($(this).val(), 10) || 0);
                var tradePresets = tahQuoteSectionsConfig.tradePresets || {};
                var sectionTitles = tahQuoteSectionsConfig.sectionTitles || {};
                var labels = tahQuoteSectionsConfig.labels || {};
                var preset = Array.isArray(tradePresets[tradeId]) ? tradePresets[tradeId] : [];

                var html = '';
                preset.forEach(function (key) {
                    var normalizedKey = String(key || '');
                    if (!normalizedKey) {
                        return;
                    }
                    html += buildQuoteSectionItem(normalizedKey, sectionTitles[normalizedKey] || normalizedKey, labels, false);
                });

                $list.html(html);
                if ($createRowTemplate.length) {
                    $list.append($createRowTemplate.clone(false, false));
                }
                syncOrderInput();
                refreshToolsAndHeader();
                refreshEmptyState();
                toggleCreateControls($list);
            });

            refreshToolsAndHeader();
            refreshEmptyState();
            toggleCreateControls($list);
        }

        var $tradePresetList = $('.tah-trade-sections-sortable');
        if ($tradePresetList.length) {
            $tradePresetList.sortable({
                axis: 'y',
                handle: '.tah-drag-handle',
                placeholder: 'tah-section-placeholder'
            });
        }

        $(document).on('click', '.tah-edit-section', function () {
            var $item = $(this).closest('.tah-quote-section-item');
            var isOpen = $item.find('.tah-section-custom-content').is(':visible');
            if (isOpen) {
                closeEditor($item);
                return;
            }

            openEditor($item);
        });

        $(document).on('click', '.tah-reset-section', function () {
            var labels = (typeof tahQuoteSectionsConfig !== 'undefined' && tahQuoteSectionsConfig.labels) ? tahQuoteSectionsConfig.labels : {};
            var $item = $(this).closest('.tah-quote-section-item');
            $item.find('.tah-section-custom-content textarea').val('');
            setSectionMode($item, 'default', labels);
            closeEditor($item);
        });

        $(document).on('input', '.tah-section-custom-content textarea', function () {
            var labels = (typeof tahQuoteSectionsConfig !== 'undefined' && tahQuoteSectionsConfig.labels) ? tahQuoteSectionsConfig.labels : {};
            var $item = $(this).closest('.tah-quote-section-item');
            var hasContent = $.trim($(this).val()).length > 0;

            if (hasContent) {
                setSectionMode($item, 'custom', labels);
                if ($item.find('.tah-section-custom-content').is(':visible')) {
                    $item.find('.tah-reset-section').show();
                }
                return;
            }

            setSectionMode($item, 'default', labels);
            $item.find('.tah-reset-section').hide();
        });

        $(document).on('input', '#tah-create-section-input', function () {
            var $container = $(this).closest('#tah-quote-sections-list');
            toggleCreateControls($container);
        });

        $(document).on('keydown', '#tah-create-section-input', function (event) {
            if (event.key !== 'Enter') {
                return;
            }

            event.preventDefault();
            if (!$list.length || typeof tahQuoteSectionsConfig === 'undefined') {
                return;
            }

            var $container = $(this).closest('#tah-quote-sections-list');
            var labels = tahQuoteSectionsConfig.labels || {};
            var title = $(this).val();
            addLocalSectionFromTitle($list, title, labels);
            $(this).val('');
            toggleCreateControls($container);
        });

        $(document).on('click', '#tah-create-section-save', function () {
            if (!$list.length || typeof tahQuoteSectionsConfig === 'undefined') {
                return;
            }

            var $container = $(this).closest('#tah-quote-sections-list');
            var $input = $container.find('#tah-create-section-input');
            var labels = tahQuoteSectionsConfig.labels || {};
            addLocalSectionFromTitle($list, $input.val(), labels);
            $input.val('').trigger('focus');
            toggleCreateControls($container);
        });

        $(document).on('click', '#tah-create-section-discard', function () {
            var $container = $(this).closest('#tah-quote-sections-list');
            var $input = $container.find('#tah-create-section-input');
            $input.val('').trigger('focus');
            toggleCreateControls($container);
        });
    });
})(jQuery);
