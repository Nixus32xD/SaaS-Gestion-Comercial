import { expect, test } from '@playwright/test';

const viewports = [
    { name: 'mobile', width: 390, height: 844 },
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
        await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);

        if (viewport.width < 1536) {
            const menu = page.getByRole('button', { name: 'Abrir navegación' });
            await menu.click();
            await expect(page.getByRole('navigation', { name: 'Secciones principales' })).toBeVisible();
            await page.keyboard.press('Escape');
            await expect(menu).toBeFocused();
        }
    });
}
