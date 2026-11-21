import React, { useState } from 'react';
import { Text } from 'react-native';
import { api } from '../lib/api';
import { Btn, Err, Field, Input, Screen, Sub, Title } from '../components/ui';
import { C } from '../lib/theme';

export default function Forgot() {
  const [email, setEmail] = useState('');
  const [done, setDone] = useState(false);
  const [err, setErr] = useState('');
  const [busy, setBusy] = useState(false);

  const send = async () => {
    setErr(''); setBusy(true);
    try {
      await api('auth/forgot', {}, { email: email.trim() });
      setDone(true);
    } catch (e: any) {
      setErr(e.message);
    }
    setBusy(false);
  };

  return (
    <Screen>
      <Title>Reset password</Title>
      <Sub>We’ll email you a link to choose a new one.</Sub>
      {done ? (
        <Text style={{ color: C.green, fontWeight: '600' }}>
          If that email has an account, a reset link is on its way. Check your inbox (and spam).
        </Text>
      ) : (
        <>
          <Err msg={err} />
          <Field label="Email">
            <Input value={email} onChangeText={setEmail} keyboardType="email-address" autoCapitalize="none" />
          </Field>
          <Btn label={busy ? 'Sending…' : 'Send reset link'} onPress={send} disabled={busy} />
        </>
      )}
    </Screen>
  );
}
