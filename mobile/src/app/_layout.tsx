import React, { useEffect } from 'react';
import { Stack, router, useSegments } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useFonts, PlayfairDisplay_700Bold, PlayfairDisplay_900Black } from '@expo-google-fonts/playfair-display';
import * as SplashScreen from 'expo-splash-screen';
import { AuthProvider, useAuth } from '../lib/store';
import { C } from '../lib/theme';

SplashScreen.preventAutoHideAsync().catch(() => {});

function Gate() {
  const { user, loading, incomplete, justJoined, clearJoin } = useAuth();
  const segments = useSegments();

  useEffect(() => {
    if (loading) return;
    const inAuth = segments[0] === 'auth';
    const inComplete = segments[0] === 'complete-profile';
    if (!user && !inAuth) router.replace('/auth');
    else if (user && incomplete && !inComplete) router.replace('/complete-profile');
    else if (user && !incomplete && (inAuth || inComplete)) {
      if (justJoined) { clearJoin(); router.replace('/welcome'); }
      else router.replace('/(tabs)/discover');
    }
  }, [user, loading, incomplete, segments, justJoined, clearJoin]);

  return (
    <Stack screenOptions={{ headerShown: false, contentStyle: { backgroundColor: C.cream } }}>
      <Stack.Screen name="(tabs)" />
      <Stack.Screen name="auth" />
      <Stack.Screen name="complete-profile" />
    </Stack>
  );
}

export default function Root() {
  const [fonts] = useFonts({ PlayfairDisplay_700Bold, PlayfairDisplay_900Black });
  useEffect(() => {
    if (fonts) SplashScreen.hideAsync().catch(() => {});
  }, [fonts]);
  if (!fonts) return null;
  return (
    <AuthProvider>
      <StatusBar style="dark" />
      <Gate />
    </AuthProvider>
  );
}
