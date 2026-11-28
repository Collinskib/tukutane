# Setup — Nipange mobile

## Prerequisites

- Node.js 20+ (CI uses 24) and npm
- Expo Go app on a physical device, or an Android emulator / iOS simulator
- For dev builds with native modules (Google sign-in): an EAS development
  build, not Expo Go

## Run

```sh
cd mobile
npm install
npx expo start          # scan the QR with Expo Go, or press a/i/w
```

The app talks to the **production** API by default
(`src/lib/theme.ts` → `BASE = https://nipange.co.ke`). To develop against a
local PHP server:

```sh
php -S 127.0.0.1:8000 -t /path/to/tukutane   # repo root
# then set BASE to http://<LAN-IP>:8000 in src/lib/theme.ts
```

(Emulators can't reach `127.0.0.1` on the host — use your machine's LAN IP,
or `10.0.2.2` on the Android emulator.)

## Environment

No secrets ship in the app. `app.json → expo.extra` carries only the public
Google OAuth client IDs (required by the sign-in SDK). The API base URL is a
plain constant in `theme.ts`.

| Value | Location | Secret? |
|---|---|---|
| `BASE` | `src/lib/theme.ts` | no |
| Google client IDs | `app.json → extra` | no (public OAuth identifiers) |
| `EXPO_TOKEN` | GitHub repo secret | yes — CI only |
| `APPLE_TEAM_ID` / ASC key | GitHub repo secret | yes — CI only, iOS later |

If a `.env` is ever introduced, keep real values out of git and mirror the
keys in `.env.example`.

## Checks

```sh
npx tsc --noEmit     # typecheck
npx expo lint        # eslint (eslint-config-expo)
npx jest             # unit tests
npx expo-doctor      # dependency/config health
```

## Google sign-in

Configured for the package `ke.co.nipange`:

- Android native: `react-native-nitro-google-signin` (Credential Manager) —
  needs a dev/production build, not Expo Go.
- Web/Expo Go fallback: `expo-auth-session` against the web client ID.
- Server: `auth/google` verifies the Google credential and issues a Nipange
  Bearer token.
