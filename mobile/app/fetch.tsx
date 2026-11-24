import { router, useFocusEffect } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useCallback, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Pressable,
  RefreshControl,
  ScrollView,
  Text,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { COLORS, styles } from '../App';
import { FetchDashboard, FetchRequest, getAuthToken, requestJson } from '../src/lib/api';
import { MenuButton } from '../src/components/NavigationDrawer';
import { useAppTheme } from '../src/lib/theme';

export default function FetchScreen() {
  const { mode } = useAppTheme();
  const [requests, setRequests] = useState<FetchRequest[]>([]);
  const [dashboard, setDashboard] = useState<FetchDashboard>({ accepted: [], posted: [] });
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState('');
  const [activeActionId, setActiveActionId] = useState<string | null>(null);
  const [nowTimestamp, setNowTimestamp] = useState(0);
  const latestId = useRef(0);

  const loadDashboard = useCallback(async () => {
    const result = await requestJson<FetchDashboard>('/fetch/requests/mine');
    setDashboard(result.data);
  }, []);

  const loadRequests = useCallback(async (refreshOnly = false) => {
    try {
      if (!await getAuthToken()) {
        router.replace({ pathname: '/account', params: { returnTo: 'fetch' } });
        return;
      }

      if (refreshOnly) {
        const updates = await requestJson<FetchRequest[]>(`/fetch/requests/updates?after=${latestId.current}`);
        const incoming = updates.data;
        const availableIds = new Set((updates.available_ids ?? []).map(String));
        if (incoming.length) {
          latestId.current = Math.max(latestId.current, ...incoming.map((item) => Number(item.id)));
          setRequests((current) => [...incoming, ...current]
            .filter((item) => (
              availableIds.has(String(item.id))
              && new Date(item.expires_at).getTime() > Date.now()
            ))
            .slice(0, 30));
        } else {
          setRequests((current) => current.filter((item) => (
            availableIds.has(String(item.id))
            && new Date(item.expires_at).getTime() > Date.now()
          )));
        }
      } else {
        const result = await requestJson<FetchRequest[]>('/fetch/requests');
        setRequests(result.data);
        latestId.current = result.data.reduce((latest, item) => Math.max(latest, Number(item.id)), 0);
      }
      await loadDashboard();
      setNowTimestamp(Date.now());
      setError('');
    } catch (issue) {
      setError(issue instanceof Error ? issue.message : 'The fetch board could not be loaded.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [loadDashboard]);

  const performAction = useCallback(async (
    fetchRequestId: number | string,
    action: 'accept' | 'delivered' | 'confirm',
  ) => {
    const actionId = `${fetchRequestId}-${action}`;
    setActiveActionId(actionId);
    setError('');

    try {
      await requestJson<FetchRequest>(`/fetch/requests/${fetchRequestId}/${action}`, {
        method: 'POST',
      });
      await loadRequests();
    } catch (issue) {
      setError(issue instanceof Error ? issue.message : 'The fetch request could not be updated.');
    } finally {
      setActiveActionId(null);
    }
  }, [loadRequests]);

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
          <View style={styles.fetchHeading}>
            <Text style={styles.fetchEyebrow}>CAMPUS REQUEST BOARD</Text>
            <Text style={styles.formHeading}>Live fetch requests</Text>
            <Text style={styles.formSubheading}>See what students need delivered around campus.</Text>
          </View>
          <Pressable
            accessibilityRole="button"
            onPress={() => router.push('/fetch-create')}
            style={[styles.primaryButton, styles.fetchCreateButton]}
          >
            <Text style={styles.primaryButtonText}>＋  Post a fetch request</Text>
          </Pressable>
          <Text style={styles.fetchFeedStatus}>Live updates every 15 seconds.</Text>
          {loading ? <ActivityIndicator color={COLORS.goldDark} style={{ marginTop: 8 }} /> : null}
          {error ? <Text style={styles.formError}>{error}</Text> : null}
          {dashboard.accepted.length > 0 ? (
            <View style={styles.fetchDashboardSection}>
              <Text style={styles.fetchDashboardHeading}>Jobs you accepted</Text>
              {dashboard.accepted.map((job) => {
                const deadlinePassed = nowTimestamp > new Date(job.delivery_deadline).getTime();
                return (
                  <View key={`accepted-${job.id}`} style={styles.fetchDashboardCard}>
                    <View style={styles.fetchRequestHeading}>
                      <View style={{ flex: 1 }}>
                        <Text style={styles.fetchRequestTitle}>{job.item_name}</Text>
                        <Text style={styles.fetchRequester}>Requested by {job.requester.name}</Text>
                      </View>
                      <Text style={styles.fetchJobStatus}>
                        {job.status === 'completed'
                          ? 'Delivery confirmed'
                          : job.status === 'delivered'
                            ? 'Waiting for confirmation'
                            : deadlinePassed
                              ? 'Deadline passed'
                              : 'Currently doing this job'}
                      </Text>
                    </View>
                    <Text style={styles.fetchMeta}>
                      Deliver to <Text style={styles.fetchLocation}>{job.delivery_location}</Text>
                    </Text>
                    <Text style={styles.fetchExpiry}>Deliver by {formatFetchTime(job.delivery_deadline)}</Text>
                    {job.status === 'accepted' && !deadlinePassed ? (
                      <Pressable
                        accessibilityRole="button"
                        disabled={activeActionId !== null}
                        onPress={() => void performAction(job.id, 'delivered')}
                        style={[styles.fetchActionButton, activeActionId === `${job.id}-delivered` && styles.disabledButton]}
                      >
                        {activeActionId === `${job.id}-delivered`
                          ? <ActivityIndicator color="#fff" />
                          : <Text style={styles.primaryButtonText}>Mark as delivered</Text>}
                      </Pressable>
                    ) : null}
                    {job.status === 'accepted' && deadlinePassed ? (
                      <Text style={styles.fetchDeadlineWarning}>The deadline passed. This request cannot be marked delivered.</Text>
                    ) : null}
                    {job.status === 'delivered' ? (
                      <Text style={styles.fetchDashboardNote}>Delivery reported. Waiting for the requester to confirm.</Text>
                    ) : null}
                    {job.status === 'completed' ? (
                      <Text style={styles.fetchDashboardNote}>The requester confirmed delivery.</Text>
                    ) : null}
                  </View>
                );
              })}
            </View>
          ) : null}
          {dashboard.posted.length > 0 ? (
            <View style={styles.fetchDashboardSection}>
              <Text style={styles.fetchDashboardHeading}>Requests you posted</Text>
              {dashboard.posted.map((request) => {
                const deadlinePassed = request.status === 'accepted'
                  && nowTimestamp > new Date(request.delivery_deadline).getTime();

                return (
                  <View key={`posted-${request.id}`} style={styles.fetchDashboardCard}>
                    <View style={styles.fetchRequestHeading}>
                      <View style={{ flex: 1 }}>
                        <Text style={styles.fetchRequestTitle}>{request.item_name}</Text>
                        <Text style={styles.fetchRequester}>
                          {request.accepted_by
                            ? `Accepted by ${request.accepted_by.name}`
                            : 'Accepted by another student'}
                        </Text>
                      </View>
                      <Text style={styles.fetchJobStatus}>
                        {request.status === 'completed'
                          ? 'Delivery confirmed'
                          : request.status === 'delivered'
                            ? 'Confirm delivery'
                            : deadlinePassed
                              ? 'Deadline passed'
                              : 'Delivery Ongoing'}
                      </Text>
                    </View>
                    <Text style={styles.fetchMeta}>
                      Deliver to <Text style={styles.fetchLocation}>{request.delivery_location}</Text>
                    </Text>
                    <Text style={styles.fetchExpiry}>Deliver by {formatFetchTime(request.delivery_deadline)}</Text>
                    {request.status === 'delivered' ? (
                      <Pressable
                        accessibilityRole="button"
                        disabled={activeActionId !== null}
                        onPress={() => void performAction(request.id, 'confirm')}
                        style={[styles.fetchActionButton, activeActionId === `${request.id}-confirm` && styles.disabledButton]}
                      >
                        {activeActionId === `${request.id}-confirm`
                          ? <ActivityIndicator color="#fff" />
                          : <Text style={styles.primaryButtonText}>Confirm delivery</Text>}
                      </Pressable>
                    ) : null}
                    {request.status === 'completed' ? (
                      <Text style={styles.fetchDashboardNote}>Delivery was confirmed by you.</Text>
                    ) : null}
                    {deadlinePassed ? (
                      <Text style={styles.fetchDeadlineWarning}>The deadline passed without an on-time delivery report.</Text>
                    ) : null}
                  </View>
                );
              })}
            </View>
          ) : null}
          {requests.map((request) => (
            <View key={request.id} style={styles.fetchRequestCard}>
              <View style={styles.fetchRequestHeading}>
                <View style={{ flex: 1 }}>
                  <Text style={styles.fetchRequestTitle}>{request.item_name}</Text>
                  <Text style={styles.fetchRequester}>Posted by {request.requester.name}</Text>
                </View>
                <Text style={styles.fetchBudget}>
                  {request.budget === null ? 'Budget flexible' : `Up to ${formatFetchBudget(request.budget)}`}
                </Text>
              </View>
              <Text style={styles.fetchDetails}>{request.details}</Text>
              <Text style={styles.fetchMeta}>
                Deliver to <Text style={styles.fetchLocation}>{request.delivery_location}</Text>
              </Text>
              <View style={styles.fetchTimeRow}>
                <Text style={styles.fetchMeta}>Posted {formatFetchTime(request.created_at)}</Text>
                <Text style={styles.fetchExpiry}>Expires {formatFetchTime(request.expires_at)}</Text>
                <Text style={styles.fetchExpiry}>Deliver by {formatFetchTime(request.delivery_deadline)}</Text>
              </View>
              {request.can_accept ? (
                <Pressable
                  accessibilityRole="button"
                  disabled={activeActionId !== null}
                  onPress={() => void performAction(request.id, 'accept')}
                  style={[styles.fetchActionButton, activeActionId === `${request.id}-accept` && styles.disabledButton]}
                >
                  {activeActionId === `${request.id}-accept`
                    ? <ActivityIndicator color="#fff" />
                    : <Text style={styles.primaryButtonText}>Accept this request</Text>}
                </Pressable>
              ) : (
                <Text style={styles.fetchDashboardNote}>This is your request.</Text>
              )}
            </View>
          ))}
          {!loading && requests.length === 0 ? (
            <View style={styles.fetchEmptyState}>
              <Text style={styles.formSubheading}>No live requests at the moment.</Text>
              <Text style={styles.formSubheading}>Check back soon or post what you need.</Text>
            </View>
          ) : null}
        </ScrollView>
      </View>
    </SafeAreaView>
  );
}

function formatFetchBudget(budget: number | string): string {
  return `₱${Number(budget).toLocaleString('en-PH', { maximumFractionDigits: 2 })}`;
}

function formatFetchTime(timestamp: string): string {
  return new Intl.DateTimeFormat('en-PH', {
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  }).format(new Date(timestamp));
}
