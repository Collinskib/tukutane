import React, { useState } from 'react';
import { ScrollView } from 'react-native';
import { api } from '../lib/api';
import { useAuth } from '../lib/store';
import { Btn, Err, Field, Input, Screen, Select, Sub, Title } from '../components/ui';
import LocationPicker from '../components/LocationPicker';

export default function CompleteProfile() {
  const { user, meta, refreshUser, join } = useAuth();
  const [gender, setGender] = useState(user?.gender === 'Prefer not to say' ? '' : (user as any)?.genderFull ?? '');
  const [age, setAge] = useState(user?.age ?? '');
  const [loc, setLoc] = useState({ county: user?.county ?? '', area: user?.area ?? '', estate: user?.estate ?? '' });
  const [funFact, setFunFact] = useState((user as any)?.funFact ?? '');
  const [err, setErr] = useState('');
  const [busy, setBusy] = useState(false);

  const save = async () => {
    setErr(''); setBusy(true);
    try {
      await api('complete-profile', {}, {
        gender, age_bracket: age,
        county: loc.county, area: loc.area, fun_fact: funFact.trim(),
        estate: loc.estate, estate_new: (loc as any).estate_new,
      });
      await refreshUser();
      join();
    } catch (e: any) {
      setErr(e.message);
    }
    setBusy(false);
  };

  return (
    <Screen bottom>
      <ScrollView keyboardShouldPersistTaps="handled">
        <Title>One more step</Title>
        <Sub>Just the same details we ask everyone at signup — it takes ten seconds.</Sub>
        <Err msg={err} />
        <Field label="Gender">
          <Select value={gender} options={meta?.genders ?? []} onChange={setGender} />
        </Field>
        <Field label="Age bracket">
          <Select value={age} options={meta?.ages ?? []} onChange={setAge} />
        </Field>
        <Field label="My fun fact or silly joke (optional)">
          <Input value={funFact} onChangeText={setFunFact} maxLength={160}
            placeholder="The thing people should know about you" />
        </Field>
        <Field label="Where do you live? (optional)">
          <LocationPicker county={loc.county} area={loc.area} estate={loc.estate} optional
            onChange={(p) => setLoc(p as any)} />
        </Field>
        <Btn label={busy ? 'Saving…' : 'Save and continue'} onPress={save} disabled={busy} />
      </ScrollView>
    </Screen>
  );
}
