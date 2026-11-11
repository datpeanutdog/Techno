# PASABUY

PASABUY is a student-to-student Laravel marketplace for buying, selling, and renting school books, supplies, technology, uniforms, and dorm essentials. Sign in or create an account to enter the marketplace hub.

## Get started

Requirements: PHP 8.3+, Composer, Node.js, and npm.

```powershell
composer install
if (!(Test-Path .env)) { Copy-Item .env.example .env }
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

To link the storages:
```powershell
php artisan storage:link
```

Open [http://localhost:8000](http://localhost:8000).

To keep the CSS and JavaScript rebuilding during development, run `npm run dev` in a second terminal.
Run `npm test` to execute the Laravel feature tests.

## Expo Go mobile app

The Expo app in `mobile/` uses the same Laravel backend. Login or registration is the first screen; after signing in, students can browse, search, rent, save favorites, open listing details, and post school-related items.

1. Connect your phone and development computer to the same Wi-Fi network.
2. Start Laravel so your phone can reach it:

   ```powershell
   php artisan serve --host=0.0.0.0 --port=8000
   ```

3. In a second terminal, start Expo:

   ```powershell
   Set-Location mobile
   npm ci
   Copy-Item .env.example .env
   npm start
   ```

4. Open Expo Go and scan the QR code. By default, the app uses the computer's Expo LAN address for the Laravel API. If you need to set `EXPO_PUBLIC_API_URL` in `mobile/.env`, use your computer's actual Wi-Fi IPv4 address (for example, `http://192.168.1.39:8000/api`), not the sample `YOUR_COMPUTER_LAN_IP`. Restart Expo after changing `.env`. On Windows, allow PHP/Laravel through the firewall on private networks if the phone cannot connect.

Both the web and mobile apps read and write the same listings and user accounts. The marketplace hub requires sign-in. Mobile access tokens are stored securely on-device. Registration, login, and posting do not require email verification. Users can edit or remove their own listings and customize their display name, campus, bio, and profile photo.

Run `php artisan storage:link` once so uploaded profile photos are publicly available. The default local mailer is `log`; password-reset links are written to `storage/logs/laravel.log` instead of sending real email. Configure a real SMTP/mail provider in `.env` before deploying. For Expo Go on a phone, use your computer's LAN address for `APP_URL` and `EXPO_PUBLIC_API_URL`.

## What's included

- Search campus listings by keyword and pickup area, filter by school category or rental/sale, and sort by price or date.
- Open a listing's detail page and see its location, seller, price, and condition.
- Register, sign in, and recover a password from the website or mobile app.
- Customize the public profile with a display name, campus, short bio, and uploaded photo or secure photo URL.
- Post new sale or rental listings after signing in. Rental posts include a day, week, or month price period.
- View, update, and remove your own listings on either platform.
- Save favorite finds in the current browser with the heart button.
- Seeded example listings to explore the marketplace right away.

Seller ratings and the seeded campus listings are sample content. Payments and buyer/seller messaging are not included.
