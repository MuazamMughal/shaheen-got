# Deploying to Hostinger

This guide works for Hostinger shared/business hosting (hPanel, or the cPanel product). You need:
- **PHP 8.3**
- **MySQL**
- **SSH access** (Business plan or higher). SSH is strongly recommended; there's a no-SSH path at the end.
- A **cron job**

> Do the steps in order. Commands in `code` run **on the server over SSH** unless they say "on your computer".

---

## 1. Prepare the hosting (once)

1. **PHP version:** hPanel → *Advanced → PHP Configuration* → choose **PHP 8.3**.
   Make sure these extensions are on: `pdo_mysql, mbstring, gd, zip, intl, bcmath, fileinfo, exif`.
2. **Database:** hPanel → *Databases → MySQL Databases* → create a database and a user. Write down the name, user and password.
3. **Email account for sending:** hPanel → *Emails* → create e.g. `noreply@your-domain.com`. Its SMTP settings are host `smtp.hostinger.com`, port `465`, SSL.
4. **SSH:** hPanel → *Advanced → SSH Access* → enable it and note the host, port and username.

## 2. Build the release (on your computer)

```bash
./deploy/build-release.sh
```

This creates `deploy/releases/shaheen-<date>.zip`, which contains the production assets and PHP dependencies. It contains no `.env` and no tests.

## 3. Upload

Upload the zip to your home folder, outside `public_html` (hPanel *File Manager* or `scp`). Then unzip it:

```bash
mkdir -p ~/shaheen-app && cd ~/shaheen-app
unzip -o ~/shaheen-*.zip
```

## 4. Configure `.env`

```bash
cd ~/shaheen-app
cp .env.production.example .env
nano .env        # fill in APP_URL, DB_*, MAIL_*, SUPER_ADMIN_*
php artisan key:generate --force
```

## 5. Point the domain at `/public`

**Option A (recommended):** hPanel → *Websites → your domain → Advanced → Change website root* (or cPanel *Domains*) → set it to `shaheen-app/public`.

**Option B (if you can't change the root):** serve the app from `public_html`.

```bash
cp -r ~/shaheen-app/public/. ~/public_html/
cp ~/shaheen-app/deploy/public_html/index.php ~/public_html/index.php
```

With option B, repeat the `cp -r …public/.` step on every update, because the built assets live in `public/build`.

## 6. First-time setup

```bash
cd ~/shaheen-app
php artisan migrate --force
php artisan db:seed --force          # settings, permissions, super admin from .env
php artisan storage:link             # uploaded logos/images
php artisan optimize                 # cache config, routes, views
```

Optional: add the starter categories, sectors, airlines and baggage types.

```bash
php artisan db:seed --class=MasterDataSeeder --force
```

After seeding, **remove `SUPER_ADMIN_PASSWORD` from `.env`**, then log in and change your password under *My Profile*.

> **Using option B?** `storage:link` creates the link inside `shaheen-app/public`. Also run:
> `ln -s ~/shaheen-app/storage/app/public ~/public_html/storage`

## 7. Cron (required: sends all emails and runs nightly jobs)

hPanel → *Advanced → Cron Jobs* → **Custom**, every minute (`* * * * *`):

```
cd /home/USERNAME/shaheen-app && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Replace `USERNAME` with your Hostinger username. Run `pwd` in SSH to see the full path. Every minute this:
- sends queued emails (`queue:work --stop-when-empty`)
- marks departed flights each night at 00:05
- prunes activity-log rows older than 365 days, once a week

## 8. Check it works

1. Open the site and log in as the super admin.
2. **Admin → Settings:** set your WhatsApp number, contact details and admin notification emails, then press **Send test email**.
3. Register a test agent from another browser. You should get the "approval needed" email within about a minute (that's the cron at work).

---

## Updating to a new version

On your computer:

```bash
./deploy/build-release.sh
```

Upload the new zip, then on the server:

```bash
cd ~/shaheen-app
php artisan down
unzip -o ~/shaheen-<new>.zip        # keeps your .env and storage/app/public uploads
php artisan migrate --force
php artisan db:seed --class=PermissionSeeder --force   # adds any new permissions
php artisan optimize:clear && php artisan optimize
php artisan up
```

With option B (`public_html`), copy `public/.` again as in step 5.

## No SSH?

1. Upload and extract the zip with *File Manager*, and edit `.env` there.
2. Generate an app key on your computer and paste it into `.env` as `APP_KEY=base64:...`:

```bash
docker compose exec app php artisan key:generate --show
```

3. Import the database: export your local DB with phpMyAdmin (localhost:8081) and import it in hPanel → *phpMyAdmin*. Or run migrations once through a temporary cron job, `php artisan migrate --force`, then delete that cron.
4. The cron from step 7 still works without SSH.

## Troubleshooting

| Symptom | Fix |
|---|---|
| Blank page / 500 | Check `storage/logs/laravel-*.log`. Usually a wrong DB password or `APP_KEY` missing. |
| Emails not arriving | Is the cron running? Check that `jobs` has no rows stuck. Use Settings → *Send test email*. |
| Images 404 | Run `php artisan storage:link` (plus the `public_html` link for option B). |
| Styles missing | `public/build` wasn't uploaded, or with option B it wasn't copied to `public_html`. |
| "419 Page expired" | `APP_URL` must match the real https domain, and `SESSION_SECURE_COOKIE=true` needs HTTPS (enable free SSL in hPanel). |
