# Testing — Nipange mobile

## Stack

- **Jest** + `jest-expo` preset for unit tests (`.test.ts(x)` next to source,
  or under `__tests__/`).
- `tsc --noEmit`, `expo lint`, `expo-doctor` as static gates — all run in
  `ci.yml` on every PR/push touching `mobile/**` or `api/**`.
- Manual/E2E: APK artifacts from `android-apk.yml` are the smoke-test build;
  full Detox/Maestro E2E is not yet set up (noted in the plan).

## What the unit tests cover

| Area | File | Coverage |
|---|---|---|
| API client | `src/lib/__tests__/api.test.ts` | query building, GET vs POST, Bearer header, `ApiError` on `{error}`/non-2xx, timeout/network→friendly message, SecureStore token set/get/clear + legacy AsyncStorage migration |
| Push deep-links | `src/lib/__tests__/push.test.ts` | web notification links → app routes (`event.php?id=` → `/event/N`, etc.) |

## Running

```sh
cd mobile
npx jest                 # unit tests
npx tsc --noEmit         # typecheck
npx expo lint            # lint
npx expo-doctor          # config health
```

## Testing the real backend

Unit tests mock `fetch`; they don't prove API behavior. For integration
confidence:

1. Point `BASE` (`src/lib/theme.ts`) at a local `php -S 127.0.0.1:8000`
   serving the repo — the API runs against your dev MySQL.
2. Exercise journeys in `USER_JOURNEYS.md` — each lists the exact routes.
3. Error paths to exercise manually: expired token (delete `api_tokens`
   row → should land on `/auth`), airplane mode (screens must show error
   states, not crash), slow network (20s timeout → friendly message),
   empty feeds/blocked users/friends-only message gate.

## Gap notes

- No E2E harness yet — most valuable first target: auth gate + join-event
  flow.
- Push-notification tests come with the push feature itself.
- Backend route behavior is implicitly covered by the live web app sharing
  the same helpers; API-route-level PHP tests don't exist in the repo.
