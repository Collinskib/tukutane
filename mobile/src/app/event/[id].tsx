import React, { useCallback, useState } from 'react';
import { Alert, Image, Pressable, ScrollView, Share, StyleSheet, Text, TextInput, View } from 'react-native';
import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';
import { api, imgUrl } from '../../lib/api';
import { useAuth } from '../../lib/store';
import { Avatar, Btn, Card, Empty, Loading, Screen } from '../../components/ui';
import { C, serifBlack } from '../../lib/theme';

const CAT_ICON: Record<string, string> = {
  Football: '⚽', Dance: '💃', Nightlife: '🌙', Wellness: '🧘', Faith: '🙏',
  Hangouts: '☕', Business: '💼',
};

export default function EventDetail() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { user } = useAuth();
  const [d, setD] = useState<any>(null);
  const [err, setErr] = useState('');
  const [busy, setBusy] = useState(false);
  const [report, setReport] = useState('');
  const [showReport, setShowReport] = useState(false);

  const load = useCallback(async () => {
    try { setD(await api('event', { id })); setErr(''); }
    catch (e: any) { setErr(e.message); }
  }, [id]);

  useFocusEffect(useCallback(() => { load(); }, [load]));

  if (!d) return <Screen bottom>{err ? <Empty msg={err} /> : <Loading />}</Screen>;
  const ev = d.event;
  const wl = d.waitlist;
  const full = ev.capacity !== null && ev.going >= ev.capacity;

  const act = async (action: string) => {
    setBusy(true);
    try {
      const r = await api('rsvp', {}, { id: ev.id, action });
      await load();
      if (action === 'join' && r.result === 'full') Alert.alert('Full', 'This event is full — you can join the waitlist.');
    } catch (e: any) {
      Alert.alert('Oops', e.message);
    }
    setBusy(false);
  };

  const toggleSave = async () => {
    await api('save', {}, { id: ev.id });
    load();
  };

  const inviteFriend = async (uid: number) => {
    try {
      await api('invite', {}, { id: ev.id, uid });
      Alert.alert('Sent', 'Invitation sent.');
      load();
    } catch (e: any) { Alert.alert('Oops', e.message); }
  };

  const cancelEvent = () => {
    const opts: any[] = [{ text: 'Keep it', style: 'cancel' }];
    if (ev.seriesId) {
      opts.push({ text: 'Cancel this date', style: 'destructive', onPress: () => api('event-cancel', {}, { id: ev.id }).then(load) });
      opts.push({ text: 'Cancel whole series', style: 'destructive', onPress: () => api('event-cancel', {}, { id: ev.id, scope: 'series' }).then(load) });
    } else {
      opts.push({ text: 'Cancel event', style: 'destructive', onPress: () => api('event-cancel', {}, { id: ev.id }).then(load) });
    }
    Alert.alert('Cancel this event?', 'Attendees will be notified.', opts);
  };

  return (
    <Screen pad={false} bottom>
      <ScrollView contentContainerStyle={{ padding: 16 }}>
        <Pressable onPress={() => router.back()}><Text style={s.back}>‹ Back</Text></Pressable>
        {imgUrl(ev.cover) ? <Image source={{ uri: imgUrl(ev.cover) }} style={s.cover} /> : null}
        <View style={s.ribbon}>
          <Text style={s.ribbonCat}>{CAT_ICON[ev.category] ?? '📍'} {ev.category}</Text>
          <Text style={s.ribbonWhen}>{ev.atLabel}</Text>
        </View>
        <Text style={s.title}>{ev.title}</Text>
        <Text style={s.meta}>{ev.venue} · {ev.place}</Text>
        <Text style={s.meta}>
          {ev.cost === 0 ? 'Free' : `KSh ${ev.cost}`} · {ev.going} going
          {ev.capacity !== null ? ` of ${ev.capacity}` : ''}
          {ev.inviteOnly ? ' · invite-only' : ''}
        </Text>
        {ev.status === 'cancelled' && <Text style={s.cancelled}>This event was cancelled by the host.</Text>}
        {ev.desc ? <Text style={s.desc}>{ev.desc}</Text> : null}

        {d.host && (
          <Pressable style={s.host} onPress={() => d.host.id !== user?.id && router.push(`/user/${d.host.id}`)}>
            <Avatar photo={d.host.photo} name={d.host.name} size={40} />
            <View style={{ marginLeft: 10, flex: 1 }}>
              <Text style={{ fontWeight: '700', color: C.ink }}>{d.host.name}</Text>
              <Text style={{ color: C.soft, fontSize: 12 }}>Host{d.host.place ? ` · ${d.host.place}` : ''}</Text>
            </View>
          </Pressable>
        )}

        {/* actions */}
        <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginVertical: 12 }}>
          {ev.mine ? (
            <>
              <Btn label="Edit event" small onPress={() => router.push({ pathname: '/event/edit', params: { id: ev.id } })} />
              <Btn label="Cancel event" small ghost warn={false} onPress={cancelEvent} />
            </>
          ) : ev.status !== 'cancelled' && (
            <>
              {d.myInvite === 'pending' && ev.inviteOnly ? (
                <>
                  <Btn label="Accept invitation" small disabled={busy} onPress={() => act('accept_invite')} />
                  <Btn label="Decline" small ghost disabled={busy} onPress={() => act('decline_invite')} />
                </>
              ) : wl?.status === 'offered' ? (
                <>
                  <Btn label="Claim your spot" small disabled={busy} onPress={() => act('claim')} />
                  <Btn label="Pass" small ghost disabled={busy} onPress={() => act('decline_offer')} />
                </>
              ) : wl?.status === 'waiting' ? (
                <>
                  <Btn label="On the waitlist" small disabled onPress={() => {}} />
                  <Btn label="Leave waitlist" small ghost disabled={busy} onPress={() => act('unwaitlist')} />
                </>
              ) : ev.joined ? (
                <Btn label="Cancel RSVP" small ghost disabled={busy} onPress={() => act('cancel')} />
              ) : full ? (
                <Btn label="Join waitlist" small disabled={busy} onPress={() => act('waitlist')} />
              ) : (
                <Btn label="Join" small disabled={busy} onPress={() => act('join')} />
              )}
            </>
          )}
          {user && <Btn label={ev.saved ? '★ Saved' : '☆ Save'} small ghost onPress={toggleSave} />}
          <Btn label="Share" small ghost onPress={() => Share.share({ message: `${ev.title} — ${ev.atLabel} at ${ev.venue}, ${ev.place}. https://nipange.co.ke/event.php?id=${ev.id}` })} />
        </View>
        {wl?.status === 'offered' && <Text style={s.spot}>🎉 A spot opened for you — claim within 2 hours.</Text>}

        {/* attendees */}
        <Text style={s.h}>Who’s going ({d.attendees.length})</Text>
        <Card>
          {d.attendees.length === 0 && <Text style={{ color: C.soft }}>Be the first to join.</Text>}
          {d.attendees.map((a: any) => (
            <Pressable key={a.id || a.name} style={s.attRow}
              onPress={() => a.id && a.id !== user?.id && router.push(`/user/${a.id}`)}>
              <Avatar photo={a.photo} name={a.name} size={32} />
              <Text style={{ marginLeft: 10, color: C.ink }}>{a.name}</Text>
              {ev.mine && a.id && !d.invited[a.id] && (
                <Text style={s.miniLink} onPress={() => inviteFriend(a.id)}>invite</Text>
              )}
            </Pressable>
          ))}
        </Card>

        {/* invite friends (host or attendee) */}
        {user && d.friends?.length > 0 && (
          <>
            <Text style={s.h}>Invite friends</Text>
            <Card>
              {d.friends.filter((fr: any) => !d.attendees.some((a: any) => a.id === fr.id)).map((fr: any) => (
                <View key={fr.id} style={s.attRow}>
                  <Avatar photo={fr.photo} name={fr.name} size={32} />
                  <Text style={{ marginLeft: 10, flex: 1, color: C.ink }}>{fr.name}</Text>
                  {d.invited[fr.id] ? (
                    <Text style={{ color: C.soft, fontSize: 13 }}>{d.invited[fr.id]}</Text>
                  ) : (
                    <Btn label="Invite" small ghost onPress={() => inviteFriend(fr.id)} />
                  )}
                </View>
              ))}
            </Card>
          </>
        )}

        {d.siblings?.length > 0 && (
          <>
            <Text style={s.h}>🔁 Also coming up</Text>
            <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }}>
              {d.siblings.map((o: any) => (
                <Text key={o.id} style={s.sibLink} onPress={() => router.push(`/event/${o.id}`)}>{o.label}</Text>
              ))}
            </View>
          </>
        )}

        {d.canChat && (
          <Btn label={`💬 Event chat${d.pinned ? ' — 📌 ' + d.pinned.body.slice(0, 30) : ''}`}
            onPress={() => router.push({ pathname: '/event/chat', params: { id: ev.id, title: ev.title } })} />
        )}

        {/* report */}
        <Pressable onPress={() => setShowReport((v) => !v)} style={{ marginTop: 16 }}>
          <Text style={{ color: C.soft, fontSize: 13 }}>⚑ Report this event</Text>
        </Pressable>
        {showReport && (
          <View style={{ marginTop: 8 }}>
            <TextInput style={s.reportInput} placeholder="What's wrong?" value={report} onChangeText={setReport} />
            <Btn label="Send report" small ghost onPress={async () => {
              await api('report', {}, { type: 'gathering', id: ev.id, reason: report });
              setShowReport(false); setReport('');
              Alert.alert('Reported', 'Our team will review it.');
            }} />
          </View>
        )}
      </ScrollView>
    </Screen>
  );
}

const s = StyleSheet.create({
  back: { color: C.green, fontWeight: '600', marginBottom: 10 },
  cover: { width: '100%', height: 200, borderRadius: 16, backgroundColor: C.line, marginBottom: 0 },
  ribbon: {
    backgroundColor: C.deep, paddingVertical: 8, paddingHorizontal: 12, borderRadius: 10,
    flexDirection: 'row', justifyContent: 'space-between', marginTop: 10,
  },
  ribbonCat: { color: '#fff8e3', fontWeight: '700', fontSize: 13 },
  ribbonWhen: { color: C.sand, fontWeight: '700', fontSize: 13 },
  title: { fontFamily: serifBlack, fontSize: 30, color: C.ink, marginTop: 12 },
  meta: { color: C.soft, marginTop: 3 },
  cancelled: { color: C.warn, fontWeight: '700', marginTop: 8 },
  desc: { color: C.ink, lineHeight: 22, marginTop: 10 },
  host: { flexDirection: 'row', alignItems: 'center', marginTop: 14 },
  h: { fontWeight: '700', color: C.ink, fontSize: 17, marginTop: 16, marginBottom: 8 },
  attRow: { flexDirection: 'row', alignItems: 'center', paddingVertical: 6 },
  miniLink: { marginLeft: 'auto', color: C.green, fontWeight: '600' },
  sibLink: { color: C.green, fontWeight: '600', marginRight: 6 },
  spot: { color: C.green, fontWeight: '600', marginBottom: 8 },
  reportInput: {
    backgroundColor: C.card, borderWidth: 1, borderColor: '#d8ddd3',
    borderRadius: 12, padding: 11, marginBottom: 8, color: C.ink,
  },
});
