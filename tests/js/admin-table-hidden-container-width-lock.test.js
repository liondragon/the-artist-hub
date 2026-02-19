'use strict';

const assert = require('assert');
const path = require('path');
const vm = require('vm');
const fs = require('fs');

function loadInteraction() {
  const interactionPath = path.join(__dirname, '..', '..', 'assets', 'js', 'admin-tables-interaction.js');
  const source = fs.readFileSync(interactionPath, 'utf8');
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

  sandbox.jQuery = function jQuery() {
    return {};
  };

  vm.runInNewContext(source, sandbox, { filename: interactionPath });
  return sandbox.window.TAHAdminTables.Interaction;
}

test('interaction avoids locking table frame width when container width is unavailable', () => {
  const interaction = loadInteraction();
  const calls = [];
  const headerCollection = {
    each() { return this; },
    removeClass() { return this; }
  };
  const colCollection = {
    children() {
      return {
        eq() {
          return {
            get() { return {}; },
            data() { return undefined; },
            css() { return this; },
            removeData() { return this; }
          };
        }
      };
    }
  };
  const fakeTable = {
    find(selector) {
      if (selector === 'thead th') {
        return headerCollection;
      }
      if (selector === 'colgroup') {
        return colCollection;
      }
      return { each() {}, removeClass() {} };
    },
    data() { return {}; }
  };

  interaction.getVisibleHeaderContext = () => ({
    visibleHeaders: [{ index: 0, key: 'item', locked: false, $th: { addClass() {} } }],
    firstVisibleIndex: 0,
    lastVisibleIndex: 0,
    lastDataVisibleIndex: 0
  });
  interaction.getStableContainerWidth = () => 0;
  interaction.setTableFrameWidth = function setTableFrameWidth($table, width) {
    calls.push(width);
  };

  interaction.syncColumnVisibility(fakeTable);

  assert.deepStrictEqual(calls, [0]);
});
