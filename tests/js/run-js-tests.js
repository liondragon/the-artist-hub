#!/usr/bin/env node
'use strict';

const fs = require('fs');
const path = require('path');

const tests = [];
global.test = function test(name, fn) {
  tests.push({ name, fn });
};

const testDir = __dirname;
const files = fs
  .readdirSync(testDir)
  .filter((file) => file.endsWith('.test.js'))
  .sort();

files.forEach((file) => {
  require(path.join(testDir, file));
});

let failures = 0;
tests.forEach(({ name, fn }) => {
  try {
    fn();
    process.stdout.write(`PASS ${name}\n`);
  } catch (error) {
    failures += 1;
    process.stdout.write(`FAIL ${name}\n`);
    process.stdout.write(`  ${error && error.message ? error.message : String(error)}\n`);
  }
});

process.stdout.write(`\nJS tests: ${tests.length - failures} passed, ${failures} failed.\n`);
process.exit(failures > 0 ? 1 : 0);
