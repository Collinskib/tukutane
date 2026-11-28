import React, { useEffect, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View, KeyboardAvoidingView, Platform } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { router } from 'expo-router';
import Constants from 'expo-constants';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { api } from '../lib/api';
import { useAuth } from '../lib/store';
import { Btn, Err, Field, Input, Select } from '../components/ui';
import LocationPicker from '../components/LocationPicker';
import { C, serif } from '../lib/theme';

// nitro-google-signin is a third-party native module absent from Expo Go —
// load it lazily so dev sessions in Expo Go don't crash on import.
type GSign = typeof import('react-native-nitro-google-signin');
let GSignin: GSign | null = null;
try {
  // eslint-disable-next-line @typescript-eslint/no-require-imports
  GSignin = require('react-native-nitro-google-signin') as GSign;
} catch { /* Expo Go: Google button hides below */ }

const G = (Constants.expoConfig?.extra as any) ?? {};
const GOOGLE_OK: boolean = !!G.googleWebClientId && !!GSignin;

if (GOOGLE_OK) {
  GSignin!.GoogleOneTapSignIn.configure({
    webClientId: G.googleWebClientId,
    iosClientId: G.googleIosClientId,
  });
}

export default function Auth() {
  const { applyAuth, meta, join } = useAuth();
  const insets = useSafeAreaInsets();
  const [mode, setMode] = useState<'login' | 'register'>('login');
  const [err, setErr] = useState('');
  const [busy, setBusy] = useState(false);
  const [f, setF] = useState({ email: '', password: '', name: '', gender: '', age_bracket: '', fun_fact: '' });
  const [loc, setLoc] = useState({ county: '', area: '', estate: '' });
  const [showPw, setShowPw] = useState(false);
  const [remember, setRemember] = useState(true);
  const set = (k: string, v: string) => setF((p) => ({ ...p, [k]: v }));

  // Pre-fill a remembered email (sessions persist via SecureStore token;
  // "Remember me" keeps your email ready so login is one tap).
  useEffect(() => {
    AsyncStorage.getItem('nipange.remember.email')
      .then((e) => { if (e) setF((p) => ({ ...p, email: e })); })
      .catch(() => {});
  }, []);

  const googleSignIn = async () => {
    setErr(''); setBusy(true);
    try {
      const gs = GSignin!;
      await gs.GoogleOneTapSignIn.checkPlayServices();
      let res = await gs.GoogleOneTapSignIn.signIn();
      if (gs.isNoSavedCredentialFoundResponse(res)) res = await gs.GoogleOneTapSignIn.createAccount();
      if (gs.isNoSavedCredentialFoundResponse(res)) res = await gs.GoogleOneTapSignIn.presentExplicitSignIn();
      if (gs.isSuccessResponse(res) && res.data.idToken) {
        const d = await api('auth/google', {}, { id_token: res.data.idToken });
        await applyAuth(d);
      } else if (!gs.isCancelledResponse(res)) {
        setErr('Google sign-in returned no token.');
      }
    } catch (e: any) {
      if (!GSignin?.isCancelledResponse(e)) setErr(e?.message ?? 'Google sign-in failed.');
    }
    setBusy(false);
  };

  const submit = async () => {
    setErr(''); setBusy(true);
    try {
      if (mode === 'login') {
        const d = await api('auth/login', {}, { email: f.email.trim(), password: f.password });
        if (remember) AsyncStorage.setItem('nipange.remember.email', f.email.trim()).catch(() => {});
        else AsyncStorage.removeItem('nipange.remember.email').catch(() => {});
        await applyAuth(d);
      } else {
        const d = await api('auth/register', {}, {
          email: f.email.trim(), password: f.password, name: f.name.trim(),
          gender: f.gender, age_bracket: f.age_bracket, fun_fact: f.fun_fact.trim(),
          county: loc.county, area: loc.area,
          estate: loc.estate === '__new' ? '__new' : loc.estate,
          estate_new: (loc as any).estate_new,
        });
        await applyAuth(d);
        join();
      }
    } catch (e: any) {
      setErr(e.message);
    }
    setBusy(false);
  };

  const googleCidConfigured = GOOGLE_OK;

  return (
    <KeyboardAvoidingView style={{ flex: 1, backgroundColor: C.cream }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView
        contentContainerStyle={[s.wrap, { paddingTop: insets.top + 30, paddingBottom: insets.bottom + 30 }]}
        keyboardShouldPersistTaps="handled">
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
          <View>
            <Input
              value={f.password} onChangeText={(v) => set('password', v)}
              secureTextEntry={!showPw} style={{ paddingRight: 56 }} />
            <Pressable style={s.eye} onPress={() => setShowPw((v) => !v)} hitSlop={8}>
              <Text style={{ color: C.green, fontWeight: '600' }}>{showPw ? 'Hide' : 'Show'}</Text>
            </Pressable>
          </View>
        </Field>

        {mode === 'login' && (
          <Pressable style={s.remember} onPress={() => setRemember((v) => !v)} hitSlop={6}>
            <View style={[s.box, remember && s.boxOn]}>
              {remember && <Text style={s.check}>✓</Text>}
            </View>
            <Text style={{ color: C.ink, fontSize: 15 }}>Remember me</Text>
          </Pressable>
        )}

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
              <Field label="My fun fact or silly joke (optional)">
                <Input value={f.fun_fact} onChangeText={(v) => set('fun_fact', v)} maxLength={160}
                  placeholder="The thing people should know about you" />
              </Field>
            </View>
            <View>
              <Field label="Where do you live? (optional)">
                <LocationPicker
                  county={loc.county} area={loc.area} estate={loc.estate} optional
                  onChange={(p) => setLoc(p as any)} />
              </Field>
            </View>
          </>
        )}

        <View style={{ marginTop: 18 }}>
          <Btn label={busy ? 'Please wait…' : mode === 'login' ? 'Log in' : 'Create account'} onPress={submit} disabled={busy} />
        </View>

        {mode === 'login' && (
          <Pressable onPress={() => router.push('/forgot')} style={{ marginTop: 12 }}>
            <Text style={{ color: C.green, textAlign: 'center' }}>Forgot your password?</Text>
          </Pressable>
        )}

        {googleCidConfigured && Platform.OS !== 'web' && (
          <Btn label="Continue with Google" ghost onPress={googleSignIn} disabled={busy} />
        )}
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const s = StyleSheet.create({
  wrap: { padding: 22, maxWidth: 480, width: '100%', alignSelf: 'center' },
  brand: { fontFamily: serif, fontSize: 40, color: C.deep, textAlign: 'center' },
  tagline: { color: C.soft, textAlign: 'center', marginBottom: 26, fontSize: 15 },
  tabs: { flexDirection: 'row', gap: 8, marginBottom: 18 },
  tab: {
    flex: 1, borderRadius: 99, paddingVertical: 10, alignItems: 'center',
    borderWidth: 1, borderColor: '#d8ddd3', backgroundColor: C.card,
  },
  tabOn: { backgroundColor: C.green, borderColor: C.green },
  tabTxt: { color: C.ink, fontWeight: '600' },
  eye: { position: 'absolute', right: 14, top: 0, bottom: 0, justifyContent: 'center' },
  remember: { flexDirection: 'row', alignItems: 'center', gap: 9, marginTop: -2, marginBottom: 4 },
  box: {
    width: 20, height: 20, borderRadius: 6, borderWidth: 1.5, borderColor: '#b9c4b3',
    backgroundColor: C.card, alignItems: 'center', justifyContent: 'center',
  },
  boxOn: { backgroundColor: C.green, borderColor: C.green },
  check: { color: '#fff', fontSize: 13, fontWeight: '700', marginTop: -1 },
});
