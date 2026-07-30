# Malaeb - Online Sports Court Booking Platform
SWE 322 - Advanced Web Programming Project

## How to run (XAMPP)
1. Start Apache and MySQL in the XAMPP Control Panel.
2. Copy this `malaeb` folder into `C:\xampp\htdocs\`.
3. phpMyAdmin (http://localhost/phpmyadmin) -> Import -> `database.sql` -> Go.
4. Open http://localhost/malaeb/

Re-importing `database.sql` is safe: it drops the three tables and rebuilds them
with fresh sample data, so you can always get back to a clean demo.

## Test accounts
| Role     | Email             | Password |
|----------|-------------------|----------|
| Admin    | admin@malaeb.com  | admin123 |
| Customer | saud@example.com  | pass1234 |

## Running the tests
```
php tests/run_tests.php
```
No database or web server needed. It checks the template filler and every
validation rule, and confirms the passwords above still match the stored hashes.

## How the HTML and PHP are separated
- **`.html` files** (in `templates/`) hold ONLY the page structure. They are pure
  HTML with blanks written as `{{name}}`, `{{price}}`, etc. No PHP code inside.
- **`.php` files** hold ONLY the logic: database queries, form handling, calculations.
- They are connected by one helper, `includes/template.php`. Its `view()` function
  loads a `.html` file and replaces each `{{blank}}` with a real value:

```php
// inside a .php file:
$content = view('courts.html', [
    'max_price' => $maxPrice,   // fills {{max_price}} in courts.html
    'cards'     => $cards,      // fills {{cards}} in courts.html
]);
render_page('Courts', $content);   // wraps it in layout.html and prints it
```

So the flow is:  **PHP (logic)  ->  view() fills the .html blanks  ->  finished page.**

### Two rules about values
1. **Everything is escaped for you.** If a court is named `<script>...</script>`,
   `view()` prints those characters instead of letting the browser run them.
   To insert markup you built yourself, say so with `raw(...)`:

```php
'name'   => $court['name'],                       // escaped (the safe default)
'action' => raw('<a href="booking.php">Book</a>'), // inserted as HTML
```

2. **Blanks are filled in one pass.** A value that happens to contain the text
   `{{price}}` is printed as-is; it never becomes another blank.

## Folder map
```
malaeb/
  bootstrap.php               # the one line every page starts with
  config/db.php               # DB connection (php)
  includes/
    auth.php                  # sessions, login/role checks, CSRF tokens, flash messages
    validation.php            # the rules for what counts as valid input
    template.php              # view() + render_page() -> links php to html
  templates/                  # PURE HTML (structure only, with {{blanks}})
    layout.html               # shared shell: nav + footer
    index.html  courts.html  booking.html
    my_bookings.html  edit_booking.html  login.html  register.html
    admin/dashboard.html  admin/bookings.html
    admin/add_court.html  admin/edit_court.html
    partials/                 # small reusable HTML pieces (rows, cards, alerts, buttons)
  index.php  courts.php  booking.php  ...   # LOGIC (php) for each page
  admin/dashboard.php  ...                   # admin logic (php)
  logout.php  admin/delete_court.php         # redirect only (no template)
  tests/run_tests.php         # checks the template filler and the validation rules
  css/style.css   js/main.js   database.sql
```

## How the security pieces fit together
- **Prepared statements** everywhere a value reaches SQL, so input is data and
  never becomes part of the query.
- **Passwords** are stored as bcrypt hashes (`password_hash`), never as text.
- **Escaping on output** is automatic in `view()`, so a stored value cannot turn
  into markup on the page.
- **CSRF tokens** on every form that changes something. Anything that cancels,
  deletes, or edits is a POST carrying a token; none of it happens on a link,
  because a link is something another site can make your browser follow.
- **Sessions** get a new id at login, and the cookie is HttpOnly + SameSite=Lax.
- **Ownership checks** in the query itself: `WHERE booking_id = ? AND user_id = ?`,
  so changing the id in the address bar cannot reach someone else's booking.
- **Overlapping bookings** are prevented inside a transaction, so two people
  clicking at the same moment cannot both get the slot.

## Configuration
`config/db.php` uses the XAMPP defaults. To point it somewhere else without
editing the file, set `MALAEB_DB_HOST`, `MALAEB_DB_USER`, `MALAEB_DB_PASS`, or
`MALAEB_DB_NAME`. Set `MALAEB_ENV=production` to keep error details out of the
page and in the log where they belong.

## Technologies
HTML5 | CSS | JavaScript | PHP (mysqli prepared statements) | MySQL (XAMPP)
