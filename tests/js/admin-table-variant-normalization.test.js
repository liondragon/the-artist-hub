'use strict';

const assert = require('assert');
const path = require('path');
const vm = require('vm');
const fs = require('fs');

function createStoreWithPostCapture(capture) {
  const storePath = path.join(__dirname, '..', '..', 'assets', 'js', 'admin-tables-store.js');
  const source = fs.readFileSync(storePath, 'utf8');
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
      post(url, payload) {
        capture.url = url;
        capture.payload = payload;
        return {
          done(doneCb) {
            doneCb({ success: true, data: {} });
            return {
              fail() {
                return this;
              }
            };
          }
        };
      },
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

test('store normalizes table/variant keys before AJAX save and pref cache writes', () => {
  const capture = {};
  const store = createStoreWithPostCapture(capture);
  const core = {
    config: {
      config: {
        prefs: {}
      },
      nonce: 'nonce',
      screenId: 'tah-quote-editor'
    },
    saveState: {
      payloadHashes: {}
    }
  };

  store.dispatchSaveRequest(
    core,
    'tah-quote-editor|pricing_editor|standard',
    {
      tableKey: 'Pricing Editor',
      variant: 'Standard Plan',
      payload: { widths: {}, order: [] },
      payloadHash: 'hash'
    }
  );

  assert.strictEqual(capture.payload.table_key, 'pricingeditor');
  assert.strictEqual(capture.payload.variant, 'standardplan');
  assert.ok(core.config.config.prefs['pricingeditor:standardplan']);
});
