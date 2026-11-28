import React, { createContext, useContext, useEffect, useMemo, useState } from 'react';
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

  useEffect(() => {
    (async () => {
      try {
        const m = await api('meta');
        setMeta(m);
      } catch { /* offline */ }
      try {
        if (await getToken()) {
          const d = await api('profile');
          setUser(d.user);
          setIncomplete(!!d.incomplete);
          registerForPush();
        }
      } catch (e: any) {
        // Only drop the token on a real auth rejection, not a flaky network.
        if (e?.status === 401 || e?.status === 403) await setToken(null);
      }
      setLoading(false);
    })();
  }, []);

  const value = useMemo<Auth>(() => ({
    user, loading, incomplete, meta, justJoined,
    join: () => setJustJoined(true),
    clearJoin: () => setJustJoined(false),
    applyAuth: async (d: any) => {
      await setToken(d.token);
      setUser(d.user);
      setIncomplete(!!d.incomplete);
      registerForPush();
    },
    refreshUser: async () => {
      try {
        const d = await api('profile');
        setUser(d.user);
        setIncomplete(!!d.incomplete);
      } catch { /* keep stale */ }
    },
    logout: async () => {
      await unregisterPush();
      try { await api('auth/logout', {}, {}); } catch { /* token may be dead */ }
      await setToken(null);
      setUser(null);
    },
  }), [user, loading, incomplete, meta, justJoined]);

  return <Ctx.Provider value={value}>{children}</Ctx.Provider>;
}
