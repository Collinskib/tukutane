import React, { useCallback, useState } from 'react';
import { FlatList, Pressable, StyleSheet, Text, View, RefreshControl } from 'react-native';
import { router, useFocusEffect } from 'expo-router';
import { api } from '../../lib/api';
import { Avatar, Empty, Loading, Screen } from '../../components/ui';
import TopBar from '../../components/TopBar';
import { C } from '../../lib/theme';

type Convo = {
  id: number; otherId: number; name: string; photo: string;
  last: string; unread: number; muted: boolean; ago: string;
};

export default function Chats() {
  const [items, setItems] = useState<Convo[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    try {
      const d = await api('conversations');
      setItems(d.items);
    } catch { /* offline */ }
    setLoading(false); setRefreshing(false);
  }, []);

  useFocusEffect(useCallback(() => {
    setLoading(true); load();
    const t = setInterval(load, 15000);
    return () => clearInterval(t);
  }, [load]));

  return (
    <Screen>
      <TopBar />
      <FlatList
        data={items}
        keyExtractor={(c) => String(c.id)}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); load(); }} tintColor={C.green} />}
        ListHeaderComponent={<Text style={s.h}>Chats</Text>}
        renderItem={({ item: c }) => (
          <Pressable style={s.row} onPress={() => router.push(`/chat/${c.id}`)}>
            <Avatar photo={c.photo} name={c.name} size={48} />
            <View style={{ flex: 1, marginLeft: 12 }}>
              <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
                <Text style={s.name} numberOfLines={1}>{c.name}{c.muted ? ' 🔕' : ''}</Text>
                <Text style={s.ago}>{c.ago}</Text>
              </View>
              <Text style={s.last} numberOfLines={1}>{c.last || 'Say hi 👋'}</Text>
            </View>
            {c.unread > 0 && <View style={s.badge}><Text style={s.badgeTxt}>{c.unread}</Text></View>}
          </Pressable>
        )}
        ListEmptyComponent={() => (loading ? <Loading /> : <Empty msg="No conversations yet — message a friend to start one." />)}
      />
    </Screen>
  );
}

const s = StyleSheet.create({
  h: { fontSize: 24, fontWeight: '700', color: C.ink, marginBottom: 12 },
  row: {
    flexDirection: 'row', alignItems: 'center', backgroundColor: C.card,
    borderWidth: 1, borderColor: '#e4e6db', borderRadius: 16, padding: 12, marginBottom: 10,
  },
  name: { fontWeight: '700', color: C.ink, fontSize: 16, flexShrink: 1 },
  ago: { color: C.soft, fontSize: 12 },
  last: { color: C.soft, fontSize: 14, marginTop: 2 },
  badge: { backgroundColor: C.accent, borderRadius: 10, minWidth: 20, height: 20, alignItems: 'center', justifyContent: 'center', marginLeft: 8 },
  badgeTxt: { color: '#fff', fontSize: 11, fontWeight: '700', paddingHorizontal: 4 },
});
