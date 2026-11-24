import { router, useLocalSearchParams, usePathname } from 'expo-router';
import { createContext, ReactNode, useCallback, useContext, useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Image,
  Modal,
  Pressable,
  ScrollView,
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
import { COLORS, createThemedStyles, useAppTheme } from '../lib/theme';

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
  useAppTheme();
  const [visible, setVisible] = useState(false);
  const [profile, setProfile] = useState<UserProfile | null>(null);
  const [unreadCount, setUnreadCount] = useState(0);
  const [loadingProfile, setLoadingProfile] = useState(false);
  const [loggingOut, setLoggingOut] = useState(false);
  const [error, setError] = useState('');
  const previousUnreadCounts = useRef<Record<number, number> | null>(null);
  const pathname = usePathname();
  const params = useLocalSearchParams<{ mode?: string; view?: string; section?: string }>();
  const mode = Array.isArray(params.mode) ? params.mode[0] : params.mode;
  const view = Array.isArray(params.view) ? params.view[0] : params.view;
  const section = Array.isArray(params.section) ? params.section[0] : params.section;
  const isMarketplace = pathname === '/';

  const refreshUnreadCount = useCallback(async (showNewMessageAlert: boolean) => {
    if (!await getAuthToken()) {
      previousUnreadCounts.current = null;
      setUnreadCount(0);
      return;
    }

    const conversations = (await requestJson<Conversation[]>('/conversations')).data;
    const nextUnreadCount = unreadMessageCount(conversations);
    const previousCounts = previousUnreadCounts.current;
    const newMessages = previousCounts === null
      ? 0
      : conversations.reduce(
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
      conversations.map((conversation) => [conversation.id, conversation.unread_messages_count]),
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

  const navigate = useCallback((path: '/account' | '/messages' | '/sell' | '/support' | '/settings' | '/contact-support' | '/fetch' | '/admin' | '/', mode?: 'rent' | 'sale' | 'saved', section?: 'profile' | 'listings') => {
    closeDrawer();
    if (path === '/admin') {
      router.push('/admin');
      return;
    }
    if (path === '/account' && section) {
      router.push({ pathname: '/account', params: { section } });
      return;
    }
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
                onPress={() => navigate('/account', undefined, 'profile')}
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
                      {profile?.name.slice(0, 1).toUpperCase() ?? 'P'}
                    </Text>
                  </View>
                )}
                <View style={drawerStyles.profileCopy}>
                  <Text style={drawerStyles.profileName} numberOfLines={1}>
                    {profile?.name ?? 'Your profile'}
                  </Text>
                  <Text style={drawerStyles.profileAction}>View profile</Text>
                </View>
              </Pressable>

              <View style={drawerStyles.navSection}>
                <Text style={drawerStyles.sectionLabel}>Explore</Text>
                <DrawerLink
                  active={isMarketplace && view !== 'saved' && mode !== 'rent'}
                  label="Browse marketplace"
                  onPress={() => navigate('/', 'sale')}
                />
                <DrawerLink
                  active={isMarketplace && view !== 'saved' && mode === 'rent'}
                  label="Browse rentals"
                  onPress={() => navigate('/', 'rent')}
                />
                <DrawerLink
                  active={isMarketplace && view === 'saved'}
                  label="Saved finds"
                  onPress={() => navigate('/', 'saved')}
                />
                <DrawerLink
                  active={pathname === '/fetch' || pathname === '/fetch-create'}
                  label="Fetch request board"
                  onPress={() => navigate('/fetch')}
                />
              </View>

              <View style={drawerStyles.navSection}>
                <Text style={drawerStyles.sectionLabel}>Your account</Text>
                <DrawerLink
                  active={pathname === '/account' && section === 'listings'}
                  label="Manage listings"
                  onPress={() => navigate('/account', undefined, 'listings')}
                />
                <DrawerLink active={pathname === '/sell'} label="List an item" onPress={() => navigate('/sell')} />
                <DrawerLink
                  active={pathname.startsWith('/messages')}
                  badge={unreadCount}
                  label="Message inbox"
                  onPress={() => navigate('/messages')}
                />
                <DrawerLink active={pathname === '/settings'} label="Settings" onPress={() => navigate('/settings')} />
              </View>

              <View style={drawerStyles.navSection}>
                <Text style={drawerStyles.sectionLabel}>Help</Text>
                <DrawerLink active={pathname === '/support'} label="Report an account" onPress={() => navigate('/support')} />
                <DrawerLink active={pathname === '/contact-support'} label="Contact support" onPress={() => navigate('/contact-support')} />
                {profile?.role === 'admin' ? (
                  <DrawerLink active={pathname.startsWith('/admin')} label="Admin tools" onPress={() => navigate('/admin')} />
                ) : null}
              </View>

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
                  <Text style={drawerStyles.logoutText}>Log out</Text>
                )}
              </Pressable>
            </View>
          </SafeAreaView>
        </View>
      </Modal>
    </NavigationDrawerContext.Provider>
  );
}

function DrawerLink({
  active = false,
  badge,
  label,
  onPress,
}: {
  active?: boolean;
  badge?: number;
  label: string;
  onPress: () => void;
}) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityState={{ selected: active }}
      onPress={onPress}
      style={({ pressed }) => [
        drawerStyles.link,
        active && drawerStyles.linkActive,
        pressed && drawerStyles.pressed,
      ]}
    >
      <Text style={[drawerStyles.linkLabel, active && drawerStyles.linkLabelActive]}>{label}</Text>
      {badge ? (
        <View style={drawerStyles.badge}>
          <Text style={drawerStyles.badgeText}>{badge > 99 ? '99+' : badge}</Text>
        </View>
      ) : null}
    </Pressable>
  );
}

const drawerStyles = createThemedStyles({
  modal: { flex: 1, flexDirection: 'row', backgroundColor: '#33251d80' },
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
    marginBottom: 20,
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
  profileAction: { marginTop: 4, color: '#e5d6c8', fontSize: 11 },
  navSection: { marginBottom: 12 },
  sectionLabel: {
    marginBottom: 7,
    marginLeft: 10,
    color: COLORS.muted,
    fontSize: 11,
    fontWeight: '800',
    letterSpacing: 1.2,
  },
  link: {
    minHeight: 44,
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 3,
    paddingHorizontal: 12,
    borderRadius: 8,
  },
  linkActive: { backgroundColor: COLORS.paleGold },
  linkLabel: { flex: 1, color: COLORS.ink, fontSize: 14, fontWeight: '600' },
  linkLabelActive: { color: COLORS.navy, fontWeight: '700' },
  badge: {
    minWidth: 22,
    height: 22,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 5,
    borderRadius: 11,
    backgroundColor: '#c9595f',
  },
  badgeText: { color: COLORS.white, fontSize: 11, fontWeight: '800' },
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
  logoutText: { color: COLORS.navy, fontSize: 14, fontWeight: '700' },
  menuButton: {
    position: 'relative',
    width: 44,
    height: 44,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: COLORS.line,
    borderRadius: 12,
    backgroundColor: COLORS.white,
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
