import React, { useEffect, useState } from 'react';
import { Pressable, ScrollView, Text } from 'react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { api } from '../../lib/api';
import { useAuth } from '../../lib/store';
import { Btn, Err, Field, Input, Screen, Select, Sub, Title } from '../../components/ui';
import LocationPicker from '../../components/LocationPicker';
import { C } from '../../lib/theme';

export default function GroupEdit() {
  const { id } = useLocalSearchParams<{ id?: string }>();
  const { meta } = useAuth();
  const editing = !!id;
  const [err, setErr] = useState('');
  const [busy, setBusy] = useState(false);
  const [f, setF] = useState({ name: '', category: '', description: '', join_policy: 'open', county: '', area: '', estate: '' });
  const set = (k: string, v: any) => setF((p) => ({ ...p, [k]: v }));

  useEffect(() => {
    if (!editing) return;
    api('group', { id }).then((d) => {
      const g = d.group;
      setF({ name: g.name, category: g.category, description: g.desc, join_policy: g.joinPolicy,
             county: g.county ?? '', area: g.area ?? '', estate: g.estate ?? '' });
    }).catch((e) => setErr(e.message));
  }, [id, editing]);

  const save = async () => {
    setErr(''); setBusy(true);
    try {
      const body: any = {
        name: f.name, category: f.category, description: f.description,
        join_policy: f.join_policy, county: f.county, area: f.area,
        estate: f.estate === '__new' ? '__new' : f.estate, estate_new: (f as any).estate_new,
      };
      if (editing) body.id = Number(id);
      const d = await api('group-save', {}, body);
      router.replace(`/group/${d.id}`);
    } catch (e: any) { setErr(e.message); }
    setBusy(false);
  };

  return (
    <Screen pad={false}>
      <ScrollView contentContainerStyle={{ padding: 16 }} keyboardShouldPersistTaps="handled">
        <Pressable onPress={() => router.back()}><Text style={{ color: C.green, fontWeight: '600', marginBottom: 10 }}>‹ Back</Text></Pressable>
        <Title>{editing ? 'Edit group' : 'Start a group'}</Title>
        <Sub>Regulars, teammates, neighbours — give them a home.</Sub>
        <Err msg={err} />
        <Field label="Group name">
          <Input value={f.name} onChangeText={(v) => set('name', v)} maxLength={80} />
        </Field>
        <Field label="Interest">
          <Select value={f.category} options={meta?.categories ?? []} onChange={(v) => set('category', v)} />
        </Field>
        <Field label="Where">
          <LocationPicker county={f.county} area={f.area} estate={f.estate}
            onChange={(p) => setF((prev) => ({ ...prev, ...p }))} />
        </Field>
        <Field label="Join policy">
          <Select value={f.join_policy}
            options={[{ value: 'open', label: 'Open — anyone joins instantly' }, { value: 'approval', label: 'Approval — admins review requests' }]}
            onChange={(v) => set('join_policy', v)} />
        </Field>
        <Field label="About">
          <Input value={f.description} onChangeText={(v) => set('description', v)} multiline maxLength={600}
            placeholder="What's this group about?" />
        </Field>
        <Btn label={busy ? 'Saving…' : editing ? 'Save changes' : 'Create group'} onPress={save} disabled={busy} />
      </ScrollView>
    </Screen>
  );
}
