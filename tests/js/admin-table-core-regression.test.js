'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');

test('core disableReorderForTable no longer mutates allow_reorder in shared config', () => {
  const corePath = path.join(__dirname, '..', '..', 'assets', 'js', 'admin-tables-core.js');
  const source = fs.readFileSync(corePath, 'utf8');

  assert.ok(
    !/allow_reorder\s*=\s*false/.test(source),
    'Unexpected shared-config mutation seam found: allow_reorder = false'
  );
  assert.ok(
    /tah-reorder-disabled/.test(source),
    'Expected per-table reorder disabled state marker missing'
  );
});
