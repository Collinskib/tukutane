import { Platform } from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import * as SecureStore from 'expo-secure-store';
import * as FileSystem from 'expo-file-system/legacy';
import { BASE } from './theme';

const TOKEN_KEY = 'nipange.token';

// SecureStore on native (Keystore/Keychain-backed); AsyncStorage on web.
export async function getToken(): Promise<string | null> {
  if (Platform.OS === 'web') return AsyncStorage.getItem(TOKEN_KEY);
  const t = await SecureStore.getItemAsync(TOKEN_KEY);
  if (t !== null) return t;
  // Migrate tokens stored before the SecureStore swap.
  const legacy = await AsyncStorage.getItem(TOKEN_KEY);
  if (legacy) {
    await SecureStore.setItemAsync(TOKEN_KEY, legacy).catch(() => {});
    await AsyncStorage.removeItem(TOKEN_KEY).catch(() => {});
  }
  return legacy;
}
export async function setToken(t: string | null) {
  if (Platform.OS === 'web') {
    if (t) await AsyncStorage.setItem(TOKEN_KEY, t);
    else await AsyncStorage.removeItem(TOKEN_KEY);
    return;
  }
  if (t) await SecureStore.setItemAsync(TOKEN_KEY, t);
  else await SecureStore.deleteItemAsync(TOKEN_KEY);
}

export class ApiError extends Error {
  status: number;
  constructor(msg: string, status = 0) { super(msg); this.status = status; }
}

// api('feed', {county:'Nairobi'}) -> GET; api('rsvp', {}, {id:1,action:'join'}) -> POST JSON
export async function api(route: string, params: Record<string, any> = {}, body?: any): Promise<any> {
  const qs = new URLSearchParams({ r: route });
  for (const [k, v] of Object.entries(params)) {
    if (v !== undefined && v !== null && v !== '') qs.set(k, String(v));
  }
  const token = await getToken();
  const ctrl = new AbortController();
  const timer = setTimeout(() => ctrl.abort(), 20000);
  let res: Response;
  try {
    res = await fetch(`${BASE}/api/app.php?${qs}`, {
      method: body !== undefined ? 'POST' : 'GET',
      headers: {
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...(body !== undefined ? { 'Content-Type': 'application/json' } : {}),
      },
      body: body !== undefined ? JSON.stringify(body) : undefined,
      signal: ctrl.signal,
    });
  } catch (e: any) {
    if (e?.name === 'AbortError') throw new ApiError('Request timed out — check your connection.', 0);
    throw new ApiError('Network error — check your connection.', 0);
  } finally {
    clearTimeout(timer);
  }
  let data: any = null;
  try { data = await res.json(); } catch { /* non-json */ }
  if (!res.ok || (data && data.error)) {
    throw new ApiError(data?.error || `Request failed (${res.status})`, res.status);
  }
  return data;
}

// Multipart upload (audio/video/large media) — Bearer auth, same route set.
export async function apiUpload(
  route: string,
  params: Record<string, any>,
  file: { uri: string; name: string; type: string },
  fields: Record<string, any> = {},
): Promise<any> {
  const qs = new URLSearchParams({ r: route });
  for (const [k, v] of Object.entries(params)) {
    if (v !== undefined && v !== null && v !== '') qs.set(k, String(v));
  }
  const token = await getToken();
  const fd = new FormData();
  for (const [k, v] of Object.entries(fields)) fd.append(k, String(v));
  fd.append('attach', { uri: file.uri, name: file.name, type: file.type } as any);
  const ctrl = new AbortController();
  const timer = setTimeout(() => ctrl.abort(), 120000); // media uploads can be slow
  let res: Response;
  try {
    res = await fetch(`${BASE}/api/app.php?${qs}`, {
      method: 'POST',
      headers: { ...(token ? { Authorization: `Bearer ${token}` } : {}) },
      body: fd,
      signal: ctrl.signal,
    });
  } catch (e: any) {
    if (e?.name === 'AbortError') throw new ApiError('Upload timed out — check your connection.', 0);
    throw new ApiError('Network error — check your connection.', 0);
  } finally {
    clearTimeout(timer);
  }
  let data: any = null;
  try { data = await res.json(); } catch { /* non-json */ }
  if (!res.ok || (data && data.error)) {
    throw new ApiError(data?.error || `Upload failed (${res.status})`, res.status);
  }
  return data;
}

// Fetch a view-once attachment (api media route) to a local cache file.
// The server marks it consumed on first authorized fetch.
export async function fetchViewOnce(messageId: number): Promise<string> {
  const token = await getToken();
  const res = await fetch(`${BASE}/api/app.php?r=media&m=${messageId}`, {
    headers: token ? { Authorization: `Bearer ${token}` } : {},
  });
  if (!res.ok) throw new ApiError(res.status === 410 ? 'Already viewed' : 'Could not open', res.status);
  const blob = await res.blob();
  const ext = (res.headers.get('Content-Type') ?? 'application/octet-stream')
    .split('/')[1]?.split(';')[0]?.replace('jpeg', 'jpg').replace('quicktime', 'mov') ?? 'bin';
  const path = `${FileSystem.cacheDirectory}vo-${messageId}.${ext}`;
  const fr = new FileReader();
  const b64 = await new Promise<string>((resolve, reject) => {
    fr.onload = () => resolve((fr.result as string).split(',')[1]);
    fr.onerror = reject;
    fr.readAsDataURL(blob);
  });
  await FileSystem.writeAsStringAsync(path, b64, { encoding: 'base64' });
  return path;
}

export function imgUrl(name?: string | null): string | undefined {
  return name ? `${BASE}/uploads/${name}` : undefined;
}
