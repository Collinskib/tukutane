import React, { useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View, KeyboardAvoidingView, Platform } from 'react-native';
import { router } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import * as Google from 'expo-auth-session/providers/google';
import Constants from 'expo-constants';
import { api } from '../lib/api';
import { useAuth } from '../lib/store';
import { Btn, Err, Field, Input, Select } from '../components/ui';
import LocationPicker from '../components/LocationPicker';
import { C, serif } from '../lib/theme';

WebBrowser.maybeCompleteAuthSession();

const GOOGLE_CID: string = (Constants.expoConfig?.extra as any)?.googleWebClientId ?? '';

export default function Auth() {
  const { applyAuth, meta } = useAuth();
  const [mode, setMode] = useState<'login' | 'register'>('login');
  const [err, setErr] = useState('');
  const [busy, setBusy] = useState(false);
  const [f, setF] = useState({ email: '', password: '', name: '', gender: '', age_bracket: '' });
  const [loc, setLoc] = useState({ county: '', area: '', estate: '' });
  const set = (k: string, v: string) => setF((p) => ({ ...p, [k]: v }));

  const [, gResponse, gPrompt] = Google.useIdTokenAuthRequest(
    GOOGLE_CID ? { clientId: GOOGLE_CID } : { clientId: 'disabled.apps.googleusercontent.com' },
  );

  React.useEffect(() => {
    (async () => {
      if (gResponse?.type === 'success' && gResponse.params.id_token) {
        setBusy(true);
        try {
          const d = await api('auth/google', {}, { id_token: gResponse.params.id_token });
          await applyAuth(d);
        } catch (e: any) {
          setErr(e.message);
        }
        setBusy(false);
      }
    })();
  }, [gResponse, applyAuth]);

  const submit = async () => {
    setErr(''); setBusy(true);
    try {
      if (mode === 'login') {
        const d = await api('auth/login', {}, { email: f.email.trim(), password: f.password });
        await applyAuth(d);
      } else {
        const d = await api('auth/register', {}, {
          email: f.email.trim(), password: f.password, name: f.name.trim(),
          gender: f.gender, age_bracket: f.age_bracket,
          county: loc.county, area: loc.area,
          estate: loc.estate === '__new' ? '__new' : loc.estate,
          estate_new: (loc as any).estate_new,
        });
        await applyAuth(d);
      }
    } catch (e: any) {
      setErr(e.message);
    }
    setBusy(false);
  };

  const googleCidConfigured = !!GOOGLE_CID && !GOOGLE_CID.startsWith('disabled');

  return (
    <KeyboardAvoidingView style={{ flex: 1, backgroundColor: C.cream }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView contentContainerStyle={s.wrap} keyboardShouldPersistTaps="handled">
        <Text style={s.brand}>Nipange<Text style={{ color: C.accent }}>.</Text></Text>
        <Text style={s.tagline}>Find your people.</Text>

        <View style={s.tabs}>
          <Pressable onPress={() => setMode('login')} style={[s.tab, mode === 'login' && s.tabOn]}>
            <Text style={[s.tabTxt, mode === 'login' && { color: '#fff' }]}>Log in</Text>
          </Pressable>
          <Pressable onPress={() => setMode('register')} style={[s.tab, mode === 'register' && s.tabOn]}>
            <Text style={[s.tabTxt, mode === 'register' && { color: '#fff' }]}>Join Nipange</Text>
          </Pressable>
        </View>

        <Err msg={err} />

        {mode === 'register' && (
          <Field label="Your name">
            <Input value={f.name} onChangeText={(v) => set('name', v)} autoComplete="name" />
          </Field>
        )}
        <Field label="Email">
          <Input value={f.email} onChangeText={(v) => set('email', v)} keyboardType="email-address" autoCapitalize="none" autoComplete="email" />
        </Field>
        <Field label={mode === 'register' ? 'Password (8+ characters)' : 'Password'}>
          <Input value={f.password} onChangeText={(v) => set('password', v)} secureTextEntry />
        </Field>

        {mode === 'register' && (
          <>
            <View style={{ flexDirection: 'row', gap: 10 }}>
              <View style={{ flex: 1 }}>
                <Select label="Gender" value={f.gender} options={meta?.genders ?? []} onChange={(v) => set('gender', v)} />
              </View>
              <View style={{ flex: 1 }}>
                <Select label="Age bracket" value={f.age_bracket} options={meta?.ages ?? []} onChange={(v) => set('age_bracket', v)} />
              </View>
            </View>
            <View style={{ marginTop: 14 }}>
              <Field label="Where do you live? (optional)">
                <LocationPicker
                  county={loc.county} area={loc.area} estate={loc.estate} optional
                  onChange={(p) => setLoc(p as any)} />
              </Field>
            </View>
          </>
        )}

        <Btn label={busy ? 'Please wait…' : mode === 'login' ? 'Log in' : 'Create account'} onPress={submit} disabled={busy} />

        {mode === 'login' && (
          <Pressable onPress={() => router.push('/forgot')} style={{ marginTop: 12 }}>
            <Text style={{ color: C.green, textAlign: 'center' }}>Forgot your password?</Text>
          </Pressable>
        )}

        {googleCidConfigured && (
          <Btn label="Continue with Google" ghost onPress={() => gPrompt()} disabled={busy} />
        )}
        {!googleCidConfigured && __DEV__ ? null : null}
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const s = StyleSheet.create({
  wrap: { padding: 22, paddingTop: 60, maxWidth: 480, width: '100%', alignSelf: 'center' },
  brand: { fontFamily: serif, fontSize: 40, color: C.deep, textAlign: 'center' },
  tagline: { color: C.soft, textAlign: 'center', marginBottom: 26, fontSize: 15 },
  tabs: { flexDirection: 'row', gap: 8, marginBottom: 18 },
  tab: {
    flex: 1, borderRadius: 99, paddingVertical: 10, alignItems: 'center',
    borderWidth: 1, borderColor: '#d8ddd3', backgroundColor: C.card,
  },
  tabOn: { backgroundColor: C.green, borderColor: C.green },
  tabTxt: { color: C.ink, fontWeight: '600' },
});
