# FlashCards

Flashcards web app for learning foreign words. Requirements: [SPECIFICATION.md](SPECIFICATION.md).

Stack: Laravel 12 (PHP 8.2) as a JSON API, MySQL/MariaDB, and a React app in TypeScript
(Vite, React Router, plain CSS). Laravel renders only the landing page and the page that
loads the React app.

| Path | What |
|---|---|
| `/` | Landing page (`resources/views/landing.blade.php`) |
| `/app/…` | React app (`resources/js`, loaded by `resources/views/app.blade.php`) |
| `/api/…` | JSON API (`routes/api.php`) |

The app works at the root of a domain or in a subfolder, e.g. `http://localhost/flashcards/`,
without rebuilding.

## Local setup (XAMPP on Windows)

XAMPP's `php.ini` must have `extension=intl` and `extension=zip` enabled.
Composer is installed as `composer.bat`. Node.js 22.12 or newer is needed for the build.

1. Start MySQL in the XAMPP Control Panel.
2. Create the databases once:
   ```
   mysql.exe -u root -e "CREATE DATABASE flashcards CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE flashcards_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   ```
3. Install dependencies and prepare the app:
   ```
   composer.bat install
   npm install
   copy .env.example .env
   php.exe artisan key:generate
   php.exe artisan migrate --seed
   ```
4. Build the interface: `npm run build`. While changing it, `npm run dev` rebuilds on every change.
5. Run it: `php.exe artisan serve`, then open http://localhost:8000.
   Or serve it from a subfolder of XAMPP's Apache: a junction `htdocs\flashcards` → `public\`,
   then open http://localhost/flashcards/.

The seeder creates languages and, in the local environment only, two accounts:
`admin` / `password` and `learner` / `password`.

New accounts must be activated by email. Locally, emails go to `storage/logs/laravel.log`
(`MAIL_MAILER=log`); open the activation link from there.

## Deployment (shared hosting, see SPECIFICATION.md §10.5)

1. Locally: `composer.bat install --no-dev` and `npm ci`, then `npm run build`.
2. Upload the project including `vendor/` and `public/build/`, without `node_modules/`.
3. Make the site or subfolder serve `public/`. If the host cannot, upload the whole project
   into the folder: its `.htaccess` sends all requests into `public/`.
4. Fill in `.env`: `APP_URL` (including the subfolder), the database, `FLASHCARDS_*` and
   the `MAIL_*` SMTP settings. Then open `/app/install?token=…` once to create the first admin.

## Tests

```
php.exe artisan test
npm run typecheck
```

PHP tests run against the `flashcards_test` MariaDB database (see `phpunit.xml`), not SQLite,
because duplicate checks depend on MariaDB collations. MySQL must be running.
