import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Alert, FlatList, Image, KeyboardAvoidingView, Platform, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { router, useLocalSearchParams } from 'expo-router';
import * as ImagePicker from 'expo-image-picker';
import * as DocumentPicker from 'expo-document-picker';
import * as FileSystem from 'expo-file-system/legacy';
import { api, imgUrl } from '../../lib/api';
import { Avatar, Screen } from '../../components/ui';
import { C } from '../../lib/theme';

type Msg = { id: number; mine: boolean; body: string; time: string; att?: string; attName?: string; attKind?: string };

export default function ChatThread() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const [items, setItems] = useState<Msg[]>([]);
  const [other, setOther] = useState<any>(null);
  const [typing, setTyping] = useState(false);
  const [muted, setMuted] = useState(false);
  const [body, setBody] = useState('');
  const [att, setAtt] = useState<{ b64: string; name: string } | null>(null);
  const lastId = useRef(0);
  const flat = useRef<FlatList>(null);

  const load = useCallback(async () => {
    try {
      const d = await api('messages', { conv: id, after: lastId.current });
      if (d.items.length) {
        lastId.current = d.items[d.items.length - 1].id;
        setItems((old) => [...old, ...d.items]);
      }
      setOther(d.other); setTyping(!!d.typing); setMuted(!!d.muted);
    } catch { /* blocked/offline */ }
  }, [id]);

  useEffect(() => {
    load();
    const t = setInterval(load, 3000);
    return () => clearInterval(t);
  }, [load]);

  const send = async () => {
    const b = body.trim();
    if (!b && !att) return;
    const payload: any = { conv: Number(id), body: b };
    if (att) { payload.att_b64 = att.b64; payload.att_name = att.name; }
    setBody(''); const a = att; setAtt(null);
    try {
      const d = await api('messages', {}, payload);
      lastId.current = d.message.id;
      setItems((old) => [...old, d.message]);
    } catch (e: any) {
      Alert.alert('Couldn’t send', e.message);
      setBody(b); setAtt(a);
    }
  };

  const pickImage = async () => {
    const r = await ImagePicker.launchImageLibraryAsync({ mediaTypes: ['images'], quality: 0.85, base64: true });
    if (!r.canceled && r.assets[0]?.base64) {
      setAtt({ b64: r.assets[0].base64, name: r.assets[0].fileName ?? 'photo.jpg' });
    }
  };

  const pickDoc = async () => {
    const r = await DocumentPicker.getDocumentAsync({ copyToCacheDirectory: true });
    if (!r.canceled && r.assets[0]) {
      const a = r.assets[0];
      const b64 = await FileSystem.readAsStringAsync(a.uri, { encoding: 'base64' });
      setAtt({ b64, name: a.name });
    }
  };

  const attach = () => Alert.alert('Attach', '', [
    { text: 'Photo', onPress: pickImage },
    { text: 'Document', onPress: pickDoc },
    { text: 'Cancel', style: 'cancel' },
  ]);

  const block = () => Alert.alert('Block this user?', 'They won’t be able to message or see you.', [
    { text: 'Cancel', style: 'cancel' },
    { text: 'Block', style: 'destructive', onPress: () => other && api('friend', {}, { uid: other.id, do: 'block' }).then(() => router.back()) },
  ]);

  return (
    <Screen pad={false} bottom>
      <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <View style={s.head}>
          <Pressable onPress={() => router.back()}><Text style={s.back}>‹</Text></Pressable>
          {other && (
            <Pressable style={{ flex: 1, flexDirection: 'row', alignItems: 'center', gap: 8 }}
              onPress={() => router.push(`/user/${other.id}`)}>
              <Avatar photo={other.photo} name={other.name} size={32} />
              <View>
                <Text style={s.headTitle}>{other.name}</Text>
                {typing && <Text style={{ color: C.green, fontSize: 11 }}>typing…</Text>}
              </View>
            </Pressable>
          )}
          <Pressable onPress={() => other && api('messages', {}, { conv: Number(id), do: 'mute', on: !muted }).then(() => setMuted(!muted))}>
            <Text style={{ fontSize: 18, paddingHorizontal: 6 }}>{muted ? '🔕' : '🔔'}</Text>
          </Pressable>
          <Pressable onPress={block}><Text style={{ fontSize: 18, paddingHorizontal: 4 }}>🚫</Text></Pressable>
        </View>

        <FlatList
          ref={flat} data={items}
          keyExtractor={(m) => String(m.id)}
          contentContainerStyle={{ padding: 12 }}
          onContentSizeChange={() => flat.current?.scrollToEnd({ animated: false })}
          renderItem={({ item: m }) => (
            <View style={[s.row, m.mine && { justifyContent: 'flex-end' }]}>
              <View style={[s.bubble, m.mine ? s.mine : s.theirs]}>
                {m.attKind === 'image' && m.att ? (
                  <Image source={{ uri: imgUrl(m.att) }} style={s.attImg} />
                ) : null}
                {m.attKind === 'file' && m.att ? (
                  <Text style={m.mine ? s.mineTxt : s.theirTxt}>📄 {m.attName || m.att}</Text>
                ) : null}
                {m.body ? <Text style={m.mine ? s.mineTxt : s.theirTxt}>{m.body}</Text> : null}
                <Text style={s.time}>{m.time}</Text>
              </View>
            </View>
          )}
        />

        {att && (
          <View style={s.attBar}>
            <Text numberOfLines={1} style={{ flex: 1, color: C.ink }}>📎 {att.name}</Text>
            <Pressable onPress={() => setAtt(null)}><Text style={{ color: C.warn, fontWeight: '700' }}>✕</Text></Pressable>
          </View>
        )}
        <View style={s.composer}>
          <Pressable onPress={attach} style={s.attachBtn}><Text style={{ fontSize: 20 }}>📎</Text></Pressable>
          <TextInput
            style={s.input} value={body} onChangeText={(t) => {
              setBody(t);
              api('messages', {}, { conv: Number(id), do: 'typing' }).catch(() => {});
            }}
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
  row: { flexDirection: 'row', marginBottom: 8 },
  bubble: { maxWidth: '75%', borderRadius: 16, padding: 10 },
  mine: { backgroundColor: C.green, borderBottomRightRadius: 4 },
  theirs: { backgroundColor: C.card, borderWidth: 1, borderColor: '#e4e6db', borderBottomLeftRadius: 4 },
  mineTxt: { color: '#fff' },
  theirTxt: { color: C.ink },
  time: { color: 'rgba(255,255,255,0.7)', fontSize: 10, marginTop: 3, alignSelf: 'flex-end' },
  attImg: { width: 180, height: 180, borderRadius: 10, marginBottom: 4, backgroundColor: C.line },
  attBar: { flexDirection: 'row', paddingHorizontal: 14, paddingVertical: 6, alignItems: 'center', gap: 8 },
  composer: { flexDirection: 'row', padding: 10, gap: 8, borderTopWidth: 1, borderTopColor: C.line, backgroundColor: C.cream },
  attachBtn: { justifyContent: 'center', paddingHorizontal: 4 },
  input: {
    flex: 1, backgroundColor: C.card, borderWidth: 1, borderColor: '#d8ddd3',
    borderRadius: 99, paddingHorizontal: 16, paddingVertical: 10, color: C.ink,
  },
  send: { width: 44, height: 44, borderRadius: 22, backgroundColor: C.green, alignItems: 'center', justifyContent: 'center' },
});
