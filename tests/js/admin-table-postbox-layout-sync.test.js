'use strict';

const fs = require('fs');
const path = require('path');

test('admin table core owns postbox reopen layout sync with frame-based stabilization', () => {
  const source = fs.readFileSync(
    path.resolve(__dirname, '../../assets/js/admin-tables-core.js'),
    'utf8'
  );

  const hasCoreOwnership = source.includes("$(document).on('postbox-toggled'")
    && source.includes('onPostboxToggled: function (event, postbox) {')
    && source.includes('scheduleStabilizedLayoutSync: function (scope) {')
    && source.includes('getLayoutWidthSignature: function (scope) {')
    && source.includes('window.requestAnimationFrame(tick);')
    && !source.includes('}, 120);');

  if (!hasCoreOwnership) {
    throw new Error('Expected core-owned postbox reopen sync with frame-based stabilization');
  }
});
