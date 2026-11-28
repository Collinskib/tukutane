# User journeys — Nipange mobile

Each journey lists the screens and API routes that carry it. ✅ = works end
to end on the live backend.

## 1. New visitor

`welcome.tsx`/`auth.tsx` → register (`auth/register`) → `complete-profile.tsx`
(`complete-profile`) → `welcome.tsx` → `(tabs)/discover`. Logged-out users
are redirected to `/auth` by the `Gate` in `_layout.tsx`. ✅

## 2. Registered user returning

App boot → `AuthProvider` reads stored token → `profile` confirms session →
lands on `(tabs)/discover`. 401/403 clears the token back to `auth.tsx`;
network failures keep it. ✅

## 3. Discovering nearby events

`discover.tsx` → `feed?view=discover&county=&area=&q=&when=&cost=` →
`EventCard` → `event/[id].tsx` (`event?id=`) → join via `rsvp`. ✅

## 4. Creating an event

`(tabs)/create.tsx` → pick venue (or free-text location), cover photo
(`expo-image-picker` → base64), date/time (`datetimepicker`), optional
series → `event-save` → event appears on web `index.php` feed immediately. ✅

## 5. Joining an event

`event/[id].tsx` → `rsvp {action:'join'}` (or waitlist when full:
`waitlist` → `claim` when promoted) → host notified via `notify()` →
event chat unlocked (`canChat`). ✅

## 6. Discovering & contacting a person

`(tabs)/people.tsx` (`people`) → `user/[id].tsx` (`user`) → friend request
(`friend`) → once accepted, `conversation` → `chat/[id].tsx` (`messages`).
Business owners can be messaged without friendship. ✅

## 7. Groups

`groups.tsx` (`groups`) → `group/[id].tsx` (`group`) → join/request per
`joinPolicy` → admin approves (`do=approve`) → post/vote in discussions
(`group_vote.php`). Create/edit via `group/edit.tsx` → `group-save`. ✅

## 8. Business owner managing a listing

`venue/edit.tsx` → `venue-save` (create/edit + photo) → venue visible in
`venues.tsx` and as a pickable location in event creation; `venue/[id].tsx`
lists the venue's upcoming events. ✅

## 9. Messaging

`(tabs)/chats.tsx` merges `conversations` + `event-chats` with unread badges
→ `chat/[id].tsx` polls `messages?conv=&after=` → send text/image
(`att_b64`), typing flag (`do=typing`), mute (`do=mute`), read receipts
(`read`). ✅

## 10. Managing the account

`profile.tsx` → edit profile/photo (`profile`), change password
(`password`), view friends (`friends.tsx`), invites (`invites.tsx`),
notifications (`notifications.tsx`), report users/events (`report`),
logout (`auth/logout`). Account deletion: ❌ not supported anywhere yet.

## Cross-platform sync guarantees

Same tables serve both clients: an event created in-app appears on the web
feed; an RSVP on the web reflects in-app; messages sync both ways; one
`users` table, one `conversations`/`messages` pair, one `gatherings` set.
