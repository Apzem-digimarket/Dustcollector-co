# Deploying dustcollector.in

This site runs on PHP, so it needs normal web hosting such as cPanel. GitHub Pages can't run it.

**Upload package:** `dustcollector-deploy.zip`, which sits next to the `Dust Collector` folder on the Mac. It contains your admin account (`data/auth.php`). That file is deliberately left out of GitHub, so if you download the code from GitHub instead, create the admin account at `/admin/` straight after uploading.

## Before you start

You need:

- cPanel hosting with **PHP 7.4 or newer** (PHP 8.x recommended)
- the domain **dustcollector.in** pointed at that hosting. It currently points to GitHub (`www` → `apzem-digimarket.github.io`, and the root domain → GitHub's 185.199.108–111.153 addresses). Do one of these at the domain registrar:
  - change the nameservers to the ones your host gives you, or
  - change the `A` record for `dustcollector.in` and `www` to your host's IP address, and remove the GitHub records.

  DNS changes can take a few hours to reach everyone.

## Steps

1. **Set the PHP version.** In cPanel, open **Select PHP Version** (or **MultiPHP Manager**) and choose PHP 8.1 or newer for dustcollector.in.
2. **Upload the zip.** Open **File Manager**, then go into `public_html`.
   - If `public_html` has a placeholder `index.html` or `default.html` from the host, delete it.
   - Click **Upload** and choose `dustcollector-deploy.zip`.
3. **Extract it.** Right-click the zip, choose **Extract**, and extract into `/public_html`. Then delete the zip from the server.
   - Click **Settings** and tick **Show Hidden Files (dotfiles)**. Check that `.htaccess` and `.user.ini` are in `public_html`.
4. **Check folder permissions.** The `data` and `uploads` folders should be **755** and the files inside **644**. This is usually already the case.
5. **Turn on HTTPS.** Open **SSL/TLS Status** and click **Run AutoSSL**, then wait until the domain shows a valid certificate.
6. **Force HTTPS** (only after step 5 works). Edit `public_html/.htaccess` and remove the `#` from these two lines:

   ```
   # RewriteCond %{HTTPS} off
   # RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   ```

7. **Log in to the admin panel** at **https://www.dustcollector.in/admin/**. The package already contains the admin account you created on your Mac, so use the same email and password.

## Check it's working

| Open this | You should see |
|---|---|
| https://www.dustcollector.in/ | Home page with the 17 products |
| https://www.dustcollector.in/products/downdraft-table | A product page |
| https://www.dustcollector.in/products/xyz | A styled "product isn't available" page |
| https://www.dustcollector.in/admin/ | Admin login |
| https://www.dustcollector.in/data/content.json | **403 Forbidden** (private data is protected) |
| https://www.dustcollector.in/sitemap.xml | A list of page addresses |

Then submit `https://www.dustcollector.in/sitemap.xml` in Google Search Console.

## If something goes wrong

| Problem | Fix |
|---|---|
| Product pages show "404 Not Found" | `.htaccess` is missing. Turn on hidden files in File Manager and re-upload it. |
| "500 Internal Server Error" on every page | Rename `.htaccess` to `htaccess.off`. If the site then loads, ask the host to enable `mod_rewrite` / AllowOverride. Product links still work without it (as `product.php?p=…`). |
| Admin says it "cannot write" when saving | Set the `data` and `uploads` folders to permission 755. |
| Image upload says "too large" | Resize the photo, or raise `upload_max_filesize` in **Select PHP Version → Options**. |
| Forgot the admin password | Delete `data/auth.php` in File Manager, then open `/admin/` and create the account again. |
| Something else is wrong | Look at `data/php-errors.log` in File Manager. |

## Backups

Your content is in only two places. Download them now and then:

- `data/content.json`: products, categories and panel text
- `uploads/`: photos uploaded through the admin
