import React, { useCallback, useRef, useState } from 'react';
import { FlatList, Pressable, StyleSheet, Text, View, RefreshControl } from 'react-native';
import { router, useFocusEffect } from 'expo-router';
import { api } from '../../lib/api';
import { useAuth } from '../../lib/store';
import { Avatar, Btn, Card, Chip, Empty, Err, Input, Loading, Screen, Select } from '../../components/ui';
import TopBar from '../../components/TopBar';
import { C, serif } from '../../lib/theme';

type P = {
  id: number; name: string; photo: string; place: string; bio: string; funFact: string;
  gender: string; age: string; isFriend: boolean; rel: string | null;
  relRequester: number; shared: string[];
};
type F = { id: number; name: string; photo: string; place: string; reqId: number };

export default function People() {
  const { meta, user } = useAuth();
  const [tab, setTab] = useState<'friends' | 'add' | 'requests'>('add');
  const [items, setItems] = useState<P[]>([]);
  const [fd, setFd] = useState<{ friends: F[]; pending: F[]; outgoing: F[] }>({ friends: [], pending: [], outgoing: [] });
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [err, setErr] = useState('');
  const [busy, setBusy] = useState(0);
  const [f, setF] = useState({ county: '', area: '', interest: '', q: '' });
  const deb = useRef<ReturnType<typeof setTimeout> | null>(null);

  const load = useCallback(async (ff = f) => {
    try {
      const [d, fr] = await Promise.all([api('people', ff), api('friends')]);
      setItems(d.items); setFd(fr); setErr('');
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

  const act = async (p: P) => {
    if (p.isFriend || busy === p.id) return;
    const do_ = p.rel === 'pending'
      ? (p.relRequester === user?.id ? 'cancel' : 'accept')
      : 'send';
    setBusy(p.id);
    try {
      await api('friend', {}, { uid: p.id, do: do_ });
      setItems((list) => list.map((x) => x.id === p.id
        ? do_ === 'send'
          ? { ...x, rel: 'pending', relRequester: user?.id ?? 0 }
          : do_ === 'accept'
            ? { ...x, isFriend: true, rel: null }
            : { ...x, rel: null, relRequester: 0 }
        : x));
      api('friends').then(setFd).catch(() => {});
    } catch { /* transient — leave state */ }
    setBusy(0);
  };

  const req = async (uid: number, action: string) => {
    try { await api('friend', {}, { uid, do: action }); api('friends').then(setFd).catch(() => {}); }
    catch { /* transient */ }
  };

  const message = async (uid: number) => {
    try {
      const r = await api('conversation', {}, { uid });
      router.push(`/chat/${r.conv}`);
    } catch { /* gate/offline */ }
  };

  const label = (p: P) =>
    p.isFriend ? 'Friends ✓'
    : p.rel === 'pending' ? (p.relRequester === user?.id ? 'Requested ✓' : 'Accept')
    : 'Connect';

  const Row = ({ p, children }: { p: F; children?: React.ReactNode }) => (
    <View style={s.row}>
      <Pressable style={{ flexDirection: 'row', alignItems: 'center', flex: 1 }} onPress={() => router.push(`/user/${p.id}`)}>
        <Avatar photo={p.photo} name={p.name} size={40} />
        <View style={{ marginLeft: 10, flex: 1 }}>
          <Text style={{ fontWeight: '600', color: C.ink }}>{p.name}</Text>
          {!!p.place && <Text style={{ color: C.soft, fontSize: 12 }}>{p.place}</Text>}
        </View>
      </Pressable>
      {children}
    </View>
  );

  const tabs = (
    <View style={s.tabs}>
      {([['friends', 'Friends'], ['add', 'Add friends'], ['requests', `Requests${fd.pending.length ? ` (${fd.pending.length})` : ''}`]] as const).map(([k, t]) => (
        <Pressable key={k} style={[s.tab, tab === k && s.tabOn]} onPress={() => setTab(k)}>
          <Text style={[s.tabT, tab === k && s.tabOnT]}>{t}</Text>
        </Pressable>
      ))}
    </View>
  );

  if (tab !== 'add') {
    return (
      <Screen pad={false}>
        <View style={{ padding: 16 }}>
          <TopBar />
          {tabs}
        </View>
        <FlatList
          data={tab === 'friends' ? fd.friends : []}
          keyExtractor={(p) => String(p.id)}
          contentContainerStyle={{ paddingHorizontal: 16 }}
          refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); load(); }} tintColor={C.green} />}
          renderItem={({ item: p }) => (
            <Row p={p}>
              <Btn label="Message" small ghost onPress={() => message(p.id)} />
            </Row>
          )}
          ListHeaderComponent={
            tab === 'requests' ? (() => (
              <View>
                {fd.pending.length > 0 && (
                  <>
                    <Text style={s.sh}>Waiting on you ({fd.pending.length})</Text>
                    <Card>
                      {fd.pending.map((p) => (
                        <Row key={p.id} p={p}>
                          <View style={{ flexDirection: 'row', gap: 6 }}>
                            <Btn label="Accept" small onPress={() => req(p.id, 'accept')} />
                            <Btn label="✕" small ghost onPress={() => req(p.id, 'decline')} />
                          </View>
                        </Row>
                      ))}
                    </Card>
                  </>
                )}
                {fd.outgoing.length > 0 && (
                  <>
                    <Text style={s.sh}>Sent by you ({fd.outgoing.length})</Text>
                    <Card>
                      {fd.outgoing.map((p) => (
                        <Row key={p.id} p={p}>
                          <Btn label="Cancel" small ghost onPress={() => req(p.id, 'cancel')} />
                        </Row>
                      ))}
                    </Card>
                  </>
                )}
              </View>
            )) : undefined
          }
          ListEmptyComponent={() => (loading ? <Loading /> : (
            tab === 'requests'
              ? (fd.pending.length || fd.outgoing.length ? null : <Empty msg="No requests right now." />)
              : <Empty msg="No friends yet — find your people in Add friends." />
          ))}
        />
      </Screen>
    );
  }

  return (
    <Screen>
      <TopBar />
      <FlatList
        data={items}
        keyExtractor={(p) => String(p.id)}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); load(); }} tintColor={C.green} />}
        ListHeaderComponent={
          <View>
            {tabs}
            <Input placeholder="Search people…" value={f.q} onChangeText={onSearch} />
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
        renderItem={({ item: p }) => {
          const quote = (p.funFact || p.bio || '').trim();
          return (
            <View style={s.card}>
              <Pressable onPress={() => router.push(`/user/${p.id}`)}>
                <View style={s.ring}><Avatar photo={p.photo} name={p.name} size={64} /></View>
              </Pressable>
              <View style={s.mid}>
                <Pressable onPress={() => router.push(`/user/${p.id}`)}>
                  <Text style={s.name} numberOfLines={1}>{p.name}</Text>
                </Pressable>
                <Text style={s.meta} numberOfLines={1}>
                  📍 {[p.place || 'Kenya', ...[p.age, p.gender].filter(Boolean)].join(' · ')}
                </Text>
                <Pressable style={[s.btn, p.isFriend && s.btnDone]} disabled={p.isFriend} onPress={() => act(p)}>
                  <Text style={[s.btnT, p.isFriend && s.btnDoneT]}>{label(p)}</Text>
                </Pressable>
                {p.shared?.length ? (
                  <Text style={s.shared} numberOfLines={1}>✓ {p.shared.slice(0, 2).join(' · ')}</Text>
                ) : null}
              </View>
              {quote ? (
                <View style={s.quote}>
                  <Text style={s.qm}>❝</Text>
                  <Text style={s.qt} numberOfLines={4}>{quote}</Text>
                </View>
              ) : null}
            </View>
          );
        }}
        ListEmptyComponent={() => (loading ? null : <Empty msg="No one matches — try widening your filters." />)}
      />
    </Screen>
  );
}

const s = StyleSheet.create({
  tabs: { flexDirection: 'row', gap: 8, marginBottom: 12 },
  tab: {
    flex: 1, alignItems: 'center', paddingVertical: 9, borderRadius: 99,
    borderWidth: 1, borderColor: '#d8ddd3', backgroundColor: C.card,
  },
  tabOn: { backgroundColor: C.green, borderColor: C.green },
  tabT: { fontWeight: '700', fontSize: 13, color: C.ink },
  tabOnT: { color: '#fff' },
  sh: { fontWeight: '700', color: C.ink, marginTop: 4, marginBottom: 8 },
  row: { flexDirection: 'row', alignItems: 'center', paddingVertical: 7, gap: 8 },
  card: {
    flexDirection: 'row', alignItems: 'center', backgroundColor: C.card,
    borderWidth: 1, borderColor: '#e4e6db', borderRadius: 16, padding: 14, marginBottom: 10, gap: 14,
  },
  ring: { borderWidth: 2, borderColor: C.green, borderRadius: 99, padding: 2 },
  mid: { flex: 1, minWidth: 0, gap: 4 },
  name: { fontFamily: serif, fontWeight: '700', color: C.ink, fontSize: 19 },
  meta: { color: C.soft, fontSize: 12 },
  shared: { color: C.green, fontSize: 11, fontWeight: '600' },
  btn: {
    alignSelf: 'flex-start', borderWidth: 1, borderColor: '#d8ddd3', borderRadius: 99,
    paddingHorizontal: 16, paddingVertical: 7, marginTop: 2,
  },
  btnDone: { borderColor: C.green, backgroundColor: '#dce9d3' },
  btnT: { fontSize: 13, fontWeight: '600', color: C.ink },
  btnDoneT: { color: C.green },
  quote: { width: 108, flexDirection: 'row', gap: 4, alignItems: 'flex-start' },
  qm: { color: C.accent, fontFamily: serif, fontSize: 22, lineHeight: 20 },
  qt: { flex: 1, fontFamily: serif, fontStyle: 'italic', fontSize: 12.5, lineHeight: 17, color: C.soft },
});
