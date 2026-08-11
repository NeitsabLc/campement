import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: '.',
  testMatch: '**/*.spec.js',
  fullyParallel: false,
  workers: 1,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? [['github'], ['line']] : 'list',
  use: {
    baseURL: process.env.APP_BASE_URL ?? 'http://127.0.0.1:8080',
    browserName: 'chromium',
    screenshot: 'only-on-failure',
    trace: 'on-first-retry',
  },
});
