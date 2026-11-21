import React from 'react';
import { Loading, Screen } from '../components/ui';

// The Gate in _layout redirects; this is just a splash while auth resolves.
export default function Index() {
  return <Screen><Loading /></Screen>;
}
