import * as ImagePicker from 'expo-image-picker';
import { router, useFocusEffect } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useCallback, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Image,
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
import { clearAuthToken, getAuthToken, requestJson, saveAuthToken } from '../src/lib/api';
import { useAppTheme } from '../src/lib/theme';

type PreviousAppeal = {
  id: number;
  message: string;
  status: string;
  admin_note: string | null;
};

type SuspensionAppeal = {
  id: number;
  message: string;
  status: string;
  admin_note: string | null;
  proof_image: string | null;
  previous_appeal: PreviousAppeal | null;
};

type SuspensionAccount = {
  name: string;
  account_identifier: string;
  email: string;
  suspended_at: string | null;
  suspension_reason: string | null;
};

type SuspensionData = {
  user: SuspensionAccount;
  appeal: SuspensionAppeal | null;
};

const MAX_PROOF_BYTES = 5 * 1024 * 1024;
const ALLOWED_PROOF_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

export default function SuspensionScreen() {
  const { mode } = useAppTheme();
  const [account, setAccount] = useState<SuspensionAccount | null>(null);
  const [appeal, setAppeal] = useState<SuspensionAppeal | null>(null);
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [message, setMessage] = useState('');
  const [proof, setProof] = useState<ImagePicker.ImagePickerAsset | null>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const loadAppeal = useCallback(async () => {
    try {
      if (!await getAuthToken()) {
        setAccount(null);
        setAppeal(null);
        return;
      }

      const result = await requestJson<SuspensionData>('/auth/suspension/me');
      setAccount(result.data.user);
      setAppeal(result.data.appeal);
      setError('');
    } catch (issue) {
      await clearAuthToken();
      setAccount(null);
      setAppeal(null);
      setError(issue instanceof Error ? issue.message : 'Your suspension details could not be loaded.');
    } finally {
      setLoading(false);
    }
  }, []);

  useFocusEffect(useCallback(() => {
    void loadAppeal();
  }, [loadAppeal]));

  const signIn = async () => {
    setBusy(true);
    setError('');
    setNotice('');
    try {
      const result = await requestJson<SuspensionAccount>('/auth/suspension/login', {
        method: 'POST',
        body: JSON.stringify({ email: email.trim(), password }),
      });
      if (!result.token) {
        throw new Error('A secure appeal session could not be started. Please try again.');
      }
      await saveAuthToken(result.token);
      setPassword('');
      await loadAppeal();
    } catch (issue) {
      setError(issue instanceof Error ? issue.message : 'Your suspended account could not be verified.');
    } finally {
      setBusy(false);
    }
  };

  const chooseProof = async () => {
    const permission = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!permission.granted) {
      Alert.alert('Photo access needed', 'Allow Pasabuy to access your photos to attach appeal evidence.');
      return;
    }

    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ['images'],
      allowsEditing: true,
      quality: 0.8,
    });
    if (result.canceled) return;

    const selected = result.assets[0];
    if (selected.mimeType && !ALLOWED_PROOF_TYPES.includes(selected.mimeType)) {
      setError('Choose a JPG, PNG, or WebP image.');
      return;
    }
    if (selected.fileSize && selected.fileSize > MAX_PROOF_BYTES) {
      setError('Choose an image that is no larger than 5 MB.');
      return;
    }
    setError('');
    setProof(selected);
  };

  const sendAppeal = async () => {
    setBusy(true);
    setError('');
    setNotice('');
    try {
      const form = new FormData();
      form.append('message', message.trim());
      if (proof) {
        if (Platform.OS === 'web' && proof.file) {
          form.append('proof', proof.file);
        } else {
          form.append('proof', {
            uri: proof.uri,
            name: proof.fileName ?? 'appeal-proof.jpg',
            type: proof.mimeType ?? 'image/jpeg',
          } as unknown as Blob);
        }
      }
      const result = await requestJson<SuspensionAppeal>('/auth/suspension/appeals', {
        method: 'POST',
        body: form,
      }, 90_000);
      setAppeal(result.data);
      setMessage('');
      setProof(null);
      setNotice(result.message ?? 'Your appeal has been sent to our support team.');
    } catch (issue) {
      setError(issue instanceof Error ? issue.message : 'Your appeal could not be sent.');
    } finally {
      setBusy(false);
    }
  };

  const signOut = async () => {
    setBusy(true);
    try {
      await requestJson<never>('/auth/suspension/logout', { method: 'POST' });
    } catch (issue) {
      setError(issue instanceof Error ? issue.message : 'The server could not end your appeal session.');
    } finally {
      await clearAuthToken();
      setAccount(null);
      setAppeal(null);
      setBusy(false);
    }
  };

  const hasOpenAppeal = appeal ? ['pending', 'under_review'].includes(appeal.status) : false;

  return (
    <SafeAreaView edges={['top', 'bottom']} style={styles.safeArea}>
      <View style={styles.app}>
        <StatusBar style={mode === 'dark' ? 'light' : 'dark'} />
        <View style={styles.header}>
          <Pressable onPress={() => router.back()} style={styles.backButton}>
            <Text style={styles.backButtonText}>‹  Back</Text>
          </Pressable>
          <Text style={styles.headerRouteTitle}>Suspension support</Text>
          <View style={{ width: 44 }} />
        </View>
        {loading ? (
          <View style={styles.loadingState}>
            <ActivityIndicator color={COLORS.goldDark} />
            <Text style={styles.helperText}>Loading account support...</Text>
          </View>
        ) : (
          <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={styles.contentArea}>
            <ScrollView contentContainerStyle={{ padding: 18, gap: 16 }} keyboardShouldPersistTaps="handled">
              {error ? <Text style={styles.formError}>{error}</Text> : null}
              {notice ? <Text style={styles.formSubheading}>{notice}</Text> : null}
              {!account ? (
                <View style={styles.formCard}>
                  <Text style={styles.formHeading}>Verify your suspended account</Text>
                  <Text style={styles.formSubheading}>Sign in to review the suspension and contact support. This session only enables suspension appeals.</Text>
                  <Text style={styles.fieldLabel}>Email</Text>
                  <TextInput autoCapitalize="none" keyboardType="email-address" onChangeText={setEmail} style={styles.formInput} value={email} />
                  <Text style={styles.fieldLabel}>Password</Text>
                  <TextInput onChangeText={setPassword} secureTextEntry style={styles.formInput} value={password} />
                  <Pressable disabled={busy} onPress={() => void signIn()} style={[styles.primaryButton, busy && styles.disabledButton]}>
                    {busy ? <ActivityIndicator color="#fff" /> : <Text style={styles.primaryButtonText}>Continue to account support</Text>}
                  </Pressable>
                </View>
              ) : (
                <>
                  <View style={styles.formCard}>
                    <Text style={styles.formHeading}>Your account is suspended</Text>
                    <Text style={styles.formSubheading}>Account ID: {account.account_identifier} · {account.email}</Text>
                    <Text style={[styles.fieldLabel, { marginTop: 16 }]}>Reason for suspension</Text>
                    <Text style={{ color: COLORS.ink, fontSize: 14, lineHeight: 21 }}>
                      {account.suspension_reason || 'No additional reason was provided. Contact support for clarification.'}
                    </Text>
                    {account.suspended_at ? (
                      <Text style={[styles.formSubheading, { marginTop: 10 }]}>Suspended {new Date(account.suspended_at).toLocaleDateString()}</Text>
                    ) : null}
                  </View>

                  {appeal?.previous_appeal ? (
                    <View style={styles.formCard}>
                      <Text style={styles.formHeading}>Response to your previous appeal</Text>
                      <Text style={styles.fieldLabel}>Your previous message</Text>
                      <Text style={{ color: COLORS.ink, lineHeight: 20 }}>{appeal.previous_appeal.message}</Text>
                      <Text style={styles.fieldLabel}>Support response</Text>
                      <Text style={{ color: COLORS.ink, lineHeight: 20 }}>
                        {appeal.previous_appeal.admin_note || 'No response message was included. You can explain what information or image was missing.'}
                      </Text>
                    </View>
                  ) : null}

                  {appeal ? (
                    <View style={styles.formCard}>
                      <Text style={styles.formHeading}>{appeal.previous_appeal ? 'Your counter-appeal' : 'Your latest appeal'} · {formatStatus(appeal.status)}</Text>
                      <Text style={{ color: COLORS.ink, lineHeight: 20, marginTop: 10 }}>{appeal.message}</Text>
                      {appeal.proof_image ? <Image source={{ uri: appeal.proof_image }} style={{ height: 200, marginTop: 12, borderRadius: 10 }} resizeMode="contain" /> : null}
                      {appeal.admin_note ? (
                        <Text style={[styles.formSubheading, { marginTop: 12 }]}>
                          {appeal.status === 'denied' ? 'Support response: ' : 'Support update: '}{appeal.admin_note}
                        </Text>
                      ) : null}
                    </View>
                  ) : null}

                  {!hasOpenAppeal ? (
                    <View style={styles.formCard}>
                      <Text style={styles.formHeading}>{appeal?.status === 'denied' ? 'Send a counter-appeal' : 'Appeal this suspension'}</Text>
                      <Text style={styles.formSubheading}>Explain why you think the suspension should be reviewed. You may attach one image as evidence (JPG, PNG, or WebP, up to 5 MB).</Text>
                      <TextInput
                        maxLength={5000}
                        multiline
                        onChangeText={setMessage}
                        placeholder="Your message to support"
                        style={[styles.formInput, styles.multilineInput, { minHeight: 130 }]}
                        value={message}
                      />
                      <Pressable disabled={busy} onPress={() => void chooseProof()} style={[styles.primaryButton, { backgroundColor: COLORS.paleNeutral, marginTop: 10 }]}>
                        <Text style={[styles.primaryButtonText, { color: COLORS.navy }]}>{proof ? 'Change evidence image' : 'Attach an image (optional)'}</Text>
                      </Pressable>
                      {proof ? (
                        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12, marginTop: 10 }}>
                          <Image source={{ uri: proof.uri }} style={{ width: 64, height: 64, borderRadius: 8 }} />
                          <Pressable onPress={() => setProof(null)}><Text style={{ color: COLORS.goldDark }}>Remove image</Text></Pressable>
                        </View>
                      ) : null}
                      <Pressable disabled={busy} onPress={() => void sendAppeal()} style={[styles.primaryButton, busy && styles.disabledButton, { marginTop: 16 }]}>
                        {busy ? <ActivityIndicator color="#fff" /> : <Text style={styles.primaryButtonText}>Send appeal</Text>}
                      </Pressable>
                    </View>
                  ) : (
                    <Text style={styles.formSubheading}>Your appeal is being reviewed. You can submit another appeal after this one has been resolved.</Text>
                  )}
                  <Pressable disabled={busy} onPress={() => void signOut()} style={[styles.primaryButton, { backgroundColor: COLORS.paleNeutral }]}>
                    <Text style={[styles.primaryButtonText, { color: COLORS.navy }]}>Log out</Text>
                  </Pressable>
                </>
              )}
            </ScrollView>
          </KeyboardAvoidingView>
        )}
      </View>
    </SafeAreaView>
  );
}

function formatStatus(status: string): string {
  return status.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}
