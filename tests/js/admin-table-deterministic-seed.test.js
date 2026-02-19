'use strict';

const fs = require('fs');
const path = require('path');

test('interaction seeds normalize widths from config/min, not live DOM width reads', () => {
  const source = fs.readFileSync(
    path.resolve(__dirname, '../../assets/js/admin-tables-interaction.js'),
    'utf8'
  );

  const hasDeterministicSeed = /normalizeVisibleColumnWidths:[\s\S]*?var width = self\.getAssignedColumnWidth\(\$table, key\);[\s\S]*?width = self\.getColumnBaseWidthPx\(tableConfig, key, minWidth, maxWidth\);[\s\S]*?width = minWidth;/.test(source);
  if (!hasDeterministicSeed) {
    throw new Error('Expected deterministic normalize seeding (assigned/base/min)');
  }
});
