import React, { createContext, useContext, useEffect, useMemo, useState } from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { api, getToken, setToken } from './api';
import { registerForPush, unregisterPush } from './push';

export type User = {
  id: number; name: string; email: string; photo: string; place: string;
  bio: string; interests: string; gender: string; age: string; funFact: string;
  county: string; area: string; estate: string; verified: boolean;
  discoverable: boolean; role: string;
};

export type Meta = {
  categories: string[]; counties: string[]; genders: string[]; ages: string[];
  areas: Record<string, Record<string, string[]>>;
  venues: { id: number; name: string; place: string }[];
};

type Auth = {
  user: User | null;
  loading: boolean;
  incomplete: boolean;
  meta: Meta | null;
  justJoined: boolean;
  join: () => void;
  clearJoin: () => void;
  refreshUser: () => Promise<void>;
  logout: () => Promise<void>;
  applyAuth: (d: any) => Promise<void>;
};

const Ctx = createContext<Auth>(null as any);
export const useAuth = () => useContext(Ctx);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [incomplete, setIncomplete] = useState(false);
  const [meta, setMeta] = useState<Meta | null>(null);
  const [loading, setLoading] = useState(true);
  const [justJoined, setJustJoined] = useState(false);

  // Boot: hydrate user+meta from cache so the Gate routes instantly, then
  // refresh both from the API in the background. Cache is cleared on logout.
  useEffect(() => {
    let alive = true;
    (async () => {
      let tok: string | null = null;
      try {
        const [cu, cm, t] = await Promise.all([
          AsyncStorage.getItem('nipange.cache.user'),
          AsyncStorage.getItem('nipange.cache.meta'),
          getToken(),
        ]);
        tok = t;
        if (!alive) return;
        // Only hydrate the user when a session token exists — a tokenless
        // cached profile would route into a dead session.
        if (cu && tok) {
          const c = JSON.parse(cu);
          setUser(c.user);
          setIncomplete(!!c.incomplete);
        }
        if (cm) setMeta(JSON.parse(cm));
      } catch { /* corrupt cache is fine — network refresh follows */ }
      setLoading(false);

      api('meta').then((m) => {
        if (!alive) return;
        setMeta(m);
        AsyncStorage.setItem('nipange.cache.meta', JSON.stringify(m)).catch(() => {});
      }).catch(() => {});
      if (!tok) return;
      try {
        const d = await api('profile');
        if (!alive) return;
        setUser(d.user);
        setIncomplete(!!d.incomplete);
        AsyncStorage.setItem('nipange.cache.user',
          JSON.stringify({ user: d.user, incomplete: !!d.incomplete })).catch(() => {});
        registerForPush();
      } catch (e: any) {
        // Only drop the session on a real auth rejection, not a flaky network.
        if (e?.status === 401 || e?.status === 403) {
          await setToken(null);
          await AsyncStorage.removeItem('nipange.cache.user').catch(() => {});
          if (!alive) return;
          setUser(null);
          setIncomplete(false);
        }
      }
    })();
    return () => { alive = false; };
  }, []);

  const value = useMemo<Auth>(() => ({
    user, loading, incomplete, meta, justJoined,
    join: () => setJustJoined(true),
    clearJoin: () => setJustJoined(false),
    applyAuth: async (d: any) => {
      await setToken(d.token);
      setUser(d.user);
      setIncomplete(!!d.incomplete);
      AsyncStorage.setItem('nipange.cache.user',
        JSON.stringify({ user: d.user, incomplete: !!d.incomplete })).catch(() => {});
      registerForPush();
    },
    refreshUser: async () => {
      try {
        const d = await api('profile');
        setUser(d.user);
        setIncomplete(!!d.incomplete);
        AsyncStorage.setItem('nipange.cache.user',
          JSON.stringify({ user: d.user, incomplete: !!d.incomplete })).catch(() => {});
      } catch { /* keep stale */ }
    },
    logout: async () => {
      await unregisterPush();
      try { await api('auth/logout', {}, {}); } catch { /* token may be dead */ }
      await setToken(null);
      await AsyncStorage.removeItem('nipange.cache.user').catch(() => {});
      setUser(null);
    },
  }), [user, loading, incomplete, meta, justJoined]);

  return <Ctx.Provider value={value}>{children}</Ctx.Provider>;
}
