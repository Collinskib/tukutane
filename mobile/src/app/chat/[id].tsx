import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Alert, FlatList, Image, KeyboardAvoidingView, Platform, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { router, useLocalSearchParams } from 'expo-router';
import * as ImagePicker from 'expo-image-picker';
import * as DocumentPicker from 'expo-document-picker';
import { useAudioRecorder, useAudioPlayer, useAudioPlayerStatus, RecordingPresets, requestRecordingPermissionsAsync } from 'expo-audio';
import { useVideoPlayer, VideoView } from 'expo-video';
import { api, apiUpload, fetchViewOnce, imgUrl } from '../../lib/api';
import { Avatar, Screen } from '../../components/ui';
import { C } from '../../lib/theme';

type Msg = {
  id: number; mine: boolean; body: string; time: string; at?: string;
  att?: string; attName?: string; attKind?: string;
  viewOnce?: boolean; opened?: boolean;
};
type Staged = { uri: string; name: string; mime: string; kind: 'image' | 'audio' | 'video' | 'file' };
type Presence = { online: boolean; lastSeen?: string | null };

const VO_LABEL: Record<string, string> = { audio: 'voice note', video: 'video', image: 'photo' };

const fmtSecs = (s: number) => `${Math.floor(s / 60)}:${String(Math.floor(s % 60)).padStart(2, '0')}`;

function presenceLabel(p?: Presence | null, typing?: boolean): { text: string; live: boolean } {
  if (typing) return { text: 'typing…', live: true };
  if (!p) return { text: '', live: false };
  if (p.online) return { text: 'online', live: true };
  if (!p.lastSeen) return { text: '', live: false };
  const t = new Date(p.lastSeen.replace(' ', 'T'));
  const now = new Date();
  const sameDay = t.toDateString() === now.toDateString();
  const yday = new Date(now.getTime() - 864e5).toDateString() === t.toDateString();
  const hm = t.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
  const day = sameDay ? '' : yday ? 'yesterday ' : t.toLocaleDateString([], { weekday: 'short' }) + ' ';
  return { text: `last seen ${day}${hm}`, live: false };
}

// Deterministic fake-waveform heights per message.
function wave(seed: number): number[] {
  const out: number[] = [];
  let x = seed || 1;
  for (let i = 0; i < 26; i++) { x = (x * 1103515245 + 12345) % 2147483648; out.push(0.25 + (x % 1000) / 1400); }
  return out;
}

function AudioBubble({ uri, mine, seed }: { uri: string; mine: boolean; seed: number }) {
  const player = useAudioPlayer(uri);
  const st = useAudioPlayerStatus(player);
  const bars = useMemo(() => wave(seed), [seed]);
  const dur = st.duration || 0;
  const pos = st.playing || st.currentTime > 0 ? st.currentTime : 0;
  const toggle = () => { st.playing ? player.pause() : (pos >= dur - 0.2 ? (player.seekTo(0), player.play()) : player.play()); };
  const fg = mine ? '#fff' : C.green;
  const dim = mine ? 'rgba(255,255,255,.35)' : 'rgba(33,78,60,.22)';
  return (
    <Pressable onPress={toggle} style={b.audio}>
      <View style={[b.audioBtn, mine ? b.audioBtnMine : b.audioBtnTheirs]}>
        <Text style={{ color: '#fff', fontSize: 13, marginLeft: st.playing ? 0 : 2 }}>{st.playing ? '⏸' : '▶'}</Text>
      </View>
      <View style={b.bars}>
        {bars.map((h, i) => {
          const played = dur > 0 && i / bars.length <= pos / dur;
          return <View key={i} style={[b.bar, { height: 4 + h * 18, backgroundColor: played ? fg : dim }]} />;
        })}
      </View>
      <Text style={[b.dur, mine ? { color: 'rgba(255,255,255,.75)' } : { color: C.soft }]}>
        {fmtSecs(st.playing || pos > 0 ? pos : dur)}
      </Text>
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
    <Pressable style={({ pressed }) => [b.onceBtn, pressed && { opacity: 0.85 }]} disabled={busy} onPress={async () => {
      setBusy(true);
      try { onOpened(m.id, await fetchViewOnce(m.id)); }
      catch (e: any) { Alert.alert('Could not open', e?.message ?? 'It may already be viewed'); setBusy(false); }
    }}>
      <Text style={b.onceTxt}>🔥 {label} — {busy ? 'opening…' : 'tap to open'}</Text>
    </Pressable>
  );
}

function dayLabel(at?: string): string | null {
  if (!at) return null;
  const d = new Date(at.replace(' ', 'T'));
  const now = new Date();
  if (d.toDateString() === now.toDateString()) return 'Today';
  if (d.toDateString() === new Date(now.getTime() - 864e5).toDateString()) return 'Yesterday';
  return d.toLocaleDateString([], { weekday: 'long', month: 'short', day: 'numeric' });
}

export default function ChatThread() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const [items, setItems] = useState<Msg[]>([]);
  const [other, setOther] = useState<any>(null);
  const [presence, setPresence] = useState<Presence | null>(null);
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
      if (d.presence) setPresence(d.presence);
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

  const cancelRecord = async () => {
    setRecording(false);
    if (recTimer.current) clearInterval(recTimer.current);
    try { await recorder.stop(); } catch { /* nothing staged */ }
  };

  const attach = () => Alert.alert('Attach', '', [
    { text: '📷  Photo', onPress: pickImage },
    { text: '🎬  Video', onPress: pickVideo },
    { text: '🎤  Voice note', onPress: toggleRecord },
    { text: '📄  Document', onPress: pickDoc },
    { text: 'Cancel', style: 'cancel' },
  ]);

  const menu = () => other && Alert.alert(other.name, '', [
    { text: muted ? '🔔  Unmute' : '🔕  Mute', onPress: () => api('messages', {}, { conv: Number(id), do: 'mute', on: !muted }).then(() => setMuted(!muted)) },
    { text: '👤  View profile', onPress: () => router.push(`/user/${other.id}`) },
    {
      text: '🚫  Block', style: 'destructive', onPress: () => Alert.alert(`Block ${other.name}?`, 'You won’t see or message each other.', [
        { text: 'Cancel', style: 'cancel' },
        { text: 'Block', style: 'destructive', onPress: () => api('friend', {}, { uid: other.id, do: 'block' }).then(() => router.back()) },
      ]),
    },
    { text: 'Cancel', style: 'cancel' },
  ]);

  const renderAttachment = (m: Msg) => {
    if (m.viewOnce && m.att) {
      const uri = voUris[m.id];
      if (uri) {
        if (m.attKind === 'video') return <VideoBubble uri={uri} />;
        if (m.attKind === 'audio') return <AudioBubble uri={uri} mine={m.mine} seed={m.id} />;
        return <Image source={{ uri }} style={s.attImg} />;
      }
      return <ViewOnceBubble m={m} onOpened={(mid, u) => {
        setVoUris((o) => ({ ...o, [mid]: u }));
        setItems((old) => old.map((x) => x.id === mid ? { ...x, opened: true } : x));
      }} />;
    }
    if (!m.att) return null;
    if (m.attKind === 'image') return <Image source={{ uri: imgUrl(m.att) }} style={s.attImg} />;
    if (m.attKind === 'audio') return <AudioBubble uri={imgUrl(m.att)!} mine={m.mine} seed={m.id} />;
    if (m.attKind === 'video') return <VideoBubble uri={imgUrl(m.att)!} />;
    return (
      <View style={b.fileRow}>
        <Text style={{ fontSize: 17 }}>📄</Text>
        <Text numberOfLines={1} style={[b.fileName, m.mine ? { color: '#fff' } : { color: C.ink }]}>{m.attName || m.att}</Text>
      </View>
    );
  };

  // Day separators + sender grouping (tighten tails inside a run).
  const rows = useMemo(() => {
    const out: { msg: Msg; sep?: string; last: boolean }[] = [];
    items.forEach((m, i) => {
      const sep = dayLabel(m.at);
      const prev = items[i - 1];
      const newDay = !prev || dayLabel(prev.at) !== sep;
      const next = items[i + 1];
      const last = !next || next.mine !== m.mine || dayLabel(next.at) !== sep;
      if (newDay) out.push({ msg: m, sep: sep ?? undefined, last });
      else out.push({ msg: m, last });
    });
    return out;
  }, [items]);

  const sub = presenceLabel(presence, typing);
  const canSend = !!(body.trim() || att);

  return (
    <Screen pad={false} bottom>
      <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <View style={s.head}>
          <Pressable onPress={() => router.back()} hitSlop={10} style={({ pressed }) => [s.iconBtn, pressed && { opacity: 0.6 }]}>
            <Text style={s.back}>‹</Text>
          </Pressable>
          {other && (
            <Pressable style={({ pressed }) => [s.who, pressed && { opacity: 0.75 }]} onPress={() => router.push(`/user/${other.id}`)}>
              <View>
                <Avatar photo={other.photo} name={other.name} size={38} />
                {sub.live && <View style={s.onlineDot} />}
              </View>
              <View style={{ flex: 1, minWidth: 0 }}>
                <Text numberOfLines={1} style={s.headTitle}>{other.name}</Text>
                {!!sub.text && (
                  <Text numberOfLines={1} style={[s.presence, sub.live && { color: C.green }]}>
                    {muted ? `🔇 ${sub.text}` : sub.text}
                  </Text>
                )}
              </View>
            </Pressable>
          )}
          <Pressable onPress={menu} hitSlop={10} style={({ pressed }) => [s.iconBtn, pressed && { opacity: 0.6 }]}>
            <Text style={s.menuDots}>⋮</Text>
          </Pressable>
        </View>

        <FlatList
          ref={flat} data={rows}
          keyExtractor={(r) => String(r.msg.id)}
          contentContainerStyle={{ paddingHorizontal: 12, paddingVertical: 10, flexGrow: 1 }}
          onContentSizeChange={() => flat.current?.scrollToEnd({ animated: false })}
          ListEmptyComponent={
            <View style={s.empty}>
              <Text style={{ fontSize: 44 }}>👋</Text>
              <Text style={s.emptyTxt}>{other ? `Say hi to ${other.name}!` : 'Say hi!'}</Text>
            </View>
          }
          renderItem={({ item: r }) => {
            const m = r.msg;
            return (
              <>
                {r.sep && <View style={s.dayWrap}><Text style={s.day}>{r.sep}</Text></View>}
                <View style={[s.row, m.mine && { justifyContent: 'flex-end' }, r.last ? { marginBottom: 8 } : { marginBottom: 2 }]}>
                  <View style={[s.bubble, m.mine ? s.mine : s.theirs, r.last && (m.mine ? s.mineTail : s.theirsTail)]}>
                    {renderAttachment(m)}
                    {m.body ? <Text style={m.mine ? s.mineTxt : s.theirTxt}>{m.body}</Text> : null}
                    <Text style={[s.time, m.mine ? s.timeMine : s.timeTheirs]}>{m.time}</Text>
                  </View>
                </View>
              </>
            );
          }}
        />

        {recording ? (
          <View style={s.composer}>
            <View style={s.recPill}>
              <View style={s.recDot} />
              <Text style={s.recTime}>{fmtSecs(recSecs)}</Text>
              <Text style={s.recHint}>recording…</Text>
            </View>
            <Pressable onPress={cancelRecord} hitSlop={8} style={({ pressed }) => [s.round, s.roundGhost, pressed && { opacity: 0.7 }]}>
              <Text style={{ color: C.warn, fontSize: 16, fontWeight: '700' }}>✕</Text>
            </Pressable>
            <Pressable onPress={toggleRecord} hitSlop={8} style={({ pressed }) => [s.round, pressed && { opacity: 0.8 }]}>
              <Text style={{ color: '#fff', fontSize: 15 }}>⏹</Text>
            </Pressable>
          </View>
        ) : (
          <View style={s.composer}>
            <Pressable onPress={attach} hitSlop={8} style={({ pressed }) => [s.round, s.roundGhost, pressed && { opacity: 0.7 }]}>
              <Text style={{ color: C.green, fontSize: 20, marginTop: -2 }}>＋</Text>
            </Pressable>
            <TextInput
              style={s.input} value={body} onChangeText={(t) => {
                setBody(t);
                api('messages', {}, { conv: Number(id), do: 'typing' }).catch(() => {});
              }}
              placeholder="Message…" placeholderTextColor={C.soft} multiline
              onSubmitEditing={send} returnKeyType="send" blurOnSubmit />
            {canSend ? (
              <Pressable onPress={send} hitSlop={8} style={({ pressed }) => [s.round, pressed && { opacity: 0.8 }]}>
                <Text style={{ color: '#fff', fontSize: 17, fontWeight: '700', marginTop: -1 }}>↑</Text>
              </Pressable>
            ) : (
              <Pressable onPress={toggleRecord} hitSlop={8} style={({ pressed }) => [s.round, s.roundGhost, pressed && { opacity: 0.7 }]}>
                <Text style={{ fontSize: 17 }}>🎤</Text>
              </Pressable>
            )}
          </View>
        )}

        {att && (
          <View style={s.stageBar}>
            {att.kind === 'image'
              ? <Image source={{ uri: att.uri }} style={s.stageThumb} />
              : <View style={[s.stageThumb, s.stageThumbPh]}>
                  <Text style={{ fontSize: 18 }}>{att.kind === 'video' ? '🎬' : att.kind === 'audio' ? '🎤' : '📄'}</Text>
                </View>}
            <View style={{ flex: 1, minWidth: 0 }}>
              <Text numberOfLines={1} style={s.stageName}>{att.name}</Text>
              {att.kind !== 'file' ? (
                <Pressable onPress={() => setOnce(!once)} hitSlop={6} style={({ pressed }) => [s.onceChip, once && s.onceChipOn, pressed && { opacity: 0.8 }]}>
                  <Text style={[s.onceChipTxt, once && { color: '#fff' }]}>🔥 view once</Text>
                </Pressable>
              ) : null}
            </View>
            <Pressable onPress={() => { setAtt(null); setOnce(false); }} hitSlop={8} style={({ pressed }) => [s.stageX, pressed && { opacity: 0.6 }]}>
              <Text style={{ color: C.soft, fontSize: 15, fontWeight: '700' }}>✕</Text>
            </Pressable>
          </View>
        )}
      </KeyboardAvoidingView>
    </Screen>
  );
}

const s = StyleSheet.create({
  head: {
    flexDirection: 'row', alignItems: 'center', gap: 4, paddingHorizontal: 8,
    paddingVertical: 8, borderBottomWidth: 1, borderBottomColor: C.line, backgroundColor: C.card,
  },
  iconBtn: { width: 38, height: 38, alignItems: 'center', justifyContent: 'center', borderRadius: 19 },
  back: { fontSize: 30, color: C.green, marginTop: -5 },
  who: { flex: 1, flexDirection: 'row', alignItems: 'center', gap: 10, paddingVertical: 2 },
  headTitle: { fontWeight: '700', color: C.ink, fontSize: 16 },
  presence: { color: C.soft, fontSize: 12, marginTop: 1 },
  onlineDot: {
    position: 'absolute', right: 0, bottom: 0, width: 11, height: 11, borderRadius: 6,
    backgroundColor: '#2e9e62', borderWidth: 2, borderColor: C.card,
  },
  menuDots: { fontSize: 22, color: C.green, fontWeight: '700' },
  dayWrap: { alignItems: 'center', marginVertical: 10 },
  day: {
    backgroundColor: '#eee9db', color: '#7a7461', fontSize: 11.5, fontWeight: '600',
    borderRadius: 99, paddingHorizontal: 12, paddingVertical: 4, overflow: 'hidden',
  },
  row: { flexDirection: 'row' },
  bubble: { maxWidth: '78%', borderRadius: 18, paddingHorizontal: 11, paddingTop: 8, paddingBottom: 6 },
  mine: { backgroundColor: C.green },
  theirs: { backgroundColor: C.card, borderWidth: 1, borderColor: '#e4e6db' },
  mineTail: { borderBottomRightRadius: 5 },
  theirsTail: { borderBottomLeftRadius: 5 },
  mineTxt: { color: '#fff', fontSize: 15.5, lineHeight: 21 },
  theirTxt: { color: C.ink, fontSize: 15.5, lineHeight: 21 },
  time: { fontSize: 10, marginTop: 2, alignSelf: 'flex-end', marginLeft: 30 },
  timeMine: { color: 'rgba(255,255,255,.65)' },
  timeTheirs: { color: '#9aa294' },
  attImg: { width: 210, height: 210, borderRadius: 12, marginBottom: 4, backgroundColor: C.line },
  empty: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: 8, paddingBottom: 60 },
  emptyTxt: { color: C.soft, fontSize: 15 },
  composer: {
    flexDirection: 'row', alignItems: 'flex-end', gap: 8, paddingHorizontal: 10,
    paddingVertical: 8, borderTopWidth: 1, borderTopColor: C.line, backgroundColor: C.card,
  },
  round: {
    width: 42, height: 42, borderRadius: 21, backgroundColor: C.green,
    alignItems: 'center', justifyContent: 'center',
  },
  roundGhost: { backgroundColor: '#eef0e8' },
  input: {
    flex: 1, backgroundColor: '#f5f4ec', borderWidth: 1, borderColor: '#e2e3d6',
    borderRadius: 21, paddingHorizontal: 15, paddingTop: 10, paddingBottom: 10,
    color: C.ink, fontSize: 15.5, maxHeight: 110,
  },
  recPill: {
    flex: 1, flexDirection: 'row', alignItems: 'center', gap: 8, height: 42,
    backgroundColor: '#f5f4ec', borderWidth: 1, borderColor: '#e2e3d6', borderRadius: 21, paddingHorizontal: 15,
  },
  recDot: { width: 10, height: 10, borderRadius: 5, backgroundColor: '#c03221' },
  recTime: { color: C.ink, fontWeight: '700', fontSize: 14, fontVariant: ['tabular-nums'] },
  recHint: { color: C.soft, fontSize: 13, flex: 1 },
  stageBar: {
    flexDirection: 'row', alignItems: 'center', gap: 10, marginHorizontal: 10, marginBottom: 8,
    backgroundColor: C.card, borderWidth: 1, borderColor: C.line, borderRadius: 14, padding: 8,
  },
  stageThumb: { width: 44, height: 44, borderRadius: 10, backgroundColor: C.line },
  stageThumbPh: { alignItems: 'center', justifyContent: 'center', backgroundColor: '#eef0e8' },
  stageName: { color: C.ink, fontSize: 13.5, fontWeight: '600' },
  onceChip: {
    alignSelf: 'flex-start', marginTop: 5, borderWidth: 1, borderColor: C.line,
    borderRadius: 99, paddingHorizontal: 10, paddingVertical: 3,
  },
  onceChipOn: { backgroundColor: C.accent, borderColor: C.accent },
  onceChipTxt: { fontSize: 11.5, color: C.ink, fontWeight: '600' },
  stageX: { width: 32, height: 32, borderRadius: 16, alignItems: 'center', justifyContent: 'center', backgroundColor: '#f0efe7' },
});

const b = StyleSheet.create({
  audio: { flexDirection: 'row', alignItems: 'center', gap: 8, paddingVertical: 2, marginBottom: 2, minWidth: 170 },
  audioBtn: { width: 32, height: 32, borderRadius: 16, alignItems: 'center', justifyContent: 'center' },
  audioBtnMine: { backgroundColor: 'rgba(0,0,0,.25)' },
  audioBtnTheirs: { backgroundColor: C.green },
  bars: { flex: 1, flexDirection: 'row', alignItems: 'center', gap: 2, height: 24 },
  bar: { width: 3, borderRadius: 2 },
  dur: { fontSize: 10.5, fontVariant: ['tabular-nums'] },
  video: { width: 210, height: 158, borderRadius: 12, marginBottom: 4, backgroundColor: '#0c0f0a' },
  fileRow: { flexDirection: 'row', alignItems: 'center', gap: 7, marginBottom: 2, maxWidth: 190 },
  fileName: { fontSize: 13.5, flex: 1 },
  onceBtn: {
    backgroundColor: C.accent, borderRadius: 99, paddingHorizontal: 14, paddingVertical: 9,
    marginBottom: 3, alignSelf: 'flex-start',
  },
  onceTxt: { color: '#fff', fontWeight: '700', fontSize: 13 },
  onceMine: { color: 'rgba(255,255,255,.85)', fontWeight: '600', fontSize: 13, marginBottom: 3 },
  onceOpened: { color: '#9aa294', fontSize: 13, marginBottom: 3 },
});
