import React, { useState } from 'react';
import {
  ActivityIndicator, FlatList, Image, Modal, Pressable, StyleSheet, Text, TextInput, View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { C, serif } from '../lib/theme';
import { imgUrl } from '../lib/api';

export function Screen({ children, pad = true }: { children?: React.ReactNode; pad?: boolean }) {
  return (
    <SafeAreaView style={s.screen} edges={['top', 'left', 'right']}>
      <View style={pad && s.pad}>{children}</View>
    </SafeAreaView>
  );
}

export function Title({ children, small }: { children: React.ReactNode; small?: boolean }) {
  return <Text style={[s.title, small && { fontSize: 20 }]}>{children}</Text>;
}

export function Sub({ children }: { children: React.ReactNode }) {
  return <Text style={s.sub}>{children}</Text>;
}

export function Btn({ label, onPress, ghost, warn, disabled, small }: {
  label: string; onPress: () => void; ghost?: boolean; warn?: boolean; disabled?: boolean; small?: boolean;
}) {
  return (
    <Pressable
      onPress={onPress}
      disabled={disabled}
      style={({ pressed }) => [
        s.btn,
        small && s.btnSm,
        ghost && s.btnGhost,
        warn && { backgroundColor: C.warn, borderColor: C.warn },
        (pressed || disabled) && { opacity: 0.7 },
      ]}>
      <Text style={[s.btnTxt, ghost && { color: C.ink }]}>{label}</Text>
    </Pressable>
  );
}

export function Chip({ label, active, onPress }: { label: string; active?: boolean; onPress: () => void }) {
  return (
    <Pressable onPress={onPress} style={[s.chip, active && s.chipOn]}>
      <Text style={[s.chipTxt, active && { color: '#fff' }]}>{label}</Text>
    </Pressable>
  );
}

export function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <View style={{ marginBottom: 14 }}>
      <Text style={s.label}>{label}</Text>
      {children}
    </View>
  );
}

export function Input(props: React.ComponentProps<typeof TextInput>) {
  return (
    <TextInput
      placeholderTextColor={C.soft}
      {...props}
      style={[s.input, props.multiline && { minHeight: 80, textAlignVertical: 'top' }, props.style]}
    />
  );
}

// Modal list picker — RN's <select> equivalent.
export function Select({ label, value, options, onChange, placeholder }: {
  label?: string; value: string; options: string[] | { value: string; label: string }[];
  onChange: (v: string) => void; placeholder?: string;
}) {
  const [open, setOpen] = useState(false);
  const opts = options.map((o) => (typeof o === 'string' ? { value: o, label: o } : o));
  const shown = opts.find((o) => o.value === value)?.label ?? '';
  return (
    <View>
      {label ? <Text style={s.label}>{label}</Text> : null}
      <Pressable style={s.input} onPress={() => setOpen(true)}>
        <Text style={{ color: shown ? C.ink : C.soft }}>{shown || placeholder || 'Choose…'}</Text>
      </Pressable>
      <Modal visible={open} transparent animationType="fade" onRequestClose={() => setOpen(false)}>
        <Pressable style={s.backdrop} onPress={() => setOpen(false)}>
          <View style={s.sheet}>
            <FlatList
              data={opts}
              keyExtractor={(o) => o.value || '__none'}
              renderItem={({ item }) => (
                <Pressable
                  style={[s.opt, item.value === value && { backgroundColor: '#eef3ef' }]}
                  onPress={() => { onChange(item.value); setOpen(false); }}>
                  <Text style={{ color: C.ink, fontSize: 16 }}>{item.label}</Text>
                </Pressable>
              )}
            />
          </View>
        </Pressable>
      </Modal>
    </View>
  );
}

export function Avatar({ photo, name, size = 40 }: { photo?: string; name?: string; size?: number }) {
  const uri = imgUrl(photo);
  const st = { width: size, height: size, borderRadius: size / 2 };
  if (uri) return <Image source={{ uri }} style={[st, { backgroundColor: C.line }]} />;
  return (
    <View style={[st, s.avaFallback]}>
      <Text style={{ color: '#fff', fontWeight: '700', fontSize: size * 0.42 }}>
        {(name || '?').trim().charAt(0).toUpperCase()}
      </Text>
    </View>
  );
}

export function Err({ msg }: { msg: string }) {
  if (!msg) return null;
  return <Text style={{ color: C.warn, marginBottom: 10 }}>{msg}</Text>;
}

export function Empty({ msg }: { msg: string }) {
  return <Text style={s.empty}>{msg}</Text>;
}

export function Loading() {
  return <ActivityIndicator color={C.green} style={{ marginTop: 40 }} size="large" />;
}

export function Card({ children, style }: { children: React.ReactNode; style?: any }) {
  return <View style={[s.card, style]}>{children}</View>;
}

const s = StyleSheet.create({
  screen: { flex: 1, backgroundColor: C.cream },
  pad: { flex: 1, padding: 16 },
  title: { fontFamily: serif, fontSize: 30, color: C.ink, marginBottom: 4 },
  sub: { color: C.soft, marginBottom: 14 },
  btn: {
    backgroundColor: C.green, borderRadius: 99, paddingVertical: 11, paddingHorizontal: 18,
    alignItems: 'center', borderWidth: 1, borderColor: C.green,
  },
  btnSm: { paddingVertical: 7, paddingHorizontal: 12 },
  btnGhost: { backgroundColor: C.card, borderColor: '#d8ddd3' },
  btnTxt: { color: '#fff', fontWeight: '600' },
  chip: {
    backgroundColor: C.card, borderWidth: 1, borderColor: '#d8ddd3', borderRadius: 99,
    paddingVertical: 7, paddingHorizontal: 13, marginRight: 8, marginBottom: 8,
  },
  chipOn: { backgroundColor: C.green, borderColor: C.green },
  chipTxt: { color: C.ink, fontSize: 14 },
  label: { fontWeight: '700', color: C.ink, marginBottom: 6 },
  input: {
    backgroundColor: C.card, borderWidth: 1, borderColor: '#d8ddd3', borderRadius: 12,
    paddingVertical: 11, paddingHorizontal: 13, color: C.ink, fontSize: 16, justifyContent: 'center',
  },
  backdrop: { flex: 1, backgroundColor: 'rgba(17,38,27,0.55)', justifyContent: 'center', padding: 28 },
  sheet: { backgroundColor: C.card, borderRadius: 18, maxHeight: 420, overflow: 'hidden' },
  opt: { paddingVertical: 13, paddingHorizontal: 16, borderBottomWidth: 1, borderBottomColor: '#eef1ea' },
  avaFallback: { backgroundColor: C.green, alignItems: 'center', justifyContent: 'center' },
  empty: { color: C.soft, textAlign: 'center', marginTop: 40, fontSize: 15 },
  card: {
    backgroundColor: C.card, borderWidth: 1, borderColor: '#e4e6db', borderRadius: 16,
    padding: 14, marginBottom: 12,
  },
});
