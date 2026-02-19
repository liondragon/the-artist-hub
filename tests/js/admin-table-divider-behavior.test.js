'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

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

test('interaction divider logic preserves locked->data boundary while excluding locked->locked and data tail', () => {
  const interaction = loadInteraction();
  const classMap = {};
  function makeHeader(index, key, locked) {
    return {
      index,
      key,
      locked,
      $th: {
        addClass(className) {
          classMap[key] = classMap[key] || [];
          classMap[key].push(className);
        }
      }
    };
  }

  const headers = [
    makeHeader(0, 'handle', true),
    makeHeader(1, 'index', true),
    makeHeader(2, 'item', false),
    makeHeader(3, 'amount', false),
    makeHeader(4, 'actions', true)
  ];

  const fakeHeadersCollection = {
    each() { return this; },
    removeClass() { return this; }
  };
  const fakeColgroup = {
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
        return fakeHeadersCollection;
      }
      if (selector === 'colgroup') {
        return fakeColgroup;
      }
      return { each() {}, removeClass() {} };
    },
    data() { return {}; }
  };

  interaction.getVisibleHeaderContext = () => ({
    visibleHeaders: headers,
    firstVisibleIndex: 0,
    lastVisibleIndex: 4,
    lastDataVisibleIndex: 3
  });
  interaction.isResizeEdgeEligible = (header) => header.key === 'item';
  interaction.getStableContainerWidth = () => 500;
  interaction.getVisibleColumnWidthSum = () => 420;
  interaction.setTableFrameWidth = function setTableFrameWidth() {};

  interaction.syncColumnVisibility(fakeTable);

  assert.ok(!classMap.handle || !classMap.handle.includes('tah-admin-has-divider'));
  assert.ok(classMap.index && classMap.index.includes('tah-admin-has-divider'));
  assert.ok(classMap.item && classMap.item.includes('tah-admin-has-divider'));
  assert.ok(!classMap.amount || !classMap.amount.includes('tah-admin-has-divider'));
  assert.ok(classMap.actions && classMap.actions.includes('tah-admin-last-visible'));
});

test('max resize bound uses alert color', () => {
  const source = fs.readFileSync(
    path.resolve(__dirname, '../../assets/css/admin.css'),
    'utf8'
  );

  const selectorIndex = source.indexOf('[data-tah-resize-bound="max"]::after');
  const selectorBlock = selectorIndex >= 0 ? source.slice(selectorIndex, selectorIndex + 220) : '';
  if (!selectorBlock.includes('background: #d63638;')) {
    throw new Error('Expected max-bound divider color to be #d63638');
  }
});
