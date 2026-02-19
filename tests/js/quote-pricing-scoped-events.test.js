'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');

test('quote pricing emits scoped table payloads for layout and row events', () => {
  const filePath = path.join(__dirname, '..', '..', 'assets', 'js', 'quote-pricing.js');
  const source = fs.readFileSync(filePath, 'utf8');

  assert.ok(
    !/trigger\('tah:table_layout_changed'\);/.test(source),
    'Found unscoped tah:table_layout_changed trigger'
  );
  assert.ok(
    !/trigger\('tah:table_row_added'\);/.test(source),
    'Found unscoped tah:table_row_added trigger'
  );
  assert.ok(
    !/pricingColumnOrder/.test(source),
    'Unexpected pricingColumnOrder fallback seam still present'
  );
  assert.ok(
    !/tahAdminTablesConfig/.test(source),
    'Quote pricing should not fall back to localized admin-table config for column order'
  );
  assert.ok(
    !/postbox-toggled/.test(source),
    'Quote pricing should not own postbox layout sync; core module owns it'
  );
});
