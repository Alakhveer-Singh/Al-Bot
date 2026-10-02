# Implementation Prompt — "Make the Bot Feel Alive" + Visual Chat Replay

Paste this into Claude Code (or any coding agent) with the Al Bot plugin open, and let it implement directly against the existing files.

---

## Context

This is a WordPress plugin (`Al Bot — Agency Plugin`, `al-bot-agency-plugin.php` + `chat-widget.php` + `scraper.php` + `admin-page.php`). The frontend widget lives in `assets/js/chat-widget.js` / `assets/css/chat-widget.css`, mounted into `#aichat-widget-root` / `#aichat-inline-root`. Chat goes through the `aichat_proxy_llm` AJAX action (admin-ajax.php, not REST). Analytics events are logged via `aichat_track_message` into `wp_aichat_analytics`. Session id is a 32-char random string generated client-side per page load and persisted with chat history in `localStorage` under `aichat_history` (1-day TTL).

Implement the following four features. Match the existing code style (vanilla JS, no build step / no framework, WP `wp_ajax_*` conventions, nonce-protected AJAX, prepared statements for all DB access). Keep everything backward compatible — new options must default to "off" so existing installs don't change behavior until an admin opts in.

---

## Feature 1: Proactive Triggers

Goal: the widget can open itself and send a canned opening line based on visitor behavior, instead of only reacting when clicked.

**Admin settings (new "Proactive Triggers" section in Settings tab, `admin-page.php`):**
- Master enable/disable toggle (option: `aichat_proactive_enabled`, default off).
- A repeatable rule list, each rule = `{ trigger_type, condition_value, delay_seconds, message, enabled }`.
- Trigger types to support:
  - `exit_intent` — mouse leaves the top of the viewport (desktop only).
  - `idle_on_page` — visitor has been on the current page N seconds with no scroll/click. `condition_value` = seconds.
  - `scroll_depth` — visitor has scrolled past N% of the page. `condition_value` = percent.
  - `url_contains` — current URL path contains a substring, e.g. `/pricing`. `condition_value` = substring.
  - `time_on_site` — cumulative seconds across the session (not just current page).
- Ship 2 sensible default rules pre-filled (disabled by default) so admins see the shape immediately:
  - exit_intent → "Before you go — can I help you find something?"
  - url_contains `/pricing` + idle 8s → "Questions about pricing? Happy to help."
- Store rules as a JSON-encoded array in one `wp_options` row (`aichat_proactive_rules`), not a new DB table — this stays consistent with how other config is stored.

**Frontend (`chat-widget.js`):**
- On load, if `aichatConfig.proactiveEnabled`, parse the rules and register listeners.
- A rule fires at most once per session (track fired rule IDs in a `Set` in memory + mirror to `sessionStorage` so a page navigation within the same tab doesn't re-fire).
- When a rule fires: open the widget bubble automatically, inject the rule's `message` as a bot message (client-side only — do not round-trip this canned message through the LLM proxy), and play the entrance animation described in Feature "Animations" below.
- Respect the existing widget-open state — don't fire a rule if the user already has the chat open, and never fire a rule twice in the same session even if multiple conditions are met.
- Debounce `idle_on_page` and `scroll_depth` listeners properly (don't run condition checks on every scroll/mousemove tick — throttle to ~200ms).

**Analytics tie-in:** when a proactive rule fires and the visitor actually replies, log it in `wp_aichat_analytics` with a new nullable column `triggered_by` (e.g. `'manual'` vs `'proactive:exit_intent'`) so the admin can see which proactive rules actually start conversations. Add this column via a migration bump in `aichat_run_migrations()` (bump `AICHAT_DB_VERSION` to `'3'`).

---

## Feature 2: Memory Across Visits

Goal: if a visitor already gave their name in a previous session (lead capture already stores name/email/phone against a session), greet them by name on return, without re-asking questions they already answered.

**Approach — no server round trip needed for the common case:**
- When `aichat_save_lead` successfully captures a name, also write it (name only — not email/phone, keep PII minimal on the client) into `localStorage` under a new key `aichat_returning_visitor` as `{ name, lastSeen: timestamp }`, alongside the existing `aichat_history` write. This is separate from `aichat_history` because history has a 1-day TTL and we want the name remembered longer (suggest 90-day TTL for this key).
- On subsequent visits (new session id, since that's generated per page load), if `aichat_returning_visitor` exists and is not expired, the *first* bot message (whether opened manually or via a proactive trigger) should be a client-side templated greeting: `"Welcome back, {name}! " + normal welcome message`, instead of the plain configured welcome message. This substitution happens purely in `chat-widget.js` — no LLM call needed for the greeting itself.
- Once the visitor sends their first real message of the new session, the *existing* lead-capture flow should still work normally, but the system prompt sent to the LLM (server-side, in `aichat_proxy_llm`) should receive a flag like `returning_visitor_name` so the model doesn't ask for the name again if it's already known. Concretely: if the client has a known name, include `[KNOWN_VISITOR_NAME: {name}]` as a hidden system-level context line appended server-side (never trust the client-sent name for anything except this — always re-verify against the `wp_aichat_leads` table by matching browser fingerprint/session lineage if you want stronger guarantees, but for v1 client-supplied name is acceptable since it's low-stakes personalization, not auth).
- Do not expose email/phone back to the client at any point — those never leave the server after being submitted.

**Edge case:** if `localStorage` is unavailable (private browsing) or empty, fall back to current behavior exactly as-is.

---

## Feature 3: Streaming Responses

Goal: replace "wait for full reply, then render" with token-by-token streaming, like a modern chat UI.

**Server side (`aichat_proxy_llm` in `al-bot-agency-plugin.php`):**
- Each of the 6 providers (Anthropic, OpenAI, Gemini, Mistral, Groq/Llama, DeepSeek) supports SSE streaming on their chat completion endpoints. Add a `stream: true` parameter to the outbound API request for the active provider, and instead of using `wp_remote_post` (which buffers the whole response), use a raw `curl` handle (or `WP_Http` with a streaming-capable transport) with `CURLOPT_WRITEFUNCTION` to relay each SSE chunk to the browser as it arrives, flushing PHP's output buffer after each chunk (`ob_flush(); flush();`).
- This bypasses the normal `wp_send_json_success` pattern — the endpoint needs to send `Content-Type: text/event-stream` and stream raw chunks instead of one JSON blob. Keep the *existing* non-streaming code path available behind a feature flag (`aichat_streaming_enabled` option, default off initially) in case a host's server config doesn't support flushing (some PHP-FPM/Nginx buffering setups will fully buffer regardless — document this as a known limitation in the Settings UI next to the toggle).
- Rate limiting, nonce checks, message-history capping, and system-prompt truncation all stay exactly as they are today — streaming only changes how the response body is delivered, not the request validation.
- The hidden `LEADDATA:{...}` and `SUGGESTIONS:[...]` markers still need to arrive as part of the streamed text; the client parses them once the stream signals completion (don't try to detect them mid-stream, since they can be split across chunks).

**Client side (`chat-widget.js`):**
- Use `fetch()` with a `ReadableStream` reader (not `XMLHttpRequest`) against the same `admin-ajax.php` endpoint, reading and appending chunks to the current bot message bubble as they arrive.
- Render the currently-streaming bubble with a blinking cursor / typing caret at the end of the text (CSS-only, see Animations section).
- On stream completion: strip `LEADDATA:`/`SUGGESTIONS:` markers from the final rendered text exactly as today, run the final text through `marked.js` + `DOMPurify` once (not per-chunk — running a full markdown parse on every token is wasteful; do a lightweight incremental text append during streaming, then swap in the fully-parsed+sanitized markdown only once the stream ends).
- If streaming is disabled (flag off) or the fetch/stream fails partway, fall back to the current full-response behavior — never leave the user with a half-rendered message and no error state.

---

## Feature 4: Animations

Goal: the widget should feel responsive and alive, not static. Pure CSS, no animation library — keep the plugin dependency-free.

Add to `assets/css/chat-widget.css`:
- **Widget launcher idle pulse** — a subtle recurring pulse/glow ring on the closed chat bubble icon (`@keyframes aichat-pulse`, ~2.5s loop, opacity/scale, respecting `prefers-reduced-motion: reduce` by disabling it entirely for those users).
- **Open/close transition** — the widget panel should scale+fade in from the launcher's position (`transform-origin` set to the bubble's corner), not just appear/disappear. ~200ms ease-out.
- **Proactive message entrance** — when a proactive trigger (Feature 1) fires, the launcher icon should do a distinct "notification" bounce (short scale bounce, 2 reps) and a small unread-dot badge should appear, separate from the idle pulse, so it reads as "something happened" vs. ambient idle state.
- **Message bubble entrance** — each new message (bot or user) slides up + fades in (~150ms) as it's appended, rather than popping in instantly.
- **Typing indicator** — three-dot bouncing indicator (staggered `animation-delay` per dot) shown while waiting for the first streamed token; replaced by the streaming text + blinking caret once the first chunk arrives.
- **Suggestion chip stagger** — when `SUGGESTIONS:[...]` chips render, stagger their entrance by ~40ms each instead of all popping in simultaneously.

All animations must respect `@media (prefers-reduced-motion: reduce)` — provide a reduced/instant variant, don't just disable everything (keep opacity fades, drop scale/bounce/pulse motion).

---

## Feature 5: Visual Chat Replay (Analytics tab, `admin-page.php`)

Goal: instead of the current flat Q&A analytics rows, let an admin open a session and watch the actual conversation as a real chat transcript.

**Data availability check first:** confirm whether `wp_aichat_analytics` currently stores enough to reconstruct a full ordered conversation per `session_id` (question + answer per row, with timestamps). If ordering/completeness is insufficient, add a `sequence` int column via the same migration bump used in Feature 1, incremented per message within a session by `aichat_track_message`.

**New "Analytics" behavior:**
- The existing Analytics table gains a new leftmost column/button: "View Conversation" per unique `session_id`.
- Clicking it opens a modal (reuse the plugin's existing modal/lightbox pattern if `admin-kb.js` already has one; otherwise add a minimal vanilla-JS modal — no new dependency) that fetches all rows for that `session_id` via a new AJAX action `aichat_get_session_transcript` (admin-only, nonce-protected, capability-checked with `current_user_can('manage_options')`).
- Render the transcript **visually like the actual widget** — reuse `chat-widget.css` bubble styles (bot bubble left-aligned with bot avatar/icon, visitor bubble right-aligned) so it looks like a real replay of the conversation, not a plain table. Include timestamps per message (relative, e.g. "2m apart") and flag any message that was marked `is_unanswered` with a small warning badge inline.
- If that session also has a matching row in `wp_aichat_leads` (same `session_id`), show the captured lead info (name/email/phone/requirement) as a small summary card pinned above the transcript.
- If the analytics row has a `triggered_by` value from Feature 1 (e.g. `proactive:exit_intent`), show a small badge at the top of the transcript: "Started via proactive trigger: Exit Intent".
- Add a simple "Export transcript" button per modal that reuses the existing CSV export pattern (`admin_post_*` handler) scoped to that one session_id.

**Performance:** paginate/cap at a reasonable number of messages per modal load (e.g. most recent 200) and lazy-load the transcript only when the modal opens (don't fetch all sessions' full transcripts on page load — the Analytics list view should stay lightweight, just session_id + summary counts + the button).

---

## Delivery expectations

- Bump plugin version and add a changelog entry at the top of `al-bot-agency-plugin.php`'s header comment.
- All new `wp_options` should have safe defaults registered on activation (extend the existing activation hook) so a fresh install doesn't have undefined option warnings.
- All new AJAX actions need nonce verification + the existing rate-limit helper (`aichat_rate_limit_check()`) applied consistently with how current public endpoints do it.
- Test manually: toggle each new feature off and confirm the plugin behaves identically to before this change (nothing here should be on by default except the animations, which are cosmetic and safe to ship enabled).
