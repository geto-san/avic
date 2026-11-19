# AVIC motor claims portal

From this directory on Linux, run `bash bin/demo.sh` with PHP 8.1+, PDO MySQL, mbstring, fileinfo and MariaDB installed. Open **http://127.0.0.1:8080** in your browser after the launcher prints the address. Keep the terminal open. Opening index.html as a file bypasses the PHP server and breaks login. The launcher prints demo accounts and keeps the database between runs.

For staging, run `php bin/help.php` for dependencies, environment variables, migrations, web server setup and tests. Set an explicit database, private upload directory and mail transport. Use clean installations and rehearse upgrades on a disposable copy. Apply migrations with `php bin/migrate.php` and inspect readiness with `php bin/check.php`. The public routes are allowlisted in both .htaccess and bin/serve-router.php.

Claimants submit losses and respond to requests. Garages handle assigned work orders. Adjusters use **Review queue → Coverage verification** to record policy evidence. The task deadline uses the configured claim SLA at submission. Completing a check records evidence independently of a claim decision. A supervisor in this milestone is an active adjuster with the additional read-only permission `coverage.view_all`. Using the database environment for the intended installation, run `php bin/coverage-supervisor.php grant ADJUSTER_EMAIL` to grant access or replace `grant` with `revoke` to withdraw it. All grants start empty. Only the assigned adjuster can record the check.

See [the operating model](docs/pilot-operating-model.md) and [delivery roadmap](docs/industry-pilot-roadmap.md) for scope and outstanding insurer decisions.
