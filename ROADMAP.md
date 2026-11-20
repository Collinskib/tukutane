# Nipange Feature Roadmap

Status of the feature brief (`Tukutane_App_Feature_Brief.docx`, from George Ng'ang'a, 18 Nov 2026)
against what is live on https://nipange.co.ke.

Legend: ✅ shipped · 🚧 partial · ⬜ not started

## Release 1 — Friends and Local Events

| # | Feature | Status |
|---|---------|--------|
| 1 | Accounts & profiles (signup, display name, photo, bio, visibility toggle) | ✅ |
| 2 | Interests (choose/update in profile) | ✅ |
| 3 | Neighbourhood selection & browsing-area switching | ✅ |
| 4 | People Nearby (area + interest filters, no exact location exposed) | ✅ |
| 5 | Friend requests (send/accept/decline/cancel/remove) | ✅ |
| 6 | Direct messages between friends, mute, block, report | ✅ (mute + message reporting added 20 Nov) |
| 7 | Events Nearby (search, area/interest/date/free-paid, Today & Weekend) | ✅ |
| 8 | Event details & RSVPs (capacity cap, save, cancel RSVP) | ✅ |
| 9 | Create/edit/cancel gatherings (invite-only supported) | ✅ |
| 10 | In-app invitations + WhatsApp share links | ✅ |
| 11 | My Plans + notifications (requests, messages, invites, reminders, cancellations, venue/time changes) | ✅ (edit-change notifications added 20 Nov) |
| 12 | Reporting & admin moderation dashboard | ✅ |

Launch requirement (shared accounts/DB across devices): ✅

Polish shipped 20 Nov: search suggestions on Discover, typing indicators in DMs,
Google sign-in, privacy/terms pages.

## Release 2 — Interest Groups and Event Coordination

| # | Feature | Status |
|---|---------|--------|
| 13 | Local interest groups (create/discover/join/leave; open + approval-required) | ✅ |
| 14 | Recurring gatherings (weekly/monthly series, per-date RSVP, edit/cancel one or series) | ✅ |
| 15 | Event group chat (hosts + confirmed attendees, pinned updates, mute/report) | ✅ |
| 16 | Waitlists (auto-offer next person with accept deadline, pass on expiry) | ✅ |

Release 2 shipped 20 Nov. Notable fixes en route: friend-action redirects were
broken (chopped referer path → wrong page after every friend action); event chat
access checks both RSVP state and host-block relationships.

## Release 3 — Organizers, Venues and Paid Events

| # | Feature | Status |
|---|---------|--------|
| 17 | Organizer profiles (bio, upcoming/past events, follow + new-event notifications) | ⬜ |
| 18 | Venue profiles (location, photos, upcoming events; hosts link gatherings) | ⬜ |
| 19 | M-Pesa ticket payments (Daraja), tickets on confirmed payment, refunds | ⬜ |

## Platform / business items (from chat)

- Native Android app on Play Store — deferred; PWA shipped (needs $25 one-time Play Store dev account).
- iOS App Store — skipped for now ($100/yr); PWA covers iPhone users.
- Subscriptions monetization — deferred until user-count threshold; ads possibly later.
- Performance: "loads quickly / smoothly" is a standing requirement — keep pages lightweight.
