(function ($) {
    'use strict';

    var namespace = window.TAHAdminTables = window.TAHAdminTables || {};

    /**
     * TAH Admin Table Manager
     * Handles column resizing, reordering, and persistence for admin tables.
     */
    var TAH_Admin_Tables = {
        config: {}, // Loaded from wp_localize_script
        modules: null,
        saveState: {
            timers: {},
            pending: {},
            payloadHashes: {}
        },

        normalizeVariantKey: function (value) {
            return String(value || '')
                .toLowerCase()
                .replace(/[^a-z0-9_-]/g, '');
        },

        init: function () {
            if (typeof tahAdminTablesConfig === 'undefined') {
                console.warn('TAH Admin Tables: Config not found.');
                return;
            }

            this.modules = this.resolveRequiredModules();
            if (!this.modules) {
                return;
            }

            this.config = tahAdminTablesConfig;
            this.bindEvents();
            this.scanAndInit();
        },

        resolveRequiredModules: function () {
            var required = {
                Constants: 'constants',
                Store: 'store',
                Interaction: 'interaction'
            };
            var missing = [];
            var resolved = {};

            Object.keys(required).forEach(function (moduleKey) {
                if (!namespace[moduleKey]) {
                    missing.push(moduleKey);
                    return;
                }
                resolved[required[moduleKey]] = namespace[moduleKey];
            });

            if (missing.length) {
                console.error('TAH Admin Tables: Missing required modules: ' + missing.join(', '));
                return null;
            }

            return resolved;
        },

        bindEvents: function () {
            var self = this;
            // Re-scan when a table is added dynamically (e.g. quote presets)
            $(document).on('tah:table_added', function (event, tableOrContainer) {
                self.onTableAdded(tableOrContainer);
            });
            // Re-validate row contract when rows are dynamically added
            $(document).on('tah:table_row_added', function (event, rowOrTable) {
                self.onRowAdded(rowOrTable);
            });
            // Handle layout changes (insurance toggles)
            $(document).on('tah:table_layout_changed', function (event, tableOrContainer) {
                self.onLayoutChanged(tableOrContainer);
            });

            $(document).on('postbox-toggled', function (event, postbox) {
                self.onPostboxToggled(event, postbox);
            });

            $(document).on('click', '.postbox .handlediv, .postbox .hndle', function () {
                var $postbox = $(this).closest('.postbox');
                window.setTimeout(function () {
                    self.onPostboxToggled({ target: $postbox.get(0) }, $postbox);
                }, 0);
            });

            $(document).on('click', '.tah-admin-reset-table', function (event) {
                event.preventDefault();
                self.handleResetAction($(this));
            });
        },

        resolvePostboxFromPayload: function (event, postbox) {
            if (postbox && postbox.jquery) {
                return postbox;
            }

            if (postbox && postbox.nodeType === 1) {
                return $(postbox);
            }

            if (typeof postbox === 'string' && postbox !== '') {
                return $('#' + postbox.replace(/^#/, ''));
            }

            if (event && event.target && event.target.nodeType === 1) {
                return $(event.target).closest('.postbox');
            }

            return $();
        },

        onPostboxToggled: function (event, postbox) {
            var $postbox = this.resolvePostboxFromPayload(event, postbox);
            if (!$postbox.length || $postbox.hasClass('closed')) {
                return;
            }

            var $tables = this.resolveCandidateTables($postbox);
            if (!$tables.length) {
                return;
            }

            this.scheduleStabilizedLayoutSync($postbox);
        },

        getLayoutWidthSignature: function (scope) {
            var parts = [];
            this.resolveCandidateTables(scope).each(function () {
                var $table = $(this);
                var width = Math.round($table.parent().width() || $table.width() || 0);
                parts.push(String(width));
            });
            return parts.join('|');
        },

        scheduleStabilizedLayoutSync: function (scope) {
            var self = this;
            var lastSignature = '';
            var stableTicks = 0;
            var maxFrames = 6;
            var frame = 0;

            function tick() {
                frame += 1;
                self.onLayoutChanged(scope);

                var signature = self.getLayoutWidthSignature(scope);
                if (signature !== '' && signature === lastSignature) {
                    stableTicks += 1;
                } else {
                    stableTicks = 0;
                    lastSignature = signature;
                }

                if (stableTicks >= 1 || frame >= maxFrames) {
                    return;
                }

                window.requestAnimationFrame(tick);
            }

            window.requestAnimationFrame(tick);
        },

        resolveCandidateTables: function (scope) {
            if (scope && scope.jquery) {
                return scope
                    .filter('table[data-tah-table]')
                    .add(scope.find('table[data-tah-table]'));
            }

            if (scope && scope.nodeType === 1) {
                var $scope = $(scope);
                return $scope
                    .filter('table[data-tah-table]')
                    .add($scope.find('table[data-tah-table]'));
            }

            return $('table[data-tah-table]');
        },

        onTableAdded: function (tableOrContainer) {
            this.refreshManagedTables(tableOrContainer, {
                rescan: true,
                normalizeWidths: true
            });
        },

        onLayoutChanged: function (tableOrContainer) {
            this.refreshManagedTables(tableOrContainer, {
                rescan: true,
                normalizeWidths: true
            });
        },

        scanAndInit: function (scope) {
            var self = this;
            var screenConfig = this.config.config || {};
            var definedTables = screenConfig.tables || {};
            var $candidateTables = this.resolveCandidateTables(scope);

            // Find all potential tables
            $candidateTables.each(function () {
                var $table = $(this);
                var tableKey = $table.attr('data-tah-table');
                var variant = '';

                if (!tableKey || !definedTables[tableKey]) {
                    if ($table.data('tah-initialized')) {
                        self.destroyTable($table);
                    }
                    return; // Config not found for this table
                }

                var tableConfig = definedTables[tableKey];
                var runtime = self.getTableRuntime(tableConfig);
                variant = self.normalizeVariantKey($table.attr(runtime.variantAttr) || '');

                var nextLifecycleSignature = self.getLifecycleSignature(tableKey, variant, runtime);

                if ($table.data('tah-initialized')) {
                    if (runtime.allowReorder && $table.data('tah-reorder-disabled')) {
                        if (self.validateTableContract($table, tableKey, runtime)) {
                            self.reinitTable($table, tableKey, variant, tableConfig, runtime);
                        }
                        return;
                    }

                    var currentLifecycleSignature = String($table.data('tah-lifecycle-signature') || '');
                    if (currentLifecycleSignature === nextLifecycleSignature) {
                        return;
                    }

                    self.reinitTable($table, tableKey, variant, tableConfig, runtime);
                    return;
                }

                if ($table.data('tah-contract-invalid')) {
                    if (!self.validateTableContract($table, tableKey, runtime)) {
                        return;
                    }
                    $table.removeData('tah-contract-invalid');
                    $table.removeData('tah-contract-warning-logged');
                }

                self.initTable($table, tableKey, variant, tableConfig, runtime);
            });

            this.pruneOrphanControls();
        },

        refreshManagedTables: function (tableOrContainer, options) {
            var settings = $.extend({
                rescan: false,
                normalizeWidths: false,
                validateReorderContract: false
            }, options || {});

            if (settings.rescan) {
                this.scanAndInit(tableOrContainer);
            }

            var self = this;
            this.resolveManagedTablesFromPayload(tableOrContainer).each(function () {
                var $table = $(this);
                var tableKey = $table.attr('data-tah-table') || 'unknown';
                var tableConfig = $table.data('tah-table-config') || {};
                var runtime = self.getTableRuntime(tableConfig);

                if (settings.validateReorderContract && runtime.allowReorder && !self.validateTableContract($table, tableKey, runtime)) {
                    self.disableReorderForTable($table);
                }

                self.applyColumnState($table, tableConfig);
                self.modules.interaction.syncColumnVisibility($table);
                if (settings.normalizeWidths) {
                    self.modules.interaction.normalizeVisibleColumnWidths($table, tableConfig);
                }
            });

            self.pruneOrphanControls();
        },

        initTable: function ($table, tableKey, variant, tableConfig, runtime) {
            this.applyColumnState($table, tableConfig);
            if (!this.validateTableContract($table, tableKey, runtime)) {
                $table.data('tah-contract-invalid', true);
                return;
            }

            var lifecycleSignature = this.getLifecycleSignature(tableKey, variant, runtime);
            $table.data('tah-initialized', true);
            $table.addClass('tah-enhanced-table');
            $table.data('tah-table-config', tableConfig);
            $table.data('tah-pref-context-key', this.modules.store.getPrefContextKey(this, tableKey, variant));
            $table.data('tah-default-order', this.extractColumnOrder($table));
            $table.data('tah-lifecycle-signature', lifecycleSignature);
            $table.removeData('tah-contract-invalid');

            // 1. Load User Preferences
            var contextKey = variant ? tableKey + ':' + variant : tableKey;
            var prefs = (this.config.config.prefs && this.config.config.prefs[contextKey]) || {};
            var safeSavedWidths = this.modules.interaction.sanitizeSavedWidths($table, prefs.widths, tableConfig);

            // 2. Normalize Columns & Inject ColGroup
            var columns = this.modules.interaction.parseColumns($table);
            this.modules.interaction.setupColGroup($table, columns, safeSavedWidths);

            // 3. Apply Reordering (if prefs exist)
            if (runtime.allowReorder && prefs.order) {
                this.modules.interaction.reorderTable(this, $table, prefs.order, tableConfig);
            }

            // 4. Initial Visibility & Separator Sync
            this.modules.interaction.syncColumnVisibility($table);

            // 5. Normalize visible widths and set deterministic table frame width.
            this.modules.interaction.normalizeVisibleColumnWidths($table, tableConfig);

            // 6. Initialize Drag & Drop (Sortable)
            if (runtime.allowReorder) {
                this.modules.interaction.initSortable(this, $table, tableKey, variant);
            }

            // 7. Initialize Resizing
            if (runtime.allowResize) {
                this.modules.interaction.initResizable(this, $table, tableKey, variant, tableConfig);
            }

            if (runtime.showReset && (runtime.allowResize || runtime.allowReorder)) {
                this.ensureTableControls($table, tableKey, variant);
            }
        },

        reinitTable: function ($table, tableKey, variant, tableConfig, runtime) {
            this.destroyTable($table);
            this.initTable($table, tableKey, variant, tableConfig, runtime);
        },

        destroyTable: function ($table) {
            if (!$table || !$table.length || !$table.data('tah-initialized')) {
                return;
            }

            var contextKey = String($table.data('tah-pref-context-key') || '');
            if (contextKey !== '') {
                this.modules.store.flushPendingSaveForContext(this, contextKey);
                delete this.saveState.payloadHashes[contextKey];
            }

            this.modules.interaction.destroy(this, $table);

            $table.removeData('tah-initialized');
            $table.removeData('tah-table-config');
            $table.removeData('tah-pref-context-key');
            $table.removeData('tah-default-order');
            $table.removeData('tah-reorder-disabled');
            $table.removeData('tah-lifecycle-signature');
            $table.removeClass('tah-enhanced-table is-tah-reordering');
        },

        getLifecycleSignature: function (tableKey, variant, runtime) {
            var currentRuntime = runtime || {};
            return [
                String(tableKey || ''),
                String(variant || ''),
                String(currentRuntime.rowSelector || ''),
                currentRuntime.allowResize ? '1' : '0',
                currentRuntime.allowReorder ? '1' : '0',
                currentRuntime.showReset ? '1' : '0'
            ].join('|');
        },

        getTableRuntime: function (tableConfig) {
            var config = tableConfig || {};

            return {
                variantAttr: config.variant_attr || 'data-tah-variant',
                rowSelector: config.row_selector || 'tbody tr',
                allowResize: config.allow_resize !== false,
                allowReorder: !!config.allow_reorder,
                showReset: config.show_reset !== false
            };
        },

        applyColumnState: function ($table, tableConfig) {
            if (!$table || !$table.length) {
                return;
            }

            var interaction = this.modules.interaction;
            $table.find('thead th[data-tah-col]').each(function () {
                var $th = $(this);
                var key = String($th.attr('data-tah-col') || '');
                if (!key) {
                    return;
                }

                var columnConfig = (tableConfig && tableConfig.columns && typeof tableConfig.columns === 'object')
                    ? tableConfig.columns[key]
                    : null;
                var locked = !!(columnConfig && columnConfig.locked === true);
                var orderable = interaction.isColumnOrderable(tableConfig, key);
                var visible = interaction.isColumnVisible(tableConfig, key);

                $th.attr('data-tah-locked', locked ? '1' : '0');
                $th.attr('data-tah-orderable', orderable ? '1' : '0');
                $th.css('display', visible ? '' : 'none');
                $table.find('tbody td[data-tah-col="' + key + '"]').css('display', visible ? '' : 'none');
            });
        },

        getI18nText: function (key, fallback) {
            var i18n = this.config && this.config.config && this.config.config.i18n ? this.config.config.i18n : {};
            if (Object.prototype.hasOwnProperty.call(i18n, key) && i18n[key]) {
                return String(i18n[key]);
            }
            return String(fallback || '');
        },

        extractColumnOrder: function ($table) {
            var order = [];
            $table.find('thead th[data-tah-col]').each(function () {
                var key = String($(this).attr('data-tah-col') || '');
                if (key !== '') {
                    order.push(key);
                }
            });
            return order;
        },

        findControlsByContextKey: function (contextKey) {
            return $('.tah-admin-table-controls').filter(function () {
                return $(this).attr('data-tah-pref-context-key') === String(contextKey || '');
            });
        },

        getInitializedTablesByContextKey: function (contextKey) {
            return $('table.tah-enhanced-table').filter(function () {
                return $(this).data('tah-pref-context-key') === contextKey;
            });
        },

        ensureTableControls: function ($table, tableKey, variant) {
            var contextKey = String($table.data('tah-pref-context-key') || '');
            if (!contextKey) {
                return;
            }

            if (this.findControlsByContextKey(contextKey).length) {
                return;
            }

            var $controls = $('<div class="tah-admin-table-controls"></div>')
                .attr('data-tah-pref-context-key', contextKey)
                .attr('data-tah-table-key', String(tableKey || ''))
                .attr('data-tah-variant', String(variant || ''));

            var resetLabel = this.getI18nText('resetTableLayout', 'Reset table layout');
            $('<button type="button" class="button-link tah-admin-reset-table"></button>')
                .attr('title', resetLabel)
                .attr('aria-label', resetLabel)
                .append('<span class="dashicons dashicons-update" aria-hidden="true"></span>')
                .appendTo($controls);

            var $targetWrap = $table.closest('.tah-group-table-wrap');
            if (!$targetWrap.length) {
                $targetWrap = $table.parent();
            }
            $targetWrap.prepend($controls);
        },

        pruneOrphanControls: function () {
            var self = this;
            $('.tah-admin-table-controls').each(function () {
                var $controls = $(this);
                var contextKey = String($controls.attr('data-tah-pref-context-key') || '');
                if (contextKey && self.getInitializedTablesByContextKey(contextKey).length) {
                    return;
                }
                $controls.remove();
            });
        },

        resetTableWidths: function ($table) {
            var tableConfig = $table.data('tah-table-config') || {};
            var columns = this.modules.interaction.parseColumns($table);
            this.modules.interaction.setupColGroup($table, columns, {});
            this.modules.interaction.syncColumnVisibility($table);
            this.modules.interaction.normalizeVisibleColumnWidths($table, tableConfig);
            this.modules.interaction.syncColumnVisibility($table);
        },

        resetTableOrder: function ($table) {
            var tableConfig = $table.data('tah-table-config') || {};
            var defaultOrder = $table.data('tah-default-order');
            if (!Array.isArray(defaultOrder) || !defaultOrder.length) {
                defaultOrder = this.extractColumnOrder($table);
            }

            this.modules.interaction.reorderTable(this, $table, defaultOrder, tableConfig);
            this.modules.interaction.syncColumnVisibility($table);
        },

        handleResetAction: function ($button) {
            var $controls = $button.closest('.tah-admin-table-controls');
            var contextKey = '';
            var tableKey = '';
            var variant = '';
            var $tables = $();

            if ($controls.length) {
                contextKey = String($controls.attr('data-tah-pref-context-key') || '');
                tableKey = String($controls.attr('data-tah-table-key') || '');
                variant = this.normalizeVariantKey(String($controls.attr('data-tah-variant') || ''));
                $tables = this.getInitializedTablesByContextKey(contextKey);
            } else {
                var $scope = $button.closest('#tah-quote-pricing');
                if (!$scope.length) {
                    $scope = $button.closest('.postbox, .wrap, body');
                }
                var $table = $scope.find('table.tah-enhanced-table[data-tah-table]').first();
                if (!$table.length) {
                    $table = $('table.tah-enhanced-table[data-tah-table]').first();
                }
                if (!$table.length) {
                    return;
                }

                contextKey = String($table.data('tah-pref-context-key') || '');
                tableKey = String($table.attr('data-tah-table') || '');
                variant = this.normalizeVariantKey(String($table.attr('data-tah-variant') || ''));
                $tables = contextKey ? this.getInitializedTablesByContextKey(contextKey) : $table;
            }

            if (!$tables.length) {
                return;
            }

            var self = this;
            $tables.each(function () {
                var $table = $(this);
                self.resetTableOrder($table);
                self.resetTableWidths($table);
            });

            this.modules.store.savePrefs(this, tableKey, variant, $tables.first(), {
                widths: {},
                order: []
            });
        },

        reportContractIssue: function ($table, tableKey, message) {
            if ($table.data('tah-contract-warning-logged')) {
                return;
            }

            console.warn('TAH Admin Tables: Disabled "' + tableKey + '" due to invalid markup contract: ' + message, $table.get(0));
            $table.data('tah-contract-warning-logged', true);
        },

        validateTableContract: function ($table, tableKey, runtime) {
            var self = this;
            var $managedHeaderCells = $table.find('thead th[data-tah-col]');
            if (!$managedHeaderCells.length) {
                this.reportContractIssue($table, tableKey, 'missing managed header cells (data-tah-col)');
                return false;
            }

            var headerKeys = [];
            var headerKeyMap = {};
            var duplicateHeaderKey = '';

            $managedHeaderCells.each(function () {
                var key = $(this).attr('data-tah-col');
                if (headerKeyMap[key]) {
                    duplicateHeaderKey = key;
                    return;
                }
                headerKeyMap[key] = true;
                headerKeys.push(key);
            });

            if (duplicateHeaderKey) {
                this.reportContractIssue($table, tableKey, 'duplicate header key "' + duplicateHeaderKey + '"');
                return false;
            }

            if (!runtime || !runtime.allowReorder) {
                return true;
            }

            var rowSelector = (runtime.rowSelector || 'tbody tr');
            var $rows = $table.find(rowSelector);
            var invalidMessage = '';

            $rows.each(function (rowIndex) {
                var $row = $(this);
                var $cells = $row.children('td');
                if (!$cells.length || $cells.filter('[colspan]').length) {
                    return;
                }

                var rowKeyMap = {};
                var rowKeyCount = 0;
                var duplicateRowKey = '';
                var unknownRowKey = '';

                $row.children('td[data-tah-col]').each(function () {
                    var key = $(this).attr('data-tah-col');
                    if (rowKeyMap[key]) {
                        duplicateRowKey = key;
                        return;
                    }
                    if (!headerKeyMap[key]) {
                        unknownRowKey = key;
                        return;
                    }

                    rowKeyMap[key] = true;
                    rowKeyCount += 1;
                });

                if (duplicateRowKey) {
                    invalidMessage = 'row ' + rowIndex + ' has duplicate cell key "' + duplicateRowKey + '"';
                    return false;
                }

                if (unknownRowKey !== '') {
                    invalidMessage = 'row ' + rowIndex + ' has unknown cell key "' + unknownRowKey + '"';
                    return false;
                }

                if (rowKeyCount === 0) {
                    return;
                }

                var hasAllKeys = true;
                headerKeys.forEach(function (key) {
                    if (!rowKeyMap[key]) {
                        hasAllKeys = false;
                    }
                });

                if (!hasAllKeys) {
                    invalidMessage = 'row ' + rowIndex + ' cell keys do not match header keys';
                    return false;
                }
            });

            if (invalidMessage !== '') {
                self.reportContractIssue($table, tableKey, invalidMessage);
                return false;
            }

            return true;
        },

        getManagedRows: function ($table, tableConfig) {
            var runtime = this.getTableRuntime(tableConfig || $table.data('tah-table-config') || {});
            return $table.find(runtime.rowSelector || 'tbody tr');
        },

        resolveManagedTablesFromPayload: function (rowOrTable) {
            var $tables = $();

            if (rowOrTable && rowOrTable.jquery) {
                $tables = rowOrTable.is('table')
                    ? rowOrTable
                    : rowOrTable.closest('table');
            } else if (rowOrTable && rowOrTable.nodeType === 1) {
                var $node = $(rowOrTable);
                $tables = $node.is('table')
                    ? $node
                    : $node.closest('table');
            }

            if (!$tables.length) {
                $tables = $('table.tah-enhanced-table');
            }

            return $tables.filter('table.tah-enhanced-table');
        },

        onRowAdded: function (rowOrTable) {
            this.refreshManagedTables(rowOrTable, {
                rescan: true,
                validateReorderContract: true
            });
        },

        disableReorderForTable: function ($table) {
            if ($table.data('tah-reorder-disabled')) {
                return;
            }

            var $headerRow = $table.find('thead tr');
            if ($headerRow.data('ui-sortable')) {
                $headerRow.sortable('destroy');
            }

            $table.data('tah-reorder-disabled', true);
        },

    };

    namespace.Core = TAH_Admin_Tables;

    $(document).ready(function () {
        TAH_Admin_Tables.init();
    });

})(jQuery);
