# Architecture — Nipange mobile

## Big picture

```
┌──────────────┐   HTTPS + Bearer    ┌──────────────────┐   PDO    ┌───────┐
│  Expo app    │ ──────────────────▶ │ api/app.php      │ ───────▶ │ MySQL │
│  (this dir)  │ ◀────── JSON ────── │ (PHP, docroot)   │          │       │
└──────────────┘                     └──────────────────┘          └───────┘
        │                                     │
   src/lib/api.ts                    api/_app_boot.php: token auth,
   single fetch client               JSON body → $_POST merge,
                                     then shared includes/db.php helpers
```

One backend serves web and mobile: the API layer is a thin adapter over the
existing `includes/db.php`/`helpers.php` functions — no duplicated business
logic, no second database.

## Layers

| Layer | Files | Responsibility |
|---|---|---|
| Routes/screens | `src/app/**` | Expo Router file-based screens + layouts. Thin: load data, render, call `api()`. |
| Components | `src/components/` | `ui.tsx` primitives (buttons, cards, chips, sheets, skeletons, empty/error states), `EventCard`, `LocationPicker`, `TopBar`. |
| API client | `src/lib/api.ts` | `api(route, params, body)` — query building, Bearer header, 20s timeout, `ApiError` normalization, `imgUrl()`. Token storage helpers (`getToken`/`setToken`). |
| Auth store | `src/lib/store.tsx` | `AuthProvider`/`useAuth`: user, meta taxonomies, boot sequence (token → `profile`), `applyAuth`, `logout`, `incomplete`/`justJoined` flags for the gate. |
| Theme | `src/lib/theme.ts` | `C` color tokens, `BASE` URL, typography, shared spacing/radius. |

## Navigation model

- `_layout.tsx` → `AuthProvider` → `Gate` watches `useAuth()` + segments:
  - no user → `/auth`
  - user + incomplete profile → `/complete-profile`
  - new registration → `/welcome`, else `/(tabs)/discover`
- `(tabs)/_layout.tsx` → 5 tabs: **Discover, People, Create, Chats, Plans**.
  Create is a raised center action. Chats shows a live unread badge.
- Everything else is pushed on the stack (`event/[id]`, `chat/[id]`, …).

## Data flow

- Server state: screens call `api()` in `useEffect` + pull-to-refresh
  handlers; lists paginate via `feed` `page`/`per`.
- Chat: interval polling `messages?conv=X&after=<lastId>` — server returns
  only newer rows plus `typing`/`muted`/peer info.
- Badges: `notifications?count=1` and conversation/event-chat unread sums.
- Auth state lives in `AuthProvider`; screens read `useAuth()` — no prop
  drilling, no duplicated session logic.

## Auth & security

- Bearer token (64-hex) issued by `auth/*` routes; server stores only
  `sha256(token)` in `api_tokens` with expiry + last-used.
- Client stores the token locally (AsyncStorage → SecureStore swap queued)
  and clears it **only** on 401/403 — flaky networks don't log users out.
- Photos/files go up as base64 in JSON; server validates + writes to
  `uploads/`, responses carry filenames resolved by `imgUrl()`.
- All permission checks (friends-only messaging, creator-only edits,
  invite-only visibility, blocks) live server-side in shared helpers.

## Why not the spec's heavier stack

- **TanStack Query/Zustand**: one endpoint, one auth store — a 60-line
  typed client plus context is smaller and already provides timeout,
  retry-on-focus patterns, and cache-free freshness semantics the app's
  data needs. Adding Query later is a non-breaking refactor if cache
  semantics become valuable.
- **RHF/Zod**: forms are short; server-side validation already returns
  user-facing error strings that must match PHP rules regardless.

## Native capabilities

| Capability | Module | Status |
|---|---|---|
| Images (picker) | `expo-image-picker` | ✅ event covers, venue/group photos, chat attachments |
| Documents | `expo-document-picker` | ✅ chat file attachments |
| Date/time | `@react-native-community/datetimepicker` | ✅ event scheduling |
| Google sign-in | `react-native-nitro-google-signin` | ✅ native (dev/prod builds); `expo-auth-session` fallback |
| Push notifications | `expo-notifications` | ❌ pending backend push support |
| GPS | `expo-location` | ➖ not needed — taxonomy location model |
| Fonts | `@expo-google-fonts/playfair-display` | ✅ display serif |
