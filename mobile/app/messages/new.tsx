import { router, useLocalSearchParams } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { COLORS, styles as appStyles } from '../../App';
import {
  clearAuthToken,
  Conversation,
  getAuthToken,
  Listing,
  requestJson,
} from '../../src/lib/api';

export default function NewMessageScreen() {
  const { listingSlug: rawListingSlug } = useLocalSearchParams<{ listingSlug?: string }>();
  const listingSlug = Array.isArray(rawListingSlug) ? rawListingSlug[0] : rawListingSlug;
  const [listing, setListing] = useState<Listing | null>(null);
  const [body, setBody] = useState('');
  const [loading, setLoading] = useState(true);
  const [sending, setSending] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    let active = true;

    void (async () => {
      if (!await getAuthToken()) {
        router.replace({ pathname: '/account', params: { returnTo: 'messages' } });
        return;
      }
      if (!listingSlug) {
        if (active) {
          setError('Choose a listing before starting a conversation.');
          setLoading(false);
        }
        return;
      }

      try {
        const result = await requestJson<Listing>(`/listings/${encodeURIComponent(listingSlug)}`);
        if (active) setListing(result.data);
      } catch (issue) {
        const detail = issue instanceof Error ? issue.message : 'This listing could not be opened.';
        if (detail.includes('(401)') || detail.includes('(Unauthenticated')) {
          await clearAuthToken();
          router.replace({ pathname: '/account', params: { returnTo: 'messages' } });
          return;
        }
        if (active) setError(detail);
      } finally {
        if (active) setLoading(false);
      }
    })();

    return () => {
      active = false;
    };
  }, [listingSlug]);

  const sendMessage = async () => {
    if (!listing || !listingSlug || !body.trim()) {
      setError('Write a message before sending.');
      return;
    }

    setSending(true);
    setError('');
    try {
      const result = await requestJson<Conversation>(
        `/listings/${encodeURIComponent(listingSlug)}/messages`,
        { method: 'POST', body: JSON.stringify({ body: body.trim() }) },
      );
      router.replace({
        pathname: '/messages/[id]',
        params: { id: String(result.data.id) },
      });
    } catch (issue) {
      setError(issue instanceof Error ? issue.message : 'Your message could not be sent.');
    } finally {
      setSending(false);
    }
  };

  return (
    <SafeAreaView edges={['top', 'bottom']} style={appStyles.safeArea}>
      <View style={appStyles.app}>
        <StatusBar style="light" />
        <View style={appStyles.topBar}>
          <Text style={appStyles.topBarText}>✳  START A CONVERSATION</Text>
          <Text style={appStyles.topBarLocation}>MESSAGE SELLER</Text>
        </View>
        <View style={appStyles.header}>
          <Pressable onPress={() => router.back()} style={appStyles.backButton}>
            <Text style={appStyles.backButtonText}>‹  Back</Text>
          </Pressable>
          <Text style={appStyles.headerRouteTitle}>Message seller</Text>
          <View style={appStyles.routeHeaderSpacer} />
        </View>

        {loading ? (
          <View style={appStyles.loadingState}>
            <ActivityIndicator color={COLORS.goldDark} />
            <Text style={appStyles.helperText}>Opening this campus find...</Text>
          </View>
        ) : (
          <KeyboardAvoidingView
            behavior={Platform.OS === 'ios' ? 'padding' : undefined}
            style={appStyles.contentArea}
          >
            <ScrollView contentContainerStyle={screenStyles.content} keyboardShouldPersistTaps="handled">
              {listing ? (
                <View style={screenStyles.listingCard}>
                  <Text style={screenStyles.eyebrow}>DISCUSSING</Text>
                  <Text style={screenStyles.listingTitle}>{listing.title}</Text>
                  <Text style={screenStyles.listingSubline}>{listing.location}</Text>
                </View>
              ) : null}
              <Text style={screenStyles.prompt}>Say hello or ask about the details.</Text>
              <TextInput
                accessibilityLabel="Write a message"
                maxLength={2000}
                multiline
                onChangeText={setBody}
                placeholder="Ask a question, make an offer, or arrange a time to meet..."
                placeholderTextColor="#9699a4"
                style={screenStyles.messageInput}
                textAlignVertical="top"
                value={body}
              />
              <Text style={screenStyles.hint}>Keep your conversation respectful and meet in a public campus spot.</Text>
              {error ? <Text style={appStyles.formError}>{error}</Text> : null}
              <Pressable
                accessibilityRole="button"
                disabled={sending || !listing}
                onPress={() => void sendMessage()}
                style={[appStyles.primaryButton, (sending || !listing) && appStyles.disabledButton]}
              >
                {sending ? (
                  <ActivityIndicator color={COLORS.white} />
                ) : (
                  <Text style={appStyles.primaryButtonText}>Send message</Text>
                )}
              </Pressable>
            </ScrollView>
          </KeyboardAvoidingView>
        )}
      </View>
    </SafeAreaView>
  );
}

const screenStyles = StyleSheet.create({
  content: { flexGrow: 1, padding: 18 },
  listingCard: {
    padding: 16,
    borderWidth: 1,
    borderColor: COLORS.line,
    borderRadius: 13,
    backgroundColor: COLORS.white,
  },
  eyebrow: { color: COLORS.muted, fontSize: 9, fontWeight: '700', letterSpacing: 1 },
  listingTitle: { marginTop: 5, color: COLORS.navy, fontSize: 16, fontWeight: '700' },
  listingSubline: { marginTop: 4, color: COLORS.muted, fontSize: 12 },
  prompt: { marginTop: 22, marginBottom: 9, color: COLORS.navy, fontSize: 14, fontWeight: '700' },
  messageInput: {
    minHeight: 150,
    padding: 13,
    borderWidth: 1,
    borderColor: COLORS.line,
    borderRadius: 12,
    backgroundColor: COLORS.white,
    color: COLORS.ink,
    fontSize: 14,
    lineHeight: 20,
  },
  hint: { marginTop: 8, marginBottom: 16, color: COLORS.muted, fontSize: 11, lineHeight: 16 },
});
