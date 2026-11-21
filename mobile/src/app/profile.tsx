import React, { useState } from 'react';
import { Alert, Image, Pressable, ScrollView, StyleSheet, Switch, Text, View } from 'react-native';
import { router } from 'expo-router';
import * as ImagePicker from 'expo-image-picker';
import { api } from '../lib/api';
import { useAuth } from '../lib/store';
import { Avatar, Btn, Card, Err, Field, Input, Screen, Select } from '../components/ui';
import LocationPicker from '../components/LocationPicker';
import { C, serifBlack } from '../lib/theme';

export default function Profile() {
  const { user, meta, refreshUser, logout } = useAuth();
  const [edit, setEdit] = useState(false);
  const [pw, setPw] = useState(false);
  const [err, setErr] = useState('');
  const [busy, setBusy] = useState(false);
  const [photo, setPhoto] = useState<{ uri: string; b64?: string } | null>(null);
  const [f, setF] = useState(() => ({
    name: user?.name ?? '', bio: user?.bio ?? '', county: user?.county ?? '',
    area: user?.area ?? '', estate: user?.estate ?? '',
    gender: (user as any)?.genderFull ?? '', age_bracket: user?.age ?? '', fun_fact: user?.funFact ?? '',
    interests: (user?.interests ?? '').split(',').filter(Boolean) as string[],
    discoverable: user?.discoverable ?? true,
  }));
  const [pwF, setPwF] = useState({ current: '', new: '' });

  if (!user) return <Screen bottom />;

  const pick = async () => {
    const r = await ImagePicker.launchImageLibraryAsync({ mediaTypes: ['images'], quality: 0.85, base64: true });
    if (!r.canceled && r.assets[0]) setPhoto({ uri: r.assets[0].uri, b64: r.assets[0].base64 ?? undefined });
  };

  const save = async () => {
    setErr(''); setBusy(true);
    try {
      const body: any = { ...f };
      if (f.estate === '__new') { body.estate = '__new'; body.estate_new = (f as any).estate_new; }
      if (photo?.b64) body.photo_b64 = photo.b64;
      await api('profile', {}, body);
      await refreshUser();
      setEdit(false);
    } catch (e: any) { setErr(e.message); }
    setBusy(false);
  };

  const changePw = async () => {
    setErr(''); setBusy(true);
    try {
      await api('password', {}, { current: pwF.current, new: pwF.new });
      Alert.alert('Done', 'Password changed.');
      setPw(false); setPwF({ current: '', new: '' });
    } catch (e: any) { setErr(e.message); }
    setBusy(false);
  };

  const resendVerify = async () => {
    // site's verify.php resend is session-based; hit it via API? no — show info
    Alert.alert('Check your inbox', 'Open nipange.co.ke/verify.php in a browser while logged in to resend the link, or use the link we already emailed you.');
  };

  return (
    <Screen pad={false} bottom>
      <ScrollView contentContainerStyle={{ padding: 16 }} keyboardShouldPersistTaps="handled">
        <Pressable onPress={() => router.back()}><Text style={s.back}>‹ Back</Text></Pressable>

        <View style={s.head}>
          <Avatar photo={photo?.uri ? undefined : user.photo} name={user.name} size={84} />
          {photo && <Image source={{ uri: photo.uri }} style={s.headImg} />}
          <View style={{ marginLeft: 16, flex: 1 }}>
            <Text style={s.name}>{user.name}</Text>
            <Text style={s.meta}>{user.email}{user.verified ? ' ✓' : ''}</Text>
            <Text style={s.meta}>{user.place || 'Kenya'}</Text>
          </View>
        </View>
        {!user.verified && (
          <Pressable onPress={resendVerify} style={s.verifyBar}>
            <Text style={{ color: C.warn, fontSize: 13 }}>✉️ Verify your email — tap for options</Text>
          </Pressable>
        )}

        {!edit && !pw && (
          <>
            <Card style={{ marginTop: 16 }}>
              {!!user.bio && <Text style={s.bio}>{user.bio}</Text>}
              {!!user.funFact && (
                <View style={{ flexDirection: 'row', gap: 8, marginBottom: 6 }}>
                  <Text style={{ color: C.accent, fontFamily: serifBlack, fontSize: 22, lineHeight: 20 }}>❝</Text>
                  <Text style={{ flex: 1, fontFamily: serifBlack, fontStyle: 'italic', fontSize: 15, lineHeight: 21, color: C.soft }}>{user.funFact}</Text>
                </View>
              )}
              <Text style={s.meta}>{[user.age, (user as any).genderFull].filter(Boolean).join(' · ')}</Text>
              {!!user.interests && <Text style={s.meta}>Into: {user.interests.split(',').join(', ')}</Text>}
            </Card>
            <Btn label="Edit profile" onPress={() => setEdit(true)} />
            <Card style={{ marginTop: 16 }}>
              <MenuRow label="Friends" onPress={() => router.push('/friends')} />
              <MenuRow label="Groups" onPress={() => router.push('/groups')} />
              <MenuRow label="Business" onPress={() => router.push('/venues')} />
              <MenuRow label="Notifications" onPress={() => router.push('/notifications')} />
              <MenuRow label="Change password" onPress={() => setPw(true)} />
              <MenuRow label="Log out" warn onPress={() =>
                Alert.alert('Log out?', '', [
                  { text: 'Stay', style: 'cancel' },
                  { text: 'Log out', style: 'destructive', onPress: async () => { await logout(); router.replace('/auth'); } },
                ])} />
            </Card>
          </>
        )}

        {edit && (
          <>
            <Err msg={err} />
            <Field label="Photo">
              <Btn label={photo ? 'Change photo' : 'Pick a photo'} ghost small onPress={pick} />
            </Field>
            <Field label="Name">
              <Input value={f.name} onChangeText={(v) => setF((p) => ({ ...p, name: v }))} maxLength={60} />
            </Field>
            <Field label="Bio">
              <Input value={f.bio} onChangeText={(v) => setF((p) => ({ ...p, bio: v }))} multiline maxLength={300} />
            </Field>
            <Field label="My fun fact or silly joke">
              <Input value={f.fun_fact} onChangeText={(v) => setF((p) => ({ ...p, fun_fact: v }))} maxLength={160}
                placeholder="The thing people should know about you" />
            </Field>
            <View style={{ flexDirection: 'row', gap: 10 }}>
              <View style={{ flex: 1 }}>
                <Field label="Gender">
                  <Select value={f.gender} options={meta?.genders ?? []} onChange={(v) => setF((p) => ({ ...p, gender: v }))} />
                </Field>
              </View>
              <View style={{ flex: 1 }}>
                <Field label="Age bracket">
                  <Select value={f.age_bracket} options={meta?.ages ?? []} onChange={(v) => setF((p) => ({ ...p, age_bracket: v }))} />
                </Field>
              </View>
            </View>
            <Field label="Where do you live?">
              <LocationPicker county={f.county} area={f.area} estate={f.estate} optional
                onChange={(p) => setF((prev) => ({ ...prev, ...p }))} />
            </Field>
            <Field label="Interests">
              <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 6 }}>
                {(meta?.categories ?? []).map((c) => {
                  const on = f.interests.includes(c);
                  return (
                    <Pressable key={c} style={[s.chip, on && { backgroundColor: C.green, borderColor: C.green }]}
                      onPress={() => setF((p) => ({
                        ...p, interests: on ? p.interests.filter((x) => x !== c) : [...p.interests, c],
                      }))}>
                      <Text style={{ color: on ? '#fff' : C.ink, fontSize: 13 }}>{c}</Text>
                    </Pressable>
                  );
                })}
              </View>
            </Field>
            <View style={s.switchRow}>
              <Switch value={f.discoverable} onValueChange={(v) => setF((p) => ({ ...p, discoverable: v }))} trackColor={{ true: C.green }} />
              <Text style={{ flex: 1, color: C.ink }}>Show me in People nearby</Text>
            </View>
            <View style={{ flexDirection: 'row', gap: 8 }}>
              <View style={{ flex: 1 }}><Btn label={busy ? 'Saving…' : 'Save'} onPress={save} disabled={busy} /></View>
              <Btn label="Cancel" ghost onPress={() => setEdit(false)} />
            </View>
          </>
        )}

        {pw && (
          <>
            <Err msg={err} />
            <Field label="Current password">
              <Input value={pwF.current} onChangeText={(v) => setPwF((p) => ({ ...p, current: v }))} secureTextEntry />
            </Field>
            <Field label="New password (8+ characters)">
              <Input value={pwF.new} onChangeText={(v) => setPwF((p) => ({ ...p, new: v }))} secureTextEntry />
            </Field>
            <View style={{ flexDirection: 'row', gap: 8 }}>
              <View style={{ flex: 1 }}><Btn label={busy ? 'Saving…' : 'Change password'} onPress={changePw} disabled={busy} /></View>
              <Btn label="Cancel" ghost onPress={() => setPw(false)} />
            </View>
          </>
        )}
      </ScrollView>
    </Screen>
  );
}

const s = StyleSheet.create({
  back: { color: C.green, fontWeight: '600', marginBottom: 10 },
  head: { flexDirection: 'row', alignItems: 'center' },
  headImg: { position: 'absolute', width: 84, height: 84, borderRadius: 42 },
  name: { fontFamily: serifBlack, fontSize: 26, color: C.ink },
  meta: { color: C.soft, marginTop: 2, fontSize: 14 },
  bio: { color: C.ink, lineHeight: 21, marginBottom: 6 },
  verifyBar: {
    backgroundColor: '#fdf3e4', borderRadius: 10, padding: 10, marginTop: 10,
  },
  menuRow: {
    flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center',
    paddingVertical: 12, borderBottomWidth: 1, borderBottomColor: '#eef1ea',
  },
  chip: {
    borderWidth: 1, borderColor: '#d8ddd3', borderRadius: 99,
    paddingVertical: 6, paddingHorizontal: 12, backgroundColor: C.card,
  },
  switchRow: { flexDirection: 'row', gap: 10, alignItems: 'center', marginBottom: 16 },
});


function MenuRow({ label, onPress, warn }: { label: string; onPress: () => void; warn?: boolean }) {
  return (
    <Pressable style={s.menuRow} onPress={onPress}>
      <Text style={{ color: warn ? C.warn : C.ink, fontWeight: '600' }}>{label}</Text>
      <Text style={{ color: C.soft }}>›</Text>
    </Pressable>
  );
}
