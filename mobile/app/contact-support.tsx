import { router } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useState } from 'react';
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  Text,
  TextInput,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { COLORS, styles } from '../App';
import { requestJson } from '../src/lib/api';
import { useAppTheme } from '../src/lib/theme';

const CONTACT_CATEGORIES = [
  { value: 'account_recovery', label: 'Account recovery' },
  { value: 'general_support', label: 'General support' },
] as const;

export default function ContactSupportScreen() {
  const { mode } = useAppTheme();
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [category, setCategory] = useState('');
  const [details, setDetails] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const submit = async () => {
    setBusy(true);
    setError('');
    setNotice('');
    try {
      const result = await requestJson<never>('/support/contact', {
        method: 'POST',
        body: JSON.stringify({
          contact_name: name.trim(),
          contact_email: email.trim(),
          category,
          details: details.trim(),
        }),
      });
      setName('');
      setEmail('');
      setCategory('');
      setDetails('');
      setNotice(result.message ?? 'Your message has been sent to customer support.');
    } catch (issue) {
      setError(issue instanceof Error ? issue.message : 'Your message could not be sent.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <SafeAreaView edges={['top', 'bottom']} style={styles.safeArea}>
      <View style={styles.app}>
        <StatusBar style={mode === 'dark' ? 'light' : 'dark'} />
        <View style={styles.header}>
          <Pressable onPress={() => router.back()} style={styles.backButton}>
            <Text style={styles.backButtonText}>‹  Back</Text>
          </Pressable>
          <Text style={styles.headerRouteTitle}>Contact support</Text>
          <View style={{ width: 44 }} />
        </View>
        <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={styles.contentArea}>
          <ScrollView contentContainerStyle={{ padding: 18 }} keyboardShouldPersistTaps="handled">
            <View style={styles.formCard}>
              <Text style={styles.formHeading}>Contact customer support</Text>
              <Text style={styles.formSubheading}>You can contact us without logging in. Share an email address so our team can follow up.</Text>
              {error ? <Text style={styles.formError}>{error}</Text> : null}
              {notice ? <Text style={styles.formSubheading}>{notice}</Text> : null}
              <Text style={styles.fieldLabel}>Your name</Text>
              <TextInput autoComplete="name" maxLength={60} onChangeText={setName} style={styles.formInput} value={name} />
              <Text style={styles.fieldLabel}>Email address where we can reach you</Text>
              <TextInput autoCapitalize="none" autoComplete="email" keyboardType="email-address" maxLength={255} onChangeText={setEmail} style={styles.formInput} value={email} />
              <Text style={styles.fieldLabel}>How can we help?</Text>
              <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }}>
                {CONTACT_CATEGORIES.map((item) => {
                  const selected = category === item.value;
                  return (
                    <Pressable
                      accessibilityRole="button"
                      accessibilityState={{ selected }}
                      key={item.value}
                      onPress={() => setCategory(item.value)}
                      style={[styles.choiceChip, selected && styles.choiceChipSelected]}
                    >
                      <Text style={[styles.choiceChipText, selected && styles.choiceChipTextSelected]}>{item.label}</Text>
                    </Pressable>
                  );
                })}
              </View>
              <Text style={styles.fieldLabel}>Your message</Text>
              <TextInput maxLength={5000} multiline onChangeText={setDetails} style={[styles.formInput, styles.multilineInput]} value={details} />
              <Text style={[styles.formSubheading, { marginTop: 8 }]}>For account recovery, include the email associated with your account. Never include your password, reset code, or payment-card information.</Text>
              <Pressable disabled={busy} onPress={() => void submit()} style={[styles.primaryButton, busy && styles.disabledButton]}>
                {busy ? <ActivityIndicator color={COLORS.white} /> : <Text style={styles.primaryButtonText}>Send message to support</Text>}
              </Pressable>
              <Pressable onPress={() => router.push('/account')} style={{ alignSelf: 'center', marginTop: 14 }}>
                <Text style={{ color: COLORS.goldDark }}>Log in to report an account</Text>
              </Pressable>
            </View>
          </ScrollView>
        </KeyboardAvoidingView>
      </View>
    </SafeAreaView>
  );
}
