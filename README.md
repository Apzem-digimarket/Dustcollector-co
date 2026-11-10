# Dustcollector-co

Website for **APZEM industrial dust collection equipment** at [www.dustcollector.in](https://www.dustcollector.in/). It includes a password-protected admin panel for managing products.

## What it does

- **Home page:** the equipment range, with category filters and product tiles that tilt towards the mouse pointer.
- **A page for every product** at `/products/<name>`, created automatically when a product is added in the admin. Each page has a photo gallery and full-screen viewer, animated key figures, a specifications table, features, applications, a quote request form and related products.
- **Admin panel** at `/admin/`, behind one email and password with a lockout after repeated failed logins. From it you can:
  - add, edit, duplicate, hide, reorder and delete products
  - upload a main photo and gallery photos for each product
  - edit specifications (choosing which appear as highlight figures), features and applications
  - manage categories
  - edit the text of the products section
  - change the login email and password

There's no database. Content is stored in `data/content.json`, and photos uploaded through the admin go in `uploads/`.

## Requirements

- PHP **7.4 or newer**, on Apache or LiteSpeed (any standard cPanel host)
- `mod_rewrite` for clean `/products/<name>` addresses. Without it, links fall back to `product.php?p=<name>`.

**GitHub Pages can't run this site, because it needs PHP.** Deploy it to PHP hosting instead; [DEPLOY.md](DEPLOY.md) has the steps.

## Run it locally

```bash
PHP_CLI_SERVER_WORKERS=8 php -S 127.0.0.1:8771 router.php
```

Then open http://127.0.0.1:8771/ for the site and http://127.0.0.1:8771/admin/ for the admin. The first visit to `/admin/` asks you to create the admin account. `router.php` applies the same rules as `.htaccess` for PHP's built-in server, and the live server refuses to serve it.

## Project layout

| Path | Purpose |
|---|---|
| `index.php` | Home page |
| `product.php` | Product page template, also the branded 404 page |
| `sitemap.php` | `/sitemap.xml` |
| `admin/` | Admin panel: `index.php` (login and page), `api.php` (save and upload), `admin.js`, `admin.css` |
| `includes/bootstrap.php` | Storage, validation, login and security helpers |
| `includes/layout.php` | Shared page head, header and footer, and the site's contact details |
| `assets/` | CSS, JS, logo and bundled product photos |
| `data/content.json` | All site content: products, categories and section text |
| `uploads/` | Photos uploaded through the admin (not stored in git) |
| `.htaccess`, `.user.ini` | Server rules and PHP settings for cPanel hosting |

## Not in this repository

These are excluded by `.gitignore`:

- `data/auth.php`: the admin's login email and password hash
- `data/login-attempts.php`: failed-login counters
- `data/php-errors.log`: the error log
- files in `uploads/`

Keep the live server's `data/` and `uploads/` folders backed up, because they hold the site's content.

## License

Apache 2.0. See [LICENSE](LICENSE).
