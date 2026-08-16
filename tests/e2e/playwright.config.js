import { defineConfig, devices } from '@playwright/test';

process.env.CAMPEMENT_E2E_RUN_ID ??= `${Date.now()}-${Math.random().toString(16).slice(2, 10)}`;

export default defineConfig({
  testDir: '.',
  testMatch: '**/*.spec.js',
  globalTeardown: './global-teardown.js',
  fullyParallel: false,
  workers: 1,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? [['github'], ['line']] : 'list',
  outputDir: '../../test-results/e2e',
  use: {
    baseURL: process.env.APP_BASE_URL ?? 'http://127.0.0.1:8080',
    screenshot: 'only-on-failure',
    trace: 'on-first-retry',
  },
  projects: [
    {
      name: 'Chromium complet',
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'Firefox compatibilite',
      grep: /@compatibilite/,
      use: { ...devices['Desktop Firefox'] },
    },
    {
      name: 'Mobile Chromium',
      grep: /@mobile/,
      use: { ...devices['Pixel 5'] },
    },
  ],
});
