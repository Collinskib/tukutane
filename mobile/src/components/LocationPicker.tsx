import React, { useMemo, useState } from 'react';
import { View } from 'react-native';
import { Select, Input } from './ui';
import { useAuth } from '../lib/store';

const NEW = '__new';

// County → sub-county → estate (optional). Pass optional=true to allow blanks.
export default function LocationPicker({ county, area, estate, onChange, optional }: {
  county: string; area: string; estate: string;
  onChange: (p: { county: string; area: string; estate: string; estate_new?: string }) => void;
  optional?: boolean;
}) {
  const { meta } = useAuth();
  const [newEstate, setNewEstate] = useState('');
  const areas = useMemo(() => meta?.areas ?? {}, [meta?.areas]);
  const subs = useMemo(() => Object.keys(areas[county] ?? {}), [areas, county]);
  const estates = useMemo(
    () => (county && area ? (areas[county]?.[area] ?? []) : []),
    [areas, county, area],
  );

  const countyOpts = [
    ...(optional ? [{ value: '', label: 'All counties' }] : []),
    ...(meta?.counties ?? []).map((c) => ({ value: c, label: c })),
  ];
  const areaOpts = [
    { value: '', label: optional ? 'Any area' : 'Sub-county…' },
    ...subs.map((a) => ({ value: a, label: a })),
  ];
  const estateOpts = [
    { value: '', label: 'Estate / neighbourhood (optional)' },
    ...estates.map((e) => ({ value: e, label: e })),
    ...(area ? [{ value: NEW, label: '＋ Add my estate…' }] : []),
  ];

  return (
    <View>
      <Select
        value={county} options={countyOpts} placeholder="County…"
        onChange={(v) => { setNewEstate(''); onChange({ county: v, area: '', estate: '' }); }} />
      <View style={{ height: 8 }} />
      <Select
        value={area} options={areaOpts}
        onChange={(v) => { setNewEstate(''); onChange({ county, area: v, estate: '' }); }} />
      <View style={{ height: 8 }} />
      <Select
        value={estate} options={estateOpts}
        onChange={(v) => onChange({ county, area, estate: v })} />
      {estate === NEW && (
        <Input
          style={{ marginTop: 8 }}
          placeholder="Your estate or neighbourhood"
          value={newEstate}
          onChangeText={(t) => { setNewEstate(t); onChange({ county, area, estate: NEW, estate_new: t }); }}
        />
      )}
    </View>
  );
}
