import React, { useCallback, useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';
import { api } from '../../lib/api';
import { useAuth } from '../../lib/store';
import { Avatar, Btn, Card, Empty, Loading, Screen } from '../../components/ui';
import { C, serifBlack } from '../../lib/theme';

export default function GroupDetail() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { user } = useAuth();
  const [d, setD] = useState<any>(null);
  const [err, setErr] = useState('');

  const load = useCallback(async () => {
    try { setD(await api('group', { id })); setErr(''); }
    catch (e: any) { setErr(e.message); }
  }, [id]);
  useFocusEffect(useCallback(() => { load(); }, [load]));

  if (!d) return <Screen>{err ? <Empty msg={err} /> : <Loading />}</Screen>;
  const g = d.group;
  const my = d.my;
  const isAdmin = my?.role === 'admin' || g.creatorId === user?.id;
  const isCreator = g.creatorId === user?.id;

  const act = async (action: string, uid?: number) => {
    try { await api('group', {}, { id: Number(id), do: action, uid }); load(); }
    catch (e: any) { Alert.alert('Oops', e.message); }
  };

  return (
    <Screen pad={false}>
      <ScrollView contentContainerStyle={{ padding: 16 }}>
        <Pressable onPress={() => router.back()}><Text style={s.back}>‹ Back</Text></Pressable>
        <Text style={s.name}>{g.name}</Text>
        <Text style={s.meta}>{g.category} · {g.place} · {d.members.length} member{d.members.length === 1 ? '' : 's'}</Text>
        <Text style={s.meta}>Started by {g.creator}</Text>
        {!!g.desc && <Text style={s.desc}>{g.desc}</Text>}

        <View style={{ flexDirection: 'row', gap: 8, marginVertical: 14, flexWrap: 'wrap' }}>
          {!my && <Btn label={g.joinPolicy === 'approval' ? 'Request to join' : 'Join group'} onPress={() => act('join')} />}
          {my?.status === 'pending' && <Btn label="Request sent — cancel?" ghost onPress={() => act('cancel_request')} />}
          {my?.status === 'active' && !isCreator && <Btn label="Leave" ghost onPress={() => act('leave')} />}
          {isAdmin && <Btn label="Edit group" small ghost onPress={() => router.push({ pathname: '/group/edit', params: { id } })} />}
          {isCreator && <Btn label="Delete" small ghost warn onPress={() =>
            Alert.alert('Delete this group?', 'For everyone.', [
              { text: 'Keep', style: 'cancel' }, { text: 'Delete', style: 'destructive', onPress: () => act('delete').then(() => router.back()) },
            ])} />}
        </View>

        {isAdmin && d.pending.length > 0 && (
          <>
            <Text style={s.h}>Join requests ({d.pending.length})</Text>
            <Card>
              {d.pending.map((m: any) => (
                <View key={m.id} style={s.row}>
                  <Avatar photo={m.photo} name={m.name} size={36} />
                  <View style={{ flex: 1, marginLeft: 10 }}>
                    <Text style={{ fontWeight: '600', color: C.ink }}>{m.name}</Text>
                    <Text style={{ color: C.soft, fontSize: 12 }}>{m.place}</Text>
                  </View>
                  <Btn label="Approve" small onPress={() => act('approve', m.id)} />
                  <Btn label="✕" small ghost onPress={() => act('decline', m.id)} />
                </View>
              ))}
            </Card>
          </>
        )}

        <Text style={s.h}>Members</Text>
        <Card>
          {d.members.map((m: any) => (
            <Pressable key={m.id} style={s.row} onPress={() => m.id !== user?.id && router.push(`/user/${m.id}`)}>
              <Avatar photo={m.photo} name={m.name} size={36} />
              <View style={{ flex: 1, marginLeft: 10 }}>
                <Text style={{ fontWeight: '600', color: C.ink }}>{m.name}</Text>
                <Text style={{ color: C.soft, fontSize: 12 }}>{m.role === 'admin' ? `Admin · ${m.place}` : m.place}</Text>
              </View>
              {isAdmin && m.id !== user?.id && m.role !== 'admin' && (
                <Text style={{ color: C.warn }} onPress={() => act('remove', m.id)}>Remove</Text>
              )}
            </Pressable>
          ))}
        </Card>
      </ScrollView>
    </Screen>
  );
}

const s = StyleSheet.create({
  back: { color: C.green, fontWeight: '600', marginBottom: 10 },
  name: { fontFamily: serifBlack, fontSize: 28, color: C.ink },
  meta: { color: C.soft, marginTop: 3 },
  desc: { color: C.ink, marginTop: 10, lineHeight: 21 },
  h: { fontWeight: '700', color: C.ink, fontSize: 17, marginTop: 10, marginBottom: 8 },
  row: { flexDirection: 'row', alignItems: 'center', paddingVertical: 6, gap: 6 },
});
