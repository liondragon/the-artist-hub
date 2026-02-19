'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

function loadCoreModule() {
  const corePath = path.join(__dirname, '..', '..', 'assets', 'js', 'admin-tables-core.js');
  const source = fs.readFileSync(corePath, 'utf8');

  const docHandlers = {};
  const sandbox = {
    window: { TAHAdminTables: {} },
    document: {},
    console
  };

  sandbox.jQuery = function jQuery(target) {
    if (target === sandbox.document) {
      return {
        on(eventName, handler) {
          docHandlers[eventName] = handler;
          return this;
        },
        ready() {
          return this;
        }
      };
    }
    return target;
  };
  sandbox.jQuery.extend = function extend(target) {
    const output = Object.assign({}, target || {});
    for (let index = 1; index < arguments.length; index += 1) {
      Object.assign(output, arguments[index] || {});
    }
    return output;
  };

  vm.runInNewContext(source, sandbox, { filename: corePath });
  return sandbox.window.TAHAdminTables.Core;
}

function createFakeTable() {
  const dataStore = {
    'tah-table-config': {}
  };
  return {
    attr(name) {
      if (name === 'data-tah-table') {
        return 'pricing_editor';
      }
      return '';
    },
    data(key, value) {
      if (arguments.length === 2) {
        dataStore[key] = value;
        return this;
      }
      return dataStore[key];
    }
  };
}

function createSingleTableCollection(table) {
  return {
    each(callback) {
      callback.call(table, 0, table);
    }
  };
}

test('core refreshManagedTables disables reorder for the failing table only', () => {
  const core = loadCoreModule();
  const fakeTable = createFakeTable();
  const disabled = [];
  let normalizedCount = 0;

  core.modules = {
    interaction: {
      syncColumnVisibility() {},
      normalizeVisibleColumnWidths() {
        normalizedCount += 1;
      }
    }
  };
  core.scanAndInit = function scanAndInit() {};
  core.resolveManagedTablesFromPayload = function resolveManagedTablesFromPayload() {
    return createSingleTableCollection(fakeTable);
  };
  core.getTableRuntime = function getTableRuntime() {
    return { allowReorder: true };
  };
  core.validateTableContract = function validateTableContract() {
    return false;
  };
  core.disableReorderForTable = function disableReorderForTable($table) {
    disabled.push($table);
  };
  core.applyColumnState = function applyColumnState() {};
  core.pruneOrphanControls = function pruneOrphanControls() {};

  core.refreshManagedTables(fakeTable, {
    validateReorderContract: true,
    normalizeWidths: false
  });

  assert.strictEqual(disabled.length, 1);
  assert.strictEqual(disabled[0], fakeTable);
  assert.strictEqual(normalizedCount, 0);
});

test('core refreshManagedTables normalizes widths only when explicitly enabled', () => {
  const core = loadCoreModule();
  const fakeTable = createFakeTable();
  let normalizedCount = 0;

  core.modules = {
    interaction: {
      syncColumnVisibility() {},
      normalizeVisibleColumnWidths() {
        normalizedCount += 1;
      }
    }
  };
  core.scanAndInit = function scanAndInit() {};
  core.resolveManagedTablesFromPayload = function resolveManagedTablesFromPayload() {
    return createSingleTableCollection(fakeTable);
  };
  core.getTableRuntime = function getTableRuntime() {
    return { allowReorder: false };
  };
  core.applyColumnState = function applyColumnState() {};
  core.pruneOrphanControls = function pruneOrphanControls() {};

  core.refreshManagedTables(fakeTable, {
    normalizeWidths: false
  });
  core.refreshManagedTables(fakeTable, {
    normalizeWidths: true
  });

  assert.strictEqual(normalizedCount, 1);
});
