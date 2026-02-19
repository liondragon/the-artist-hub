'use strict';

const fs = require('fs');
const path = require('path');

test('interaction divider logic preserves locked->data boundary while excluding locked->locked and data tail', () => {
  const source = fs.readFileSync(
    path.resolve(__dirname, '../../assets/js/admin-tables-interaction.js'),
    'utf8'
  );

  const hasLockedToDataBoundary = /hasLockedToDataBoundary = nextHeader && header\.locked && !nextHeader\.locked;/.test(source);
  const hasLockedToLockedBoundary = /hasLockedToLockedBoundary = nextHeader && header\.locked && nextHeader\.locked;/.test(source);
  const hasTailGuard = /isAtOrPastDataTail = header\.index >= context\.lastDataVisibleIndex;/.test(source);
  const hasBoundaryRule = /!isLeadingLockedUtility && !isAtOrPastDataTail && !hasLockedToLockedBoundary && \(isResizeBoundary \|\| hasLockedToDataBoundary\)/.test(source);

  if (!hasLockedToDataBoundary || !hasLockedToLockedBoundary || !hasTailGuard || !hasBoundaryRule) {
    throw new Error('Expected selective divider boundary logic for utility/data columns and tail suppression');
  }
});

test('max resize bound uses alert color', () => {
  const source = fs.readFileSync(
    path.resolve(__dirname, '../../assets/css/admin.css'),
    'utf8'
  );

  if (!/data-tah-resize-bound="max"\]::after\s*\{[\s\S]*?background:\s*#d63638;/.test(source)) {
    throw new Error('Expected max-bound divider color to be #d63638');
  }
});
