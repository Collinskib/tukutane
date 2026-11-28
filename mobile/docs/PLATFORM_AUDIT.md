# Platform audit — Nipange (nipange.co.ke)

Audited against the production PHP codebase in this repository (server-rendered
PHP 8.3 + MySQL, deployed to cPanel/Apache). Last verified: 2026-11-28.

## Backend

| Concern | Reality |
|---|---|
| Stack | PHP 8.3, server-rendered, no build step. MySQL via PDO (`includes/db.php`). |
| Auth (web) | PHP sessions; `users` table, `register_user()` in `includes/auth.php`. Google OAuth via `oauth-google.php`. |
| Auth (mobile) | `Authorization: Bearer <64-hex>` against `api_tokens` (`sha256` hash stored, `expires_at`, `last_used`). Issued by `api/app.php` auth routes; bootstrapped in `api/_app_boot.php`. |
| Media | `uploads/` on the docroot; `api_store_image()` accepts base64 in JSON bodies; `img.php` serves sized images. |
| Notifications | `notifications` table + `notify()` helper (in-app only — **no push transport**). |
| Messaging | `conversations` + `messages` tables; polling endpoint (`messages?conv=X&after=Y`); typing flags, read receipts, mute, attachments. No websocket/SSE. |
| Location model | Taxonomy, not GPS: `county → area → estate` (Kenyan counties). "Nearby" = same/similar area, sorted by `people.php` proximity order — **no lat/lng anywhere**. |
| Payments | None. `cost` is an integer (KES) displayed on events; no payment processing. |
| Age gate | 18+ enforced via `AGE_BRACKETS` (`18-24` … `50+`) at registration + consent line on auth screens. |
| Moderation | `report` route → `reports` table; `block_user()`/`is_blocked()`/`blocked_list()`; admin area (`admin/`). |

## JSON API (`api/app.php`)

Single entry point: `/api/app.php?r=<route>`. JSON POST bodies are merged into
`$_POST` so web helpers are reused unchanged. Auth is optional on read routes,
required (`api_need()`) on mutating/member routes. Same session helpers back
both web and API — one backend, one dataset.

Routes: `meta`, `auth/login`, `auth/register`, `auth/google`, `auth/forgot`,
`auth/logout`, `feed`, `event`, `rsvp`, `save`, `event-save`, `event-cancel`,
`invite`, `my-invites`, `event-chat`, `event-chats`, `people`, `user`,
`friend`, `friends`, `conversation`, `conversations`, `messages`, `groups`,
`group`, `group-save`, `venues`, `venue`, `venue-save`, `notifications`,
`profile`, `complete-profile`, `password`, `report`.

Auxiliary endpoints also used by the web app: `api/messages.php`,
`api/notifications.php`, `api/event_chat.php`, `api/group_vote.php`,
`api/suggest.php`.

## Feature areas (web → API → mobile)

| Area | Web | API route(s) | Mobile screen(s) |
|---|---|---|---|
| Auth & onboarding | `auth.php`, `oauth-google.php`, `forgot.php`, `reset.php`, `welcome.php`, `complete-profile.php` | `auth/*`, `complete-profile`, `profile` | `auth.tsx`, `forgot.tsx`, `welcome.tsx`, `complete-profile.tsx` |
| Discovery feed | `index.php` (`view=discover`) | `feed` | `(tabs)/discover.tsx` |
| Events | `event.php`, `create.php`, `edit.php`, `rsvp.php`, `save.php` | `event`, `event-save`, `event-cancel`, `rsvp`, `save` | `event/[id].tsx`, `event/edit.tsx`, `(tabs)/create.tsx`, `(tabs)/plans.tsx` |
| Recurring series | `gathering_series` | `event` (`siblings`, `seriesId`, `seriesFreq`) | `event/[id].tsx` |
| Waitlists & invites | `rsvp.php` waitlist ops | `rsvp` actions, `invite`, `my-invites` | `event/[id].tsx`, `invites.tsx` |
| Event group chats | `event.php` chat tab | `event-chat`, `event-chats` | `event/chat.tsx`, `(tabs)/chats.tsx` |
| People | `people.php`, `user.php` | `people`, `user` | `(tabs)/people.tsx`, `user/[id].tsx` |
| Friends | `friend.php`, `friends.php` | `friend`, `friends` | `friends.tsx` |
| Groups | `groups.php`, `group.php`, `group-edit.php` | `groups`, `group`, `group-save`, `group_vote.php` | `groups.tsx`, `group/[id].tsx`, `group/edit.tsx` |
| Venues/businesses | `venues.php`, `venue.php`, `venue-edit.php`, `venue-plan.php` | `venues`, `venue`, `venue-save` | `venues.tsx`, `venue/[id].tsx`, `venue/edit.tsx` |
| Messaging | `messages.php` | `conversation`, `conversations`, `messages` | `(tabs)/chats.tsx`, `chat/[id].tsx` |
| Notifications | `api/notifications.php` + header bell | `notifications` | `notifications.tsx` |
| Profile/settings | `profile.php`, `user.php` | `profile`, `password`, `complete-profile` | `profile.tsx` |
| Safety | `report.php`, block UI | `report` | report buttons on `user/[id].tsx`, `event/[id].tsx` |
| Legal | `about.php`, `privacy.php`, `terms.php`, `guidelines.php` | — (web pages) | not in app — consent text links out |
| Admin | `admin/` | — | out of scope |

## Backend gaps relevant to mobile

1. **Push notifications** — `notify()` writes in-app rows only. No device-token
   table, no registration route, no outbound push sender. Required by spec;
   needs a `push_tokens` table + `push/register` route + Expo push transport.
2. **Real-time transport** — none. Chat uses short-interval polling
   (`messages_since … after=id`), which already works; acceptable for current
   scale but noted as a scale limit.
3. **Password reset deep-linking** — `auth/forgot` sends a `reset.php` link
   that opens on the web. Acceptable, but a native reset screen would need a
   token-redeem route.
4. **Account deletion** — no self-serve deletion on web or API. Store
   submission (Apple) will likely require adding it.
5. **GPS/venue maps** — platform is taxonomy-based; no coordinates to consume.
6. **Institutions/campus** — implemented and then deliberately removed
   (commits `61192a0` → `6f7eaa7`, `b512c4b`: "universities get their own
   app"). The master spec asks for campus support; the current product
   direction excludes it. **Not implemented by design.**
