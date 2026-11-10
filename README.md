# FlashCards

Flashcards web app for learning foreign words. Requirements: [SPECIFICATION.md](SPECIFICATION.md).

Stack: Laravel 12, PHP 8.2, MySQL/MariaDB, server-rendered Blade pages with plain CSS. No Node build step.

## Local setup (XAMPP on Windows)

XAMPP's `php.ini` must have `extension=intl` and `extension=zip` enabled.
Composer is installed as `composer.bat`.

1. Start MySQL in the XAMPP Control Panel.
2. Create the databases once:
   ```
   mysql.exe -u root -e "CREATE DATABASE flashcards CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE flashcards_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   ```
3. Install dependencies and prepare the app:
   ```
   composer.bat install
   copy .env.example .env
   php.exe artisan key:generate
   php.exe artisan migrate --seed
   ```
4. Run it: `php.exe artisan serve`, then open http://localhost:8000.

The seeder creates five languages and, in the local environment only, two accounts:
`admin` / `password` and `learner` / `password`.

## Tests

```
php.exe artisan test
```

Tests run against the `flashcards_test` MariaDB database (see `phpunit.xml`), not SQLite,
because duplicate checks depend on MariaDB collations. MySQL must be running.
