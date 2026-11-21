import React, { useEffect, useState } from 'react';
import { Image, Pressable, ScrollView, StyleSheet, Text } from 'react-native';
import { router, useLocalSearchParams } from 'expo-router';
import * as ImagePicker from 'expo-image-picker';
import { api, imgUrl } from '../../lib/api';
import { Btn, Err, Field, Input, Screen, Sub, Title } from '../../components/ui';
import LocationPicker from '../../components/LocationPicker';
import { C } from '../../lib/theme';

export default function VenueEdit() {
  const { id } = useLocalSearchParams<{ id?: string }>();
  const editing = !!id;
  const [err, setErr] = useState('');
  const [busy, setBusy] = useState(false);
  const [photo, setPhoto] = useState<{ uri: string; b64?: string } | null>(null);
  const [f, setF] = useState({ name: '', description: '', county: '', area: '', estate: '' });

  useEffect(() => {
    if (!editing) return;
    api('venue', { id }).then((d) => {
      const v = d.venue;
      setF({ name: v.name, description: v.desc, county: v.county ?? '', area: v.area ?? '', estate: v.estate ?? '' });
      if (v.photo) setPhoto({ uri: imgUrl(v.photo)! });
    }).catch((e) => setErr(e.message));
  }, [id, editing]);

  const pick = async () => {
    const r = await ImagePicker.launchImageLibraryAsync({ mediaTypes: ['images'], quality: 0.85, base64: true });
    if (!r.canceled && r.assets[0]) setPhoto({ uri: r.assets[0].uri, b64: r.assets[0].base64 ?? undefined });
  };

  const save = async () => {
    setErr(''); setBusy(true);
    try {
      const body: any = {
        name: f.name, description: f.description, county: f.county, area: f.area,
        estate: f.estate === '__new' ? '__new' : f.estate, estate_new: (f as any).estate_new,
      };
      if (photo?.b64) body.photo_b64 = photo.b64;
      if (editing) body.id = Number(id);
      const d = await api('venue-save', {}, body);
      router.replace(`/venue/${d.id}`);
    } catch (e: any) { setErr(e.message); }
    setBusy(false);
  };

  return (
    <Screen pad={false} bottom>
      <ScrollView contentContainerStyle={{ padding: 16 }} keyboardShouldPersistTaps="handled">
        <Pressable onPress={() => router.back()}><Text style={{ color: C.green, fontWeight: '600', marginBottom: 10 }}>‹ Back</Text></Pressable>
        <Title>{editing ? 'Edit venue' : 'Add a venue'}</Title>
        <Sub>A spot people can pick when they create a plan.</Sub>
        <Err msg={err} />
        <Field label="Venue name">
          <Input value={f.name} onChangeText={(v) => setF((p) => ({ ...p, name: v }))} maxLength={80} />
        </Field>
        <Field label="Where">
          <LocationPicker county={f.county} area={f.area} estate={f.estate}
            onChange={(p) => setF((prev) => ({ ...prev, ...p }))} />
        </Field>
        <Field label="About">
          <Input value={f.description} onChangeText={(v) => setF((p) => ({ ...p, description: v }))}
            multiline maxLength={600} placeholder="What makes it good for meetups?" />
        </Field>
        <Field label="Photo (optional)">
          {photo?.uri ? <Image source={{ uri: photo.uri }} style={s.photo} /> : null}
          <Btn label={photo ? 'Change photo' : 'Pick a photo'} ghost small onPress={pick} />
        </Field>
        <Btn label={busy ? 'Saving…' : editing ? 'Save changes' : 'Add venue'} onPress={save} disabled={busy} />
      </ScrollView>
    </Screen>
  );
}

const s = StyleSheet.create({
  photo: { width: '100%', height: 150, borderRadius: 12, backgroundColor: C.line, marginBottom: 8 },
});
