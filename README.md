# Little Olive Analytics Demo

A compact end-to-end analytics example: a restaurant demo emits browser page and interaction events, records an order-created business event on the server, stores events in SQLite, and displays date-filtered reporting.

## Local setup

Requirements: PHP 8.2+, Composer, Node.js 20+, and npm.

```sh
npm install
cd backend
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
cd ..
npm run dev
```

Open the Vite URL (normally `http://localhost:5173`). Vite proxies `/api` requests to the Laravel API on `http://127.0.0.1:8000`; `npm run dev` starts both servers. Open **Analytics** in the header to see page views and event trends; try a menu selection and submit an order to generate custom and server-side events.

For a production-style local run:

```sh
npm run build
npm run preview
```

Run `npm start` in a second terminal to start the Laravel API. Laravel owns the `/api` endpoints and SQLite database at `backend/database/database.sqlite`. React can use a separate API host by setting `VITE_API_BASE_URL` to its base URL at build time; configure the API's allowed frontend origins in `CORS_ALLOWED_ORIGINS`. Set `ANALYTICS_SERVER_KEY` in `backend/.env` to authorize trusted server event submissions; production must use a non-development key.

Run the Laravel API tests with `npm test`.

For the architecture, database flow, API behavior, design decisions, setup, limitations, and interview explanation, see the [assignment and interview guide](docs/ASSIGNMENT_GUIDE_HI.md).

For an English project overview and step-by-step user guide, see the [project overview and user guide](docs/PROJECT_OVERVIEW_AND_USER_GUIDE.md).

## Event and identity decisions

- An event has an idempotency `event_id`, lowercase `event_name`, anonymous `visitor_id`, tab-scoped `session_id`, `source`, optional relative `page_path`, JSON `properties`, client/business `occurred_at`, and server `received_at`.
- The browser SDK uses a random first-party ID in local storage and a random session ID in session storage. IDs are pseudonymous, not authenticated account identities, and may be cleared or unavailable across devices.
- Browser ingestion is same-origin and accepts batches of up to 50 events. Events deduplicate on `event_id`; malformed events fail the entire batch so callers can retry safely. Server event submissions use `X-Analytics-Key`; the key must never be shipped to browsers.
- Orders are priced by Laravel and saved atomically with their trusted `order_created` analytics event. Other backend business flows can use the reusable `App\Services\Analytics\ServerEventTracker` to record trusted server events.
- Dashboard dates are UTC calendar dates, inclusive at both ends, with a maximum 90-day range. “Unique visitors” is the number of distinct anonymous browser IDs with a page view in the selected range. Daily chart values include all event types.

## API

- `POST /api/events` — `{ "events": [...] }`, returns accepted and duplicate counts. Public browser events; add `X-Analytics-Key` for trusted server events.
- `GET /api/analytics/summary?from=YYYY-MM-DD&to=YYYY-MM-DD` — totals, zero-filled daily counts, popular pages, and event counts.
- `GET /api/menu` — demo menu and server-owned prices.
- `POST /api/orders` — accepts menu item, quantity, visitor ID, and session ID; returns the server-priced order and confirms that its analytics event was saved.

## Operational notes and limitations

The ingestion API limits event batches to 50, event properties to 4 KB, validates event names/IDs/paths, bounds event timestamps to 30 days in the past and 5 minutes in the future, and applies Laravel's per-IP request limit. Public browser ingestion remains susceptible to fabricated traffic; the server key protects server-only event claims, not public page views.

SQLite is appropriate for a single-process demonstration, not a high-volume multi-instance analytics pipeline. Retention, consent management, authenticated identities, delivery queues/retries, bot filtering, and cross-region/time-zone reporting are deliberately out of scope.
