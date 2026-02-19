const { test, expect } = require('@playwright/test');

const adminUser = process.env.TAH_E2E_USERNAME || '';
const adminPass = process.env.TAH_E2E_PASSWORD || '';
const quotePostId = process.env.TAH_E2E_QUOTE_ID || '';

const tableKey = 'pricing_editor';
const tableSelector = '#tah-pricing-groups .tah-pricing-table-editor';

function requireE2EEnv() {
    const missing = [];
    if (!adminUser) {
        missing.push('TAH_E2E_USERNAME');
    }
    if (!adminPass) {
        missing.push('TAH_E2E_PASSWORD');
    }
    if (!quotePostId) {
        missing.push('TAH_E2E_QUOTE_ID');
    }
    return missing;
}

function normalizeBaseURL(baseURL) {
    let parsed;
    try {
        parsed = new URL(baseURL);
    } catch (error) {
        throw new Error(`Invalid Playwright baseURL: "${String(baseURL)}"`);
    }

    if (parsed.protocol !== 'https:') {
        parsed.protocol = 'https:';
    }

    return parsed.toString().replace(/\/$/, '');
}

async function loginAndOpenQuoteEditor(page, baseURL) {
    const adminBaseURL = normalizeBaseURL(baseURL);
    const editUrl = `${adminBaseURL}/wp-admin/post.php?post=${quotePostId}&action=edit`;
    const loginUrl = `${adminBaseURL}/wp-login.php?redirect_to=${encodeURIComponent(editUrl)}`;

    await page.goto(loginUrl, { waitUntil: 'domcontentloaded' });
    if (page.url().includes('wp-login.php')) {
        await page.fill('#user_login', adminUser);
        await page.fill('#user_pass', adminPass);
        await page.click('#wp-submit');
        await page.waitForURL(`**/wp-admin/post.php?post=${quotePostId}&action=edit**`, { timeout: 20000 });
    } else {
        await page.goto(editUrl, { waitUntil: 'domcontentloaded' });
    }

    const hasPricingEditor = await page.locator('#tah-quote-pricing').isVisible();
    if (!hasPricingEditor) {
        const currentUrl = page.url();
        const headingText = await page.locator('h1').first().textContent().catch(() => '');
        throw new Error(
            `Quote editor not reachable. url=${currentUrl}; h1=${String(headingText || '').trim()}; expected #tah-quote-pricing. ` +
            `Check TAH_E2E_BASE_URL, TAH_E2E_QUOTE_ID (must be an existing quotes post), and user edit permissions.`
        );
    }
}

async function clearVariantPrefs(page, variant) {
    const ajaxResult = await page.evaluate(async ({ tableKey, variant }) => {
        const config = window.tahAdminTablesConfig || null;
        if (!config || !config.nonce || !config.screenId) {
            return {
                ok: false,
                reason: 'missing_config',
            };
        }

        const form = new URLSearchParams();
        form.set('action', 'tah_save_table_prefs');
        form.set('nonce', config.nonce);
        form.set('screen_id', config.screenId);
        form.set('table_key', tableKey);
        form.set('variant', variant);

        const ajaxUrl = window.ajaxurl || '/wp-admin/admin-ajax.php';
        const response = await fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            },
            body: form.toString(),
        });

        const rawText = await response.text();
        let payload = null;
        try {
            payload = JSON.parse(rawText);
        } catch (error) {
            payload = null;
        }

        return {
            ok: response.ok,
            status: response.status,
            payload,
            rawText,
        };
    }, { tableKey, variant });

    if (!ajaxResult || !ajaxResult.ok || !ajaxResult.payload || ajaxResult.payload.success !== true) {
        throw new Error(`Failed to clear table prefs for variant "${variant}"`);
    }
}

async function setQuoteFormat(page, format) {
    const formatSelect = page.locator('#tah-quote-format');
    await expect(formatSelect).toBeVisible();
    await formatSelect.selectOption(format);
    await expect(formatSelect).toHaveValue(format);
    await page.waitForFunction(({ selector, expectedFormat }) => {
        const table = document.querySelector(selector);
        if (!table) {
            return false;
        }

        const variant = table.getAttribute('data-tah-variant');
        if (!variant) {
            return true;
        }

        return variant === expectedFormat;
    }, { selector: tableSelector, expectedFormat: format });
}

async function waitForTableReady(page) {
    await page.waitForFunction((selector) => {
        const table = document.querySelector(selector);
        if (!table) {
            return false;
        }
        return table.classList.contains('tah-enhanced-table')
            && table.querySelectorAll('colgroup col').length > 0
            && table.querySelectorAll('.tah-admin-resize-handle').length > 0;
    }, tableSelector);
}

async function getVisibleHeaderOrder(page) {
    return await page.evaluate((selector) => {
        return Array.from(document.querySelectorAll(`${selector} thead th`))
            .filter((th) => window.getComputedStyle(th).display !== 'none')
            .map((th) => th.getAttribute('data-tah-col') || '');
    }, tableSelector);
}

async function getColumnWidth(page, columnKey) {
    return await page.evaluate(({ selector, key }) => {
        const table = document.querySelector(selector);
        if (!table) {
            return 0;
        }

        const col = table.querySelector(`colgroup col[data-col-key="${key}"]`);
        if (!col) {
            return 0;
        }

        return Math.round(parseFloat(window.getComputedStyle(col).width || '0'));
    }, { selector: tableSelector, key: columnKey });
}

async function getDescriptionFieldWidth(page) {
    return await page.evaluate((selector) => {
        const field = document.querySelector(`${selector} tbody .tah-line-description`);
        if (!field) {
            return 0;
        }
        return Math.round(field.getBoundingClientRect().width);
    }, tableSelector);
}

async function togglePricingPostbox(page) {
    const postbox = page.locator('#tah_quote_pricing');
    await expect(postbox).toHaveCount(1);
    const visibleToggle = postbox.locator('.postbox-header .hndle:visible, .postbox-header .handlediv:visible').first();
    if (await visibleToggle.count()) {
        await visibleToggle.scrollIntoViewIfNeeded();
        await visibleToggle.click({ force: true });
        return;
    }

    await page.evaluate(() => {
        const postboxEl = document.querySelector('#tah_quote_pricing');
        if (!postboxEl) {
            return;
        }

        const toggle = postboxEl.querySelector('.postbox-header .hndle, .postbox-header .handlediv');
        if (toggle instanceof HTMLElement) {
            toggle.click();
        }
    });
}

async function getVisibleColumnWidths(page) {
    return await page.evaluate((selector) => {
        const table = document.querySelector(selector);
        if (!table) {
            return {};
        }

        const result = {};
        const headers = Array.from(table.querySelectorAll('thead th[data-tah-col]'))
            .filter((th) => window.getComputedStyle(th).display !== 'none');

        headers.forEach((th) => {
            const key = String(th.getAttribute('data-tah-col') || '');
            if (!key) {
                return;
            }
            const col = table.querySelector(`colgroup col[data-col-key="${key}"]`);
            if (!col) {
                return;
            }
            result[key] = Math.round(parseFloat(window.getComputedStyle(col).width || '0'));
        });

        return result;
    }, tableSelector);
}

async function getVisibleWidthsForKeys(page, keys) {
    const all = await getVisibleColumnWidths(page);
    const picked = {};
    keys.forEach((key) => {
        if (Object.prototype.hasOwnProperty.call(all, key)) {
            picked[key] = all[key];
        }
    });
    return picked;
}

async function getTableOverflowState(page) {
    return await page.evaluate((selector) => {
        const table = document.querySelector(selector);
        if (!table) {
            return null;
        }

        const wrap = table.closest('.tah-group-table-wrap');
        if (!wrap) {
            return null;
        }

        return {
            tableWidth: Math.round(table.getBoundingClientRect().width),
            wrapWidth: Math.round(wrap.getBoundingClientRect().width),
            wrapScrollWidth: Math.round(wrap.scrollWidth),
            wrapClientWidth: Math.round(wrap.clientWidth),
            hasHorizontalOverflow: wrap.scrollWidth > (wrap.clientWidth + 1),
        };
    }, tableSelector);
}

async function waitForManagedTableCount(page, count) {
    await page.waitForFunction(({ selector, expectedCount }) => {
        const tables = Array.from(document.querySelectorAll(selector));
        if (tables.length !== expectedCount) {
            return false;
        }
        return tables.every((table) => {
            return table.classList.contains('tah-enhanced-table')
                && table.querySelectorAll('colgroup col').length > 0
                && table.querySelectorAll('.tah-admin-resize-handle').length > 0;
        });
    }, { selector: tableSelector, expectedCount: count });
}

async function dragResizeHandle(page, columnKey, deltaX) {
    const handle = page.locator(`${tableSelector} thead th[data-tah-col="${columnKey}"] .tah-admin-resize-handle`).first();
    await expect(handle).toBeVisible();
    await handle.scrollIntoViewIfNeeded();

    const box = await handle.boundingBox();
    if (!box) {
        throw new Error(`Resize handle for "${columnKey}" is not visible.`);
    }

    const startX = box.x + (box.width / 2);
    const startY = box.y + (box.height / 2);

    await page.mouse.move(startX, startY);
    await page.mouse.down();
    await page.mouse.move(startX + deltaX, startY, { steps: 16 });
    await page.mouse.up();
}

async function dragResizeHandleBySteps(page, columnKey, deltaX, steps = 20) {
    const handle = page.locator(`${tableSelector} thead th[data-tah-col="${columnKey}"] .tah-admin-resize-handle`).first();
    await expect(handle).toBeVisible();
    await handle.scrollIntoViewIfNeeded();

    const box = await handle.boundingBox();
    if (!box) {
        throw new Error(`Resize handle for "${columnKey}" is not visible.`);
    }

    const startX = box.x + (box.width / 2);
    const startY = box.y + (box.height / 2);
    await page.mouse.move(startX, startY);
    await page.mouse.down();
    await page.mouse.move(startX + deltaX, startY, { steps });
    await page.mouse.up();
}

async function dragResizeHandleAndSampleTracking(page, columnKey, deltaX, steps = 8) {
    const handle = page.locator(`${tableSelector} thead th[data-tah-col="${columnKey}"] .tah-admin-resize-handle`).first();
    await expect(handle).toBeVisible();
    await handle.scrollIntoViewIfNeeded();

    const box = await handle.boundingBox();
    if (!box) {
        throw new Error(`Resize handle for "${columnKey}" is not visible.`);
    }

    const startX = box.x + (box.width / 2);
    const startY = box.y + (box.height / 2);
    await page.mouse.move(startX, startY);
    await page.mouse.down();
    await page.mouse.move(startX + deltaX, startY, { steps });

    const sample = await page.evaluate(({ selector, key, pointerX }) => {
        const table = document.querySelector(selector);
        if (!table) {
            return null;
        }

        const header = table.querySelector(`thead th[data-tah-col="${key}"]`);
        if (!header) {
            return null;
        }

        const rect = header.getBoundingClientRect();
        return {
            pointerX: Math.round(pointerX),
            rightEdgeX: Math.round(rect.right),
            leftEdgeX: Math.round(rect.left),
            width: Math.round(rect.width),
        };
    }, { selector: tableSelector, key: columnKey, pointerX: startX + deltaX });

    await page.mouse.up();
    return sample;
}

async function dragResizeHandleAndSampleBoundState(page, columnKey, deltaX, steps = 12) {
    const handle = page.locator(`${tableSelector} thead th[data-tah-col="${columnKey}"] .tah-admin-resize-handle`).first();
    await expect(handle).toBeVisible();
    await handle.scrollIntoViewIfNeeded();

    const box = await handle.boundingBox();
    if (!box) {
        throw new Error(`Resize handle for "${columnKey}" is not visible.`);
    }

    const startX = box.x + (box.width / 2);
    const startY = box.y + (box.height / 2);
    await page.mouse.move(startX, startY);
    await page.mouse.down();
    await page.mouse.move(startX + deltaX, startY, { steps });

    const boundState = await page.evaluate(({ selector, key }) => {
        const th = document.querySelector(`${selector} thead th[data-tah-col="${key}"]`);
        if (!th) {
            return '';
        }
        return String(th.getAttribute('data-tah-resize-bound') || '');
    }, { selector: tableSelector, key: columnKey });

    await page.mouse.up();
    return boundState;
}

async function dragResizeHandleAndCaptureWidths(page, columnKey, deltaX) {
    const handle = page.locator(`${tableSelector} thead th[data-tah-col="${columnKey}"] .tah-admin-resize-handle`).first();
    await expect(handle).toBeVisible();
    await handle.scrollIntoViewIfNeeded();

    const box = await handle.boundingBox();
    if (!box) {
        throw new Error(`Resize handle for "${columnKey}" is not visible.`);
    }

    const startX = box.x + (box.width / 2);
    const startY = box.y + (box.height / 2);

    await page.mouse.move(startX, startY);
    await page.mouse.down();
    await page.mouse.move(startX + deltaX, startY, { steps: 20 });

    const widthDuringDrag = await getColumnWidth(page, columnKey);

    await page.mouse.up();
    const widthAfterRelease = await getColumnWidth(page, columnKey);
    await page.waitForTimeout(350);
    const widthAfterSettle = await getColumnWidth(page, columnKey);

    return {
        widthDuringDrag,
        widthAfterRelease,
        widthAfterSettle,
    };
}

async function dragHeaderBefore(page, sourceKey, targetKey) {
    const source = page.locator(`${tableSelector} thead th[data-tah-col="${sourceKey}"]`).first();
    const target = page.locator(`${tableSelector} thead th[data-tah-col="${targetKey}"]`).first();
    await expect(source).toBeVisible();
    await expect(target).toBeVisible();
    await source.scrollIntoViewIfNeeded();
    await target.scrollIntoViewIfNeeded();

    const sourceBox = await source.boundingBox();
    const targetBox = await target.boundingBox();
    if (!sourceBox || !targetBox) {
        throw new Error(`Cannot drag "${sourceKey}" before "${targetKey}" due to missing header box.`);
    }

    const startX = sourceBox.x + (sourceBox.width / 2);
    const startY = sourceBox.y + (sourceBox.height / 2);
    const targetX = targetBox.x + 4;
    const targetY = targetBox.y + (targetBox.height / 2);

    await page.mouse.move(startX, startY);
    await page.mouse.down();
    await page.mouse.move((startX + targetX) / 2, targetY, { steps: 10 });
    await page.mouse.move(targetX, targetY, { steps: 10 });
    await page.mouse.up();
}

async function startHeaderDragByDelta(page, sourceKey, deltaX) {
    const source = page.locator(`${tableSelector} thead th[data-tah-col="${sourceKey}"]`).first();
    await expect(source).toBeVisible();
    await source.scrollIntoViewIfNeeded();

    const sourceBox = await source.boundingBox();
    if (!sourceBox) {
        throw new Error(`Cannot begin drag "${sourceKey}" due to missing header box.`);
    }

    const startX = sourceBox.x + (sourceBox.width / 2);
    const startY = sourceBox.y + (sourceBox.height / 2);
    const moveX = startX + deltaX;

    await page.mouse.move(startX, startY);
    await page.mouse.down();
    await page.mouse.move(moveX, startY, { steps: 8 });
}

async function readDragHelperState(page) {
    return await page.evaluate(() => {
        const helper = document.querySelector('.ui-sortable-helper');
        if (!helper) {
            return { exists: false };
        }

        const style = window.getComputedStyle(helper);
        const text = String(helper.textContent || '').trim();
        const rect = helper.getBoundingClientRect();

        return {
            exists: true,
            text,
            color: style.color,
            display: style.display,
            visibility: style.visibility,
            opacity: style.opacity,
            width: Math.round(rect.width),
            height: Math.round(rect.height),
        };
    });
}

async function readDragPlaceholderState(page) {
    return await page.evaluate(() => {
        const placeholder = document.querySelector('th.tah-admin-column-placeholder');
        if (!placeholder) {
            return { exists: false };
        }

        const style = window.getComputedStyle(placeholder);
        const text = String(placeholder.textContent || '').trim();
        const rect = placeholder.getBoundingClientRect();

        return {
            exists: true,
            text,
            tag: placeholder.tagName.toLowerCase(),
            visibility: style.visibility,
            opacity: style.opacity,
            width: Math.round(rect.width),
            height: Math.round(rect.height),
        };
    });
}

async function getHeaderLabel(page, key) {
    return await page.evaluate(({ selector, columnKey }) => {
        const th = document.querySelector(`${selector} thead th[data-tah-col="${columnKey}"]`);
        if (!th) {
            return '';
        }
        return String(th.textContent || '').trim();
    }, { selector: tableSelector, columnKey: key });
}

async function clickResetAction(page) {
    const footerButton = page.locator('#tah-quote-pricing .tah-pricing-editor-footer .tah-admin-reset-table').first();
    if (await footerButton.isVisible().catch(() => false)) {
        await footerButton.click();
        return;
    }

    const button = page.locator('#tah-pricing-groups .tah-admin-table-controls .tah-admin-reset-table').first();
    await expect(button).toBeVisible();
    await button.click();
}

async function assertTableFitsContainer(page) {
    const metrics = await page.evaluate((selector) => {
        const table = document.querySelector(selector);
        if (!table) {
            return null;
        }

        const wrap = table.closest('.tah-group-table-wrap');
        const tableWidth = Math.round(table.getBoundingClientRect().width);
        const wrapWidth = wrap ? Math.round(wrap.getBoundingClientRect().width) : tableWidth;
        const overflow = table.scrollWidth - table.clientWidth;

        return {
            tableWidth,
            wrapWidth,
            overflow
        };
    }, tableSelector);

    expect(metrics).not.toBeNull();
    // Contract allows either slack (table narrower) or horizontal overflow (table wider).
    // Keep this as a basic sanity guard against broken geometry only.
    expect(metrics.tableWidth).toBeGreaterThan(0);
    expect(metrics.wrapWidth).toBeGreaterThan(0);
    expect(metrics.tableWidth).toBeLessThan(metrics.wrapWidth * 4);
    expect(metrics.overflow).toBeGreaterThanOrEqual(0);
}

async function runFormatFlow(page, baseURL, format, resizeColumn, moveSource, moveTarget) {
    const editUrl = `${normalizeBaseURL(baseURL)}/wp-admin/post.php?post=${quotePostId}&action=edit`;

    await setQuoteFormat(page, format);
    await waitForTableReady(page);
    await clearVariantPrefs(page, format);
    await page.goto(editUrl, { waitUntil: 'domcontentloaded' });

    await setQuoteFormat(page, format);
    await waitForTableReady(page);
    await assertTableFitsContainer(page);
    const baselineWidth = await getColumnWidth(page, resizeColumn);
    const baselineOrder = await getVisibleHeaderOrder(page);

    await dragResizeHandle(page, resizeColumn, 80);
    await expect.poll(async () => {
        const currentWidth = await getColumnWidth(page, resizeColumn);
        return Math.abs(currentWidth - baselineWidth);
    }, { timeout: 3000 }).toBeGreaterThan(12);
    const widthAfterResize = await getColumnWidth(page, resizeColumn);

    const orderBefore = await getVisibleHeaderOrder(page);
    expect(orderBefore.indexOf(moveSource)).toBeGreaterThan(orderBefore.indexOf(moveTarget));
    await dragHeaderBefore(page, moveSource, moveTarget);
    await expect.poll(async () => {
        const order = await getVisibleHeaderOrder(page);
        return order.indexOf(moveSource) - order.indexOf(moveTarget);
    }, { timeout: 3000 }).toBeLessThan(0);
    await assertTableFitsContainer(page);

    await page.waitForTimeout(1200);
    await page.goto(editUrl, { waitUntil: 'domcontentloaded' });

    await setQuoteFormat(page, format);
    await waitForTableReady(page);
    await assertTableFitsContainer(page);

    const widthAfterReload = await getColumnWidth(page, resizeColumn);

    const orderAfterReload = await getVisibleHeaderOrder(page);
    expect(orderAfterReload.indexOf(moveSource)).toBeGreaterThan(-1);
    expect(orderAfterReload.indexOf(moveTarget)).toBeGreaterThan(-1);
    expect(orderAfterReload.indexOf(moveSource)).toBeLessThan(orderAfterReload.indexOf(moveTarget));

    await clickResetAction(page);
    await page.waitForTimeout(900);

    await expect.poll(async () => (await getVisibleHeaderOrder(page)).join('|'), { timeout: 3000 }).toBe(baselineOrder.join('|'));

    await page.goto(editUrl, { waitUntil: 'domcontentloaded' });
    await setQuoteFormat(page, format);
    await waitForTableReady(page);
    await assertTableFitsContainer(page);

    const widthAfterResetReload = await getColumnWidth(page, resizeColumn);
    const orderAfterResetReload = await getVisibleHeaderOrder(page);
    expect(orderAfterResetReload).toEqual(baselineOrder);
    expect(Math.abs(widthAfterResetReload - baselineWidth)).toBeLessThan(28);
}

test.describe('Quote pricing table columns smoke', () => {
    test.beforeEach(async ({ page, baseURL }) => {
        const missing = requireE2EEnv();
        test.skip(missing.length > 0, `Missing env vars: ${missing.join(', ')}`);
        await loginAndOpenQuoteEditor(page, baseURL);
    });

    test('standard format: load → resize → reorder → refresh', async ({ page, baseURL }) => {
        await runFormatFlow(page, baseURL, 'standard', 'description', 'rate', 'qty');
    });

    test('insurance format: load → resize → reorder → refresh', async ({ page, baseURL }) => {
        await runFormatFlow(page, baseURL, 'insurance', 'description', 'qty', 'labor');
    });

    test('resize does not snap back after mouseup (regression)', async ({ page, baseURL }) => {
        const editUrl = `${normalizeBaseURL(baseURL)}/wp-admin/post.php?post=${quotePostId}&action=edit`;

        await setQuoteFormat(page, 'standard');
        await waitForTableReady(page);
        await clearVariantPrefs(page, 'standard');
        await page.goto(editUrl, { waitUntil: 'domcontentloaded' });

        await setQuoteFormat(page, 'standard');
        await waitForTableReady(page);

        const baselineWidth = await getColumnWidth(page, 'description');
        const sample = await dragResizeHandleAndCaptureWidths(page, 'description', 120);

        // Sanity: drag should visibly change width while dragging.
        expect(sample.widthDuringDrag).toBeGreaterThan(baselineWidth + 24);

        // Regression check: width should not snap back on release/settle.
        expect(sample.widthAfterRelease).toBeGreaterThan(baselineWidth + 16);
        expect(sample.widthAfterSettle).toBeGreaterThan(baselineWidth + 16);
        expect(sample.widthAfterSettle).toBeGreaterThanOrEqual(sample.widthAfterRelease - 10);
    });

    test('reorder drag helper keeps visible header text (regression)', async ({ page }) => {
        await setQuoteFormat(page, 'standard');
        await waitForTableReady(page);

        const pairs = [
            { source: 'item', deltaX: 48 },
            { source: 'rate', deltaX: -48 },
        ];

        for (const pair of pairs) {
            const expectedLabel = await getHeaderLabel(page, pair.source);
            await startHeaderDragByDelta(page, pair.source, pair.deltaX);
            const helper = await readDragHelperState(page);
            const placeholder = await readDragPlaceholderState(page);
            await page.mouse.up();

            expect(helper.exists).toBe(true);
            expect(helper.text.length).toBeGreaterThan(0);
            expect(helper.visibility).not.toBe('hidden');
            expect(Number(helper.opacity)).toBeGreaterThan(0.05);
            expect(helper.width).toBeGreaterThan(20);
            expect(helper.height).toBeGreaterThan(10);
            if (expectedLabel !== '') {
                expect(helper.text.toLowerCase()).toContain(expectedLabel.toLowerCase());
            }

            expect(placeholder.exists).toBe(true);
            expect(placeholder.tag).toBe('th');
            if (expectedLabel !== '') {
                expect(placeholder.text.toLowerCase()).toContain(expectedLabel.toLowerCase());
            }
        }
    });

    test('resize active column only (trace regression)', async ({ page }) => {
        await setQuoteFormat(page, 'standard');
        await waitForTableReady(page);
        await clearVariantPrefs(page, 'standard');
        await page.reload({ waitUntil: 'domcontentloaded' });
        await setQuoteFormat(page, 'standard');
        await waitForTableReady(page);

        const targetKey = 'item';
        const before = await getVisibleColumnWidths(page);
        expect(before[targetKey]).toBeGreaterThan(0);

        await dragResizeHandleBySteps(page, targetKey, 180, 30);
        const afterGrow = await getVisibleColumnWidths(page);
        expect(afterGrow[targetKey]).toBeGreaterThan(before[targetKey] + 20);

        const toleratedDriftPx = 4;
        Object.keys(before).forEach((key) => {
            if (key === targetKey) {
                return;
            }
            const delta = Math.abs((afterGrow[key] || 0) - (before[key] || 0));
            expect(delta, `grow drift key=${key} before=${before[key]} after=${afterGrow[key]}`).toBeLessThanOrEqual(toleratedDriftPx);
        });

        await dragResizeHandleBySteps(page, targetKey, -180, 30);
        const afterShrink = await getVisibleColumnWidths(page);
        Object.keys(before).forEach((key) => {
            const delta = Math.abs((afterShrink[key] || 0) - (before[key] || 0));
            expect(delta, `shrink drift key=${key} before=${before[key]} after=${afterShrink[key]}`).toBeLessThanOrEqual(8);
        });
    });

    test('overflow appears then clears when resizing back (trace regression)', async ({ page }) => {
        await setQuoteFormat(page, 'standard');
        await waitForTableReady(page);
        await clearVariantPrefs(page, 'standard');
        await page.reload({ waitUntil: 'domcontentloaded' });
        await setQuoteFormat(page, 'standard');
        await waitForTableReady(page);

        const targetKey = 'item';
        const baselineOverflow = await getTableOverflowState(page);
        expect(baselineOverflow).not.toBeNull();

        await dragResizeHandleBySteps(page, targetKey, 260, 34);
        const overflowAfterGrow = await getTableOverflowState(page);
        expect(overflowAfterGrow).not.toBeNull();
        expect(overflowAfterGrow.hasHorizontalOverflow).toBe(true);

        await dragResizeHandleBySteps(page, targetKey, -260, 34);
        const overflowAfterShrink = await getTableOverflowState(page);
        expect(overflowAfterShrink).not.toBeNull();
        expect(overflowAfterShrink.hasHorizontalOverflow).toBe(false);
    });

    test('after reset, active divider tracks pointer during resize (regression)', async ({ page }) => {
        await setQuoteFormat(page, 'standard');
        await waitForTableReady(page);
        await clickResetAction(page);
        await page.waitForTimeout(300);
        await waitForTableReady(page);

        const sample = await dragResizeHandleAndSampleTracking(page, 'description', 70, 10);
        expect(sample).not.toBeNull();
        // Pointer should remain close to the active column right edge while dragging.
        expect(Math.abs(sample.pointerX - sample.rightEdgeX)).toBeLessThanOrEqual(10);
        expect(sample.width).toBeGreaterThan(30);
    });

    test('utility columns remain fixed while resizing data columns (regression)', async ({ page }) => {
        await setQuoteFormat(page, 'standard');
        await waitForTableReady(page);
        await clearVariantPrefs(page, 'standard');
        await page.reload({ waitUntil: 'domcontentloaded' });
        await setQuoteFormat(page, 'standard');
        await waitForTableReady(page);

        const utilityKeys = ['handle', 'index', 'actions'];
        const baseline = await getVisibleColumnWidths(page);
        utilityKeys.forEach((key) => {
            expect(baseline[key]).toBeGreaterThan(0);
        });

        await dragResizeHandleBySteps(page, 'item', 180, 30);
        await dragResizeHandleBySteps(page, 'description', -160, 30);
        const after = await getVisibleColumnWidths(page);

        utilityKeys.forEach((key) => {
            const delta = Math.abs((after[key] || 0) - (baseline[key] || 0));
            expect(delta, `utility drift key=${key} baseline=${baseline[key]} after=${after[key]}`).toBeLessThanOrEqual(2);
        });
    });

    test('variant toggle keeps shared column widths stable (phase 2)', async ({ page }) => {
        await setQuoteFormat(page, 'standard');
        await waitForTableReady(page);
        await clearVariantPrefs(page, 'standard');
        await clearVariantPrefs(page, 'insurance');
        await page.reload({ waitUntil: 'domcontentloaded' });
        await setQuoteFormat(page, 'standard');
        await waitForTableReady(page);

        const trackedKeys = ['item', 'description', 'qty', 'rate', 'amount'];
        const baseline = await getVisibleWidthsForKeys(page, trackedKeys);
        expect(Object.keys(baseline).length).toBeGreaterThan(2);

        await dragResizeHandleBySteps(page, 'item', 140, 24);
        await dragResizeHandleBySteps(page, 'description', -90, 20);
        const tuned = await getVisibleWidthsForKeys(page, trackedKeys);

        await setQuoteFormat(page, 'insurance');
        await waitForTableReady(page);
        await setQuoteFormat(page, 'standard');
        await waitForTableReady(page);
        const afterToggleRoundTrip = await getVisibleWidthsForKeys(page, trackedKeys);

        Object.keys(tuned).forEach((key) => {
            const delta = Math.abs((afterToggleRoundTrip[key] || 0) - (tuned[key] || 0));
            expect(delta, `variant toggle drift key=${key} tuned=${tuned[key]} after=${afterToggleRoundTrip[key]}`).toBeLessThanOrEqual(8);
        });
    });

    test('adding row/group does not mutate existing table widths (phase 2)', async ({ page }) => {
        await setQuoteFormat(page, 'standard');
        await waitForTableReady(page);
        await clearVariantPrefs(page, 'standard');
        await page.reload({ waitUntil: 'domcontentloaded' });
        await setQuoteFormat(page, 'standard');
        await waitForTableReady(page);
        await waitForManagedTableCount(page, 1);

        await dragResizeHandleBySteps(page, 'item', 120, 20);
        await dragResizeHandleBySteps(page, 'description', -80, 16);
        const trackedKeys = ['handle', 'index', 'item', 'description', 'qty', 'rate', 'amount', 'actions'];
        const before = await getVisibleWidthsForKeys(page, trackedKeys);

        await page.locator('#tah-pricing-groups .tah-group-card').first().locator('.tah-add-line-item').click();
        await page.waitForFunction(() => document.querySelectorAll('#tah-pricing-groups .tah-group-card').length >= 1);
        const afterAddRow = await getVisibleWidthsForKeys(page, trackedKeys);

        Object.keys(before).forEach((key) => {
            const delta = Math.abs((afterAddRow[key] || 0) - (before[key] || 0));
            expect(delta, `add-row drift key=${key} before=${before[key]} after=${afterAddRow[key]}`).toBeLessThanOrEqual(4);
        });

        await page.locator('#tah-add-group').click();
        await waitForManagedTableCount(page, 2);
        const afterAddGroup = await getVisibleWidthsForKeys(page, trackedKeys);

        Object.keys(before).forEach((key) => {
            const delta = Math.abs((afterAddGroup[key] || 0) - (before[key] || 0));
            expect(delta, `add-group drift key=${key} before=${before[key]} after=${afterAddGroup[key]}`).toBeLessThanOrEqual(6);
        });
    });

    test('postbox collapse + refresh + reopen keeps description width stable (regression)', async ({ page, baseURL }) => {
        const editUrl = `${normalizeBaseURL(baseURL)}/wp-admin/post.php?post=${quotePostId}&action=edit`;

        await setQuoteFormat(page, 'standard');
        await waitForTableReady(page);
        await clearVariantPrefs(page, 'standard');
        await page.reload({ waitUntil: 'domcontentloaded' });
        await setQuoteFormat(page, 'standard');
        await waitForTableReady(page);

        const postbox = page.locator('#tah_quote_pricing');
        const baselineDescriptionColWidth = await getColumnWidth(page, 'description');
        const baselineDescriptionFieldWidth = await getDescriptionFieldWidth(page);
        expect(baselineDescriptionColWidth).toBeGreaterThan(0);
        expect(baselineDescriptionFieldWidth).toBeGreaterThan(0);

        await togglePricingPostbox(page);
        await expect(postbox).toHaveClass(/closed/);

        await page.goto(editUrl, { waitUntil: 'domcontentloaded' });
        const isClosedAfterReload = await postbox.evaluate((el) => el.classList.contains('closed'));
        if (!isClosedAfterReload) {
            await togglePricingPostbox(page);
            await expect(postbox).toHaveClass(/closed/);
            await page.reload({ waitUntil: 'domcontentloaded' });
        }

        await togglePricingPostbox(page);
        await expect(postbox).not.toHaveClass(/closed/);
        await waitForTableReady(page);
        await assertTableFitsContainer(page);

        await expect.poll(
            async () => Math.abs((await getColumnWidth(page, 'description')) - baselineDescriptionColWidth),
            { timeout: 5000 }
        ).toBeLessThanOrEqual(4);

        await expect.poll(
            async () => Math.abs((await getDescriptionFieldWidth(page)) - baselineDescriptionFieldWidth),
            { timeout: 5000 }
        ).toBeLessThanOrEqual(4);
    });

    test('resize shows boundary feedback when min/max bounds are hit (phase 3)', async ({ page }) => {
        await setQuoteFormat(page, 'standard');
        await waitForTableReady(page);

        const key = 'qty';
        const minBound = await dragResizeHandleAndSampleBoundState(page, key, -320, 20);
        expect(minBound).toBe('min');

        const maxBound = await dragResizeHandleAndSampleBoundState(page, key, 320, 20);
        expect(maxBound).toBe('max');
    });
});
