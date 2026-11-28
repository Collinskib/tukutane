# Mobile implementation plan — status

## Architecture (as built)

```
mobile/
  src/app/          Expo Router screens
    (tabs)/         Discover · People · Create · Chats · Plans
    auth, forgot, welcome, complete-profile   onboarding stack
    event/[id], event/edit, event/chat        events
    user/[id], friends, invites               people
    groups, group/[id], group/edit            groups
    venues, venue/[id], venue/edit            businesses
    chat/[id], notifications, profile         messaging/account
  src/components/   ui.tsx (design system), EventCard, LocationPicker, TopBar
  src/lib/          theme.ts (tokens + BASE), api.ts (client), store.tsx (auth)
  docs/             this documentation set
```

Navigation: `Gate` in `_layout.tsx` guards auth/profile-completion and
routes between `auth` → `complete-profile` → `welcome` → `(tabs)`.

## Stack decisions vs. the spec

| Spec asked | Used | Why |
|---|---|---|
| TanStack Query + Zustand | Thin `api()` client + React context (`store.tsx`) | Single endpoint, small surface — the client already handles auth/timeout/errors; stores stay readable. Not opposed to adding Query later for cache semantics. |
| React Hook Form + Zod | Controlled inputs + server-side validation messages | Forms are small; server already validates and returns copy-ready errors. Zod would duplicate rules that must match PHP anyway. |
| SecureStore | AsyncStorage (pending swap) | Token is a low-entropy 64-hex; SecureStore swap is queued (see below). |
| Expo Location | Not used — by design | Platform location is county/area/estate taxonomy; no GPS exists backend-side. Battery-friendly. |
| Expo Notifications | Not yet | Requires backend push-token table + sender — see gaps. |

## Design system

Site parity: cream `#faf8f2`, forest `#163e31`/`#214e3c`, burnt-orange
`#d45c36` (scarce, CTA-only), Playfair Display for headlines, system sans
for body, pill/rounded geometry, EAT + KES formatting. Tokens in
`src/lib/theme.ts`; shared primitives in `src/components/ui.tsx`.

## Phase status

| Phase | Status |
|---|---|
| 1 Discovery & docs | ✅ this document set |
| 2 Architecture & init | ✅ |
| 3 Auth & navigation | ✅ (incl. native Google sign-in) |
| 4 Events/people/groups/businesses | ✅ |
| 5 Messaging & notifications | ◐ messaging ✅ · in-app notifications ✅ · **push ❌** |
| 6 Campus | ➖ excluded by product decision (see FEATURE_INVENTORY) |
| 7 Testing/optimization/security | ◐ Jest infra + first tests added; SecureStore swap queued |
| 8 CI/CD | ◐ Android APK workflow ✅ · iOS scaffold ✅ · ci.yml + release.yml added |

## Remaining work (priority order)

1. **Push notifications** — `push_tokens` table + `push/register` route +
   Expo push transport in `notify()` + `expo-notifications` client
   registration. Largest functional gap.
2. **SecureStore** for the auth token (web fallback to AsyncStorage).
3. **ci.yml / release.yml** — typecheck, lint, tests on PRs; tagged-release
   pipeline.
4. **Test coverage** — Jest + `jest-expo`; start with `api.ts` and store
   logic, grow toward screen tests.
5. **Account deletion** — backend route needed before App Store review.
6. **Native password-reset redeem** — optional; web `reset.php` suffices now.
