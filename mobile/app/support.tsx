import * as ImagePicker from 'expo-image-picker';
import { ImageManipulator, SaveFormat } from 'expo-image-manipulator';
import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';
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
import { clearAuthToken, getAuthToken, requestJson } from '../src/lib/api';
import { MenuButton } from '../src/components/NavigationDrawer';

const REPORT_CATEGORIES = [
  { value: 'inappropriate_content', label: 'Inappropriate content' },
  { value: 'scam', label: 'Scam or fraud' },
  { value: 'harassment', label: 'Harassment or bullying' },
  { value: 'other', label: 'Other' },
] as const;

type ReportCategory = (typeof REPORT_CATEGORIES)[number]['value'];

type SupportReport = {
  id: number;
  category_label: string;
  details: string;
  status: string;
  proof_count: number;
  admin_note: string | null;
  created_at: string;
};

const MAX_PROOF_IMAGE_BYTES = 4 * 1024 * 1024;
const MAX_TOTAL_PROOF_BYTES = 12 * 1024 * 1024;

export default function SupportScreen() {
  const { reportedUserId, reportedUserName } = useLocalSearchParams<{
    reportedUserId?: string;
    reportedUserName?: string;
  }>();
  const [reportedEmail, setReportedEmail] = useState('');
  const [category, setCategory] = useState<ReportCategory>('inappropriate_content');
  const [details, setDetails] = useState('');
  const [proofs, setProofs] = useState<ImagePicker.ImagePickerAsset[]>([]);
  const [reports, setReports] = useState<SupportReport[]>([]);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');

  const loadReports = useCallback(async () => {
    if (!await getAuthToken()) {
      router.replace({ pathname: '/account', params: { returnTo: 'support' } });
      return;
    }

    try {
      const result = await requestJson<SupportReport[]>('/support/reports');
      setReports(result.data);
    } catch (issue) {
      const detail = issue instanceof Error ? issue.message : 'Your reports could not be loaded.';
      if (detail.includes('(401)') || detail.includes('(Unauthenticated')) {
        await clearAuthToken();
        router.replace({ pathname: '/account', params: { returnTo: 'support' } });
        return;
      }
      setError(detail);
    }
  }, []);

  useFocusEffect(useCallback(() => {
    void loadReports();
  }, [loadReports]));

  const pickProofs = async () => {
    if (proofs.length === 3) return;

    const permission = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!permission.granted) {
      Alert.alert('Photo access needed', 'Allow Pasabuy to access selected photos for your support report.');
      return;
    }

    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ['images'],
      allowsMultipleSelection: true,
      selectionLimit: 3 - proofs.length,
      quality: 0.8,
    });

    if (!result.canceled) {
      setProofs((current) => [...current, ...result.assets].slice(0, 3));
    }
  };

  const submitReport = async () => {
    setBusy(true);
    setError('');
    setMessage('');

    try {
      const form = new FormData();
      if (reportedUserId) {
        form.append('reported_user_id', reportedUserId);
      } else {
        form.append('reported_email', reportedEmail.trim());
      }
      form.append('category', category);
      form.append('details', details.trim());

      let totalProofBytes = 0;
      for (const [index, proof] of proofs.entries()) {
        if (Platform.OS === 'web') {
          if (!proof.file) {
            throw new Error('A selected image could not be read. Please choose it again.');
          }

          if (proof.file.size > MAX_PROOF_IMAGE_BYTES) {
            throw new Error('Each proof image must be 4 MB or smaller.');
          }

          totalProofBytes += proof.file.size;
          if (totalProofBytes > MAX_TOTAL_PROOF_BYTES) {
            throw new Error('Proof images must total 12 MB or less.');
          }

          form.append(`proofs[${index}]`, proof.file);
          continue;
        }

        const resizeScale = Math.min(1, 1600 / proof.width, 1600 / proof.height);
        const resizedImage = await ImageManipulator.manipulate(proof.uri)
          .resize({
            width: Math.max(1, Math.round(proof.width * resizeScale)),
            height: Math.max(1, Math.round(proof.height * resizeScale)),
          })
          .renderAsync();
        const uploadImage = await resizedImage.saveAsync({
          compress: 0.7,
          format: SaveFormat.JPEG,
          base64: true,
        });

        if (!uploadImage.base64) {
          throw new Error('A proof image could not be prepared. Please choose it again.');
        }

        const imageSize = Math.ceil(uploadImage.base64.length * 3 / 4);
        if (imageSize > MAX_PROOF_IMAGE_BYTES) {
          throw new Error('A proof image is still too large after compression. Please choose a smaller photo.');
        }

        totalProofBytes += imageSize;
        if (totalProofBytes > MAX_TOTAL_PROOF_BYTES) {
          throw new Error('Proof images must total 12 MB or less.');
        }

        form.append(`proofs[${index}]`, {
          uri: uploadImage.uri,
          name: `support-proof-${index + 1}.jpg`,
          type: 'image/jpeg',
        } as unknown as Blob);
      }

      const result = await requestJson<SupportReport>('/support/reports', {
        method: 'POST',
        body: form,
      }, 90_000);

      setReports((current) => [result.data, ...current]);
      setReportedEmail('');
      setDetails('');
      setProofs([]);
      setMessage(result.message ?? 'Your report has been sent to customer support.');
    } catch (issue) {
      setError(issue instanceof Error ? issue.message : 'Your report could not be submitted.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <SafeAreaView edges={['top', 'bottom']} style={styles.safeArea}>
      <View style={styles.app}>
        <StatusBar style="dark" />
        <View style={styles.header}>
          <Pressable onPress={() => router.back()} style={styles.backButton}>
            <Text style={styles.backButtonText}>‹  Back</Text>
          </Pressable>
          <Text style={styles.headerRouteTitle}>Customer support</Text>
          <MenuButton />
        </View>
        <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={styles.contentArea}>
          <ScrollView contentContainerStyle={{ padding: 20, gap: 16 }} keyboardShouldPersistTaps="handled">
            {error ? <Text style={styles.formError}>{error}</Text> : null}
            {message ? <Text style={styles.formSubheading}>{message}</Text> : null}

            <View style={styles.formCard}>
              <Text style={styles.formHeading}>Report an account</Text>
              <Text style={styles.formSubheading}>A support admin reviews every report. Submitting one does not automatically suspend an account.</Text>

              {reportedUserId ? (
                <Text style={[styles.formSubheading, { marginTop: 14 }]}>
                  Reporting account: {reportedUserName ?? `Account #${reportedUserId}`}
                </Text>
              ) : (
                <>
                  <Text style={styles.fieldLabel}>Reported account email</Text>
                  <TextInput autoCapitalize="none" keyboardType="email-address" onChangeText={setReportedEmail} placeholder="person@example.com" style={styles.formInput} value={reportedEmail} />
                </>
              )}

              <Text style={styles.fieldLabel}>What are you reporting?</Text>
              <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginTop: 7 }}>
                {REPORT_CATEGORIES.map((item) => {
                  const selected = category === item.value;
                  return (
                    <Pressable
                      accessibilityRole="button"
                      accessibilityState={{ selected }}
                      key={item.value}
                      onPress={() => setCategory(item.value)}
                      style={{
                        borderWidth: 1,
                        borderColor: selected ? COLORS.goldDark : COLORS.line,
                        borderRadius: 18,
                        backgroundColor: selected ? COLORS.paleGold : '#fff',
                        paddingVertical: 8,
                        paddingHorizontal: 12,
                      }}
                    >
                      <Text style={{ color: selected ? COLORS.navy : COLORS.muted, fontSize: 12 }}>{item.label}</Text>
                    </Pressable>
                  );
                })}
              </View>

              <Text style={styles.fieldLabel}>What happened?</Text>
              <TextInput maxLength={5000} multiline onChangeText={setDetails} placeholder="Include relevant dates, messages, or transaction details." style={[styles.formInput, styles.multilineInput]} value={details} />

              <Text style={styles.fieldLabel}>Image proof (up to 3, 4 MB each)</Text>
              <Pressable disabled={busy || proofs.length >= 3} onPress={() => void pickProofs()} style={[styles.primaryButton, { backgroundColor: COLORS.paleNeutral, marginTop: 8 }]}>
                <Text style={[styles.primaryButtonText, { color: COLORS.navy }]}>{proofs.length ? `Add proof images (${proofs.length}/3)` : 'Choose proof images'}</Text>
              </Pressable>
              {proofs.length ? (
                <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 10, marginTop: 12 }}>
                  {proofs.map((proof, index) => (
                    <View key={`${proof.uri}-${index}`} style={{ position: 'relative' }}>
                      <Image source={{ uri: proof.uri }} style={{ width: 76, height: 76, borderRadius: 8 }} />
                      <Pressable
                        accessibilityLabel={`Remove proof image ${index + 1}`}
                        onPress={() => setProofs((current) => current.filter((_, proofIndex) => proofIndex !== index))}
                        style={{ position: 'absolute', top: -8, right: -8, width: 32, height: 32, borderRadius: 16, backgroundColor: COLORS.navy, alignItems: 'center', justifyContent: 'center' }}
                      >
                        <Text style={{ color: '#fff', fontWeight: '700' }}>×</Text>
                      </Pressable>
                    </View>
                  ))}
                </View>
              ) : null}

              <Pressable disabled={busy} onPress={() => void submitReport()} style={[styles.primaryButton, busy && styles.disabledButton, { marginTop: 16 }]}>
                {busy ? <ActivityIndicator color="#fff" /> : <Text style={styles.primaryButtonText}>Send report to support</Text>}
              </Pressable>
            </View>

            <View style={styles.formCard}>
              <Text style={styles.formHeading}>Your reports</Text>
              {reports.length ? reports.map((report) => (
                <View key={report.id} style={{ borderTopWidth: 1, borderTopColor: COLORS.line, paddingVertical: 14 }}>
                  <Text style={{ color: COLORS.navy, fontWeight: '700' }}>{report.category_label} · {report.status}</Text>
                  <Text style={{ color: COLORS.muted, fontSize: 12, lineHeight: 18, marginTop: 5 }}>{report.details}</Text>
                  <Text style={{ color: COLORS.muted, fontSize: 11, marginTop: 6 }}>
                    {new Date(report.created_at).toLocaleDateString()} · {report.proof_count} proof image(s)
                  </Text>
                  {report.admin_note ? <Text style={{ color: COLORS.goldDark, fontSize: 12, marginTop: 8 }}>Support update: {report.admin_note}</Text> : null}
                </View>
              )) : <Text style={styles.formSubheading}>Your submitted reports will appear here.</Text>}
            </View>
          </ScrollView>
        </KeyboardAvoidingView>
      </View>
    </SafeAreaView>
  );
}
