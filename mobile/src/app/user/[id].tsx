import React, { useCallback, useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';
import { api } from '../../lib/api';
import { useAuth } from '../../lib/store';
import { Avatar, Btn, Empty, Loading, Screen } from '../../components/ui';
import EventCard, { Ev } from '../../components/EventCard';
import { C, serifBlack } from '../../lib/theme';

export default function UserProfile() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { user } = useAuth();
  const [d, setD] = useState<any>(null);
  const [err, setErr] = useState('');
  const [report, setReport] = useState('');
  const [showReport, setShowReport] = useState(false);

  const load = useCallback(async () => {
    try { setD(await api('user', { id })); setErr(''); }
    catch (e: any) { setErr(e.message); }
  }, [id]);

  useFocusEffect(useCallback(() => { setD(null); load(); }, [load]));

  if (!d) return <Screen>{err ? <Empty msg={err} /> : <Loading />}</Screen>;
  const u = d.user;
  const rel = d.rel;
  const isMe = user?.id === u.id;

  const act = async (action: string) => {
    try { await api('friend', {}, { uid: u.id, do: action }); load(); }
    catch (e: any) { Alert.alert('Oops', e.message); }
  };

  const message = async () => {
    try {
      const r = await api('conversation', {}, { uid: u.id });
      router.push(`/chat/${r.conv}`);
    } catch (e: any) { Alert.alert('Oops', e.message); }
  };

  return (
    <Screen pad={false}>
      <ScrollView contentContainerStyle={{ padding: 16 }}>
        <Pressable onPress={() => router.back()}><Text style={s.back}>‹ Back</Text></Pressable>
        <View style={s.head}>
          <Avatar photo={u.photo} name={u.name} size={84} />
          <View style={{ marginLeft: 16, flex: 1 }}>
            <Text style={s.name}>{u.name}</Text>
            <Text style={s.meta}>
              {[u.age, u.gender, u.place].filter(Boolean).join(' · ') || 'Kenya'}
            </Text>
            {d.followers > 0 && <Text style={s.meta}>{d.followers} follower{d.followers === 1 ? '' : 's'}</Text>}
          </View>
        </View>

        {u.bio ? <Text style={s.bio}>{u.bio}</Text> : null}
        {u.interests ? (
          <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 6, marginTop: 10 }}>
            {u.interests.split(',').filter(Boolean).map((i: string) => (
              <View key={i} style={[s.chip, d.shared?.includes(i) && { borderColor: C.green }]}>
                <Text style={{ color: d.shared?.includes(i) ? C.green : C.ink, fontSize: 13 }}>{i}</Text>
              </View>
            ))}
          </View>
        ) : null}
        {d.shared?.length ? <Text style={s.shared}>Also into: {d.shared.join(', ')}</Text> : null}

        {!isMe && (
          <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginVertical: 14 }}>
            {d.blocked ? (
              <Btn label="Unblock" small ghost onPress={() => act('unblock')} />
            ) : (
              <>
                {rel?.status === 'accepted' && <Btn label="Message" small onPress={message} />}
                {!rel && <Btn label="Add friend" small onPress={() => act('send')} />}
                {rel?.status === 'pending' && rel.requester === user?.id && <Btn label="Cancel request" small ghost onPress={() => act('cancel')} />}
                {rel?.status === 'pending' && rel.requester !== user?.id && (
                  <>
                    <Btn label="Accept" small onPress={() => act('accept')} />
                    <Btn label="Decline" small ghost onPress={() => act('decline')} />
                  </>
                )}
                {rel?.status === 'accepted' && <Btn label="Remove" small ghost onPress={() => act('remove')} />}
                <Btn label={d.following ? 'Following ✓' : 'Follow'} small ghost onPress={() => act(d.following ? 'unfollow' : 'follow')} />
                <Btn label="Block" small ghost warn onPress={() => Alert.alert('Block ' + u.name + '?', '', [
                  { text: 'Cancel', style: 'cancel' }, { text: 'Block', style: 'destructive', onPress: () => act('block') },
                ])} />
              </>
            )}
          </View>
        )}

        {d.hosting?.length > 0 && (
          <>
            <Text style={s.h}>Hosting</Text>
            {d.hosting.map((ev: Ev) => <EventCard key={ev.id} ev={ev} />)}
          </>
        )}

        {!isMe && (
          <>
            <Pressable onPress={() => setShowReport((v) => !v)} style={{ marginTop: 10 }}>
              <Text style={{ color: C.soft, fontSize: 13 }}>⚑ Report this member</Text>
            </Pressable>
            {showReport && (
              <View style={{ marginTop: 8 }}>
                <TextInput style={s.reportInput} placeholder="What's wrong?" value={report} onChangeText={setReport} />
                <Btn label="Send report" small ghost onPress={async () => {
                  await api('report', {}, { type: 'user', id: u.id, reason: report });
                  setShowReport(false); setReport('');
                  Alert.alert('Reported', 'Our team will review it.');
                }} />
              </View>
            )}
          </>
        )}
      </ScrollView>
    </Screen>
  );
}

const s = StyleSheet.create({
  back: { color: C.green, fontWeight: '600', marginBottom: 12 },
  head: { flexDirection: 'row', alignItems: 'center' },
  name: { fontFamily: serifBlack, fontSize: 28, color: C.ink },
  meta: { color: C.soft, marginTop: 2 },
  bio: { color: C.ink, marginTop: 12, lineHeight: 21 },
  chip: {
    borderWidth: 1, borderColor: '#d8ddd3', borderRadius: 99,
    paddingVertical: 5, paddingHorizontal: 11, backgroundColor: C.card,
  },
  shared: { color: C.green, marginTop: 10, fontWeight: '600' },
  h: { fontWeight: '700', color: C.ink, fontSize: 17, marginTop: 8, marginBottom: 8 },
  reportInput: {
    backgroundColor: C.card, borderWidth: 1, borderColor: '#d8ddd3',
    borderRadius: 12, padding: 11, marginBottom: 8, color: C.ink,
  },
});
