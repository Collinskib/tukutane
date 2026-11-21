import React, { useCallback, useState } from 'react';
import { FlatList, StyleSheet, Text, View, RefreshControl } from 'react-native';
import { router, useFocusEffect } from 'expo-router';
import { api } from '../../lib/api';
import { Chip, Empty, Loading, Screen } from '../../components/ui';
import EventCard, { Ev } from '../../components/EventCard';
import TopBar from '../../components/TopBar';
import { C } from '../../lib/theme';

type Inv = { inviteId: number; status: string; inviter: string; event: Ev };

export default function Plans() {
  const [tab, setTab] = useState<'mine' | 'hosted' | 'saved' | 'invites'>('mine');
  const [items, setItems] = useState<Ev[]>([]);
  const [invites, setInvites] = useState<Inv[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async (t = tab) => {
    try {
      if (t === 'invites') {
        const d = await api('my-invites');
        setInvites(d.items);
      } else {
        const d = await api('feed', { view: t, per: 50 });
        setItems(d.items);
      }
    } catch { /* offline */ }
    setLoading(false); setRefreshing(false);
  }, [tab]);

  useFocusEffect(useCallback(() => { setLoading(true); load(); }, [load]));

  const act = async (inv: Inv, action: 'accept_invite' | 'decline_invite') => {
    await api('rsvp', {}, { id: inv.event.id, action });
    load();
  };

  return (
    <Screen>
      <TopBar />
      <FlatList
        data={tab === 'invites' ? invites : items}
        keyExtractor={(x: any) => String(tab === 'invites' ? x.inviteId : x.id)}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); load(); }} tintColor={C.green} />}
        ListHeaderComponent={
          <View>
            <Text style={s.h}>My plans</Text>
            <View style={{ flexDirection: 'row', marginBottom: 10 }}>
              {([['mine', 'Going'], ['hosted', 'Hosting'], ['saved', 'Saved'], ['invites', 'Invites']] as const).map(([v, l]) => (
                <Chip key={v} label={l} active={tab === v} onPress={() => setTab(v)} />
              ))}
            </View>
          </View>
        }
        renderItem={({ item }: { item: any }) =>
          tab === 'invites' ? (
            <View style={s.inv}>
              <Text style={s.invTxt}>{item.inviter} invited you</Text>
              <EventCard ev={item.event} />
              {item.status === 'pending' && (
                <View style={{ flexDirection: 'row', gap: 8, marginTop: -4 }}>
                  <Chip label="Accept" active onPress={() => act(item, 'accept_invite')} />
                  <Chip label="Decline" onPress={() => act(item, 'decline_invite')} />
                </View>
              )}
              {item.status !== 'pending' && <Text style={s.status}>Status: {item.status}</Text>}
            </View>
          ) : (
            <EventCard ev={item} />
          )
        }
        ListEmptyComponent={() => (loading ? <Loading /> : (
          <Empty msg={tab === 'invites' ? 'No pending invites.' : 'Nothing here yet — join an event or create one.'} />
        ))}
        ListFooterComponent={() =>
          tab === 'mine' ? (
            <Text style={s.cta} onPress={() => router.push('/event/edit')}>＋ Create a plan</Text>
          ) : null
        }
      />
    </Screen>
  );
}

const s = StyleSheet.create({
  h: { fontSize: 24, fontWeight: '700', color: C.ink, marginBottom: 12 },
  inv: {
    backgroundColor: C.card, borderWidth: 1, borderColor: '#e4e6db',
    borderRadius: 16, padding: 12, marginBottom: 12,
  },
  invTxt: { color: C.soft, marginBottom: 8, fontWeight: '600' },
  status: { color: C.soft, marginTop: 4 },
  cta: { color: C.green, fontWeight: '700', textAlign: 'center', marginVertical: 16, fontSize: 16 },
});
