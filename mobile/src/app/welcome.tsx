import React from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { router } from 'expo-router';
import { useAuth } from '../lib/store';
import { Screen } from '../components/ui';
import { C, serif } from '../lib/theme';

export default function Welcome() {
  const { user } = useAuth();
  const first = (user?.name ?? '').split(' ')[0] || 'there';

  return (
    <Screen bottom>
      <View style={s.wrap}>
        <Text style={s.emoji}>🎉</Text>
        <Text style={s.title}>Karibu, {first}!</Text>
        <Text style={s.sub}>You made it. What would you like to do first?</Text>

        <Pressable style={[s.tile, { backgroundColor: C.green }]} onPress={() => router.replace('/(tabs)/create')}>
          <Text style={s.tileEmoji}>📅</Text>
          <View style={{ flex: 1 }}>
            <Text style={s.tileTitle}>Plan an event</Text>
            <Text style={s.tileSub}>Coffee meetups, hikes, game nights — gather your people.</Text>
          </View>
        </Pressable>

        <Pressable style={[s.tile, { backgroundColor: C.accent }]} onPress={() => router.replace('/venue/edit')}>
          <Text style={s.tileEmoji}>🏪</Text>
          <View style={{ flex: 1 }}>
            <Text style={s.tileTitle}>List your business</Text>
            <Text style={s.tileSub}>Put your café, shop or studio on the map — free.</Text>
          </View>
        </Pressable>

        <Pressable onPress={() => router.replace('/(tabs)/discover')} style={{ marginTop: 22 }}>
          <Text style={s.skip}>Skip for now →</Text>
        </Pressable>
      </View>
    </Screen>
  );
}

const s = StyleSheet.create({
  wrap: { flex: 1, justifyContent: 'center', paddingHorizontal: 8 },
  emoji: { fontSize: 46, textAlign: 'center' },
  title: { fontFamily: serif, fontSize: 30, color: C.ink, textAlign: 'center', marginTop: 10 },
  sub: { color: C.soft, textAlign: 'center', marginTop: 6, marginBottom: 26 },
  tile: {
    flexDirection: 'row', alignItems: 'center', gap: 14, borderRadius: 18,
    padding: 18, marginBottom: 14,
  },
  tileEmoji: { fontSize: 30 },
  tileTitle: { fontFamily: serif, fontSize: 19, color: '#fff' },
  tileSub: { color: 'rgba(255,255,255,.85)', fontSize: 12.5, marginTop: 2 },
  skip: { color: C.soft, textAlign: 'center', fontSize: 14 },
});
