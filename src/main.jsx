import React, { useEffect, useMemo, useState } from "react";
import { createRoot } from "react-dom/client";
import { createAnalyticsClient } from "./analytics.js";
import { apiUrl } from "./api.js";
import "./styles.css";

const analytics = createAnalyticsClient();
const money = (cents) => `$${(cents / 100).toFixed(2)}`;

function usePageView(path) {
  useEffect(() => {
    analytics.pageView(path).catch((error) => {
      console.error("Page view could not be recorded:", error);
    });
  }, [path]);
}

function Header({ active }) {
  return (
    <header className="site-header">
      <a className="brand" href="/" aria-label="Little Olive home">
        <span className="brand-mark">lo</span>
        <span>LITTLE OLIVE <small>NEIGHBORHOOD KITCHEN</small></span>
      </a>
      <nav aria-label="Main navigation">
        <a className={active === "menu" ? "active" : ""} href="/">The menu</a>
        <a className={active === "dashboard" ? "active" : ""} href="/dashboard">Analytics</a>
      </nav>
      <a className="header-order" href="#menu">ORDER FOR PICKUP <span>↗</span></a>
    </header>
  );
}

function Restaurant() {
  const [menu, setMenu] = useState([]);
  const [itemId, setItemId] = useState("margherita");
  const [quantity, setQuantity] = useState(1);
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");
  usePageView("/");

  useEffect(() => {
    fetch(apiUrl("/api/menu"))
      .then((response) => {
        if (!response.ok) throw new Error("The menu could not be loaded.");
        return response.json();
      })
      .then(setMenu)
      .catch((requestError) => setError(requestError.message));
  }, []);

  async function placeOrder(event) {
    event.preventDefault();
    setMessage("");
    setError("");
    try {
      const response = await fetch(apiUrl("/api/orders"), {
        method: "POST",
        headers: { "content-type": "application/json" },
        body: JSON.stringify({
          item_id: itemId,
          quantity,
          visitor_id: analytics.visitorId,
          session_id: analytics.sessionId
        })
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.error || result.message || "Your order could not be placed.");
      const order = result.order;
      setMessage(`Order ${order.order_id.slice(0, 8)} created — ${money(order.total_cents)}. ${result.analytics === "tracked" ? "It's in the analytics dashboard." : "Order saved; analytics delivery is temporarily unavailable."}`);
    } catch (requestError) {
      setError(requestError.message);
    }
  }

  async function trackMenuInterest(id) {
    try {
      await analytics.event("menu_item_selected", { item_id: id });
    } catch (trackingError) {
      console.error("Menu interaction could not be recorded:", trackingError);
    }
  }

  const selected = menu.find((item) => item.id === itemId);
  return (
    <>
      <Header active="menu" />
      <main>
        <section className="hero">
          <div className="hero-copy">
            <p className="eyebrow">GOOD FOOD, GATHERED CLOSE</p>
            <h1>A little<br /><em>olive</em> branch.</h1>
            <p className="hero-intro">Seasonal Italian cooking, made slowly and shared generously. Your neighborhood table is waiting.</p>
            <a className="button button-dark" href="#menu">EXPLORE THE MENU <span>↓</span></a>
            <p className="hero-note">MADE WITH LOVE IN BROOKLYN, NY</p>
          </div>
          <div className="hero-art" role="img" aria-label="Illustration of a freshly made Italian meal">
            <div className="sun-shape" />
            <div className="plate"><div className="pasta pasta-one" /><div className="pasta pasta-two" /><div className="tomato tomato-one" /><div className="tomato tomato-two" /><div className="leaf leaf-one" /><div className="leaf leaf-two" /><div className="olive olive-one" /><div className="olive olive-two" /></div>
            <span className="art-caption">A GOOD THING, SHARED.</span>
            <span className="art-stamp">FATTO<br />CON<br />AMORE</span>
          </div>
        </section>

        <section className="menu-section" id="menu">
          <div className="section-heading">
            <div><p className="eyebrow">A FEW HOUSE FAVORITES</p><h2>From our kitchen</h2></div>
            <span>FRESHLY MADE, JUST FOR YOU</span>
          </div>
          <div className="menu-layout">
            <div className="menu-list">
              {menu.map((item, index) => (
                <button className={`menu-item ${item.id === itemId ? "selected" : ""}`} key={item.id} onClick={() => { setItemId(item.id); trackMenuInterest(item.id); }}>
                  <span className="menu-number">0{index + 1}</span>
                  <span className="menu-name">{item.name}<small>{["San Marzano tomato, basil, fior di latte", "Slow-braised tomato, parmesan, basil", "Espresso-soaked, just a little sweet"][index]}</small></span>
                  <span className="menu-price">{money(item.price_cents)}</span>
                  <span className="menu-arrow">↗</span>
                </button>
              ))}
            </div>
            <form className="order-card" onSubmit={placeOrder}>
              <p className="eyebrow">A TABLE FOR ONE (OR MORE)</p>
              <h3>Make it yours.</h3>
              <label htmlFor="item">Your pick</label>
              <select id="item" value={itemId} onChange={(event) => { setItemId(event.target.value); trackMenuInterest(event.target.value); }}>
                {menu.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
              </select>
              <label htmlFor="quantity">How many?</label>
              <select id="quantity" value={quantity} onChange={(event) => setQuantity(Number(event.target.value))}>
                {[1, 2, 3, 4, 5, 6, 7, 8, 9, 10].map((number) => <option key={number} value={number}>{number}</option>)}
              </select>
              <button className="button button-olive" type="submit" disabled={!selected}>PLACE PICKUP ORDER <span>↗</span></button>
              {selected && <p className="order-total">TOTAL <strong>{money(selected.price_cents * quantity)}</strong></p>}
              {message && <p className="notice success" role="status">{message}</p>}
              {error && <p className="notice error" role="alert">{error}</p>}
            </form>
          </div>
        </section>
        <footer className="footer"><span>GOOD FOOD. GOOD COMPANY. ALWAYS.</span><a href="/dashboard">See this demo’s analytics ↗</a></footer>
      </main>
    </>
  );
}

function AnalyticsDashboard() {
  const today = new Date().toISOString().slice(0, 10);
  const weekAgo = new Date();
  weekAgo.setUTCDate(weekAgo.getUTCDate() - 6);
  const [from, setFrom] = useState(weekAgo.toISOString().slice(0, 10));
  const [to, setTo] = useState(today);
  const [summary, setSummary] = useState(null);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(true);
  usePageView("/dashboard");

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    fetch(apiUrl(`/api/analytics/summary?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`))
      .then(async (response) => {
        const result = await response.json();
        if (!response.ok) throw new Error(result.error || result.message || "Analytics could not be loaded.");
        return result;
      })
      .then((result) => {
        if (!cancelled) { setSummary(result); setError(""); }
      })
      .catch((requestError) => { if (!cancelled) setError(requestError.message); })
      .finally(() => { if (!cancelled) setLoading(false); });
    return () => { cancelled = true; };
  }, [from, to]);

  const maxEvents = useMemo(() => Math.max(1, ...(summary?.daily.map((day) => day.events) || [])), [summary]);
  return (
    <>
      <Header active="dashboard" />
      <main className="dashboard">
        <div className="dashboard-title">
          <div><p className="eyebrow">LITTLE OLIVE / INSIGHTS</p><h1>A closer look.</h1><p>Everything happening around your neighborhood table.</p></div>
          <div className="date-controls"><label>FROM<input type="date" value={from} max={to} onChange={(event) => setFrom(event.target.value)} /></label><span>—</span><label>TO<input type="date" value={to} min={from} max={today} onChange={(event) => setTo(event.target.value)} /></label></div>
        </div>
        {error && <p className="notice error" role="alert">{error}</p>}
        <div className="metrics">
          <Metric label="PAGE VIEWS" value={summary?.totals.page_views} description="Visits to your pages" loading={loading} />
          <Metric label="UNIQUE VISITORS" value={summary?.totals.unique_visitors} description="Distinct anonymous visitors" loading={loading} />
          <Metric label="ALL EVENTS" value={summary?.totals.total_events} description="Browser + server events" loading={loading} />
        </div>
        <div className="dashboard-grid">
          <section className="panel trend-panel">
            <div className="panel-heading"><div><p className="eyebrow">THE RHYTHM OF THE ROOM</p><h2>Event activity</h2></div><span>EVENTS / DAY</span></div>
            {loading ? <p className="empty">Gathering your numbers…</p> : <div className="chart" role="img" aria-label="Daily event activity bar chart">
              {summary?.daily.map((day) => <div className="chart-column" key={day.day} title={`${day.day}: ${day.events} events, ${day.page_views} page views`}><span className="chart-value">{day.events || ""}</span><div className="bar-track"><div className="bar" style={{ height: `${Math.max(day.events ? 8 : 0, day.events / maxEvents * 100)}%` }} /></div><span className="chart-label">{new Date(`${day.day}T00:00:00Z`).toLocaleDateString("en", { month: "short", day: "numeric", timeZone: "UTC" })}</span></div>)}
            </div>}
            <div className="legend"><span /> ALL CAPTURED EVENTS <span className="legend-muted">PAGE VIEWS INCLUDED</span></div>
          </section>
          <section className="panel pages-panel">
            <div className="panel-heading"><div><p className="eyebrow">WHERE THEY WENT</p><h2>Popular pages</h2></div><span>VIEWS</span></div>
            {loading ? <p className="empty">Gathering your numbers…</p> : summary?.popular_pages.length ? <ol className="ranked-list">{summary.popular_pages.map((page, index) => <li key={page.page_path}><span className="rank">{String(index + 1).padStart(2, "0")}</span><span className="rank-name">{page.page_path}<small>{page.visitors} visitors</small></span><strong>{page.views}</strong></li>)}</ol> : <p className="empty">No page views in this date range yet. Visit the restaurant page to get started.</p>}
          </section>
          <section className="panel events-panel">
            <div className="panel-heading"><div><p className="eyebrow">WHAT’S HAPPENING</p><h2>Event mix</h2></div><span>EVENTS</span></div>
            {loading ? <p className="empty">Gathering your numbers…</p> : summary?.event_counts.length ? <ol className="ranked-list">{summary.event_counts.map((event) => <li key={event.event_name}><span className="event-dot" /><span className="rank-name">{event.event_name}<small>{event.event_name === "order_created" ? "server-side" : event.event_name === "page_view" ? "browser" : "custom"}</small></span><strong>{event.count}</strong></li>)}</ol> : <p className="empty">No events in this date range yet.</p>}
          </section>
        </div>
        <p className="data-note">Visitor IDs are anonymous first-party browser IDs. Server order events use the same visitor and session IDs, so activity can be compared without collecting personal information.</p>
      </main>
      <footer className="footer"><span>GOOD FOOD. GOOD COMPANY. ALWAYS.</span><a href="/">Back to the restaurant ↗</a></footer>
    </>
  );
}

function Metric({ label, value, description, loading }) {
  return <section className="metric-card"><p className="eyebrow">{label}</p><strong>{loading ? "—" : (value ?? 0).toLocaleString()}</strong><span>{description}</span></section>;
}

function App() {
  return window.location.pathname === "/dashboard" ? <AnalyticsDashboard /> : <Restaurant />;
}

createRoot(document.getElementById("root")).render(<App />);
