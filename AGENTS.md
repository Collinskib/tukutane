# Tukutane — agent guide

**Product direction lives in [`docs/PRODUCT_VISION.md`](docs/PRODUCT_VISION.md)** —
read it before making product or design decisions. Nipange is a local discovery
platform for people, businesses and event organizers; never present unbuilt
capabilities as live features.

Server-rendered PHP app (no build step). Pages are `*.php` at the root, shared
markup lives in `includes/layout.php`, styling is a single hand-written
stylesheet `assets/style.css`, light interactivity via Alpine.js
(`assets/alpine.min.js`) and `assets/app.js`. Deployed to cPanel/Apache + MySQL.

## Deploys

Pushing to `master` on GitHub deploys automatically: a repo webhook POSTs to
`https://nipange.co.ke/deploy-hook.php`, which verifies the
`X-Hub-Signature-256` HMAC against `~/.deploy-hook-secret` (server-only file,
not committed) and runs `~/repositories/tukutane/deploy.sh` under a lock.
`deploy.sh` does `git fetch` + `reset --hard origin/master` then copies files
to `~/public_html/` — it mirrors `.cpanel.yml` (keep them in sync); cPanel's
own deploy flag is stuck at `deployable:0` on this host, so the hook bypasses
it. Deploy log: `~/deploy-hook.log`. `deploy.sh`/`deploy-hook.php` are the
only deploy machinery; `deploy.sh` is not copied into the docroot.

## Design system — "Nipange" (MANDATORY for all UI work)

All visual decisions are already made. Do not invent new design language.

**Palette** — use the CSS custom properties in `assets/style.css` `:root`, never raw hex:
`--bg` cream `#faf8f2` page background · `--green-1/2/3` deep forest greens
(primary surfaces/buttons) · `--accent` burnt orange `#d45c36` (CTAs, badges,
active states only — it is scarce on purpose) · `--cream`, `--mist`, `--line`,
`--ink`, `--ink-soft`. Danger `#a33a1f`, warn `#f0e0bb`/`#6b5410`.

**Typography** — `var(--serif)` (Georgia) for display: `.brand`, `h1–h3`, `.form`
headings, `.person .name`, quotes — set at `font-weight:400`, large sizes,
tight leading (~1.0). `var(--sans)` (system-ui) for everything else. Never add
web fonts.

**Shape** — pill geometry everywhere: `--r-pill` for buttons/chips/badges/nav,
`--r-card` 16px cards, `--r-hero` 22px hero/covers, `--r-input` 12px inputs,
`--r-dialog` 18px. Soft 1px `--line`/`--line-soft` borders + `--shadow`
(low-spread green-tinted) — never heavy black shadows.

**Motion** — fast and felt, not seen: `--dur-fast` 140ms (press/hover),
`--dur-ui` 220ms (state changes), `--dur-sheet` 320ms (drawers). Curves:
`--ease-out` for entrances/hovers, `--ease-drawer` for sheets. Animate only
`transform`/`opacity`. Press feedback = `scale(.95–.98)`, hover =
`translateY(-1…-3px)` + shadow (guard with `@media(hover:hover) and
(pointer:fine)`). Respect `prefers-reduced-motion`.

**Component vocabulary** — reuse before creating: `.btn-block`, `.btn-ghost`,
`.btn-mini`, `.btn-danger`, `.btn-warn`, `.chip` (`.on`), `.checkrow` pills,
`.card` + `.ribbon`, `.person`, `.tag`, `.status-pill`, `.form` + `.field`,
`.tabs`, `.homepills`, `.ev-meta .m`, `.hostcard`, `.chat-layout`/`.conv-list`,
`.toast`, `.dropdown`, `.avatar` (+`.ph` `.sm` `.lg`), `.verifybar`.

**Voice** — warm Kenyan community feel: editorial serif headlines, generous
whitespace, real photos in `.ribbon.cover`/`.scene`/`.ev-cover` with
green-tinted gradients, emoji used sparingly in pills/nav.

## Banned (the generic-AI look)

No purple/blue gradients, no indigo/blue primary buttons, no Tailwind/shadcn
defaults, no Inter/Roboto imports, no glassmorphism outside the existing
backdrop-blur header/nav, no drop shadows with pure black, no square corners on
interactive elements, no lorem ipsum, no new frameworks (no Tailwind, Bootstrap,
React) — PHP + hand CSS + Alpine only.

## UI workflow for agents

1. Read `assets/style.css` and a comparable existing page before writing markup.
2. Reuse token vars and component classes; add new CSS only for genuinely new
   components, in the same single-line, section-commented style.
3. After changing a page, render it (PHP's built-in server works:
   `php -S localhost:8000`), screenshot at ~1280px and ~390px, and critique the
   result against this file before declaring done.
4. Keep PHP escaping via `e()` and reuse `layout.php` helpers (`page_head`,
   `site_header`, `location_picker`, …).
