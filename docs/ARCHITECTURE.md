# Shaheen Group of Travels — Group Ticketing Platform

**Status:** Draft v0.1 for discussion · 2026-11-17

A B2B web app where **Shaheen admins** publish group flight inventory (Umrah, KSA one-way, UAE, and so on) and **registered travel agents** book seats for their customers. Admins confirm or reject bookings, and every step sends email and in-app notifications.

---

## 1. Tech stack

| Layer | Choice | Why |
|---|---|---|
| Backend | **Laravel 12** (PHP 8.3) | Mature, and runs on Hostinger shared hosting |
| Frontend | **Vue 3 + Inertia.js** (Composition API, `<script setup>`) | One app and one deploy. No separate API or CORS, and session auth just works on cPanel |
| Styling | **Tailwind CSS v4** with CSS-variable theme tokens | Admins can change the brand colors at runtime |
| Database | **MySQL 8** | Hostinger default |
| Auth | Laravel Breeze (Inertia + Vue) with session guard | Simple and secure |
| Roles/permissions | `spatie/laravel-permission` | Admin-defined staff roles |
| PDF | `barryvdh/laravel-dompdf` | Booking vouchers and ticket exports |
| Mail | Laravel Mailables over SMTP (Hostinger mail), queued | Mail sending never blocks requests |
| Queue | `database` driver, run by cron | Shared hosting has no Supervisor or Redis |
| Notifications | Laravel Notifications (`mail` + `database` channels), with a bell icon that polls every 30s | No websockets on shared hosting |
| Audit | `spatie/laravel-activitylog` | Records who confirmed, rejected or edited what |
| UI kit | Headless UI + Heroicons, and a small set of our own components | Light, consistent and branded |

> **Why Inertia rather than a separate Vue SPA plus API?** On cPanel a single Laravel app is much easier to deploy and maintain. If you later need a mobile app, you can add a `/api` layer with Sanctum next to it.

---

## 2. Brand and theming

Colors are taken from the logo and stored as **settings** that admins can edit (Admin → Settings → Appearance).

| Token | Default | Usage |
|---|---|---|
| `--color-primary` | `#FFFFFF` / `#F7F8FC` (light surfaces) | Page backgrounds, cards |
| `--color-secondary` | `#0B1B4D` (logo navy) | Headers, sidebar, primary buttons, table heads |
| `--color-tertiary` | `#D4A84B` (logo gold) | Accents, highlights, prices, active states, badges |
| `--color-success` / `--color-danger` / `--color-warning` | green / red / amber | Booking statuses |

How it works:
- A `settings` table holds the values. `HandleInertiaRequests` shares them, and the root layout writes them into `:root { --color-secondary: … }`.
- Tailwind v4 `@theme` maps them to utilities (`bg-secondary`, `text-tertiary`, …), so changing a color in the admin panel restyles the whole app with no rebuild.
- Logo, favicon, site name, ticker/announcement text and the WhatsApp number can also be edited in settings.

Typography: **Poppins** for headings and **Inter** for body text and tables.

---

## 3. Users and access

One `users` table with a `type` column:

| Type | Who | Access |
|---|---|---|
| `super_admin` | Owner | Everything. Cannot be restricted |
| `staff` | Admin employees | Only what their **role** allows (roles are created by the super admin) |
| `agent` | Travel agency user | Agent portal only. Must be **approved** before they can log in and book |

### Staff roles and permissions
The super admin creates roles (e.g. "Booking Desk", "Inventory Manager") and ticks permissions:

```
dashboard.view
categories.manage     sectors.manage      airlines.manage     baggage.manage
flights.view          flights.create      flights.edit        flights.delete   flights.publish
bookings.view         bookings.approve_payment                bookings.confirm
bookings.reject       bookings.edit       bookings.delete
bookings.export
agents.view           agents.approve      agents.edit         agents.suspend
prices.view           ← controls ALL fare/price visibility
reports.view          settings.manage     roles.manage        staff.manage
```

**Hiding prices is enforced on the server, not only in the UI.** API resources (`FlightResource`, `BookingResource`) leave out every price field when the user lacks `prices.view`, so staff cannot see prices even through browser dev tools.

Other rules (built in Step 2):
- **Agents always see prices.** They book at those fares, so `prices.view` only applies to the admin side.
- **`roles.manage` and `staff.manage` are admin-level.** Anyone with them can grant permissions, so give them only to trusted staff. Staff cannot edit or deactivate their own account.
- Every admin-side user can open the dashboard; the statistics need `dashboard.view`.
- Permissions are defined in `app/Support/Permissions.php`; run `PermissionSeeder` after adding one.

### Agent lifecycle
`pending → approved → (suspended)` or `pending → rejected`. Only approved agents can see flights.

---

## 4. Domain model

```
Category (Umrah Groups, KSA Oneway, UAE, Muscat, UK, Bahrain, Kuwait, Qatar…)
  └── Sector / Sub-category (LAHORE-JEDDAH-LAHORE, MULTAN-JEDDAH-MULTAN…)
        └── Flight (inventory block)  ── Airline, BaggageType
              └── Booking (by Agent)
                    └── Passengers
```

### 4.1 Tables

**users**: id, type, name, email, phone, password, status (`pending|approved|rejected|suspended`), approved_by, approved_at, last_login_at, timestamps

**agencies** (1:1 with agent user): user_id, agency_name, owner_name, city, address, phone, whatsapp, logo, `notification_emails` (JSON, the extra emails that receive booking mails), credit_limit (future), timestamps

**categories**: id, name, slug, country, flag_icon, image, description, sort_order, is_active

**sectors**: id, category_id, name (`MULTAN-JEDDAH-MULTAN`), origin, destination, trip_type (`one_way|return`), sort_order, is_active

**airlines**: id, name, iata_code, logo, is_active

**baggage_types**: id, label (`20+10 KG`, `1PC X 23 KG`), description, is_active

**flights**: the main inventory block
| Column | Notes |
|---|---|
| category_id, sector_id, airline_id | FKs |
| departure_date, arrival_date | Departure = travel date. Arrival = return date for return groups |
| flight_code | One string, e.g. `9P700 / 9P701` |
| departure_time, arrival_time | Stored as 24h `TIME`. The 12h/24h buttons only change input and display format |
| baggage_type_id | One baggage type per flight |
| meal | boolean |
| fare_price | Displayed "from" price on listings |
| adult_price, child_price, infant_price | |
| total_seats | Quantity available when created |
| seats_confirmed | Counter, kept in sync inside transactions |
| pnr | Hidden from agents until their booking is confirmed (see open question 1) |
| status | `draft → live → sold_out / closed / departed` |
| created_by, timestamps, soft deletes |

`seats_available = total_seats − seats_confirmed` (computed)

**A return flight is one record (one leg)**, with no separate segments table. The fields match your admin form exactly.

**bookings**
| Column | Notes |
|---|---|
| booking_no | `BK-000123` |
| agent_id, flight_id | |
| booker_name, booker_phone, booker_email | From the booking form |
| adults, children, infants | Requested counts |
| adult_price, child_price, infant_price, total_price | **Snapshot** taken at booking time, so later price edits do not change existing bookings |
| status | `pending | payment_approved | confirmed | rejected | cancelled` |
| payment_status | `unpaid | approved` |
| payment_approved_by / payment_approved_at, payment_note | Who approved the payment, with an optional reference (bank slip no., amount received) |
| confirmed_by / confirmed_at, rejected_by / rejected_at, reject_reason | |
| admin_remarks, agent_remarks | |
| timestamps, soft deletes | |

**booking_passengers**: booking_id, pax_type (`adult|child|infant`), title (Mr/Mrs/Ms/Mstr/Miss), given_name, surname, passport_no, dob, passport_issue_date, passport_expiry_date, is_lead (family head)

**booking_status_logs**: booking_id, from_status, to_status, user_id, note, timestamp

**settings**: key, value (theme colors, admin notification emails, admin WhatsApp number, company info, ticker text)

**notifications**: Laravel's built-in table. **activity_log**: Spatie.

---

## 5. Core business rules: seats and booking flow

Decided rules:
- **No seat holding and no expiry.** A pending booking does not reserve seats and never times out. It stays pending until an admin acts on it.
- **No partial confirmation.** A booking is confirmed or rejected as a whole.
- **Payment first.** An admin must approve the agent's payment before the booking can be confirmed.

In the *"5 seats available, several agents book them all"* case, several pending bookings can compete for the same seats. Whichever has its payment approved and is confirmed first gets the seats, and the admin rejects the rest.

```
 Agent submits booking ──► status = PENDING, payment = UNPAID
        │                    • allowed if requested pax ≤ seats_available
        │                    • agent sends payment proof to admin (WhatsApp button)
        ▼
 Admin ─┬─► APPROVE PAYMENT ──► status = PAYMENT_APPROVED (note: slip no. / amount)
        │         │
        │         ▼
        │     CONFIRM BOOKING ──► DB transaction + row lock on flight
        │                          • re-check seats_available ≥ pax (else block)
        │                          • seats_confirmed += pax
        │                          • if seats_available == 0 → flight = SOLD_OUT,
        │                            hide from agents, notify admin, and flag the
        │                            remaining pending bookings as "no seats left"
        └─► REJECT (from pending or payment_approved) ──► reason required, emails agent
 Agent may EDIT / DELETE only while PENDING (before payment is approved).
 Admin may cancel a CONFIRMED booking → seats return to inventory.
```

- **"Confirm Booking" stays disabled** until the payment is approved. These are separate permissions (`bookings.approve_payment`, `bookings.confirm`), so payment approval can be given to accounts staff only.
- **Race safety:** confirmation uses `SELECT … FOR UPDATE` on the flight row, so two staff members can never over-confirm.
- **Agent visibility:** agents see only flights that are `live` with `seats_available > 0` and a departure date of today or later.
- **Admin status toggle:** the status column in the admin bookings list is a dropdown. Each change goes through the same service (`BookingService::approvePayment/confirm/reject/cancel`), so the rules and emails always apply.
- **Auto-close:** a daily job marks flights `departed` after their departure date.

---

## 6. Notifications matrix

All mail is queued. Each event also creates an in-app (bell) notification.

| Event | Admin(s) | Agent (+ agency emails) | Booker email |
|---|:-:|:-:|:-:|
| Agent signs up | ✅ "approval needed" | ✅ "received, pending approval" | |
| Agent approved / rejected | | ✅ | |
| Booking created | ✅ | ✅ booking summary | ✅ (optional) |
| Payment approved | | ✅ | |
| Booking confirmed | | ✅ full flight details + PNR + PDF voucher | ✅ |
| Booking rejected (incl. "seats taken") | | ✅ with reason | ✅ |
| Booking cancelled | ✅ | ✅ | |
| Flight sold out (0 seats left) | ✅ | | |
| Flight low on seats (≤ N, configurable) | ✅ | | |

"Admin(s)" means the super admin, staff with `bookings.view`, and the admin emails set in Settings.

---

## 7. Screens

### 7.1 Agent portal (light theme, navy + gold accents)
1. **Public landing**: hero, category cards (as in your 6th screenshot), WhatsApp button, "Agent Login / Register".
2. **Register / Login**: agency details. After signing up, the agent sees a "pending approval" screen.
3. **Dashboard**: stat cards (Confirmed / Pending / Cancelled), announcement ticker, quick links (your 3rd screenshot, redesigned).
4. **Groups — step 1:** grid of **categories** with image, flag and country.
5. **Groups — step 2 (category page):** **sector pills** at the top (`All · LAHORE-JEDDAH-LAHORE · MULTAN-JEDDAH-MULTAN …`). Flights are listed below in tables grouped by sector, with airline logo. Columns: Date (out/in), Flight, Time, Bag, Meal, Fare, Seats left, **Book Now**.
6. **Booking modal / page** (your 1st screenshot, improved):
   - Pax counters (Adults / Child / Infants) with live price breakdown
   - Booker name / phone / email
   - Passenger grid: one row per pax with Title, Given name, Surname, Passport #, DOB, Passport issue, Passport expiry. Client-side and server-side validation (passport expiry later than travel date, child and infant age checks)
   - Submit → success screen with booking number and a **WhatsApp button** to send payment proof
7. **My Bookings**: filters (date range, status, sector). Columns:
   `Date · Booking ID · Agency · PNR · Airline · Sector · Lead Passenger · Travel Date · Status · Payment · Actions (WhatsApp · PDF · Edit · Delete)`. Edit and delete are allowed only while pending.
   - **WhatsApp button:** opens `wa.me/<admin number>` (number set in admin Settings) with a prefilled message, for example: *"Booking BK-000123 · MULTAN-JEDDAH-MULTAN · SV801 · 04-12-2026 · 3 pax · PKR 555,000 — payment proof attached."* The agent then attaches the payment slip in WhatsApp.
   - The original "Time Remaining in Expiration" column is dropped, because bookings no longer expire.
8. **Profile**: agency info and notification emails.

### 7.2 Admin panel
1. **Dashboard**: today's bookings, pending queue, seats sold, flights about to sell out, revenue (hidden without `prices.view`).
2. **Masters**: Categories, Sectors, Airlines, Baggage Types (CRUD, sorting, active toggle).
3. **Flights**: list with filters, plus a single create/edit form with all your fields (one set of dates, flight code, times and baggage), plus the **12h / 24h toggle**, "Duplicate flight" (fast entry for daily groups), "Publish / Unpublish", and a seats bar (confirmed / pending / available).
4. **Bookings**: same columns as the agent view plus agency name. Each row has an **Approve Payment** button (with optional slip no. / amount note), then a **Confirm Booking** button that unlocks after payment is approved, plus Reject. Also: inline status toggle, bulk reject, PDF export, a per-flight view showing all competing pending requests side by side, and a WhatsApp button to message the agent.
5. **Agents**: approval queue, approve/reject/suspend, view agency bookings.
6. **Staff & Roles**: create staff users, build roles with a permission checkbox matrix.
7. **Settings**: theme colors (live preview), logo, company info, admin WhatsApp number, admin notification emails, low-seat threshold, mail test.
8. **Activity log.**

---

## 8. Code structure

```
shaheen-GOT/
├── docker/                     # local dev only
│   ├── php/Dockerfile          # php:8.3-fpm + extensions matching Hostinger
│   ├── nginx/default.conf
│   └── mysql/my.cnf
├── docker-compose.yml          # app, nginx, mysql, node (vite), mailpit, phpmyadmin
├── app/
│   ├── Enums/                  # UserType, BookingStatus, FlightStatus, PaxType
│   ├── Models/
│   ├── Http/
│   │   ├── Controllers/Admin/  # FlightController, BookingController, AgentController, RoleController…
│   │   ├── Controllers/Agent/  # GroupController, BookingController, DashboardController
│   │   ├── Middleware/         # EnsureUserType, EnsureAgentApproved
│   │   ├── Requests/           # FormRequest validation per action
│   │   └── Resources/          # API resources (price fields gated by permission)
│   ├── Services/               # BookingService, FlightInventoryService, SettingsService
│   ├── Notifications/          # BookingCreated, BookingConfirmed, AgentApproved, FlightSoldOut…
│   ├── Policies/               # BookingPolicy, FlightPolicy
│   ├── Jobs/ & Console/        # MarkDepartedFlights
│   └── Pdf/                    # booking voucher view
├── resources/
│   ├── css/app.css             # Tailwind v4 @theme → CSS variables
│   ├── js/
│   │   ├── Layouts/            # AdminLayout.vue, AgentLayout.vue, PublicLayout.vue
│   │   ├── Pages/Admin/…       # Inertia pages
│   │   ├── Pages/Agent/…
│   │   ├── Pages/Public/…
│   │   ├── Components/ui/      # Button, Input, Modal, DataTable, Badge, StatCard, WhatsAppButton, TimeInput(12/24h)
│   │   └── Composables/        # usePermissions, useMoney, useWhatsApp
│   └── views/pdf/              # dompdf blade templates
├── routes/web.php              # /admin/* and /agent/* groups with middleware
├── database/migrations, seeders (roles, permissions, demo data)
└── docs/
```

---

## 9. Local development (Docker)

`docker-compose.yml` services:
- **app**: PHP 8.3-FPM (same version and extensions as Hostinger: pdo_mysql, gd, zip, intl, bcmath)
- **nginx**: serves `public/` on `localhost:8080`
- **mysql**: 8.0, with a persistent volume
- **node**: runs `npm run dev` (Vite HMR on 5173)
- **mailpit**: catches every email at `localhost:8025`, so notifications can be tested safely
- **phpmyadmin**: optional, on `localhost:8081`
- **scheduler/queue**: one container runs `schedule:work` and `queue:work`, standing in for the cron setup used in production

---

## 10. Hostinger deployment

1. Build assets locally or in CI: `npm run build`. Hostinger shared hosting usually has no Node.
2. Upload the code via Git (hPanel Git deploy) or SSH. Run `composer install --no-dev -o`.
3. Point the domain's document root to `/public`. If that is not possible, keep the app in `~/shaheen-app` and put only the `public/` contents in `public_html` with adjusted `index.php` paths.
4. Set up `.env` (DB, SMTP from Hostinger email, `APP_ENV=production`, `QUEUE_CONNECTION=database`).
5. `php artisan migrate --force && php artisan storage:link && php artisan optimize`
6. **Cron (every minute):**
   `* * * * * cd ~/shaheen-app && php artisan schedule:run >> /dev/null 2>&1`
   The scheduler runs `queue:work --stop-when-empty` each minute (sends mail), plus the flight-departed job.

---

## 11. Delivery phases

| Phase | Scope |
|---|---|
| **1. Foundation** | Docker, Laravel + Inertia + Vue + Tailwind, theme tokens, auth, user types, layouts |
| **2. Admin masters** | Categories, sectors, airlines, baggage, settings (theme + logo) |
| **3. Flights** | Flight CRUD, 12/24h input, publish, duplicate, seat counters |
| **4. Agent portal** | Registration + approval, category → sector listing, booking form with passengers |
| **5. Booking workflow** | Approve payment → confirm / reject / cancel, race-safe inventory, status toggle, WhatsApp button, PDF |
| **6. Notifications** | All mails + in-app bell, Mailpit testing |
| **7. Roles & permissions** | Staff, role builder, server-side price hiding |
| **8. Polish & deploy** | Dashboards, reports, activity log, Hostinger go-live |
| *Later* | Payments / ledger / bank (as in the reference portal), agent credit limits, Umrah packages |

---

## 12. Decisions log

| # | Topic | Decision (2026-11-17) |
|---|---|---|
| D1 | Return flights | **One leg per flight.** A single record with departure/arrival date, one flight code string, departure/arrival time and one baggage type. No segments table |
| D2 | Seat holding | None. Pending bookings compete; seats are used only on confirmation |
| D3 | Partial confirmation | None. Confirm or reject the whole booking |
| D4 | Hold time / expiry | None. Bookings stay pending until an admin acts |
| D5 | Payment | Admin must **Approve Payment** before **Confirm Booking** is allowed |
| D6 | WhatsApp | Agent booking screens have a WhatsApp button to the admin number with a prefilled booking message |

## 13. Open questions

1. **PNR visibility:** should agents see the PNR on listings, or only after confirmation?
2. **"Agency (Super Admin)" column:** is this the agent's agency name, or the admin who handled it?
3. **Edit/delete by agent:** only while pending, as above? Soft delete (kept for audit)?
4. **Child/infant pricing:** can each be zero or hidden (the reference shows "PKR:" blank)?
5. **Agent self-registration fields:** which documents or details are needed (CNIC, IATA/license no., address)?
6. **Payment proof:** is WhatsApp enough, or should agents also upload the slip in the portal so it is stored with the booking?
7. **Languages / currency:** English only, PKR only?
