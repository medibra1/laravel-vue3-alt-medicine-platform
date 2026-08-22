import { expect, test } from '@playwright/test';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dirname = path.dirname(fileURLToPath(import.meta.url));

const fixture = JSON.parse(
    readFileSync(path.join(dirname, '.fixture.json'), 'utf-8'),
) as { patientId: number; ongoingTreatmentId: number; closedTreatmentId: number };

test.beforeEach(async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[type="email"]', process.env.SUPER_ADMIN_EMAIL ?? 'admin@ruqya-app.test');
    await page.fill('input[type="password"]', process.env.SUPER_ADMIN_PASSWORD ?? 'password12345');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
});

test('navigates between the three patient file tabs and shows the right content in each', async ({ page }) => {
    await page.goto(`/admin/patients/${fixture.patientId}/edit`);
    await page.waitForLoadState('networkidle');

    // Defaults to "Traitement en cours" because this patient has an ongoing treatment.
    await expect(page.getByRole('tab', { name: 'Traitement en cours' })).toHaveAttribute('aria-selected', 'true');
    await expect(page.locator('.v-window-item--active')).toContainText('Traitement en cours');
    await expect(page.locator('.v-window-item--active')).not.toContainText('Centre d\'accueil');

    await page.getByRole('tab', { name: 'Informations' }).click();
    await expect(page.locator('.v-window-item--active')).toContainText('Centre d\'accueil');

    await page.getByRole('tab', { name: 'Historique' }).click();
    await expect(page.locator('.v-window-item--active')).toContainText('Historique des traitements');
    await expect(page.locator('.v-window-item--active')).toContainText('Fermé');

    await page.getByRole('tab', { name: 'Traitement en cours' }).click();
    await expect(page.locator('.v-window-item--active')).toContainText('En cours');

    expect(page.url()).toContain('tab=ongoing');
});
