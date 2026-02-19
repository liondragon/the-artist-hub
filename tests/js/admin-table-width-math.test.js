'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

test('admin table module no longer enqueues width-math as a runtime dependency', () => {
  const modulePath = path.join(
    __dirname,
    '..',
    '..',
    'inc',
    'modules',
    'admin-table-columns',
    'class-admin-table-columns-module.php'
  );
  const source = fs.readFileSync(modulePath, 'utf8');

  assert.ok(!source.includes('tah-admin-tables-width-math'), 'width-math script should not be registered/enqueued');
  assert.ok(
    source.includes("'deps' => ['jquery', 'jquery-ui-sortable', 'tah-admin-tables-constants']"),
    'Interaction runtime deps should be limited to jquery, jquery-ui-sortable, and constants'
  );
  assert.ok(
    source.includes('window.TAHAdminTablesRuntimeConstants') && source.includes('get_client_runtime_constants'),
    'Module should inject server-owned runtime constants before constants module'
  );
});

test('interaction caps derived max column width by fallback hard cap', () => {
  const interactionPath = path.join(
    __dirname,
    '..',
    '..',
    'assets',
    'js',
    'admin-tables-interaction.js'
  );
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
  const interaction = sandbox.window.TAHAdminTables.Interaction;
  interaction.getStableContainerWidth = () => 2000;

  const resolvedMax = interaction.getDefaultColumnMaxWidthPx({});
  assert.strictEqual(resolvedMax, 3000);
});

test('constants module prefers localized runtime width bounds when provided', () => {
  const constantsPath = path.join(
    __dirname,
    '..',
    '..',
    'assets',
    'js',
    'admin-tables-constants.js'
  );
  const source = fs.readFileSync(constantsPath, 'utf8');
  const sandbox = {
    window: {
      TAHAdminTables: {},
      TAHAdminTablesRuntimeConstants: {
        widths: {
          minPx: 44,
          normalizeEpsilonPx: 3,
          saveBounds: {
            maxFloorPx: 512,
            maxFactor: 4,
            fallbackMaxPx: 4096
          },
          savedSanity: {
            minFactor: 0.8,
            maxFactor: 1.1
          }
        }
      }
    },
    console
  };

  vm.runInNewContext(source, sandbox, { filename: constantsPath });
  const constants = sandbox.window.TAHAdminTables.Constants;
  assert.strictEqual(constants.widths.minPx, 44);
  assert.strictEqual(constants.widths.normalizeEpsilonPx, 3);
  assert.strictEqual(constants.widths.saveBounds.fallbackMaxPx, 4096);
  assert.strictEqual(constants.widths.savedSanity.maxFactor, 1.1);
});

test('constants module fails closed when runtime constants are missing', () => {
  const constantsPath = path.join(
    __dirname,
    '..',
    '..',
    'assets',
    'js',
    'admin-tables-constants.js'
  );
  const source = fs.readFileSync(constantsPath, 'utf8');
  const errors = [];
  const sandbox = {
    window: { TAHAdminTables: {} },
    console: {
      error(message) {
        errors.push(String(message || ''));
      }
    }
  };

  vm.runInNewContext(source, sandbox, { filename: constantsPath });

  assert.strictEqual(typeof sandbox.window.TAHAdminTables.Constants, 'undefined');
  assert.ok(errors.some((entry) => entry.includes('Missing runtime constants for width bounds')));
});
