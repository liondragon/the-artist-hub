'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');

test('core contract fallback is scoped per table instance (multi-table safe)', () => {
  const corePath = path.join(__dirname, '..', '..', 'assets', 'js', 'admin-tables-core.js');
  const source = fs.readFileSync(corePath, 'utf8');

  assert.ok(
    /refreshManagedTables:\s*function\s*\(tableOrContainer,\s*options\)/.test(source),
    'Expected shared managed-table refresh path for lifecycle events'
  );
  assert.ok(
    /validateReorderContract\s*&&\s*runtime\.allowReorder\s*&&\s*!self\.validateTableContract\(\$table,\s*tableKey,\s*runtime\)[\s\S]*self\.disableReorderForTable\(\$table\)/.test(source),
    'Expected per-instance reorder fallback in shared refresh path'
  );
  assert.ok(
    /disableReorderForTable:\s*function \(\$table\)/.test(source),
    'Expected disableReorderForTable to accept current table instance'
  );
  assert.ok(
    /\$table\.data\('tah-reorder-disabled', true\)/.test(source),
    'Expected per-table reorder disabled marker'
  );
  assert.ok(
    !/disableReorderForTable:\s*function \(\$table\)[\s\S]*\$\('table/.test(source),
    'disableReorderForTable must not query/disable all tables globally'
  );
});
