'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

function loadCoreModule() {
  const corePath = path.join(__dirname, '..', '..', 'assets', 'js', 'admin-tables-core.js');
  const source = fs.readFileSync(corePath, 'utf8');
  const sandbox = {
    window: { TAHAdminTables: {} },
    document: {},
    console
  };

  sandbox.jQuery = function jQuery(target) {
    if (target === sandbox.document) {
      return {
        on() { return this; },
        ready() { return this; }
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

function createFakeTable(name) {
  const dataStore = {
    name
  };
  return {
    attr(key) {
      if (key === 'data-tah-table') {
        return name;
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

function createCollection(tables) {
  return {
    each(callback) {
      tables.forEach((table, index) => callback.call(table, index, table));
    }
  };
}

test('core refresh fallback disables reorder only for invalid table instance', () => {
  const core = loadCoreModule();
  const firstTable = createFakeTable('table_a');
  const secondTable = createFakeTable('table_b');
  const disabled = [];

  core.modules = {
    interaction: {
      syncColumnVisibility() {},
      normalizeVisibleColumnWidths() {}
    }
  };
  core.scanAndInit = function scanAndInit() {};
  core.resolveManagedTablesFromPayload = function resolveManagedTablesFromPayload() {
    return createCollection([firstTable, secondTable]);
  };
  core.getTableRuntime = function getTableRuntime() {
    return { allowReorder: true };
  };
  core.applyColumnState = function applyColumnState() {};
  core.pruneOrphanControls = function pruneOrphanControls() {};
  core.validateTableContract = function validateTableContract(table) {
    return table !== firstTable;
  };
  core.disableReorderForTable = function disableReorderForTable(table) {
    disabled.push(table);
    table.data('tah-reorder-disabled', true);
  };

  core.refreshManagedTables(null, {
    validateReorderContract: true,
    normalizeWidths: false
  });

  assert.deepStrictEqual(disabled, [firstTable]);
  assert.strictEqual(firstTable.data('tah-reorder-disabled'), true);
  assert.strictEqual(secondTable.data('tah-reorder-disabled'), undefined);
});
