import { expect, test } from '@playwright/test';

const viewports = [
    { name: 'mobile-small', width: 360, height: 800 },
    { name: 'mobile', width: 390, height: 844 },
    { name: 'mobile-large', width: 430, height: 932 },
    { name: 'tablet', width: 768, height: 1024 },
    { name: 'notebook', width: 1366, height: 768 },
    { name: 'desktop', width: 1920, height: 1080 },
];

async function login(page) {
    await page.goto('/login');
    await page.getByLabel('Email').fill('admin@demo.test');
    await page.getByLabel('Contrasena').fill('password');
    await page.getByRole('button', { name: 'Ingresar' }).click();
    await expect(page).toHaveURL(/dashboard/);
}

async function loginAsSuperAdmin(page) {
    await page.goto('/login');
    await page.getByLabel('Email').fill('superadmin@example.com');
    await page.getByLabel('Contrasena').fill('password');
    await page.getByRole('button', { name: 'Ingresar' }).click();
    await expect(page).toHaveURL(/admin\/businesses/);
}

const expectNoHorizontalOverflow = (page) => expect.poll(() => page.evaluate(() => (
    document.documentElement.scrollWidth <= window.innerWidth
))).toBe(true);

test('login and branch selector operate without external providers', async ({ page }) => {
    await login(page);
    const branch = page.getByLabel('Sucursal');
    await expect(branch).toBeVisible();
    await branch.selectOption({ label: 'Sucursal Centro' });
    await expect(branch).toHaveValue(/\d+/);
});

test('critical operational modules are reachable with local fakes only', async ({ page }) => {
    await page.setViewportSize({ width: 1920, height: 1080 });
    await login(page);

    for (const [label, expectedHeading] of [
        ['Ventas', /ventas/i],
        ['Compras', /compras/i],
        ['Productos', /productos/i],
        ['Transferencias', /transferencias/i],
        ['Caja', /caja/i],
        ['Cuenta corriente', /cuenta corriente/i],
    ]) {
        await page
            .getByRole('navigation', { name: 'Secciones principales' })
            .getByRole('link', { name: new RegExp(`${label}$`, 'i') })
            .click();
        await expect(page.getByRole('heading', { name: expectedHeading }).first()).toBeVisible();
    }
});

test('simple cash sale keeps the fast scanner path and checkout CTA available', async ({ page }) => {
    await login(page);
    await page.goto('/sales/create');
    const productSearch = page.getByRole('combobox', { name: 'Producto' });
    await productSearch.fill('779100000001');
    await productSearch.press('Enter');
    await expect(page.getByText('Gaseosa cola 2.25L').first()).toBeVisible();
    await expect(page.getByRole('button', { name: 'Confirmar venta' }).first()).toBeEnabled();
});

for (const viewport of viewports) {
    test(`dashboard has no horizontal overflow at ${viewport.name}`, async ({ page }) => {
        await page.setViewportSize(viewport);
        await login(page);
        await expectNoHorizontalOverflow(page);

        if (viewport.width < 1536) {
            const menu = page.getByRole('button', { name: 'Abrir navegación' });
            await menu.click();
            await expect(page.getByRole('navigation', { name: 'Secciones principales' })).toBeVisible();
            await page.keyboard.press('Escape');
            await expect(menu).toBeFocused();
        }
    });
}

test('mobile operating screens expose cards, touch CTAs and no page overflow', async ({ page }, testInfo) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await login(page);

    const menu = page.getByRole('button', { name: 'Abrir navegación' });
    await menu.click();
    await expect(page.getByRole('navigation', { name: 'Secciones principales' })).toBeVisible();
    await page.keyboard.press('Tab');
    await expect.poll(() => page.evaluate(() => document.activeElement?.closest('#main-navigation') !== null)).toBe(true);
    await page.getByText('Productos', { exact: true }).first().click();
    await expect(page.getByRole('button', { name: /filtros/i })).toBeVisible();
    await page.getByRole('button', { name: /filtros/i }).click();
    await expect(page.locator('#product-advanced-filters')).toBeVisible();
    await expectNoHorizontalOverflow(page);

    await page.goto('/sales/create');
    await expect(page.getByRole('combobox', { name: 'Producto' })).toBeVisible();
    await expect(page.getByRole('button', { name: /Confirmar venta/ }).first()).toBeVisible();
    await expectNoHorizontalOverflow(page);

    await page.goto('/purchases/create');
    await expect(page.getByRole('button', { name: /Confirmar compra/ }).first()).toBeVisible();
    await expectNoHorizontalOverflow(page);

    for (const [path, heading] of [
        ['/cash-register', /caja/i],
        ['/inventory/transfers', /transferencias/i],
        ['/customers', /clientes/i],
        ['/customer-accounts', /cuenta corriente/i],
    ]) {
        await page.goto(path);
        await expect(page.getByRole('heading', { name: heading }).first()).toBeVisible();
        await expectNoHorizontalOverflow(page);
    }

    await page.goto('/dashboard');
    await page.screenshot({ path: testInfo.outputPath('mobile-dashboard.png') });
});

test('superadmin mobile screens keep administrative actions reachable', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await loginAsSuperAdmin(page);
    await expect(page.getByRole('link', { name: 'Editar' }).first()).toBeVisible();
    await expect(page.getByRole('button', { name: 'Archivar' }).first()).toBeVisible();
    await expectNoHorizontalOverflow(page);

    await page.getByRole('link', { name: 'Nuevo comercio' }).click();
    await expect(page.getByRole('button', { name: 'Crear comercio' })).toBeVisible();
    await expectNoHorizontalOverflow(page);

    await page.goto('/admin/businesses');
    await page.getByRole('link', { name: 'Editar' }).first().click();
    await expect(page.getByLabel('Sección administrativa')).toBeVisible();
    await expectNoHorizontalOverflow(page);

    await page.goto('/admin/global-products');
    await expect(page.getByRole('button', { name: /Sincronizar productos/ })).toBeVisible();
    await expectNoHorizontalOverflow(page);

    await page.goto('/admin/commercial-guide');
    await expect(page.getByRole('heading', { name: /gu[ií]a comercial/i }).first()).toBeVisible();
    await expectNoHorizontalOverflow(page);
});
