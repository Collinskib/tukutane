# Build & release — Nipange mobile

All builds run on GitHub Actions via EAS Build — no local Xcode/Android
Studio needed. Workflows live in the repo root `.github/workflows/`.

## Workflows

| Workflow | Trigger | Output |
|---|---|---|
| `ci.yml` | PRs + pushes touching `mobile/**` or `api/**` | typecheck, lint, unit tests, expo-doctor |
| `android-apk.yml` | push to `master` touching `mobile/**`, manual | internal APK artifact `nipange-apk` |
| `ios-build.yml` | manual only | scaffold — fails fast until Apple creds exist |
| `release.yml` | tag `mobile-v*`, manual | production AAB + iOS production build via EAS |

## Secrets

| Secret | Used by | Source |
|---|---|---|
| `EXPO_TOKEN` | all EAS workflows | https://expo.dev/settings/access-tokens |
| `APPLE_TEAM_ID` | iOS | Apple Developer → Membership |
| ASC API key vars | iOS submit (later) | App Store Connect → API keys |

EAS holds the Android keystore remotely (generated on first `apk` build) —
nothing sensitive is committed.

## EAS profiles (`eas.json`)

| Profile | Purpose |
|---|---|
| `development` | dev-client build for native-module testing (e.g. Google sign-in) |
| `apk` | downloadable internal APK — what `android-apk.yml` runs |
| `production` | store builds: AAB for Play, `.ipa` for iOS; `autoIncrement` |

## Releasing

1. CI green on `master`; APK smoke-tested from the workflow artifact.
2. Bump `version` in `app.json` when releasing user-facing changes.
3. Tag `mobile-vX.Y.Z` (or dispatch `release.yml`) → EAS builds AAB + iOS.
4. Play Store: upload the AAB artifact manually for now;
   `eas submit --platform android` can automate once a service-account key
   is added as a secret. iOS: `eas submit --platform ios` → TestFlight once
   the Apple account + ASC key exist.

## Versioning

`cli.appVersionSource: remote` — EAS is the source of truth for build
numbers; `app.json → version` is the user-facing semver.
