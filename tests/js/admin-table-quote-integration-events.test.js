'use strict';

const fs = require('fs');
const path = require('path');

test('quote pricing emits a single table lifecycle event when adding a new group', () => {
  const source = fs.readFileSync(
    path.resolve(__dirname, '../../assets/js/quote-pricing.js'),
    'utf8'
  );

  const addGroupIndex = source.indexOf("$('#tah-add-group').on('click'");
  if (addGroupIndex < 0) {
    throw new Error('Expected #tah-add-group click handler');
  }

  const addGroupBlock = source.slice(addGroupIndex, addGroupIndex + 2500);
  const emitsTableAdded = addGroupBlock.includes("trigger('tah:table_added'");
  const emitsRowAddedForGroupTable = addGroupBlock.includes("trigger('tah:table_row_added', [$group.find('table')])");

  if (!emitsTableAdded || emitsRowAddedForGroupTable) {
    throw new Error('Expected add-group path to emit only tah:table_added for new tables');
  }
});
