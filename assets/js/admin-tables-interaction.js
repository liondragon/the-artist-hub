(function ($, window) {
    'use strict';

    var namespace = window.TAHAdminTables = window.TAHAdminTables || {};
    var constants = namespace.Constants;
    if (!constants || !constants.widths || !constants.sort) {
        console.error('TAH Admin Tables: Missing constants module for interaction.');
        return;
    }

    var widthConstants = constants.widths;
    var savedSanity = widthConstants.savedSanity;
    var saveBounds = widthConstants.saveBounds || {};
    var defaultMinWidthPx = widthConstants.minPx;
    var normalizeEpsilonPx = widthConstants.normalizeEpsilonPx;
    var savedMinFactor = savedSanity.minFactor;
    var savedMaxFactor = Math.max(savedSanity.maxFactor, Number(saveBounds.maxFactor) || savedSanity.maxFactor);

    var sortConstants = constants.sort;
    var dragDistancePx = sortConstants.dragDistancePx;
    var dragOpacity = sortConstants.dragOpacity;
    var helperZIndex = sortConstants.helperZIndex;

    var resizeStateKey = 'tah-resize-state';
    var sortSessionKey = 'tah-sort-drag-session';
    var reorderArmKey = 'tah-sort-reorder-arm';

    function sanitizeNamespacePart(value) {
        return String(value || '')
            .replace(/[^a-zA-Z0-9_-]/g, '_')
            .toLowerCase();
    }

    function getResizeNamespace($table) {
        var contextKey = $table.data('tah-pref-context-key') || '';
        var tableKey = $table.attr('data-tah-table') || '';
        var rawKey = contextKey || tableKey || 'table';
        return '.tahResize_' + sanitizeNamespacePart(rawKey);
    }

    function getReorderNamespace($table) {
        var contextKey = $table.data('tah-pref-context-key') || '';
        var tableKey = $table.attr('data-tah-table') || '';
        var rawKey = contextKey || tableKey || 'table';
        return '.tahReorder_' + sanitizeNamespacePart(rawKey);
    }

    namespace.Interaction = {
        getColumnConfig: function (tableConfig, columnKey) {
            var key = String(columnKey || '');
            if (!key) {
                return null;
            }

            var config = tableConfig || {};
            if (!config.columns || typeof config.columns !== 'object') {
                return null;
            }

            var columnConfig = config.columns[key];
            if (!columnConfig || typeof columnConfig !== 'object') {
                return null;
            }

            return columnConfig;
        },

        getColumnWidthVarName: function (columnKey) {
            var safeKey = String(columnKey || '')
                .replace(/[^a-zA-Z0-9_-]/g, '_')
                .toLowerCase();
            return '--tah-col-' + safeKey + '-width';
        },

        setColumnWidth: function ($table, $col, columnKey, widthPx) {
            if (!$table || !$table.length || !$col || !$col.length || !columnKey) {
                return;
            }

            var value = Number(widthPx);
            if (!Number.isFinite(value) || value <= 0) {
                return;
            }

            var width = Math.round(value) + 'px';
            var varName = this.getColumnWidthVarName(columnKey);
            var tableEl = $table.get(0);

            if (tableEl && tableEl.style) {
                tableEl.style.setProperty(varName, width);
            }

            $col.css('width', 'var(' + varName + ')');
        },

        setTableFrameWidth: function ($table, widthPx) {
            if (!$table || !$table.length) {
                return;
            }

            var nextWidth = Number(widthPx);
            if (!Number.isFinite(nextWidth) || nextWidth <= 0) {
                $table.css({
                    width: '',
                    minWidth: ''
                });
                return;
            }

            var rounded = Math.round(nextWidth) + 'px';
            $table.css({
                width: rounded,
                minWidth: rounded
            });
        },

        clearColumnWidth: function ($table, $col, columnKey) {
            if (!$table || !$table.length || !$col || !$col.length || !columnKey) {
                return;
            }

            var varName = this.getColumnWidthVarName(columnKey);
            var tableEl = $table.get(0);
            if (tableEl && tableEl.style) {
                tableEl.style.removeProperty(varName);
            }

            $col.css('width', 'var(' + varName + ')');
        },

        getColumnWidth: function ($table, $col, $th) {
            if (!$col || !$col.length) {
                return 0;
            }

            var colEl = $col.get(0);
            if (colEl && typeof colEl.getBoundingClientRect === 'function') {
                var rectWidth = colEl.getBoundingClientRect().width;
                if (Number.isFinite(rectWidth) && rectWidth > 0) {
                    return rectWidth;
                }
            }

            var cssWidth = parseFloat($col.css('width'));
            if (Number.isFinite(cssWidth) && cssWidth > 0) {
                return cssWidth;
            }

            if ($th && $th.length) {
                var thWidth = $th.outerWidth();
                if (Number.isFinite(thWidth) && thWidth > 0) {
                    return thWidth;
                }
            }

            return 0;
        },

        getStableContainerWidth: function ($table) {
            if (!$table || !$table.length || !$table.is(':visible')) {
                return 0;
            }

            var $postbox = $table.closest('.postbox');
            if ($postbox.length && $postbox.hasClass('closed')) {
                return 0;
            }

            var tableEl = $table.get(0);
            if (!tableEl || typeof tableEl.getBoundingClientRect !== 'function') {
                return 0;
            }

            var tableRect = tableEl.getBoundingClientRect();
            if (!Number.isFinite(tableRect.width) || tableRect.width <= 0 || !Number.isFinite(tableRect.height) || tableRect.height <= 0) {
                return 0;
            }

            var parentEl = $table.parent().get(0);
            if (!parentEl || typeof parentEl.getBoundingClientRect !== 'function') {
                return 0;
            }

            var parentRect = parentEl.getBoundingClientRect();
            if (!Number.isFinite(parentRect.width) || parentRect.width <= 0) {
                return 0;
            }
            var parentStyle = window.getComputedStyle(parentEl);
            var paddingLeft = parseFloat(parentStyle.paddingLeft || '0');
            var paddingRight = parseFloat(parentStyle.paddingRight || '0');
            var horizontalPadding = 0;
            if (Number.isFinite(paddingLeft) && paddingLeft > 0) {
                horizontalPadding += paddingLeft;
            }
            if (Number.isFinite(paddingRight) && paddingRight > 0) {
                horizontalPadding += paddingRight;
            }

            var clientWidth = Number(parentEl.clientWidth || 0);
            var contentWidth = clientWidth > 0 ? clientWidth - horizontalPadding : 0;
            if (Number.isFinite(contentWidth) && contentWidth > 0) {
                return Math.round(contentWidth);
            }

            // Fallback when client metrics are unavailable.
            return Math.round(parentRect.width);
        },

        getAssignedColumnWidth: function ($table, columnKey) {
            if (!$table || !$table.length || !columnKey) {
                return 0;
            }

            var tableEl = $table.get(0);
            if (!tableEl || !tableEl.style) {
                return 0;
            }

            var varName = this.getColumnWidthVarName(columnKey);
            var assigned = parseFloat(tableEl.style.getPropertyValue(varName) || '0');
            if (!Number.isFinite(assigned) || assigned <= 0) {
                return 0;
            }

            return assigned;
        },

        getVisibleColumnWidthSum: function ($table) {
            var self = this;
            var total = 0;

            this.getVisibleHeaders($table).forEach(function (header) {
                var key = header.key;
                if (!key) {
                    return;
                }

                var $col = $table.find('colgroup col[data-col-key="' + key + '"]');
                if (!$col.length) {
                    return;
                }

                var width = self.getAssignedColumnWidth($table, key);
                if (!Number.isFinite(width) || width <= 0) {
                    width = self.getColumnWidth($table, $col, header.$th);
                }
                if (!Number.isFinite(width) || width <= 0) {
                    return;
                }
                total += width;
            });

            return Math.round(total);
        },

        getColumnMinWidthPx: function (tableConfig, columnKey) {
            var minWidth = defaultMinWidthPx;
            var columnConfig = this.getColumnConfig(tableConfig, columnKey);

            if (columnConfig && Number.isFinite(Number(columnConfig.min_px_resolved))) {
                minWidth = Math.max(minWidth, Math.round(Number(columnConfig.min_px_resolved)));
            }

            return minWidth;
        },

        getDefaultColumnMaxWidthPx: function ($table) {
            var containerWidth = this.getStableContainerWidth($table);
            var saveBounds = widthConstants.saveBounds;
            var hardCap = Number(saveBounds.fallbackMaxPx) || 0;

            if (containerWidth > 0) {
                var derivedMax = Math.max(saveBounds.maxFloorPx, Math.round(containerWidth * saveBounds.maxFactor));
                if (hardCap > 0) {
                    return Math.min(derivedMax, hardCap);
                }
                return derivedMax;
            }

            return saveBounds.fallbackMaxPx;
        },

        getColumnMaxWidthPx: function ($table, tableConfig, columnKey, minWidthPx) {
            var columnConfig = this.getColumnConfig(tableConfig, columnKey);
            var maxWidth = this.getDefaultColumnMaxWidthPx($table);

            if (columnConfig && Number.isFinite(Number(columnConfig.max_px_resolved))) {
                maxWidth = Math.min(maxWidth, Math.round(Number(columnConfig.max_px_resolved)));
            }

            if (Number.isFinite(Number(minWidthPx))) {
                maxWidth = Math.max(maxWidth, Math.round(Number(minWidthPx)));
            }

            return maxWidth;
        },

        getColumnBaseWidthPx: function (tableConfig, columnKey, minWidthPx, maxWidthPx) {
            var columnConfig = this.getColumnConfig(tableConfig, columnKey);
            if (!columnConfig || !Number.isFinite(Number(columnConfig.base_px_resolved))) {
                return 0;
            }

            var baseWidth = Math.round(Number(columnConfig.base_px_resolved));
            if (Number.isFinite(Number(minWidthPx))) {
                baseWidth = Math.max(baseWidth, Math.round(Number(minWidthPx)));
            }
            if (Number.isFinite(Number(maxWidthPx))) {
                baseWidth = Math.min(baseWidth, Math.round(Number(maxWidthPx)));
            }

            return baseWidth > 0 ? baseWidth : 0;
        },

        isColumnNonResizable: function (tableConfig, columnKey) {
            var columnConfig = this.getColumnConfig(tableConfig, columnKey);
            if (!columnConfig) {
                return false;
            }

            return columnConfig.resizable === false;
        },

        isColumnOrderable: function (tableConfig, columnKey) {
            var columnConfig = this.getColumnConfig(tableConfig, columnKey);
            if (!columnConfig) {
                return true;
            }

            return columnConfig.orderable !== false;
        },

        isColumnVisible: function (tableConfig, columnKey) {
            var columnConfig = this.getColumnConfig(tableConfig, columnKey);
            if (!columnConfig) {
                return true;
            }

            return columnConfig.visible !== false;
        },

        parseColumns: function ($table) {
            var cols = [];
            $table.find('thead th[data-tah-col]').each(function () {
                var $th = $(this);
                var key = $th.attr('data-tah-col') || '';
                if (!key) {
                    return;
                }
                cols.push({
                    key: key,
                    el: $th,
                    locked: $th.attr('data-tah-locked') === '1'
                });
            });
            return cols;
        },

        getVisibleHeaders: function ($table) {
            var visibleHeaders = [];
            $table.find('thead th').each(function (index) {
                var $th = $(this);
                if ($th.css('display') === 'none') {
                    return;
                }

                visibleHeaders.push({
                    index: index,
                    $th: $th,
                    key: String($th.attr('data-tah-col') || ''),
                    locked: $th.attr('data-tah-locked') === '1'
                });
            });

            return visibleHeaders;
        },

        getVisibleHeaderContext: function ($table) {
            var visibleHeaders = this.getVisibleHeaders($table);
            var firstVisibleIndex = -1;
            var lastVisibleIndex = -1;
            var lastDataVisibleIndex = -1;

            if (!visibleHeaders.length) {
                return {
                    visibleHeaders: [],
                    firstVisibleIndex: firstVisibleIndex,
                    lastVisibleIndex: lastVisibleIndex,
                    lastDataVisibleIndex: lastDataVisibleIndex
                };
            }

            firstVisibleIndex = visibleHeaders[0].index;
            lastVisibleIndex = visibleHeaders[visibleHeaders.length - 1].index;
            lastDataVisibleIndex = lastVisibleIndex;

            for (var i = visibleHeaders.length - 1; i >= 0; i -= 1) {
                if (!visibleHeaders[i].locked && visibleHeaders[i].key) {
                    lastDataVisibleIndex = visibleHeaders[i].index;
                    break;
                }
            }

            return {
                visibleHeaders: visibleHeaders,
                firstVisibleIndex: firstVisibleIndex,
                lastVisibleIndex: lastVisibleIndex,
                lastDataVisibleIndex: lastDataVisibleIndex
            };
        },

        isResizeEdgeEligible: function (header, context, tableConfig) {
            if (!header || !header.key || !context) {
                return false;
            }
            if (header.locked || this.isColumnNonResizable(tableConfig, header.key)) {
                return false;
            }
            // Keep edge affordance model aligned with divider rendering: no edge at/after data tail.
            if (header.index >= context.lastDataVisibleIndex) {
                return false;
            }
            return true;
        },

        getResizeEdgeEligibleKeyMap: function ($table, tableConfig) {
            var keyMap = {};
            var context = this.getVisibleHeaderContext($table);
            var self = this;

            context.visibleHeaders.forEach(function (header) {
                if (self.isResizeEdgeEligible(header, context, tableConfig)) {
                    keyMap[header.key] = true;
                }
            });

            return keyMap;
        },

        getConfiguredFillerKey: function (tableConfig) {
            var config = tableConfig || {};
            if (!Object.prototype.hasOwnProperty.call(config, 'filler_column_key')) {
                return '';
            }

            var raw = String(config.filler_column_key || '').toLowerCase();
            return raw.replace(/[^a-z0-9_-]/g, '');
        },

        sanitizeSavedWidths: function ($table, savedWidths, tableConfig) {
            if (!savedWidths || typeof savedWidths !== 'object') {
                return {};
            }

            var tableWidth = this.getStableContainerWidth($table);
            if (!tableWidth) {
                return savedWidths;
            }

            var estimatedTotal = 0;
            var hasVisibleSavedWidths = false;
            var self = this;

            $table.find('thead th:visible').each(function () {
                var key = $(this).attr('data-tah-col');
                if (!key) {
                    return;
                }

                var savedWidth = Number(savedWidths[key]);
                if (Number.isFinite(savedWidth) && savedWidth > 0) {
                    hasVisibleSavedWidths = true;
                    estimatedTotal += savedWidth;
                    return;
                }

                var minEstimate = self.getColumnMinWidthPx(tableConfig, key);
                estimatedTotal += minEstimate;
            });

            if (hasVisibleSavedWidths && (estimatedTotal > tableWidth * savedMaxFactor || estimatedTotal < tableWidth * savedMinFactor)) {
                return {};
            }

            return savedWidths;
        },

        setupColGroup: function ($table, columns, savedWidths) {
            var self = this;
            var $colgroup = $table.find('colgroup');
            if (!$colgroup.length) {
                $colgroup = $('<colgroup></colgroup>').prependTo($table);
            } else {
                $colgroup.empty();
            }

            columns.forEach(function (col) {
                var $col = $('<col>').attr('data-col-key', col.key);
                if (col.key) {
                    $col.css('width', 'var(' + self.getColumnWidthVarName(col.key) + ')');
                }
                if (savedWidths && savedWidths[col.key]) {
                    self.setColumnWidth($table, $col, col.key, savedWidths[col.key]);
                } else if (col.key) {
                    // Reset any stale runtime var so width can re-seed from live layout.
                    self.clearColumnWidth($table, $col, col.key);
                }
                $colgroup.append($col);
            });

            // Keep table frame controlled by CSS layout; widths are applied via colgroup only.
            $table.css({
                width: '',
                minWidth: ''
            });
        },

        reorderTable: function (core, $table, orderArray, tableConfig) {
            var $thead = $table.find('thead tr');
            var headerMap = {};
            var currentOrder = [];
            var nextOrder = [];
            var seen = {};

            $thead.children('th[data-tah-col]').each(function () {
                var key = String($(this).attr('data-tah-col') || '');
                if (!key || headerMap[key]) {
                    return;
                }
                headerMap[key] = $(this);
                currentOrder.push(key);
            });

            orderArray.forEach(function (key) {
                if (Object.prototype.hasOwnProperty.call(headerMap, key) && !seen[key]) {
                    nextOrder.push(key);
                    seen[key] = true;
                }
            });

            currentOrder.forEach(function (key) {
                if (!seen[key]) {
                    nextOrder.push(key);
                }
            });

            nextOrder.forEach(function (key) {
                $thead.append(headerMap[key]);
            });

            var $colgroup = $table.find('colgroup');
            var colMap = {};
            $colgroup.children('col').each(function () {
                var key = String($(this).attr('data-col-key') || '');
                if (!key || colMap[key]) {
                    return;
                }
                colMap[key] = $(this);
            });
            nextOrder.forEach(function (key) {
                if (colMap[key]) {
                    $colgroup.append(colMap[key]);
                }
            });

            core.getManagedRows($table, tableConfig).each(function () {
                var $row = $(this);
                var cellMap = {};
                $row.children('td[data-tah-col]').each(function () {
                    var key = String($(this).attr('data-tah-col') || '');
                    if (!key || cellMap[key]) {
                        return;
                    }
                    cellMap[key] = $(this);
                });
                nextOrder.forEach(function (key) {
                    if (cellMap[key]) {
                        $row.append(cellMap[key]);
                    }
                });
            });
        },

        normalizeVisibleColumnWidths: function ($table, tableConfig) {
            var self = this;

            var containerWidth = this.getStableContainerWidth($table);
            if (!containerWidth) {
                return;
            }

            var visibleHeaders = this.getVisibleHeaders($table);
            var visibleCols = [];
            var flexCols = [];
            var totalWidth = 0;

            visibleHeaders.forEach(function (header) {
                var $th = header.$th;
                var key = header.key;
                if (!key) {
                    return;
                }

                var $col = $table.find('colgroup col[data-col-key="' + key + '"]');
                if (!$col.length) {
                    return;
                }

                var minWidth = self.getColumnMinWidthPx(tableConfig, key);
                var maxWidth = self.getColumnMaxWidthPx($table, tableConfig, key, minWidth);
                var width = self.getAssignedColumnWidth($table, key);
                if (!Number.isFinite(width) || width <= 0) {
                    width = self.getColumnBaseWidthPx(tableConfig, key, minWidth, maxWidth);
                }
                if (!Number.isFinite(width) || width <= 0) {
                    // Deterministic seed path when no saved/runtime width exists:
                    // use config-derived minimum instead of browser layout reads.
                    width = minWidth;
                }
                if (!Number.isFinite(width) || width <= 0) {
                    return;
                }
                var isFixedWidth = header.locked || self.isColumnNonResizable(tableConfig, key);

                var colState = {
                    key: key,
                    locked: header.locked,
                    width: width,
                    minWidth: minWidth,
                    maxWidth: maxWidth,
                    $col: $col,
                    isFixedWidth: isFixedWidth
                };

                visibleCols.push(colState);

                totalWidth += width;
                if (!isFixedWidth) {
                    flexCols.push(colState);
                }
            });

            if (!visibleCols.length || totalWidth <= 0) {
                return;
            }

            visibleCols.forEach(function (col) {
                var clamped = Math.round(col.width);
                if (clamped < col.minWidth) {
                    clamped = col.minWidth;
                }
                if (clamped > col.maxWidth) {
                    clamped = col.maxWidth;
                }
                col.nextWidth = clamped;
                self.setColumnWidth($table, col.$col, col.key, col.nextWidth);
            });

            var clampedTotal = 0;
            visibleCols.forEach(function (col) {
                clampedTotal += col.nextWidth;
            });

            var fillerKey = self.getConfiguredFillerKey(tableConfig);
            if (fillerKey !== '' && clampedTotal < containerWidth - normalizeEpsilonPx) {
                var fillerColumn = null;
                for (var idx = 0; idx < flexCols.length; idx += 1) {
                    if (flexCols[idx].key === fillerKey && !self.isColumnNonResizable(tableConfig, fillerKey)) {
                        fillerColumn = flexCols[idx];
                        break;
                    }
                }

                if (fillerColumn) {
                    var remainingSlack = Math.round(containerWidth - clampedTotal);
                    var availableGrowth = Math.max(0, fillerColumn.maxWidth - fillerColumn.nextWidth);
                    var growth = Math.min(remainingSlack, availableGrowth);
                    if (growth > 0) {
                        fillerColumn.nextWidth += growth;
                        self.setColumnWidth($table, fillerColumn.$col, fillerColumn.key, fillerColumn.nextWidth);
                        clampedTotal += growth;
                    }
                }
            }

            self.setTableFrameWidth($table, clampedTotal);
        },

        syncColumnVisibility: function ($table) {
            var $headers = $table.find('thead th');
            var $colgroup = $table.find('colgroup');

            $headers.each(function (index) {
                var isHidden = $(this).css('display') === 'none';
                var $col = $colgroup.children('col').eq(index);
                var colElement = $col.get(0);
                if (!colElement) {
                    return;
                }

                if (isHidden) {
                    if ($col.data('tah-hidden-width') === undefined) {
                        $col.data('tah-hidden-width', colElement.style.width || '');
                    }
                    $col.css({
                        display: 'none',
                        width: '0px',
                        minWidth: '0px',
                        maxWidth: '0px'
                    });
                } else {
                    var previousWidth = $col.data('tah-hidden-width');
                    $col.css({
                        display: '',
                        minWidth: '',
                        maxWidth: ''
                    });

                    if (previousWidth !== undefined) {
                        if (previousWidth) {
                            $col.css('width', previousWidth);
                        } else {
                            colElement.style.removeProperty('width');
                        }
                        $col.removeData('tah-hidden-width');
                    } else if (parseFloat($col.css('width')) === 0) {
                        colElement.style.removeProperty('width');
                    }
                }
            });

            $headers.removeClass('tah-admin-has-divider');
            $headers.removeClass('tah-admin-last-visible');

            var context = this.getVisibleHeaderContext($table);
            if (!context.visibleHeaders.length) {
                return;
            }
            var self = this;
            var tableConfig = $table.data('tah-table-config') || {};

            context.visibleHeaders.forEach(function (header, visibleIndex) {
                var nextHeader = context.visibleHeaders[visibleIndex + 1] || null;
                var isLeadingLockedUtility = header.index === context.firstVisibleIndex && header.locked;
                var isAtOrPastDataTail = header.index >= context.lastDataVisibleIndex;
                var hasLockedToDataBoundary = nextHeader && header.locked && !nextHeader.locked;
                var hasLockedToLockedBoundary = nextHeader && header.locked && nextHeader.locked;
                var isResizeBoundary = self.isResizeEdgeEligible(header, context, tableConfig);

                // Show divider when boundary is meaningful:
                // - normal resize edge
                // - locked utility -> first data column transition (e.g. # | item)
                // but never:
                // - before leading utility column
                // - between locked utility columns
                // - at/after last data column tail (e.g. margin | actions)
                if (!isLeadingLockedUtility && !isAtOrPastDataTail && !hasLockedToLockedBoundary && (isResizeBoundary || hasLockedToDataBoundary)) {
                    header.$th.addClass('tah-admin-has-divider');
                }

                if (header.index === context.lastVisibleIndex) {
                    header.$th.addClass('tah-admin-last-visible');
                }
            });

            var containerWidth = this.getStableContainerWidth($table);
            if (!containerWidth) {
                this.setTableFrameWidth($table, 0);
                return;
            }

            this.setTableFrameWidth($table, this.getVisibleColumnWidthSum($table));
        },

        captureDragSessionSnapshot: function (core, $table, tableConfig) {
            var $colgroup = $table.find('colgroup');
            var colMap = {};
            var rowCellMaps = [];

            $colgroup.children('col').each(function () {
                var key = $(this).attr('data-col-key');
                if (key) {
                    colMap[key] = $(this);
                }
            });

            core.getManagedRows($table, tableConfig).each(function () {
                var cellMap = {};
                $(this).children('td').each(function () {
                    var key = $(this).attr('data-tah-col');
                    if (key) {
                        cellMap[key] = $(this);
                    }
                });
                rowCellMaps.push({
                    $row: $(this),
                    cellMap: cellMap
                });
            });

            return {
                $colgroup: $colgroup,
                colMap: colMap,
                rowCellMaps: rowCellMaps
            };
        },

        clearDragSessionSnapshot: function ($table) {
            if (!$table || !$table.length) {
                return;
            }
            $table.removeData(sortSessionKey);
        },

        initSortable: function (core, $table, tableKey, variant) {
            this.destroySortable(core, $table);
            var self = this;
            var reorderNamespace = getReorderNamespace($table);
            if ($table.find('thead th[data-tah-orderable="1"]').length === 0) {
                return;
            }
            $table.data('tah-reorder-namespace', reorderNamespace);

            var releaseReorderArm = function () {
                if (!$table.data(reorderArmKey)) {
                    return;
                }

                $table.removeData(reorderArmKey);
                $table.removeClass('is-tah-reorder-armed');
            };

            $table.find('thead').on('mousedown' + reorderNamespace, 'th[data-tah-orderable="1"]', function (event) {
                if (event.which && event.which !== 1) {
                    return;
                }
                if ($(event.target).closest('.tah-admin-resize-handle, input, select, textarea, button, a').length) {
                    return;
                }

                $table.data(reorderArmKey, true);
                $table.addClass('is-tah-reorder-armed');
            });

            $(document).on('mouseup' + reorderNamespace, releaseReorderArm);
            $(window).on('blur' + reorderNamespace, releaseReorderArm);

            $table.find('thead tr').sortable({
                items: '> th[data-tah-orderable="1"]',
                axis: 'x',
                cursor: 'grabbing',
                cancel: '.tah-admin-resize-handle, input, select, textarea, button, a',
                appendTo: 'body',
                scroll: false,
                distance: dragDistancePx,
                opacity: dragOpacity,
                tolerance: 'pointer',
                placeholder: 'tah-admin-column-placeholder',
                forcePlaceholderSize: true,
                helper: function (e, ui) {
                    var $clone = ui.clone();
                    var sourceEl = ui.get(0);
                    var computed = window.getComputedStyle(sourceEl);

                    $clone.css({
                        width: ui.outerWidth() + 'px',
                        height: ui.outerHeight() + 'px',
                        background: computed.backgroundColor,
                        color: computed.color,
                        borderColor: computed.borderColor,
                        zIndex: helperZIndex,
                        boxSizing: computed.boxSizing,
                        opacity: dragOpacity,
                        paddingTop: computed.paddingTop,
                        paddingRight: computed.paddingRight,
                        paddingBottom: computed.paddingBottom,
                        paddingLeft: computed.paddingLeft,
                        fontSize: computed.fontSize,
                        fontWeight: computed.fontWeight,
                        lineHeight: computed.lineHeight,
                        letterSpacing: computed.letterSpacing,
                        textTransform: computed.textTransform,
                        overflow: 'hidden'
                    });

                    $clone.find('.tah-admin-resize-handle').remove();
                    return $clone;
                },
                start: function (e, ui) {
                    var tableConfig = $table.data('tah-table-config') || {};
                    var sessionSnapshot = self.captureDragSessionSnapshot(core, $table, tableConfig);
                    $table.data(sortSessionKey, sessionSnapshot);
                    var placeholderLabel = String(ui.item.text() || '').trim();
                    if (placeholderLabel === '') {
                        placeholderLabel = String(ui.item.attr('data-tah-col') || '').trim();
                    }
                    if (placeholderLabel !== '') {
                        ui.placeholder.text(placeholderLabel);
                    }

                    releaseReorderArm();
                    $table.addClass('is-tah-reordering');
                    ui.placeholder.css({
                        width: ui.item.outerWidth() + 'px'
                    });
                },
                stop: function () {
                    var tableConfig = $table.data('tah-table-config') || {};
                    try {
                        self.syncColumnOrderAfterSort(core, $table, tableConfig);
                        core.modules.store.savePrefs(core, tableKey, variant, $table);
                        core.modules.interaction.syncColumnVisibility($table);
                    } finally {
                        self.clearDragSessionSnapshot($table);
                        $table.removeClass('is-tah-reordering');
                        releaseReorderArm();
                    }
                }
            });
        },

        destroySortable: function (core, $table) {
            if (!$table || !$table.length) {
                return;
            }

            var reorderNamespace = String($table.data('tah-reorder-namespace') || getReorderNamespace($table));
            $table.find('thead').off(reorderNamespace);
            $(document).off(reorderNamespace);
            $(window).off(reorderNamespace);
            $table.removeData('tah-reorder-namespace');
            $table.removeData(reorderArmKey);
            $table.removeClass('is-tah-reorder-armed');

            var $headerRow = $table.find('thead tr');
            if ($headerRow.data('ui-sortable')) {
                $headerRow.sortable('destroy');
            }
            this.clearDragSessionSnapshot($table);
        },

        syncColumnOrderAfterSort: function (core, $table, tableConfig) {
            var $headers = $table.find('thead th');
            var newOrderKeys = [];

            $headers.each(function () {
                var key = String($(this).attr('data-tah-col') || '');
                if (key) {
                    newOrderKeys.push(key);
                }
            });

            var sessionSnapshot = $table.data(sortSessionKey) || null;
            var $colgroup = sessionSnapshot && sessionSnapshot.$colgroup ? sessionSnapshot.$colgroup : $table.find('colgroup');
            var colMap = sessionSnapshot && sessionSnapshot.colMap ? sessionSnapshot.colMap : {};

            if (!sessionSnapshot) {
                $colgroup.children('col').each(function () {
                    var key = $(this).attr('data-col-key');
                    if (key) {
                        colMap[key] = $(this);
                    }
                });
            }

            $colgroup.empty();
            newOrderKeys.forEach(function (key) {
                if (colMap[key]) {
                    $colgroup.append(colMap[key]);
                }
            });

            var rowCellMaps = sessionSnapshot && sessionSnapshot.rowCellMaps ? sessionSnapshot.rowCellMaps : [];
            if (!sessionSnapshot) {
                core.getManagedRows($table, tableConfig).each(function () {
                    var cellMap = {};
                    $(this).children('td').each(function () {
                        var key = $(this).attr('data-tah-col');
                        if (key) {
                            cellMap[key] = $(this);
                        }
                    });
                    rowCellMaps.push({
                        $row: $(this),
                        cellMap: cellMap
                    });
                });
            }

            rowCellMaps.forEach(function (rowState) {
                newOrderKeys.forEach(function (key) {
                    if (rowState.cellMap[key]) {
                        rowState.$row.append(rowState.cellMap[key]);
                    }
                });
            });
        },

        initResizable: function (core, $table, tableKey, variant, tableConfig) {
            this.destroyResizable(core, $table);
            var self = this;
            var $headerRow = $table.find('thead tr');
            var namespaceKey = getResizeNamespace($table);
            var resizeEdgeEligibleMap = this.getResizeEdgeEligibleKeyMap($table, tableConfig);
            var state = {
                activeResize: null,
                namespace: namespaceKey
            };
            $table.data(resizeStateKey, state);

            function stopResize() {
                if (!state.activeResize) {
                    return;
                }

                $(document).off('mousemove' + namespaceKey + ' mouseup' + namespaceKey);

                state.activeResize.$th.removeClass('is-tah-resize-active');
                state.activeResize.$th.removeAttr('data-tah-resize-bound');
                $('body').removeClass('tah-admin-is-resizing');

                if ($headerRow.data('ui-sortable')) {
                    $headerRow.sortable('option', 'disabled', false);
                }

                core.modules.store.savePrefs(core, tableKey, variant, $table);
                state.activeResize = null;
            }

            $table.find('th').each(function () {
                var $th = $(this);
                var key = $th.attr('data-tah-col');

                $th.find('.tah-admin-resize-handle').remove();

                if (!key || !resizeEdgeEligibleMap[key]) {
                    return;
                }

                var $handle = $('<span class="tah-admin-resize-handle" aria-hidden="true"></span>');
                $th.append($handle);

                $handle.on('mousedown.tahResize', function (event) {
                    if (event.which && event.which !== 1) {
                        return;
                    }

                    event.preventDefault();
                    event.stopPropagation();

                    var $col = $table.find('colgroup col[data-col-key="' + key + '"]');
                    var currentWidth = self.getColumnWidth($table, $col, $th);
                    if (!Number.isFinite(currentWidth) || currentWidth <= 0) {
                        currentWidth = $th.outerWidth() || 0;
                    }

                    var minPx = self.getColumnMinWidthPx(tableConfig, key);
                    var maxPx = self.getColumnMaxWidthPx($table, tableConfig, key, minPx);
                    if (maxPx < minPx) {
                        maxPx = minPx;
                    }

                    state.activeResize = {
                        startX: event.pageX,
                        startWidth: currentWidth,
                        minWidth: minPx,
                        maxWidth: maxPx,
                        $th: $th,
                        $col: $col
                    };

                    $th.addClass('is-tah-resize-active');
                    $th.removeAttr('data-tah-resize-bound');
                    $('body').addClass('tah-admin-is-resizing');

                    if ($headerRow.data('ui-sortable')) {
                        $headerRow.sortable('option', 'disabled', true);
                    }

                    $(document).on('mousemove' + namespaceKey, function (moveEvent) {
                        if (!state.activeResize) {
                            return;
                        }

                        moveEvent.preventDefault();

                        var rawWidth = state.activeResize.startWidth + (moveEvent.pageX - state.activeResize.startX);
                        var nextWidth = rawWidth;
                        var boundState = '';
                        if (nextWidth < state.activeResize.minWidth) {
                            nextWidth = state.activeResize.minWidth;
                            boundState = 'min';
                        }
                        if (nextWidth > state.activeResize.maxWidth) {
                            nextWidth = state.activeResize.maxWidth;
                            boundState = 'max';
                        }

                        if (boundState === '') {
                            state.activeResize.$th.removeAttr('data-tah-resize-bound');
                        } else {
                            state.activeResize.$th.attr('data-tah-resize-bound', boundState);
                        }

                        self.setColumnWidth($table, state.activeResize.$col, key, nextWidth);
                        self.setTableFrameWidth($table, self.getVisibleColumnWidthSum($table));
                    });

                    $(document).on('mouseup' + namespaceKey, function (upEvent) {
                        upEvent.preventDefault();
                        stopResize();
                    });
                });
            });
        },

        destroyResizable: function (core, $table) {
            if (!$table || !$table.length) {
                return;
            }

            var state = $table.data(resizeStateKey) || null;
            var namespaceKey = (state && state.namespace) ? state.namespace : getResizeNamespace($table);
            var activeResize = state && state.activeResize ? state.activeResize : null;
            var $headerRow = $table.find('thead tr');

            $(document).off('mousemove' + namespaceKey + ' mouseup' + namespaceKey);
            $table.find('.tah-admin-resize-handle').off('.tahResize').remove();

            if (activeResize && activeResize.$th) {
                activeResize.$th.removeClass('is-tah-resize-active');
                activeResize.$th.removeAttr('data-tah-resize-bound');
            }
            $table.find('thead th').removeClass('is-tah-resize-active');
            $table.find('thead th').removeAttr('data-tah-resize-bound');
            $('body').removeClass('tah-admin-is-resizing');

            if ($headerRow.data('ui-sortable')) {
                $headerRow.sortable('option', 'disabled', false);
            }

            $table.removeData(resizeStateKey);
        },

        destroy: function (core, $table) {
            this.destroySortable(core, $table);
            this.destroyResizable(core, $table);
        }
    };
})(jQuery, window);
