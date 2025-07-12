const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
puppeteer.use(StealthPlugin());

const GREEN = '\x1b[32m';
const RESET = '\x1b[0m';

(async () => {
  const browser = await puppeteer.launch({ headless: true });
  const page = await browser.newPage();

  // 1. Open the website
  await page.goto('https://www.salik.ae/en', { waitUntil: 'networkidle2', timeout: 60000 });

  // 2. Click the Sign in button
  let found = false;
  const allButtons = await page.$$('a,button,input');
  for (const btn of allButtons) {
    const text = await page.evaluate(el => el.innerText || el.value, btn);
    if (text && text.trim().toLowerCase().includes('sign in')) {
      await btn.click();
      found = true;
      break;
    }
  }

  if (found) {
    console.log(GREEN + 'Sign in button clicked successfully' + RESET);
  } else {
    console.log('Sign in button not found');
    await browser.close();
    return;
  }

  // 3. Wait for navigation
  await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 60000 });

  // 4. Get the login URL
  const loginUrl = page.url();

  // 5. Go to the login URL
  await page.goto(loginUrl, { waitUntil: 'networkidle2', timeout: 60000 });

  // 6. Fill in the login credentials
  // Find the Username field
  const usernameInput = await page.$('input[name="username"], input[id*="user"], input[placeholder*="User"], input[aria-label*="User"], input[type="text"]');
  if (usernameInput) {
    await usernameInput.type('51564893', { delay: 100 });
  } else {
    console.log('Username field not found');
    await browser.close();
    return;
  }

  // Find the Password field
  const passwordInput = await page.$('input[type="password"], input[name*="pass"], input[id*="pass"], input[placeholder*="Pass"], input[aria-label*="Pass"]');
  if (passwordInput) {
    await passwordInput.type('Emad1981', { delay: 100 });
  } else {
    console.log('Password field not found');
    await browser.close();
    return;
  }

  // Click the final Sign in button
  const signInBtn = await page.$('input[type="submit"][value="Sign in to your Salik account"], button, input[type="submit"]');
  let loginBtnFound = false;
  if (signInBtn) {
    const btnText = await page.evaluate(el => el.value || el.innerText, signInBtn);
    if (btnText && btnText.includes('Sign in to your Salik account')) {
      await signInBtn.click();
      loginBtnFound = true;
    }
  }
  if (!loginBtnFound) {
    // Try to find the button by text
    const allLoginBtns = await page.$$('button, input[type="submit"]');
    for (const btn of allLoginBtns) {
      const text = await page.evaluate(el => el.value || el.innerText, btn);
      if (text && text.includes('Sign in with Salik account')) {
        await btn.click();
        loginBtnFound = true;
        break;
      }
    }
  }
  if (loginBtnFound) {
    console.log(GREEN + 'Sign in to your Salik account button clicked' + RESET);
  } else {
    console.log('Sign in to your Salik account button not found');
    await browser.close();
    return;
  }

  // Wait for navigation after login
  await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 60000 });

  // 7. Go to the dashboard page
  const dashboardUrl = 'https://customers.salik.ae/en/account/dashboard';
  await page.goto(dashboardUrl, { waitUntil: 'networkidle2', timeout: 60000 });

  // 8. Print the first 5000 characters of the dashboard page
  const dashboardText = await page.evaluate(() => document.body.innerText.slice(0, 5000));
  console.log(dashboardText);

  // 9. Extract and print the balance value
  const balance = await page.evaluate(() => {
    const el = document.querySelector('.top-widget-balance-heading .blanceResult');
    return el ? el.textContent.trim() : null;
  });
  const fs = require('fs');
  if (balance !== null) {
    fs.writeFileSync('scripts/salik_balance.txt', balance);
    console.log(GREEN + 'Balance: ' + balance + ' AED' + RESET);
  } else {
    fs.writeFileSync('scripts/salik_balance.txt', '');
    console.log('Balance not found');
  }

  // 10. انتقل مباشرة إلى صفحة الرحلات Trips
  const tripsUrl = 'https://customers.salik.ae/en/account/trips';
  await page.goto(tripsUrl, { waitUntil: 'networkidle2', timeout: 60000 });
  console.log(GREEN + 'Navigated directly to Trips page' + RESET);

  // اضغط على زر Load more حتى يختفي
  let loadMoreVisible = true;
  while (loadMoreVisible) {
    loadMoreVisible = await page.evaluate(() => {
      const btn = document.querySelector('#loadMore');
      if (btn && btn.offsetParent !== null) { // الزر ظاهر
        btn.click();
        return true;
      }
      return false;
    });
    if (loadMoreVisible) {
      await new Promise(resolve => setTimeout(resolve, 2000));
    }
  }
  console.log(GREEN + 'All trips loaded' + RESET);

  const tripsContent = await page.evaluate(() => document.body.innerText.slice(0, 5000));
  console.log(tripsContent);
  fs.writeFileSync('scripts/salik_trips.txt', tripsContent);

  // استخراج بيانات الصفوف فقط من جدول الرحلات (تجاهل صفوف التفاصيل)
  const tripsRows = await page.evaluate(() => {
    const rows = Array.from(document.querySelectorAll('table tbody tr.open'));
    return rows.map(row => {
      const th = row.querySelector('th span');
      const tds = row.querySelectorAll('td');
      return {
        trip_date: th ? th.textContent.trim() : '',
        trip_time: tds[0] ? tds[0].textContent.trim() : '',
        plate: tds[1] ? tds[1].textContent.trim() : '',
        toll_gate: tds[2] ? tds[2].textContent.trim() : '',
        direction: tds[3] ? tds[3].textContent.trim() : '',
        amount: tds[4] ? tds[4].textContent.trim() : '',
      };
    });
  });

  fs.writeFileSync('scripts/debug_trips_rows.json', JSON.stringify(tripsRows, null, 2));
  fs.writeFileSync('scripts/salik_trips.json', JSON.stringify(tripsRows, null, 2));
  console.log('Trips data saved to scripts/salik_trips.json');

  await browser.close();
})();
