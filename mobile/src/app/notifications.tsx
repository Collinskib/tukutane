import React, { useCallback, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text } from 'react-native';
import { router, useFocusEffect } from 'expo-router';
import { api } from '../lib/api';
import { Card, Empty, Loading, Screen } from '../components/ui';
import { C } from '../lib/theme';

// notification.link is a web path like "event.php?id=3" or "messages.php?c=7"
// — map it to an app route.
function toRoute(link: string): string | null {
  const m = link.match(/^event\.php\?id=(\d+)/);    if (m) return `/event/${m[1]}`;
  const c = link.match(/^messages\.php\?c=(\d+)/);  if (c) return `/chat/${c[1]}`;
  const u = link.match(/^user\.php\?id=(\d+)/);     if (u) return `/user/${u[1]}`;
  const g = link.match(/^group\.php\?id=(\d+)/);    if (g) return `/group/${g[1]}`;
  if (/^people\.php/.test(link)) return '/people';
  if (/^friends\.php/.test(link)) return '/friends';
  if (/^plans\.php/.test(link)) return '/(tabs)/plans';
  if (/^index\.php/.test(link)) return '/(tabs)/discover';
  return null;
}

export default function Notifications() {
  const [items, setItems] = useState<any[] | null>(null);
  const load = useCallback(async () => {
    try {
      const d = await api('notifications');
      setItems(d.items);
      api('notifications', {}, {}).catch(() => {}); // mark read
    } catch { setItems([]); }
  }, []);
  useFocusEffect(useCallback(() => { load(); }, [load]));

  return (
    <Screen pad={false}>
      <ScrollView contentContainerStyle={{ padding: 16 }}>
        <Pressable onPress={() => router.back()}><Text style={s.back}>‹ Back</Text></Pressable>
        <Text style={s.h}>Notifications</Text>
        {!items && <Loading />}
        {items?.length === 0 && <Empty msg="All caught up." />}
        {items?.map((n) => {
          const route = toRoute(n.link || '');
          return (
            <Pressable key={n.id} onPress={() => route && router.push(route as any)}>
              <Card style={[n.unread && { borderLeftWidth: 3, borderLeftColor: C.accent }, { marginBottom: 8 }]}>
                <Text style={{ color: C.ink, lineHeight: 20 }}>{n.body}</Text>
                <Text style={{ color: C.soft, fontSize: 12, marginTop: 4 }}>{n.ago}</Text>
              </Card>
            </Pressable>
          );
        })}
      </ScrollView>
    </Screen>
  );
}

const s = StyleSheet.create({
  back: { color: C.green, fontWeight: '600', marginBottom: 10 },
  h: { fontSize: 24, fontWeight: '700', color: C.ink, marginBottom: 12 },
});
