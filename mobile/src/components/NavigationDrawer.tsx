import { router, usePathname } from 'expo-router';
import { createContext, ReactNode, useCallback, useContext, useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Image,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import {
  clearAuthToken,
  Conversation,
  getAuthToken,
  requestJson,
  UserProfile,
} from '../lib/api';

const COLORS = {
  navy: '#202348',
  navyDeep: '#171a39',
  gold: '#e9aa31',
  cream: '#fcfaf5',
  white: '#fffefa',
  ink: '#292c43',
  muted: '#777d8c',
  line: '#e9e7df',
  paleGold: '#f5efdf',
  paleBlue: '#e9ebf3',
};

const MESSAGE_POLL_INTERVAL_MS = 30_000;

type NavigationDrawerContextValue = {
  openDrawer: () => void;
  unreadCount: number;
};

const NavigationDrawerContext = createContext<NavigationDrawerContextValue | null>(null);

export function useNavigationDrawer(): NavigationDrawerContextValue {
  const context = useContext(NavigationDrawerContext);
  if (!context) {
    throw new Error('useNavigationDrawer must be used within NavigationDrawerProvider.');
  }

  return context;
}

export function MenuButton() {
  const { openDrawer, unreadCount } = useNavigationDrawer();

  return (
    <Pressable
      accessibilityLabel="Open navigation menu"
      accessibilityRole="button"
      onPress={openDrawer}
      style={({ pressed }) => [drawerStyles.menuButton, pressed && drawerStyles.pressed]}
    >
      <Text style={drawerStyles.menuButtonText}>☰</Text>
      {unreadCount > 0 ? (
        <View style={drawerStyles.menuBadge}>
          <Text style={drawerStyles.badgeText}>{unreadCount > 99 ? '99+' : unreadCount}</Text>
        </View>
      ) : null}
    </Pressable>
  );
}

function unreadMessageCount(conversations: Conversation[]): number {
  return conversations.reduce((total, conversation) => total + conversation.unread_messages_count, 0);
}

export function NavigationDrawerProvider({ children }: { children: ReactNode }) {
  const [visible, setVisible] = useState(false);
  const [profile, setProfile] = useState<UserProfile | null>(null);
  const [unreadCount, setUnreadCount] = useState(0);
  const [loadingProfile, setLoadingProfile] = useState(false);
  const [loggingOut, setLoggingOut] = useState(false);
  const [error, setError] = useState('');
  const previousUnreadCounts = useRef<Record<number, number> | null>(null);
  const pathname = usePathname();

  const refreshUnreadCount = useCallback(async (showNewMessageAlert: boolean) => {
    if (!await getAuthToken()) {
      previousUnreadCounts.current = null;
      setUnreadCount(0);
      return;
    }

    const result = await requestJson<Conversation[]>('/conversations');
    const nextUnreadCount = unreadMessageCount(result.data);
    const previousCounts = previousUnreadCounts.current;
    const newMessages = previousCounts === null
      ? 0
      : result.data.reduce(
          (total, conversation) => total + Math.max(
            0,
            conversation.unread_messages_count - (previousCounts[conversation.id] ?? 0),
          ),
          0,
        );

    setUnreadCount(nextUnreadCount);
    if (showNewMessageAlert && newMessages > 0) {
      Alert.alert(
        'New message',
        newMessages === 1 ? 'You have a new message in your inbox.' : `You have ${newMessages} new messages in your inbox.`,
        [
          { text: 'Later', style: 'cancel' },
          {
            text: 'Open inbox',
            onPress: () => {
              setVisible(false);
              router.push('/messages');
            },
          },
        ],
      );
    }

    previousUnreadCounts.current = Object.fromEntries(
      result.data.map((conversation) => [conversation.id, conversation.unread_messages_count]),
    );
  }, []);

  useEffect(() => {
    let active = true;

    const pollForMessages = async (showNewMessageAlert: boolean) => {
      try {
        await refreshUnreadCount(showNewMessageAlert);
        if (active) setError('');
      } catch {
        if (active) setError('Inbox notifications could not be refreshed. Pull down in your inbox to try again.');
      }
    };

    void pollForMessages(false);
    const interval = setInterval(() => {
      void pollForMessages(true);
    }, MESSAGE_POLL_INTERVAL_MS);

    return () => {
      active = false;
      clearInterval(interval);
    };
  }, [refreshUnreadCount]);

  const openDrawer = useCallback(() => {
    setVisible(true);
    setError('');
    setLoadingProfile(true);

    void (async () => {
      try {
        if (!await getAuthToken()) {
          setVisible(false);
          setProfile(null);
          if (pathname !== '/account') router.push('/account');
          return;
        }

        void refreshUnreadCount(false).catch(() => {
          setError('Your inbox could not be refreshed.');
        });
        const result = await requestJson<UserProfile>('/auth/me');
        setProfile(result.data);
      } catch {
        setError('Your profile could not be loaded. Please try again.');
      } finally {
        setLoadingProfile(false);
      }
    })();
  }, [pathname, refreshUnreadCount]);

  const closeDrawer = useCallback(() => {
    setVisible(false);
  }, []);

  const navigate = useCallback((path: '/account' | '/messages' | '/sell' | '/', mode?: 'rent' | 'sale' | 'saved') => {
    closeDrawer();
    if (path === '/' && mode) {
      router.replace(mode === 'saved'
        ? { pathname: '/', params: { view: 'saved' } }
        : { pathname: '/', params: { mode } });
      return;
    }

    router.push(path);
  }, [closeDrawer]);

  const logOut = useCallback(async () => {
    setLoggingOut(true);
    setError('');

    let logoutError = '';
    try {
      await requestJson<never>('/auth/logout', { method: 'POST' });
    } catch (issue) {
      logoutError = issue instanceof Error ? issue.message : 'The server could not end your session.';
    }

    try {
      await clearAuthToken();
      previousUnreadCounts.current = null;
      setUnreadCount(0);
      setProfile(null);
      setVisible(false);
      router.replace('/account');
      if (logoutError) {
        Alert.alert('Signed out on this device', `The server could not be reached to end your session.\n\n${logoutError}`);
      }
    } catch (issue) {
      setError(issue instanceof Error ? issue.message : 'Your session could not be cleared on this device.');
    } finally {
      setLoggingOut(false);
    }
  }, []);

  return (
    <NavigationDrawerContext.Provider value={{ openDrawer, unreadCount }}>
      {children}
      <Modal
        animationType="fade"
        onRequestClose={closeDrawer}
        transparent
        visible={visible}
      >
        <View style={drawerStyles.modal}>
          <Pressable
            accessibilityLabel="Close navigation menu"
            onPress={closeDrawer}
            style={drawerStyles.backdrop}
          />
          <SafeAreaView edges={['top', 'bottom']} style={drawerStyles.panel}>
            <ScrollView contentContainerStyle={drawerStyles.content} showsVerticalScrollIndicator={false}>
              <Pressable
                accessibilityRole="button"
                onPress={() => navigate('/account')}
                style={({ pressed }) => [drawerStyles.profileHeader, pressed && drawerStyles.pressed]}
              >
                {loadingProfile ? (
                  <View style={drawerStyles.avatarPlaceholder}>
                    <ActivityIndicator color={COLORS.gold} />
                  </View>
                ) : profile?.avatar_url ? (
                  <Image source={{ uri: profile.avatar_url }} style={drawerStyles.avatar} />
                ) : (
                  <View style={drawerStyles.avatarPlaceholder}>
                    <Text style={drawerStyles.avatarInitial}>
                      {profile?.name.slice(0, 1).toUpperCase() ?? '☺'}
                    </Text>
                  </View>
                )}
                <View style={drawerStyles.profileCopy}>
                  <Text style={drawerStyles.profileName} numberOfLines={1}>
                    {profile?.name ?? 'Your profile'}
                  </Text>
                  <Text style={drawerStyles.profileAction}>Customize your profile  ›</Text>
                </View>
                <Text style={drawerStyles.profileArrow}>›</Text>
              </Pressable>

              <Text style={drawerStyles.sectionLabel}>YOUR PASABUY</Text>
              <DrawerLink icon="⌕" label="Browse marketplace" onPress={() => navigate('/', 'sale')} />
              <DrawerLink icon="↗" label="Browse rentals" onPress={() => navigate('/', 'rent')} />
              <DrawerLink icon="♡" label="Saved finds" onPress={() => navigate('/', 'saved')} />
              <DrawerLink icon="＋" label="List an item" onPress={() => navigate('/sell')} />
              <DrawerLink
                badge={unreadCount}
                icon="✉"
                label="Message inbox"
                onPress={() => navigate('/messages')}
              />

              {error ? <Text style={drawerStyles.error}>{error}</Text> : null}
            </ScrollView>

            <View style={drawerStyles.footer}>
              <Pressable
                accessibilityRole="button"
                disabled={loggingOut}
                onPress={() => void logOut()}
                style={({ pressed }) => [
                  drawerStyles.logoutButton,
                  pressed && drawerStyles.pressed,
                  loggingOut && drawerStyles.disabled,
                ]}
              >
                {loggingOut ? (
                  <ActivityIndicator color={COLORS.navy} />
                ) : (
                  <>
                    <Text style={drawerStyles.logoutIcon}>⇥</Text>
                    <Text style={drawerStyles.logoutText}>Log out</Text>
                  </>
                )}
              </Pressable>
              <Text style={drawerStyles.footerCaption}>PASABUY · YOUR CAMPUS COMMUNITY</Text>
            </View>
          </SafeAreaView>
        </View>
      </Modal>
    </NavigationDrawerContext.Provider>
  );
}

function DrawerLink({
  badge,
  icon,
  label,
  onPress,
}: {
  badge?: number;
  icon: string;
  label: string;
  onPress: () => void;
}) {
  return (
    <Pressable
      accessibilityRole="button"
      onPress={onPress}
      style={({ pressed }) => [drawerStyles.link, pressed && drawerStyles.pressed]}
    >
      <Text style={drawerStyles.linkIcon}>{icon}</Text>
      <Text style={drawerStyles.linkLabel}>{label}</Text>
      {badge ? (
        <View style={drawerStyles.badge}>
          <Text style={drawerStyles.badgeText}>{badge > 99 ? '99+' : badge}</Text>
        </View>
      ) : null}
      <Text style={drawerStyles.linkArrow}>›</Text>
    </Pressable>
  );
}

const drawerStyles = StyleSheet.create({
  modal: { flex: 1, flexDirection: 'row', backgroundColor: '#171a3980' },
  backdrop: { flex: 1 },
  panel: {
    width: '84%',
    maxWidth: 360,
    height: '100%',
    justifyContent: 'space-between',
    backgroundColor: COLORS.cream,
    elevation: 18,
    shadowColor: '#000',
    shadowOffset: { width: -4, height: 0 },
    shadowOpacity: 0.18,
    shadowRadius: 16,
  },
  content: { flexGrow: 1, padding: 18 },
  profileHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    marginBottom: 28,
    padding: 14,
    borderRadius: 16,
    backgroundColor: COLORS.navyDeep,
  },
  avatar: { width: 52, height: 52, borderWidth: 2, borderColor: COLORS.gold, borderRadius: 26 },
  avatarPlaceholder: {
    width: 52,
    height: 52,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 2,
    borderColor: COLORS.gold,
    borderRadius: 26,
    backgroundColor: COLORS.navy,
  },
  avatarInitial: { color: COLORS.gold, fontSize: 22, fontWeight: '700' },
  profileCopy: { flex: 1, minWidth: 0 },
  profileName: { color: COLORS.white, fontSize: 15, fontWeight: '700' },
  profileAction: { marginTop: 4, color: '#d3d2e0', fontSize: 11 },
  profileArrow: { color: COLORS.gold, fontSize: 24 },
  sectionLabel: {
    marginBottom: 11,
    color: COLORS.muted,
    fontSize: 9,
    fontWeight: '800',
    letterSpacing: 1.2,
  },
  link: {
    minHeight: 52,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 13,
    marginBottom: 5,
    paddingHorizontal: 13,
    borderRadius: 12,
  },
  linkIcon: { width: 25, color: COLORS.navy, fontSize: 20, textAlign: 'center' },
  linkLabel: { flex: 1, color: COLORS.ink, fontSize: 14, fontWeight: '600' },
  linkArrow: { color: COLORS.muted, fontSize: 22 },
  badge: {
    minWidth: 22,
    height: 22,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 5,
    borderRadius: 11,
    backgroundColor: '#c9595f',
  },
  badgeText: { color: COLORS.white, fontSize: 10, fontWeight: '800' },
  error: { marginTop: 10, color: '#a0453e', fontSize: 11, lineHeight: 16 },
  footer: { paddingHorizontal: 18, paddingBottom: 14 },
  logoutButton: {
    minHeight: 50,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingHorizontal: 14,
    borderWidth: 1,
    borderColor: COLORS.line,
    borderRadius: 12,
    backgroundColor: COLORS.white,
  },
  logoutIcon: { color: COLORS.navy, fontSize: 18 },
  logoutText: { color: COLORS.navy, fontSize: 14, fontWeight: '700' },
  footerCaption: { marginTop: 14, color: COLORS.muted, fontSize: 8, textAlign: 'center', letterSpacing: 0.8 },
  menuButton: {
    position: 'relative',
    width: 40,
    height: 40,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 12,
    backgroundColor: COLORS.paleBlue,
  },
  menuButtonText: { color: COLORS.navy, fontSize: 19, fontWeight: '700' },
  menuBadge: {
    position: 'absolute',
    top: -5,
    right: -5,
    minWidth: 18,
    height: 18,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 4,
    borderWidth: 1,
    borderColor: COLORS.cream,
    borderRadius: 9,
    backgroundColor: '#c9595f',
  },
  pressed: { opacity: 0.72 },
  disabled: { opacity: 0.55 },
});
