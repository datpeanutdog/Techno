import { router, useFocusEffect } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useCallback, useState } from 'react';
import { ActivityIndicator, Pressable, ScrollView, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { COLORS, styles } from '../App';
import { getAuthToken } from '../src/lib/api';
import { MenuButton } from '../src/components/NavigationDrawer';
import { ThemeMode, useAppTheme } from '../src/lib/theme';

const OPTIONS: { value: ThemeMode; label: string; description: string; icon: string }[] = [
  { value: 'light', label: 'Light', description: 'Keep the campus palette bright and warm.', icon: '☼' },
  { value: 'dark', label: 'Dark', description: 'Use a softer dark palette in low light.', icon: '☾' },
];

export default function SettingsScreen() {
  const { mode, setMode, error: preferenceError } = useAppTheme();
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  useFocusEffect(useCallback(() => {
    let active = true;
    void getAuthToken().then((token) => {
      if (active && !token) router.replace('/account');
    }).catch((issue: unknown) => {
      if (active) setError(issue instanceof Error ? issue.message : 'Your session could not be checked.');
    }).finally(() => {
      if (active) setLoading(false);
    });

    return () => {
      active = false;
    };
  }, []));

  const chooseMode = async (nextMode: ThemeMode) => {
    setSaving(true);
    setError('');
    try {
      await setMode(nextMode);
    } catch (issue) {
      setError(issue instanceof Error ? issue.message : 'Your appearance preference could not be saved.');
    } finally {
      setSaving(false);
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
          <Text style={styles.headerRouteTitle}>Settings</Text>
          <MenuButton />
        </View>
        {loading ? (
          <View style={styles.loadingState}>
            <ActivityIndicator color={COLORS.goldDark} />
            <Text style={styles.helperText}>Loading settings...</Text>
          </View>
        ) : (
          <ScrollView contentContainerStyle={{ padding: 18, gap: 16 }}>
            {error || preferenceError ? <Text style={styles.formError}>{error || preferenceError}</Text> : null}
            <View style={styles.formCard}>
              <Text style={styles.formSubheading}>COMFORTABLE BROWSING</Text>
              <Text style={[styles.formHeading, { marginTop: 6 }]}>Appearance</Text>
              <Text style={styles.formSubheading}>Choose how Pasabuy looks on this device. Your choice is saved for your next visit.</Text>
              <View accessibilityRole="radiogroup" style={{ gap: 10, marginTop: 18 }}>
                {OPTIONS.map((option) => {
                  const selected = mode === option.value;
                  return (
                    <Pressable
                      accessibilityRole="radio"
                      accessibilityState={{ checked: selected, disabled: saving }}
                      disabled={saving}
                      key={option.value}
                      onPress={() => void chooseMode(option.value)}
                      style={[
                        styles.formCard,
                        {
                          flexDirection: 'row',
                          alignItems: 'center',
                          gap: 12,
                          marginTop: 0,
                          borderColor: selected ? COLORS.goldDark : COLORS.line,
                          backgroundColor: selected ? COLORS.paleGold : COLORS.white,
                        },
                      ]}
                    >
                      <Text style={{ color: COLORS.goldDark, fontSize: 24, width: 30, textAlign: 'center' }}>{option.icon}</Text>
                      <View style={{ flex: 1 }}>
                        <Text style={{ color: COLORS.navy, fontSize: 15, fontWeight: '700' }}>{option.label}</Text>
                        <Text style={styles.formSubheading}>{option.description}</Text>
                      </View>
                      <View style={{
                        width: 20,
                        height: 20,
                        alignItems: 'center',
                        justifyContent: 'center',
                        borderWidth: 2,
                        borderColor: selected ? COLORS.goldDark : COLORS.muted,
                        borderRadius: 10,
                      }}>
                        {selected ? <View style={{ width: 10, height: 10, borderRadius: 5, backgroundColor: COLORS.goldDark }} /> : null}
                      </View>
                    </Pressable>
                  );
                })}
              </View>
              {saving ? <ActivityIndicator color={COLORS.goldDark} style={{ marginTop: 12 }} /> : null}
            </View>

            <View style={styles.formCard}>
              <Text style={styles.formSubheading}>YOUR ACCOUNT</Text>
              <Pressable onPress={() => router.push('/account')} style={{ paddingVertical: 12 }}>
                <Text style={{ color: COLORS.navy, fontWeight: '700' }}>Profile settings</Text>
                <Text style={styles.formSubheading}>Update your name, campus, bio, or photo.</Text>
              </Pressable>
              <View style={{ height: 1, backgroundColor: COLORS.line }} />
              <Pressable onPress={() => router.push('/support')} style={{ paddingVertical: 12 }}>
                <Text style={{ color: COLORS.navy, fontWeight: '700' }}>Help and support</Text>
                <Text style={styles.formSubheading}>Report inappropriate behavior or a scam.</Text>
              </Pressable>
            </View>
          </ScrollView>
        )}
      </View>
    </SafeAreaView>
  );
}
