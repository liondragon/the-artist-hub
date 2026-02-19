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

test('interaction seeds normalize widths from config/min, not live DOM width reads', () => {
  const interaction = loadInteraction();
  const applied = [];
  let frameWidth = null;
  const fakeCol = { length: 1 };
  const fakeTable = {
    find(selector) {
      if (selector === 'colgroup col[data-col-key="amount"]') {
        return fakeCol;
      }
      return { length: 0 };
    }
  };

  interaction.getStableContainerWidth = () => 600;
  interaction.getVisibleHeaders = () => [{ key: 'amount', locked: false, $th: {} }];
  interaction.getColumnMinWidthPx = () => 90;
  interaction.getColumnMaxWidthPx = () => 220;
  interaction.getAssignedColumnWidth = () => 0;
  interaction.getColumnBaseWidthPx = () => 0;
  interaction.isColumnNonResizable = () => false;
  interaction.setColumnWidth = function setColumnWidth($table, $col, key, width) {
    applied.push({ key, width, col: $col });
  };
  interaction.setTableFrameWidth = function setTableFrameWidth($table, width) {
    frameWidth = width;
  };

  interaction.normalizeVisibleColumnWidths(fakeTable, {});

  assert.deepStrictEqual(applied, [{ key: 'amount', width: 90, col: fakeCol }]);
  assert.strictEqual(frameWidth, 90);
});
