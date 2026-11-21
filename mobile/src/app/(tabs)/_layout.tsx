import React, { useCallback, useState } from 'react';
import { Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { Tabs, router, useFocusEffect } from 'expo-router';
import { api } from '../../lib/api';
import { C } from '../../lib/theme';

function TabIcon({ emoji, focused }: { emoji: string; focused: boolean }) {
  return <Text style={{ fontSize: 22, opacity: focused ? 1 : 0.5 }}>{emoji}</Text>;
}

export default function TabsLayout() {
  const [unread, setUnread] = useState(0);
  const insets = useSafeAreaInsets();

  const poll = useCallback(async () => {
    try {
      const d = await api('notifications', { count: 1 });
      setUnread((d.unreadMsg ?? 0) + (d.unread ?? 0));
    } catch { /* offline */ }
  }, []);

  useFocusEffect(useCallback(() => {
    poll();
    const t = setInterval(poll, 30000);
    return () => clearInterval(t);
  }, [poll]));

  return (
    <Tabs
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: C.green,
        tabBarInactiveTintColor: C.soft,
        tabBarStyle: {
          backgroundColor: C.cream, borderTopColor: C.line,
          height: 62 + insets.bottom, paddingBottom: 8 + insets.bottom,
        },
        tabBarLabelStyle: { fontSize: 11 },
      }}>
      <Tabs.Screen name="discover" options={{
        title: 'Discover',
        tabBarIcon: (p) => <TabIcon emoji="🔎" focused={p.focused} />,
      }} />
      <Tabs.Screen name="people" options={{
        title: 'People',
        tabBarIcon: (p) => <TabIcon emoji="🧑‍🤝‍🧑" focused={p.focused} />,
      }} />
      <Tabs.Screen
        name="create"
        options={{
          title: '',
          tabBarIcon: () => (
            <View style={{
              width: 46, height: 46, borderRadius: 23, backgroundColor: C.accent,
              alignItems: 'center', justifyContent: 'center', marginTop: -18,
            }}>
              <Text style={{ color: '#fff', fontSize: 26, fontWeight: '600', marginTop: -2 }}>＋</Text>
            </View>
          ),
        }}
        listeners={{ tabPress: (e) => { e.preventDefault(); router.push('/event/edit'); } }}
      />
      <Tabs.Screen name="chats" options={{
        title: 'Chats',
        tabBarBadge: unread > 0 ? unread : undefined,
        tabBarIcon: (p) => <TabIcon emoji="💬" focused={p.focused} />,
      }} />
      <Tabs.Screen name="plans" options={{
        title: 'Plans',
        tabBarIcon: (p) => <TabIcon emoji="📅" focused={p.focused} />,
      }} />
    </Tabs>
  );
}
