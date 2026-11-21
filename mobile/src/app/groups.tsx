import React, { useCallback, useState } from 'react';
import { FlatList, Pressable, StyleSheet, Text, View, RefreshControl } from 'react-native';
import { router, useFocusEffect } from 'expo-router';
import { api } from '../lib/api';
import { useAuth } from '../lib/store';
import { Chip, Empty, Input, Loading, Screen, Select } from '../components/ui';
import { C } from '../lib/theme';

export default function Groups() {
  const { meta } = useAuth();
  const [items, setItems] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [f, setF] = useState({ view: '', category: '', county: '', area: '', q: '' });

  const load = useCallback(async (ff = f) => {
    try {
      const d = await api('groups', ff);
      setItems(d.items);
    } catch { /* offline */ }
    setLoading(false); setRefreshing(false);
  }, [f]);

  useFocusEffect(useCallback(() => { setLoading(true); load(); }, [load]));
  const subs = f.county ? Object.keys(meta?.areas?.[f.county] ?? {}) : [];

  return (
    <Screen bottom>
      <FlatList
        data={items}
        keyExtractor={(g) => String(g.id)}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); load(); }} tintColor={C.green} />}
        ListHeaderComponent={
          <View>
            <Pressable onPress={() => router.back()}><Text style={s.back}>‹ Back</Text></Pressable>
            <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
              <Text style={s.h}>Groups</Text>
              <Text style={s.new} onPress={() => router.push('/group/edit')}>＋ New group</Text>
            </View>
            <Input placeholder="Search groups…" value={f.q} onChangeText={(q) => { const nf = { ...f, q }; setF(nf); load(nf); }} />
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
            <View style={{ flexDirection: 'row', marginBottom: 8 }}>
              <Chip label="All" active={f.view !== 'mine'} onPress={() => { const nf = { ...f, view: '' }; setF(nf); load(nf); }} />
              <Chip label="My groups" active={f.view === 'mine'} onPress={() => { const nf = { ...f, view: 'mine' }; setF(nf); load(nf); }} />
            </View>
            {loading && <Loading />}
          </View>
        }
        renderItem={({ item: g }) => (
          <Pressable style={s.card} onPress={() => router.push(`/group/${g.id}`)}>
            <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
              <Text style={s.gname}>{g.name}</Text>
              {g.myStatus === 'active' && <Text style={{ color: C.green, fontWeight: '700' }}>✓</Text>}
              {g.myStatus === 'pending' && <Text style={{ color: C.soft, fontSize: 12 }}>pending</Text>}
            </View>
            <Text style={s.gmeta}>{g.category} · {g.place} · {g.members} member{g.members === 1 ? '' : 's'}</Text>
            {!!g.desc && <Text style={s.gdesc} numberOfLines={2}>{g.desc}</Text>}
            <Text style={s.gpolicy}>{g.joinPolicy === 'approval' ? 'Join by approval' : 'Open to join'}</Text>
          </Pressable>
        )}
        ListEmptyComponent={() => (loading ? null : <Empty msg="No groups here yet — start one!" />)}
      />
    </Screen>
  );
}

const s = StyleSheet.create({
  back: { color: C.green, fontWeight: '600', marginBottom: 8 },
  h: { fontSize: 24, fontWeight: '700', color: C.ink },
  new: { color: C.green, fontWeight: '700' },
  card: {
    backgroundColor: C.card, borderWidth: 1, borderColor: '#e4e6db',
    borderRadius: 16, padding: 14, marginBottom: 10,
  },
  gname: { fontWeight: '700', color: C.ink, fontSize: 17, flex: 1 },
  gmeta: { color: C.soft, fontSize: 13, marginTop: 3 },
  gdesc: { color: C.ink, fontSize: 14, marginTop: 6 },
  gpolicy: { color: C.green, fontSize: 12, marginTop: 8, fontWeight: '600' },
});
