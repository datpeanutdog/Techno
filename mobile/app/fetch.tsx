import { router, useFocusEffect } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useCallback, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Pressable,
  RefreshControl,
  ScrollView,
  Text,
  TextInput,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { COLORS, styles } from '../App';
import { FetchRequest, getAuthToken, requestJson } from '../src/lib/api';
import { MenuButton } from '../src/components/NavigationDrawer';
import { useAppTheme } from '../src/lib/theme';

const DURATION_UNITS = ['minutes', 'hours', 'days'] as const;
type DurationUnit = (typeof DURATION_UNITS)[number];

export default function FetchScreen() {
  const { mode } = useAppTheme();
  const [requests, setRequests] = useState<FetchRequest[]>([]);
  const [itemName, setItemName] = useState('');
  const [details, setDetails] = useState('');
  const [budget, setBudget] = useState('');
  const [location, setLocation] = useState('');
  const [durationValue, setDurationValue] = useState('30');
  const [durationUnit, setDurationUnit] = useState<DurationUnit>('minutes');
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [posting, setPosting] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const latestId = useRef(0);

  const loadRequests = useCallback(async (refreshOnly = false) => {
    try {
      if (!await getAuthToken()) {
        router.replace({ pathname: '/account', params: { returnTo: 'fetch' } });
        return;
      }

      if (refreshOnly) {
        const updates = await requestJson<FetchRequest[]>(`/fetch/requests/updates?after=${latestId.current}`);
        const incoming = updates.data;
        if (incoming.length) {
          latestId.current = Math.max(latestId.current, ...incoming.map((item) => Number(item.id)));
          setRequests((current) => [...incoming, ...current]
            .filter((item) => new Date(item.expires_at).getTime() > Date.now())
            .slice(0, 30));
        } else {
          setRequests((current) => current.filter((item) => new Date(item.expires_at).getTime() > Date.now()));
        }
      } else {
        const result = await requestJson<FetchRequest[]>('/fetch/requests');
        setRequests(result.data);
        latestId.current = result.data.reduce((latest, item) => Math.max(latest, Number(item.id)), 0);
      }
      setError('');
    } catch (issue) {
      setError(issue instanceof Error ? issue.message : 'Live fetch requests could not be loaded.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useFocusEffect(useCallback(() => {
    let active = true;
    const poll = () => {
      if (active) void loadRequests(true);
    };
    void loadRequests();
    const interval = setInterval(poll, 15_000);
    return () => {
      active = false;
      clearInterval(interval);
    };
  }, [loadRequests]));

  const postRequest = async () => {
    setPosting(true);
    setError('');
    setNotice('');
    try {
      const result = await requestJson<FetchRequest>('/fetch/requests', {
        method: 'POST',
        body: JSON.stringify({
          item_name: itemName.trim(),
          details: details.trim(),
          budget: budget.trim() || null,
          delivery_location: location.trim(),
          duration_value: Number(durationValue),
          duration_unit: durationUnit,
        }),
      });
      setRequests((current) => [result.data, ...current].slice(0, 30));
      latestId.current = Math.max(latestId.current, Number(result.data.id));
      setItemName('');
      setDetails('');
      setBudget('');
      setLocation('');
      setDurationValue('30');
      setNotice(result.message ?? 'Your fetch request is live.');
    } catch (issue) {
      setError(issue instanceof Error ? issue.message : 'Your fetch request could not be posted.');
    } finally {
      setPosting(false);
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
          <Text style={styles.headerRouteTitle}>Fetch requests</Text>
          <MenuButton />
        </View>
        <ScrollView
          contentContainerStyle={{ padding: 18, gap: 16 }}
          refreshControl={
            <RefreshControl
              colors={[COLORS.goldDark]}
              onRefresh={() => {
                setRefreshing(true);
                void loadRequests();
              }}
              refreshing={refreshing}
              tintColor={COLORS.goldDark}
            />
          }
          keyboardShouldPersistTaps="handled"
        >
          <View style={styles.formCard}>
            <Text style={styles.formHeading}>Live campus requests</Text>
            <Text style={styles.formSubheading}>See what students need delivered around campus. This feed refreshes every 15 seconds.</Text>
            {loading ? <ActivityIndicator color={COLORS.goldDark} style={{ marginTop: 16 }} /> : null}
            {requests.map((request) => (
              <View key={request.id} style={{ borderTopWidth: 1, borderTopColor: COLORS.line, paddingVertical: 14 }}>
                <View style={{ flexDirection: 'row', justifyContent: 'space-between', gap: 10 }}>
                  <View style={{ flex: 1 }}>
                    <Text style={{ color: COLORS.navy, fontSize: 16, fontWeight: '700' }}>{request.item_name}</Text>
                    <Text style={{ color: COLORS.muted, fontSize: 12, marginTop: 3 }}>Posted by {request.requester.name}</Text>
                  </View>
                  <Text style={{ color: COLORS.goldDark, fontSize: 12, fontWeight: '700' }}>
                    {request.budget === null ? 'Budget flexible' : `Up to ${formatFetchBudget(request.budget)}`}
                  </Text>
                </View>
                <Text style={{ color: COLORS.ink, fontSize: 13, lineHeight: 19, marginTop: 9 }}>{request.details}</Text>
                <Text style={{ color: COLORS.muted, fontSize: 12, marginTop: 8 }}>
                  Deliver to <Text style={{ color: COLORS.navy, fontWeight: '700' }}>{request.delivery_location}</Text>
                </Text>
                <Text style={{ color: COLORS.muted, fontSize: 11, marginTop: 4 }}>Expires {request.expires_at_display}</Text>
              </View>
            ))}
            {!loading && requests.length === 0 ? <Text style={styles.formSubheading}>No live requests at the moment. Post what you need below.</Text> : null}
          </View>

          <View style={styles.formCard}>
            <Text style={styles.formHeading}>Post a fetch request</Text>
            <Text style={styles.formSubheading}>Use a campus pickup point rather than your home address.</Text>
            {error ? <Text style={styles.formError}>{error}</Text> : null}
            {notice ? <Text style={styles.formSubheading}>{notice}</Text> : null}
            <Text style={styles.fieldLabel}>Item you need</Text>
            <TextInput maxLength={90} onChangeText={setItemName} placeholder="e.g. Bluebook and black pens" style={styles.formInput} value={itemName} />
            <Text style={styles.fieldLabel}>Details</Text>
            <TextInput maxLength={1000} multiline onChangeText={setDetails} placeholder="Add helpful details like size, quantity, or preferred brand." style={[styles.formInput, styles.multilineInput]} value={details} />
            <Text style={styles.fieldLabel}>Budget (optional)</Text>
            <TextInput keyboardType="decimal-pad" onChangeText={setBudget} placeholder="₱" style={styles.formInput} value={budget} />
            <Text style={styles.fieldLabel}>Campus delivery spot</Text>
            <TextInput maxLength={100} onChangeText={setLocation} placeholder="e.g. Main library entrance" style={styles.formInput} value={location} />
            <Text style={styles.fieldLabel}>How long should it stay live?</Text>
            <View style={{ flexDirection: 'row', gap: 8 }}>
              <TextInput keyboardType="number-pad" onChangeText={setDurationValue} style={[styles.formInput, { flex: 1 }]} value={durationValue} />
              {DURATION_UNITS.map((unit) => (
                <Pressable
                  accessibilityRole="button"
                  accessibilityState={{ selected: durationUnit === unit }}
                  key={unit}
                  onPress={() => setDurationUnit(unit)}
                  style={[styles.choiceChip, durationUnit === unit && styles.choiceChipSelected]}
                >
                  <Text style={[styles.choiceChipText, durationUnit === unit && styles.choiceChipTextSelected]}>{unit}</Text>
                </Pressable>
              ))}
            </View>
            <Text style={styles.formSubheading}>Choose 5 minutes to 1 day.</Text>
            <Pressable disabled={posting} onPress={() => void postRequest()} style={[styles.primaryButton, posting && styles.disabledButton]}>
              {posting ? <ActivityIndicator color="#fff" /> : <Text style={styles.primaryButtonText}>Post request</Text>}
            </Pressable>
          </View>
        </ScrollView>
      </View>
    </SafeAreaView>
  );
}

function formatFetchBudget(budget: number | string): string {
  return `₱${Number(budget).toLocaleString('en-PH', { maximumFractionDigits: 2 })}`;
}
