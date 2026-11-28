import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Alert, FlatList, Image, KeyboardAvoidingView, Platform, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { router, useLocalSearchParams } from 'expo-router';
import * as ImagePicker from 'expo-image-picker';
import * as DocumentPicker from 'expo-document-picker';
import { useAudioRecorder, useAudioPlayer, RecordingPresets, requestRecordingPermissionsAsync } from 'expo-audio';
import { useVideoPlayer, VideoView } from 'expo-video';
import { api, apiUpload, fetchViewOnce, imgUrl } from '../../lib/api';
import { Avatar, Screen } from '../../components/ui';
import { C } from '../../lib/theme';

type Msg = {
  id: number; mine: boolean; body: string; time: string;
  att?: string; attName?: string; attKind?: string;
  viewOnce?: boolean; opened?: boolean;
};
type Staged = { uri: string; name: string; mime: string; kind: 'image' | 'audio' | 'video' | 'file' };

const VO_LABEL: Record<string, string> = { audio: '🎤 voice note', video: '🎬 video', image: '📷 photo' };

function AudioBubble({ uri, mine }: { uri: string; mine: boolean }) {
  const player = useAudioPlayer(uri);
  const [playing, setPlaying] = useState(false);
  return (
    <Pressable style={[b.audio, mine ? b.audioMine : null]} onPress={() => {
      if (player.playing) { player.pause(); setPlaying(false); }
      else { player.seekTo(0); player.play(); setPlaying(true); }
    }}>
      <Text style={{ fontSize: 20 }}>{playing ? '⏸' : '▶️'}</Text>
      <View style={b.audioWave} />
    </Pressable>
  );
}

function VideoBubble({ uri }: { uri: string }) {
  const player = useVideoPlayer(uri);
  return <VideoView player={player} style={b.video} contentFit="cover" nativeControls />;
}

// View-once chip: tap → stream once via the gated route → show inline.
function ViewOnceBubble({ m, onOpened }: { m: Msg; onOpened: (id: number, uri: string) => void }) {
  const [busy, setBusy] = useState(false);
  const label = `View once ${VO_LABEL[m.attKind ?? 'image'] ?? 'media'}`;
  if (m.opened) return <Text style={b.onceOpened}>✓ {label} — opened</Text>;
  if (m.mine) return <Text style={b.onceMine}>🔥 {label}</Text>;
  return (
    <Pressable style={b.onceBtn} disabled={busy} onPress={async () => {
      setBusy(true);
      try { onOpened(m.id, await fetchViewOnce(m.id)); }
      catch (e: any) { Alert.alert('Could not open', e?.message ?? 'It may already be viewed'); setBusy(false); }
    }}>
      <Text style={b.onceTxt}>🔥 {label} — {busy ? 'opening…' : 'tap to open'}</Text>
    </Pressable>
  );
}

export default function ChatThread() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const [items, setItems] = useState<Msg[]>([]);
  const [other, setOther] = useState<any>(null);
  const [typing, setTyping] = useState(false);
  const [muted, setMuted] = useState(false);
  const [body, setBody] = useState('');
  const [att, setAtt] = useState<Staged | null>(null);
  const [once, setOnce] = useState(false);
  const [voUris, setVoUris] = useState<Record<number, string>>({});
  const [recSecs, setRecSecs] = useState(0);
  const lastId = useRef(0);
  const flat = useRef<FlatList>(null);
  const recTimer = useRef<ReturnType<typeof setInterval> | null>(null);
  const recorder = useAudioRecorder(RecordingPresets.HIGH_QUALITY);
  const [recording, setRecording] = useState(false);

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
    setBody(''); const a = att; setAtt(null); const vo = once; setOnce(false);
    try {
      const d = a
        ? await apiUpload('messages', {}, { uri: a.uri, name: a.name, type: a.mime },
            { conv: Number(id), body: b, view_once: vo ? 1 : 0 })
        : await api('messages', {}, { conv: Number(id), body: b });
      lastId.current = d.message.id;
      setItems((old) => [...old, d.message]);
    } catch (e: any) {
      Alert.alert('Couldn’t send', e.message);
      setBody(b); setAtt(a); setOnce(vo);
    }
  };

  const stage = (uri: string, name: string, mime: string, kind: Staged['kind']) =>
    setAtt({ uri, name, mime, kind });

  const pickImage = async () => {
    const r = await ImagePicker.launchImageLibraryAsync({ mediaTypes: ['images'], quality: 0.85 });
    const a = r.assets?.[0];
    if (!r.canceled && a) stage(a.uri, a.fileName ?? 'photo.jpg', a.mimeType ?? 'image/jpeg', 'image');
  };

  const pickVideo = async () => {
    const r = await ImagePicker.launchImageLibraryAsync({ mediaTypes: ['videos'], videoMaxDuration: 120 });
    const a = r.assets?.[0];
    if (!r.canceled && a) stage(a.uri, a.fileName ?? 'video.mp4', a.mimeType ?? 'video/mp4', 'video');
  };

  const pickDoc = async () => {
    const r = await DocumentPicker.getDocumentAsync({ copyToCacheDirectory: true });
    const a = r.assets?.[0];
    if (!r.canceled && a) stage(a.uri, a.name, a.mimeType ?? 'application/octet-stream', 'file');
  };

  const toggleRecord = async () => {
    if (recording) {
      setRecording(false);
      if (recTimer.current) clearInterval(recTimer.current);
      await recorder.stop();
      if (recorder.uri) stage(recorder.uri, 'voice-note.m4a', 'audio/mp4', 'audio');
      return;
    }
    const { granted } = await requestRecordingPermissionsAsync();
    if (!granted) { Alert.alert('Microphone needed', 'Allow mic access to send voice notes.'); return; }
    try {
      await recorder.prepareToRecordAsync();
      recorder.record();
      setRecording(true); setRecSecs(0);
      recTimer.current = setInterval(() => setRecSecs((x) => x + 1), 1000);
    } catch { Alert.alert('Could not record', 'Try again.'); }
  };

  const attach = () => Alert.alert('Attach', '', [
    { text: 'Photo', onPress: pickImage },
    { text: 'Video', onPress: pickVideo },
    { text: 'Voice note', onPress: toggleRecord },
    { text: 'Document', onPress: pickDoc },
    { text: 'Cancel', style: 'cancel' },
  ]);

  const block = () => Alert.alert('Block this user?', 'They won’t be able to message or see you.', [
    { text: 'Cancel', style: 'cancel' },
    { text: 'Block', style: 'destructive', onPress: () => other && api('friend', {}, { uid: other.id, do: 'block' }).then(() => router.back()) },
  ]);

  const renderAttachment = (m: Msg) => {
    if (m.viewOnce && m.att) {
      const uri = voUris[m.id];
      if (uri) {
        if (m.attKind === 'video') return <VideoBubble uri={uri} />;
        if (m.attKind === 'audio') return <AudioBubble uri={uri} mine={m.mine} />;
        return <Image source={{ uri }} style={s.attImg} />;
      }
      return <ViewOnceBubble m={m} onOpened={(mid, u) => {
        setVoUris((o) => ({ ...o, [mid]: u }));
        setItems((old) => old.map((x) => x.id === mid ? { ...x, opened: true } : x));
      }} />;
    }
    if (!m.att) return null;
    if (m.attKind === 'image') return <Image source={{ uri: imgUrl(m.att) }} style={s.attImg} />;
    if (m.attKind === 'audio') return <AudioBubble uri={imgUrl(m.att)!} mine={m.mine} />;
    if (m.attKind === 'video') return <VideoBubble uri={imgUrl(m.att)!} />;
    return <Text style={m.mine ? s.mineTxt : s.theirTxt}>📄 {m.attName || m.att}</Text>;
  };

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
                {renderAttachment(m)}
                {m.body ? <Text style={m.mine ? s.mineTxt : s.theirTxt}>{m.body}</Text> : null}
                <Text style={s.time}>{m.time}</Text>
              </View>
            </View>
          )}
        />

        {recording && (
          <View style={s.attBar}>
            <Text style={{ flex: 1, color: C.warn, fontWeight: '700' }}>⏺ {recSecs}s — tap 🎤 to stop</Text>
          </View>
        )}
        {att && (
          <View style={s.attBar}>
            <Text numberOfLines={1} style={{ flex: 1, color: C.ink }}>📎 {att.name}</Text>
            {att.kind !== 'file' && (
              <Pressable onPress={() => setOnce(!once)} style={[s.onceChip, once && s.onceChipOn]}>
                <Text style={{ fontSize: 12, color: once ? '#fff' : C.ink }}>🔥 view once</Text>
              </Pressable>
            )}
            <Pressable onPress={() => { setAtt(null); setOnce(false); }}><Text style={{ color: C.warn, fontWeight: '700' }}>✕</Text></Pressable>
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
  onceChip: { borderWidth: 1, borderColor: C.line, borderRadius: 99, paddingHorizontal: 10, paddingVertical: 3 },
  onceChipOn: { backgroundColor: C.accent ?? '#d45c36', borderColor: 'transparent' },
  composer: { flexDirection: 'row', padding: 10, gap: 8, borderTopWidth: 1, borderTopColor: C.line, backgroundColor: C.cream },
  attachBtn: { justifyContent: 'center', paddingHorizontal: 4 },
  input: {
    flex: 1, backgroundColor: C.card, borderWidth: 1, borderColor: '#d8ddd3',
    borderRadius: 99, paddingHorizontal: 16, paddingVertical: 10, color: C.ink,
  },
  send: { width: 44, height: 44, borderRadius: 22, backgroundColor: C.green, alignItems: 'center', justifyContent: 'center' },
});

const b = StyleSheet.create({
  audio: { flexDirection: 'row', alignItems: 'center', gap: 8, paddingVertical: 4, marginBottom: 4 },
  audioMine: {},
  audioWave: { width: 120, height: 3, borderRadius: 2, backgroundColor: 'rgba(255,255,255,.4)' },
  video: { width: 200, height: 200, borderRadius: 10, marginBottom: 4, backgroundColor: '#000' },
  onceBtn: { backgroundColor: '#d45c36', borderRadius: 99, paddingHorizontal: 12, paddingVertical: 8, marginBottom: 4 },
  onceTxt: { color: '#fff', fontWeight: '700', fontSize: 13 },
  onceMine: { color: '#fff', fontWeight: '600', marginBottom: 4 },
  onceOpened: { color: 'rgba(120,110,90,.9)', fontSize: 13, marginBottom: 4 },
});
