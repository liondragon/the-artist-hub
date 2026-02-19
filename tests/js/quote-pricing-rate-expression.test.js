'use strict';

const fs = require('fs');
const path = require('path');

test('quote pricing rate parser accepts arithmetic expressions as override values', () => {
  const filePath = path.join(__dirname, '..', '..', 'assets', 'js', 'quote-pricing.js');
  const source = fs.readFileSync(filePath, 'utf8');

  const expressionSupport = /var mathExpression = evaluateMathExpression\(raw, NaN\);\s*if \(mathExpression\.valid\)\s*\{\s*var normalized = compactNumber\(mathExpression\.value\);\s*return \{ mode: 'override', modifier: mathExpression\.value, normalizedFormula: normalized \};\s*\}/m.test(source);
  if (!expressionSupport) {
    throw new Error('Expected parseRateFormula to support arithmetic expression overrides');
  }
});
