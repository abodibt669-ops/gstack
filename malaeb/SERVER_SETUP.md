# Wagti — server setup (read before going live)

## 1. PHP
PHP 8.x with the **mysqli**, **mbstring** and **curl** extensions. XAMPP ships all three.
On a Linux host install them (e.g. `php-mysql php-mbstring php-curl`): without mbstring
registration fails with a fatal error, and without curl live payments fail.

## 2. Database
- **Real server:** import **`schema.sql`** — tables only, no accounts, never drops anything.
  Create (or pick) the database first and import into it; the file does not create or select one.
- **Your machine only:** `database.sql` — drops everything and loads demo accounts with public
  passwords (admin123 / pass1234). Never import it on the real server.
- **A database created before login throttling:** run `migrations/001_login_attempts.sql` once.

## 3. Create the admin (and court owners)
The website cannot create admins or owners, on purpose. From the command line on the server:
```
php tools/create_user.php admin "Your Name" you@example.com 05XXXXXXXX
php tools/create_user.php owner "Owner Name" owner@club.com 05XXXXXXXX
```
Min 12-character password, or press Enter to generate a strong one (shown once).
Run it with the same `MALAEB_DB_*` environment variables as the site.

## 4. Environment variables
| Variable | Production value |
|---|---|
| `MALAEB_ENV` | `production` (hides errors from visitors; switches payments to strict mode) |
| `MALAEB_DB_HOST` / `MALAEB_DB_USER` / `MALAEB_DB_PASS` / `MALAEB_DB_NAME` | a dedicated DB user — not root with an empty password |
| `WAGTI_PAYMENTS_MODE` | `live` |
| `MOYASAR_SECRET_KEY` / `MOYASAR_PUBLISHABLE_KEY` | your real keys from the Moyasar dashboard |
| `WAGTI_BASE_URL` | **Required.** The site's public `https://` address, e.g. `https://wagti.example.com` (include the subfolder if the site lives in one). The payment callback URL is built from it; without it the callback would come from the visitor's `Host` header, which a visitor can forge, so payments stay off until it is set. |
| `MOYASAR_API_BASE` | **leave unset** (defaults to `https://api.moyasar.com/v1`). It exists only so tests can point at a stub gateway; set on a real server, your secret key would be sent to whatever it points at. |

With Apache + mod_php (the XAMPP setup), set these with `SetEnv NAME value` in the virtual host.

With `MALAEB_ENV=production`, payments stay **switched off** until `WAGTI_PAYMENTS_MODE=live`,
both real keys are set, and `WAGTI_BASE_URL` is the site's `https://` address; customers see
"Online payment is temporarily unavailable" and the server log names what is missing.
On your own machine (no `MALAEB_ENV`) the simulated checkout works as before.

## 5. Web server
The `.htaccess` files block `*.sql`, `*.md`, hidden files, `.git/`, `config/`, `includes/`,
`templates/`, `tests/`, `tools/` and `migrations/`, and add basic security headers.
They need Apache with `AllowOverride All` (XAMPP default) and, for the headers, `mod_headers`.
On nginx, add equivalent rules — `.htaccess` is ignored there.
Serve the site over HTTPS only, then add an HSTS header at the host.

## 6. Login protection
After 5 failed logins for one email (or 20 from one IP) within 15 minutes, logins for it are
refused for 15 minutes. If the site sits behind a proxy/CDN (e.g. Cloudflare), configure Apache
`mod_remoteip` so the real visitor IP is used — otherwise every visitor shares the proxy's IP.
