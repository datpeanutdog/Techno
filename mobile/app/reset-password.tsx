import { router, useLocalSearchParams } from 'expo-router';
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

export default function ResetPasswordScreen() {
  const { mode } = useAppTheme();
  const params = useLocalSearchParams<{ email?: string; token?: string }>();
  const [email, setEmail] = useState(typeof params.email === 'string' ? params.email : '');
  const [token, setToken] = useState(typeof params.token === 'string' ? params.token : '');
  const [password, setPassword] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [complete, setComplete] = useState(false);

  const resetPassword = async () => {
    setBusy(true);
    setError('');
    setNotice('');
    try {
      const result = await requestJson<never>('/auth/reset-password', {
        method: 'POST',
        body: JSON.stringify({
          token: token.trim(),
          email: email.trim(),
          password,
          password_confirmation: confirmation,
        }),
      });
      setNotice(result.message ?? 'Your password has been reset.');
      setComplete(true);
    } catch (issue) {
      setError(issue instanceof Error ? issue.message : 'Your password could not be reset.');
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
          <Text style={styles.headerRouteTitle}>Reset password</Text>
          <View style={{ width: 44 }} />
        </View>
        <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={styles.contentArea}>
          <ScrollView contentContainerStyle={{ padding: 18 }} keyboardShouldPersistTaps="handled">
            <View style={styles.formCard}>
              <Text style={styles.formHeading}>Choose a new password</Text>
              <Text style={styles.formSubheading}>Open the reset link from your email and enter the token and email address below. If the link opened in a browser, copy the token from its URL.</Text>
              {error ? <Text style={styles.formError}>{error}</Text> : null}
              {notice ? <Text style={styles.formSubheading}>{notice}</Text> : null}
              {!complete ? (
                <>
                  <Text style={styles.fieldLabel}>Reset token</Text>
                  <TextInput autoCapitalize="none" onChangeText={setToken} style={styles.formInput} value={token} />
                  <Text style={styles.fieldLabel}>Email address</Text>
                  <TextInput autoCapitalize="none" keyboardType="email-address" onChangeText={setEmail} style={styles.formInput} value={email} />
                  <Text style={styles.fieldLabel}>New password</Text>
                  <TextInput onChangeText={setPassword} secureTextEntry style={styles.formInput} value={password} />
                  <Text style={styles.fieldLabel}>Confirm password</Text>
                  <TextInput onChangeText={setConfirmation} secureTextEntry style={styles.formInput} value={confirmation} />
                  <Pressable disabled={busy} onPress={() => void resetPassword()} style={[styles.primaryButton, busy && styles.disabledButton]}>
                    {busy ? <ActivityIndicator color={COLORS.white} /> : <Text style={styles.primaryButtonText}>Reset password</Text>}
                  </Pressable>
                </>
              ) : (
                <Pressable onPress={() => router.replace('/account')} style={styles.primaryButton}>
                  <Text style={styles.primaryButtonText}>Return to log in</Text>
                </Pressable>
              )}
            </View>
          </ScrollView>
        </KeyboardAvoidingView>
      </View>
    </SafeAreaView>
  );
}
