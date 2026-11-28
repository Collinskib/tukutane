import { Platform } from 'react-native';
import * as Notifications from 'expo-notifications';
import * as Device from 'expo-device';
import Constants from 'expo-constants';
import { router } from 'expo-router';
import { api } from './api';

// Foreground behavior: show the banner/list, keep sound on.
Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldPlaySound: true,
    shouldSetBadge: true,
    shouldShowBanner: true,
    shouldShowList: true,
  }),
});

let currentToken: string | null = null;

// Register this device's Expo push token with the API (api/app.php?r=push/register).
// Safe to call repeatedly — the server upserts on the token.
// No-ops on web, simulators, and Expo Go (remote push needs a dev build).
export async function registerForPush(): Promise<void> {
  if (Platform.OS === 'web' || !Device.isDevice) return;
  try {
    if (Platform.OS === 'android') {
      await Notifications.setNotificationChannelAsync('default', {
        name: 'Nipange',
        // eslint-disable-next-line import/namespace -- runtime enum exists; rule can't see export * chains
        importance: Notifications.AndroidImportance.HIGH,
        sound: 'default',
        enableVibrate: true,
        vibrationPattern: [0, 250, 250, 250],
        enableLights: true,
        lightColor: '#163e31',
      });
    }
    const { status: existing } = await Notifications.getPermissionsAsync();
    const status = existing === 'granted'
      ? existing
      : (await Notifications.requestPermissionsAsync()).status;
    if (status !== 'granted') { console.warn('[push] permission', status); return; }

    const projectId =
      Constants?.expoConfig?.extra?.eas?.projectId ?? Constants?.easConfig?.projectId;
    if (!projectId) { console.warn('[push] no EAS projectId in config'); return; }

    const token = (await Notifications.getExpoPushTokenAsync({ projectId })).data;
    if (!token) { console.warn('[push] empty token'); return; }
    currentToken = token;
    await api('push/register', {}, { token, platform: Platform.OS });
    console.log('[push] registered', token.slice(0, 24));
  } catch (e: any) {
    // Push is best-effort — never break auth or app boot over it.
    console.warn('[push] register failed:', e?.message ?? e);
  }
}

// Called on logout so this device stops receiving pushes for the account.
export async function unregisterPush(): Promise<void> {
  if (!currentToken) return;
  try { await api('push/unregister', {}, { token: currentToken }); } catch { /* best-effort */ }
  currentToken = null;
}

// Web notification links (e.g. "event.php?id=5", "messages.php?c=3") → app routes.
export function routeForLink(link: string): string | null {
  const m = (re: RegExp) => link.match(re)?.[1];
  let id: string | undefined;
  if ((id = m(/event\.php\?.*id=(\d+)/))) return `/event/${id}`;
  if ((id = m(/messages\.php\?.*c=(\d+)/))) return `/chat/${id}`;
  if ((id = m(/group\.php\?.*id=(\d+)/))) return `/group/${id}`;
  if ((id = m(/venue\.php\?.*id=(\d+)/))) return `/venue/${id}`;
  if ((id = m(/user\.php\?.*id=(\d+)/))) return `/user/${id}`;
  if (link.includes('friends.php')) return '/friends';
  if (link.includes('people.php')) return '/(tabs)/people';
  if (link.includes('index.php')) return '/(tabs)/discover';
  return null;
}

// Register notification action categories. Message pushes carry
// categoryId 'msg-reply' → Android shows an inline-reply field.
export async function setupNotificationActions(): Promise<void> {
  try {
    await Notifications.setNotificationCategoryAsync('msg-reply', [
      {
        identifier: 'reply',
        buttonTitle: 'Reply',
        textInput: { submitButtonTitle: 'Send', placeholder: 'Message…' },
        options: { opensAppToForeground: false },
      },
    ]);
  } catch { /* best-effort */ }
}

function convFromLink(link: unknown): number | null {
  const m = typeof link === 'string' ? link.match(/messages\.php\?.*c=(\d+)/) : null;
  return m ? Number(m[1]) : null;
}

// Mount the response listener once; returns an unsubscribe for cleanup.
export function listenForPushTaps(): () => void {
  const sub = Notifications.addNotificationResponseReceivedListener((res) => {
    const link = res.notification.request.content.data?.link;
    // Inline reply action — send straight to the conversation, stay in shade.
    if (res.actionIdentifier === 'reply') {
      const conv = convFromLink(link);
      const text = (res.userText ?? '').trim();
      if (conv && text) api('messages', {}, { conv, body: text }).catch(() => {});
      return;
    }
    if (typeof link !== 'string' || !link) return;
    const route = routeForLink(link);
    if (route) router.push(route as any);
  });
  // Cold start: app opened by tapping a notification.
  Notifications.getLastNotificationResponseAsync().then((res) => {
    const link = res?.notification.request.content.data?.link;
    if (res?.actionIdentifier === 'reply') {
      const conv = convFromLink(link);
      const text = (res.userText ?? '').trim();
      if (conv && text) api('messages', {}, { conv, body: text }).catch(() => {});
      return;
    }
    if (typeof link !== 'string' || !link) return;
    const route = routeForLink(link);
    if (route) router.push(route as any);
  }).catch(() => {});
  return () => sub.remove();
}
