# FlashCards — Specification v0.3

Status: agreed for v1 · 2026-10-31 · v0.3 (React interface, email activation, subfolder hosting) 2026-11-10

## 1. Purpose and scope

A web application for learning foreign words with flashcards. An admin maintains one shared vocabulary of two-language cards. Learners study it by typing the translation of the word shown. The app tracks each learner's progress per card and uses it to decide which cards appear in each session.

Version 1 is web-only. It must run on PHP shared hosting and locally under XAMPP, at the root of a domain or in a subfolder of an existing website (§10.2).

## 2. Roles

| Role | Can | Cannot |
|---|---|---|
| Admin | Manage languages and cards, import and export, and everything a learner can | — |
| Learner | Learn, change own learning settings and password | Edit the vocabulary, see other learners' data |

- There is one vocabulary. Learners see it only through learn mode.
- Learners are isolated: each has their own progress, sessions and settings. An admin who learns is treated as one more learner.
- Admins learn so they can fix translations they find wrong while studying (§6.3). Letting other users edit cards comes later (§11).
- Roles are checked on the server for every request, not only hidden in the UI.

## 3. Terms

| Term | Meaning |
|---|---|
| Card | A word in language 1 and its translation in language 2, with optional notes and examples for each side |
| Direction | The "from" and "to" languages a learner picks, e.g. EN → DE. A card with EN and DE can be studied in both directions |
| Session | One study run in one direction: a fixed set of cards, each asked until it has been answered correctly once |
| Queue | The order in which the session's remaining cards are asked |
| Retry counter | Kept per learner, card and direction. While it is above 0, the card is included in every new session |
| New / active / learned | The card has no progress record yet / retry > 0 / retry = 0 |
| Postponement (P) | Number of sessions a learned card is skipped. After that it is released back to the pool |
| Pool | The cards a new session can draw from: new cards and released learned cards |

## 4. Vocabulary

### 4.1 Card fields

| Field | Required | Rules |
|---|---|---|
| Language 1 | yes | From the language list |
| Word 1 | yes | Max 255 characters. Exactly the expected answer: no brackets or optional parts (put those in Notes) |
| Notes 1 | no | Max 255 characters, e.g. "female, plural" |
| Examples 1 | no | Free text memo, multi-line, max 2000 characters |
| Language 2 | yes | Must differ from Language 1 |
| Word 2, Notes 2, Examples 2 | as above | Same rules as side 1 |
| Complexity | yes | Integer 1–10, default 5. Lower values are learned first |

Notes and examples are shown as plain text with line breaks kept. No HTML or Markdown.

### 4.2 Languages

- The admin manages a list of languages: a unique code (e.g. `de`) and a display name (e.g. "German").
- A language can be deleted only when no card uses it.
- The language page shows how many cards each language has per complexity, plus a row for all cards. A card counts for both of its languages. Each count in the all-cards row links to the card list filtered by that complexity.

### 4.3 Vocabulary rules

1. **Duplicates.** Two cards are duplicates when they have the same two languages, in either order, and both words are equal after normalization (§7.1). A duplicate is rejected when saved and skipped on import, unless the admin chooses to update existing cards from the file (§5.4).
2. **Same word, different translation.** Two cards with the same word on one side but different translations are allowed, with a warning. The learner would see the same prompt with two different expected answers, so the notes or examples should tell them apart. Proper support comes with synonyms (§11).
3. Editing words, notes, examples or complexity keeps learners' progress.
4. Changing a card's languages resets all learners' progress for that card and removes it from open sessions. Swapping the two sides (same two languages) keeps progress.
5. Deleting a card is permanent. It also removes the card from all learners' progress and open sessions. If an open session has no cards left, it is marked finished.

## 5. Admin functions

### 5.1 Card list

- Filter by language pair and complexity. Search in both words, and optionally (a checkbox) in both notes, which often hold alternative translations. Sort by any column. 50 cards per page.
- Columns: language 1, word 1, language 2, word 2, complexity, edit, delete.
- Select several rows and delete them, after a confirmation.
- Select several rows, pick a complexity and set it for all of them. Learners keep their progress.

### 5.2 Add and edit a card

- A form with the fields from §4.1. Validation errors appear next to the field.
- "Save and add another" keeps both languages and the complexity selected, for fast entry.

### 5.3 Delete a card

The confirmation dialog shows both words. There is no undo.

### 5.4 CSV import and export

**Format**
- UTF-8, with or without a BOM. The delimiter (comma, semicolon or tab) is detected automatically, because Excel in many locales saves with semicolons.
- Fields are quoted per RFC 4180. Quoted fields may span several lines.
- A header row is required. Columns can be in any order, and unknown columns are ignored:

```
lang1,word1,notes1,examples1,lang2,word2,notes2,examples2,complexity
```

- `lang1` and `lang2` are language codes that must already exist. An empty `complexity` means 5.

**Import**
1. The admin uploads a file. The app checks every row without saving anything.
2. A preview shows how many rows will be imported, the duplicates that will be skipped, and the errors with their row numbers.
3. Rows that match an existing card (§4.3) but give it a different complexity, notes or examples are listed separately, with each change. Only columns present in the file count, and an empty `complexity` leaves the existing value unchanged. Words are never changed: they identify the card.
4. For a clean re-import, the admin can tick language pairs of the file that already have cards. The preview is checked again as if those pairs were empty, and warns how many cards will be deleted. On import, all cards of the ticked pairs (in either language order) are deleted first, together with learners' progress for them.
5. The admin confirms either "import" (new cards only; changed existing cards are skipped) or "import and update" (new cards plus the listed changes). Everything, including the deletion, is saved in one transaction.

The maximum file size is the host's PHP `upload_max_filesize`. Target: 10,000 rows.

**Export**
- All cards or one language pair, in the format above.
- UTF-8 with a BOM and comma delimiter, so Excel shows non-Latin text correctly.
- Importing an export into an empty vocabulary must reproduce the same cards.

### 5.5 Anki import and export

**Import** reads Anki's "Notes in Plain Text (.txt)" export:
- The admin picks language 1, language 2 and one complexity for all rows.
- The admin maps each column to word 1, word 2, notes 1, notes 2, examples 1, examples 2 or "ignore", looking at a preview of the first 5 rows.
- Lines starting with `#` are Anki headers. A `#separator:` header is honoured; without one, the separator is a tab.
- HTML is stripped: `<br>` becomes a line break, tags are removed and entities are decoded.
- The preview, confirmation and duplicate rules from §5.4 apply.

**Export** writes a tab-separated `.txt` that Anki can import:

```
#separator:Tab
#html:true
#columns:Word 1	Word 2	Notes 1	Notes 2	Examples 1	Examples 2	Tags
#tags column:7
```

- Text is HTML-escaped and line breaks become `<br>`.
- Complexity is written as a tag, e.g. `complexity_5`.

Anki package files (`.apkg`) are out of scope (§11).

## 6. Learn mode

### 6.1 Start page

- Lists every direction that has cards. Each language pair that has cards is offered in both directions.
- Each direction shows its counts of new, active and learned cards, and a **Resume** button if a session is open.
- Choosing a direction resumes its open session, or builds a new one (§8.2).
- A learner can have at most one open session per direction.

### 6.2 Question screen

For a card studied from language A to language B:
- the word in language A, in large text
- the notes in language A
- the examples in language A, below the word
- an answer field, focused automatically
- **Submit** (Enter) and **I don't know**
- the number of cards left in the session

The language B side is not sent to the browser before the answer is submitted.

### 6.3 Result screen

- A correct or incorrect mark.
- On a wrong answer or "I don't know": the learner's answer and the correct answer.
- On a correct answer: the stored word as well, so the learner sees its exact spelling.
- The notes and examples in language B.
- **Next** (Enter).
- For an admin: **Edit card**. It opens the card form (§5.2) over this screen. Saving shows the updated card here; cancelling closes the form.

### 6.4 Session end

- A summary: the number of cards, how many were correct on the first try, and the cards with mistakes (word, correct answer, number of wrong answers).
- Buttons: **Start next session** and **Back to start page**.

### 6.5 Leaving a session

The learner can leave at any time. Progress is saved after every answer, and the session resumes at the same point next time.

### 6.6 Repeated submissions

Each answer request carries the card ID. The server accepts it only when that card is first in the queue. A repeated request is ignored and the learner sees the current question, so the retry counter is never changed twice for one answer. The Submit button is disabled while an answer is being sent.

## 7. Answer checking

### 7.1 Normalization

Both the typed answer and the stored word go through these steps before comparison:

1. Unicode NFC, so "é" typed as one character equals "e" plus a combining accent.
2. Lower case, Unicode-aware (`Ä` → `ä`, `ẞ` → `ß`).
3. Remove dots (`.`) and commas (`,`).
4. Trim, and collapse any run of whitespace (including non-breaking spaces) into one space.

The answer is correct when the two results are equal. Everything else must match the stored word exactly: accents, articles, the English "to", spelling variants, other punctuation (`'` `-` `!` `?` and so on) and typos.

### 7.2 Examples (also used as unit tests)

| Stored word | Typed | Result | Reason |
|---|---|---|---|
| der Hund | `  Der   Hund ` | correct | Case and extra spaces ignored |
| der Hund | `der Hund.` | correct | Dot dropped |
| der Hund, die Hunde | `der Hund die Hunde` | correct | Comma dropped |
| der Hund | `der Hund!` | wrong | Only dots and commas are dropped |
| der Hund | `derHund` | wrong | The space between words must be there |
| der Hund | `Hund` | wrong | Article missing |
| café | `cafe` | wrong | Accent missing |
| Straße | `strasse` | wrong | ß is not ss |
| to go | `go` | wrong | "to" missing |
| colour | `color` | wrong | Spelling variant |
| l'eau | `leau` | wrong | Apostrophe must match |
| l’eau | `l'eau` | wrong | Typographic and straight apostrophes are different characters (§12) |
| e-mail | `email` | wrong | Hyphen must match |

### 7.3 "I don't know"

Counts as a wrong answer.

## 8. Sessions and progress

### 8.1 Progress record

Each learner has a progress record for every card and direction they have studied:
- `retry`: an integer ≥ 0, created as 1 when the card first enters a session in that direction.
- `learned_session`: the number of the session in which `retry` last reached 0. Empty while the card is active.

Sessions are numbered separately for each learner and direction: 1, 2, 3, and so on.

A learned card is **released** back to the pool after P sessions: when the new session's number is greater than `learned_session + P`.

### 8.2 Building a new session

Inputs: the learner's session size S and postponement P (§9.2), and the new session's number.

1. Take **all active cards** (retry > 0). They are always included, even if there are more than S. That can only happen after the learner lowers S.
2. Fill up to S cards from the **pool**: new cards and released cards together, lowest complexity first, in random order within one complexity. When a complexity level runs out, continue with the next one.
   - A new card gets a progress record with retry = 1.
   - A released card gets retry = 1 and an empty `learned_session`.
3. If the pool runs out, the session has fewer than S cards.
   - If there would be no cards at all, but some learned cards are still postponed, take up to S of them, those closest to release first, and set their retry to 1. Without this, no session could be built, the session number would never advance, and postponed cards would never be released.
   - If there are no cards at all, show "There are no cards to learn in this direction right now."
4. Shuffle the cards to form the queue.

### 8.3 Effect of an answer

| Answer | Retry | Queue |
|---|---|---|
| Correct | Decrease by 1, not below 0. When it reaches 0, set `learned_session` to this session's number | The card is done for this session |
| Wrong, or "I don't know" | Increase by 1 | The card moves to the end of the queue |

The session finishes when every card has been answered correctly once.

Results of these rules:
- A card is learned only in a session where it is answered correctly on the first try.
- After a session with X wrong answers, a card starting at retry 1 is shown in the next X sessions, if each of them is answered correctly first time.

### 8.4 Worked example

With P = 2 (the default is 10):

| Session | Shown | Answers | Retry |
|---|---|---|---|
| 1 | yes, new | wrong, wrong, correct | 1 → 2 → 3 → 2 |
| 2 | yes, active | correct | 2 → 1 |
| 3 | yes, active | wrong, correct | 1 → 2 → 1 |
| 4 | yes, active | correct | 1 → 0, learned, `learned_session` = 4 |
| 5, 6 | no, postponed | — | 0 |
| 7 | released (7 > 4 + 2), shown if picked from the pool | correct | 1 → 0, `learned_session` = 7 |

## 9. Accounts and settings

### 9.1 Accounts

- **Registration**: anyone can create a learner account with a username (3–32 characters, unique, case-insensitive), an email address (unique, case-insensitive) and a password (at least 8 characters, typed twice). Registration can be turned off in configuration.
- **Activation**: a new account cannot be used until it is activated.
  1. After registering, the learner is not logged in. The app says it sent an activation link to the given address.
  2. Opening the link activates the account and shows the login page with a confirmation. The link works for 24 hours.
  3. Logging in to an account that is not activated fails with a message and a **Send the link again** button. The button sends the username and password just typed, which proves ownership without telling strangers which usernames exist. It is limited to 6 requests per minute.
  4. An expired or broken link shows the login page with a message. Logging in then offers a new link.
  5. Emails are sent while the request runs, because shared hosts have no queue worker. Locally they are written to the log file.
- **Login and logout.** Login is by username; the email address is only used for activation in v1. Passwords are stored as hashes.
- **Password change**: any logged-in user can change their password on the Settings page. They type the current password once and the new one twice; the new one follows the registration rule.
- **First admin**: created on a one-time install page (§10.5) with a username, email and password. The install token proves ownership, so this account is activated at once. Further admins are added directly in the database in v1.
- Accounts created before activation was introduced count as activated and may have no email address.
- There is no password reset, email change or user management in v1.

### 9.2 Learner settings

Each learner sets these on a Settings page:

| Setting | Range | Default |
|---|---|---|
| Words per session (S) | 5–100 | 20 |
| Learned words postponed for P sessions | 1–100 | 10 |

- Defaults come from the configuration file (`.env`).
- Changes apply from the next new session. An open session keeps its cards.
- A changed P also applies to cards already learned, because release is checked each time a session is built.

### 9.3 Header

Every screen of a logged-in user has a header with:
- the app name, linking to the user's start page (admin: card list; learner: learn start page)
- the main links: **Cards**, **Languages** and **Import / export** for admins, and **Learn** for everyone
- on the right, the username. Clicking it opens a menu with **Settings** and **Log out**. The menu closes on a click outside it, on Escape, and after an item is chosen.

## 10. Technical design

### 10.1 Stack

- **PHP + Laravel.** Use the newest Laravel version whose PHP requirement is met by both XAMPP and the host. Laravel 12 needs PHP 8.2+. Check XAMPP's PHP version first. Laravel serves only the JSON API (§10.3), the landing page, and the one page that loads the React app.
- **MySQL or MariaDB.** XAMPP includes MariaDB. Use `utf8mb4` everywhere.
- **User interface**: a React single-page app in TypeScript, built with Vite and routed with React Router. It keeps the plain CSS; there is no CSS framework.
- **The build runs on the developer's machine** (`npm run build`). The server needs no Node.js, so deployment is still a file upload.
- **PHP extensions**: `intl` (for `Normalizer`), `mbstring`, `pdo_mysql`. In XAMPP, `extension=intl` must be enabled in `php.ini`.
- **Business logic** lives in plain classes that controllers call: `AnswerChecker`, `SessionBuilder`, `ProgressService`, `Vocabulary`, `CardImporter`, `CsvReader`, `AnkiReader`.
- **Tests**: unit tests (PHPUnit) cover at least the table in §7.2 and the walk-through in §8.4. Feature tests cover every API endpoint.

### 10.2 Pages and URLs

| Path | What |
|---|---|
| `/` | Landing page, rendered by Laravel: what the app does, and links to log in and register (or to open the app when logged in) |
| `/app/…` | The React app: every screen, including login, registration and the install page |
| `/api/…` | The JSON API used by the React app |
| `/api/email/verify/…` | The activation link from the email. It redirects to the app's login page |

- **Subfolders.** The app works at the root of a domain or in any subfolder, such as `https://example.com/flashcards/`, without rebuilding.
  - No server or browser code assumes the root. The server builds every URL from the request.
  - The page that loads the React app tells it the folder it runs in.
  - Login cookies are limited to the app's folder, so they do not clash with the website around it.
  - A test runs the app in a subfolder.
- **Every screen has its own URL**, so reloading, bookmarks and the Back button work. The card list keeps its filters, sorting and page in the URL.

### 10.3 API

- JSON in and out. File uploads are `multipart/form-data`; exports are file downloads.
- **Login sessions use Laravel's session cookie.** The app and the API share one origin, so no tokens are kept in the browser. Requests that change data send the `X-XSRF-TOKEN` header, copied from Laravel's `XSRF-TOKEN` cookie.
- **Status codes:**

  | Code | Meaning |
  |---|---|
  | 401 | Not logged in |
  | 403 | Wrong role, or the account is not activated |
  | 404 | Not found, including other learners' sessions |
  | 409 | A repeated answer (§6.6) |
  | 422 | Validation errors per field, shown next to the field |
  | 429 | Too many requests |

- **The rule from §6.2 holds at the API.** The question response contains only the "from" side; the "to" side comes in the answer response.

### 10.4 Data model

```
users                id, username (unique), email (unique, NULL for older accounts),
                     email_verified_at NULL, password, role ENUM(admin, learner),
                     session_size NULL, postpone_sessions NULL, timestamps

languages            id, code (unique), name

cards                id, complexity TINYINT(1–10),
                     lang1_id → languages, word1, word1_norm, notes1, examples1,
                     lang2_id → languages, word2, word2_norm, notes2, examples2,
                     timestamps
                     index (lang1_id, lang2_id, complexity)

learner_cards        id, user_id → users (cascade), card_id → cards (cascade),
                     from_lang_id → languages, retry SMALLINT UNSIGNED DEFAULT 1,
                     learned_session INT NULL, timestamps
                     unique (user_id, card_id, from_lang_id)

study_sessions       id, user_id → users (cascade), from_lang_id, to_lang_id,
                     seq INT, status ENUM(open, finished), created_at, finished_at
                     unique (user_id, from_lang_id, to_lang_id, seq)

study_session_cards  id, study_session_id → study_sessions (cascade),
                     card_id → cards (cascade), position INT,
                     wrong_count SMALLINT DEFAULT 0, done BOOL DEFAULT 0
                     unique (study_session_id, card_id)
```

Notes:
- `*_norm` columns hold the normalized word (§7.1) and use the `utf8mb4_bin` collation. The usual `utf8mb4_unicode_ci` treats é and e as equal, which would break the duplicate check.
- The duplicate check runs in code, because it has to match cards stored in either language order.
- Session tables are named `study_sessions` and `study_session_cards`, because Laravel already uses a `sessions` table for logins.
- The queue: the next card is the one with the lowest `position` among cards not done. A wrong answer sets the card's `position` to the current maximum + 1.

### 10.5 Deployment

- **Local**: XAMPP plus Composer and Node.js, installed separately. The site is served from `public/`. During development, `npm run dev` rebuilds the interface on every change.
- **Shared hosting**:
  1. Locally, run `composer install --no-dev` and `npm ci && npm run build`. Upload the project including `vendor/` and `public/build/`. `node_modules/` is not needed.
  2. Make the site, or the subfolder, serve `public/`. This works in either of two ways:
     - Point the document root, or the subfolder (by an alias or a symbolic link), at `public/`.
     - If the host allows neither, upload the whole project into the folder. Its `.htaccess` sends every request into `public/`, so the rest of the project cannot be downloaded.
  3. Make `storage/` and `bootstrap/cache/` writable.
  4. Fill in `.env`:
     - `APP_URL`, the full address including any subfolder. It is used for URLs built outside a request.
     - the database settings
     - `FLASHCARDS_INSTALL_TOKEN`, `FLASHCARDS_REGISTRATION_OPEN` and the setting defaults
     - the `MAIL_*` settings of the host's SMTP server, and a real `MAIL_FROM_ADDRESS`
- **Install page** `/app/install?token=…`: many shared hosts have no SSH, so this page runs the database migrations and creates the first admin. It answers "not found" once an admin exists, or when `FLASHCARDS_INSTALL_TOKEN` is empty.
- **Login sessions and cache are stored in files** (`SESSION_DRIVER=file`, `CACHE_STORE=file`), so the install page works before any database tables exist.

### 10.6 Security baseline

Laravel's defaults cover most of this:
- hashed passwords
- CSRF protection on every request that changes data (the `XSRF-TOKEN` cookie and header, §10.3)
- escaped output: React escapes text, and notes and examples are never rendered as HTML
- parameterized queries
- role checks on every API route
- signed, expiring activation links

## 11. Out of scope for v1

- Synonyms and multiple accepted answers
- Typo tolerance and an "I was right" override
- On-screen keyboard for special characters
- Password reset, changing the email address, user management screens, account deletion
- Edit rights for chosen learners (a per-user permission on top of the learner role), so others can fix translations
- Learners reporting a wrong card, with a comment, for an admin to review
- Registration limits and bot protection (decide before public release)
- Statistics, layout optimized for mobile, text-to-speech
- Offline use, local desktop build, mobile app
- Time-based spaced repetition (SM-2, FSRS)
- Anki `.apkg` packages
- Decks or topics inside a language pair

## 12. Known gaps

These are accepted for v1 on purpose. They will be revisited once the app has been used for a while.

1. **Repeats follow the retry counter.** X wrong answers mean X more sessions. The original RoundUp(X/2) rule is dropped for now, and the counter has no ceiling.
2. **Postponement counts sessions, not time.** Several sessions in one evening release learned cards quickly. A long break releases nothing.
3. **Little early release.** When the pool runs out, sessions get shorter. Postponed cards come back early only when a session would otherwise be empty (§8.2 step 3), not to fill a short session (the original "or until all words are used" rule).
4. **Released cards compete with new cards by complexity.** A released card with high complexity waits until all lower-complexity cards are used.
5. **Look-alike characters must match exactly.** A typographic apostrophe `’` is not the same as `'`. The same goes for the different dashes and quote marks. Text pasted from Word or exported from Anki often contains them.
6. **Same prompt on two cards** (§4.3 rule 2) until synonyms are supported.
7. **Default complexity is 5** when none is given in the form or on import. Not yet confirmed.
8. **A password change does not log out other devices.** Login sessions opened elsewhere stay valid until they expire or log out.
9. **Accounts that are never activated stay forever.** They keep their username and email address, so someone could register with another person's address and block it.
10. **Older accounts have no email address**, and nobody can add one in v1.
11. **Reloading the result screen skips to the next question.** The result is kept in the browser only; the answer itself is saved.
12. **The email links back to the address the request came from.** Behind a proxy that changes the host or scheme, `APP_URL` and Laravel's trusted proxies must be configured.
