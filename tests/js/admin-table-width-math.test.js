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

  assert.ok(!/tah-admin-tables-width-math/.test(source), 'width-math script should not be registered/enqueued');
  assert.ok(
    /'tah-admin-tables-interaction'\s*=>\s*\[[\s\S]*'deps'\s*=>\s*\['jquery', 'jquery-ui-sortable', 'tah-admin-tables-constants'\]/.test(source),
    'Interaction runtime deps should be limited to jquery, jquery-ui-sortable, and constants'
  );
  assert.ok(
    /TAHAdminTablesRuntimeConstants/.test(source) && /get_client_runtime_constants/.test(source),
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

  assert.ok(
    /getDefaultColumnMaxWidthPx[\s\S]*?hardCap[\s\S]*?Math\.min\(derivedMax, hardCap\)/.test(source),
    'Expected interaction max-width derivation to cap against fallbackMaxPx'
  );
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

  assert.ok(
    /TAHAdminTablesRuntimeConstants/.test(source)
    && /runtimeWidths/.test(source)
    && /hasRuntimeWidths/.test(source)
    && /Missing runtime constants for width bounds/.test(source),
    'Expected constants module to consume localized runtime width bounds'
  );
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
