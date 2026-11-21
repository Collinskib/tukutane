import React, { useCallback, useEffect, useRef, useState } from 'react';
import { FlatList, KeyboardAvoidingView, Platform, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { api } from '../../lib/api';
import { Avatar, Screen } from '../../components/ui';
import { C } from '../../lib/theme';

type Msg = { id: number; mine: boolean; body: string; time: string; name: string; photo: string; pinned: boolean };

export default function EventChat() {
  const { id, title } = useLocalSearchParams<{ id: string; title?: string }>();
  
  const [items, setItems] = useState<Msg[]>([]);
  const [pinned, setPinned] = useState<Msg | null>(null);
  const [muted, setMuted] = useState(false);
  const [body, setBody] = useState('');
  const [isHost, setIsHost] = useState(false);
  const lastId = useRef(0);

  const load = useCallback(async () => {
    try {
      const d = await api('event-chat', { id, after: lastId.current });
      if (d.items.length) {
        lastId.current = d.items[d.items.length - 1].id;
        setItems((old) => [...old, ...d.items]);
      }
      setPinned(d.pinned);
    } catch { /* not allowed / offline */ }
  }, [id]);

  useEffect(() => {
    load();
    api('event', { id }).then((d) => {
      setMuted(!!d.chatMuted);
      setIsHost(!!d.event.mine);
    }).catch(() => {});
    const t = setInterval(load, 3000);
    return () => clearInterval(t);
  }, [load, id]);

  const send = async () => {
    const b = body.trim();
    if (!b) return;
    setBody('');
    try {
      const d = await api('event-chat', {}, { id: Number(id), body: b });
      lastId.current = d.message.id;
      setItems((old) => [...old, d.message]);
    } catch { setBody(b); }
  };

  const pin = (m: Msg) => api('event-chat', {}, { id: Number(id), do: 'pin', mid: m.id }).then(load);
  const toggleMute = () => api('event-chat', {}, { id: Number(id), do: 'mute', on: !muted }).then(() => setMuted(!muted));

  return (
    <Screen pad={false}>
      <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined} keyboardVerticalOffset={0}>
        <View style={s.head}>
          <Pressable onPress={() => router.back()}><Text style={s.back}>‹</Text></Pressable>
          <View style={{ flex: 1 }}>
            <Text style={s.headTitle} numberOfLines={1}>{title || 'Event chat'}</Text>
          </View>
          <Pressable onPress={toggleMute}><Text style={{ fontSize: 18 }}>{muted ? '🔕' : '🔔'}</Text></Pressable>
        </View>
        {pinned && (
          <Pressable style={s.pinned} onLongPress={() => isHost && api('event-chat', {}, { id: Number(id), do: 'unpin', mid: pinned.id }).then(load)}>
            <Text numberOfLines={1} style={{ color: C.ink }}>📌 {pinned.body}</Text>
          </Pressable>
        )}
        <FlatList
          data={items} inverted={false}
          keyExtractor={(m) => String(m.id)}
          contentContainerStyle={{ padding: 12 }}
          onContentSizeChange={() => {}}
          renderItem={({ item: m }) => (
            <Pressable onLongPress={() => isHost && !m.pinned && pin(m)} style={[s.row, m.mine && { justifyContent: 'flex-end' }]}>
              {!m.mine && <Avatar photo={m.photo} name={m.name} size={28} />}
              <View style={[s.bubble, m.mine ? s.mine : s.theirs]}>
                {!m.mine && <Text style={s.sender}>{m.name}</Text>}
                <Text style={m.mine ? s.mineTxt : s.theirTxt}>{m.body}</Text>
                <Text style={s.time}>{m.time}</Text>
              </View>
            </Pressable>
          )}
        />
        <View style={s.composer}>
          <TextInput
            style={s.input} value={body} onChangeText={setBody}
            placeholder="Message…" placeholderTextColor={C.soft}
            onSubmitEditing={send} returnKeyType="send" />
          <Pressable onPress={send} style={s.send}><Text style={{ color: '#fff', fontWeight: '700' }}>➤</Text></Pressable>
        </View>
      </KeyboardAvoidingView>
    </Screen>
  );
}

const s = StyleSheet.create({
  head: { flexDirection: 'row', alignItems: 'center', padding: 12, gap: 10, borderBottomWidth: 1, borderBottomColor: C.line },
  back: { fontSize: 26, color: C.green, marginTop: -4 },
  headTitle: { fontWeight: '700', color: C.ink, fontSize: 16 },
  pinned: { backgroundColor: '#fdf3e4', padding: 8, paddingHorizontal: 14 },
  row: { flexDirection: 'row', marginBottom: 8, alignItems: 'flex-end', gap: 8 },
  bubble: { maxWidth: '75%', borderRadius: 16, padding: 10 },
  mine: { backgroundColor: C.green, borderBottomRightRadius: 4 },
  theirs: { backgroundColor: C.card, borderWidth: 1, borderColor: '#e4e6db', borderBottomLeftRadius: 4 },
  sender: { color: C.soft, fontSize: 11, marginBottom: 2 },
  mineTxt: { color: '#fff' },
  theirTxt: { color: C.ink },
  time: { color: 'rgba(255,255,255,0.7)', fontSize: 10, marginTop: 3, alignSelf: 'flex-end' },
  composer: { flexDirection: 'row', padding: 10, gap: 8, borderTopWidth: 1, borderTopColor: C.line, backgroundColor: C.cream },
  input: {
    flex: 1, backgroundColor: C.card, borderWidth: 1, borderColor: '#d8ddd3',
    borderRadius: 99, paddingHorizontal: 16, paddingVertical: 10, color: C.ink,
  },
  send: { width: 44, height: 44, borderRadius: 22, backgroundColor: C.green, alignItems: 'center', justifyContent: 'center' },
});
