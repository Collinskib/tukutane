import React, { useCallback, useRef, useState } from 'react';
import { FlatList, ImageBackground, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { router, useFocusEffect } from 'expo-router';
import { api } from '../../lib/api';
import { useAuth } from '../../lib/store';
import { Btn, Chip, Empty, Err, Input, Loading, Screen, Select } from '../../components/ui';
import EventCard, { Ev } from '../../components/EventCard';
import TopBar from '../../components/TopBar';
import { C, serifBlack } from '../../lib/theme';

const WHEN = [['', 'Any time'], ['today', 'Today'], ['tomorrow', 'Tomorrow'], ['weekend', 'This weekend']];
const COST = [['', 'Any cost'], ['free', 'Free'], ['paid', 'Paid']];
const SORT = [['soon', 'Soonest'], ['popular', 'Popular'], ['featured', 'Featured']];

export default function Discover() {
  const { meta } = useAuth();
  const [items, setItems] = useState<Ev[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [err, setErr] = useState('');
  const [page, setPage] = useState(1);
  const [showFilters, setShowFilters] = useState(false);
  const [f, setF] = useState({ county: '', area: '', interest: '', when: '', cost: '', sort: 'soon', q: '' });
  const deb = useRef<ReturnType<typeof setTimeout> | null>(null);

  const load = useCallback(async (p = 1, append = false, ff = f) => {
    try {
      const d = await api('feed', { ...ff, page: p, per: 20 });
      setItems((old) => (append ? [...old, ...d.items] : d.items));
      setPage(p);
      setErr('');
    } catch (e: any) {
      setErr(e.message);
    }
    setLoading(false); setRefreshing(false);
  }, [f]);

  useFocusEffect(useCallback(() => { load(1); }, [load]));

  const applyF = (patch: Partial<typeof f>) => { setLoading(true); setF((p) => ({ ...p, ...patch })); };

  const onSearch = (q: string) => {
    setF((p) => ({ ...p, q }));
    if (deb.current) clearTimeout(deb.current);
    deb.current = setTimeout(() => { setLoading(true); load(1, false, { ...f, q }); }, 350);
  };

  const subs = f.county ? Object.keys(meta?.areas?.[f.county] ?? {}) : [];

  return (
    <Screen>
      <TopBar />
      <FlatList
        data={items}
        keyExtractor={(e) => String(e.id)}
        renderItem={({ item }) => <EventCard ev={item} />}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); load(1); }} tintColor={C.green} />}
        onEndReached={() => items.length >= 20 * page && load(page + 1, true)}
        onEndReachedThreshold={0.4}
        ListHeaderComponent={
          <View>
            {/* hero */}
            <ImageBackground
              source={require('../../../assets/hero.webp')}
              style={s.hero} imageStyle={{ borderRadius: 22 }}>
              <View style={s.heroShade}>
                <Text style={s.heroKick}>ACROSS KENYA</Text>
                <Text style={s.heroTitle}>Find your people.</Text>
                <Text style={s.heroSub}>Find a game to watch, a place to dance, or a group to spend your Saturday with.</Text>
              </View>
            </ImageBackground>

            <View style={{ flexDirection: 'row', gap: 8, marginBottom: 10 }}>
              <View style={{ flex: 1 }}>
                <Input placeholder="Search events, places…" value={f.q} onChangeText={onSearch} />
              </View>
              <Btn label="Filters" ghost small onPress={() => setShowFilters((v) => !v)} />
            </View>

            {showFilters && (
              <View style={s.filters}>
                <Select label="County" value={f.county}
                  options={[{ value: '', label: 'All counties' }, ...(meta?.counties ?? []).map((c) => ({ value: c, label: c }))]}
                  onChange={(v) => applyF({ county: v, area: '' })} />
                <Select label="Area" value={f.area}
                  options={[{ value: '', label: 'Any area' }, ...subs.map((a) => ({ value: a, label: a }))]}
                  onChange={(v) => applyF({ area: v })} />
                <Select label="Sort" value={f.sort}
                  options={SORT.map(([v, l]) => ({ value: v, label: l }))}
                  onChange={(v) => applyF({ sort: v })} />
              </View>
            )}

            <ScrollView horizontal showsHorizontalScrollIndicator={false} style={{ marginBottom: 2 }}>
              {['All interests', ...(meta?.categories ?? [])].map((c) => (
                <Chip key={c} label={c} active={(f.interest || 'All interests') === c}
                  onPress={() => applyF({ interest: c === 'All interests' ? '' : c })} />
              ))}
            </ScrollView>
            <ScrollView horizontal showsHorizontalScrollIndicator={false}>
              {WHEN.map(([v, l]) => (
                <Chip key={v} label={l} active={f.when === v} onPress={() => applyF({ when: v })} />
              ))}
              {COST.map(([v, l]) => (
                <Chip key={v} label={l} active={f.cost === v} onPress={() => applyF({ cost: v })} />
              ))}
            </ScrollView>

            <View style={{ flexDirection: 'row', gap: 14, marginBottom: 10 }}>
              <Text style={s.links} onPress={() => router.push('/groups')}>Groups</Text>
              <Text style={s.links} onPress={() => router.push('/venues')}>Venues</Text>
              <Text style={s.links} onPress={() => router.push('/friends')}>Friends</Text>
              <Text style={s.links} onPress={() => router.push('/invites')}>Invites</Text>
            </View>

            <Text style={s.h2}>Events nearby</Text>
            <Err msg={err} />
            {loading && <Loading />}
          </View>
        }
        ListEmptyComponent={() => (loading ? null : <Empty msg="Nothing matches — try widening your filters." />)}
      />
    </Screen>
  );
}

const s = StyleSheet.create({
  hero: { height: 220, marginBottom: 16 },
  heroShade: {
    flex: 1, borderRadius: 22, backgroundColor: 'rgba(22,62,49,0.55)',
    padding: 22, justifyContent: 'flex-end',
  },
  heroKick: { color: C.sand, fontWeight: '700', letterSpacing: 2, fontSize: 11 },
  heroTitle: { fontFamily: serifBlack, fontSize: 34, color: '#fff8e3', marginVertical: 4 },
  heroSub: { color: '#e0e8df', fontSize: 13, maxWidth: 280 },
  filters: { marginBottom: 12 },
  links: { color: C.green, fontWeight: '600', fontSize: 15 },
  h2: { fontFamily: serifBlack, fontSize: 22, color: C.ink, marginBottom: 10 },
});
