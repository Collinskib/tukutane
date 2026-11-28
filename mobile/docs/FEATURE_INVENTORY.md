# Feature inventory — Nipange mobile

Status legend: ✅ implemented & wired to live API · ◐ partial · ❌ not implemented · ➖ N/A (not on platform)

## Authentication & accounts

| Feature | Status | Notes |
|---|---|---|
| Email registration | ✅ | `auth/register` → `auth.tsx`; validates against `AGE_BRACKETS`, county→area resolution server-side |
| Email login / logout | ✅ | `auth/login`, `auth/logout`; token issued/stored locally |
| Google sign-in | ✅ | `auth/google` + `react-native-nitro-google-signin` (Credential Manager on Android; web fallback via expo-auth-session) |
| Password recovery | ✅ | `auth/forgot` → `forgot.tsx`; reset link opens `reset.php` on web |
| Secure session persistence | ◐ | Bearer token in `AsyncStorage`; SecureStore migration pending |
| Profile creation/editing | ✅ | `profile`, `complete-profile`; photo via base64 upload |
| Account settings (password) | ✅ | `password` route, `profile.tsx` |
| Account deletion | ❌ | Not supported by backend (web or API) — needed for App Store |
| Age gate | ✅ | 18+ consent on auth screen; server validates `age_bracket` |

## Discovery & events

| Feature | Status | Notes |
|---|---|---|
| Upcoming events feed | ✅ | `feed?view=discover`, paginated (`page`/`per`) |
| Today / tomorrow / weekend | ✅ | `feed?when=` filters |
| Popular / free / paid | ✅ | `feed?sort=popular`, `feed?cost=` |
| Search + category filter | ✅ | `feed?q=`, `feed?interest=`; category chips |
| Location filter | ✅ | `feed?county=&area=` (taxonomy, not GPS) |
| Price/date filters | ✅ | `cost`, `when` params |
| Event cards & detail | ✅ | `EventCard.tsx`, `event/[id].tsx` — attendees, host, waitlist, siblings |
| Join / leave event | ✅ | `rsvp` join/cancel |
| Waitlist & claim | ✅ | `rsvp` waitlist/unwaitlist/claim/decline_offer |
| Invites (send/respond) | ✅ | `invite`, `my-invites`, rsvp accept/decline; `invites.tsx` |
| Create / edit / cancel event | ✅ | `event-save`, `event-cancel`; `(tabs)/create.tsx`, `event/edit.tsx` |
| Recurring series | ✅ | Series fields on `event`; sibling date picker |
| Save for later (Plans) | ✅ | `save`; `(tabs)/plans.tsx` |
| Share event link | ◐ | Standard RN share of `event.php?id=` URL |
| Event chat | ✅ | `event-chat`, `event-chats`; `event/chat.tsx` + Chats tab |

## People & friends

| Feature | Status | Notes |
|---|---|---|
| People directory/search | ✅ | `people`; `(tabs)/people.tsx` with proximity ordering |
| Public profiles | ✅ | `user`; `user/[id].tsx` |
| Friend requests/accept | ✅ | `friend`, `friends`; `friends.tsx` + header badge |
| Location-based discovery | ✅ | Area/county proximity (taxonomy); **GPS intentionally absent** |
| Follow | ➖ | Platform uses friends, not follows |

## Groups

| Feature | Status | Notes |
|---|---|---|
| Discover/search groups | ✅ | `groups`; `groups.tsx` |
| Group detail, members, posts | ✅ | `group`; `group/[id].tsx` — discussions + voting (`api/group_vote.php`) |
| Create/edit/delete | ✅ | `group-save`, `group?do=delete`; `group/edit.tsx` |
| Join / leave / request | ✅ | `group` do=join/leave/cancel_request (join policies honored) |
| Admin approve/decline/remove | ✅ | `group` do=approve/decline/remove |

## Businesses & venues

| Feature | Status | Notes |
|---|---|---|
| Venue discovery/search | ✅ | `venues`; `venues.tsx` |
| Venue profile + events | ✅ | `venue`; `venue/[id].tsx` |
| Create/edit listing | ✅ | `venue-save`; `venue/edit.tsx` |
| Owner messaging | ✅ | Business owners exempt from friends-only rule in `messages` |

## Messaging & notifications

| Feature | Status | Notes |
|---|---|---|
| Conversation list | ✅ | `conversations`, `event-chats`; `(tabs)/chats.tsx` |
| 1:1 chat, history, send | ✅ | `messages` (polling `after=id`) |
| Read receipts / unread | ✅ | `read_at`, `unread` counts, `mark_conversation_read` |
| Typing indicator | ✅ | `set_typing`/`peer_typing` |
| Image attachments | ✅ | `att_b64` → `api_store_attachment` |
| Audio/video attachments | ✅ | multipart `attach` → `upload_attachment`; expo-audio recording, expo-video playback |
| View-once media | ✅ | `view_once` flag; gated `r=media` route streams once to recipient then 410s |
| Mute conversation | ✅ | `do=mute` |
| In-app notifications | ✅ | `notifications` list + mark-read; `notifications.tsx` |
| **Push notifications** | ✅ | Expo push → FCM; token register on boot/login, inline reply, deep links |

## Campus/institutions

❌ **Excluded by product decision** — implemented then removed
(`61192a0`→`6f7eaa7`, `b512c4b`). Master spec section 4 is superseded by
`docs/PRODUCT_VISION.md` ("universities get their own app").
