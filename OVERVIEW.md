# Al Bot — Agency Plugin

**Version:** 1.0.0
**Requires:** WordPress 5.8+, PHP 7.4+
**License:** GPL v2
**Text domain:** `al-bot-agency`

> Full-featured AI chat plugin for agencies — unlimited WordPress sites, all 6 AI model integrations, auto-training, smart lead capture, knowledge base uploads, analytics, and white-label bot branding.

## What it is

A white-label AI chatbot plugin built for agencies to resell to their clients. It embeds a floating (or shortcode-embedded) AI chat widget on a WordPress site that is auto-trained on the site's own content — pages/posts, extra URLs, and uploaded files — so it only answers questions about that specific site. It captures visitor leads through natural conversation, escalates to a human when needed, and logs analytics, all configured per-site from a branded admin panel. It supports six different LLM providers so an agency can bring its own API key per client.

## File layout

| File | Role |
|---|---|
| `al-bot-agency-plugin.php` | Main plugin file — activation/DB setup, cron, AJAX handlers, multi-LLM proxy, KB file parsing |
| `admin-page.php` | Admin UI — Settings / Knowledge Base / Analytics / Leads tabs |
| `chat-widget.php` | Mounts the frontend widget (config + nonces only, no KB content) |
| `scraper.php` | Auto-trainer — builds the system prompt from site content |
| `uninstall.php` | Cleanup on plugin removal |
| `assets/js/chat-widget.js` | Frontend widget behavior |
| `assets/js/admin-kb.js` | Knowledge Base admin page behavior |
| `assets/css/chat-widget.css` | Widget styling |
| `assets/images/providers/` | Provider logos (claude, openai, gemini, mistral, llama, deepseek) |
| `ai-logos/` | Source logo assets |

## Multi-LLM support

One active provider at a time, switchable via a provider-chip picker in Settings, each with its own API key + model:

- **Claude (Anthropic)** — default model `claude-sonnet-4-6`
- **OpenAI** — default model `gpt-4o`
- **Gemini** — default model `gemini-2.0-flash`
- **Mistral** — default model `mistral-large-latest`
- **Llama** (via Groq) — default model `llama-3.3-70b-versatile`
- **DeepSeek** — default model `deepseek-chat`

`aichat_get_active_provider()` resolves the current provider; `aichat_proxy_llm` proxies chat requests server-side so the client never sees the system prompt or trained KB content.

## Key features

### AJAX handlers (`al-bot-agency-plugin.php`)
- `aichat_proxy_llm` (public) — proxies chat to the active LLM; builds the system prompt server-side; rate-limited (30 requests / 5 min); caps history to last 6 turns; truncates system prompt to 20,000 chars.
- `aichat_save_lead` (public) — upserts a lead row keyed by `session_id`.
- `aichat_track_message` (public) — logs Q&A pairs + "unanswered" flag to analytics.
- `aichat_train_now` (admin) — manually triggers the scraper.
- `aichat_upload_kb_file` / `aichat_delete_kb_file` (admin) — manage KB files.
- `aichat_save_kb_urls` (admin) — saves extra URLs to scrape (same-domain only).
- `aichat_handoff_notification` (public) — saves lead + emails the site owner on human-handoff request.
- `aichat_get_widget_config` (public) — refreshes nonces/appearance client-side to defeat page-cache staleness (WP Rocket, Cloudflare APO, CDNs).

### Shortcode
`[ai_site_chat]` — renders an inline embed (`#aichat-inline-root`); only renders if the active provider has an API key set.

### Security
- Transient-based per-IP rate limiting (`aichat_rate_limit_check()`) on every public AJAX endpoint.
- Nonces on all AJAX calls.
- KB URLs restricted to same-domain (enforced at both save time and scrape time).
- KB upload directory protected with a per-install random token, `.htaccess deny from all`, and a blank `index.php`.
- Bot responses rendered via `marked.js` then sanitized with `DOMPurify` before `innerHTML` insertion.
- Non-destructive DB migrations on `init` (`aichat_run_migrations()`), including auto-replacement of deprecated/invalid Claude model IDs.

### Export
`admin_post_aichat_export_leads_csv` and `admin_post_aichat_export_leads_pdf` export the leads table.

## Chat widget (frontend)

- No system prompt or KB content ever ships to the browser — only appearance config + nonces (`window.aichatConfig`), printed inline in `wp_footer` at priority 1, with `data-noptimize` / `data-cfasync` to survive script optimizers.
- Mounts to `#aichat-widget-root` (floating) and/or `#aichat-inline-root` (shortcode embed).
- 32-char random session ID per page load; chat history persisted to `localStorage` (`aichat_history`, 1-day TTL).
- **Conversational lead capture** (default mode): the widget opens with a welcome message, and the LLM is instructed via system prompt to naturally ask for name → email → phone → requirement one at a time, backing off if declined twice. Once complete, the model appends a hidden `LEADDATA:{...}` marker to its reply, which the widget JS strips and posts to `aichat_save_lead`. Two alternate modes exist: `gate` (form before chat) and `inline-form`, selected via the `aichat_lead_capture_mode` option.
- **Low-confidence detection** — a hardcoded phrase list (e.g. "i don't know", "not mentioned", "outside the scope") flags weak answers for analytics/handoff.
- **Suggestion chips** — every bot reply ends with a hidden `SUGGESTIONS:["q1","q2","q3"]` line, parsed into clickable follow-ups.
- Human handoff mode, voice mode toggle (`aichat_voice_mode`), light/dark theme, "hide branding" white-label option.
- Dynamic theming: primary color lightened 38 units to build the widget's gradient chrome.
- Loads `marked.js` and `DOMPurify` from jsDelivr CDN.

## Scraper / auto-training (`scraper.php`)

`aichat_run_scraper()` rebuilds the entire system prompt and stores it in `wp_options` (`aichat_system_prompt`), sourced from:

1. **WordPress posts/pages** — all published, non-password-protected; shortcodes rendered, tags stripped, truncated to 800 words each.
2. **Extra KB URLs** (`aichat_kb_urls` option) — fetched via `wp_remote_get`; strips `<script>/<style>/<nav>/<footer>/<header>/<aside>`; truncated to 1000 words; same-domain only.
3. **Uploaded KB files** — read from the `wp_aichat_kb_files` table; truncated to 1500 words each.
4. Returns a `WP_Error` if nothing is found.

Runs daily via a scheduled cron event, and on-demand via the "Train Now" button. Stores `aichat_last_trained` and `aichat_post_count`.

The generated prompt encodes: identity/role/tone (from admin settings), strict "only answer about this site" guardrails, refusal to reveal which AI model powers it, language auto-detection/mirroring, a 2–4 sentence answer-length cap, the `SUGGESTIONS:[...]` footer contract, and the lead-capture instructions.

**Supported KB file types** (parsed in the main plugin file): TXT, CSV (line-by-line, capped 2,000 rows), JSON (pretty-printed), PDF (regex-based text-object extraction, no external library), DOCX/PPTX (via `ZipArchive`, stripping XML from `word/document.xml` / `ppt/slides/slideN.xml`).

## Admin panel (`admin-page.php`)

Menu: **Al Bot Agency** → Settings / Knowledge Base / Analytics / Leads (shared pill-tab nav).

**Settings**
- LLM Model Configuration — provider picker (6 chips), API key + model dropdown per provider.
- Widget Appearance — bot name, emoji or uploaded icon, primary color (WP color picker), welcome message, light/dark theme, lead capture mode, hide-branding checkbox, live preview.
- Bot Personality — role, tone (friendly/professional/casual/formal), personality text, custom instructions.
- Human Handoff — notification email (via `wp_mail`), enable toggle.
- Voice mode toggle.

**Knowledge Base**
Drag-and-drop upload (PDF/TXT/CSV/DOCX/PPTX/JSON, 10MB cap), file list with delete, same-domain URL list, "Train Now" button, last-trained timestamp + indexed item count.

**Analytics**
Tracked question/answer pairs, sortable, with unanswered-question flagging.

**Leads**
Captured leads table with CSV/PDF export.

## Architecture

**Database tables** (via `dbDelta`, versioned by `aichat_db_version`, currently `2`):
- `wp_aichat_leads` — session_id, name, email, phone, requirement, transcript, timestamps.
- `wp_aichat_analytics` — session_id, user_question, bot_answer, is_unanswered, page_url, created_at.
- `wp_aichat_kb_files` — file_name, file_type, file_path, file_size, content, uploaded_at.

**Cron:** `aichat_auto_train_cron` — scheduled daily on activation, cleared on deactivation; runs `aichat_run_scraper()`.

**External services:**
- `api.anthropic.com/v1/messages` (Claude)
- `api.openai.com` (OpenAI)
- `generativelanguage.googleapis.com` (Gemini)
- `api.mistral.ai` (Mistral)
- `api.groq.com` (Llama)
- `api.deepseek.com` (DeepSeek)
- Google Fonts + jsDelivr CDN (marked.js, DOMPurify) on the frontend.

**No REST API routes** — everything runs through `admin-ajax.php` (`wp_ajax_*` / `wp_ajax_nopriv_*`) plus two `admin_post_*` export handlers.

**Uninstall:** removes the plugin's custom tables, options, and uploaded KB directory.
