import React, { useCallback, useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { router, useFocusEffect } from 'expo-router';
import { api } from '../lib/api';
import { Avatar, Btn, Card, Empty, Loading, Screen } from '../components/ui';
import { C } from '../lib/theme';

type F = { id: number; name: string; photo: string; place: string; reqId: number };

export default function Friends() {
  const [d, setD] = useState<any>(null);
  const load = useCallback(async () => {
    try { setD(await api('friends')); } catch { /* offline */ }
  }, []);
  useFocusEffect(useCallback(() => { load(); }, [load]));

  if (!d) return <Screen bottom><Loading /></Screen>;

  const act = async (uid: number, action: string) => {
    try { await api('friend', {}, { uid, do: action }); load(); }
    catch (e: any) { Alert.alert('Oops', e.message); }
  };

  const Row = ({ p, children }: { p: F; children?: React.ReactNode }) => (
    <View style={s.row}>
      <Pressable style={{ flexDirection: 'row', alignItems: 'center', flex: 1 }} onPress={() => router.push(`/user/${p.id}`)}>
        <Avatar photo={p.photo} name={p.name} size={40} />
        <View style={{ marginLeft: 10, flex: 1 }}>
          <Text style={{ fontWeight: '600', color: C.ink }}>{p.name}</Text>
          {!!p.place && <Text style={{ color: C.soft, fontSize: 12 }}>{p.place}</Text>}
        </View>
      </Pressable>
      {children}
    </View>
  );

  return (
    <Screen pad={false} bottom>
      <ScrollView contentContainerStyle={{ padding: 16 }}>
        <Pressable onPress={() => router.back()}><Text style={s.back}>‹ Back</Text></Pressable>
        <Text style={s.h}>Friends</Text>

        {d.pending.length > 0 && (
          <>
            <Text style={s.sh}>Requests ({d.pending.length})</Text>
            <Card>
              {d.pending.map((p: F) => (
                <Row key={p.id} p={p}>
                  <View style={{ flexDirection: 'row', gap: 6 }}>
                    <Btn label="Accept" small onPress={() => act(p.id, 'accept')} />
                    <Btn label="✕" small ghost onPress={() => act(p.id, 'decline')} />
                  </View>
                </Row>
              ))}
            </Card>
          </>
        )}

        {d.friends.length > 0 ? (
          <Card>
            {d.friends.map((p: F) => (
              <Row key={p.id} p={p}>
                <Btn label="Message" small ghost onPress={async () => {
                  const r = await api('conversation', {}, { uid: p.id });
                  router.push(`/chat/${r.conv}`);
                }} />
              </Row>
            ))}
          </Card>
        ) : (
          <Empty msg="No friends yet — find people on the People tab." />
        )}

        {d.outgoing.length > 0 && (
          <>
            <Text style={s.sh}>Sent requests</Text>
            <Card>
              {d.outgoing.map((p: F) => (
                <Row key={p.id} p={p}>
                  <Btn label="Cancel" small ghost onPress={() => act(p.id, 'cancel')} />
                </Row>
              ))}
            </Card>
          </>
        )}

        {d.blocked.length > 0 && (
          <>
            <Text style={s.sh}>Blocked</Text>
            <Card>
              {d.blocked.map((p: F) => (
                <Row key={p.id} p={p}>
                  <Btn label="Unblock" small ghost onPress={() => act(p.id, 'unblock')} />
                </Row>
              ))}
            </Card>
          </>
        )}
      </ScrollView>
    </Screen>
  );
}

const s = StyleSheet.create({
  back: { color: C.green, fontWeight: '600', marginBottom: 10 },
  h: { fontSize: 24, fontWeight: '700', color: C.ink, marginBottom: 12 },
  sh: { fontWeight: '700', color: C.ink, marginTop: 8, marginBottom: 8 },
  row: { flexDirection: 'row', alignItems: 'center', paddingVertical: 7, gap: 8 },
});
