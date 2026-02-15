(function ($) {
    'use strict';

    var config = window.tahQuotePricingConfig || {};
    var labels = config.labels || {};
    var roundingMultiple = Number(config.rounding || 1);
    var roundingDirection = String(config.roundingDirection || 'nearest');
    var ajaxUrl = String(config.ajaxUrl || '');
    var ajaxAction = String(config.ajaxAction || 'tah_save_pricing');
    var ajaxNonce = String(config.ajaxNonce || '');

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
        var description = escHtml(data.description || '');
        var qty = normalizeNumber(data.quantity, 1);
        var rateResolved = normalizeNumber(data.resolvedPrice, 0);
        var rateFormula = escHtml(data.rateFormula || compactNumber(rateResolved));
        var qtyFormula = escHtml(data.qtyFormula || compactNumber(qty));

        return '' +
            '<tr class="tah-line-item-row" data-item-id="0">' +
            '<td class="tah-cell-handle"><span class="tah-drag-handle tah-line-handle" aria-hidden="true">' + dragHandleSvg() + '</span></td>' +
            '<td class="tah-cell-index"><span class="tah-line-index">1</span></td>' +
            '<td class="tah-cell-item">' +
            '<input type="text" class="tah-form-control tah-line-title" value="' + title + '" placeholder="Line item">' +
            '<input type="hidden" class="tah-line-id" value="0">' +
            '<input type="hidden" class="tah-line-pricing-item-id" value="0">' +
            '<input type="hidden" class="tah-line-item-type" value="standard">' +
            '<input type="hidden" class="tah-line-unit-type" value="flat">' +
            '<input type="hidden" class="tah-line-is-selected" value="1">' +
            '<input type="hidden" class="tah-line-material-cost" value="">' +
            '<input type="hidden" class="tah-line-labor-cost" value="">' +
            '<input type="hidden" class="tah-line-line-sku" value="">' +
            '<input type="hidden" class="tah-line-tax-rate" value="">' +
            '<input type="hidden" class="tah-line-note" value="">' +
            '<input type="hidden" class="tah-line-previous-resolved-price" value="">' +
            '</td>' +
            '<td class="tah-cell-description"><input type="text" class="tah-form-control tah-line-description" value="' + description + '" placeholder="Description"></td>' +
            '<td class="tah-cell-qty"><input type="text" class="tah-form-control tah-line-qty" value="' + compactNumber(qty) + '" data-formula="' + qtyFormula + '" data-resolved="' + compactNumber(qty) + '"></td>' +
            '<td class="tah-cell-rate">' +
            '<div class="tah-rate-field">' +
            '<input type="text" class="tah-form-control tah-line-rate" value="' + escHtml(formatCurrency(rateResolved)) + '" data-formula="' + rateFormula + '" data-resolved="' + compactNumber(rateResolved) + '">' +
            '<span class="tah-badge tah-badge--custom tah-line-rate-badge">' + escHtml(labels.customBadge || 'CUSTOM') + '</span>' +
            '<input type="hidden" class="tah-line-catalog-price" value="0">' +
            '</div>' +
            '</td>' +
            '<td class="tah-cell-amount"><span class="tah-line-amount" data-amount="' + compactNumber(qty * rateResolved) + '">' + escHtml(formatCurrency(qty * rateResolved)) + '</span></td>' +
            '<td class="tah-cell-margin"><span class="tah-line-margin">--</span></td>' +
            '<td class="tah-cell-actions"><button type="button" class="tah-icon-button tah-icon-button--danger tah-delete-line" aria-label="Delete line item" title="Delete line item"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button></td>' +
            '</tr>';
    }

    function buildGroupHtml(groupKey) {
        var groupName = escHtml(labels.groupDefaultName || 'New Group');

        return '' +
            '<section class="tah-group-card" data-group-id="0" data-group-key="' + escHtml(groupKey) + '">' +
            '<header class="tah-group-header">' +
            '<div class="tah-group-title-row">' +
            '<span class="tah-drag-handle tah-group-handle" aria-hidden="true">' + dragHandleSvg() + '</span>' +
            '<input type="text" class="tah-form-control tah-group-name" value="' + groupName + '" placeholder="Group name">' +
            '<div class="tah-group-actions">' +
            '<button type="button" class="tah-icon-button tah-toggle-group" aria-label="Collapse group" title="Collapse group"><span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span></button>' +
            '<button type="button" class="tah-icon-button tah-icon-button--danger tah-delete-group" aria-label="Delete group" title="Delete group"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>' +
            '</div>' +
            '</div>' +
            '<div class="tah-group-settings">' +
            '<input type="text" class="tah-form-control tah-group-description" value="" placeholder="Group description (optional)">' +
            '<label class="tah-group-setting">Selection <select class="tah-form-control tah-group-selection-mode"><option value="all">All</option><option value="multi">Multi</option><option value="single">Single</option></select></label>' +
            '<label class="tah-inline-checkbox"><input type="checkbox" class="tah-group-show-subtotal" checked> Show subtotal</label>' +
            '<label class="tah-inline-checkbox"><input type="checkbox" class="tah-group-collapsed"> Start collapsed</label>' +
            '</div>' +
            '</header>' +
            '<div class="tah-group-table-wrap">' +
            '<table class="tah-pricing-table-editor">' +
            '<thead><tr><th class="tah-col-handle"></th><th class="tah-col-index">#</th><th class="tah-col-item">Item</th><th class="tah-col-description">Description</th><th class="tah-col-qty">Qty</th><th class="tah-col-rate">Rate</th><th class="tah-col-amount">Amount</th><th class="tah-col-margin">Margin</th><th class="tah-col-actions"></th></tr></thead>' +
            '<tbody class="tah-line-items-body">' + buildRowHtml({ title: '', quantity: 1, resolvedPrice: 0, rateFormula: '0', qtyFormula: '1' }) + '</tbody>' +
            '</table>' +
            '<div class="tah-group-footer">' +
            '<button type="button" class="button button-secondary tah-add-line-item">+ Add Item</button>' +
            '<div class="tah-group-subtotal-row"><span class="tah-group-subtotal-label">Subtotal</span><strong class="tah-group-subtotal-value">$0.00</strong></div>' +
            '</div>' +
            '</div>' +
            '</section>';
    }

    function getFieldFormula($field) {
        if ($field.is(':focus')) {
            return String($field.val() || '').trim();
        }
        return String($field.attr('data-formula') || '').trim();
    }

    function refreshRateRow($row) {
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
        var showSubtotal = $group.find('.tah-group-show-subtotal').is(':checked');

        $group.find('.tah-line-item-row').each(function (idx) {
            var $row = $(this);
            $row.find('.tah-line-index').text(String(idx + 1));

            var qty = refreshQtyRow($row);
            var rateMeta = refreshRateRow($row);
            var amount = Number((qty * rateMeta.resolved).toFixed(2));

            $row.find('.tah-line-amount').attr('data-amount', compactNumber(amount)).text(formatCurrency(amount));
            refreshMargin($row, rateMeta.resolved);

            subtotal += amount;
        });

        subtotal = Number(subtotal.toFixed(2));
        $group.find('.tah-group-subtotal-value').text(formatCurrency(subtotal));
        $group.find('.tah-group-subtotal-row').toggle(showSubtotal);
        return subtotal;
    }

    function refreshTotals() {
        var grandTotal = 0;

        $('#tah-pricing-groups .tah-group-card').each(function () {
            grandTotal += refreshGroup($(this));
        });

        $('#tah-pricing-grand-total-value').text(formatCurrency(Number(grandTotal.toFixed(2))));
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

                out.push({
                    id: parseInt(String($row.find('.tah-line-id').val() || '0'), 10) || 0,
                    group_key: groupKey,
                    pricing_item_id: parseInt(String($row.find('.tah-line-pricing-item-id').val() || '0'), 10) || 0,
                    item_type: String($row.find('.tah-line-item-type').val() || 'standard'),
                    title: title,
                    description: String($row.find('.tah-line-description').val() || '').trim(),
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
                    note: String($row.find('.tah-line-note').val() || ''),
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

    $(function () {
        if (!$('#tah-quote-pricing').length) {
            return;
        }

        initGroupSortable();
        initLineSortable($('#tah-quote-pricing'));

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
            var $input = $(this);
            $input.val(String($input.attr('data-formula') || '$'));
            $input.select();
        });

        $(document).on('blur', '.tah-line-rate', function () {
            refreshTotals();
        });

        $(document).on('change input', '.tah-group-show-subtotal, .tah-group-collapsed, .tah-group-selection-mode, .tah-line-title, .tah-line-description', function () {
            refreshTotals();
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
