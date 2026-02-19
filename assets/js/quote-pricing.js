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
    var pricingTableKey = String(config.pricingTableKey || 'pricing_editor');
    var descriptionCollapsedHeightPx = 32;
    var descriptionExpandedMinHeightPx = 64;

    var groupCounter = 1;
    var saveStatusTimer = null;
    var autoSaveTimer = null;
    var isAjaxSaving = false;
    var hasPendingAjaxSave = false;

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

    function getPricingTemplateHeadHtml() {
        var template = document.getElementById('tah-pricing-table-head-template');
        if (!template || !template.innerHTML) {
            return '';
        }

        return String(template.innerHTML);
    }

    function extractColumnOrderFromHeadHtml(headHtml) {
        var html = String(headHtml || '').trim();
        if (html === '') {
            return [];
        }

        var wrapper = document.createElement('table');
        wrapper.innerHTML = html;

        var order = [];
        wrapper.querySelectorAll('thead th[data-tah-col]').forEach(function (th) {
            var key = String(th.getAttribute('data-tah-col') || '');
            if (key) {
                order.push(key);
            }
        });
        return order;
    }

    function getCurrentPricingColumnOrder() {
        var $firstTable = $('.tah-pricing-table-editor').first();
        var domOrder = [];
        if ($firstTable.length) {
            $firstTable.find('thead th').each(function () {
                var key = String($(this).attr('data-tah-col') || '');
                if (key) {
                    domOrder.push(key);
                }
            });
        }
        if (domOrder.length) {
            return domOrder;
        }

        var templateOrder = extractColumnOrderFromHeadHtml(getPricingTemplateHeadHtml());
        if (templateOrder.length) {
            return templateOrder;
        }

        return [];
    }

    function getPricingTableHeaderHtml() {
        var $firstHeader = $('.tah-pricing-table-editor thead').first();
        if ($firstHeader.length) {
            return '<thead>' + $firstHeader.html() + '</thead>';
        }
        return getPricingTemplateHeadHtml();
    }

    function getSelectedQuoteFormat() {
        var $field = $('#tah-quote-format');
        if ($field.length) {
            return normalizeQuoteFormat($field.val());
        }

        var $editor = $('#tah-quote-pricing');
        return normalizeQuoteFormat($editor.attr('data-quote-format'));
    }

    function getSelectedTradeId() {
        var $select = $('#tah-trade-term-id');
        if ($select.length) {
            return parseInt(String($select.val() || '0'), 10) || 0;
        }

        var $checkedRadio = $('input[name="tah_trade_term_id"]:checked');
        if ($checkedRadio.length) {
            return parseInt(String($checkedRadio.val() || '0'), 10) || 0;
        }

        return 0;
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
            return { mode: 'default', modifier: 0, normalizedFormula: '$', valid: true };
        }

        var addMatch = raw.match(/^\$\s*([+-])\s*(\d+(?:\.\d+)?)$/);
        if (addMatch) {
            var addModifier = Number(addMatch[2]);
            if (addMatch[1] === '-') {
                addModifier = addModifier * -1;
            }
            return { mode: 'addition', modifier: addModifier, normalizedFormula: raw, valid: true };
        }

        var pctMatch = raw.match(/^\$\s*\*\s*([+-]?\d+(?:\.\d+)?)$/);
        if (pctMatch) {
            return { mode: 'percentage', modifier: Number(pctMatch[1]), normalizedFormula: raw, valid: true };
        }

        var fixedMatch = raw.match(/^[+-]?\d+(?:\.\d+)?$/);
        if (fixedMatch) {
            return { mode: 'override', modifier: Number(raw), normalizedFormula: raw, valid: true };
        }

        var mathExpression = evaluateMathExpression(raw, NaN);
        if (mathExpression.valid) {
            var normalized = compactNumber(mathExpression.value);
            return { mode: 'override', modifier: mathExpression.value, normalizedFormula: normalized, valid: true };
        }

        return { mode: 'default', modifier: 0, normalizedFormula: raw, valid: false };
    }

    function setFormulaFieldValidity($field, isValid) {
        if (!$field || !$field.length) {
            return;
        }

        if (isValid) {
            $field.removeClass('is-invalid');
            $field.attr('aria-invalid', 'false');
            return;
        }

        $field.addClass('is-invalid');
        $field.attr('aria-invalid', 'true');
    }

    function hasInvalidFormulaFields() {
        return $('#tah-quote-pricing').find('.tah-line-qty.is-invalid, .tah-line-rate.is-invalid').length > 0;
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

    function isCustomRateState(pricingItemId, resolvedRate, basePrice) {
        if (!pricingItemId || pricingItemId <= 0) {
            return false;
        }

        var resolved = normalizeNumber(resolvedRate, 0);
        var base = normalizeNumber(basePrice, 0);
        return Math.abs(resolved - base) >= 0.005;
    }

    function dragHandleSvg() {
        return '<svg viewBox="0 0 32 32" class="svg-icon"><path d="M 14 5.5 a 3 3 0 1 1 -3 -3 A 3 3 0 0 1 14 5.5 Z m 7 3 a 3 3 0 1 0 -3 -3 A 3 3 0 0 0 21 8.5 Z m -10 4 a 3 3 0 1 0 3 3 A 3 3 0 0 0 11 12.5 Z m 10 0 a 3 3 0 1 0 3 3 A 3 3 0 0 0 21 12.5 Z m -10 10 a 3 3 0 1 0 3 3 A 3 3 0 0 0 11 22.5 Z m 10 0 a 3 3 0 1 0 3 3 A 3 3 0 0 0 21 22.5 Z"></path></svg>';
    }

    function createRowFromTemplate() {
        var template = document.getElementById('tah-pricing-row-template');
        if (!template || !template.innerHTML) {
            return null;
        }

        var wrapper = document.createElement('tbody');
        wrapper.innerHTML = String(template.innerHTML).trim();
        var row = wrapper.querySelector('tr.tah-line-item-row');
        return row ? row.cloneNode(true) : null;
    }

    function reorderRowCells($row, order) {
        var rowNode = $row.get(0);
        if (!rowNode) {
            return;
        }

        var cellMap = {};
        Array.prototype.slice.call(rowNode.children).forEach(function (cell) {
            var key = String(cell.getAttribute('data-tah-col') || '');
            if (key && !cellMap[key]) {
                cellMap[key] = cell;
            }
        });

        var finalOrder = [];
        var seen = {};
        order.forEach(function (key) {
            if (!key || seen[key] || !cellMap[key]) {
                return;
            }
            seen[key] = true;
            finalOrder.push(key);
        });
        Object.keys(cellMap).forEach(function (key) {
            if (seen[key] || !cellMap[key]) {
                return;
            }
            seen[key] = true;
            finalOrder.push(key);
        });

        finalOrder.forEach(function (key) {
            rowNode.appendChild(cellMap[key]);
        });
    }

    function buildRowHtml(data) {
        var title = data.title == null ? '' : String(data.title);
        var description = String(data.description || data.note || '');
        var qty = normalizeNumber(data.quantity, 1);
        var pricingItemId = parseInt(String(data.pricingItemId || 0), 10) || 0;
        var unitType = String(data.unitType || 'flat');
        var itemType = String(data.itemType || 'standard');
        var catalogPrice = normalizeNumber(data.catalogPrice, 0);
        var rateResolved = normalizeNumber(data.resolvedPrice, 0);
        var rateFormula = String(data.rateFormula || (pricingItemId > 0 ? '$' : compactNumber(rateResolved)));
        var qtyFormula = String(data.qtyFormula || compactNumber(qty));
        var customRateClass = isCustomRateState(pricingItemId, rateResolved, catalogPrice)
            ? ' tah-line-rate--custom'
            : '';
        var lineSku = String(data.lineSku || '');
        var materialCost = data.materialCost == null || data.materialCost === ''
            ? ''
            : compactNumber(normalizeNumber(data.materialCost, 0));
        var laborCost = data.laborCost == null || data.laborCost === ''
            ? ''
            : compactNumber(normalizeNumber(data.laborCost, 0));
        var taxRate = data.taxRate == null ? '' : String(data.taxRate);
        var note = String(data.note || '');

        // Determine Order from existing table headers (if present in DOM)
        // This ensures that when we add a NEW row, it matches the User's sorted order.
        var order = getCurrentPricingColumnOrder();
        if (!order.length) {
            console.error('TAH Quote Pricing: Unable to resolve pricing column order.');
            return '';
        }

        var rowElement = createRowFromTemplate();
        if (!rowElement) {
            console.error('TAH Quote Pricing: Row template is missing or invalid.');
            return '';
        }

        var $row = $(rowElement);
        reorderRowCells($row, order);

        var amount = qty * rateResolved;
        var $rateInput = $row.find('.tah-line-rate');
        $row.attr('data-item-id', '0');
        $row.find('.tah-line-index').text('1');
        $row.find('.tah-line-title').val(title);
        $row.find('.tah-line-id').val('0');
        $row.find('.tah-line-pricing-item-id').val(String(pricingItemId));
        $row.find('.tah-line-item-type').val(itemType);
        $row.find('.tah-line-unit-type').val(unitType);
        $row.find('.tah-line-is-selected').val('1');
        $row.find('.tah-line-note').val(note);
        $row.find('.tah-line-previous-resolved-price').val('');
        $row.find('.tah-line-line-sku').val(lineSku);
        $row.find('.tah-line-description').val(description);
        $row.find('.tah-line-material-cost').val(materialCost);
        $row.find('.tah-line-labor-cost').val(laborCost);
        $row.find('.tah-line-qty')
            .val(compactNumber(qty))
            .attr('data-formula', qtyFormula)
            .attr('data-resolved', compactNumber(qty));
        $rateInput
            .val(formatCurrency(rateResolved))
            .attr('data-formula', rateFormula)
            .attr('data-resolved', compactNumber(rateResolved))
            .removeClass('tah-line-rate--custom')
            .addClass(customRateClass.trim());
        $row.find('.tah-line-catalog-price').val(compactNumber(catalogPrice));
        $row.find('.tah-line-tax-rate').val(taxRate);
        $row.find('.tah-line-tax-amount').text('$0.00');
        $row.find('.tah-line-amount')
            .attr('data-amount', compactNumber(amount))
            .text(formatCurrency(amount));
        $row.find('.tah-line-margin').text('--');

        return $row.get(0).outerHTML;
    }

    function collapseDescriptionField($field) {
        if (!$field || !$field.length) {
            return;
        }

        var $row = $field.closest('.tah-line-item-row');
        if ($row.length) {
            $row.removeClass('is-description-expanded');
        }

        $field.css({
            height: descriptionCollapsedHeightPx + 'px',
            minHeight: descriptionCollapsedHeightPx + 'px',
            overflowY: 'hidden'
        });
    }

    function expandDescriptionField($field) {
        if (!$field || !$field.length) {
            return;
        }

        var element = $field.get(0);
        if (!element) {
            return;
        }

        var $row = $field.closest('.tah-line-item-row');
        if ($row.length) {
            $row.addClass('is-description-expanded');
        }

        element.style.height = 'auto';
        var nextHeight = Math.max(descriptionExpandedMinHeightPx, Math.ceil(element.scrollHeight || 0));
        if (!nextHeight || !Number.isFinite(nextHeight)) {
            nextHeight = descriptionExpandedMinHeightPx;
        }

        $field.css({
            height: nextHeight + 'px',
            minHeight: descriptionExpandedMinHeightPx + 'px',
            overflowY: 'hidden'
        });
    }

    function syncDescriptionFieldHeight($field) {
        if (!$field || !$field.length) {
            return;
        }

        var element = $field.get(0);
        if (!element) {
            return;
        }

        $field.css({
            minHeight: descriptionCollapsedHeightPx + 'px',
            height: 'auto',
            overflowY: 'hidden'
        });
        var scrollHeight = Math.ceil(element.scrollHeight || 0);
        var shouldExpand = scrollHeight > (descriptionCollapsedHeightPx + 1);

        if (shouldExpand) {
            expandDescriptionField($field);
            return;
        }

        collapseDescriptionField($field);
    }

    function initDescriptionFields($scope) {
        var $context = $scope && $scope.length ? $scope : $(document);
        $context.find('.tah-line-description').each(function () {
            collapseDescriptionField($(this));
        });
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
        var toggleLabel = 'Collapse group';
        var toggleIcon = 'dashicons-arrow-up-alt2';
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
        var groupClasses = 'tah-group-card';
        var quoteVariant = normalizeQuoteFormat(getSelectedQuoteFormat());

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
            '<table class="tah-pricing-table-editor tah-resizable-table" data-tah-table="' + pricingTableKey + '" data-tah-variant="' + quoteVariant + '">' +
            getPricingTableHeaderHtml() +
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
            var $newPrimaryRow = $(buildRowHtml({
                title: '',
                quantity: 1,
                resolvedPrice: 0,
                rateFormula: '0',
                qtyFormula: '1'
            }));
            $primaryBody.append($newPrimaryRow);
            $(document).trigger('tah:table_row_added', [$newPrimaryRow]);
        }

        $primary.find('.tah-group-name').val(labels.insuranceGroupName || 'Insurance Items');
        $primary.find('.tah-group-show-subtotal').prop('checked', false);
        initLineSortable($primary);
        initAutocomplete($primary);
    }

    function applyTradeContextFilter(format) {
        var normalizedFormat = normalizeQuoteFormat(format);
        var $select = $('#tah-trade-term-id');
        if ($select.length) {
            var selectedTradeId = getSelectedTradeId();
            var selectedAllowed = selectedTradeId === 0;

            $select.find('option').each(function () {
                var $option = $(this);
                var tradeId = parseInt(String($option.val() || '0'), 10) || 0;
                if (tradeId === 0) {
                    $option.prop('disabled', false).prop('hidden', false);
                    return;
                }

                var context = normalizeTradeContext(tradeContexts[String(tradeId)]);
                var isAllowed = isTradeContextAllowed(normalizedFormat, context);
                $option.prop('disabled', !isAllowed).prop('hidden', !isAllowed);
                if (tradeId === selectedTradeId && isAllowed) {
                    selectedAllowed = true;
                }
            });

            if (!selectedAllowed) {
                $select.val('0').trigger('change');
            }
            return;
        }

        var hadActiveSelection = false;
        var activeSelectionStillVisible = false;
        $('input[name="tah_trade_term_id"]').each(function () {
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
            $('input[name="tah_trade_term_id"][value="0"]').prop('checked', true).trigger('change');
        }
    }

    function updateTableHeaderLabels(format) {
        var normalizedFormat = normalizeQuoteFormat(format);
        var useInsurance = normalizedFormat === 'insurance';

        $('#tah-pricing-groups .tah-pricing-table-editor th[data-standard-label]').each(function () {
            var $th = $(this);
            var next = useInsurance ? $th.attr('data-insurance-label') : $th.attr('data-standard-label');
            if (next) {
                var $resizeHandle = $th.children('.tah-admin-resize-handle').detach();
                $th.text(next);
                if ($resizeHandle.length) {
                    $th.append($resizeHandle);
                }
            }
        });
    }

    function applyQuoteFormatUi(format) {
        var normalizedFormat = normalizeQuoteFormat(format);
        var isInsurance = normalizedFormat === 'insurance';
        var $editor = $('#tah-quote-pricing');

        $editor.attr('data-quote-format', normalizedFormat);
        $editor.toggleClass('is-quote-format-insurance', isInsurance);
        $editor.find('.tah-pricing-table-editor').attr('data-tah-variant', normalizedFormat);
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
        $(document).trigger('tah:table_layout_changed', [$editor.find('.tah-pricing-table-editor')]);
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
            initDescriptionFields($groupsWrap);
            // Announce new table to the Admin Table Manager
            $groupsWrap.find('.tah-pricing-table-editor').last().each(function () {
                $(document).trigger('tah:table_added', [$(this)]);
            });
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
        initDescriptionFields($groupsWrap);
        // Announce new tables to the Admin Table Manager
        $groupsWrap.find('.tah-pricing-table-editor').each(function () {
            $(document).trigger('tah:table_added', [$(this)]);
        });
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
            $insuranceRateInput.removeClass('tah-line-rate--custom');
            setFormulaFieldValidity($insuranceRateInput, true);

            return {
                parsed: { mode: 'override', modifier: unitPrice },
                resolved: unitPrice,
                materialCost: materialCost,
                laborCost: laborCost,
                taxRate: taxRate
            };
        }

        var $rateInput = $row.find('.tah-line-rate');
        var basePrice = normalizeNumber($row.find('.tah-line-catalog-price').val(), 0);
        var previousResolved = normalizeNumber($rateInput.attr('data-resolved'), 0);
        var formulaRaw = getFieldFormula($rateInput);
        var parsed = parseRateFormula(formulaRaw);
        var pricingItemId = parseInt(String($row.find('.tah-line-pricing-item-id').val() || '0'), 10) || 0;
        if (!parsed.valid) {
            var invalidFallback = previousResolved;
            setFormulaFieldValidity($rateInput, false);
            $rateInput.attr('data-formula', formulaRaw === '' ? '$' : formulaRaw);
            $rateInput.attr('data-resolved', compactNumber(invalidFallback));
            if (!$rateInput.is(':focus')) {
                $rateInput.val(formulaRaw === '' ? '$' : formulaRaw);
            }
            $rateInput.toggleClass('tah-line-rate--custom', isCustomRateState(pricingItemId, invalidFallback, basePrice));

            return {
                parsed: parsed,
                resolved: invalidFallback
            };
        }

        setFormulaFieldValidity($rateInput, true);
        var resolved = resolveRate(parsed, basePrice, previousResolved);

        $rateInput.attr('data-formula', String(parsed.normalizedFormula || (formulaRaw === '' ? '$' : formulaRaw)));
        $rateInput.attr('data-resolved', compactNumber(resolved));
        if (!$rateInput.is(':focus')) {
            $rateInput.val(formatCurrency(resolved));
        }
        $rateInput.toggleClass('tah-line-rate--custom', isCustomRateState(pricingItemId, resolved, basePrice));

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
        if (!result.valid) {
            setFormulaFieldValidity($qtyInput, false);
            $qtyInput.attr('data-formula', formulaRaw === '' ? compactNumber(fallback) : formulaRaw);
            $qtyInput.attr('data-resolved', compactNumber(fallback));
            if (!$qtyInput.is(':focus')) {
                $qtyInput.val(formulaRaw === '' ? compactNumber(fallback) : formulaRaw);
            }
            return fallback;
        }

        setFormulaFieldValidity($qtyInput, true);
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

        $(document).trigger('tah:table_row_added', [$newRow]);
        initDescriptionFields($newRow);
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

        if (autoSaveTimer) {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = null;
        }

        var payload = serializeForSubmit();

        if (hasInvalidFormulaFields()) {
            setSaveStatus('error', labels.invalidFormula || 'Invalid formula');
            return false;
        }

        if (isAjaxSaving) {
            hasPendingAjaxSave = true;
            return true;
        }

        hasPendingAjaxSave = false;
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
            if (hasPendingAjaxSave) {
                hasPendingAjaxSave = false;
                saveDraftAjax();
            }
        });

        return true;
    }

    function scheduleDraftSave() {
        var quoteId = getEditorQuoteId();
        if (quoteId <= 0 || !ajaxUrl || !ajaxNonce) {
            return;
        }

        if (autoSaveTimer) {
            clearTimeout(autoSaveTimer);
        }

        autoSaveTimer = window.setTimeout(function () {
            autoSaveTimer = null;
            saveDraftAjax();
        }, 260);
    }

    function commitFormulaFromInput($field, fallbackFormula) {
        if (!$field || !$field.length) {
            return;
        }

        var raw = String($field.val() || '').trim();
        if (raw === '') {
            $field.attr('data-formula', String(fallbackFormula || ''));
            return;
        }

        $field.attr('data-formula', raw);
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
            initDescriptionFields($('#tah-quote-pricing'));
            refreshTotals();

        $(document).on('focus', '.tah-line-qty', function () {
            var $input = $(this);
            $input.val(String($input.attr('data-formula') || $input.attr('data-resolved') || '0'));
            $input.select();
        });

        $(document).on('blur', '.tah-line-qty', function () {
            commitFormulaFromInput($(this), '0');
            refreshTotals();
            scheduleDraftSave();
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
            commitFormulaFromInput($(this), '$');
            refreshTotals();
            scheduleDraftSave();
        });

        $(document).on('input', '.tah-line-qty, .tah-line-rate', function () {
            setFormulaFieldValidity($(this), true);
            if (!isAjaxSaving) {
                setSaveStatus('clear');
            }
        });

        $(document).on('change input', '.tah-group-show-subtotal, .tah-group-collapsed, .tah-group-selection-mode, .tah-line-title, .tah-line-description, .tah-line-line-sku, .tah-line-material-cost, .tah-line-labor-cost, .tah-line-tax-rate, #tah-quote-tax-rate', function () {
            refreshTotals();
        });

        $(document).on('focus', '.tah-line-description', function () {
            syncDescriptionFieldHeight($(this));
        });

        $(document).on('input', '.tah-line-description', function () {
            var $field = $(this);
            if ($field.is(':focus')) {
                syncDescriptionFieldHeight($field);
            }
        });

        $(document).on('blur', '.tah-line-description', function () {
            collapseDescriptionField($(this));
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
            $(document).trigger('tah:table_row_added', [$row]);
            initLineSortable($group);
            initAutocomplete($group);
            initDescriptionFields($row);
            prepareRowForCurrentFormat($row);
            refreshTotals();
            $row.find('.tah-line-title').trigger('focus');
        });

        $(document).on('click', '.tah-delete-line', function (event) {
            event.preventDefault();
            var $group = $(this).closest('.tah-group-card');
            $(this).closest('.tah-line-item-row').remove();

            if (!$group.find('.tah-line-item-row').length) {
                var $newRow = $(buildRowHtml({
                    title: '',
                    quantity: 1,
                    resolvedPrice: 0,
                    rateFormula: '0',
                    qtyFormula: '1'
                }));
                $group.find('.tah-line-items-body').append($newRow);
                $(document).trigger('tah:table_row_added', [$newRow]);
                initDescriptionFields($newRow);
                prepareRowForCurrentFormat($newRow);
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

            if (isCollapsed) {
                $icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
                $toggle.attr('aria-label', labels.expand || 'Expand group').attr('title', labels.expand || 'Expand group');
            } else {
                $icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
                $toggle.attr('aria-label', labels.collapse || 'Collapse group').attr('title', labels.collapse || 'Collapse group');
            }

            $(document).trigger('tah:table_layout_changed', [$group.find('table')]);
        });

        $(document).on('keydown', '.tah-line-title, .tah-line-qty, .tah-line-rate', function (event) {
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
            // Announce new table to Admin Table Manager
            $(document).trigger('tah:table_added', [$group.find('table')]);
            initLineSortable($group);
            initAutocomplete($group);
            initDescriptionFields($group);
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

        $(document).on('change', '#tah-trade-term-id, input[name="tah_trade_term_id"]', function () {
            if (getSelectedQuoteFormat() !== 'standard') {
                return;
            }

            var tradeId = getSelectedTradeId();
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
