import { Platform } from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import * as SecureStore from 'expo-secure-store';
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

export function imgUrl(name?: string | null): string | undefined {
  return name ? `${BASE}/uploads/${name}` : undefined;
}
