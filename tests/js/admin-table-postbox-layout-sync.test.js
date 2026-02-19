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

test('admin table core owns postbox reopen layout sync with frame-based stabilization', () => {
  const core = loadCoreModule();
  const calls = [];
  const fakePostbox = {
    length: 1,
    hasClass(className) {
      return className === 'closed' ? false : false;
    }
  };

  core.resolvePostboxFromPayload = function resolvePostboxFromPayload() {
    return fakePostbox;
  };
  core.resolveCandidateTables = function resolveCandidateTables() {
    return { length: 1 };
  };
  core.scheduleStabilizedLayoutSync = function scheduleStabilizedLayoutSync(scope) {
    calls.push(scope);
  };

  core.onPostboxToggled({}, fakePostbox);

  assert.strictEqual(calls.length, 1);
  assert.strictEqual(calls[0], fakePostbox);
});
