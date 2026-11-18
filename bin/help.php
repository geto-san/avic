<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

echo <<<'HELP'
AVIC commands (run from the project root)

Local class demonstration (Linux with PHP and MariaDB installed):
  bash bin/demo.sh
  Starts a private demo database and http://127.0.0.1:8080. Captures reset emails
  locally and prints their file path. Ctrl+C stops both processes. Data stays
  under ~/.local/share/avic-class-demo (or AVIC_DEMO_DIR). Fresh demos contain
  sign-in accounts only; policies and claims start empty. No external mail.
  Set AVIC_DEMO_PORT=8081 if port 8080 is occupied.

Requirements: PHP 8.1+ with PDO MySQL, mbstring, fileinfo; MySQL/MariaDB.
Tests also use Composer, curl, DOM/XML and Node.js for Playwright.

1. Create a database and a user with privileges on that database.
2. Export AVIC_DB_HOST, AVIC_DB_PORT, AVIC_DB_NAME, AVIC_DB_USER, AVIC_DB_PASS.
   .env.example lists options; PHP does not load .env automatically.
   On MySQL/older MariaDB, set AVIC_DB_COLLATION=utf8mb4_unicode_ci.
3. php bin/migrate.php                 Apply pending schema migrations
   php bin/migrate.php --status        Show migration state
4. php config/db/seed-demo.php         Optional demo data (development only)
5. php -S 127.0.0.1:8080 bin/serve-router.php
   Open http://127.0.0.1:8080. Use separate browser profiles for separate roles.

Demo password: Demo2026! (unless AVIC_DEMO_SECRET was set while seeding)
  Claimant: doreen@example.ug
  Adjusters: brian.okot@avic.ug, grace.a@avic.ug
  Garage: desk@kigongomotors.ug
Claimants add existing insurance under Policies; adjusters verify registrations
before those policies can be used for claims. Adjusters can also issue cover.
Workflow: submit -> review / request documents -> approve -> claimant accepts
settlement and confirms payee details -> processing -> verified payment -> close.
Rejection and document requests require a reason. Payment completion requires an
external transaction reference and a verification note (no money is sent by AVIC).
Complaints go to another active adjuster under Complaints. Open complaints block
payment and closure. A complaint response preserves the original claim decision.
Existing unpaid claims require claimant acceptance after the workflow migration.
Code map: claim-state.php defines claim transitions; workflow-rules.php contains
shared checks; settlements.php records claimant consent; payouts-update.php
records verified payment; complaints.php manages independent complaint review.
These files are under config/api/. The corresponding browser rendering is in
assets/js/pages-common.js, pages-adjuster.js and pages-complaints.js.
Document requests use named items: claims-decision.php creates them, and
document-responses.php accepts one file per item without editing claim details.
The claimant uses pages/claimant/document-upload.html, reached from the notification.
Create adjuster: php config/auth/seed-adjuster.php PASSWORD NAME EMAIL PHONE

Checks:
  php bin/check.php                    Check database, migrations, uploads and mail
  composer install                     Install pinned PHPUnit dependencies
  composer lint                        Check PHP syntax
  composer test:unit                    Unit tests without MySQL
  composer test:feature                 HTTP tests against a separate *_test database
  npm ci                               Install pinned browser-test dependencies
  npx playwright install chromium      Install the test browser
  npm run test:browser                  Exercise the running demo at localhost:8080

Feature tests RESET their database. Configure AVIC_TEST_DB_HOST, AVIC_TEST_DB_PORT,
AVIC_TEST_DB_NAME (must end in _test), AVIC_TEST_DB_USER and AVIC_TEST_DB_PASS.
Never use the demonstration or production database for these tests.
Browser tests create accounts and claims; use a separate seeded demo database.
Override its URL with AVIC_BROWSER_URL. CI runs both test layers.
Set AVIC_BROWSER_MAIL_FILE to the runner's mail.eml path to also test password
reset delivery into the local capture file and sign-in with the changed password.

Deployment:
  Set AVIC_ENV=production, secure AVIC_DB_* values, and AVIC_UPLOAD_DIR to a writable
  absolute directory OUTSIDE the web root. Serve through HTTPS. Apache needs
  mod_rewrite and AllowOverride for .htaccess. The local router restricts the same
  public paths. PHP's development server is not a production server.
  Set AVIC_PUBLIC_URL to your HTTPS URL and AVIC_MAIL_FROM to your sender address.
  Configure PHP sendmail_path / the server MTA with your SMTP service credentials.
  Reset tests capture mail locally; live delivery requires that deployment setup.
  GET config/api/health.php reports database/schema readiness without private data.

HELP;
