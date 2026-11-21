import React, { useCallback, useState } from 'react';
import { Image, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';
import { api, imgUrl } from '../../lib/api';
import { useAuth } from '../../lib/store';
import { Btn, Empty, Loading, Screen } from '../../components/ui';
import EventCard, { Ev } from '../../components/EventCard';
import { C, serifBlack } from '../../lib/theme';

export default function VenueDetail() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { user } = useAuth();
  const [d, setD] = useState<any>(null);
  const [err, setErr] = useState('');

  const load = useCallback(async () => {
    try { setD(await api('venue', { id })); setErr(''); }
    catch (e: any) { setErr(e.message); }
  }, [id]);
  useFocusEffect(useCallback(() => { load(); }, [load]));

  if (!d) return <Screen bottom>{err ? <Empty msg={err} /> : <Loading />}</Screen>;
  const v = d.venue;
  const mine = v.creatorId === user?.id;

  return (
    <Screen pad={false} bottom>
      <ScrollView contentContainerStyle={{ padding: 16 }}>
        <Pressable onPress={() => router.back()}><Text style={s.back}>‹ Back</Text></Pressable>
        {imgUrl(v.photo) ? <Image source={{ uri: imgUrl(v.photo) }} style={s.photo} /> : null}
        <Text style={s.name}>{v.name}</Text>
        <Text style={s.meta}>{v.place}</Text>
        {!!v.desc && <Text style={s.desc}>{v.desc}</Text>}
        {mine && (
          <View style={{ marginVertical: 12 }}>
            <Btn label="Edit venue" small ghost onPress={() => router.push({ pathname: '/venue/edit', params: { id } })} />
          </View>
        )}
        <Text style={s.h}>Upcoming events here</Text>
        {d.events.length === 0 && <Empty msg="Nothing scheduled — host something here!" />}
        {d.events.map((ev: Ev) => <EventCard key={ev.id} ev={ev} />)}
      </ScrollView>
    </Screen>
  );
}

const s = StyleSheet.create({
  back: { color: C.green, fontWeight: '600', marginBottom: 10 },
  photo: { width: '100%', height: 180, borderRadius: 16, backgroundColor: C.line, marginBottom: 12 },
  name: { fontFamily: serifBlack, fontSize: 28, color: C.ink },
  meta: { color: C.soft, marginTop: 4 },
  desc: { color: C.ink, marginTop: 10, lineHeight: 21 },
  h: { fontWeight: '700', color: C.ink, fontSize: 17, marginTop: 14, marginBottom: 8 },
});
