'use strict';

const fs = require('fs');
const path = require('path');

test('quote pricing rate parser accepts arithmetic expressions as override values', () => {
  const filePath = path.join(__dirname, '..', '..', 'assets', 'js', 'quote-pricing.js');
  const source = fs.readFileSync(filePath, 'utf8');

  const parserStart = source.indexOf('function parseRateFormula(input) {');
  const parserBlock = parserStart >= 0 ? source.slice(parserStart, parserStart + 1400) : '';
  const expressionSupport = parserBlock.includes('var mathExpression = evaluateMathExpression(raw, NaN);')
    && parserBlock.includes('if (mathExpression.valid) {')
    && parserBlock.includes("return { mode: 'override', modifier: mathExpression.value, normalizedFormula: normalized, valid: true };");
  if (!expressionSupport) {
    throw new Error('Expected parseRateFormula to support arithmetic expression overrides');
  }
});
