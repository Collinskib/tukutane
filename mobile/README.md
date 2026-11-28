# Nipange mobile app

Expo (React Native) app for nipange.co.ke. Talks to the PHP JSON API at
`/api/app.php` on the production site — same backend, same data as the web
app. Theme matches the site (green `#163e31`/`#214e3c`, cream `#faf8f2`,
orange `#d45c36`).

## Layout

- `src/app/` — Expo Router screens (tabs + detail routes)
- `src/components/` — shared UI (`ui.tsx`, `EventCard`, `LocationPicker`, `TopBar`)
- `src/lib/` — theme tokens, API client (`api.ts`), auth store (`store.tsx`), push registration + deep-links (`push.ts`)
- `eas.json` — EAS build profiles (`apk` = downloadable APK, `production`)
- `docs/` — platform audit, feature inventory, API integration, journeys, architecture, setup, build/release, testing

## Run locally

```sh
cd mobile
npm install
npx expo start          # scan QR with Expo Go, or press w for web
```

The API base URL is `src/lib/theme.ts` → `BASE` (defaults to
`https://nipange.co.ke`). Point it at a local PHP server for dev if needed.

## Checks

```sh
npx tsc --noEmit
npx expo lint
npx jest
npx expo-doctor
```

## Push notifications

Device registration is `src/lib/push.ts` → `api/app.php?r=push/register`
(tokens land in the `push_tokens` table). Delivery is a cron outbox:
`notifications.pushed_at` queues new rows and `cron/push.php` drains them to
the Expo push API. One-time setup on the server:

1. Run `migrate-push.php` as admin once (creates `push_tokens`, adds
   `notifications.pushed_at`), then remove it from the docroot.
2. Add `cron/push.php` to cPanel cron alongside `reminders.php` (e.g. every
   minute).
3. Remote push needs a dev/production build — not Expo Go.

## Builds (GitHub Actions)

- `.github/workflows/ci.yml` — typecheck, lint, Jest, expo-doctor + PHP
  syntax on PRs and pushes touching `mobile/**`/`api/**`.
- `.github/workflows/android-apk.yml` — builds the APK on every push to
  `master` touching `mobile/**`, and via manual dispatch. The APK is
  uploaded as a run artifact named `nipange-apk`.
- `.github/workflows/ios-build.yml` — manual scaffold. A signed iOS build
  requires a paid Apple Developer account; the workflow fails fast with
  instructions until then.
- `.github/workflows/release.yml` — tag `mobile-v*` or manual → production
  AAB (+ iOS once Apple credentials exist).

### Required secrets

| Secret | Needed for | Where to get it |
|---|---|---|
| `EXPO_TOKEN` | both workflows | https://expo.dev/settings/access-tokens |
| `APPLE_TEAM_ID` | iOS later | Apple Developer account → Membership |

EAS manages the Android keystore remotely — generated automatically on the
first build, nothing to commit.

### First run

1. Create an Expo account + `EXPO_TOKEN`, add it to repo secrets.
2. Push or dispatch the Android workflow — it runs
   `eas project:init --non-interactive` which creates the project under the
   token's account, then builds.
3. Download `nipange.apk` from the run artifacts (or the Expo dashboard).

## Google sign-in

Native sign-in uses `react-native-nitro-google-signin` (Android Credential
Manager / iOS GIDSignIn) — no browser redirect. The `auth/google` API route
verifies the returned Google `id_token` against `GOOGLE_CLIENT_ID` (web)
plus `GOOGLE_APP_CLIENT_IDS` (Android + iOS) on the server. OAuth clients
live in GCP project `nipange-515310` and are wired into `app.json` →
`expo.extra` (`googleWebClientId`, `googleAndroidClientId`,
`googleIosClientId`). The Android client is bound
to package `ke.co.nipange` + the EAS-managed keystore SHA-1
(`0B:47:30:5D:75:9D:64:AA:F6:30:FF:4D:C3:87:12:AE:07:33:CA:55`); if the
keystore ever changes, create a new Android OAuth client with the new
fingerprint.
