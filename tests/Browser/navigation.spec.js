const {test, expect} = require('@playwright/test');
const fs = require('node:fs');

// Visit every desk screen using its real seeded role, at both supported widths.
for (const width of [1366,390]) {
  test(`all role screens and list interactions at ${width}px`, async ({browser,baseURL}) => {
    for (const [role,email] of [['claimant','doreen@example.ug'],['adjuster','brian.okot@avic.ug'],['garage','desk@kigongomotors.ug']]) {
      const context = await browser.newContext({baseURL,viewport:{width,height:900}});
      const page = await context.newPage(); const errors = [];
      page.on('pageerror', error => errors.push(error.message));
      await page.goto('/pages/auth/login.html'); await page.locator('#email').fill(email); await page.locator('#password').fill('Demo2026!');
      await page.getByRole('button',{name:'Sign in',exact:true}).click(); await expect(page).toHaveURL(/dashboard\.html$/);
      const sessionCookie = (await context.cookies()).find(cookie => cookie.name === 'AVICSESSID');
      expect(sessionCookie).toMatchObject({secure:true,httpOnly:true,sameSite:'Lax'});
      await page.locator('.shell.is-ready').waitFor();
      const claimId = await page.evaluate(() => AVIC.claims[0]?.id);
      for (const filename of fs.readdirSync('pages/' + role).filter(name => name.endsWith('.html'))) {
        const query = filename === 'claim-detail.html' || filename === 'review.html' ? '?id=' + claimId
          : filename === 'estimate-new.html' || filename === 'document-upload.html' ? '?claim=' + claimId : '';
        await page.goto('/pages/' + role + '/' + filename + query);
        await expect(page.locator('.shell')).toHaveClass(/is-ready/);
        await expect(page.locator('.workspace-loading')).toHaveCount(0);
        await expect(page.locator('.topbar h1')).toHaveText(await page.locator('body').getAttribute('data-title'));
        await expect(page.locator('.page h1, .topbar__who')).toHaveCount(0);
        const audit = await page.evaluate(() => {
          const ids = [...document.querySelectorAll('[id]')].map(element => element.id);
          return {overflow:document.documentElement.scrollWidth > innerWidth + 1,duplicates:ids.filter((id,index) => ids.indexOf(id) !== index),misplacedActions:[...document.querySelectorAll('.page__actions')].some(actions => !actions.parentElement.matches('.filters') || actions.parentElement.lastElementChild !== actions)};
        });
        expect(audit, role + '/' + filename).toEqual({overflow:false,duplicates:[],misplacedActions:false});
      }
      await page.goto('/pages/' + role + '/notifications.html');
      await expect(page.locator('.workspace-loading')).toHaveCount(0);
      const link = page.locator('[data-open-notification]').first();
      if (await link.count()) {
        const target = await link.getAttribute('href');
        await link.click(); await expect(page).toHaveURL(new URL(target,baseURL + '/pages/' + role + '/').href);
      }
      expect(errors,role + ' browser errors').toEqual([]);
      await context.close();
    }
    const publicPage = await browser.newPage({baseURL,viewport:{width,height:900}});
    for (const path of ['/','/pages/auth/login.html','/pages/auth/register.html','/pages/auth/forgot-password.html','/pages/auth/reset-password.html','/pages/errors/403.html','/pages/errors/404.html']) {
      await publicPage.goto(path);
      expect(await publicPage.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1),path).toBe(true);
    }
    await publicPage.close();
  });
}
