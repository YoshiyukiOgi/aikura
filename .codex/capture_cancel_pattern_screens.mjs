import { chromium } from 'playwright';
import fs from 'node:fs/promises';
import { join } from 'node:path';

const baseUrl = 'http://localhost:8080';
const outDir = 'C:/Users/ogich/OneDrive/デスクトップ/etc/codex workspace/酒蔵販売業務システム/storage/app/codex-screens';
await fs.mkdir(outDir, { recursive: true });

const browser = await chromium.launch({
  executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
  headless: true,
});
const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });

await page.goto(`${baseUrl}/retail-login`, { waitUntil: 'domcontentloaded' });
await page.fill('input[name="email"]', 'codex-demo@example.com');
await page.fill('input[name="password"]', 'password');
await Promise.all([
  page.waitForNavigation({ waitUntil: 'networkidle' }),
  page.click('button[type="submit"]'),
]);

await page.goto(`${baseUrl}/retail/purchase-orders`, { waitUntil: 'networkidle' });
console.log(`retail page: ${page.url()} / ${await page.title()}`);
await page.screenshot({ path: join(outDir, 'cancel-pattern-retail-purchase-orders.png'), fullPage: true });

await page.goto(`${baseUrl}/sales-orders`, { waitUntil: 'networkidle' });
await page.waitForTimeout(2500);
console.log(`brewery page: ${page.url()} / ${await page.title()}`);
await page.screenshot({ path: join(outDir, 'cancel-pattern-brewery-sales-orders.png'), fullPage: true });

await page.fill('#filter-q', 'O-202608-000007');
await page.selectOption('#filter-status', 'cancelled');
await page.waitForTimeout(1000);
await page.screenshot({ path: join(outDir, 'cancel-pattern-brewery-p2-cancelled.png'), fullPage: true });

await browser.close();

const files = [
  join(outDir, 'cancel-pattern-retail-purchase-orders.png'),
  join(outDir, 'cancel-pattern-brewery-sales-orders.png'),
  join(outDir, 'cancel-pattern-brewery-p2-cancelled.png'),
];
for (const file of files) {
  const stat = await fs.stat(file);
  console.log(`${file} ${stat.size}`);
}
