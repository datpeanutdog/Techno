import { Stack } from 'expo-router';
import { COLORS } from '../App';

export default function RootLayout() {
  return (
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
      <Stack.Screen name="listing/[slug]" />
      <Stack.Screen name="messages/index" />
      <Stack.Screen name="messages/new" />
      <Stack.Screen name="messages/[id]" />
    </Stack>
  );
}
