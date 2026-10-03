/**
 * AI Site Chat — Frontend Widget v2.0
 *
 * Handles: floating + inline modes, chat UI,
 * multi-LLM API calls, quick-reply chips, typing animation,
 * conversational lead capture, analytics tracking,
 * language auto-detect, notification dot.
 *
 * Lead capture flow (NO upfront gate):
 *   - Chat opens immediately with welcome message.
 *   - Bot is instructed (via system prompt) to ask for name / email /
 *     phone / requirement naturally during the conversation.
 *   - When the bot has all four it appends a hidden marker:
 *       LEADDATA:{"name":"...","email":"...","phone":"...","requirement":"..."}
 *   - JS strips that marker, saves the lead via AJAX, and stops asking.
 */
(function () {
  'use strict';

  if (!window.aichatConfig) return;
  var cfg = window.aichatConfig;

  /* ── Session ID ─────────────────────────────────────────────────────────── */

  function generateSessionId() {
    var chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    var id = '';
    for (var i = 0; i < 32; i++) {
      id += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return id;
  }

  var sessionId = generateSessionId();

  /* ── Low-confidence detection ───────────────────────────────────────────── */

  var LOW_CONF_PHRASES = [
    "i don't have information",
    "i don't know",
    "i'm not sure",
    "i cannot find",
    "not mentioned",
    "not in the",
    "i'm unable to",
    "outside the scope",
    "i apologize",
    "i don't have specific",
    "i couldn't find",
    "no information",
    "i have no information",
    "beyond my knowledge",
    "not provided in",
    "only answer questions",
    "not able to assist",
    "can't help with",
    "unable to assist",
    "feel free to reach out",
  ];

  function isLowConfidence(text) {
    var lower = text.toLowerCase();
    for (var i = 0; i < LOW_CONF_PHRASES.length; i++) {
      if (lower.indexOf(LOW_CONF_PHRASES[i]) !== -1) return true;
    }
    return false;
  }

  /* ── Colour helpers ─────────────────────────────────────────────────────── */

  function lightenHex(hex, amount) {
    hex = hex.replace('#', '');
    if (hex.length === 3) {
      hex = hex.split('').map(function (c) { return c + c; }).join('');
    }
    var num = parseInt(hex, 16);
    var r   = Math.min(255, (num >> 16)         + amount);
    var g   = Math.min(255, ((num >> 8) & 0xff) + amount);
    var b   = Math.min(255, (num & 0xff)        + amount);
    return '#' + ((1 << 24) | (r << 16) | (g << 8) | b).toString(16).slice(1);
  }

  var primary, secondary, gradient;
  function computeColors() {
    primary   = cfg.primaryColor || '#22c55e';
    secondary = lightenHex(primary, 38);
    gradient  = 'linear-gradient(135deg, ' + primary + ', ' + secondary + ')';
  }
  computeColors();

  /* ── Chat history persistence (localStorage) ───────────────────────────── */

  var STORAGE_KEY = 'aichat_history';
  var STORAGE_TTL = 1 * 24 * 60 * 60 * 1000; // 1 day

  function saveHistory(state) {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify({
        ts:              Date.now(),
        allMessages:     state.allMessages,
        apiMessages:     state.apiMessages,
        userName:        state.userName,
        userEmail:       state.userEmail,
        userPhone:       state.userPhone,
        userRequirement: state.userRequirement,
        leadCollected:   state.leadCollected,
        leadCaptured:    state.leadCaptured,
        leadData:        state.leadData,
        leadSaved:       state.leadSaved,
      }));
    } catch (e) {}
  }

  function loadHistory() {
    try {
      var raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) return null;
      var data = JSON.parse(raw);
      if (!data || !data.ts || Date.now() - data.ts > STORAGE_TTL) {
        localStorage.removeItem(STORAGE_KEY);
        return null;
      }
      return data;
    } catch (e) { return null; }
  }

  /* ── Returning visitor memory ───────────────────────────────────────────────
     Chat history lives in aichat_history on a 1-day TTL. A remembered NAME is
     worth keeping much longer than a transcript, so it gets its own key with
     its own TTL. Name only — email and phone are submitted to the server and
     never come back to the browser. */

  var RETURNING_KEY = 'aichat_returning_visitor';
  var RETURNING_TTL = 90 * 24 * 60 * 60 * 1000; // 90 days

  function rememberVisitorName(name) {
    if (!name || typeof name !== 'string') return;
    try {
      localStorage.setItem(RETURNING_KEY, JSON.stringify({
        name: String(name).slice(0, 80),
        lastSeen: Date.now(),
      }));
    } catch (e) {}
  }

  /* Returns the remembered name, or '' when unavailable/expired/absent —
     private browsing throws on localStorage, and that falls back to exactly
     the pre-1.1 behaviour. */
  function getRememberedName() {
    try {
      var raw = localStorage.getItem(RETURNING_KEY);
      if (!raw) return '';
      var data = JSON.parse(raw);
      if (!data || !data.name || !data.lastSeen) return '';
      if (Date.now() - data.lastSeen > RETURNING_TTL) {
        localStorage.removeItem(RETURNING_KEY);
        return '';
      }
      return String(data.name);
    } catch (e) { return ''; }
  }

  var returningName = getRememberedName();

  /* ── Shared state ───────────────────────────────────────────────────────── */

  function makeState() {
    var saved = loadHistory();
    return {
      isOpen:          false,
      hasOpened:       false,
      userName:        saved ? (saved.userName        || '') : '',
      userEmail:       saved ? (saved.userEmail       || '') : '',
      userPhone:       saved ? (saved.userPhone       || '') : '',
      userRequirement: saved ? (saved.userRequirement || '') : '',
      leadCollected:   saved ? (saved.leadCollected   || saved.leadCaptured || false) : false,
      leadCaptured:    saved ? (saved.leadCaptured    || saved.leadCollected || false) : false,
      leadData:        saved ? (saved.leadData        || null) : null,
      leadSaved:       saved ? (saved.leadSaved       || false) : false,
      apiMessages:     saved ? (saved.apiMessages     || []) : [],
      allMessages:     saved ? (saved.allMessages     || []) : [],
      isTyping:        false,
      // How this conversation started — 'manual' until a proactive rule fires.
      triggeredBy:     'manual',
      // A proactive rule's message, waiting in the teaser bubble to be
      // carried into the chat the moment it's opened.
      pendingProactiveMessage: null,
      // ── Handoff state ──
      handoffMode:            false,
      handoffStep:            '',
      handoffName:            '',
      handoffPhone:           '',
      handoffEmail:           '',
      handoffTriggerQuestion: '',
      handoffDone:            false,
      handoffStepAttempts:    0,
      handoffLastNameAttempt: '',
      pendingMessage: '',
      // ── WhatsApp exit prompt ──
      whatsappPromptShown: false,
      whatsappMode:        false,
      whatsappNumber:      '',
      _pendingClose:       null,
    };
  }

  /* ── Utility ────────────────────────────────────────────────────────────── */

  function escHtml(str) {
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(String(str || '')));
    return d.innerHTML;
  }

  /* Accepts international formats — an optional leading "+" followed by
     7-15 digits (E.164-ish) — rather than requiring exactly 10 digits, which
     locked out visitors outside the US/India or anyone entering a country code. */
  function isValidPhone(phone) {
    return /^\+?\d{7,15}$/.test(phone);
  }

  /* Sanitize HTML before it's ever assigned via innerHTML. marked.js renders
     whatever text the LLM returns — without this, a crafted bot response
     (e.g. an <img onerror> tag) could run script on the page. */
  function sanitizeHtml(html) {
    if (window.DOMPurify && typeof window.DOMPurify.sanitize === 'function') {
      return window.DOMPurify.sanitize(html);
    }
    // DOMPurify failed to load — fail safe by escaping instead of trusting raw HTML.
    return escHtml(html).replace(/\n/g, '<br>');
  }

  /* ── SVG icons ──────────────────────────────────────────────────────────── */

  function iconChat() {
    return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 2H4C2.9 2 2 2.9 2 4V22L6 18H20C21.1 18 22 17.1 22 16V4C22 2.9 21.1 2 20 2ZM20 16H5.17L4 17.17V4H20V16Z" fill="white"/></svg>';
  }
  function iconClose() {
    return '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M12 4L4 12M4 4L12 12" stroke="white" stroke-width="2" stroke-linecap="round"/></svg>';
  }
  function iconCloseAlt() {
    return '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M15 5L5 15M5 5L15 15" stroke="white" stroke-width="2" stroke-linecap="round"/></svg>';
  }
  function iconSend() {
    return '<svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true"><path d="M16.5 1.5L1.5 9L7.5 10.5L9 16.5L16.5 1.5Z" stroke="white" stroke-width="1.8" stroke-linejoin="round" fill="none"/></svg>';
  }

  /* Bot icon helpers — use uploaded image when available, else emoji/SVG */
  function botAvatarHTML() {
    if (cfg.botIconUrl) {
      return '<img src="' + cfg.botIconUrl + '" alt="" style="width:100%;height:100%;border-radius:50%;object-fit:cover;" aria-hidden="true">';
    }
    return escHtml(cfg.botEmoji);
  }
  function botToggleIconHTML() {
    if (cfg.botIconUrl) {
      return '<img src="' + cfg.botIconUrl + '" alt="" style="width:32px;height:32px;border-radius:50%;object-fit:cover;" aria-hidden="true">';
    }
    return iconChat();
  }

  /* ── Widget HTML builder ────────────────────────────────────────────────── */

  function buildHeader(closeId) {
    return (
      '<div class="aichat-header">' +
        '<div class="aichat-header-info">' +
          '<div class="aichat-header-avatar">' + botAvatarHTML() + '</div>' +
          '<div>' +
            '<div class="aichat-header-name">' + escHtml(cfg.botName) + '</div>' +
            '<div class="aichat-header-status">Online</div>' +
          '</div>' +
        '</div>' +
        '<button id="' + closeId + '" class="aichat-close-btn" aria-label="Close chat">' + iconClose() + '</button>' +
      '</div>'
    );
  }

  // Functions, not values computed once at parse time — cfg.leadCaptureMode
  // can change after refreshWidgetConfig() runs (see Init below), and these
  // must reflect whatever it is at the moment the widget actually builds.
  function isGateMode() { return cfg.leadCaptureMode === 'gate'; }
  function isInlineFormMode() { return cfg.leadCaptureMode === 'inline-form'; }

  /* Session / localStorage gate check */
  var GATE_PASSED_KEY  = 'aichat_gate_passed';
  function gateAlreadyPassed(state) {
    try { if (sessionStorage.getItem(GATE_PASSED_KEY) === '1') return true; } catch (e) {}
    if (state && state.leadCollected) return true;
    if (state && state.userName && state.userEmail && state.userPhone) return true;
    return false;
  }
  function syncLeadContext(state) {
    if (!state) return;
    var hasContact = !!(state.userName || state.userEmail || state.userPhone || state.userRequirement);
    state.leadCaptured = !!(state.leadCaptured || state.leadCollected || (state.userName && state.userEmail && state.userPhone));
    if (state.leadCaptured || hasContact) {
      state.leadData = {
        name:        state.userName        || '',
        phone:       state.userPhone       || '',
        email:       state.userEmail       || '',
        requirement: state.userRequirement || '',
      };
    }
  }

  function markLeadCaptured(state, data) {
    if (!state) return;
    data = data || {};
    state.userName        = data.name        || state.userName        || '';
    state.userPhone       = data.phone       || state.userPhone       || '';
    state.userEmail       = data.email       || state.userEmail       || '';
    state.userRequirement = data.requirement || state.userRequirement || '';
    state.leadCollected   = true;
    state.leadCaptured    = true;
    syncLeadContext(state);
  }

  function buildLeadContextMessage(state) {
    if (!state) return '';
    syncLeadContext(state);
    if (!state.leadCaptured || !state.leadData) return '';
    var ld = state.leadData;
    return 'Lead form already completed. User provided: ' +
      'Name: ' + (ld.name || '(not provided)') + ', ' +
      'Phone: ' + (ld.phone || '(not provided)') + ', ' +
      'Email: ' + (ld.email || '(not provided)') + '. ' +
      (ld.requirement ? 'Requirement: ' + ld.requirement + '. ' : '') +
      'Do NOT ask for these details again.';
  }

  function markGatePassed() {
    try { sessionStorage.setItem(GATE_PASSED_KEY, '1'); } catch (e) {}
  }

  /* Build window: always include BOTH gate + chat so all elements exist in DOM from the start.
     Visibility is controlled with display style, never innerHTML replacement. */
  function buildWindowHTML(prefix, showGate) {
    var gateDisplay = showGate ? '' : 'display:none;';
    var chatDisplay = showGate ? 'display:none;' : '';
    return (
      '<div id="' + prefix + 'aichat-window" class="aichat-window aichat-theme-' + (cfg.widgetTheme || 'light') + '" role="dialog" aria-modal="true" aria-label="' + escHtml(cfg.botName) + ' Chat" aria-hidden="true">' +
        /* Gate panel */
        '<div id="' + prefix + 'aichat-gate" class="aichat-gate" style="' + gateDisplay + '">' +
          buildHeader(prefix + 'aichat-close-gate') +
          '<div class="aichat-gate-body">' +
            '<div class="aichat-gate-avatar">' + botAvatarHTML() + '</div>' +
            '<h2 class="aichat-gate-title">Chat with ' + escHtml(cfg.botName) + '</h2>' +
            '<p class="aichat-gate-subtitle">Please share a few details to get started.</p>' +
            '<form id="' + prefix + 'aichat-gate-form" class="aichat-gate-form" novalidate>' +
              '<input type="text"  id="' + prefix + 'aichat-gate-name"  class="aichat-input" placeholder="Your Name *"      autocomplete="name"  />' +
              '<input type="email" id="' + prefix + 'aichat-gate-email" class="aichat-input" placeholder="Email Address *"  autocomplete="email" />' +
              '<input type="tel"   id="' + prefix + 'aichat-gate-phone" class="aichat-input" placeholder="Phone Number *"   autocomplete="tel"   />' +
              '<div class="aichat-error-msg" id="' + prefix + 'aichat-gate-error"></div>' +
              '<button type="submit" class="aichat-btn-primary">Start Chat &#8594;</button>' +
            '</form>' +
          '</div>' +
        '</div>' +
        /* Chat panel */
        '<div id="' + prefix + 'aichat-chat" class="aichat-chat" style="' + chatDisplay + '">' +
          buildHeader(prefix + 'aichat-close-chat') +
          '<div id="' + prefix + 'aichat-messages" class="aichat-messages" role="log" aria-live="polite" aria-label="Chat messages"></div>' +
          '<div id="' + prefix + 'aichat-quick-replies" class="aichat-quick-replies" aria-label="Quick reply suggestions"></div>' +
          '<div class="aichat-input-bar">' +
            '<input type="text" id="' + prefix + 'aichat-msg-input" class="aichat-msg-input" placeholder="Type a message\u2026" aria-label="Type your message" autocomplete="off" />' +
            '<button id="' + prefix + 'aichat-send" class="aichat-send-btn" aria-label="Send">' + iconSend() + '</button>' +
          '</div>' +
          brandingHTML() +
        '</div>' +
      '</div>'
    );
  }

  /* "White-Label Branding" setting (aichat_hide_branding) \u2014 omit the footer
     entirely when hidden, rather than just hiding it with CSS, so agencies
     running white-label never ship an "Orate" mention to their clients. */
  function brandingHTML() {
    if (cfg.hideBranding) return '';
    return '<div class="aichat-branding">Powered by <strong>Orate</strong></div>';
  }

  /* Show chat panel, hide gate panel — no innerHTML replacement needed */
  function revealChat(state, prefix) {
    var gate = document.getElementById(prefix + 'aichat-gate');
    var chat = document.getElementById(prefix + 'aichat-chat');
    if (gate) gate.style.display = 'none';
    if (chat) chat.style.display = '';
    if (state.allMessages.length > 0) {
      restoreMessages(state, prefix);
    } else {
      showWelcome(state, prefix);
    }
    setTimeout(function () {
      var el = document.getElementById(prefix + 'aichat-msg-input');
      if (el) el.focus();
    }, 80);
  }

  /* ── FLOATING WIDGET ────────────────────────────────────────────────────── */

  function buildFloatingWidget() {
    var root = document.getElementById('aichat-widget-root');
    if (!root) return;

    root.style.setProperty('--aichat-primary',   primary);
    root.style.setProperty('--aichat-secondary', secondary);
    root.style.setProperty('--aichat-gradient',  gradient);

    var state    = makeState();
    var showGate = isGateMode() && !gateAlreadyPassed(state);

    root.innerHTML =
      '<button id="aichat-toggle" class="aichat-toggle-btn" aria-label="Open chat" aria-expanded="false">' +
        '<span class="aichat-toggle-icon" id="aichat-toggle-icon">' + botToggleIconHTML() + '</span>' +
        '<span class="aichat-notif-dot" id="aichat-notif-dot"></span>' +
      '</button>' +
      /* Proactive teaser — a small standalone callout, not the chat window
         itself. Shown instead of opening the panel when a rule fires; opening
         it (or the launcher, while it's showing) carries its message into the
         chat. See showProactiveTeaser() / openFloatingHandlingProactive(). */
      '<div id="aichat-teaser" class="aichat-teaser" role="status" aria-live="polite" hidden>' +
        '<button type="button" id="aichat-teaser-close" class="aichat-teaser-close" aria-label="Dismiss">&times;</button>' +
        '<div class="aichat-teaser-text" id="aichat-teaser-text"></div>' +
      '</div>' +
      buildWindowHTML('', showGate);

    /* All event bindings happen here — gate + chat elements both exist in DOM */
    bindGateForm(state, '');
    bindChatEvents(state, '');

    document.getElementById('aichat-toggle').addEventListener('click', function () {
      if (state.isOpen) closeFloating(state); else openFloatingHandlingProactive(state);
    });

    var teaserEl       = document.getElementById('aichat-teaser');
    var teaserCloseBtn = document.getElementById('aichat-teaser-close');
    if (teaserCloseBtn) {
      teaserCloseBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        hideProactiveTeaser();
        // The pending message and unread dot stay — dismissing the teaser
        // only closes the callout, it doesn't discard the trigger. Clicking
        // the launcher afterward still opens the chat with the message.
      });
    }
    if (teaserEl) {
      teaserEl.addEventListener('click', function () {
        if (state.isOpen) return;
        openFloatingHandlingProactive(state);
      });
    }
    window.addEventListener('beforeunload', function () {
      if ((state.allMessages.length > 0 || state.userName) && !state.leadSaved) sendBeacons(state);
    });

    registerProactiveRules(state);
  }

  /* Bind gate form — always called; does nothing if gate panel not present / not in gate mode */
  function bindGateForm(state, prefix) {
    var form  = document.getElementById(prefix + 'aichat-gate-form');
    var errEl = document.getElementById(prefix + 'aichat-gate-error');
    var closeBtn = document.getElementById(prefix + 'aichat-close-gate');

    if (closeBtn) {
      closeBtn.addEventListener('click', function () {
        if (prefix === '') closeFloating(state);
        else {
          var win = document.getElementById(prefix + 'aichat-window');
          if (win) win.classList.remove('aichat-window--open');
        }
      });
    }

    if (!form) return;

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var name  = (document.getElementById(prefix + 'aichat-gate-name')  || {}).value || '';
      var email = (document.getElementById(prefix + 'aichat-gate-email') || {}).value || '';
      var phone = (document.getElementById(prefix + 'aichat-gate-phone') || {}).value || '';
      name  = name.trim();
      email = email.trim();
      phone = phone.trim();

      if (!name || !email || !phone) {
        if (errEl) errEl.textContent = 'Please fill in all fields.';
        return;
      }
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        if (errEl) errEl.textContent = 'Please enter a valid email address.';
        return;
      }
      if (!isValidPhone(phone)) {
        if (errEl) {
          if (/[^0-9+]/.test(phone)) {
            errEl.textContent = 'Phone number must contain only digits (and an optional leading +).';
          } else {
            errEl.textContent = 'Phone number must be 7 to 15 digits.';
          }
        }
        return;
      }
      if (errEl) errEl.textContent = '';

      markLeadCaptured(state, { name: name, email: email, phone: phone });

      // Wipe the lead-collection thread from API history so the LLM
      // never sees "And your email?" messages again and stops asking.
      state.apiMessages = [];

      saveLead(state, false);
      saveHistory(state);
      markGatePassed();

      revealChat(state, prefix);
    });
  }

  /* Bind chat send/close — always called; elements always exist in DOM */
  function bindChatEvents(state, prefix) {
    var sendBtn  = document.getElementById(prefix + 'aichat-send');
    var input    = document.getElementById(prefix + 'aichat-msg-input');
    var closeBtn = document.getElementById(prefix + 'aichat-close-chat');

    if (sendBtn) sendBtn.addEventListener('click', function () { sendMessage(state, prefix); });
    if (input)   input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(state, prefix); }
    });
    if (closeBtn) closeBtn.addEventListener('click', function () {
      if (state.allMessages.length > 0 && !state.leadSaved) saveLead(state, true);

      // Show WhatsApp prompt before closing (only once, only if there was a conversation)
      if (state.allMessages.length > 0 && !state.whatsappPromptShown) {
        var doClose = function () {
          if (prefix === '') closeFloating(state);
          else {
            var win = document.getElementById(prefix + 'aichat-window');
            if (win) win.classList.remove('aichat-window--open');
          }
        };
        triggerWhatsAppPrompt(state, prefix, doClose);
        return;
      }

      if (prefix === '') closeFloating(state);
      else {
        var win = document.getElementById(prefix + 'aichat-window');
        if (win) win.classList.remove('aichat-window--open');
      }
    });
  }

  /* Open the widget, then — if a proactive message is waiting in the teaser —
     carry it into the chat as the first bot bubble and clear the teaser.
     Used by both the launcher click and a click on the teaser itself, so the
     message lands the same way regardless of which one the visitor clicks. */
  function openFloatingHandlingProactive(state) {
    openFloating(state);
    hideProactiveTeaser();

    if (state.pendingProactiveMessage) {
      var msg = state.pendingProactiveMessage;
      state.pendingProactiveMessage = null;
      renderBotBubble(state, '', msg, []);
      state.allMessages.push({ role: 'assistant', content: msg });
      state.apiMessages.push({ role: 'assistant', content: msg });
      saveHistory(state);
    }
  }

  function showProactiveTeaser(rule) {
    var teaser = document.getElementById('aichat-teaser');
    var textEl = document.getElementById('aichat-teaser-text');
    if (!teaser || !textEl) return;
    textEl.textContent = rule.message;
    teaser.hidden = false;
  }

  function hideProactiveTeaser() {
    var teaser = document.getElementById('aichat-teaser');
    if (teaser) teaser.hidden = true;
  }

  function openFloating(state) {
    state.isOpen = true;
    var win = document.getElementById('aichat-window');
    win.classList.add('aichat-window--open');
    win.setAttribute('aria-hidden', 'false');
    document.getElementById('aichat-toggle').setAttribute('aria-expanded', 'true');
    var dot = document.getElementById('aichat-notif-dot');
    if (dot) dot.style.display = 'none';
    clearProactiveNotice();

    if (!state.hasOpened) {
      state.hasOpened = true;
      /* Only load chat history / welcome if chat panel is currently visible */
      var chatEl = document.getElementById('aichat-chat');
      var gateEl = document.getElementById('aichat-gate');
      var gateVisible = gateEl && gateEl.style.display !== 'none';
      if (!gateVisible) {
        if (state.allMessages.length > 0) {
          restoreMessages(state, '');
        } else {
          showWelcome(state, '');
        }
      }
    }

    document.getElementById('aichat-toggle-icon').innerHTML = iconCloseAlt();
    // Hide toggle button on mobile so it doesn't overlap the send button
    if (window.innerWidth <= 600) {
      var toggleBtn = document.getElementById('aichat-toggle');
      if (toggleBtn) toggleBtn.style.display = 'none';
    }
    setTimeout(function () {
      var el = document.getElementById('aichat-gate-name') || document.getElementById('aichat-msg-input');
      if (el) el.focus();
    }, 260);
  }

  function closeFloating(state) {
    state.isOpen = false;
    var win = document.getElementById('aichat-window');
    win.classList.remove('aichat-window--open');
    win.setAttribute('aria-hidden', 'true');
    document.getElementById('aichat-toggle').setAttribute('aria-expanded', 'false');
    document.getElementById('aichat-toggle-icon').innerHTML = botToggleIconHTML();
    // Restore toggle button visibility on mobile
    if (window.innerWidth <= 600) {
      var toggleBtn = document.getElementById('aichat-toggle');
      if (toggleBtn) toggleBtn.style.display = '';
    }
  }

  /* ══════════════════════════════════════════════════════════════════════════
     PROACTIVE TRIGGERS

     Rules configured in Settings can open the widget and drop in a canned
     opening line based on visitor behaviour. Everything here is inert unless
     cfg.proactiveEnabled is true and at least one rule is enabled, so an
     install that never opts in behaves exactly as it did before.

     The canned message is injected client-side — it is NOT round-tripped
     through the LLM proxy, so a trigger costs no API call.

     Firing policy:
       - a rule fires at most once per session (ids mirrored to sessionStorage,
         so navigating within the tab doesn't re-fire it);
       - at most ONE proactive message per session overall, even when several
         rules qualify — two unprompted pop-ups in one visit reads as spam;
       - never while the visitor already has the chat open, and never after
         they've opened it themselves.
  ══════════════════════════════════════════════════════════════════════════ */

  var PROACTIVE_FIRED_KEY = 'aichat_proactive_fired';
  var TIME_ON_SITE_KEY    = 'aichat_time_on_site';
  var THROTTLE_MS         = 200;

  var firedRuleIds  = new Set();
  var proactiveDone = false;   // One proactive message per session, total.
  var proactiveTimers = [];

  /* Rule ids that already fired, restored from sessionStorage on load. */
  function loadFiredRuleIds() {
    try {
      var raw = sessionStorage.getItem(PROACTIVE_FIRED_KEY);
      if (!raw) return;
      var ids = JSON.parse(raw);
      if (Array.isArray(ids)) {
        ids.forEach(function (id) { firedRuleIds.add(String(id)); });
        if (ids.length) proactiveDone = true;
      }
    } catch (e) {}
  }

  function persistFiredRuleIds() {
    try {
      sessionStorage.setItem(PROACTIVE_FIRED_KEY, JSON.stringify(Array.from(firedRuleIds)));
    } catch (e) {}
  }

  /* Leading-edge throttle — scroll and mousemove fire far too often to run a
     condition check on every tick. */
  function throttle(fn, wait) {
    var last = 0;
    var pending = null;
    return function () {
      var now = Date.now();
      var args = arguments;
      var self = this;
      if (now - last >= wait) {
        last = now;
        fn.apply(self, args);
      } else if (!pending) {
        pending = setTimeout(function () {
          pending = null;
          last = Date.now();
          fn.apply(self, args);
        }, wait - (now - last));
      }
    };
  }

  function isDesktopViewport() {
    return window.innerWidth > 768 && !('ontouchstart' in window);
  }

  /* Cumulative seconds across the session, not just this page. */
  function readTimeOnSite() {
    try {
      var v = parseInt(sessionStorage.getItem(TIME_ON_SITE_KEY), 10);
      return isNaN(v) ? 0 : v;
    } catch (e) { return 0; }
  }

  function writeTimeOnSite(seconds) {
    try { sessionStorage.setItem(TIME_ON_SITE_KEY, String(seconds)); } catch (e) {}
  }

  function canFire(state, rule) {
    if (proactiveDone) return false;
    if (firedRuleIds.has(rule.id)) return false;
    if (state.isOpen || state.hasOpened) return false;
    return true;
  }

  /* Show the notification bounce and a small standalone teaser bubble with
     the rule's message — NOT the full chat window. Opening the chat (via the
     launcher or the teaser itself) is what actually carries the message in;
     see openFloatingHandlingProactive(). */
  function fireProactiveRule(state, rule) {
    if (!canFire(state, rule)) return;

    proactiveDone = true;
    firedRuleIds.add(rule.id);
    persistFiredRuleIds();
    clearProactiveTimers();

    // Attribute the conversation in analytics — only once the visitor
    // actually replies does this reach the server (see sendWithText).
    state.triggeredBy = 'proactive:' + rule.trigger_type;

    // Bounce the launcher first so the visitor sees WHERE the message came
    // from, then reveal the teaser — the bounce runs for ~450ms (see
    // aichat-notify-bounce in the CSS).
    var toggle = document.getElementById('aichat-toggle');
    var dot    = document.getElementById('aichat-notif-dot');
    if (toggle) toggle.classList.add('aichat-toggle-btn--notify');
    if (dot)    dot.classList.add('aichat-notif-dot--alert');

    setTimeout(function () {
      state.pendingProactiveMessage = rule.message;
      showProactiveTeaser(rule);
    }, 450);
  }

  function clearProactiveTimers() {
    proactiveTimers.forEach(function (t) { clearTimeout(t); });
    proactiveTimers = [];
  }

  /* Schedule a fire after the rule's delay, re-checking eligibility at the
     moment it lands (the visitor may have opened the chat in the meantime). */
  function scheduleFire(state, rule) {
    if (!canFire(state, rule)) return;
    var delay = Math.max(0, (parseInt(rule.delay_seconds, 10) || 0) * 1000);
    proactiveTimers.push(setTimeout(function () {
      fireProactiveRule(state, rule);
    }, delay));
  }

  function registerProactiveRules(state) {
    if (!cfg.proactiveEnabled) return;

    var rules = Array.isArray(cfg.proactiveRules) ? cfg.proactiveRules : [];
    rules = rules.filter(function (r) { return r && r.enabled && r.message && r.id; });
    if (!rules.length) return;

    loadFiredRuleIds();
    if (proactiveDone) return;

    var scrollRules = [];
    var idleRules   = [];
    var exitRules   = [];
    var timeRules   = [];

    rules.forEach(function (rule) {
      switch (rule.trigger_type) {
        case 'url_contains':
          // Path-level match, evaluated once: the URL doesn't change under us.
          var needle = String(rule.condition_value || '');
          var here   = window.location.pathname + window.location.search;
          if (needle && here.indexOf(needle) !== -1) scheduleFire(state, rule);
          break;
        case 'exit_intent':  exitRules.push(rule);   break;
        case 'idle_on_page': idleRules.push(rule);   break;
        case 'scroll_depth': scrollRules.push(rule); break;
        case 'time_on_site': timeRules.push(rule);   break;
      }
    });

    /* ── Exit intent (desktop only) ── */
    if (exitRules.length && isDesktopViewport()) {
      document.addEventListener('mouseout', function (e) {
        if (proactiveDone) return;
        // Pointer left through the TOP of the viewport, and left the document
        // entirely rather than moving onto a child element.
        if (e.clientY > 0 || e.relatedTarget || e.toElement) return;
        exitRules.forEach(function (rule) { scheduleFire(state, rule); });
      });
    }

    /* ── Idle on page ── */
    if (idleRules.length) {
      var idleTimers = [];
      var resetIdle = function () {
        idleTimers.forEach(function (t) { clearTimeout(t); });
        idleTimers = [];
        if (proactiveDone) return;
        idleRules.forEach(function (rule) {
          var seconds = Math.max(1, parseInt(rule.condition_value, 10) || 1);
          idleTimers.push(setTimeout(function () {
            scheduleFire(state, rule);
          }, seconds * 1000));
        });
      };
      var resetIdleThrottled = throttle(resetIdle, THROTTLE_MS);
      window.addEventListener('scroll', resetIdleThrottled, { passive: true });
      document.addEventListener('click', resetIdleThrottled);
      resetIdle();
    }

    /* ── Scroll depth ── */
    if (scrollRules.length) {
      var onScroll = throttle(function () {
        if (proactiveDone) return;
        var doc       = document.documentElement;
        var scrolled  = window.pageYOffset || doc.scrollTop || 0;
        var scrollable = Math.max(1, (doc.scrollHeight || 0) - window.innerHeight);
        var percent   = Math.min(100, (scrolled / scrollable) * 100);
        scrollRules.forEach(function (rule) {
          var target = Math.max(1, parseInt(rule.condition_value, 10) || 1);
          if (percent >= target) scheduleFire(state, rule);
        });
      }, THROTTLE_MS);
      window.addEventListener('scroll', onScroll, { passive: true });
      onScroll(); // A short page may already be past the threshold.
    }

    /* ── Time on site (cumulative across pages in this tab) ── */
    if (timeRules.length) {
      var baseline = readTimeOnSite();
      var started  = Date.now();
      var tick = setInterval(function () {
        var total = baseline + Math.floor((Date.now() - started) / 1000);
        writeTimeOnSite(total);
        if (proactiveDone) { clearInterval(tick); return; }
        timeRules.forEach(function (rule) {
          var target = Math.max(1, parseInt(rule.condition_value, 10) || 1);
          if (total >= target) scheduleFire(state, rule);
        });
      }, 1000);
    } else {
      // Still accumulate, so a time_on_site rule enabled later (or on another
      // page) sees a truthful total for this session.
      var base2 = readTimeOnSite();
      var start2 = Date.now();
      window.addEventListener('beforeunload', function () {
        writeTimeOnSite(base2 + Math.floor((Date.now() - start2) / 1000));
      });
    }
  }

  /* Clear the "something happened" affordances once the visitor engages. */
  function clearProactiveNotice() {
    var toggle = document.getElementById('aichat-toggle');
    var dot    = document.getElementById('aichat-notif-dot');
    if (toggle) toggle.classList.remove('aichat-toggle-btn--notify');
    if (dot)    dot.classList.remove('aichat-notif-dot--alert');
  }

  /* ── WhatsApp Exit Prompt ───────────────────────────────────────────────── */

  function triggerWhatsAppPrompt(state, p, onClose) {
    state.whatsappPromptShown = true;
    state.whatsappMode = true;
    state._pendingClose = onClose;

    // Make sure chat panel is visible (not gate)
    var gate = document.getElementById(p + 'aichat-gate');
    var chat = document.getElementById(p + 'aichat-chat');
    if (gate) gate.style.display = 'none';
    if (chat) chat.style.display = '';

    setTimeout(function () {
      renderBotBubble(state, p,
        'Before you go! \uD83D\uDCF1 Would you like me to send our **contact details** (location, phone number & more) directly to your WhatsApp?\n\nJust share your WhatsApp number below! (Or type **skip** to close.)',
        []
      );
      var input = document.getElementById(p + 'aichat-msg-input');
      if (input) { input.placeholder = 'Enter your WhatsApp number\u2026'; input.focus(); }
    }, 180);
  }

  /* ── INLINE WIDGET ──────────────────────────────────────────────────────── */

  function buildInlineWidget() {
    var root = document.getElementById('aichat-inline-root');
    if (!root) return;

    root.style.setProperty('--aichat-primary',   primary);
    root.style.setProperty('--aichat-secondary', secondary);
    root.style.setProperty('--aichat-gradient',  gradient);

    var state    = makeState();
    var showGate = isGateMode() && !gateAlreadyPassed(state);

    root.innerHTML = buildWindowHTML('il-', showGate);

    var win = document.getElementById('il-aichat-window');
    if (win) {
      win.classList.add('aichat-window--open', 'aichat-window--inline');
      win.setAttribute('aria-hidden', 'false');
    }

    bindGateForm(state, 'il-');
    bindChatEvents(state, 'il-');
    window.addEventListener('beforeunload', function () {
      if ((state.allMessages.length > 0 || state.userName) && !state.leadSaved) sendBeacons(state);
    });

    if (!showGate) {
      if (state.allMessages.length > 0) {
        restoreMessages(state, 'il-');
      } else {
        showWelcome(state, 'il-');
      }
    }
  }

  /* ── Inline form (in-conversation lead capture) ─────────────────────────── */

  function renderInlineForm(state, p) {
    var container = document.getElementById(p + 'aichat-messages');
    if (!container) return;

    // Render the intro message directly in the DOM (not saved to history so it disappears cleanly).
    var introEl = document.createElement('div');
    introEl.id        = p + 'aichat-inline-form-intro';
    introEl.className = 'aichat-message aichat-message--bot';
    introEl.innerHTML =
      '<div class="aichat-bot-row">' +
        '<div class="aichat-bot-avatar">' + botAvatarHTML() + '</div>' +
        '<div class="aichat-bubble aichat-bubble--bot">Kindly fill in the form below to continue. \uD83D\uDE0A</div>' +
      '</div>';
    container.appendChild(introEl);
    scrollBottom(p);

    var formEl = document.createElement('div');
    formEl.id        = p + 'aichat-inline-form-wrapper';
    formEl.className = 'aichat-message aichat-inline-form-wrapper';
    formEl.innerHTML =
      '<div class="aichat-inline-form">' +
        '<form id="' + p + 'aichat-inline-form" novalidate>' +
          '<input type="text"  id="' + p + 'aichat-inf-name"  class="aichat-input aichat-inf-input" placeholder="Your Name *"             autocomplete="name"  />' +
          '<input type="email" id="' + p + 'aichat-inf-email" class="aichat-input aichat-inf-input" placeholder="Email Address *"          autocomplete="email" />' +
          '<input type="tel"   id="' + p + 'aichat-inf-phone" class="aichat-input aichat-inf-input" placeholder="Phone Number *" autocomplete="tel" maxlength="16" />' +
          '<div class="aichat-error-msg" id="' + p + 'aichat-inf-error"></div>' +
          '<button type="submit" class="aichat-btn-primary aichat-inf-btn">Start Chat &#8594;</button>' +
        '</form>' +
      '</div>';

    container.appendChild(formEl);
    scrollBottom(p);
    setSendDisabled(p, true);

    var form  = document.getElementById(p + 'aichat-inline-form');
    var errEl = document.getElementById(p + 'aichat-inf-error');
    if (!form) return;

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var name  = (document.getElementById(p + 'aichat-inf-name')  || {}).value || '';
      var email = (document.getElementById(p + 'aichat-inf-email') || {}).value || '';
      var phone = (document.getElementById(p + 'aichat-inf-phone') || {}).value || '';
      name  = name.trim();
      email = email.trim();
      phone = phone.trim();

      if (!name || !email || !phone) {
        if (errEl) errEl.textContent = 'Please fill in all fields.';
        return;
      }
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        if (errEl) errEl.textContent = 'Please enter a valid email address.';
        return;
      }
      if (!isValidPhone(phone)) {
        if (errEl) {
          if (/[^0-9+]/.test(phone)) {
            errEl.textContent = 'Phone number must contain only digits (and an optional leading +).';
          } else {
            errEl.textContent = 'Phone number must be 7 to 15 digits.';
          }
        }
        return;
      }
      if (errEl) errEl.textContent = '';

      markLeadCaptured(state, { name: name, email: email, phone: phone });

      // Wipe the lead-collection thread from API history so the LLM
      // never sees "And your email?" messages again and stops asking.
      state.apiMessages = [];

      saveLead(state, false);
      saveHistory(state);
      markGatePassed();

      // Remove the intro message and the inline form from chat.
      var intro = document.getElementById(p + 'aichat-inline-form-intro');
      if (intro) intro.parentNode.removeChild(intro);
      var wrapper = document.getElementById(p + 'aichat-inline-form-wrapper');
      if (wrapper) wrapper.parentNode.removeChild(wrapper);

      // Re-enable chat input.
      setSendDisabled(p, false);

      // Brief acknowledgment.
      var ack = 'Thanks, ' + escHtml(name) + '! 😊';
      renderBotBubble(state, p, ack, []);
      state.allMessages.push({ role: 'assistant', content: 'Thanks, ' + name + '! 😊' });
      state.apiMessages.push({ role: 'assistant', content: 'Thanks, ' + name + '! 😊' });
      saveHistory(state);

      // If there is a pending message (the question that triggered the form), answer it now.
      if (state.pendingMessage) {
        var pending = state.pendingMessage;
        state.pendingMessage = '';
        state.apiMessages.push({ role: 'user', content: pending });
        state.isTyping = true;
        setSendDisabled(p, true);
        showTyping(p);
        callLLM(state.apiMessages.slice(), state)
          .then(function (rawResponse) {
            clearTyping(p);
            state.isTyping = false;
            setSendDisabled(p, false);
            var displayText = renderBotBubble(state, p, rawResponse, [
              'Tell me more',
              'What else can you help with?',
              'How do I get in touch?',
            ]);
            state.allMessages.push({ role: 'assistant', content: displayText });
            state.apiMessages.push({ role: 'assistant', content: displayText });
            saveHistory(state);
            trackMessage(pending, displayText);
            if (!state.handoffDone && isLowConfidence(displayText)) {
              state.handoffTriggerQuestion = pending;
              setTimeout(function () { startHandoff(state, p, pending); }, 800);
            }
          })
          .catch(function () {
            clearTyping(p);
            state.isTyping = false;
            setSendDisabled(p, false);
            var errMsg = 'Sorry, something went wrong. Please try again!';
            renderBotBubble(state, p, errMsg, []);
            state.allMessages.push({ role: 'assistant', content: errMsg });
            state.apiMessages.push({ role: 'assistant', content: errMsg });
          });
      } else {
        showQuickReplies(state, p, [
          'Tell me about your services',
          'How can I get started?',
          'I have a question',
        ]);
        setTimeout(function () {
          var inputEl = document.getElementById(p + 'aichat-msg-input');
          if (inputEl) inputEl.focus();
        }, 80);
      }
    });
  }

  /* ── Welcome message ────────────────────────────────────────────────────── */

  function showWelcome(state, p) {
    var nameGreet = state.userName ? ', ' + state.userName : '';
    var welcome = (cfg.welcomeMessage || 'Hi! How can I help you today?')
                    .replace(/Hi!/i, 'Hi' + nameGreet + '!');

    /* Returning visitor: a name captured in an EARLIER session (this one has a
       fresh session id). Templated here in JS — the greeting costs no LLM call.
       Skipped when this session already knows the name, so the welcome doesn't
       say it twice. */
    if (!state.userName && returningName) {
      welcome = 'Welcome back, ' + returningName + '! ' + welcome;
    }

    renderBotBubble(state, p, welcome, []);
    state.allMessages.push({ role: 'assistant', content: welcome });
    state.apiMessages.push({ role: 'assistant', content: welcome });

    // Only ask for name in conversational mode if we don't already know it —
    // either from this session or from a previous visit.
    if (!isGateMode() && !isInlineFormMode() && !state.userName && !returningName) {
      var nameAsk = 'May I know your name? 😊';
      renderBotBubble(state, p, nameAsk, [
        'Tell me about your services',
        'How can I get started?',
        'I have a question',
      ]);
      state.allMessages.push({ role: 'assistant', content: nameAsk });
      state.apiMessages.push({ role: 'assistant', content: nameAsk });
    } else {
      // Attach suggestion chips to the welcome bubble — no second message needed.
      showQuickReplies(state, p, [
        'Tell me about your services',
        'How can I get started?',
        'I have a question',
      ]);
    }

    saveHistory(state);
  }

  /* ── Restore saved chat history ─────────────────────────────────────────── */

  function restoreMessages(state, p) {
    state.allMessages.forEach(function (m, i) {
      if (m.role === 'user') {
        renderUserBubble(p, m.content);
      } else {
        var html;
        if (window.marked && typeof window.marked.parse === 'function') {
          try { html = sanitizeHtml(window.marked.parse(m.content)); }
          catch (e) { html = escHtml(m.content).replace(/\n/g, '<br>'); }
        } else {
          html = escHtml(m.content).replace(/\n/g, '<br>');
        }
        var el = document.createElement('div');
        el.className = 'aichat-message aichat-message--bot';
        el.innerHTML =
          '<div class="aichat-bot-row">' +
            '<div class="aichat-bot-avatar">' + botAvatarHTML() + '</div>' +
            '<div class="aichat-bubble aichat-bubble--bot">' + html + '</div>' +
          '</div>';
        document.getElementById(p + 'aichat-messages').appendChild(el);
        scrollBottom(p);
      }
    });
  }

  /* ── Message rendering ──────────────────────────────────────────────────── */

  function scrollBottom(p) {
    var el = document.getElementById(p + 'aichat-messages');
    if (el) el.scrollTop = el.scrollHeight;
  }

  function renderUserBubble(p, text) {
    var el = document.createElement('div');
    el.className = 'aichat-message aichat-message--user';
    el.innerHTML = '<div class="aichat-bubble aichat-bubble--user">' + escHtml(text) + '</div>';
    document.getElementById(p + 'aichat-messages').appendChild(el);
    scrollBottom(p);
  }

  function parseBotResponse(raw) {
    var suggestions = [];
    var leadData    = null;
    var displayText = raw;

    // Extract LEADDATA marker (hidden from visitor).
    var leadMatch = raw.match(/LEADDATA:\s*(\{[\s\S]*?\})\s*(\n|$)/m);
    if (leadMatch) {
      try {
        leadData    = JSON.parse(leadMatch[1]);
        displayText = displayText.replace(leadMatch[0], '').trim();
      } catch (e) { /* ignore malformed JSON */ }
    }

    // Extract SUGGESTIONS marker.
    var sugMatch = displayText.match(/SUGGESTIONS:\s*(\[[\s\S]*?\])\s*$/m);
    if (sugMatch) {
      displayText = displayText.slice(0, sugMatch.index).trim();
      try {
        var parsed = JSON.parse(sugMatch[1]);
        if (Array.isArray(parsed)) {
          suggestions = parsed.filter(function (s) { return typeof s === 'string' && s.trim(); }).slice(0, 3);
        }
      } catch (e) { /* ignore */ }
    }

    return { displayText: displayText, suggestions: suggestions, leadData: leadData };
  }

  function renderBotBubble(state, p, raw, fallbackSuggestions) {
    clearQuickReplies(p);
    var parsed      = parseBotResponse(raw);
    var displayText = parsed.displayText;
    var suggestions = parsed.suggestions.length ? parsed.suggestions : (fallbackSuggestions || []);

    // Process collected lead data.
    if (parsed.leadData && !state.leadCollected) {
      var ld = parsed.leadData;
      markLeadCaptured(state, ld);
      saveLead(state, false);
    }

    var html;
    if (window.marked && typeof window.marked.parse === 'function') {
      try { html = sanitizeHtml(window.marked.parse(displayText)); }
      catch (e) { html = escHtml(displayText).replace(/\n/g, '<br>'); }
    } else {
      html = escHtml(displayText).replace(/\n/g, '<br>');
    }

    var el = document.createElement('div');
    el.className = 'aichat-message aichat-message--bot';
    el.innerHTML =
      '<div class="aichat-bot-row">' +
        '<div class="aichat-bot-avatar">' + botAvatarHTML() + '</div>' +
        '<div class="aichat-bubble aichat-bubble--bot">' + html + '</div>' +
      '</div>';
    document.getElementById(p + 'aichat-messages').appendChild(el);

    // Auto-widen window and wrap tables in scrollable container if table detected.
    if (html.indexOf('<table') !== -1) {
      var winId = p ? p + 'aichat-window' : 'aichat-window';
      var win = document.getElementById(winId) ||
                document.querySelector('.aichat-window');
      if (win) win.classList.add('aichat-window--wide');

      // Wrap each table in a scrollable div.
      var bubble = el.querySelector('.aichat-bubble--bot');
      if (bubble) {
        var tables = bubble.querySelectorAll('table');
        tables.forEach(function (tbl) {
          var wrap = document.createElement('div');
          wrap.className = 'aichat-table-wrap';
          tbl.parentNode.insertBefore(wrap, tbl);
          wrap.appendChild(tbl);
        });
      }
    }

    scrollBottom(p);

    if (suggestions.length) showQuickReplies(state, p, suggestions);
    return displayText;
  }

  /* ── Typing indicator ───────────────────────────────────────────────────── */

  function showTyping(p) {
    clearTyping(p);
    var el = document.createElement('div');
    el.id        = p + 'aichat-typing';
    el.className = 'aichat-message aichat-message--bot';
    el.setAttribute('aria-label', 'AI is typing');
    el.innerHTML =
      '<div class="aichat-bot-row">' +
        '<div class="aichat-bot-avatar">' + botAvatarHTML() + '</div>' +
        '<div class="aichat-bubble aichat-bubble--bot aichat-typing">' +
          '<span class="aichat-dot"></span><span class="aichat-dot"></span><span class="aichat-dot"></span>' +
        '</div>' +
      '</div>';
    document.getElementById(p + 'aichat-messages').appendChild(el);
    scrollBottom(p);
  }

  function clearTyping(p) {
    var el = document.getElementById(p + 'aichat-typing');
    if (el) el.parentNode.removeChild(el);
  }

  /* ── Quick replies ──────────────────────────────────────────────────────── */

  function showQuickReplies(state, p, suggestions) {
    var container = document.getElementById(p + 'aichat-quick-replies');
    if (!container) return;
    container.innerHTML = '';
    suggestions.forEach(function (text, i) {
      var btn = document.createElement('button');
      btn.className   = 'aichat-chip';
      btn.textContent = text;
      btn.title       = text;
      // Staggered entrance — the CSS reads this as the animation-delay.
      btn.style.setProperty('--aichat-chip-delay', (i * 40) + 'ms');
      btn.addEventListener('click', function () { sendWithText(state, p, text); });
      container.appendChild(btn);
    });
  }

  function clearQuickReplies(p) {
    var c = document.getElementById(p + 'aichat-quick-replies');
    if (c) c.innerHTML = '';
  }

  /* ── Send message ───────────────────────────────────────────────────────── */

  /* ── Human handoff ──────────────────────────────────────────────────────── */

  // Kept narrow and intentional — broader terms like "human", "customer
  // support", or "representative" false-positive on unrelated questions
  // (e.g. "human resources", "what's your support policy?").
  var HUMAN_TRIGGERS = [
    'talk to a human', 'speak to a human', 'human agent', 'real person',
    'live agent', 'live person', 'live chat',
    'speak to someone', 'talk to someone', 'talk to a person', 'speak to a person',
    'speak with someone', 'talk with someone', 'real agent', 'support agent',
    'call me', 'arrange a call', 'schedule a call', 'phone call', 'book a call',
  ];

  function isHumanRequest(text) {
    var lower = text.toLowerCase();
    for (var i = 0; i < HUMAN_TRIGGERS.length; i++) {
      if (lower.indexOf(HUMAN_TRIGGERS[i]) !== -1) return true;
    }
    return false;
  }

  function startHandoff(state, p, triggerQuestion) {
    if (state.handoffDone) return;
    state.handoffMode            = true;
    state.handoffTriggerQuestion = triggerQuestion || '';
    state.handoffName  = state.handoffName  || state.userName  || '';
    state.handoffPhone = state.handoffPhone || state.userPhone  || '';
    state.handoffEmail = state.handoffEmail || state.userEmail  || '';
    // FIX: check all three fields — if all already known (e.g. form was filled),
    // step becomes 'done' and we skip the intro entirely.
    state.handoffStep  = !state.handoffName ? 'name' : (!state.handoffPhone ? 'phone' : (!state.handoffEmail ? 'email' : 'done'));

    // All details already available — save immediately, but still confirm to
    // the visitor rather than leaving their "talk to a human" message unanswered.
    if (state.handoffStep === 'done') {
      state.handoffMode = false;
      state.handoffDone = true;
      saveHandoffLead(state);
      var alreadyDoneMsg = "Of course! 🙌 We've got your details on file and our team will reach out to you shortly.";
      renderBotBubble(state, p, alreadyDoneMsg, []);
      state.allMessages.push({ role: 'assistant', content: alreadyDoneMsg });
      saveHistory(state);
      return;
    }

    var intro = "I can arrange a call for you! Kindly share your details 😊";
    renderBotBubble(state, p, intro, []);
    state.allMessages.push({ role: 'assistant', content: intro });

    setTimeout(function () {
      var ask = handoffAskText(state.handoffStep, state.handoffName);
      renderBotBubble(state, p, ask, []);
      state.allMessages.push({ role: 'assistant', content: ask });
      saveHistory(state);
    }, 600);
  }

  function handoffAskText(step, name) {
    if (step === 'name')  return "What's your name?";
    if (step === 'phone') return (name ? "Thanks " + name + "! " : "") + "What's your phone number? 📞";
    if (step === 'email') return "And your email address? 📧";
    return '';
  }

  // Rejects obvious declines/nonsense so they don't get stored as a "name"
  // (e.g. a reply like "haha I said no" was previously saved as the visitor's
  // name and echoed straight back in the next question).
  //
  // Split in two: phrases that are essentially NEVER a real name (safe to
  // always reject) vs. bare words that double as real names/nicknames
  // (e.g. "Skip") — those are only treated as a decline the FIRST time; if
  // the visitor types the exact same word again, we take it as them
  // insisting it really is their name rather than asking forever.
  var HANDOFF_DECLINE_RE   = /^\s*(no+p?e?|nah+|i('| a)?m? ?(not|don'?t|won'?t|will ?not)\b.*|i'?d rather not.*|not (telling|gonna|going to).*|leave me alone)\s*[.!]*\s*$/i;
  var HANDOFF_AMBIGUOUS_RE = /^\s*(skip|pass|none)\s*[.!]*\s*$/i;

  // Strips a conversational lead-in ("My name is X", "I'm X", "Call me X",
  // "This is X") down to just the name itself, so a full-sentence reply
  // doesn't get stored/echoed back verbatim (e.g. "Thanks My name is
  // Alakhveer Singh!" instead of "Thanks Alakhveer Singh!").
  var HANDOFF_NAME_PREFIX_RE = /^(?:my\s+name\s+is|i\s*a?m|this\s+is|it'?s|call\s+me)\s+/i;

  function extractHandoffName(text) {
    var t = text.trim().replace(HANDOFF_NAME_PREFIX_RE, '').trim();
    // Drop a trailing pleasantry like ", nice to meet you" or a lone "." "!".
    t = t.replace(/[,.\s]*\b(nice to meet you|thanks?|thank you)\b.*$/i, '').trim();
    t = t.replace(/[.!]+$/, '').trim();
    return t || text.trim();
  }

  /**
   * @param  {string} name         Already lead-in-stripped candidate name.
   * @param  {string} lastAttempt  The previous raw reply for this field, if any.
   * @return {'valid'|'decline'|'ambiguous'}  'ambiguous' repeated verbatim is
   *         treated as 'valid' by the caller.
   */
  function classifyHandoffName(name, lastAttempt) {
    var t = name.trim();
    if (!t || t.length > 60 || HANDOFF_DECLINE_RE.test(t)) return 'decline';
    if (HANDOFF_AMBIGUOUS_RE.test(t)) {
      var repeated = lastAttempt && t.toLowerCase() === lastAttempt.toLowerCase();
      return repeated ? 'valid' : 'ambiguous';
    }
    return 'valid';
  }

  function isValidHandoffPhone(text) {
    var digits = text.replace(/[^\d+]/g, '');
    return isValidPhone(digits) ? digits : false;
  }

  function isValidHandoffEmail(text) {
    var t = text.trim();
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(t) ? t : false;
  }

  function handleHandoffStep(state, p, text) {
    var next = '';
    var invalidMsg = '';
    var fieldLabel = { name: 'name', phone: 'phone number', email: 'email address' }[state.handoffStep] || 'detail';

    if (state.handoffStep === 'name') {
      var cleanedName  = extractHandoffName(text);
      var nameVerdict  = classifyHandoffName(cleanedName, state.handoffLastNameAttempt);
      if (nameVerdict !== 'valid') {
        state.handoffLastNameAttempt = cleanedName;
        invalidMsg = (nameVerdict === 'ambiguous')
          ? "Just to confirm — is \"" + cleanedName + "\" your name? If so, go ahead and type it again 😊"
          : "Sorry, I didn't quite catch a name there — could you share your name? 😊";
      } else {
        state.handoffName = cleanedName;
        state.handoffLastNameAttempt = '';
        state.handoffStep = !state.handoffPhone ? 'phone' : (!state.handoffEmail ? 'email' : 'done');
        state.handoffStepAttempts = 0;
        next = state.handoffStep !== 'done' ? handoffAskText(state.handoffStep, state.handoffName) : '';
      }
    } else if (state.handoffStep === 'phone') {
      var validPhone = isValidHandoffPhone(text);
      if (!validPhone) {
        invalidMsg = "That doesn't look like a valid phone number — mind sharing 7 to 15 digits? 📞";
      } else {
        state.handoffPhone = validPhone;
        state.handoffStep  = !state.handoffEmail ? 'email' : 'done';
        state.handoffStepAttempts = 0;
        next = state.handoffStep === 'email' ? handoffAskText('email', state.handoffName) : '';
      }
    } else if (state.handoffStep === 'email') {
      var validEmail = isValidHandoffEmail(text);
      if (!validEmail) {
        invalidMsg = "That doesn't look like a valid email address — mind double-checking it? 📧";
      } else {
        state.handoffEmail = validEmail;
        state.handoffStep  = 'done';
        state.handoffStepAttempts = 0;
      }
    }

    if (invalidMsg) {
      state.handoffStepAttempts = (state.handoffStepAttempts || 0) + 1;

      // Don't loop forever on a repeated decline/invalid reply — back off
      // gracefully after a second miss, same spirit as the conversational
      // lead-capture flow (ask once more, then stop asking).
      if (state.handoffStepAttempts >= 2) {
        state.handoffMode         = false;
        state.handoffStepAttempts = 0;
        var giveUpMsg = "No worries at all if you'd rather not share your " + fieldLabel + "! 😊 I won't keep asking — feel free to reach out to us directly whenever you're ready, or just let me know if there's anything else I can help with.";
        setTimeout(function () {
          renderBotBubble(state, p, giveUpMsg, []);
          state.allMessages.push({ role: 'assistant', content: giveUpMsg });
          saveHistory(state);
        }, 400);
        return;
      }

      setTimeout(function () {
        renderBotBubble(state, p, invalidMsg, []);
        state.allMessages.push({ role: 'assistant', content: invalidMsg });
        saveHistory(state);
      }, 400);
      return;
    }

    if (state.handoffStep === 'done') {
      state.handoffMode = false;
      state.handoffDone = true;
      // Merge into the shared lead-context tracking too, so a later chat
      // question (e.g. "what are my details?") has the same info the LLM
      // needs, instead of the two tracking systems disagreeing.
      markLeadCaptured(state, { name: state.handoffName, phone: state.handoffPhone, email: state.handoffEmail });
      saveHandoffLead(state);
      var confirm = "Perfect! 🙌 We've got your details and our team will reach out to you shortly.";
      setTimeout(function () {
        renderBotBubble(state, p, confirm, []);
        state.allMessages.push({ role: 'assistant', content: confirm });
        saveHistory(state);
      }, 400);
    } else if (next) {
      setTimeout(function () {
        renderBotBubble(state, p, next, []);
        state.allMessages.push({ role: 'assistant', content: next });
        saveHistory(state);
      }, 400);
    }
  }

  function saveHandoffLead(state) {
    if (!cfg.ajaxUrl || !cfg.handoffNonce) return;
    var transcript = buildTranscript(state);
    var form = new FormData();
    form.append('action',           'aichat_handoff_notification');
    form.append('nonce',            cfg.handoffNonce);
    form.append('session_id',       sessionId);
    form.append('name',             state.handoffName  || '');
    form.append('phone',            state.handoffPhone || '');
    form.append('email',            state.handoffEmail || '');
    form.append('trigger_question', state.handoffTriggerQuestion || '');
    form.append('transcript',       transcript);
    fetch(cfg.ajaxUrl, { method: 'POST', body: form }).catch(function () {});
  }

  function sendMessage(state, p) {
    var input = document.getElementById(p + 'aichat-msg-input');
    if (!input) return;
    var text = input.value.trim();
    if (!text || state.isTyping) return;
    input.value = '';
    sendWithText(state, p, text);
  }

  function sendWithText(state, p, text) {
    if (state.isTyping) return;
    clearQuickReplies(p);
    renderUserBubble(p, text);
    state.allMessages.push({ role: 'user', content: text });
    saveHistory(state);

    // Handle WhatsApp number capture
    if (state.whatsappMode) {
      state.whatsappMode = false;
      var lower = text.toLowerCase().trim();
      var num = text.replace(/\D/g, '');
      if (lower === 'skip' || lower === 'no' || lower === 'nahi' || lower === 'nope') {
        renderBotBubble(state, p, 'No problem! Feel free to reach out anytime. Have a great day! \uD83D\uDE0A', []);
      } else if (num.length >= 7) {
        state.whatsappNumber = num;
        // Record it as a lead so a human can actually follow up \u2014 nothing in
        // this widget sends messages to WhatsApp automatically, so the copy
        // below is honest about what happens next instead of promising that.
        if (!state.userPhone) state.userPhone = num;
        state.userRequirement = (state.userRequirement ? state.userRequirement + ' ' : '') +
          'Requested contact details on WhatsApp +' + num + '.';
        saveLead(state, false);
        var input = document.getElementById(p + 'aichat-msg-input');
        if (input) input.placeholder = 'Type a message\u2026';
        renderBotBubble(state, p, 'Thanks! \u2705 We\u2019ve noted your WhatsApp number **+' + num + '** and our team will reach out with our contact details. Have a great day! \uD83D\uDE0A', []);
      } else {
        renderBotBubble(state, p, 'No problem! Feel free to reach out anytime. Have a great day! \uD83D\uDE0A', []);
      }
      saveHistory(state);
      if (state._pendingClose) {
        setTimeout(function () {
          if (state._pendingClose) { state._pendingClose(); state._pendingClose = null; }
        }, 2800);
      }
      return;
    }

    if (state.handoffMode) {
      handleHandoffStep(state, p, text);
      return;
    }

    if (!state.handoffDone && isHumanRequest(text)) {
      state.handoffTriggerQuestion = text;
      setTimeout(function () { startHandoff(state, p, text); }, 200);
      return;
    }

    // In inline-form mode, show the form after the user sends their first message.
    if (isInlineFormMode() && !gateAlreadyPassed(state)) {
      state.pendingMessage = text; // remember original question to answer after form submit
      renderInlineForm(state, p);
      saveHistory(state);
      return;
    }

    state.apiMessages.push({ role: 'user', content: text });

    state.isTyping = true;
    setSendDisabled(p, true);
    showTyping(p);

    requestReply(state, p, state.apiMessages.slice())
      .then(function (rawResponse) {
        clearTyping(p);
        state.isTyping = false;
        setSendDisabled(p, false);

        var displayText = renderBotBubble(state, p, rawResponse, [
          'Tell me more',
          'What else can you help with?',
          'How do I get in touch?',
        ]);

        state.allMessages.push({ role: 'assistant', content: displayText });
        state.apiMessages.push({ role: 'assistant', content: displayText });
        saveHistory(state);

        // Analytics tracking. A proactive attribution is recorded only here —
        // i.e. only once the visitor has actually replied to the trigger.
        trackMessage(text, displayText, state.triggeredBy);

        if (!state.handoffDone && isLowConfidence(displayText)) {
          state.handoffTriggerQuestion = text;
          setTimeout(function () { startHandoff(state, p, text); }, 800);
        }
      })
      .catch(function (err) {
        clearTyping(p);
        state.isTyping = false;
        setSendDisabled(p, false);

        var detail = (err && err.message) ? err.message : String(err);
        console.error('[AI Site Chat] API Error ▶', detail);
        console.error('[AI Site Chat] Provider:', cfg.llmProvider, '| Model:', cfg.modelName);

        var errMsg = 'Sorry, something went wrong. Please try again later!';
        renderBotBubble(state, p, errMsg, []);
        state.allMessages.push({ role: 'assistant', content: errMsg });
        state.apiMessages.push({ role: 'assistant', content: errMsg });
      });
  }

  /* Fetch one reply, streamed when the site has streaming on and the browser
     can do it, buffered otherwise. Resolves with the raw reply text either
     way, so callers don't branch. */
  function requestReply(state, p, messages) {
    if (!streamingAvailable()) return callLLM(messages, state);

    var bubble = null;

    return callLLMStreaming(messages, state, function (chunk) {
        // First token: the typing dots have done their job.
        if (!bubble) {
          clearTyping(p);
          bubble = createStreamingBubble(p);
        }
        bubble.append(chunk);
      })
      .then(function (raw) {
        // Hand off to renderBotBubble() for the single parsed+sanitised render.
        if (bubble) bubble.destroy();
        return raw;
      })
      .catch(function () {
        // A stream that died partway leaves partial text on screen. Drop it
        // and retry once on the buffered path so the visitor ends up with a
        // complete answer — or, if that fails too, a real error message from
        // the caller's own catch. Never a half-written bubble and no error.
        if (bubble) bubble.destroy();
        showTyping(p);
        return callLLM(messages, state);
      });
  }

  function setSendDisabled(p, disabled) {
    var btn   = document.getElementById(p + 'aichat-send');
    var input = document.getElementById(p + 'aichat-msg-input');
    if (btn)   btn.disabled   = disabled;
    if (input) input.disabled = disabled;
  }

  /* ══════════════════════════════════════════════════════════════════════════
     MULTI-LLM API LAYER
     Supports: Claude, OpenAI, Google Gemini, Mistral, Llama (Groq), DeepSeek

     The system prompt (trained knowledge base + personality + lead-capture
     rules) is built entirely server-side in aichat_ajax_proxy_llm() —
     see orate-agency-plugin.php. The client only sends the conversation
     messages plus a few lead-context flags; it never sees or supplies the
     prompt text itself. This keeps the knowledge base out of page source
     and keeps the proxy from being scriptable as a general-purpose chatbot
     with an attacker-supplied system prompt.
  ══════════════════════════════════════════════════════════════════════════ */

  function callLLM(messages, state) {
    if (!cfg.ajaxUrl || !cfg.chatNonce) {
      return Promise.reject(new Error('Chat proxy not configured.'));
    }

    syncLeadContext(state);
    var ld = state.leadData || {};

    var form = new FormData();
    form.append('action',           'aichat_proxy_llm');
    form.append('nonce',            cfg.chatNonce);
    form.append('messages',         JSON.stringify(messages));
    form.append('lead_captured',    state.leadCaptured ? '1' : '');
    form.append('lead_name',        state.userName || ld.name || '');
    form.append('lead_email',       state.userEmail || ld.email || '');
    form.append('lead_phone',       state.userPhone || ld.phone || '');
    form.append('lead_requirement', state.userRequirement || ld.requirement || '');
    // Personalisation only — the server uses this for one hidden context line
    // and nothing else (see aichat_build_system_prompt_for_request).
    if (returningName && !state.userName) form.append('returning_visitor_name', returningName);

    return fetch(cfg.ajaxUrl, { method: 'POST', body: form })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (!data.success) throw new Error(data.data || 'API error');
        return data.data;
      });
  }

  /* ── Streaming (SSE) ──────────────────────────────────────────────────────
     The proxy can deliver a reply token-by-token when aichat_streaming_enabled
     is on. Markdown is deliberately NOT parsed per chunk — a full marked.js +
     DOMPurify pass on every token is wasteful and can flicker on half-written
     syntax. During the stream the bubble appends plain text with a blinking
     caret; once the stream ends the finished text is parsed and sanitised once,
     through the same renderBotBubble() path the buffered reply uses (so
     LEADDATA / SUGGESTIONS handling, table wrapping, and lead capture are
     identical on both paths). Those markers are only looked for at the end —
     they can be split across chunks. */

  function streamingAvailable() {
    return !!(cfg.streamingEnabled &&
              window.fetch &&
              window.ReadableStream &&
              window.TextDecoder);
  }

  /* A temporary bot bubble that grows as chunks arrive. */
  function createStreamingBubble(p) {
    var el = document.createElement('div');
    el.className = 'aichat-message aichat-message--bot';
    el.innerHTML =
      '<div class="aichat-bot-row">' +
        '<div class="aichat-bot-avatar">' + botAvatarHTML() + '</div>' +
        '<div class="aichat-bubble aichat-bubble--bot aichat-bubble--streaming">' +
          '<span class="aichat-stream-text"></span><span class="aichat-caret" aria-hidden="true"></span>' +
        '</div>' +
      '</div>';

    var container = document.getElementById(p + 'aichat-messages');
    if (container) container.appendChild(el);

    var textEl = el.querySelector('.aichat-stream-text');
    scrollBottom(p);

    return {
      append: function (chunk) {
        // textContent, not innerHTML — nothing from the model is treated as
        // markup until the single sanitised parse at the end.
        if (textEl) textEl.textContent += chunk;
        scrollBottom(p);
      },
      destroy: function () {
        if (el.parentNode) el.parentNode.removeChild(el);
      },
    };
  }

  /* Resolves with the complete raw reply text. Rejects on transport or API
     error; callers fall back to the buffered path. */
  function callLLMStreaming(messages, state, onChunk) {
    if (!cfg.ajaxUrl || !cfg.chatNonce) {
      return Promise.reject(new Error('Chat proxy not configured.'));
    }

    syncLeadContext(state);
    var ld = state.leadData || {};

    var form = new FormData();
    form.append('action',           'aichat_proxy_llm');
    form.append('nonce',            cfg.chatNonce);
    form.append('messages',         JSON.stringify(messages));
    form.append('stream',           '1');
    form.append('lead_captured',    state.leadCaptured ? '1' : '');
    form.append('lead_name',        state.userName || ld.name || '');
    form.append('lead_email',       state.userEmail || ld.email || '');
    form.append('lead_phone',       state.userPhone || ld.phone || '');
    form.append('lead_requirement', state.userRequirement || ld.requirement || '');
    if (returningName && !state.userName) form.append('returning_visitor_name', returningName);

    return fetch(cfg.ajaxUrl, { method: 'POST', body: form }).then(function (res) {
      if (!res.ok) throw new Error('HTTP ' + res.status);

      // The server falls back to a buffered JSON reply when streaming isn't
      // possible on this host — honour whichever it actually sent.
      var type = res.headers.get('Content-Type') || '';
      if (type.indexOf('text/event-stream') === -1) {
        return res.json().then(function (data) {
          if (!data || !data.success) throw new Error((data && data.data) || 'API error');
          return data.data;
        });
      }
      if (!res.body || !res.body.getReader) throw new Error('Streaming unsupported.');

      var reader  = res.body.getReader();
      var decoder = new TextDecoder();
      var buffer  = '';   // Incomplete trailing SSE line.
      var full    = '';   // Everything received so far.

      function pump() {
        return reader.read().then(function (result) {
          if (result.done) return full;

          buffer += decoder.decode(result.value, { stream: true });
          var lines = buffer.split('\n');
          buffer = lines.pop(); // Keep the possibly-incomplete last line.

          for (var i = 0; i < lines.length; i++) {
            var line = lines[i].trim();
            if (!line || line.indexOf('data:') !== 0) continue;

            var payload = line.slice(5).trim();
            if (!payload || payload === '[DONE]') continue;

            var frame;
            try { frame = JSON.parse(payload); } catch (e) { continue; }

            if (frame && frame.error) throw new Error(frame.error);
            if (frame && typeof frame.t === 'string' && frame.t) {
              full += frame.t;
              if (onChunk) onChunk(frame.t);
            }
          }
          return pump();
        });
      }

      return pump().then(function (text) {
        if (!text) throw new Error('Empty stream.');
        return text;
      });
    });
  }

  /* ── Analytics tracking ─────────────────────────────────────────────────── */

  function trackMessage(question, answer, triggeredBy) {
    if (!cfg.ajaxUrl || !cfg.trackNonce) return;
    var unanswered = isLowConfidence(answer) ? 1 : 0;

    var form = new FormData();
    form.append('action',        'aichat_track_message');
    form.append('nonce',         cfg.trackNonce);
    form.append('session_id',    sessionId);
    form.append('question',      question);
    form.append('answer',        answer);
    form.append('is_unanswered', unanswered);
    form.append('page_url',      cfg.pageUrl || window.location.href);
    form.append('triggered_by',  triggeredBy || 'manual');

    fetch(cfg.ajaxUrl, { method: 'POST', body: form }).catch(function () {});
  }

  /* ── Transcript ─────────────────────────────────────────────────────────── */

  function buildTranscript(state) {
    return state.allMessages.map(function (m) {
      var sender = (m.role === 'user') ? (state.userName || 'Visitor') : cfg.botName;
      return sender + ': ' + m.content;
    }).join('\n\n');
  }

  /* ── Lead saving (AJAX) ─────────────────────────────────────────────────── */

  function saveLead(state, markSaved) {
    if (!cfg.ajaxUrl || !cfg.nonce) return;
    // Need at least one piece of contact info to be worth saving.
    if (!state.userName && !state.userEmail && !state.userPhone) return;

    var transcript = buildTranscript(state);

    var form = new FormData();
    form.append('action',      'aichat_save_lead');
    form.append('nonce',       cfg.nonce);
    form.append('session_id',  sessionId);
    form.append('name',        state.userName        || '');
    form.append('email',       state.userEmail       || '');
    form.append('phone',       state.userPhone       || '');
    form.append('requirement', state.userRequirement || '');
    form.append('transcript',  transcript);

    fetch(cfg.ajaxUrl, { method: 'POST', body: form })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.success) return;
        if (markSaved) state.leadSaved = true;
        // Remember the name for the next visit (90-day key, separate from the
        // 1-day history key). Name only — email/phone stay server-side.
        if (state.userName) {
          rememberVisitorName(state.userName);
          returningName = state.userName;
        }
      })
      .catch(function () {});

  }

  /* ── Beacon (page unload) ───────────────────────────────────────────────── */

  function sendBeacons(state) {
    if (!state.userName && !state.userEmail && !state.userPhone) return;
    var transcript = buildTranscript(state);

    if (cfg.ajaxUrl) {
      var params = new URLSearchParams();
      params.append('action',      'aichat_save_lead');
      params.append('nonce',       cfg.nonce);
      params.append('session_id',  sessionId);
      params.append('name',        state.userName        || '');
      params.append('email',       state.userEmail       || '');
      params.append('phone',       state.userPhone       || '');
      params.append('requirement', state.userRequirement || '');
      params.append('transcript',  transcript);
      try { navigator.sendBeacon(cfg.ajaxUrl, params); } catch (e) {}
    }
  }

  /* ── marked.js config ───────────────────────────────────────────────────── */

  function configureMarked() {
    if (window.marked && window.marked.setOptions) {
      window.marked.setOptions({ breaks: true, gfm: true });
    }
  }

  /* ── Fresh widget config ─────────────────────────────────────────────────
     Everything baked into the page HTML at render time — nonces AND
     appearance settings (bot name/emoji/icon/colour/welcome message/theme) —
     goes stale on cached pages (WP Rocket, Cloudflare APO, a CDN, etc.): a
     page cached before an admin change keeps serving the OLD values until
     the cache clears, so a saved Settings change can look like it "didn't
     work" even though it saved correctly. Fetch fresh values right away and
     swap them into cfg in place BEFORE building the widget DOM, so what
     renders always reflects the latest saved settings, cached page or not. */

  function refreshWidgetConfig() {
    if (!cfg.ajaxUrl || !cfg.configNonce) return Promise.resolve();
    var form = new FormData();
    form.append('action', 'aichat_get_widget_config');
    form.append('nonce',  cfg.configNonce);
    return fetch(cfg.ajaxUrl, { method: 'POST', body: form })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (!data || !data.success || !data.data) return;
        var d = data.data;
        if (d.nonce)        cfg.nonce        = d.nonce;
        if (d.trackNonce)   cfg.trackNonce   = d.trackNonce;
        if (d.handoffNonce) cfg.handoffNonce = d.handoffNonce;
        if (d.chatNonce)    cfg.chatNonce    = d.chatNonce;
        if (typeof d.botName === 'string')         cfg.botName        = d.botName;
        if (typeof d.botEmoji === 'string')         cfg.botEmoji       = d.botEmoji;
        // botIconUrl legitimately clears back to '' (custom icon removed,
        // falls back to the provider logo or emoji resolved server-side) —
        // always take the fresh value rather than only truthy ones.
        if ('botIconUrl' in d)                      cfg.botIconUrl     = d.botIconUrl;
        if (typeof d.primaryColor === 'string')     cfg.primaryColor   = d.primaryColor;
        if (typeof d.welcomeMessage === 'string')    cfg.welcomeMessage = d.welcomeMessage;
        if (typeof d.leadCaptureMode === 'string')   cfg.leadCaptureMode = d.leadCaptureMode;
        if (typeof d.widgetTheme === 'string')       cfg.widgetTheme    = d.widgetTheme;
        if (typeof d.hideBranding === 'boolean')     cfg.hideBranding   = d.hideBranding;
        // These three were missing here — a cached page's inline config could
        // serve a stale value (e.g. still 'off') indefinitely even after the
        // admin saved 'on', exactly the staleness this refresh exists to fix.
        if (typeof d.proactiveEnabled === 'boolean') cfg.proactiveEnabled = d.proactiveEnabled;
        if (Array.isArray(d.proactiveRules))         cfg.proactiveRules   = d.proactiveRules;
        if (typeof d.streamingEnabled === 'boolean') cfg.streamingEnabled = d.streamingEnabled;
        computeColors();
      })
      .catch(function () {});
  }

  /* ── Init ───────────────────────────────────────────────────────────────── */

  function init() {
    configureMarked();
    refreshWidgetConfig().then(function () {
      buildFloatingWidget();
      buildInlineWidget();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();
