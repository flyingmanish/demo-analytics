import { apiUrl } from "./api.js";

function getOrCreate(storage, key) {
  const existing = storage.getItem(key);
  if (existing) return existing;
  const value = crypto.randomUUID();
  storage.setItem(key, value);
  return value;
}

export function createAnalyticsClient({ endpoint = apiUrl("/api/events") } = {}) {
  const visitorId = getOrCreate(window.localStorage, "little-olive-visitor");
  const sessionId = getOrCreate(window.sessionStorage, "little-olive-session");

  async function track(eventName, properties = {}, pagePath = null) {
    const response = await fetch(endpoint, {
      method: "POST",
      headers: { "content-type": "application/json" },
      body: JSON.stringify({
        events: [{
          event_id: crypto.randomUUID(),
          event_name: eventName,
          visitor_id: visitorId,
          session_id: sessionId,
          page_path: pagePath,
          properties,
          occurred_at: new Date().toISOString()
        }]
      })
    });
    if (!response.ok) {
      const result = await response.json().catch(() => ({}));
      throw new Error(result.error || `Analytics request failed (${response.status}).`);
    }
    return response.json();
  }

  return {
    visitorId,
    sessionId,
    track,
    pageView(pagePath) {
      return track("page_view", {}, pagePath);
    },
    event(eventName, properties = {}) {
      return track(eventName, properties);
    }
  };
}
