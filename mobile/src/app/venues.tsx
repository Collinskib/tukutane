import React, { useCallback, useState } from 'react';
import { FlatList, Image, Pressable, StyleSheet, Text, View, RefreshControl } from 'react-native';
import { router, useFocusEffect } from 'expo-router';
import { api, imgUrl } from '../lib/api';
import { useAuth } from '../lib/store';
import { Empty, Input, Loading, Screen, Select } from '../components/ui';
import { C } from '../lib/theme';

export default function Venues() {
  const { meta, user } = useAuth();
  const [items, setItems] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [f, setF] = useState({ county: '', area: '', q: '' });

  const load = useCallback(async (ff = f) => {
    try {
      const d = await api('venues', ff);
      setItems(d.items);
    } catch { /* offline */ }
    setLoading(false); setRefreshing(false);
  }, [f]);

  useFocusEffect(useCallback(() => { setLoading(true); load(); }, [load]));
  const subs = f.county ? Object.keys(meta?.areas?.[f.county] ?? {}) : [];

  const message = async (v: any) => {
    try {
      const r = await api('conversation', {}, { uid: v.creatorId });
      router.push(`/chat/${r.conv}`);
    } catch { /* gate/offline */ }
  };

  return (
    <Screen bottom>
      <FlatList
        data={items}
        keyExtractor={(v) => String(v.id)}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); load(); }} tintColor={C.green} />}
        ListHeaderComponent={
          <View>
            <Pressable onPress={() => router.back()}><Text style={s.back}>‹ Back</Text></Pressable>
            <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
              <Text style={s.h}>Business</Text>
              <Text style={s.new} onPress={() => router.push('/venue/edit')}>＋ Add your business</Text>
            </View>
            <Input placeholder="Search businesses…" value={f.q} onChangeText={(q) => { const nf = { ...f, q }; setF(nf); load(nf); }} />
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
            {loading && <Loading />}
          </View>
        }
        renderItem={({ item: v }) => (
          <Pressable style={s.card} onPress={() => router.push(`/venue/${v.id}`)}>
            {imgUrl(v.photo) ? <Image source={{ uri: imgUrl(v.photo) }} style={s.photo} /> : null}
            <View style={{ padding: 12 }}>
              <Text style={s.vname}>{v.name}</Text>
              <Text style={s.vmeta}>{v.place} · {v.upcoming} upcoming event{v.upcoming === 1 ? '' : 's'}</Text>
              {!!v.desc && <Text style={s.vdesc} numberOfLines={2}>{v.desc}</Text>}
              <View style={{ flexDirection: 'row', alignItems: 'center', marginTop: 6, gap: 10 }}>
                <Text style={s.vcreator}>Added by {v.creator}</Text>
                {v.creatorId !== user?.id && (
                  <Pressable onPress={() => message(v)} style={s.msgchip}>
                    <Text style={s.msgchipT}>💬 Message</Text>
                  </Pressable>
                )}
              </View>
            </View>
          </Pressable>
        )}
        ListEmptyComponent={() => (loading ? null : <Empty msg="No businesses listed here yet — add yours!" />)}
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
    borderRadius: 16, marginBottom: 10, overflow: 'hidden',
  },
  photo: { width: '100%', height: 120, backgroundColor: C.line },
  vname: { fontWeight: '700', color: C.ink, fontSize: 17 },
  vmeta: { color: C.soft, fontSize: 13, marginTop: 3 },
  vdesc: { color: C.ink, fontSize: 14, marginTop: 6 },
  vcreator: { color: C.soft, fontSize: 12 },
  msgchip: { backgroundColor: C.green, borderRadius: 99, paddingHorizontal: 10, paddingVertical: 4 },
  msgchipT: { color: '#fff', fontSize: 12, fontWeight: '700' },
});
