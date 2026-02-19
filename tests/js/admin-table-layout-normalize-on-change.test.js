'use strict';

const fs = require('fs');
const path = require('path');

test('admin table core normalizes visible column widths on layout change', () => {
  const source = fs.readFileSync(
    path.resolve(__dirname, '../../assets/js/admin-tables-core.js'),
    'utf8'
  );

  const hasLayoutNormalize = source.includes('onLayoutChanged: function (tableOrContainer) {')
    && source.includes('this.refreshManagedTables(tableOrContainer, {')
    && source.includes('normalizeWidths: true')
    && source.includes('if (settings.normalizeWidths) {')
    && source.includes('self.modules.interaction.normalizeVisibleColumnWidths($table, tableConfig);');

  if (!hasLayoutNormalize) {
    throw new Error('Expected onLayoutChanged to re-normalize visible column widths');
  }
});
