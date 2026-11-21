import AsyncStorage from '@react-native-async-storage/async-storage';
import { BASE } from './theme';

const TOKEN_KEY = 'nipange.token';

export async function getToken(): Promise<string | null> {
  return AsyncStorage.getItem(TOKEN_KEY);
}
export async function setToken(t: string | null) {
  if (t) await AsyncStorage.setItem(TOKEN_KEY, t);
  else await AsyncStorage.removeItem(TOKEN_KEY);
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
  const res = await fetch(`${BASE}/api/app.php?${qs}`, {
    method: body !== undefined ? 'POST' : 'GET',
    headers: {
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(body !== undefined ? { 'Content-Type': 'application/json' } : {}),
    },
    body: body !== undefined ? JSON.stringify(body) : undefined,
  });
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
