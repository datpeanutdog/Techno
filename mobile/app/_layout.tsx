import { useEffect, useRef } from 'react';
import { Stack } from 'expo-router';
import { COLORS } from '../App';
import { NavigationDrawerProvider } from '../src/components/NavigationDrawer';
import { AppState, View } from 'react-native';
import {
  clearAuthToken,
  getAuthToken,
  isApiErrorStatus,
  requestJson,
} from '../src/lib/api';

export default function RootLayout() {
  const lastActivityAt = useRef(0);
  const appState = useRef(AppState.currentState);

  useEffect(() => {
    let isSendingHeartbeat = false;
    lastActivityAt.current = Date.now();

    const sendHeartbeat = async () => {
      if (appState.current !== 'active' || isSendingHeartbeat) return;

      isSendingHeartbeat = true;

      try {
        if (!await getAuthToken()) return;

        await requestJson('/presence/heartbeat', {
          method: 'POST',
          body: JSON.stringify({
            active: Date.now() - lastActivityAt.current < 15 * 60 * 1000,
          }),
        });
      } catch (issue) {
        if (isApiErrorStatus(issue, 401)) {
          await clearAuthToken();
        } else {
          console.error('Unable to update messaging presence.', issue);
        }
      } finally {
        isSendingHeartbeat = false;
      }
    };

    void sendHeartbeat();
    const interval = setInterval(() => void sendHeartbeat(), 60 * 1000);
    const subscription = AppState.addEventListener('change', (nextState) => {
      appState.current = nextState;

      if (nextState === 'active') {
        lastActivityAt.current = Date.now();
        void sendHeartbeat();
      }
    });

    return () => {
      clearInterval(interval);
      subscription.remove();
    };
  }, []);

  return (
    <NavigationDrawerProvider>
      <View
        onTouchStart={() => {
          lastActivityAt.current = Date.now();
        }}
        style={{ flex: 1 }}
      >
        <Stack
          screenOptions={{
            headerShown: false,
            contentStyle: { backgroundColor: COLORS.cream },
            animation: 'slide_from_right',
          }}
        >
          <Stack.Screen name="index" />
          <Stack.Screen name="sell" />
          <Stack.Screen name="account" />
          <Stack.Screen name="support" />
          <Stack.Screen name="listing/[slug]" />
          <Stack.Screen name="seller/[id]" />
          <Stack.Screen name="messages/index" />
          <Stack.Screen name="messages/new" />
          <Stack.Screen name="messages/[id]" />
        </Stack>
      </View>
    </NavigationDrawerProvider>
  );
}
