'use strict';

const fs = require('fs');
const path = require('path');

test('quote pricing schedules draft save on qty/rate blur', () => {
  const filePath = path.join(__dirname, '..', '..', 'assets', 'js', 'quote-pricing.js');
  const source = fs.readFileSync(filePath, 'utf8');

  const hasScheduler = /function scheduleDraftSave\(\)\s*\{[\s\S]*window\.setTimeout\(/.test(source);
  const qtyBlurSaves = /\$\(document\)\.on\('blur', '\.tah-line-qty'[\s\S]*commitFormulaFromInput\(\$\(this\), '0'\);[\s\S]*scheduleDraftSave\(\);/.test(source);
  const rateBlurSaves = /\$\(document\)\.on\('blur', '\.tah-line-rate'[\s\S]*commitFormulaFromInput\(\$\(this\), '\$'\);[\s\S]*scheduleDraftSave\(\);/.test(source);

  if (!hasScheduler || !qtyBlurSaves || !rateBlurSaves) {
    throw new Error('Expected qty/rate blur handlers to schedule debounced draft save');
  }
});

test('quote pricing queues a follow-up save while ajax save is in flight', () => {
  const filePath = path.join(__dirname, '..', '..', 'assets', 'js', 'quote-pricing.js');
  const source = fs.readFileSync(filePath, 'utf8');

  const hasPendingFlag = /var hasPendingAjaxSave = false;/.test(source);
  const marksPendingWhenBusy = /if \(isAjaxSaving\)\s*\{\s*hasPendingAjaxSave = true;\s*return true;\s*\}/.test(source);
  const drainsPendingInAlways = /\.always\(function \(\) \{\s*isAjaxSaving = false;\s*if \(hasPendingAjaxSave\)\s*\{\s*hasPendingAjaxSave = false;\s*saveDraftAjax\(\);/m.test(source);

  if (!hasPendingFlag || !marksPendingWhenBusy || !drainsPendingInAlways) {
    throw new Error('Expected in-flight ajax saves to queue and drain one follow-up save');
  }
});

test('quote pricing commits blur input into formula attributes before refresh', () => {
  const filePath = path.join(__dirname, '..', '..', 'assets', 'js', 'quote-pricing.js');
  const source = fs.readFileSync(filePath, 'utf8');

  const hasCommitHelper = /function commitFormulaFromInput\(\$field, fallbackFormula\)\s*\{[\s\S]*\$field\.attr\('data-formula', raw\);/.test(source);
  if (!hasCommitHelper) {
    throw new Error('Expected blur formula commit helper for qty/rate persistence');
  }
});
