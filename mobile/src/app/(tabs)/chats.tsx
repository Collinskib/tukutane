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
type EChat = {
  id: number; title: string; atLabel: string; host: number;
  last: string; unread: number; muted: boolean;
};

export default function Chats() {
  const [tab, setTab] = useState<'chats' | 'groups'>('chats');
  const [items, setItems] = useState<Convo[]>([]);
  const [echats, setEchats] = useState<EChat[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    try {
      const [c, e] = await Promise.all([api('conversations'), api('event-chats')]);
      setItems(c.items); setEchats(e.items);
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
      <View style={s.tabs}>
        <Pressable style={[s.tab, tab === 'chats' && s.tabOn]} onPress={() => setTab('chats')}>
          <Text style={[s.tabT, tab === 'chats' && s.tabOnT]}>Chats</Text>
        </Pressable>
        <Pressable style={[s.tab, tab === 'groups' && s.tabOn]} onPress={() => setTab('groups')}>
          <Text style={[s.tabT, tab === 'groups' && s.tabOnT]}>Groups</Text>
        </Pressable>
      </View>
      {tab === 'chats' ? (
        <FlatList
          data={items}
          keyExtractor={(c) => String(c.id)}
          refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); load(); }} tintColor={C.green} />}
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
      ) : (
        <FlatList
          data={echats}
          keyExtractor={(g) => String(g.id)}
          refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); load(); }} tintColor={C.green} />}
          renderItem={({ item: g }) => (
            <Pressable style={s.row} onPress={() => router.push({ pathname: '/event/chat', params: { id: String(g.id), title: g.title } })}>
              <View style={s.gicon}><Text style={{ fontSize: 20 }}>🎉</Text></View>
              <View style={{ flex: 1, marginLeft: 12 }}>
                <Text style={s.name} numberOfLines={1}>{g.title}{g.muted ? ' 🔕' : ''}</Text>
                <Text style={s.last} numberOfLines={1}>{g.last || `Event ${g.atLabel}`}</Text>
              </View>
              {g.unread > 0 && <View style={s.badge}><Text style={s.badgeTxt}>{g.unread}</Text></View>}
            </Pressable>
          )}
          ListEmptyComponent={() => (loading ? <Loading /> : <Empty msg="No group chats yet — join an event and its chat appears here." />)}
        />
      )}
    </Screen>
  );
}

const s = StyleSheet.create({
  tabs: { flexDirection: 'row', gap: 8, marginBottom: 14 },
  tab: {
    flex: 1, alignItems: 'center', paddingVertical: 9, borderRadius: 99,
    borderWidth: 1, borderColor: '#d8ddd3', backgroundColor: C.card,
  },
  tabOn: { backgroundColor: C.green, borderColor: C.green },
  tabT: { fontWeight: '700', fontSize: 14, color: C.ink },
  tabOnT: { color: '#fff' },
  row: {
    flexDirection: 'row', alignItems: 'center', backgroundColor: C.card,
    borderWidth: 1, borderColor: '#e4e6db', borderRadius: 16, padding: 12, marginBottom: 10,
  },
  gicon: {
    width: 48, height: 48, borderRadius: 24, backgroundColor: C.green,
    alignItems: 'center', justifyContent: 'center',
  },
  name: { fontWeight: '700', color: C.ink, fontSize: 16, flexShrink: 1 },
  ago: { color: C.soft, fontSize: 12 },
  last: { color: C.soft, fontSize: 14, marginTop: 2 },
  badge: { backgroundColor: C.accent, borderRadius: 10, minWidth: 20, height: 20, alignItems: 'center', justifyContent: 'center', marginLeft: 8 },
  badgeTxt: { color: '#fff', fontSize: 11, fontWeight: '700', paddingHorizontal: 4 },
});
