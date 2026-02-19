'use strict';

const fs = require('fs');
const path = require('path');

test('quote pricing emits a single table lifecycle event when adding a new group', () => {
  const source = fs.readFileSync(
    path.resolve(__dirname, '../../assets/js/quote-pricing.js'),
    'utf8'
  );

  const hasSingleAddEvent = /#tah-add-group[\s\S]*trigger\('tah:table_added'/.test(source)
    && !/#tah-add-group[\s\S]*trigger\('tah:table_row_added',\s*\[\$group\.find\('table'\)\]\)/.test(source);

  if (!hasSingleAddEvent) {
    throw new Error('Expected add-group path to emit only tah:table_added for new tables');
  }
});
