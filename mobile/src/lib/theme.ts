import { Platform } from 'react-native';

// Matches nipange.co.ke's palette + type (see assets/style.css on the site).
export const C = {
  green: '#214e3c',     // primary buttons / accents
  deep: '#163e31',      // hero background
  cream: '#faf8f2',     // app background
  card: '#ffffff',
  accent: '#d45c36',    // brand dot / highlights
  sand: '#f4ab77',      // hero kicker
  ink: '#192b21',
  soft: '#4a6053',
  line: '#e2e3d9',
  warn: '#a33a1f',
};

export const serif = Platform.select({ ios: 'PlayfairDisplay_700Bold', android: 'PlayfairDisplay_700Bold', default: 'serif' })!;
export const serifBlack = Platform.select({ ios: 'PlayfairDisplay_900Black', android: 'PlayfairDisplay_900Black', default: 'serif' })!;

export const BASE = 'https://nipange.co.ke';
