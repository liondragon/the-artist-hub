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

test('admin table core normalizes visible column widths on layout change', () => {
  const core = loadCoreModule();
  const calls = [];

  core.refreshManagedTables = function refreshManagedTables(payload, options) {
    calls.push({ payload, options });
  };

  const payload = { table: 'pricing_editor' };
  core.onLayoutChanged(payload);

  assert.strictEqual(calls.length, 1);
  assert.strictEqual(calls[0].payload, payload);
  assert.strictEqual(calls[0].options.rescan, true);
  assert.strictEqual(calls[0].options.normalizeWidths, true);
});
