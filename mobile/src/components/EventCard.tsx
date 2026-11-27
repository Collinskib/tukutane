import React from 'react';
import { Image, Pressable, StyleSheet, Text, View } from 'react-native';
import { router } from 'expo-router';
import { C, serif } from '../lib/theme';
import { imgUrl } from '../lib/api';

export type Ev = {
  id: number; title: string; category: string; place: string; venue: string;
  at: string; atLabel: string; cost: number; going: number; capacity: number | null;
  cover: string; joined: boolean; mine: boolean; inviteOnly: boolean; status: string;
};

const CAT_ICON: Record<string, string> = {
  Football: '⚽', Dance: '💃', Nightlife: '🌙', Wellness: '🧘', Faith: '🙏',
  Hangouts: '☕', Business: '💼',
};

export default function EventCard({ ev }: { ev: Ev }) {
  const cover = imgUrl(ev.cover);
  const full = ev.capacity !== null && ev.going >= ev.capacity;
  return (
    <Pressable style={s.card} onPress={() => router.push(`/event/${ev.id}`)}>
      <View style={s.ribbon}>
        <Text style={s.ribbonCat}>{CAT_ICON[ev.category] ?? '📍'} {ev.category}</Text>
        <Text style={s.ribbonWhen}>{ev.atLabel}</Text>
      </View>
      {cover ? <Image source={{ uri: cover }} style={s.cover} /> : null}
      <View style={s.body}>
        <Text style={s.title} numberOfLines={2}>{ev.title}</Text>
        <Text style={s.meta} numberOfLines={1}>{ev.venue} · {ev.place}</Text>
        <View style={s.row}>
          <Text style={s.going}>
            {ev.going} going{ev.capacity !== null ? ` / ${ev.capacity}` : ''}
            {ev.inviteOnly ? ' · invite-only' : ''}
            {ev.status === 'cancelled' ? ' · CANCELLED' : full ? ' · FULL' : ''}
          </Text>
          <Text style={s.cost}>{ev.cost === 0 ? 'Free' : `KSh ${ev.cost}`}</Text>
        </View>
        {ev.joined ? <Text style={s.joinedTag}>✓ You’re in</Text> : null}
      </View>
    </Pressable>
  );
}

const s = StyleSheet.create({
  card: {
    backgroundColor: C.card, borderWidth: 1, borderColor: '#e4e6db',
    borderRadius: 16, overflow: 'hidden', marginBottom: 12,
  },
  ribbon: {
    backgroundColor: C.deep, paddingVertical: 9, paddingHorizontal: 14,
    flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center',
  },
  ribbonCat: { color: '#fff8e3', fontWeight: '700', fontSize: 13 },
  ribbonWhen: { color: C.sand, fontWeight: '700', fontSize: 13 },
  cover: { width: '100%', height: 140, backgroundColor: C.line },
  body: { padding: 14 },
  title: { fontFamily: serif, fontSize: 19, color: C.ink, marginBottom: 3 },
  meta: { color: C.soft, fontSize: 14, marginBottom: 8 },
  row: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  going: { color: C.soft, fontSize: 13 },
  cost: { color: C.green, fontWeight: '700' },
  joinedTag: { color: C.green, fontWeight: '700', marginTop: 6 },
});
