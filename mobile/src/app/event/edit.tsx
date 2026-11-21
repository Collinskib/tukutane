import React, { useEffect, useState } from 'react';
import { Alert, Image, Platform, Pressable, ScrollView, StyleSheet, Switch, Text, View } from 'react-native';
import { router, useLocalSearchParams } from 'expo-router';
import DateTimePicker from '@react-native-community/datetimepicker';
import * as ImagePicker from 'expo-image-picker';
import { api, imgUrl } from '../../lib/api';
import { useAuth } from '../../lib/store';
import { Btn, Err, Field, Input, Screen, Select, Sub, Title } from '../../components/ui';
import LocationPicker from '../../components/LocationPicker';
import { C } from '../../lib/theme';

const fmt = (d: Date) =>
  `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;

export default function EventEdit() {
  const { id } = useLocalSearchParams<{ id?: string }>();
  const { meta } = useAuth();
  const editing = !!id;
  const [err, setErr] = useState('');
  const [busy, setBusy] = useState(false);
  const [showPicker, setShowPicker] = useState(false);
  const [dt, setDt] = useState(() => new Date(Date.now() + 86400000));
  const [cover, setCover] = useState<{ uri: string; b64?: string } | null>(null);
  const [f, setF] = useState({
    title: '', category: '', venue: '', description: '', cost: '0', capacity: '',
    audience: 'public', invite_only: false, repeat: '', county: '', area: '', estate: '',
  });
  const set = (k: string, v: any) => setF((p) => ({ ...p, [k]: v }));

  useEffect(() => {
    if (!editing) return;
    api('event', { id }).then((d) => {
      const e = d.event;
      setF({
        title: e.title, category: e.category, venue: e.venue, description: e.desc,
        cost: String(e.cost), capacity: e.capacity === null ? '' : String(e.capacity),
        audience: e.audience, invite_only: e.inviteOnly, repeat: '',
        county: e.county, area: e.area, estate: e.estate,
      });
      setDt(new Date(e.at.replace(' ', 'T')));
      if (e.cover) setCover({ uri: imgUrl(e.cover)! });
    }).catch((e) => setErr(e.message));
  }, [id, editing]);

  const pickCover = async () => {
    const r = await ImagePicker.launchImageLibraryAsync({ mediaTypes: ['images'], quality: 0.85, base64: true });
    if (!r.canceled && r.assets[0]) {
      const a = r.assets[0];
      setCover({ uri: a.uri, b64: a.base64 ?? undefined });
    }
  };

  const save = async () => {
    setErr(''); setBusy(true);
    try {
      const body: any = {
        title: f.title, category: f.category, county: f.county, area: f.area,
        estate: f.estate === '__new' ? '__new' : f.estate,
        estate_new: (f as any).estate_new,
        venue: f.venue, date: fmt(dt), cost: Number(f.cost) || 0,
        capacity: f.capacity, audience: f.audience, invite_only: f.invite_only,
        description: f.description,
      };
      if (!editing) body.repeat = f.repeat;
      if (cover?.b64) body.cover_b64 = cover.b64;
      if (editing) {
        body.id = Number(id);
        const d = await api('event', { id });
        if (d.event.seriesId) {
          body.scope = await new Promise<string>((resolve) =>
            Alert.alert('Apply changes', 'This is a repeating event.', [
              { text: 'This date only', onPress: () => resolve('one') },
              { text: 'Whole series', onPress: () => resolve('series') },
            ]));
        }
      }
      const d = await api('event-save', {}, body);
      router.replace(`/event/${d.id}`);
    } catch (e: any) {
      setErr(e.message);
    }
    setBusy(false);
  };

  const venueOpts = [{ value: '', label: 'Pick a listed venue…' },
    ...(meta?.venues ?? []).map((v) => ({ value: v.name, label: `${v.name} · ${v.place}` }))];

  return (
    <Screen pad={false} bottom>
      <ScrollView contentContainerStyle={{ padding: 16 }} keyboardShouldPersistTaps="handled">
        <Pressable onPress={() => router.back()}><Text style={{ color: C.green, fontWeight: '600', marginBottom: 10 }}>‹ Back</Text></Pressable>
        <Title>{editing ? 'Edit event' : 'Create a plan'}</Title>
        <Sub>{editing ? 'Update the details — attendees see changes.' : 'Post it, share it, fill the room.'}</Sub>
        <Err msg={err} />

        <Field label="Event name">
          <Input value={f.title} onChangeText={(v) => set('title', v)} maxLength={80} />
        </Field>
        <Field label="Interest">
          <Select value={f.category} options={meta?.categories ?? []} onChange={(v) => set('category', v)} />
        </Field>
        <Field label="Where">
          <LocationPicker county={f.county} area={f.area} estate={f.estate}
            onChange={(p) => setF((prev) => ({ ...prev, ...p }))} />
        </Field>
        <Field label="Venue">
          <Input value={f.venue} onChangeText={(v) => set('venue', v)} placeholder="e.g. rooftop lounge, sports café" maxLength={80} />
          {f.venue === '' && (
            <View style={{ marginTop: 8 }}>
              <Select value="" options={venueOpts} onChange={(v) => set('venue', v)} />
            </View>
          )}
          <Text style={s.hint} onPress={() => router.push('/venue/edit')}>Or add a venue →</Text>
        </Field>

        <Field label="Date and time (Kenya)">
          <Pressable onPress={() => setShowPicker(true)}>
            <View pointerEvents="none"><Input value={fmt(dt)} editable={false} /></View>
          </Pressable>
        </Field>
        {showPicker && (
          <DateTimePicker
            value={dt} mode="datetime" display={Platform.OS === 'ios' ? 'spinner' : 'default'}
            minimumDate={new Date()}
            onChange={(_, d) => { setShowPicker(Platform.OS === 'ios'); if (d) setDt(d); }}
          />
        )}

        <View style={{ flexDirection: 'row', gap: 10 }}>
          <View style={{ flex: 1 }}>
            <Field label="Cost in KSh (0 = free)">
              <Input value={f.cost} onChangeText={(v) => set('cost', v)} keyboardType="numeric" />
            </Field>
          </View>
          <View style={{ flex: 1 }}>
            <Field label="Capacity (blank = unlimited)">
              <Input value={f.capacity} onChangeText={(v) => set('capacity', v)} keyboardType="numeric" placeholder="e.g. 20" />
            </Field>
          </View>
        </View>

        <Field label="Who can see it">
          <Select value={f.audience}
            options={[{ value: 'public', label: 'Discover feed' }, { value: 'friends', label: 'My friends only' }]}
            onChange={(v) => set('audience', v)} />
        </Field>

        <View style={s.switchRow}>
          <Switch value={f.invite_only} onValueChange={(v) => set('invite_only', v)} trackColor={{ true: C.green }} />
          <Text style={{ flex: 1, color: C.ink }}>Invite-only — people join by invitation, not the public feed</Text>
        </View>

        {!editing && (
          <Field label="Repeat">
            <Select value={f.repeat}
              options={[{ value: '', label: 'One-time event' }, { value: 'weekly', label: 'Weekly — next 8 dates' }, { value: 'monthly', label: 'Monthly — next 4 dates' }]}
              onChange={(v) => set('repeat', v)} />
          </Field>
        )}

        <Field label="Details">
          <Input value={f.description} onChangeText={(v) => set('description', v)} multiline maxLength={300}
            placeholder="What should people expect?" />
        </Field>

        <Field label="Cover image (optional)">
          {cover?.uri ? <Image source={{ uri: cover.uri }} style={s.cover} /> : null}
          <Btn label={cover ? 'Change image' : 'Pick an image'} ghost small onPress={pickCover} />
        </Field>

        <Btn label={busy ? 'Publishing…' : editing ? 'Save changes' : 'Publish plan'} onPress={save} disabled={busy} />
      </ScrollView>
    </Screen>
  );
}

const s = StyleSheet.create({
  hint: { color: C.green, marginTop: 8 },
  switchRow: { flexDirection: 'row', gap: 10, alignItems: 'center', marginBottom: 14 },
  cover: { width: '100%', height: 150, borderRadius: 12, backgroundColor: C.line, marginBottom: 8 },
});
