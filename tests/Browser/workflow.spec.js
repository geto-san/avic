const {test, expect} = require('@playwright/test');
const fs = require('node:fs');

async function login(page, email, password = 'Demo2026!') {
  await page.goto('/pages/auth/login.html');
  await page.locator('#email').fill(email);
  await page.locator('#password').fill(password);
  await page.getByRole('button', {name: 'Sign in', exact: true}).click();
  await expect(page).toHaveURL(/dashboard\.html$/);
  await expect(page.locator('.shell')).toHaveClass(/is-ready/);
  const menu = page.getByRole('button', {name: 'Menu', exact: true});
  if (await menu.isVisible()) await menu.click();
}

async function confirm(page, name) {
  await page.locator('.modal').getByRole('button', {name, exact: true}).click();
}

for (const viewport of [{width: 1366, height: 900}, {width: 390, height: 844}]) {
  test(`registration to policy to closed claim at ${viewport.width}px`, async ({browser, baseURL}, testInfo) => {
    const contexts = await Promise.all([0, 1, 2, 3].map(() => browser.newContext({baseURL, viewport})));
    const [claimant, adjuster, garage, reviewer] = await Promise.all(contexts.map(context => context.newPage()));
    const errors = [];
    for (const page of [claimant, adjuster, garage]) page.on('pageerror', error => errors.push(error.message));
    const unique = Date.now() + '-' + viewport.width;
    const email = 'browser-' + unique + '@example.ug';
    const password = 'Browser-Pass123!';
    const today = new Date().toISOString().slice(0, 10);
    const nextYear = new Date();
    nextYear.setUTCFullYear(nextYear.getUTCFullYear() + 1);
    try {
      await claimant.goto('/pages/auth/register.html');
      await claimant.locator('.choice[data-role="claimant"]').click();
      await claimant.locator('#full_name').fill('Browser Claimant ' + unique);
      await claimant.locator('#email').fill(email);
      await claimant.locator('#phone').fill('+256700123456');
      await claimant.locator('#password').fill(password);
      await claimant.locator('#password2').fill(password);
      await claimant.locator('#terms').check();
      await claimant.getByRole('button', {name: 'Create account'}).click();
      await expect(claimant.locator('#done')).toBeVisible();
      await login(claimant, email, password);

      await claimant.goto('/pages/claimant/claim-new.html');
      await claimant.getByRole('link', {name:'Add an existing policy',exact:true}).click();
      await claimant.locator('#insurer_name').fill('Class demonstration insurer');
      await claimant.locator('#policy_number').fill('EXISTING-' + unique);
      for (const [name,value] of Object.entries({vehicle_make:'Toyota',vehicle_model:'Corolla',vehicle_plate:'T'+unique,
        vehicle_year:'2020',coverage_limit:'20000000',start_date:today,end_date:nextYear.toISOString().slice(0,10)})) {
        await claimant.locator('#'+name).fill(value);
      }
      await claimant.getByRole('button',{name:'Submit for verification',exact:true}).click();
      await expect(claimant.locator('#policy-registrations')).toContainText('Awaiting verification');
      await claimant.screenshot({path:testInfo.outputPath('registered-policy.png'),fullPage:true});
      await login(adjuster, 'brian.okot@avic.ug');
      await adjuster.getByRole('link',{name:'Policies',exact:true}).click();
      const registration=adjuster.locator('#policy-registrations article').filter({hasText:'EXISTING-'+unique});
      await registration.locator('textarea').fill('Policy schedule and insurer reference checked for the classroom demonstration.');
      await registration.getByRole('button',{name:'Verify policy',exact:true}).click();
      await expect(registration).toContainText('Verified');
      await adjuster.screenshot({path:testInfo.outputPath('policies.png'),fullPage:true});

      await claimant.goto('/pages/claimant/claim-new.html');
      await expect(claimant.locator('#policy_id option')).toHaveCount(2);
      const policyId = await claimant.locator('#policy_id option').nth(1).getAttribute('value');
      await claimant.locator('#policy_id').selectOption(policyId);
      await claimant.getByRole('button', {name: 'Continue', exact: true}).click();
      await claimant.locator('[data-type="collision"]').click();
      await claimant.locator('#incident_date').fill(today);
      await claimant.locator('#incident_location').fill('Browser test road ' + unique);
      await claimant.locator('#incident_description').fill('Vehicle damaged while parked.');
      await claimant.getByRole('button', {name: 'Continue', exact: true}).click();
      await claimant.locator('#claim-file-input').setInputFiles({name: 'vehicle-photo.png', mimeType: 'image/png',
        buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64')});
      await expect(claimant.locator('#filelist')).toContainText('vehicle-photo.png');
      await claimant.getByRole('button', {name: 'Continue', exact: true}).click();
      await claimant.locator('#parts_cost').fill('500000');
      await claimant.locator('#labor_cost').fill('100000');
      await claimant.getByRole('button', {name: 'Continue', exact: true}).click();
      await claimant.locator('#confirm_true').check();
      await claimant.getByRole('button', {name: 'Submit claim', exact: true}).click();
      await confirm(claimant, 'Submit claim');
      await expect(claimant).toHaveURL(/claims\.html$/);
      await expect(claimant.locator('.shell')).toHaveClass(/is-ready/);
      const claim = await claimant.evaluate(() => AVIC.claims[0]);
      const claimId = claim.id;
      await claimant.getByRole('link', {name: claim.claim_number, exact: true}).click();
      await expect(claimant.locator('.shell')).toHaveClass(/is-ready/);
      const docUrl = await claimant.evaluate(id => AVIC.docUrl(AVIC.docsFor(id)[0]), claimId);
      expect((await claimant.request.get(docUrl)).status()).toBe(200);

      if (claim.adjuster_id !== 2) {
        if (!(await adjuster.getByRole('button', {name:'Sign out',exact:true}).isVisible())) await adjuster.getByRole('button',{name:'Menu',exact:true}).click();
        await adjuster.getByRole('button', {name: 'Sign out', exact: true}).click();
        await expect(adjuster).toHaveURL(/login\.html$/);
        await login(adjuster, 'grace.a@avic.ug');
      }
      await adjuster.goto('/pages/adjuster/review.html?id=' + claimId);
      await adjuster.getByRole('button', {name: 'Start review', exact: true}).click();
      await expect(adjuster.getByRole('dialog')).toHaveAccessibleName('Start review?');
      await expect(adjuster.locator('.modal [data-x]')).toBeFocused();
      await adjuster.keyboard.press('Shift+Tab');
      await expect(adjuster.locator('.modal [data-ok]')).toBeFocused();
      await confirm(adjuster, 'Start review');
      await expect(adjuster.locator('#c-badge')).toContainText('Under review');
      await expect(adjuster.locator('#next-action')).toContainText('Review preparation');
      await adjuster.locator('[data-decision="request_docs"]').click();
      await adjuster.locator('#document-type-1').selectOption('police_report');
      await adjuster.getByRole('button', {name:'Add another document',exact:true}).click();
      await adjuster.locator('#document-type-2').selectOption('other');
      await adjuster.locator('#document-name-2').fill('Driving licence');
      await adjuster.locator('#review_notes').fill('Please provide readable copies.');
      await adjuster.locator('#decision button[type=submit]').click();
      await confirm(adjuster, 'Record decision');
      await expect(adjuster).toHaveURL(/queue\.html$/);
      await claimant.goto('/pages/claimant/notifications.html');
      await claimant.getByRole('link', {name:/More documents needed/}).first().click();
      await expect(claimant).toHaveURL(/document-upload\.html/);
      await expect(claimant.locator('#requested-upload-list')).toContainText('Driving licence');
      await expect(claimant.locator('#policy_id')).toHaveCount(0);
      for (const name of ['police-report.png','driving-licence.png']) {
        const response = claimant.locator('[data-requested-document]').first();
        await response.locator('input[type=file]').setInputFiles({name,mimeType:'image/png',buffer:Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==','base64')});
        await response.getByRole('button',{name:'Upload document',exact:true}).click();
        await expect(claimant.locator('#requested-upload-list')).toContainText(name === 'police-report.png' ? 'awaiting the adjuster' : 'awaiting the adjuster');
        await expect(claimant.locator('[data-requested-document]')).toHaveCount(name === 'police-report.png' ? 1 : 0);
      }
      await expect(claimant.locator('#upload-result')).toContainText('All requested documents uploaded');
      await claimant.screenshot({path:testInfo.outputPath('document-response.png'),fullPage:true});
      await claimant.getByRole('link',{name:'Back to claim',exact:true}).click();
      await adjuster.goto('/pages/adjuster/queue.html');
      await adjuster.locator('tbody tr').filter({hasText:claim.claim_number}).getByRole('cell').nth(1).click();
      await expect(adjuster).toHaveURL(/review\.html\?id=\d+$/);

      await adjuster.locator('#assign-garage').selectOption('3');
      await adjuster.locator('#do-assign').click();
      await expect(adjuster.getByText('Work order sent to the garage.', {exact: true})).toBeVisible();

      await login(garage, 'desk@kigongomotors.ug');
      await garage.goto('/pages/garage/estimate-new.html?claim=' + claimId);
      await garage.locator('#parts_cost').fill('500000');
      await garage.locator('#labor_cost').fill('100000');
      await garage.locator('#repair_days').fill('3');
      await garage.getByRole('button', {name: 'Send quote to the adjuster'}).click();
      await confirm(garage, 'Send quote');
      await expect(garage).toHaveURL(/estimates\.html$/);
      expect((await garage.request.get('/config/api/claims-pdf.php?claim_id=' + claimId)).status()).toBe(403);

      await adjuster.goto('/pages/adjuster/estimates.html');
      const row = adjuster.locator('tr').filter({hasText: claim.claim_number});
      await row.getByRole('button', {name: 'Approve', exact: true}).click();
      await confirm(adjuster, 'Approve estimate');
      await expect(row).toContainText('Approved');
      await adjuster.goto('/pages/adjuster/review.html?id=' + claimId);
      await adjuster.locator('[data-decision="approve"]').click();
      await adjuster.locator('[name="recommended_amount"]').fill('600000');
      await adjuster.locator('#decision button[type="submit"]').click();
      await confirm(adjuster, 'Record decision');
      await expect(adjuster).toHaveURL(/queue\.html$/);
      await claimant.reload();
      await expect(claimant.locator('#settlement-form')).toBeVisible();
      await claimant.locator('#settlement-name').fill('Browser Claimant ' + unique);
      await claimant.locator('#settlement-account').fill('+256700123456');
      await claimant.locator('#settlement-confirmed').check();
      await claimant.getByRole('button', {name:'Accept settlement', exact:true}).click();
      await expect(claimant.locator('#c-payout')).toContainText('Settlement accepted');
      await adjuster.goto('/pages/adjuster/review.html?id=' + claimId);
      await adjuster.locator('#p-advance').click();
      await confirm(adjuster, 'Advance payout');
      await expect(adjuster.locator('#c-payout')).toContainText('Processing');
      await adjuster.locator('#p-advance').click();
      await adjuster.locator('#external-reference').fill('BROWSER-' + unique);
      await adjuster.locator('#payment-note').fill('Class demonstration receipt checked against the recorded payout.');
      await adjuster.locator('[name=payment_confirmed]').check();
      await confirm(adjuster, 'Advance payout');
      await expect(adjuster.locator('#c-badge')).toContainText('Paid');
      await adjuster.getByRole('button', {name: 'Close claim', exact: true}).click();
      await confirm(adjuster, 'Close claim');
      await expect(adjuster.locator('#c-badge')).toContainText('Closed');
      await expect(adjuster.locator('#c-sla')).toBeEmpty();
      await adjuster.screenshot({path: testInfo.outputPath('closed-claim.png'), fullPage: true});
      await claimant.reload();
      await expect(claimant.locator('#c-badge')).toContainText('Closed');
      await expect(claimant.locator('.timeline .is-current')).toContainText('Closed');
      expect((await claimant.request.get('/config/api/claims-pdf.php?claim_id=' + claimId)).status()).toBe(200);
      await claimant.goto('/pages/claimant/dashboard.html');
      await expect(claimant.locator('#k-paid')).toContainText('600,000');
      await claimant.goto('/pages/claimant/complaints.html?claim=' + claimId);
      await claimant.locator('#complaint-details').fill('Please clarify the final settlement calculation.');
      await claimant.getByRole('button', {name:'Submit complaint',exact:true}).click();
      await expect(claimant.locator('#complaint-list')).toContainText('Please clarify');
      await login(reviewer, claim.adjuster_id === 2 ? 'grace.a@avic.ug' : 'brian.okot@avic.ug');
      await reviewer.goto('/pages/adjuster/complaints.html');
      const complaint = reviewer.locator('article').filter({hasText:claim.claim_number});
      await complaint.getByRole('button', {name:'Start complaint review',exact:true}).click();
      await expect(complaint).toContainText('under review');
      await complaint.locator('textarea').fill('Reviewed the calculation and explained the approved repairs. Original claim records are preserved.');
      await complaint.getByRole('button', {name:'Resolve complaint',exact:true}).click();
      await expect(complaint).toContainText('resolved');
      await claimant.reload();
      await expect(claimant.locator('#complaint-list')).toContainText('Reviewed the calculation');
      await reviewer.screenshot({path:testInfo.outputPath('complaint-resolved.png'),fullPage:true});

      for (const page of [claimant, adjuster, garage]) {
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
      }
      expect(errors).toEqual([]);
    } finally {
      await Promise.all(contexts.map(context => context.close()));
    }
  });
}

test('password reset through a captured email and the browser', async ({page, baseURL}) => {
  const mailbox = process.env.AVIC_BROWSER_MAIL_FILE;
  test.skip(!mailbox, 'Set AVIC_BROWSER_MAIL_FILE to the local PHP mail capture file.');
  const email = 'reset-browser-' + Date.now() + '@example.test';
  const oldPassword = 'Before-Reset123!';
  const newPassword = 'After-Reset456!';
  const csrf = async () => (await (await page.request.get('/config/auth/session-token.php')).json()).csrf_token;
  const post = async (path, data) => page.request.post(path, {data, headers: {'X-CSRF-Token': await csrf()}});
  const registered = await post('/config/auth/register.php', {
    role: 'claimant', full_name: 'Reset Browser Test', email, phone: '+256700555666',
    password: oldPassword, password2: oldPassword, terms: true
  });
  expect(registered.ok()).toBe(true);
  const offset = fs.statSync(mailbox).size;
  await page.goto('/pages/auth/forgot-password.html');
  await page.locator('#email').fill(email);
  await page.getByRole('button', {name: 'Send the reset link'}).click();
  await expect(page.locator('#sent')).toBeVisible();
  await expect.poll(() => fs.readFileSync(mailbox, 'utf8').slice(offset)).toContain(email);
  const message = fs.readFileSync(mailbox, 'utf8').slice(offset);
  const link = message.match(/https?:\/\/[^\s]+\/pages\/auth\/reset-password\.html\?token=[a-f0-9]{64}/)?.[0];
  expect(link).toBeTruthy();
  expect(new URL(link).origin).toBe(new URL(baseURL).origin);
  await page.goto(link);
  await page.locator('#password').fill(newPassword);
  await page.locator('#password2').fill(newPassword);
  await page.getByRole('button', {name: 'Change password'}).click();
  await expect(page).toHaveURL(/login\.html$/);
  expect((await post('/config/auth/login.php', {email, password: oldPassword})).status()).toBe(401);
  await login(page, email, newPassword);
  const reused = await post('/config/auth/reset-password.php', {
    token: new URL(link).searchParams.get('token'), password: oldPassword, password2: oldPassword
  });
  expect(reused.status()).toBe(400);
});
