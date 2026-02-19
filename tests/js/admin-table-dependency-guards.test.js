'use strict';

const assert = require('assert');
const path = require('path');
const vm = require('vm');

function buildSandbox() {
  const sandbox = {
    window: { TAHAdminTables: {} },
    console: {
      errors: [],
      error(message) {
        this.errors.push(String(message || ''));
      }
    }
  };
  sandbox.jQuery = function jQuery() {
    return {};
  };
  return sandbox;
}

function runScript(sandbox, filePath) {
  const source = require('fs').readFileSync(filePath, 'utf8');
  vm.runInNewContext(source, sandbox, { filename: filePath });
}

test('interaction fails closed and logs error when constants are missing', () => {
  const interactionPath = path.join(__dirname, '..', '..', 'assets', 'js', 'admin-tables-interaction.js');
  const sandbox = buildSandbox();

  runScript(sandbox, interactionPath);

  assert.strictEqual(typeof sandbox.window.TAHAdminTables.Interaction, 'undefined');
  assert.ok(
    sandbox.console.errors.some((entry) => entry.includes('Missing constants module for interaction')),
    'Expected explicit interaction dependency error'
  );
});

test('store fails closed and logs error when constants are missing', () => {
  const storePath = path.join(__dirname, '..', '..', 'assets', 'js', 'admin-tables-store.js');
  const sandbox = buildSandbox();

  runScript(sandbox, storePath);

  assert.strictEqual(typeof sandbox.window.TAHAdminTables.Store, 'undefined');
  assert.ok(
    sandbox.console.errors.some((entry) => entry.includes('Missing constants module for store')),
    'Expected explicit store dependency error'
  );
});
