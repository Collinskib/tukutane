import React, { useEffect } from 'react';
import { router } from 'expo-router';
import { Screen, Loading } from '../components/ui';

// Invites live under My plans → Invites tab.
export default function Invites() {
  useEffect(() => { router.replace('/(tabs)/plans'); }, []);
  return <Screen><Loading /></Screen>;
}
