'use strict';

const assert = require('assert');
const path = require('path');
const vm = require('vm');
const fs = require('fs');

function createFakeTable() {
  const dataStore = {
    'tah-reorder-namespace': '.tahReorder_screen_pricing_editor_standard'
  };
  const headerRow = {
    data() {
      return null;
    },
    sortable() {},
  };
  const thead = {
    offCalls: [],
    off(namespace) {
      this.offCalls.push(namespace);
    }
  };

  return {
    length: 1,
    _dataStore: dataStore,
    _thead: thead,
    _headerRow: headerRow,
    data(key, value) {
      if (arguments.length === 2) {
        this._dataStore[key] = value;
        return this;
      }
      return this._dataStore[key];
    },
    removeData(key) {
      delete this._dataStore[key];
      return this;
    },
    removeClass() {
      return this;
    },
    find(selector) {
      if (selector === 'thead') {
        return this._thead;
      }
      if (selector === 'thead tr') {
        return this._headerRow;
      }
      return {
        off() {},
        data() { return null; }
      };
    }
  };
}

test('interaction destroySortable unbinds only table-scoped reorder namespace', () => {
  const interactionPath = path.join(__dirname, '..', '..', 'assets', 'js', 'admin-tables-interaction.js');
  const source = fs.readFileSync(interactionPath, 'utf8');
  const documentOffCalls = [];
  const windowOffCalls = [];

  const sandbox = {
    window: {
      TAHAdminTables: {
        Constants: {
          widths: {
            savedSanity: { minFactor: 0.7, maxFactor: 1.02 },
            saveBounds: { maxFactor: 3, maxFloorPx: 480, fallbackMaxPx: 3000 },
            minPx: 40,
            normalizeEpsilonPx: 2
          },
          sort: {
            dragDistancePx: 5,
            dragOpacity: 0.9,
            helperZIndex: 9999
          }
        }
      }
    },
    document: {},
    console
  };

  sandbox.jQuery = function jQuery(target) {
    if (target === sandbox.document) {
      return {
        off(namespace) {
          documentOffCalls.push(namespace);
        }
      };
    }
    if (target === sandbox.window) {
      return {
        off(namespace) {
          windowOffCalls.push(namespace);
        }
      };
    }
    return target;
  };

  vm.runInNewContext(source, sandbox, { filename: interactionPath });

  const table = createFakeTable();
  sandbox.window.TAHAdminTables.Interaction.destroySortable({}, table);

  assert.deepStrictEqual(documentOffCalls, ['.tahReorder_screen_pricing_editor_standard']);
  assert.deepStrictEqual(windowOffCalls, ['.tahReorder_screen_pricing_editor_standard']);
  assert.strictEqual(table.data('tah-reorder-namespace'), undefined);
});
