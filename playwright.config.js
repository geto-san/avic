const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests/Browser',
  workers: 1,
  timeout: 90000,
  use: {
    baseURL: process.env.AVIC_BROWSER_URL || 'http://127.0.0.1:8080',
    headless: true,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    launchOptions: process.env.AVIC_BROWSER_EXECUTABLE ? {executablePath: process.env.AVIC_BROWSER_EXECUTABLE} : {}
  },
  reporter: [['list'], ['junit', {outputFile: 'test-results/browser.xml'}]]
});
