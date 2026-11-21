import React, { useCallback, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { router, useFocusEffect } from 'expo-router';
import { Avatar } from './ui';
import { api } from '../lib/api';
import { useAuth } from '../lib/store';
import { C, serifBlack } from '../lib/theme';

export default function TopBar() {
  const { user } = useAuth();
  const [unread, setUnread] = useState(0);
  useFocusEffect(useCallback(() => {
    let on = true;
    api('notifications', { count: 1 }).then((d) => on && setUnread(d.unread ?? 0)).catch(() => {});
    return () => { on = false; };
  }, []));

  return (
    <View style={s.bar}>
      <Text style={s.brand}>Nipange<Text style={{ color: C.accent }}>.</Text></Text>
      <View style={{ flex: 1 }} />
      <Pressable onPress={() => router.push('/notifications')} style={{ padding: 6 }}>
        <Text style={{ fontSize: 20 }}>🔔</Text>
        {unread > 0 && (
          <View style={s.dot}><Text style={s.dotTxt}>{unread > 9 ? '9+' : unread}</Text></View>
        )}
      </Pressable>
      <Pressable onPress={() => router.push('/profile')} style={{ padding: 6, paddingRight: 0 }}>
        <Avatar photo={user?.photo} name={user?.name} size={32} />
      </Pressable>
    </View>
  );
}

const s = StyleSheet.create({
  bar: { flexDirection: 'row', alignItems: 'center', marginBottom: 12 },
  brand: { fontFamily: serifBlack, fontSize: 26, color: C.deep },
  dot: {
    position: 'absolute', top: 0, right: 0, backgroundColor: C.accent,
    borderRadius: 8, minWidth: 16, height: 16, alignItems: 'center', justifyContent: 'center',
  },
  dotTxt: { color: '#fff', fontSize: 10, fontWeight: '700', paddingHorizontal: 3 },
});
