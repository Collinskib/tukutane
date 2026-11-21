import React, { useCallback, useRef, useState } from 'react';
import { FlatList, Pressable, StyleSheet, Text, View, RefreshControl } from 'react-native';
import { router, useFocusEffect } from 'expo-router';
import { api } from '../../lib/api';
import { useAuth } from '../../lib/store';
import { Avatar, Chip, Empty, Err, Input, Loading, Screen, Select } from '../../components/ui';
import TopBar from '../../components/TopBar';
import { C } from '../../lib/theme';

type P = {
  id: number; name: string; photo: string; place: string; bio: string;
  gender: string; age: string; isFriend: boolean; rel: string | null;
  relRequester: number; shared: string[];
};

export default function People() {
  const { meta } = useAuth();
  const [items, setItems] = useState<P[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [err, setErr] = useState('');
  const [f, setF] = useState({ county: '', area: '', interest: '', q: '' });
  const deb = useRef<ReturnType<typeof setTimeout> | null>(null);

  const load = useCallback(async (ff = f) => {
    try {
      const d = await api('people', ff);
      setItems(d.items); setErr('');
    } catch (e: any) { setErr(e.message); }
    setLoading(false); setRefreshing(false);
  }, [f]);

  useFocusEffect(useCallback(() => { setLoading(true); load(); }, [load]));
  const onSearch = (q: string) => {
    setF((p) => ({ ...p, q }));
    if (deb.current) clearTimeout(deb.current);
    deb.current = setTimeout(() => { setLoading(true); load({ ...f, q }); }, 350);
  };
  const subs = f.county ? Object.keys(meta?.areas?.[f.county] ?? {}) : [];

  return (
    <Screen>
      <TopBar />
      <FlatList
        data={items}
        keyExtractor={(p) => String(p.id)}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); load(); }} tintColor={C.green} />}
        ListHeaderComponent={
          <View>
            <Text style={s.h}>People nearby</Text>
            <Text style={s.sub}>Find your people — friends, teammates, dance partners.</Text>
            <Input placeholder="Search names or bios…" value={f.q} onChangeText={onSearch} />
            <View style={{ flexDirection: 'row', gap: 8, marginTop: 8, marginBottom: 8 }}>
              <View style={{ flex: 1 }}>
                <Select value={f.county}
                  options={[{ value: '', label: 'All counties' }, ...(meta?.counties ?? []).map((c) => ({ value: c, label: c }))]}
                  onChange={(v) => { const nf = { ...f, county: v, area: '' }; setF(nf); setLoading(true); load(nf); }} />
              </View>
              <View style={{ flex: 1 }}>
                <Select value={f.area}
                  options={[{ value: '', label: 'Any area' }, ...subs.map((a) => ({ value: a, label: a }))]}
                  onChange={(v) => { const nf = { ...f, area: v }; setF(nf); setLoading(true); load(nf); }} />
              </View>
            </View>
            <FlatList horizontal data={['', ...(meta?.categories ?? [])]} keyExtractor={(c) => c || 'all'}
              showsHorizontalScrollIndicator={false} style={{ marginBottom: 6 }}
              renderItem={({ item: c }) => (
                <Chip label={c || 'All interests'} active={f.interest === c}
                  onPress={() => { const nf = { ...f, interest: c }; setF(nf); setLoading(true); load(nf); }} />
              )} />
            <Err msg={err} />
            {loading && <Loading />}
          </View>
        }
        renderItem={({ item: p }) => (
          <Pressable style={s.row} onPress={() => router.push(`/user/${p.id}`)}>
            <Avatar photo={p.photo} name={p.name} size={48} />
            <View style={{ flex: 1, marginLeft: 12 }}>
              <Text style={s.name}>{p.name}{p.isFriend ? ' · ✓' : ''}</Text>
              <Text style={s.meta} numberOfLines={1}>
                {[p.age, p.gender, p.place].filter(Boolean).join(' · ') || 'Kenya'}
              </Text>
              {p.shared?.length ? (
                <Text style={s.shared} numberOfLines={1}>Also into: {p.shared.join(', ')}</Text>
              ) : null}
            </View>
            <Text style={{ color: C.soft }}>›</Text>
          </Pressable>
        )}
        ListEmptyComponent={() => (loading ? null : <Empty msg="No one matches — try widening your filters." />)}
      />
    </Screen>
  );
}

const s = StyleSheet.create({
  h: { fontSize: 24, fontWeight: '700', color: C.ink, marginBottom: 2 },
  sub: { color: C.soft, marginBottom: 12 },
  row: {
    flexDirection: 'row', alignItems: 'center', backgroundColor: C.card,
    borderWidth: 1, borderColor: '#e4e6db', borderRadius: 16, padding: 12, marginBottom: 10,
  },
  name: { fontWeight: '700', color: C.ink, fontSize: 16 },
  meta: { color: C.soft, fontSize: 13, marginTop: 1 },
  shared: { color: C.green, fontSize: 12, marginTop: 2 },
});
