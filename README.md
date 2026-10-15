# Little Olive Analytics Demo

A small end-to-end analytics system demonstrated through a neighborhood restaurant website. Visitors can browse a sample menu and submit a demonstration pickup order. Browser activity and trusted server-side order events are stored in SQLite and reported on a date-filtered dashboard.

This is a demo: it does not take payment or send orders to a real restaurant.

## Features

- Reusable browser analytics SDK for page views and custom interaction events.
- Laravel event-ingestion API with validation, source assignment, and duplicate protection.
- Server-side order pricing and persistence, with an `order_created` event recorded atomically.
- SQLite storage for analytics events and demo orders.
- Dashboard for page views, anonymous unique visitors, popular pages, event counts, and daily trends.
- Date-range filtering for dashboard reports.

## Architecture

```text
Browser (React)
  ├── Restaurant menu and demo order form
  ├── Browser analytics SDK ── POST /api/events ──┐
  └── Analytics dashboard ── GET /api/analytics/summary
                                                   │
Laravel API                                       │
  ├── Validates browser events                    │
  ├── Prices and saves demo orders                │
  └── Records trusted order_created events        │
                                                   ▼
                                      SQLite: events and orders
```

The browser SDK sends page views and user interactions. Laravel handles order creation and records its trusted business event. The dashboard asks Laravel for a summary of stored events for the selected date range.

## Local setup

Requirements: PHP 8.2+, Composer, Node.js 20+, and npm.

From the project root:

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

Open the Vite URL, normally `http://localhost:5173`. `npm run dev` starts Vite and the Laravel API; Vite proxies `/api` requests to `http://127.0.0.1:8000`. Use **The menu** to generate browser activity and submit a demo order, then open **Analytics** to see the reports.

The default local database is `backend/database/database.sqlite`. Laravel's SQLite configuration uses that file unless `DB_DATABASE` is set in `backend/.env`.

Useful commands:

```sh
npm test       # Run Laravel API tests
npm run build  # Build the frontend
npm start      # Start only the Laravel API
```

For a production-style local frontend preview, run `npm run build` and then `npm run preview`. Run `npm start` in a separate terminal for the API. To use a separate API host, set `VITE_API_BASE_URL` at frontend build time and configure the API's allowed frontend origins in `CORS_ALLOWED_ORIGINS`.

## Browser SDK integration

The reusable browser SDK is implemented in [`src/analytics.js`](src/analytics.js). Create a client once in browser code, then call its methods when a page or interaction should be tracked:

```js
import { createAnalyticsClient } from "./analytics.js";

const analytics = createAnalyticsClient();

// Record a page view.
await analytics.pageView("/");

// Record a custom interaction with event properties.
await analytics.event("menu_item_selected", { item_id: "margherita" });
```

The client also exposes `track(eventName, properties, pagePath)` for lower-level tracking, plus `visitorId` and `sessionId`. It sends browser events to `POST /api/events`. In this app, page views are recorded by `usePageView()` in `src/main.jsx`, and menu selection is recorded as `menu_item_selected`.

## Event and identity model

Each stored event contains:

| Field | Purpose |
|---|---|
| `event_id` | Unique event identifier; primary key used to prevent duplicate rows |
| `event_name` | Lowercase event type, such as `page_view` |
| `visitor_id` | Anonymous browser identifier |
| `session_id` | Anonymous tab/session identifier |
| `source` | Server-assigned `browser` or `server` source |
| `page_path` | Optional relative page path |
| `properties` | JSON object with event-specific details |
| `occurred_at` | Event timestamp in UTC |
| `received_at` | API receipt timestamp in UTC |

The SDK stores a random visitor ID in browser `localStorage` and a session ID in `sessionStorage`. These IDs are pseudonymous, not authenticated identities. Clearing browser storage, using a private window, or switching browser/device can result in a new visitor ID.

## Assumptions

- This is one sample restaurant with a small, fixed menu; it is not a multi-restaurant ordering platform.
- A `page_view` means the browser tracking code ran for a page load/navigation. It does not necessarily represent a distinct human visit.
- A unique visitor means a distinct anonymous `visitor_id` with at least one page view in the selected date range—not a verified person.
- An order is considered created after the backend validates and saves it. This demo does not model restaurant acceptance, preparation, payment, or fulfillment.
- Event timestamps and dashboard calendar dates use UTC so browser and server events can be compared consistently.

## API and data flow

- `POST /api/events` accepts `{ "events": [...] }`, validates the event data, assigns its source, stores valid events, and returns accepted and duplicate counts. Browser submissions cannot claim the trusted `order_created` event. The endpoint accepts batches of 1–50 events; the SDK currently sends one event per request.
- `GET /api/analytics/summary?from=YYYY-MM-DD&to=YYYY-MM-DD` returns totals, daily activity, popular pages, and event counts for an inclusive UTC date range.
- `GET /api/menu` returns the demo menu and server-owned prices.
- `POST /api/orders` accepts an item ID, quantity, and anonymous visitor/session IDs. Laravel validates the request, calculates the price itself, and returns the created demo order.

When an order is submitted, Laravel writes the order to `orders` and records its trusted `order_created` event in `events` within one database transaction. If either write fails, both are rolled back. The reusable server tracking helper is `App\Services\Analytics\ServerEventTracker`.

## Key decisions and trade-offs

- **Anonymous browser identity:** No accounts or personal information are required, making the demo simple to integrate. The trade-off is that visitor counts are approximate and do not identify a person across browsers or devices.
- **Event IDs and duplicate protection:** `event_id` is the events table primary key. Re-sending an event with the same ID will not create another row. The SDK currently makes a new ID per call and does not persist a retry queue.
- **Server-owned business events:** Laravel, not the browser, records `order_created`, so the event is tied to a successfully processed order. Order and event writes are atomic.
- **Server-owned prices:** The order API looks up the menu price on the server rather than trusting a price sent by the browser.
- **SQLite:** A local SQLite database keeps setup lightweight and is sufficient for this single-instance demonstration. It is not intended as a high-volume, multi-instance analytics store.
- **UTC reporting:** Dashboard date filters use inclusive UTC calendar dates, with a maximum range of 90 days. Unique visitors count distinct anonymous visitor IDs with a `page_view` in that range; daily chart totals include all event types.
- **Trusted API key:** `ANALYTICS_SERVER_KEY` can authorize trusted server event submissions to the event-ingestion API. Never put this key in frontend code; use a non-development key in production. The order flow records its event internally through the server helper.

## Failure handling and security

The API validates event names, identifiers, paths, timestamps, and properties; rejects browser attempts to submit `order_created`; and limits event batches to 50 and serialized properties to 4 KB. Event timestamps must be within the last 30 days and no more than 5 minutes in the future. Laravel applies a per-IP request limit to event ingestion. Invalid requests return an error rather than being treated as successful event delivery.

Public browser events can still be fabricated by clients; the server key protects trusted server event submissions, not public page views. The SDK propagates request failures to its caller and does not currently retry failed requests automatically.

## Known limitations

- Orders are demonstrations only: there is no payment, real restaurant submission, or pickup scheduling.
- The dashboard reports event aggregates; it is not an individual customer order-history page.
- Visitor/session IDs are anonymous and can change when browser storage is cleared or a different browser/device is used.
- Browser ingestion is public and can receive fabricated traffic.
- There is no durable browser delivery queue, automatic retry/backoff, or batch flush in the SDK.
- Retention policies, consent management, bot filtering, authenticated identities, and cross-region/time-zone reporting are out of scope.
- SQLite is intended for a local, single-process demo rather than a high-volume analytics pipeline.

## Tests and further documentation

Run `npm test` to execute the Laravel API tests. The assignment/interview guide contains a Hindi walkthrough, and the project overview contains a longer user guide:

- [Assignment and interview guide](docs/ASSIGNMENT_GUIDE_HI.md)
- [Project overview and user guide](docs/PROJECT_OVERVIEW_AND_USER_GUIDE.md)
