import { router, useFocusEffect } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useCallback, useState } from 'react';
import {
  ActivityIndicator,
  Image,
  Pressable,
  RefreshControl,
  ScrollView,
  Text,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { COLORS, styles as appStyles } from '../../App';
import {
  clearAuthToken,
  Conversation,
  getAuthToken,
  requestJson,
} from '../../src/lib/api';
import { MenuButton } from '../../src/components/NavigationDrawer';
import { createThemedStyles, useAppTheme } from '../../src/lib/theme';

function formatMessageTime(value: string | undefined): string {
  if (!value) return '';

  const date = new Date(value);
  return Number.isNaN(date.getTime())
    ? ''
    : date.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });
}

export default function MessagesScreen() {
  const { mode } = useAppTheme();
  const [conversations, setConversations] = useState<Conversation[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState('');

  const loadConversations = useCallback(async () => {
    setError('');

    try {
      if (!await getAuthToken()) {
        router.replace({ pathname: '/account', params: { returnTo: 'messages' } });
        return;
      }

      const result = await requestJson<Conversation[]>('/conversations');
      setConversations(result.data);
    } catch (issue) {
      const detail = issue instanceof Error ? issue.message : 'Your messages could not be loaded.';
      if (detail.includes('(401)') || detail.includes('(Unauthenticated')) {
        await clearAuthToken();
        router.replace({ pathname: '/account', params: { returnTo: 'messages' } });
        return;
      }
      setError(detail);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      let isFocused = true;
      let isLoading = false;
      const refreshConversations = async () => {
        if (!isFocused || isLoading) return;

        isLoading = true;
        try {
          await loadConversations();
        } finally {
          isLoading = false;
        }
      };

      void refreshConversations();
      const interval = setInterval(() => void refreshConversations(), 30 * 1000);

      return () => {
        isFocused = false;
        clearInterval(interval);
      };
    }, [loadConversations]),
  );

  return (
    <SafeAreaView edges={['top', 'bottom']} style={appStyles.safeArea}>
      <View style={appStyles.app}>
        <StatusBar style={mode === 'dark' ? 'light' : 'dark'} />
        <View style={appStyles.header}>
          <Pressable onPress={() => router.back()} style={appStyles.backButton}>
            <Text style={appStyles.backButtonText}>‹  Back</Text>
          </Pressable>
          <Text style={appStyles.headerRouteTitle}>Your messages</Text>
          <MenuButton />
        </View>

        {loading ? (
          <View style={appStyles.loadingState}>
            <ActivityIndicator color={COLORS.goldDark} />
            <Text style={appStyles.helperText}>Opening your conversations...</Text>
          </View>
        ) : error ? (
          <View style={appStyles.emptyState}>
            <Text style={appStyles.emptyTitle}>Could not load your messages.</Text>
            <Text style={appStyles.emptyDescription}>{error}</Text>
            <Pressable onPress={() => void loadConversations()} style={appStyles.primaryButton}>
              <Text style={appStyles.primaryButtonText}>Try again</Text>
            </Pressable>
          </View>
        ) : (
          <ScrollView
            contentContainerStyle={screenStyles.content}
            refreshControl={
              <RefreshControl
                colors={[COLORS.goldDark]}
                onRefresh={() => {
                  setRefreshing(true);
                  void loadConversations();
                }}
                refreshing={refreshing}
                tintColor={COLORS.goldDark}
              />
            }
            showsVerticalScrollIndicator={false}
          >
            <Text style={screenStyles.intro}>
              Private conversations about the school finds you’re buying or renting.
            </Text>
            {conversations.length ? conversations.map((conversation) => (
              <Pressable
                accessibilityRole="button"
                key={conversation.id}
                onPress={() => router.push({
                  pathname: '/messages/[id]',
                  params: { id: String(conversation.id) },
                })}
                style={({ pressed }) => [screenStyles.row, pressed && screenStyles.rowPressed]}
              >
                {conversation.other_participant.avatar_url ? (
                  <Image source={{ uri: conversation.other_participant.avatar_url }} style={screenStyles.avatar} />
                ) : (
                  <View style={screenStyles.avatarPlaceholder}>
                    <Text style={screenStyles.avatarText}>
                      {conversation.other_participant.name.slice(0, 1).toUpperCase()}
                    </Text>
                  </View>
                )}
                <View style={screenStyles.copy}>
                  <View style={screenStyles.topline}>
                    <View style={screenStyles.conversationIdentity}>
                      <Text numberOfLines={1} style={screenStyles.name}>
                        {conversation.other_participant.name}
                      </Text>
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
                          {conversation.other_participant.presence_status.charAt(0).toUpperCase()
                            + conversation.other_participant.presence_status.slice(1)}
                        </Text>
                      </View>
                    </View>
                    <Text style={screenStyles.time}>
                      {formatMessageTime(conversation.latest_message?.created_at)}
                    </Text>
                  </View>
                  <Text numberOfLines={1} style={screenStyles.listing}>
                    {conversation.listing.title}
                  </Text>
                  <Text numberOfLines={2} style={screenStyles.preview}>
                    {conversation.latest_message?.body ?? 'Open the conversation'}
                  </Text>
                </View>
                {conversation.unread_messages_count > 0 ? (
                  <View style={screenStyles.unreadBadge}>
                    <Text style={screenStyles.unreadText}>{conversation.unread_messages_count}</Text>
                  </View>
                ) : null}
              </Pressable>
            )) : (
              <View style={appStyles.emptyState}>
                <Text style={appStyles.emptyIcon}>✉</Text>
                <Text style={appStyles.emptyTitle}>No conversations yet.</Text>
                <Text style={appStyles.emptyDescription}>
                  Open a school find and message its seller to talk through the details privately.
                </Text>
                <Pressable onPress={() => router.replace('/')} style={appStyles.primaryButton}>
                  <Text style={appStyles.primaryButtonText}>Browse school finds</Text>
                </Pressable>
              </View>
            )}
          </ScrollView>
        )}
      </View>
    </SafeAreaView>
  );
}

const screenStyles = createThemedStyles({
  content: { flexGrow: 1, padding: 18 },
  intro: { marginBottom: 16, color: COLORS.muted, fontSize: 14, lineHeight: 21 },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    marginBottom: 10,
    padding: 13,
    borderWidth: 1,
    borderColor: COLORS.line,
    borderRadius: 13,
    backgroundColor: COLORS.white,
  },
  rowPressed: { opacity: 0.75 },
  avatar: { width: 44, height: 44, borderRadius: 22 },
  avatarPlaceholder: {
    width: 44,
    height: 44,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 22,
    backgroundColor: COLORS.paleGold,
  },
  avatarText: { color: COLORS.navy, fontSize: 17, fontWeight: '700' },
  copy: { flex: 1, minWidth: 0 },
  topline: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 8 },
  conversationIdentity: { flex: 1, minWidth: 0, gap: 3 },
  name: { color: COLORS.navy, fontSize: 14, fontWeight: '700' },
  presenceLine: { flexDirection: 'row', alignItems: 'center', gap: 5 },
  presenceDot: { width: 7, height: 7, borderRadius: 4 },
  presenceOnline: { backgroundColor: '#25834b' },
  presenceAway: { backgroundColor: '#c18a24' },
  presenceOffline: { backgroundColor: '#8a8177' },
  presenceText: { color: COLORS.muted, fontSize: 10 },
  time: { color: COLORS.muted, fontSize: 11 },
  listing: { marginTop: 3, color: COLORS.goldDark, fontSize: 12, fontWeight: '700' },
  preview: { marginTop: 3, color: COLORS.muted, fontSize: 12, lineHeight: 18 },
  unreadBadge: {
    minWidth: 22,
    height: 22,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 5,
    borderRadius: 11,
    backgroundColor: COLORS.navy,
  },
  unreadText: { color: COLORS.white, fontSize: 11, fontWeight: '700' },
});
