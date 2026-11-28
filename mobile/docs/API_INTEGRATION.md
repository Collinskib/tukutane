# API integration — Nipange mobile

## Overview

The app talks to a single JSON endpoint on the production site:

```
{BASE}/api/app.php?r=<route>[&query=params]
```

`BASE` is defined in `src/lib/theme.ts` (default `https://nipange.co.ke`).

- **GET** for reads, **POST** (`Content-Type: application/json`) for writes.
- Auth: `Authorization: Bearer <64-hex token>` when a session exists.
- Errors: non-2xx or `{ "error": "…" }` → thrown as `ApiError` (`.status`).
- Client: `api()` in `src/lib/api.ts` — 20s `AbortController` timeout,
  auth header injection, error normalization. Screens never call `fetch`
  directly.

## Auth flow

```
POST r=auth/login   {email, password} → {ok, token, user, incomplete}
POST r=auth/register {email,password,name,county,gender,age_bracket,fun_fact}
POST r=auth/google  {id_token | access_token, …} → same shape
POST r=auth/forgot  {email} → {ok}        (reset link goes to web reset.php)
POST r=auth/logout  (Bearer) → revokes the token row
GET  r=profile      (Bearer) → {user, incomplete}
```

Tokens are opaque 64-hex; server stores `sha256(token)` in `api_tokens` with
`expires_at`/`last_used`. The app keeps the token locally
(`AsyncStorage` → pending `SecureStore`), attaches it to every request, and
only clears it on 401/403 — transient network failures keep the session.

`GET r=meta` returns the vocab the app needs to render pickers:
`categories`, `counties`, `genders`, `ages`, `areas` (nested
county→area→estates), `venues`.

## Route reference

| Route | Method | Auth | Purpose | Key params |
|---|---|---|---|---|
| `meta` | GET | — | Taxonomies + venue list | — |
| `feed` | GET | opt | Event discovery/mine/hosted/saved | `view, interest, county, area, q, when, cost, sort, page, per` |
| `event` | GET | opt | Event detail | `id` → event, host, attendees, waitlist, myInvite, canChat, pinned, siblings, friends, invited |
| `rsvp` | POST | ✓ | Join/cancel/waitlist/invite responses | `id`, `action` = join\|cancel\|waitlist\|unwaitlist\|claim\|decline_offer\|accept_invite\|decline_invite |
| `event-save` | POST | ✓ | Create or edit event | `id` (omit = create), `title, category, event_at, cost, capacity, description, cover_b64, venue_id, audience, invite_only, series fields` |
| `event-cancel` | POST | ✓ | Cancel owned event | `id` |
| `save` | POST | ✓ | Toggle plan/save | `id` → `{saved}` |
| `invite` | POST | ✓ | Invite a user to an event | `id`, `user` |
| `my-invites` | GET | ✓ | Pending invites | — |
| `event-chat` | GET/POST | ✓ | Per-event group chat | `id`, `after`; POST `body`, `att_b64` |
| `event-chats` | GET | ✓ | My event chats w/ unread | — |
| `people` | GET | ✓ | People directory | `q`, `county`, `area`, proximity-ordered |
| `user` | GET | ✓ | Public profile | `id` |
| `friend` | POST | ✓ | Friend request/accept/etc. | `id`, `do` |
| `friends` | GET | ✓ | Friends + pending | — |
| `conversation` | POST | ✓ | Get-or-create 1:1 conv | `user` → `{id}` |
| `conversations` | GET | ✓ | Conv list w/ unread/muted | — |
| `messages` | GET/POST | ✓ | History + send | `conv`, `after`; POST `do`=typing\|mute, `body`, `att_b64` or multipart `attach`, `view_once` |
| `media` | GET | ✓ | Stream view-once attachment | `m` (message id); 410 after first recipient fetch |
| `groups` | GET | ✓ | Group directory | `view, category, county, area, q` |
| `group` | GET/POST | ✓ | Detail + membership ops | `id`; POST `do`=join\|leave\|cancel_request\|approve\|decline\|remove\|delete |
| `group-save` | POST | ✓ | Create/edit group | `id?`, `name, category, county, area, description, join_policy` |
| `venues` | GET | ✓ | Venue directory | `county, area, q` |
| `venue` | GET | ✓ | Venue detail + events | `id` |
| `venue-save` | POST | ✓ | Create/edit venue | `id?`, `name, county, area, estate, description, photo_b64` |
| `notifications` | GET/POST | ✓ | List; POST marks all read | `count` for badge |
| `profile` | GET/POST | ✓ | Read/update self | `photo_b64`, fields |
| `complete-profile` | POST | ✓ | Onboarding completion | location/gender/age/interests |
| `password` | POST | ✓ | Change password | `current`, `new` |
| `report` | POST | ✓ | Report user/event | `type`, `id`, `reason` |

## Media

Images are uploaded as base64 in the JSON body (`photo_b64`, `cover_b64`,
`att_b64` + `att_name`) and stored by `api_store_image()` /
`api_store_attachment()` under `uploads/`. Responses return the bare filename;
the app builds URLs via `imgUrl()` → `{BASE}/uploads/<file>`.

Chat media larger than the 10MB base64 cap (voice notes, videos) goes up as
`multipart/form-data` via `apiUpload()` — PHP populates `$_FILES['attach']` and
`upload_attachment()` handles per-kind caps (image 10MB, audio 15MB, video
60MB). View-once attachments (`view_once=1`) are stored in `uploads-private/`
(deny-all `.htaccess`) and only stream through `r=media&m=<id>`, which marks
`consumed_at` on the first authorized fetch by the recipient — sender and
repeat fetches get 410.

## Real-time model

No sockets. Chats poll `messages?conv=X&after=<lastId>` on a short interval
and receive `{items, typing, other, muted}` — incremental, cheap, and already
proven on the web app. Notification badges poll `notifications?count=1`.

## Auth/permission rules enforced server-side

- Friends-only messaging (except business owners).
- Invite-only events hidden from public feed unless creator/invited.
- Edit/delete restricted to creators (`403` otherwise).
- `is_blocked()` enforced on message send.
- `api_need()` → 401 on member routes without a valid token.

## Known gaps

See `PLATFORM_AUDIT.md` → "Backend gaps": push transport, native reset
deep-link, self-serve account deletion.
