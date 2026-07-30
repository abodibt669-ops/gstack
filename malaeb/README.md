# Malaeb - Online Sports Court Booking Platform
SWE 322 - Advanced Web Programming Project

## How to run (XAMPP)
1. Start Apache and MySQL in the XAMPP Control Panel.
2. Copy this `malaeb` folder into `C:\xampp\htdocs\`.
3. phpMyAdmin (http://localhost/phpmyadmin) -> Import -> `database.sql` -> Go.
4. Open http://localhost/malaeb/

## Test accounts
| Role     | Email             | Password |
|----------|-------------------|----------|
| Admin    | admin@malaeb.com  | admin123 |
| Customer | saud@example.com  | pass1234 |

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

## Folder map
```
malaeb/
  config/db.php               # DB connection (php)
  includes/
    auth.php                  # login / role helpers (php)
    template.php              # view() + render_page() -> links php to html
  templates/                  # PURE HTML (structure only, with {{blanks}})
    layout.html               # shared shell: nav + footer
    index.html  courts.html  booking.html
    my_bookings.html  edit_booking.html  login.html  register.html
    admin/dashboard.html  admin/bookings.html
    admin/add_court.html  admin/edit_court.html
    partials/                 # small reusable HTML pieces (rows, cards, alerts)
  index.php  courts.php  booking.php  ...   # LOGIC (php) for each page
  admin/dashboard.php  ...                   # admin logic (php)
  logout.php  admin/delete_court.php         # redirect only (no template)
  css/style.css   js/main.js   database.sql
```

## Technologies
HTML5 | CSS | JavaScript | PHP (mysqli prepared statements) | MySQL (XAMPP)
