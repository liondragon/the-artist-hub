'use strict';

const fs = require('fs');
const path = require('path');

const source = fs.readFileSync(
  path.resolve(__dirname, '../../assets/js/admin-tables-interaction.js'),
  'utf8'
);

test('interaction avoids locking table frame width when container width is unavailable', () => {
  const hasGuard = source.includes('getStableContainerWidth: function ($table) {')
    && /syncColumnVisibility:[\s\S]*?var containerWidth = this\.getStableContainerWidth\(\$table\);[\s\S]*?if \(!containerWidth\) \{[\s\S]*?setTableFrameWidth\(\$table, 0\);[\s\S]*?return;[\s\S]*?\}/.test(source);
  if (!hasGuard) {
    throw new Error('Expected visibility-gated container width helper and hidden-container guard in syncColumnVisibility');
  }
});
