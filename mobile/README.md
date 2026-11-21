# Nipange mobile app

Expo (React Native) app for nipange.co.ke. Talks to the PHP JSON API at
`/api/app.php` on the production site — same backend, same data as the web
app. Theme matches the site (green `#163e31`/`#214e3c`, cream `#faf8f2`,
orange `#d45c36`).

## Layout

- `src/app/` — Expo Router screens (tabs + detail routes)
- `src/components/` — shared UI (`ui.tsx`, `EventCard`, `LocationPicker`, `TopBar`)
- `src/lib/` — theme tokens, API client (`api.ts`), auth store (`store.tsx`)
- `eas.json` — EAS build profiles (`apk` = downloadable APK, `production`)

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
npx eslint .
npx expo-doctor
```

## Builds (GitHub Actions)

- `.github/workflows/android-apk.yml` — builds the APK on every push to
  `master` touching `mobile/**`, and via manual dispatch. The APK is
  uploaded as a run artifact named `nipange-apk`.
- `.github/workflows/ios-build.yml` — manual scaffold. A signed iOS build
  requires a paid Apple Developer account; the workflow fails fast with
  instructions until then.

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

The `auth/google` API route verifies a Google `id_token` (web client ID
audience). Configure the OAuth client IDs for Expo/Android/iOS in Google
Cloud Console and set them in `src/app/auth.tsx`.
