'use strict';

const assert = require('assert');
const path = require('path');
const vm = require('vm');

function runStoreModule(postImplementation) {
  const storePath = path.join(__dirname, '..', '..', 'assets', 'js', 'admin-tables-store.js');
  const source = require('fs').readFileSync(storePath, 'utf8');
  const sandbox = {
    window: {
      TAHAdminTables: {
        Constants: {
          store: {
            saveDebounceMs: 1
          }
        }
      }
    },
    ajaxurl: '/wp-admin/admin-ajax.php',
    console,
    jQuery: {
      post: postImplementation,
      extend(target) {
        const output = Object.assign({}, target);
        for (let index = 1; index < arguments.length; index += 1) {
          Object.assign(output, arguments[index] || {});
        }
        return output;
      }
    }
  };
  vm.runInNewContext(source, sandbox, { filename: storePath });
  return sandbox.window.TAHAdminTables.Store;
}

test('store treats wp_send_json_error payloads as failures', () => {
  let errorCalled = false;
  const store = runStoreModule(() => ({
    done(doneCb) {
      doneCb({ success: false, data: { message: 'error' } });
      return {
        fail() {
          return this;
        }
      };
    }
  }));

  store.dispatchSaveRequest(
    {
      config: {
        config: {},
        nonce: 'nonce',
        screenId: 'screen'
      },
      saveState: {
        payloadHashes: {}
      }
    },
    'screen|pricing_editor|standard',
    {
      tableKey: 'pricing_editor',
      variant: 'standard',
      payload: { widths: {}, order: [] },
      payloadHash: 'hash',
      onError() {
        errorCalled = true;
      }
    }
  );

  assert.strictEqual(errorCalled, true, 'Expected onError to run for response.success=false');
});

test('store treats network failures as failures', () => {
  let errorCalled = false;
  const store = runStoreModule(() => ({
    done() {
      return {
        fail(failCb) {
          failCb();
          return this;
        }
      };
    }
  }));

  store.dispatchSaveRequest(
    {
      config: {
        config: {},
        nonce: 'nonce',
        screenId: 'screen'
      },
      saveState: {
        payloadHashes: {}
      }
    },
    'screen|pricing_editor|standard',
    {
      tableKey: 'pricing_editor',
      variant: 'standard',
      payload: { widths: {}, order: [] },
      payloadHash: 'hash',
      onError() {
        errorCalled = true;
      }
    }
  );

  assert.strictEqual(errorCalled, true, 'Expected onError to run for failed request');
});

test('store updates in-memory prefs and payload hash on success', () => {
  let successCalled = false;
  const core = {
    config: {
      config: {
        prefs: {}
      },
      nonce: 'nonce',
      screenId: 'screen'
    },
    saveState: {
      payloadHashes: {}
    }
  };
  const store = runStoreModule(() => ({
    done(doneCb) {
      doneCb({ success: true, data: {} });
      return {
        fail() {
          return this;
        }
      };
    }
  }));

  const payload = { widths: { item: 160 }, order: ['item', 'amount'] };
  store.dispatchSaveRequest(
    core,
    'screen|pricing_editor|standard',
    {
      tableKey: 'pricing_editor',
      variant: 'standard',
      payload,
      payloadHash: JSON.stringify(payload),
      onSuccess() {
        successCalled = true;
      }
    }
  );

  assert.ok(
    core.config.config.prefs['pricing_editor:standard'],
    'Expected pref cache write on success'
  );
  assert.strictEqual(core.config.config.prefs['pricing_editor:standard'].widths.item, 160);
  assert.strictEqual(core.saveState.payloadHashes['screen|pricing_editor|standard'], JSON.stringify(payload));
  assert.strictEqual(successCalled, true, 'Expected onSuccess callback on successful response');
});
