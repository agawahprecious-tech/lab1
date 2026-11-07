# Lab 01 - Student Registration

PHP 8 + MySQL/MariaDB form with server-side validation and secure insertion (PDO prepared statements).

## Files
| File | Purpose |
|---|---|
| `index.php` | Form, validation, database insert, extension feature |
| `style.css` | Layout and styling |
| `database.sql` | Creates database `lab01_student_registration` and its table(s) |
| `README.md` | This document |

## Setup
1. Install XAMPP, WAMP, MAMP or any PHP 8.0+ and MySQL 5.7+/MariaDB 10.2+ stack.
2. Import the schema: `mysql -u root -p < database.sql` (or paste it into phpMyAdmin > SQL).
3. Copy this folder into the web root (e.g. `htdocs/`). If your MySQL user or password differs from `root` / empty, edit the `DB_USER` and `DB_PASS` constants at the top of `index.php`.
4. Open `http://localhost/lab01_student_registration/index.php`.

## Database design
`students` (PK `student_id`, UNIQUE `email`, `created_at` default CURRENT_TIMESTAMP). Full definition in `database.sql`.

## Validation rules
- Full name: required, 3-80 characters, letters plus space . ' - only.
- Email: required, valid format, stored lower-case, unique (pre-check + UNIQUE index + error 1062 handling).
- Telephone: required. Accepted local format = 9 digits starting with 6 (Cameroon mobile), e.g. `671234567`. Spaces, hyphens and a `+237`/`237` prefix are tolerated and stripped; only the 9 digits are stored.
- Date of birth: real calendar date (YYYY-MM-DD), in the past, 1900 or later.
- Programme and level: required, must be one of the listed options (server whitelist).
- Gender: optional; if sent it must be Male or Female.
- Registration policy: must be ticked.

All checks run on the server; HTML5 attributes are only a convenience (the form uses `novalidate` so the server messages can be demonstrated). Invalid submissions keep the entered values (escaped with `htmlspecialchars`) and show a message beside each faulty field.

## Security measures
- Every query uses PDO prepared statements with real (non-emulated) preparation; user input is never concatenated into SQL.
- Success is shown only after `rowCount() === 1`; connection and SQL errors are logged with `error_log` and a generic message is shown to the user.
- CSRF token on every POST, post/redirect/get after success to prevent resubmission.
- All output is HTML-escaped.

## Extension challenge
Searchable list under the form (name, email or programme, `?q=`).

## Test cases
Run each case, then fill in the last column with the observed result and attach screenshots of the form and of the database query below.

| # | Case | Input | Expected result | Actual result |
|---|---|---|---|---|
| 1 | Valid submission | Name `Ada Lovelace`, `ada@example.com`, `671234567`, DOB `2000-05-10`, any programme/level, policy ticked | Success message with student number; 1 new row | _fill in_ |
| 2 | Missing required value | Leave Full name empty | Error "Enter your full name."; no row; other values kept | _fill in_ |
| 3 | Invalid email | `ada@@example` | Error under Email; no row | _fill in_ |
| 4 | Invalid telephone | `12345` | Error "Enter 9 digits starting with 6"; no row | _fill in_ |
| 5 | Future date of birth | DOB = tomorrow | Error "must be in the past"; no row | _fill in_ |
| 6 | Policy not ticked | All valid, policy unticked | Error under policy; no row | _fill in_ |
| 7 | Duplicate email | Re-submit test 1 (or `ADA@Example.com`) | Error "already registered"; still 1 row for that email | _fill in_ |
| 8 | SQL injection attempt | Name `Robert'); DROP TABLE students;--` | Rejected by name rule (and parameterized anyway); table intact | _fill in_ |

### Database evidence query
```sql
SELECT * FROM students ORDER BY student_id DESC;
```

## Assumptions
- Use fictional data only; no real personal data.
- Phone numbers follow the Cameroon mobile format (9 digits starting with 6) because prices are in XAF; change the regular expression in `index.php` to adapt.
