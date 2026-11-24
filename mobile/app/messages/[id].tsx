import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useCallback, useRef, useState } from 'react';
import {
  ActivityIndicator,
  AppState,
  Image,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  RefreshControl,
  ScrollView,
  Text,
  TextInput,
  View,
} from 'react-native';
import type { ScrollViewInstance } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { COLORS, styles as appStyles } from '../../App';
import {
  ChatMessage,
  clearAuthToken,
  ConversationThread,
  getAuthToken,
  PresenceStatus,
  requestJson,
} from '../../src/lib/api';
import { MenuButton } from '../../src/components/NavigationDrawer';
import { createThemedStyles, useAppTheme } from '../../src/lib/theme';

function formatMessageDate(value: string): string {
  const date = new Date(value);
  return Number.isNaN(date.getTime())
    ? ''
    : date.toLocaleString('en-PH', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
      });
}

function mergeConversationMessages(
  current: ConversationThread,
  updated: ConversationThread,
): ConversationThread {
  const updatedMessages = new Map(updated.messages.map((message) => [message.id, message]));
  const currentMessageIds = new Set(current.messages.map((message) => message.id));
  const messages = current.messages.map((message) => {
    const refreshedMessage = updatedMessages.get(message.id);
    return refreshedMessage ? { ...message, ...refreshedMessage } : message;
  });

  return {
    ...updated,
    messages: [
      ...messages,
      ...updated.messages.filter((message) => !currentMessageIds.has(message.id)),
    ],
  };
}

function ProfileAvatar({ url, name, size }: { url: string | null; name: string; size: number }) {
  const avatarSize = { width: size, height: size, borderRadius: size / 2 };

  return url ? (
    <Image source={{ uri: url }} style={avatarSize} />
  ) : (
    <View style={[avatarSize, screenStyles.messageAvatarFallback]}>
      <Text style={screenStyles.messageAvatarInitial}>
        {name.trim().slice(0, 1).toUpperCase() || '?'}
      </Text>
    </View>
  );
}

function presenceLabel(status: PresenceStatus): string {
  return status.charAt(0).toUpperCase() + status.slice(1);
}

export default function ConversationScreen() {
  const { mode } = useAppTheme();
  const { id: rawId } = useLocalSearchParams<{ id: string }>();
  const id = Array.isArray(rawId) ? rawId[0] : rawId;
  const [conversation, setConversation] = useState<ConversationThread | null>(null);
  const [body, setBody] = useState('');
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [sending, setSending] = useState(false);
  const [error, setError] = useState('');
  const [liveError, setLiveError] = useState('');
  const scrollView = useRef<ScrollViewInstance | null>(null);

  const loadConversation = useCallback(async (): Promise<boolean> => {
    setError('');

    try {
      if (!await getAuthToken()) {
        router.replace({ pathname: '/account', params: { returnTo: 'messages' } });
        return false;
      }

      const result = await requestJson<ConversationThread>(`/conversations/${encodeURIComponent(id)}`);
      setConversation((current) => current ? mergeConversationMessages(current, result.data) : result.data);
      setLiveError('');
      return true;
    } catch (issue) {
      const detail = issue instanceof Error ? issue.message : 'This conversation could not be loaded.';
      if (detail.includes('(401)') || detail.includes('(Unauthenticated')) {
        await clearAuthToken();
        router.replace({ pathname: '/account', params: { returnTo: 'conversation', conversationId: id } });
        return false;
      }
      setError(detail);
      return false;
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [id]);

  const pollConversation = useCallback(async (): Promise<boolean> => {
    try {
      if (!await getAuthToken()) {
        router.replace({ pathname: '/account', params: { returnTo: 'messages' } });
        return false;
      }

      const result = await requestJson<ConversationThread>(`/conversations/${encodeURIComponent(id)}`);
      setConversation((current) => current ? mergeConversationMessages(current, result.data) : result.data);
      setLiveError('');
      return true;
    } catch (issue) {
      const detail = issue instanceof Error ? issue.message : 'New messages could not be checked.';
      if (detail.includes('(401)') || detail.includes('(Unauthenticated')) {
        await clearAuthToken();
        router.replace({ pathname: '/account', params: { returnTo: 'conversation', conversationId: id } });
        return false;
      }
      setLiveError(detail);
      return false;
    }
  }, [id]);

  useFocusEffect(
    useCallback(() => {
      let isFocused = true;
      let isForeground = AppState.currentState !== 'background' && AppState.currentState !== 'inactive';
      let isRequestInFlight = false;
      let hasLoadedConversation = false;

      const refresh = async () => {
        if (!isFocused || !isForeground || isRequestInFlight) return;

        isRequestInFlight = true;
        try {
          if (hasLoadedConversation) {
            await pollConversation();
          } else {
            hasLoadedConversation = await loadConversation();
          }
        } finally {
          isRequestInFlight = false;
        }
      };

      void refresh();
      const interval = setInterval(() => void refresh(), 3000);
      const appStateSubscription = AppState.addEventListener('change', (nextState) => {
        isForeground = nextState === 'active';
        if (isForeground) void refresh();
      });

      return () => {
        isFocused = false;
        clearInterval(interval);
        appStateSubscription.remove();
      };
    }, [loadConversation, pollConversation]),
  );

  const sendMessage = async () => {
    const messageBody = body.trim();
    if (!conversation || !messageBody) {
      setError('Write a message before sending.');
      return;
    }

    setSending(true);
    setError('');
    try {
      const result = await requestJson<ChatMessage>(`/conversations/${conversation.id}/messages`, {
        method: 'POST',
        body: JSON.stringify({ body: messageBody }),
      });
      setConversation((current) => {
        if (!current || current.messages.some((message) => message.id === result.data.id)) {
          return current;
        }

        return { ...current, messages: [...current.messages, result.data] };
      });
      setBody('');
    } catch (issue) {
      setError(issue instanceof Error ? issue.message : 'Your message could not be sent.');
    } finally {
      setSending(false);
    }
  };

  const lastSeenMessageId = conversation?.messages.reduce((latestId, message) => {
    const ownMessage = message.sender_id !== conversation.other_participant.id;
    return ownMessage && message.read_at ? Math.max(latestId, message.id) : latestId;
  }, 0) ?? 0;

  return (
    <SafeAreaView edges={['top', 'bottom']} style={appStyles.safeArea}>
      <View style={appStyles.app}>
        <StatusBar style={mode === 'dark' ? 'light' : 'dark'} />
        <View style={appStyles.header}>
          <Pressable onPress={() => router.back()} style={appStyles.backButton}>
            <Text style={appStyles.backButtonText}>‹  Back</Text>
          </Pressable>
          <Text numberOfLines={1} style={appStyles.headerRouteTitle}>
            {conversation?.other_participant.name ?? 'Conversation'}
          </Text>
          <MenuButton />
        </View>

        {loading ? (
          <View style={appStyles.loadingState}>
            <ActivityIndicator color={COLORS.goldDark} />
            <Text style={appStyles.helperText}>Opening your conversation...</Text>
          </View>
        ) : error && !conversation ? (
          <View style={appStyles.emptyState}>
            <Text style={appStyles.emptyTitle}>Could not open this conversation.</Text>
            <Text style={appStyles.emptyDescription}>{error}</Text>
            <Pressable onPress={() => router.back()} style={appStyles.primaryButton}>
              <Text style={appStyles.primaryButtonText}>Back to messages</Text>
            </Pressable>
          </View>
        ) : conversation ? (
          <KeyboardAvoidingView
            behavior={Platform.OS === 'ios' ? 'padding' : undefined}
            style={appStyles.contentArea}
          >
            <View style={screenStyles.listingHeader}>
              {conversation.other_participant.avatar_url ? (
                <Image
                  source={{ uri: conversation.other_participant.avatar_url }}
                  style={screenStyles.avatar}
                />
              ) : (
                <View style={screenStyles.avatarPlaceholder}>
                  <Text style={screenStyles.avatarText}>
                    {conversation.other_participant.name.slice(0, 1).toUpperCase()}
                  </Text>
                </View>
              )}
              <View style={screenStyles.listingCopy}>
                <Text style={screenStyles.sellerName}>{conversation.other_participant.name}</Text>
                <View style={screenStyles.presenceLine}>
                  <View
                    style={[
                      screenStyles.presenceDot,
                      conversation.other_participant.presence_status === 'online'
                        ? screenStyles.presenceOnline
                        : conversation.other_participant.presence_status === 'away'
                          ? screenStyles.presenceAway
                          : screenStyles.presenceOffline,
                    ]}
                  />
                  <Text style={screenStyles.presenceText}>
                    {presenceLabel(conversation.other_participant.presence_status)}
                  </Text>
                </View>
                <Text numberOfLines={1} style={screenStyles.listingTitle}>
                  {conversation.listing.title}
                </Text>
              </View>
              <Text style={screenStyles.listingType}>
                {conversation.listing.type === 'rent' ? 'RENT' : 'SALE'}
              </Text>
            </View>

            <ScrollView
              ref={scrollView}
              contentContainerStyle={screenStyles.thread}
              keyboardShouldPersistTaps="handled"
              onContentSizeChange={() => scrollView.current?.scrollToEnd({ animated: true })}
              refreshControl={
                <RefreshControl
                  colors={[COLORS.goldDark]}
                  onRefresh={() => {
                    setRefreshing(true);
                    void loadConversation();
                  }}
                  refreshing={refreshing}
                  tintColor={COLORS.goldDark}
                />
              }
              showsVerticalScrollIndicator={false}
            >
              {conversation.messages.length ? conversation.messages.map((message) => {
                const ownMessage = message.sender_id !== conversation.other_participant.id;
                return (
                  <View key={message.id} style={[screenStyles.messageRow, ownMessage && screenStyles.ownMessageRow]}>
                    {!ownMessage ? (
                      <ProfileAvatar
                        name={message.sender_name}
                        size={28}
                        url={message.sender_avatar_url}
                      />
                    ) : null}
                    <View style={screenStyles.messageContent}>
                      <View style={[screenStyles.message, ownMessage && screenStyles.ownMessage]}>
                        <Text style={[screenStyles.messageBody, ownMessage && screenStyles.ownMessageBody]}>
                          {message.body}
                        </Text>
                        <Text style={[screenStyles.messageDate, ownMessage && screenStyles.ownMessageDate]}>
                          {formatMessageDate(message.created_at)}
                        </Text>
                      </View>
                      {ownMessage && message.id === lastSeenMessageId ? (
                        <View style={screenStyles.seenReceipt}>
                          <ProfileAvatar
                            name={conversation.other_participant.name}
                            size={17}
                            url={conversation.other_participant.avatar_url}
                          />
                          <Text style={screenStyles.seenText}>Seen</Text>
                        </View>
                      ) : null}
                    </View>
                    {ownMessage ? (
                      <ProfileAvatar
                        name={message.sender_name}
                        size={28}
                        url={message.sender_avatar_url}
                      />
                    ) : null}
                  </View>
                );
              }) : (
                <View style={appStyles.emptyState}>
                  <Text style={appStyles.emptyTitle}>Your private chat starts here.</Text>
                  <Text style={appStyles.emptyDescription}>
                    Say hello and work out the details directly.
                  </Text>
                </View>
              )}
            </ScrollView>

            {liveError ? <Text style={appStyles.formError}>{`Live updates paused: ${liveError}`}</Text> : null}
            {error ? <Text style={appStyles.formError}>{error}</Text> : null}
            <View style={screenStyles.compose}>
              <TextInput
                accessibilityLabel="Write a message"
                editable={!sending}
                maxLength={2000}
                multiline
                onChangeText={setBody}
                onSubmitEditing={() => void sendMessage()}
                placeholder="Write a message..."
                placeholderTextColor={COLORS.muted}
                style={screenStyles.composeInput}
                value={body}
              />
              <Pressable
                accessibilityLabel="Send message"
                accessibilityRole="button"
                disabled={sending || !body.trim()}
                onPress={() => void sendMessage()}
                style={[screenStyles.sendButton, (sending || !body.trim()) && screenStyles.sendDisabled]}
              >
                {sending ? (
                  <ActivityIndicator color={COLORS.white} />
                ) : (
                  <Text style={screenStyles.sendButtonText}>Send</Text>
                )}
              </Pressable>
            </View>
            <Text style={screenStyles.hint}>Meet in a public campus spot and agree on the details before paying or borrowing.</Text>
          </KeyboardAvoidingView>
        ) : null}
      </View>
    </SafeAreaView>
  );
}

const screenStyles = createThemedStyles({
  listingHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    padding: 13,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.line,
    backgroundColor: COLORS.white,
  },
  avatar: { width: 38, height: 38, borderRadius: 19 },
  avatarPlaceholder: {
    width: 38,
    height: 38,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 19,
    backgroundColor: COLORS.paleGold,
  },
  avatarText: { color: COLORS.navy, fontSize: 15, fontWeight: '700' },
  listingCopy: { flex: 1, minWidth: 0 },
  sellerName: { color: COLORS.navy, fontSize: 13, fontWeight: '700' },
  presenceLine: { flexDirection: 'row', alignItems: 'center', gap: 5, marginTop: 2 },
  presenceDot: { width: 7, height: 7, borderRadius: 4 },
  presenceOnline: { backgroundColor: '#25834b' },
  presenceAway: { backgroundColor: '#c18a24' },
  presenceOffline: { backgroundColor: '#8a8177' },
  presenceText: { color: COLORS.muted, fontSize: 11 },
  listingTitle: { marginTop: 2, color: COLORS.muted, fontSize: 12 },
  listingType: { color: COLORS.goldDark, fontSize: 11, fontWeight: '800' },
  thread: { flexGrow: 1, justifyContent: 'flex-end', gap: 10, padding: 16 },
  messageRow: { width: '100%', flexDirection: 'row', alignItems: 'flex-end', gap: 7 },
  ownMessageRow: { justifyContent: 'flex-end' },
  messageContent: { maxWidth: '84%', minWidth: 0, flexShrink: 1 },
  messageAvatarFallback: {
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: COLORS.paleGold,
  },
  messageAvatarInitial: { color: COLORS.navy, fontSize: 10, fontWeight: '700' },
  message: {
    alignSelf: 'flex-start',
    paddingHorizontal: 13,
    paddingVertical: 10,
    borderRadius: 14,
    backgroundColor: COLORS.white,
  },
  ownMessage: { alignSelf: 'flex-end', backgroundColor: COLORS.navy },
  messageBody: { color: COLORS.ink, fontSize: 13, lineHeight: 19 },
  ownMessageBody: { color: COLORS.white },
  messageDate: { alignSelf: 'flex-end', marginTop: 4, color: COLORS.muted, fontSize: 11 },
  ownMessageDate: { color: '#e5d6c8' },
  seenReceipt: { flexDirection: 'row', alignItems: 'center', justifyContent: 'flex-end', gap: 5, marginTop: 5 },
  seenText: { color: COLORS.muted, fontSize: 10 },
  compose: {
    flexDirection: 'row',
    alignItems: 'flex-end',
    gap: 9,
    padding: 12,
    borderTopWidth: 1,
    borderTopColor: COLORS.line,
    backgroundColor: COLORS.white,
  },
  composeInput: {
    flex: 1,
    maxHeight: 110,
    minHeight: 48,
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderWidth: 1,
    borderColor: COLORS.line,
    borderRadius: 12,
    color: COLORS.ink,
    fontSize: 13,
  },
  sendButton: {
    minHeight: 48,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 16,
    borderRadius: 12,
    backgroundColor: COLORS.navy,
  },
  sendDisabled: { opacity: 0.55 },
  sendButtonText: { color: COLORS.white, fontSize: 12, fontWeight: '700' },
  hint: { paddingHorizontal: 14, paddingBottom: 8, color: COLORS.muted, fontSize: 12, lineHeight: 17, textAlign: 'center' },
});
