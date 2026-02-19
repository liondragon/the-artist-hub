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

function createFakeTable(sharedConfig) {
  const dataStore = {
    'tah-table-config': sharedConfig
  };

  return {
    data(key, value) {
      if (arguments.length === 2) {
        dataStore[key] = value;
        return this;
      }
      return dataStore[key];
    },
    find(selector) {
      if (selector === 'thead tr') {
        return {
          data() {
            return null;
          },
          sortable() {}
        };
      }
      return {
        data() {
          return null;
        }
      };
    }
  };
}

test('core disableReorderForTable only marks per-table state and preserves shared config', () => {
  const core = loadCoreModule();
  const sharedConfig = { allow_reorder: true };
  const table = createFakeTable(sharedConfig);

  core.disableReorderForTable(table);

  assert.strictEqual(table.data('tah-reorder-disabled'), true);
  assert.deepStrictEqual(table.data('tah-table-config'), sharedConfig);
  assert.strictEqual(sharedConfig.allow_reorder, true);
});
