'use strict';

const fs = require('fs');
const path = require('path');

test('quote pricing schedules draft save on qty/rate blur', () => {
  const filePath = path.join(__dirname, '..', '..', 'assets', 'js', 'quote-pricing.js');
  const source = fs.readFileSync(filePath, 'utf8');

  const hasScheduler = source.includes('function scheduleDraftSave() {')
    && source.includes('window.setTimeout(');
  const qtyHandlerStart = source.indexOf("$(document).on('blur', '.tah-line-qty'");
  const rateHandlerStart = source.indexOf("$(document).on('blur', '.tah-line-rate'");
  const qtyHandlerBlock = qtyHandlerStart >= 0 ? source.slice(qtyHandlerStart, qtyHandlerStart + 800) : '';
  const rateHandlerBlock = rateHandlerStart >= 0 ? source.slice(rateHandlerStart, rateHandlerStart + 800) : '';
  const qtyBlurSaves = qtyHandlerBlock.includes("commitFormulaFromInput($(this), '0');")
    && qtyHandlerBlock.includes('scheduleDraftSave();');
  const rateBlurSaves = rateHandlerBlock.includes("commitFormulaFromInput($(this), '$');")
    && rateHandlerBlock.includes('scheduleDraftSave();');

  if (!hasScheduler || !qtyBlurSaves || !rateBlurSaves) {
    throw new Error('Expected qty/rate blur handlers to schedule debounced draft save');
  }
});

test('quote pricing queues a follow-up save while ajax save is in flight', () => {
  const filePath = path.join(__dirname, '..', '..', 'assets', 'js', 'quote-pricing.js');
  const source = fs.readFileSync(filePath, 'utf8');

  const hasPendingFlag = source.includes('var hasPendingAjaxSave = false;');
  const marksPendingWhenBusy = source.includes('if (isAjaxSaving) {')
    && source.includes('hasPendingAjaxSave = true;')
    && source.includes('return true;');
  const alwaysIndex = source.indexOf('.always(function () {');
  const alwaysBlock = alwaysIndex >= 0 ? source.slice(alwaysIndex, alwaysIndex + 400) : '';
  const drainsPendingInAlways = alwaysBlock.includes('isAjaxSaving = false;')
    && alwaysBlock.includes('if (hasPendingAjaxSave) {')
    && alwaysBlock.includes('hasPendingAjaxSave = false;')
    && alwaysBlock.includes('saveDraftAjax();');

  if (!hasPendingFlag || !marksPendingWhenBusy || !drainsPendingInAlways) {
    throw new Error('Expected in-flight ajax saves to queue and drain one follow-up save');
  }
});

test('quote pricing commits blur input into formula attributes before refresh', () => {
  const filePath = path.join(__dirname, '..', '..', 'assets', 'js', 'quote-pricing.js');
  const source = fs.readFileSync(filePath, 'utf8');

  const helperStart = source.indexOf('function commitFormulaFromInput($field, fallbackFormula) {');
  const helperBlock = helperStart >= 0 ? source.slice(helperStart, helperStart + 900) : '';
  const hasCommitHelper = helperBlock.includes("$field.attr('data-formula', raw);");
  if (!hasCommitHelper) {
    throw new Error('Expected blur formula commit helper for qty/rate persistence');
  }
});
