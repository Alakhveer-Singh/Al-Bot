// ── Where every "Join the waitlist" button points. Swap for a real form later. ──
const WAITLIST_URL = "https://discord.gg/enRfBFpjb7";

// The scripted hero conversation. No real AI calls: this only plays back.
const SCRIPT = [
  { bot: "Hi there 👋 I'm Leafy, the assistant for Green Leaf Interiors. Ask me anything about our work." },
  { user: "Do you design modular kitchens?" },
  { bot: "Yes! We design, build and install modular kitchens, from the first sketch to the final fitting. Most kitchens take 3 to 4 weeks, and every project starts with a free site visit.",
    chips: ["See kitchen styles", "What does it cost?", "Book a site visit"] },
  { chip: "Book a site visit" },
  { bot: "Happy to set that up. May I have your name?" },
  { user: "Priya Sharma" },
  { bot: "Thanks, Priya! What's the best email to reach you on?" },
  { user: "priya@example.com" },
  { bot: "Got it. And what would you like done? A rough size and timeline helps our designers." },
  { user: "An L-shaped kitchen, about 10×12 ft. Hoping to start next month." },
  { bot: "Lovely. I've passed this to our design team, and they'll email you within a day to fix a visit time." },
  { lead: true },
];

const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;

(() => {
  document.documentElement.classList.remove('no-js');
  document.querySelectorAll('[data-waitlist]').forEach(a => { a.href = WAITLIST_URL; });

  // theme (shared key with alakhveer.com)
  const root = document.documentElement, tb = document.getElementById('themeBtn');
  const syncTheme = () => tb.setAttribute('aria-label', root.dataset.theme === 'light' ? 'Switch to dark theme' : 'Switch to light theme');
  syncTheme();
  tb.addEventListener('click', () => {
    root.dataset.theme = root.dataset.theme === 'light' ? 'dark' : 'light';
    try { localStorage.setItem('theme', root.dataset.theme); } catch (e) {}
    syncTheme();
  });

  const nav = document.getElementById('nav');
  const onScroll = () => nav.classList.toggle('scrolled', scrollY > 20);
  addEventListener('scroll', onScroll, { passive: true }); onScroll();

  // ── reveal on scroll ──
  const io = new IntersectionObserver(es => es.forEach(en => {
    if (!en.isIntersecting) return;
    en.target.classList.add('in'); io.unobserve(en.target);
  }), { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
  document.querySelectorAll('.rv').forEach(el => io.observe(el));

  // ── chat demo ──
  const msgs = document.getElementById('msgs'), chips = document.getElementById('chips');
  const field = document.getElementById('field'), fieldText = document.getElementById('fieldText');
  const sendBtn = document.getElementById('sendBtn'), leadCard = document.getElementById('leadCard');
  const stage = document.getElementById('demo');
  let run = 0, played = false, done = false;

  const add = (who, text) => {
    const li = document.createElement('li');
    li.className = who;
    li.innerHTML = '<div class="bub"></div>';
    li.firstChild.textContent = text;
    msgs.append(li);
    msgs.scrollTop = msgs.scrollHeight;
    return li;
  };
  const setChips = list => {
    chips.innerHTML = '';
    (list || []).forEach(t => { const s = document.createElement('span'); s.textContent = t; chips.append(s); });
    msgs.scrollTop = msgs.scrollHeight;
  };
  const reset = () => {
    msgs.innerHTML = ''; setChips(); fieldText.textContent = '';
    field.classList.remove('on'); leadCard.classList.remove('show');
  };

  // Show the finished conversation at once (reduced motion)
  const renderAll = () => {
    reset();
    SCRIPT.forEach(s => {
      if (s.bot) add('b', s.bot);
      if (s.user || s.chip) add('u', s.user || s.chip);
    });
    leadCard.classList.add('show');
    msgs.scrollTop = msgs.scrollHeight;
    done = true;
  };

  async function play() {
    const id = ++run;
    const wait = ms => new Promise((ok, no) => setTimeout(() => (id === run ? ok() : no()), ms));
    reset(); done = false; played = true;
    try {
      await wait(500);
      for (const s of SCRIPT) {
        if (s.bot) {
          const t = add('b', '');
          t.firstChild.className = 'bub typing';
          t.firstChild.innerHTML = '<i></i><i></i><i></i>';
          await wait(Math.min(1700, 700 + s.bot.length * 7));
          t.firstChild.className = 'bub';
          t.firstChild.textContent = s.bot;
          msgs.scrollTop = msgs.scrollHeight;
          if (s.chips) { await wait(250); setChips(s.chips); }
          await wait(900);
        } else if (s.chip) {
          const c = [...chips.children].find(x => x.textContent === s.chip);
          if (c) c.classList.add('hit');
          await wait(450);
          setChips(); add('u', s.chip); await wait(300);
        } else if (s.user) {
          field.classList.add('on');
          for (const ch of s.user) { fieldText.textContent += ch; await wait(26 + Math.random() * 30); }
          await wait(250);
          sendBtn.classList.add('hit'); await wait(130); sendBtn.classList.remove('hit');
          fieldText.textContent = ''; field.classList.remove('on');
          add('u', s.user); await wait(300);
        } else if (s.lead) {
          await wait(400); leadCard.classList.add('show');
        }
      }
      done = true;
    } catch (e) { /* cancelled by a newer run */ }
  }

  const start = () => (reduce ? renderAll() : play());
  // Play when the demo comes into view; replay when scrolled back to it
  let away = false;
  new IntersectionObserver(([en]) => {
    if (en.isIntersecting) {
      if (!played || (away && !reduce)) start();
      away = false;
    } else if (played) {
      away = true;
      if (!done) run++; // stop the half-finished run; it restarts on return
    }
  }, { threshold: 0.35 }).observe(stage);
  document.getElementById('replay').addEventListener('click', start);
  document.getElementById('seeIt').addEventListener('click', () => { if (played) setTimeout(start, reduce ? 0 : 500); });

})();
