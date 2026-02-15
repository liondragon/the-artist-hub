(function ($) {
    'use strict';

    var config = window.tahQuotePricingConfig || {};
    var labels = config.labels || {};
    var roundingMultiple = Number(config.rounding || 1);
    var roundingDirection = String(config.roundingDirection || 'nearest');
    var ajaxUrl = String(config.ajaxUrl || '');
    var ajaxAction = String(config.ajaxAction || 'tah_save_pricing');
    var ajaxSearchAction = String(config.ajaxSearchAction || 'tah_search_pricing_items');
    var ajaxApplyPresetAction = String(config.ajaxApplyPresetAction || 'tah_apply_trade_pricing_preset');
    var ajaxNonce = String(config.ajaxNonce || '');
    var tradeContexts = config.tradeContexts || {};

    var groupCounter = 1;
    var saveStatusTimer = null;
    var isAjaxSaving = false;

    function escHtml(text) {
        var div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function normalizeNumber(value, fallback) {
        var n = Number(value);
        if (!Number.isFinite(n)) {
            return fallback;
        }
        return n;
    }

    function compactNumber(value) {
        var n = normalizeNumber(value, 0);
        var out = n.toFixed(4).replace(/\.?0+$/, '');
        return out === '-0' ? '0' : out;
    }

    function formatCurrency(value) {
        var n = normalizeNumber(value, 0);
        var sign = n < 0 ? '-' : '';
        return sign + '$' + Math.abs(n).toFixed(2);
    }

    function normalizeQuoteFormat(value) {
        var format = String(value || '').toLowerCase().trim();
        if (format === 'insurance') {
            return 'insurance';
        }
        return 'standard';
    }

    function getSelectedQuoteFormat() {
        var $field = $('#tah-quote-format');
        if ($field.length) {
            return normalizeQuoteFormat($field.val());
        }

        var $editor = $('#tah-quote-pricing');
        return normalizeQuoteFormat($editor.attr('data-quote-format'));
    }

    function normalizeTradeContext(value) {
        var context = String(value || '').toLowerCase().trim();
        if (context === 'all') {
            context = 'both';
        }
        if (context === 'insurance') {
            return 'insurance';
        }
        if (context === 'both') {
            return 'both';
        }
        return 'standard';
    }

    function isTradeContextAllowed(format, context) {
        if (context === 'both') {
            return true;
        }
        return format === context;
    }

    function isInsuranceFormat(format) {
        return normalizeQuoteFormat(format) === 'insurance';
    }

    function getQuoteTaxRate() {
        var raw = String($('#tah-quote-tax-rate').val() || '').trim();
        if (raw === '') {
            return 0;
        }
        var rate = normalizeNumber(raw, 0);
        if (rate < 0) {
            return 0;
        }
        return rate;
    }

    function parseRateFormula(input) {
        var raw = String(input || '').trim();

        if (raw === '' || raw === '$') {
            return { mode: 'default', modifier: 0 };
        }

        var addMatch = raw.match(/^\$\s*([+-])\s*(\d+(?:\.\d+)?)$/);
        if (addMatch) {
            var addModifier = Number(addMatch[2]);
            if (addMatch[1] === '-') {
                addModifier = addModifier * -1;
            }
            return { mode: 'addition', modifier: addModifier };
        }

        var pctMatch = raw.match(/^\$\s*\*\s*([+-]?\d+(?:\.\d+)?)$/);
        if (pctMatch) {
            return { mode: 'percentage', modifier: Number(pctMatch[1]) };
        }

        var fixedMatch = raw.match(/^[+-]?\d+(?:\.\d+)?$/);
        if (fixedMatch) {
            return { mode: 'override', modifier: Number(raw) };
        }

        return { mode: 'default', modifier: 0 };
    }

    function applyRounding(value) {
        var n = normalizeNumber(value, 0);
        var multiple = roundingMultiple > 1 ? roundingMultiple : 1;

        if (multiple <= 1) {
            return Number(n.toFixed(2));
        }

        var scaled = n / multiple;
        if (roundingDirection === 'up') {
            return Number((Math.ceil(scaled) * multiple).toFixed(2));
        }

        if (roundingDirection === 'down') {
            return Number((Math.floor(scaled) * multiple).toFixed(2));
        }

        return Number((Math.round(scaled) * multiple).toFixed(2));
    }

    function resolveRate(parsed, basePrice, currentResolved) {
        var mode = parsed.mode;
        var modifier = normalizeNumber(parsed.modifier, 0);
        var catalogBase = normalizeNumber(basePrice, 0);
        var fallback = normalizeNumber(currentResolved, 0);
        var anchor = catalogBase !== 0 ? catalogBase : fallback;
        var resolved = anchor;

        if (mode === 'addition') {
            resolved = anchor + modifier;
        } else if (mode === 'percentage') {
            resolved = anchor * modifier;
        } else if (mode === 'override') {
            resolved = modifier;
        }

        return applyRounding(resolved);
    }

    function evaluateMathExpression(rawInput, fallback) {
        var raw = String(rawInput || '').trim();
        if (raw === '') {
            return {
                formula: '',
                value: normalizeNumber(fallback, 0),
                valid: true
            };
        }

        if (!/^[0-9+\-*/().\s]+$/.test(raw)) {
            return {
                formula: raw,
                value: normalizeNumber(fallback, 0),
                valid: false
            };
        }

        var value;
        try {
            value = Function('"use strict"; return (' + raw + ');')();
        } catch (err) {
            return {
                formula: raw,
                value: normalizeNumber(fallback, 0),
                valid: false
            };
        }

        if (!Number.isFinite(value)) {
            return {
                formula: raw,
                value: normalizeNumber(fallback, 0),
                valid: false
            };
        }

        return {
            formula: raw,
            value: Number(Number(value).toFixed(2)),
            valid: true
        };
    }

    function rateBadge(mode, pricingItemId) {
        if (!pricingItemId || mode === 'override') {
            return {
                label: labels.customBadge || 'CUSTOM',
                cls: 'tah-badge--custom'
            };
        }

        if (mode === 'default') {
            return {
                label: labels.defaultBadge || 'DEFAULT',
                cls: 'tah-badge--neutral'
            };
        }

        return {
            label: labels.modifiedBadge || 'MODIFIED',
            cls: 'tah-badge--accent'
        };
    }

    function dragHandleSvg() {
        return '<svg viewBox="0 0 32 32" class="svg-icon"><path d="M 14 5.5 a 3 3 0 1 1 -3 -3 A 3 3 0 0 1 14 5.5 Z m 7 3 a 3 3 0 1 0 -3 -3 A 3 3 0 0 0 21 8.5 Z m -10 4 a 3 3 0 1 0 3 3 A 3 3 0 0 0 11 12.5 Z m 10 0 a 3 3 0 1 0 3 3 A 3 3 0 0 0 21 12.5 Z m -10 10 a 3 3 0 1 0 3 3 A 3 3 0 0 0 11 22.5 Z m 10 0 a 3 3 0 1 0 3 3 A 3 3 0 0 0 21 22.5 Z"></path></svg>';
    }

    function buildRowHtml(data) {
        var title = escHtml(data.title == null ? '' : data.title);
        var description = escHtml(data.description || data.note || '');
        var qty = normalizeNumber(data.quantity, 1);
        var pricingItemId = parseInt(String(data.pricingItemId || 0), 10) || 0;
        var unitType = escHtml(data.unitType || 'flat');
        var itemType = escHtml(data.itemType || 'standard');
        var catalogPrice = normalizeNumber(data.catalogPrice, 0);
        var rateResolved = normalizeNumber(data.resolvedPrice, 0);
        var rateFormulaRaw = String(data.rateFormula || (pricingItemId > 0 ? '$' : compactNumber(rateResolved)));
        var rateFormula = escHtml(rateFormulaRaw);
        var qtyFormula = escHtml(data.qtyFormula || compactNumber(qty));
        var badgeMeta = rateBadge(parseRateFormula(rateFormulaRaw).mode, pricingItemId);
        var lineSku = escHtml(data.lineSku || '');
        var materialCost = data.materialCost == null || data.materialCost === ''
            ? ''
            : escHtml(compactNumber(normalizeNumber(data.materialCost, 0)));
        var laborCost = data.laborCost == null || data.laborCost === ''
            ? ''
            : escHtml(compactNumber(normalizeNumber(data.laborCost, 0)));
        var taxRate = escHtml(data.taxRate == null ? '' : String(data.taxRate));
        var note = escHtml(data.note || '');

        return '' +
            '<tr class="tah-line-item-row" data-item-id="0">' +
            '<td class="tah-cell-handle"><span class="tah-drag-handle tah-line-handle" aria-hidden="true">' + dragHandleSvg() + '</span></td>' +
            '<td class="tah-cell-index"><span class="tah-line-index">1</span></td>' +
            '<td class="tah-cell-item">' +
            '<input type="text" class="tah-form-control tah-line-title" value="' + title + '" placeholder="Line item">' +
            '<input type="hidden" class="tah-line-id" value="0">' +
            '<input type="hidden" class="tah-line-pricing-item-id" value="' + escHtml(String(pricingItemId)) + '">' +
            '<input type="hidden" class="tah-line-item-type" value="' + itemType + '">' +
            '<input type="hidden" class="tah-line-unit-type" value="' + unitType + '">' +
            '<input type="hidden" class="tah-line-is-selected" value="1">' +
            '<input type="hidden" class="tah-line-note" value="' + note + '">' +
            '<input type="hidden" class="tah-line-previous-resolved-price" value="">' +
            '</td>' +
            '<td class="tah-cell-sku tah-cell-insurance"><input type="text" class="tah-form-control tah-line-line-sku" value="' + lineSku + '" placeholder="SKU"></td>' +
            '<td class="tah-cell-description"><button type="button" class="button-link tah-line-note-toggle tah-cell-insurance" aria-label="Toggle F9 note" title="Toggle F9 note">F9</button><input type="text" class="tah-form-control tah-line-description" value="' + description + '" placeholder="Description"></td>' +
            '<td class="tah-cell-material tah-cell-insurance"><input type="number" step="0.01" class="tah-form-control tah-line-material-cost" value="' + materialCost + '" placeholder="0.00"></td>' +
            '<td class="tah-cell-labor tah-cell-insurance"><input type="number" step="0.01" class="tah-form-control tah-line-labor-cost" value="' + laborCost + '" placeholder="0.00"></td>' +
            '<td class="tah-cell-qty"><input type="text" class="tah-form-control tah-line-qty" value="' + compactNumber(qty) + '" data-formula="' + qtyFormula + '" data-resolved="' + compactNumber(qty) + '"></td>' +
            '<td class="tah-cell-rate">' +
            '<div class="tah-rate-field">' +
            '<input type="text" class="tah-form-control tah-line-rate" value="' + escHtml(formatCurrency(rateResolved)) + '" data-formula="' + rateFormula + '" data-resolved="' + compactNumber(rateResolved) + '">' +
            '<span class="tah-badge ' + escHtml(badgeMeta.cls) + ' tah-line-rate-badge">' + escHtml(badgeMeta.label) + '</span>' +
            '<input type="hidden" class="tah-line-catalog-price" value="' + escHtml(compactNumber(catalogPrice)) + '">' +
            '</div>' +
            '</td>' +
            '<td class="tah-cell-tax tah-cell-insurance"><input type="number" step="0.0001" min="0" class="tah-form-control tah-line-tax-rate" value="' + taxRate + '" placeholder="Quote default"><span class="tah-line-tax-amount">$0.00</span></td>' +
            '<td class="tah-cell-amount"><span class="tah-line-amount" data-amount="' + compactNumber(qty * rateResolved) + '">' + escHtml(formatCurrency(qty * rateResolved)) + '</span></td>' +
            '<td class="tah-cell-margin"><span class="tah-line-margin">--</span></td>' +
            '<td class="tah-cell-actions"><button type="button" class="tah-icon-button tah-icon-button--danger tah-delete-line" aria-label="Delete line item" title="Delete line item"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button></td>' +
            '</tr>';
    }

    function buildGroupHtml(groupKey, groupData) {
        var group = groupData || {};
        var groupName = escHtml(group.name || labels.groupDefaultName || 'New Group');
        var groupDescription = escHtml(group.description || '');
        var selectionMode = String(group.selectionMode || 'all');
        if (['all', 'multi', 'single'].indexOf(selectionMode) === -1) {
            selectionMode = 'all';
        }
        var showSubtotal = group.showSubtotal !== false;
        var isCollapsed = !!group.isCollapsed;
        var toggleLabel = isCollapsed ? 'Expand group' : 'Collapse group';
        var toggleIcon = isCollapsed ? 'dashicons-arrow-down-alt2' : 'dashicons-arrow-up-alt2';
        var rowsHtml = '';
        var items = Array.isArray(group.items) ? group.items : [];

        if (!items.length) {
            rowsHtml = buildRowHtml({ title: '', quantity: 1, resolvedPrice: 0, rateFormula: '0', qtyFormula: '1' });
        } else {
            rowsHtml = items.map(function (item) {
                return buildRowHtml({
                    title: item.title || '',
                    description: item.description || '',
                    quantity: item.quantity,
                    resolvedPrice: item.resolvedPrice,
                    rateFormula: item.rateFormula || '$',
                    qtyFormula: compactNumber(normalizeNumber(item.quantity, 1)),
                    pricingItemId: item.pricingItemId || 0,
                    unitType: item.unitType || 'flat',
                    catalogPrice: item.catalogPrice || 0,
                    itemType: item.itemType || 'standard',
                    lineSku: item.lineSku || '',
                    materialCost: item.materialCost,
                    laborCost: item.laborCost,
                    taxRate: item.taxRate,
                    note: item.note || ''
                });
            }).join('');
        }
        var subtotalHiddenClass = showSubtotal ? '' : ' style="display:none;"';
        var groupClasses = isCollapsed ? 'tah-group-card is-collapsed' : 'tah-group-card';

        return '' +
            '<section class="' + groupClasses + '" data-group-id="0" data-group-key="' + escHtml(groupKey) + '">' +
            '<header class="tah-group-header">' +
            '<div class="tah-group-title-row">' +
            '<span class="tah-drag-handle tah-group-handle" aria-hidden="true">' + dragHandleSvg() + '</span>' +
            '<input type="text" class="tah-form-control tah-group-name" value="' + groupName + '" placeholder="Group name">' +
            '<div class="tah-group-actions">' +
            '<button type="button" class="tah-icon-button tah-toggle-group" aria-label="' + toggleLabel + '" title="' + toggleLabel + '"><span class="dashicons ' + toggleIcon + '" aria-hidden="true"></span></button>' +
            '<button type="button" class="tah-icon-button tah-icon-button--danger tah-delete-group" aria-label="Delete group" title="Delete group"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>' +
            '</div>' +
            '</div>' +
            '<div class="tah-group-settings">' +
            '<input type="text" class="tah-form-control tah-group-description" value="' + groupDescription + '" placeholder="Group description (optional)">' +
            '<label class="tah-group-setting">Selection <select class="tah-form-control tah-group-selection-mode">' +
            '<option value="all"' + (selectionMode === 'all' ? ' selected' : '') + '>All</option>' +
            '<option value="multi"' + (selectionMode === 'multi' ? ' selected' : '') + '>Multi</option>' +
            '<option value="single"' + (selectionMode === 'single' ? ' selected' : '') + '>Single</option>' +
            '</select></label>' +
            '<label class="tah-inline-checkbox"><input type="checkbox" class="tah-group-show-subtotal"' + (showSubtotal ? ' checked' : '') + '> Show subtotal</label>' +
            '<label class="tah-inline-checkbox"><input type="checkbox" class="tah-group-collapsed"' + (isCollapsed ? ' checked' : '') + '> Start collapsed</label>' +
            '</div>' +
            '</header>' +
            '<div class="tah-group-table-wrap">' +
            '<table class="tah-pricing-table-editor">' +
            '<thead><tr><th class="tah-col-handle"></th><th class="tah-col-index">#</th><th class="tah-col-item">Item</th><th class="tah-col-sku tah-col-insurance">SKU</th><th class="tah-col-description" data-standard-label="Description" data-insurance-label="F9 Note">Description</th><th class="tah-col-material tah-col-insurance">Material</th><th class="tah-col-labor tah-col-insurance">Labor</th><th class="tah-col-qty">Qty</th><th class="tah-col-rate" data-standard-label="Rate" data-insurance-label="Unit Price">Rate</th><th class="tah-col-tax tah-col-insurance">Tax</th><th class="tah-col-amount">Amount</th><th class="tah-col-margin">Margin</th><th class="tah-col-actions"></th></tr></thead>' +
            '<tbody class="tah-line-items-body">' + rowsHtml + '</tbody>' +
            '</table>' +
            '<div class="tah-group-footer">' +
            '<button type="button" class="button button-secondary tah-add-line-item">+ Add Item</button>' +
            '<div class="tah-group-subtotal-row"' + subtotalHiddenClass + '><span class="tah-group-subtotal-label">Subtotal</span><strong class="tah-group-subtotal-value">$0.00</strong></div>' +
            '</div>' +
            '</div>' +
            '</section>';
    }

    function hasPopulatedEditorRows() {
        var hasRows = false;

        $('#tah-pricing-groups .tah-line-item-row').each(function () {
            var $row = $(this);
            var title = String($row.find('.tah-line-title').val() || '').trim();
            if (title !== '') {
                hasRows = true;
                return false;
            }
            return undefined;
        });

        return hasRows;
    }

    function showPresetNotice(level, message) {
        var $status = $('#tah-pricing-save-status');
        if (!$status.length) {
            return;
        }

        if (!message) {
            setSaveStatus('clear');
            return;
        }

        if (level === 'error') {
            setSaveStatus('error', message);
            return;
        }

        setSaveStatus('success', message);
    }

    function ensureSingleInsuranceGroup() {
        var $groups = $('#tah-pricing-groups .tah-group-card');
        if ($groups.length <= 1) {
            return;
        }

        var $primary = $groups.first();
        var $primaryBody = $primary.find('.tah-line-items-body').first();

        $groups.slice(1).each(function () {
            var $group = $(this);
            $group.find('.tah-line-item-row').appendTo($primaryBody);
            $group.remove();
        });

        if (!$primaryBody.find('.tah-line-item-row').length) {
            $primaryBody.append(buildRowHtml({
                title: '',
                quantity: 1,
                resolvedPrice: 0,
                rateFormula: '0',
                qtyFormula: '1'
            }));
        }

        $primary.find('.tah-group-name').val(labels.insuranceGroupName || 'Insurance Items');
        $primary.find('.tah-group-show-subtotal').prop('checked', false);
        initLineSortable($primary);
        initAutocomplete($primary);
    }

    function applyTradeContextFilter(format) {
        var normalizedFormat = normalizeQuoteFormat(format);
        var hadActiveSelection = false;
        var activeSelectionStillVisible = false;

        $('#tah_trade_single_select input[name="tah_trade_term_id"]').each(function () {
            var $input = $(this);
            var tradeId = parseInt(String($input.val() || '0'), 10) || 0;
            var $row = $input.closest('p');
            if (!$row.length) {
                $row = $input.closest('label');
            }

            if (tradeId === 0) {
                $input.prop('disabled', false);
                $row.show();
                if ($input.is(':checked')) {
                    hadActiveSelection = true;
                    activeSelectionStillVisible = true;
                }
                return;
            }

            var context = normalizeTradeContext(tradeContexts[String(tradeId)]);
            var isAllowed = isTradeContextAllowed(normalizedFormat, context);

            $input.prop('disabled', !isAllowed);
            $row.toggle(isAllowed);

            if ($input.is(':checked')) {
                hadActiveSelection = true;
                if (isAllowed) {
                    activeSelectionStillVisible = true;
                } else {
                    $input.prop('checked', false);
                }
            }
        });

        if (hadActiveSelection && !activeSelectionStillVisible) {
            $('#tah_trade_single_select input[name="tah_trade_term_id"][value="0"]').prop('checked', true).trigger('change');
        }
    }

    function updateTableHeaderLabels(format) {
        var normalizedFormat = normalizeQuoteFormat(format);
        var useInsurance = normalizedFormat === 'insurance';

        $('#tah-pricing-groups .tah-pricing-table-editor th[data-standard-label]').each(function () {
            var $th = $(this);
            var next = useInsurance ? $th.attr('data-insurance-label') : $th.attr('data-standard-label');
            if (next) {
                $th.text(next);
            }
        });
    }

    function applyQuoteFormatUi(format) {
        var normalizedFormat = normalizeQuoteFormat(format);
        var isInsurance = normalizedFormat === 'insurance';
        var $editor = $('#tah-quote-pricing');

        $editor.attr('data-quote-format', normalizedFormat);
        $editor.toggleClass('is-quote-format-insurance', isInsurance);
        $editor.find('.tah-insurance-tax-rate-field').toggle(isInsurance);
        $editor.find('.tah-pricing-insurance-hint').toggle(isInsurance);

        if (isInsurance) {
            ensureSingleInsuranceGroup();
        }

        $editor.find('.tah-line-item-row').each(function () {
            prepareRowForCurrentFormat($(this), isInsurance);
        });

        updateTableHeaderLabels(normalizedFormat);
        applyTradeContextFilter(normalizedFormat);
        refreshTotals();
    }

    function applyPresetToEditor(groups) {
        var $groupsWrap = $('#tah-pricing-groups');
        if (!$groupsWrap.length) {
            return;
        }

        $groupsWrap.empty();

        if (!Array.isArray(groups) || !groups.length) {
            groupCounter += 1;
            $groupsWrap.append(buildGroupHtml('group-new-' + Date.now() + '-' + groupCounter, null));
            initLineSortable($groupsWrap);
            initAutocomplete($groupsWrap);
            refreshTotals();
            return;
        }

        groups.forEach(function (group, index) {
            groupCounter += 1;
            var groupKey = 'group-preset-' + Date.now() + '-' + groupCounter + '-' + index;
            $groupsWrap.append(buildGroupHtml(groupKey, {
                name: String(group.name || labels.groupDefaultName || 'New Group'),
                description: String(group.description || ''),
                selectionMode: String(group.selection_mode || 'all'),
                showSubtotal: !!group.show_subtotal,
                isCollapsed: !!group.is_collapsed,
                items: Array.isArray(group.items) ? group.items.map(function (item) {
                    return {
                        title: String(item.title || ''),
                        description: String(item.description || ''),
                        quantity: normalizeNumber(item.quantity, 1),
                        resolvedPrice: normalizeNumber(item.resolved_price, 0),
                        rateFormula: String(item.rate_formula || '$'),
                        pricingItemId: parseInt(String(item.pricing_item_id || 0), 10) || 0,
                        unitType: String(item.unit_type || 'flat'),
                        catalogPrice: normalizeNumber(item.catalog_price, 0),
                        itemType: 'standard'
                    };
                }) : []
            }));
        });

        initLineSortable($groupsWrap);
        initAutocomplete($groupsWrap);
        refreshTotals();
    }

    function applyTradePreset(tradeId) {
        var quoteId = getEditorQuoteId();
        if (!ajaxUrl || !ajaxNonce || quoteId <= 0 || tradeId <= 0) {
            return;
        }

        if (hasPopulatedEditorRows()) {
            showPresetNotice('warning', labels.presetAlreadyPopulated || 'Pricing is already populated for this quote. Preset was not applied.');
            return;
        }

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: ajaxApplyPresetAction,
                nonce: ajaxNonce,
                quote_id: quoteId,
                trade_id: tradeId,
                tah_quote_format: getSelectedQuoteFormat()
            }
        }).done(function (response) {
            if (!response || response.success !== true || !response.data) {
                showPresetNotice('error', labels.saveError || 'Save failed');
                return;
            }

            var data = response.data;
            if (!data.applied) {
                if (data.reason === 'no_preset') {
                    showPresetNotice('warning', data.message || labels.presetNoPreset || 'No pricing preset is configured for this trade.');
                    return;
                }
                if (data.reason === 'already_populated') {
                    showPresetNotice('warning', data.message || labels.presetAlreadyPopulated || 'Pricing is already populated for this quote. Preset was not applied.');
                    return;
                }
                if (data.reason === 'unsupported_format') {
                    showPresetNotice('warning', data.message || labels.presetUnsupportedFormat || 'Pricing presets apply only to standard quotes.');
                    return;
                }
                showPresetNotice('warning', data.message || labels.presetNoPreset || 'No pricing preset is configured for this trade.');
                return;
            }

            applyPresetToEditor(Array.isArray(data.groups) ? data.groups : []);
            if (normalizeNumber(data.missing_count, 0) > 0) {
                showPresetNotice('warning', data.message || labels.presetSkipped || 'Some preset items were skipped because they no longer exist in the catalog.');
                return;
            }
            showPresetNotice('success', data.message || labels.presetApplied || 'Pricing preset applied.');
        }).fail(function () {
            showPresetNotice('error', labels.saveError || 'Save failed');
        });
    }

    function getFieldFormula($field) {
        if ($field.is(':focus')) {
            return String($field.val() || '').trim();
        }
        return String($field.attr('data-formula') || '').trim();
    }

    function refreshRateRow($row) {
        if (isInsuranceFormat(getSelectedQuoteFormat())) {
            var materialCost = normalizeNumber($row.find('.tah-line-material-cost').val(), 0);
            var laborCost = normalizeNumber($row.find('.tah-line-labor-cost').val(), 0);
            if (materialCost < 0) {
                materialCost = 0;
            }
            if (laborCost < 0) {
                laborCost = 0;
            }

            var unitPrice = Number((materialCost + laborCost).toFixed(2));
            var $insuranceRateInput = $row.find('.tah-line-rate');
            var taxRateRaw = String($row.find('.tah-line-tax-rate').val() || '').trim();
            var taxRate = taxRateRaw === '' ? getQuoteTaxRate() : normalizeNumber(taxRateRaw, 0);
            if (taxRate < 0) {
                taxRate = 0;
            }

            $insuranceRateInput.attr('data-formula', compactNumber(unitPrice));
            $insuranceRateInput.attr('data-resolved', compactNumber(unitPrice));
            $insuranceRateInput.val(formatCurrency(unitPrice));

            return {
                parsed: { mode: 'override', modifier: unitPrice },
                resolved: unitPrice,
                materialCost: materialCost,
                laborCost: laborCost,
                taxRate: taxRate
            };
        }

        var $rateInput = $row.find('.tah-line-rate');
        var $badge = $row.find('.tah-line-rate-badge');
        var basePrice = normalizeNumber($row.find('.tah-line-catalog-price').val(), 0);
        var previousResolved = normalizeNumber($rateInput.attr('data-resolved'), 0);
        var formulaRaw = getFieldFormula($rateInput);
        var parsed = parseRateFormula(formulaRaw);
        var pricingItemId = parseInt(String($row.find('.tah-line-pricing-item-id').val() || '0'), 10) || 0;
        var resolved = resolveRate(parsed, basePrice, previousResolved);
        var badge = rateBadge(parsed.mode, pricingItemId);

        $rateInput.attr('data-formula', formulaRaw === '' ? '$' : formulaRaw);
        $rateInput.attr('data-resolved', compactNumber(resolved));
        if (!$rateInput.is(':focus')) {
            $rateInput.val(formatCurrency(resolved));
        }

        $badge.removeClass('tah-badge--neutral tah-badge--accent tah-badge--custom').addClass(badge.cls).text(badge.label);

        return {
            parsed: parsed,
            resolved: resolved
        };
    }

    function refreshQtyRow($row) {
        var $qtyInput = $row.find('.tah-line-qty');
        var fallback = normalizeNumber($qtyInput.attr('data-resolved'), 0);
        var formulaRaw = getFieldFormula($qtyInput);
        var result = evaluateMathExpression(formulaRaw, fallback);

        $qtyInput.attr('data-formula', result.formula === '' ? compactNumber(result.value) : result.formula);
        $qtyInput.attr('data-resolved', compactNumber(result.value));
        if (!$qtyInput.is(':focus')) {
            $qtyInput.val(compactNumber(result.value));
        }

        return result.value;
    }

    function refreshMargin($row, resolvedRate) {
        var material = normalizeNumber($row.find('.tah-line-material-cost').val(), 0);
        var labor = normalizeNumber($row.find('.tah-line-labor-cost').val(), 0);
        var hasCost = $.trim(String($row.find('.tah-line-material-cost').val() || '') + String($row.find('.tah-line-labor-cost').val() || '')) !== '';

        if (!hasCost || resolvedRate === 0) {
            $row.find('.tah-line-margin').text('--');
            return;
        }

        var margin = ((resolvedRate - (material + labor)) / resolvedRate) * 100;
        if (!Number.isFinite(margin)) {
            $row.find('.tah-line-margin').text('--');
            return;
        }

        $row.find('.tah-line-margin').text(margin.toFixed(1) + '%');
    }

    function refreshGroup($group) {
        var subtotal = 0;
        var taxTotal = 0;
        var showSubtotal = $group.find('.tah-group-show-subtotal').is(':checked');
        var useInsuranceTotals = isInsuranceFormat(getSelectedQuoteFormat());

        $group.find('.tah-line-item-row').each(function (idx) {
            var $row = $(this);
            $row.find('.tah-line-index').text(String(idx + 1));

            var qty = refreshQtyRow($row);
            var rateMeta = refreshRateRow($row);
            var baseAmount = Number((qty * rateMeta.resolved).toFixed(2));
            var lineTax = 0;
            var amount = baseAmount;

            if (useInsuranceTotals) {
                lineTax = Number((qty * normalizeNumber(rateMeta.materialCost, 0) * normalizeNumber(rateMeta.taxRate, 0)).toFixed(2));
                taxTotal += lineTax;
                amount = Number((baseAmount + lineTax).toFixed(2));
                $row.find('.tah-line-note').val(String($row.find('.tah-line-description').val() || '').trim());
                $row.find('.tah-line-tax-amount').text(formatCurrency(lineTax));
            }

            $row.find('.tah-line-amount').attr('data-amount', compactNumber(amount)).text(formatCurrency(amount));
            if (useInsuranceTotals) {
                $row.find('.tah-line-margin').text('--');
            } else {
                refreshMargin($row, rateMeta.resolved);
            }

            subtotal += baseAmount;
        });

        subtotal = Number(subtotal.toFixed(2));
        $group.find('.tah-group-subtotal-value').text(formatCurrency(subtotal));
        $group.find('.tah-group-subtotal-row').toggle(showSubtotal);
        taxTotal = Number(taxTotal.toFixed(2));

        return {
            subtotal: subtotal,
            taxTotal: taxTotal,
            grandTotal: Number((subtotal + taxTotal).toFixed(2))
        };
    }

    function refreshTotals() {
        var subtotal = 0;
        var taxTotal = 0;
        var grandTotal = 0;
        var useInsuranceTotals = isInsuranceFormat(getSelectedQuoteFormat());

        $('#tah-pricing-groups .tah-group-card').each(function () {
            var groupTotals = refreshGroup($(this));
            subtotal += normalizeNumber(groupTotals.subtotal, 0);
            taxTotal += normalizeNumber(groupTotals.taxTotal, 0);
            grandTotal += normalizeNumber(groupTotals.grandTotal, 0);
        });

        subtotal = Number(subtotal.toFixed(2));
        taxTotal = Number(taxTotal.toFixed(2));
        grandTotal = Number(grandTotal.toFixed(2));

        $('#tah-pricing-subtotal-value').text(formatCurrency(subtotal));
        $('#tah-pricing-tax-total-value').text(formatCurrency(taxTotal));
        $('#tah-pricing-subtotal-row').toggle(useInsuranceTotals);
        $('#tah-pricing-tax-total-row').toggle(useInsuranceTotals);
        $('#tah-pricing-grand-total-value').text(formatCurrency(Number(grandTotal.toFixed(2))));
    }

    function prepareRowForCurrentFormat($row, insuranceMode) {
        if (!$row || !$row.length) {
            return;
        }

        var isInsurance = insuranceMode === undefined
            ? isInsuranceFormat(getSelectedQuoteFormat())
            : !!insuranceMode;
        $row.find('.tah-line-rate').prop('readonly', isInsurance);

        if (isInsurance) {
            var $descriptionCell = $row.find('.tah-cell-description');
            var hasNote = String($descriptionCell.find('.tah-line-description').val() || '').trim() !== '';
            $descriptionCell.toggleClass('is-note-collapsed', !hasNote);
            return;
        }

        $row.find('.tah-cell-description').removeClass('is-note-collapsed');
    }

    function initGroupSortable() {
        var $groups = $('#tah-pricing-groups');
        if (!$groups.length || !$groups.sortable) {
            return;
        }

        $groups.sortable({
            axis: 'y',
            handle: '.tah-group-handle',
            placeholder: 'tah-group-placeholder',
            stop: function () {
                refreshTotals();
            }
        });
    }

    function initLineSortable($scope) {
        $scope.find('.tah-line-items-body').each(function () {
            var $tbody = $(this);
            if (!$tbody.sortable) {
                return;
            }

            if ($tbody.data('sortable-ready')) {
                return;
            }

            $tbody.sortable({
                axis: 'y',
                handle: '.tah-line-handle',
                placeholder: 'tah-line-placeholder',
                stop: function () {
                    refreshTotals();
                }
            });
            $tbody.data('sortable-ready', true);
        });
    }

    function insertRowAfter($row) {
        var html = buildRowHtml({
            title: '',
            quantity: 1,
            resolvedPrice: 0,
            rateFormula: '0',
            qtyFormula: '1'
        });

        var $newRow = $(html);
        if ($row && $row.length) {
            $newRow.insertAfter($row);
        } else {
            return null;
        }

        prepareRowForCurrentFormat($newRow);
        refreshTotals();
        return $newRow;
    }

    function serializeGroups() {
        var out = [];

        $('#tah-pricing-groups .tah-group-card').each(function (index) {
            var $group = $(this);
            out.push({
                id: parseInt(String($group.attr('data-group-id') || '0'), 10) || 0,
                client_key: String($group.attr('data-group-key') || ''),
                name: String($group.find('.tah-group-name').val() || '').trim(),
                description: String($group.find('.tah-group-description').val() || '').trim(),
                selection_mode: String($group.find('.tah-group-selection-mode').val() || 'all'),
                show_subtotal: $group.find('.tah-group-show-subtotal').is(':checked'),
                is_collapsed: $group.find('.tah-group-collapsed').is(':checked'),
                sort_order: index
            });
        });

        return out;
    }

    function serializeItems() {
        var out = [];
        var useInsurance = isInsuranceFormat(getSelectedQuoteFormat());

        $('#tah-pricing-groups .tah-group-card').each(function () {
            var $group = $(this);
            var groupKey = String($group.attr('data-group-key') || '');

            $group.find('.tah-line-item-row').each(function (index) {
                var $row = $(this);
                var qty = refreshQtyRow($row);
                var rateMeta = refreshRateRow($row);
                var title = String($row.find('.tah-line-title').val() || '').trim();

                if (!title) {
                    return;
                }

                var descriptionValue = String($row.find('.tah-line-description').val() || '').trim();
                var noteValue = useInsurance
                    ? descriptionValue
                    : String($row.find('.tah-line-note').val() || '');
                $row.find('.tah-line-note').val(noteValue);

                out.push({
                    id: parseInt(String($row.find('.tah-line-id').val() || '0'), 10) || 0,
                    group_key: groupKey,
                    pricing_item_id: parseInt(String($row.find('.tah-line-pricing-item-id').val() || '0'), 10) || 0,
                    item_type: String($row.find('.tah-line-item-type').val() || 'standard'),
                    title: title,
                    description: descriptionValue,
                    quantity: Number(qty.toFixed(2)),
                    qty_formula: String($row.find('.tah-line-qty').attr('data-formula') || ''),
                    unit_type: String($row.find('.tah-line-unit-type').val() || 'flat'),
                    rate_formula: String($row.find('.tah-line-rate').attr('data-formula') || ''),
                    resolved_price: Number(rateMeta.resolved.toFixed(2)),
                    is_selected: String($row.find('.tah-line-is-selected').val() || '1') !== '0',
                    sort_order: index,
                    material_cost: String($row.find('.tah-line-material-cost').val() || '').trim(),
                    labor_cost: String($row.find('.tah-line-labor-cost').val() || '').trim(),
                    line_sku: String($row.find('.tah-line-line-sku').val() || '').trim(),
                    tax_rate: String($row.find('.tah-line-tax-rate').val() || '').trim(),
                    note: noteValue,
                    previous_resolved_price: String($row.find('.tah-line-previous-resolved-price').val() || '').trim()
                });
            });
        });

        return out;
    }

    function serializeForSubmit() {
        var payload = {
            groups: serializeGroups(),
            items: serializeItems()
        };

        $('#tah-pricing-groups-json').val(JSON.stringify(payload.groups));
        $('#tah-pricing-items-json').val(JSON.stringify(payload.items));
        return payload;
    }

    function setSaveStatus(state, message) {
        var $status = $('#tah-pricing-save-status');
        if (!$status.length) {
            return;
        }

        if (saveStatusTimer) {
            clearTimeout(saveStatusTimer);
            saveStatusTimer = null;
        }

        $status.removeClass('is-saving is-success is-error');

        if (state === 'clear') {
            $status.text('');
            return;
        }

        if (state === 'saving') {
            $status.addClass('is-saving');
            $status.text(message || labels.saveSaving || 'Saving...');
            return;
        }

        if (state === 'success') {
            $status.addClass('is-success');
            $status.text(message || labels.saveSaved || 'Saved');
            saveStatusTimer = window.setTimeout(function () {
                setSaveStatus('clear');
            }, 2200);
            return;
        }

        $status.addClass('is-error');
        $status.text(message || labels.saveError || 'Save failed');
    }

    function saveDraftAjax() {
        var $editor = $('#tah-quote-pricing');
        var quoteId = parseInt(String($editor.attr('data-quote-id') || '0'), 10) || 0;
        if (!$editor.length || quoteId <= 0 || !ajaxUrl || !ajaxNonce) {
            return false;
        }

        if (isAjaxSaving) {
            return true;
        }

        var payload = serializeForSubmit();

        isAjaxSaving = true;
        setSaveStatus('saving');

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: ajaxAction,
                nonce: ajaxNonce,
                quote_id: quoteId,
                tah_quote_format: getSelectedQuoteFormat(),
                tah_quote_tax_rate: String($('#tah-quote-tax-rate').val() || '').trim(),
                groups_json: JSON.stringify(payload.groups),
                items_json: JSON.stringify(payload.items)
            }
        }).done(function (response) {
            if (!response || response.success !== true) {
                setSaveStatus('error', labels.saveError || 'Save failed');
                return;
            }

            setSaveStatus('success', labels.saveSaved || 'Saved');
        }).fail(function () {
            setSaveStatus('error', labels.saveError || 'Save failed');
        }).always(function () {
            isAjaxSaving = false;
        });

        return true;
    }

    function normalizeCatalogPrice(value) {
        if (typeof value === 'number') {
            return Number.isFinite(value) ? value : 0;
        }

        if (typeof value === 'string') {
            var cleaned = value.replace(/[^0-9.\-]/g, '');
            var parsed = Number(cleaned);
            return Number.isFinite(parsed) ? parsed : 0;
        }

        return 0;
    }

    function getEditorQuoteId() {
        var $editor = $('#tah-quote-pricing');
        return parseInt(String($editor.attr('data-quote-id') || '0'), 10) || 0;
    }

    function applyCatalogSuggestion($row, item) {
        var insuranceMode = isInsuranceFormat(getSelectedQuoteFormat());
        var title = String(item.title || item.label || item.value || item.sku || '').trim();
        if (title) {
            $row.find('.tah-line-title').val(title);
        }

        $row.find('.tah-line-pricing-item-id').val(String(item.id || 0));
        $row.find('.tah-line-description').val(String(item.description || ''));
        $row.find('.tah-line-unit-type').val(String(item.unit_type || 'flat'));
        $row.find('.tah-line-item-type').val('standard');

        var unitPrice = normalizeCatalogPrice(item.unit_price);
        var $rateInput = $row.find('.tah-line-rate');
        $rateInput.attr('data-formula', insuranceMode ? compactNumber(unitPrice) : '$');
        $rateInput.attr('data-resolved', compactNumber(unitPrice));
        $rateInput.val(formatCurrency(unitPrice));
        $row.find('.tah-line-catalog-price').val(compactNumber(unitPrice));

        if (insuranceMode) {
            $row.find('.tah-line-line-sku').val(String(item.sku || ''));
            $row.find('.tah-line-material-cost').val(compactNumber(unitPrice));
            $row.find('.tah-line-labor-cost').val('0');
            $row.find('.tah-line-tax-rate').val(String($row.find('.tah-line-tax-rate').val() || '').trim());
        }

        refreshTotals();
    }

    function fetchCatalogSuggestions(term, callback) {
        var quoteId = getEditorQuoteId();
        if (!ajaxUrl || !ajaxNonce || quoteId <= 0 || !term) {
            callback([]);
            return;
        }

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: ajaxSearchAction,
                nonce: ajaxNonce,
                quote_id: quoteId,
                tah_quote_format: getSelectedQuoteFormat(),
                term: term
            }
        }).done(function (response) {
            if (!response || response.success !== true || !response.data || !Array.isArray(response.data.items)) {
                callback([]);
                return;
            }

            var items = response.data.items.map(function (item) {
                var label = String(item.label || item.title || item.value || item.sku || '').trim();
                var unitPrice = normalizeCatalogPrice(item.unit_price);
                return {
                    label: label,
                    value: label,
                    item: item,
                    unitPrice: unitPrice
                };
            });

            callback(items);
        }).fail(function () {
            callback([]);
        });
    }

    function attachLineItemAutocomplete($input) {
        if (!$input.length || typeof $input.autocomplete !== 'function') {
            return;
        }

        if ($input.data('tahAutocompleteReady')) {
            return;
        }

        $input.autocomplete({
            minLength: 2,
            delay: 180,
            source: function (request, response) {
                fetchCatalogSuggestions(String(request.term || '').trim(), response);
            },
            focus: function (event, ui) {
                event.preventDefault();
                $input.val(ui.item.value);
            },
            select: function (event, ui) {
                event.preventDefault();
                applyCatalogSuggestion($input.closest('.tah-line-item-row'), ui.item.item || {});
            }
        });

        var instance = $input.autocomplete('instance');
        if (instance && typeof instance._renderItem === 'function') {
            instance._renderItem = function (ul, ui) {
                var priceText = formatCurrency(ui.item.unitPrice);
                var markup = '<div class="tah-pricing-suggest-item">' +
                    '<span class="tah-pricing-suggest-title">' + escHtml(ui.item.label) + '</span>' +
                    '<span class="tah-pricing-suggest-price">' + escHtml(priceText || (labels.suggestionNoPrice || 'No price')) + '</span>' +
                    '</div>';
                return $('<li>').append(markup).appendTo(ul);
            };
        }

        $input.data('tahAutocompleteReady', true);
    }

    function initAutocomplete($scope) {
        $scope.find('.tah-line-title').each(function () {
            attachLineItemAutocomplete($(this));
        });
    }

    $(function () {
        if (!$('#tah-quote-pricing').length) {
            return;
        }

        initGroupSortable();
        initLineSortable($('#tah-quote-pricing'));
        initAutocomplete($('#tah-quote-pricing'));

        applyQuoteFormatUi(getSelectedQuoteFormat());
        refreshTotals();

        $(document).on('focus', '.tah-line-qty', function () {
            var $input = $(this);
            $input.val(String($input.attr('data-formula') || $input.attr('data-resolved') || '0'));
            $input.select();
        });

        $(document).on('blur', '.tah-line-qty', function () {
            refreshTotals();
        });

        $(document).on('focus', '.tah-line-rate', function () {
            if (isInsuranceFormat(getSelectedQuoteFormat())) {
                return;
            }
            var $input = $(this);
            $input.val(String($input.attr('data-formula') || '$'));
            $input.select();
        });

        $(document).on('blur', '.tah-line-rate', function () {
            refreshTotals();
        });

        $(document).on('change input', '.tah-group-show-subtotal, .tah-group-collapsed, .tah-group-selection-mode, .tah-line-title, .tah-line-description, .tah-line-line-sku, .tah-line-material-cost, .tah-line-labor-cost, .tah-line-tax-rate, #tah-quote-tax-rate', function () {
            refreshTotals();
        });

        $(document).on('click', '.tah-line-note-toggle', function (event) {
            if (!isInsuranceFormat(getSelectedQuoteFormat())) {
                return;
            }

            event.preventDefault();
            var $cell = $(this).closest('.tah-cell-description');
            $cell.toggleClass('is-note-collapsed');
            if (!$cell.hasClass('is-note-collapsed')) {
                $cell.find('.tah-line-description').trigger('focus');
            }
        });

        $(document).on('click', '.tah-add-line-item', function (event) {
            event.preventDefault();
            var $group = $(this).closest('.tah-group-card');
            var $tbody = $group.find('.tah-line-items-body');
            var $row = $(buildRowHtml({
                title: '',
                quantity: 1,
                resolvedPrice: 0,
                rateFormula: '0',
                qtyFormula: '1'
            }));
            $tbody.append($row);
            initLineSortable($group);
            initAutocomplete($group);
            prepareRowForCurrentFormat($row);
            refreshTotals();
            $row.find('.tah-line-title').trigger('focus');
        });

        $(document).on('click', '.tah-delete-line', function (event) {
            event.preventDefault();
            var $group = $(this).closest('.tah-group-card');
            $(this).closest('.tah-line-item-row').remove();

            if (!$group.find('.tah-line-item-row').length) {
                $group.find('.tah-line-items-body').append(buildRowHtml({
                    title: '',
                    quantity: 1,
                    resolvedPrice: 0,
                    rateFormula: '0',
                    qtyFormula: '1'
                }));
                prepareRowForCurrentFormat($group.find('.tah-line-item-row').last());
                initLineSortable($group);
            }

            refreshTotals();
        });

        $(document).on('click', '.tah-delete-group', function (event) {
            event.preventDefault();
            $(this).closest('.tah-group-card').remove();
            refreshTotals();
        });

        $(document).on('click', '.tah-toggle-group', function (event) {
            event.preventDefault();
            var $group = $(this).closest('.tah-group-card');
            var $toggle = $(this);
            var $icon = $toggle.find('.dashicons');

            $group.toggleClass('is-collapsed');
            var isCollapsed = $group.hasClass('is-collapsed');
            $group.find('.tah-group-collapsed').prop('checked', isCollapsed);

            if (isCollapsed) {
                $icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
                $toggle.attr('aria-label', labels.expand || 'Expand group').attr('title', labels.expand || 'Expand group');
            } else {
                $icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
                $toggle.attr('aria-label', labels.collapse || 'Collapse group').attr('title', labels.collapse || 'Collapse group');
            }
        });

        $(document).on('change', '.tah-group-collapsed', function () {
            var $group = $(this).closest('.tah-group-card');
            var isCollapsed = $(this).is(':checked');
            $group.toggleClass('is-collapsed', isCollapsed);
            var $toggle = $group.find('.tah-toggle-group');
            var $icon = $toggle.find('.dashicons');

            if (isCollapsed) {
                $icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
            } else {
                $icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
            }
        });

        $(document).on('keydown', '.tah-line-title, .tah-line-description, .tah-line-qty, .tah-line-rate', function (event) {
            if (event.key !== 'Enter') {
                return;
            }

            event.preventDefault();
            var $currentRow = $(this).closest('.tah-line-item-row');
            var $newRow = insertRowAfter($currentRow);
            if ($newRow) {
                $newRow.find('.tah-line-title').trigger('focus');
            }
        });

        $('#tah-add-group').on('click', function (event) {
            event.preventDefault();
            groupCounter += 1;

            var key = 'group-new-' + Date.now() + '-' + groupCounter;
            var $group = $(buildGroupHtml(key));
            $('#tah-pricing-groups').append($group);
            initLineSortable($group);
            initAutocomplete($group);
            refreshTotals();
            $group.find('.tah-group-name').trigger('focus');
        });

        $('#post').on('submit', function () {
            serializeForSubmit();
        });

        $('#save-post').on('click', function (event) {
            if (saveDraftAjax()) {
                event.preventDefault();
            }
        });

        $(document).on('change', '#tah-quote-format', function () {
            applyQuoteFormatUi($(this).val());
        });

        $(document).on('change', 'input[name="tah_trade_term_id"]', function () {
            if (getSelectedQuoteFormat() !== 'standard') {
                return;
            }

            var tradeId = parseInt(String($(this).val() || '0'), 10) || 0;
            if (tradeId <= 0) {
                return;
            }
            applyTradePreset(tradeId);
        });

        $(document).on('keydown', function (event) {
            var isSaveShortcut = (event.ctrlKey || event.metaKey) && String(event.key || '').toLowerCase() === 's';
            if (!isSaveShortcut) {
                return;
            }

            event.preventDefault();
            saveDraftAjax();
        });
    });
})(jQuery);
