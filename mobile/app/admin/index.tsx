import { router, useFocusEffect } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useCallback, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Image,
  Platform,
  Pressable,
  ScrollView,
  Text,
  TextInput,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { COLORS, styles } from '../../App';
import { getAuthToken, requestJson } from '../../src/lib/api';
import { MenuButton } from '../../src/components/NavigationDrawer';
import { useAppTheme } from '../../src/lib/theme';

type AdminSection = 'users' | 'reports' | 'appeals';
type AppealTab = 'active' | 'resolved';
type PageMeta = { current_page: number; last_page: number; total: number };

type AdminUser = {
  id: number;
  name: string;
  email: string;
  role: 'admin' | 'user';
  account_identifier: string;
  can_change_role: boolean;
};

type AdminReport = {
  id: number;
  category: string;
  category_label: string;
  details: string;
  status: string;
  admin_note: string | null;
  proof_count: number;
  proof_images?: string[];
  created_at: string;
  contact_name: string | null;
  contact_email: string | null;
  reporter: { name: string; email: string; account_identifier: string } | null;
  reported_user: {
    id: number;
    name: string;
    email: string;
    account_identifier: string;
    is_suspended: boolean;
    suspension_reason: string | null;
  } | null;
};

type AdminAppeal = {
  id: number;
  message: string;
  status: string;
  admin_note: string | null;
  created_at: string;
  proof_image?: string | null;
  previous_appeal?: { id: number; message: string; status: string; admin_note: string | null } | null;
  user: {
    id: number;
    name: string;
    email: string;
    account_identifier: string;
    is_suspended: boolean;
    suspension_reason: string | null;
  } | null;
};

const EMPTY_META: PageMeta = { current_page: 1, last_page: 1, total: 0 };

export default function AdminScreen() {
  const { mode } = useAppTheme();
  const [section, setSection] = useState<AdminSection>('reports');
  const [appealTab, setAppealTab] = useState<AppealTab>('active');
  const [page, setPage] = useState(1);
  const [meta, setMeta] = useState(EMPTY_META);
  const [users, setUsers] = useState<AdminUser[]>([]);
  const [reports, setReports] = useState<AdminReport[]>([]);
  const [appeals, setAppeals] = useState<AdminAppeal[]>([]);
  const [selectedReport, setSelectedReport] = useState<AdminReport | null>(null);
  const [selectedAppeal, setSelectedAppeal] = useState<AdminAppeal | null>(null);
  const [reportStatus, setReportStatus] = useState('reviewed');
  const [appealStatus, setAppealStatus] = useState('under_review');
  const [adminNote, setAdminNote] = useState('');
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const loadSection = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      if (!await getAuthToken()) {
        router.replace('/account');
        return;
      }

      if (section === 'users') {
        const result = await requestJson<AdminUser[]>(`/admin/users?page=${page}`);
        setUsers(result.data);
        setMeta(result.meta ?? EMPTY_META);
      } else if (section === 'reports') {
        const result = await requestJson<AdminReport[]>(`/admin/support-reports?page=${page}`);
        setReports(result.data);
        setMeta(result.meta ?? EMPTY_META);
      } else {
        const result = await requestJson<AdminAppeal[]>(`/admin/suspension-appeals?tab=${appealTab}&page=${page}`);
        setAppeals(result.data);
        setMeta(result.meta ?? EMPTY_META);
      }
    } catch (issue) {
      setError(issue instanceof Error ? issue.message : 'Admin data could not be loaded.');
    } finally {
      setLoading(false);
    }
  }, [appealTab, page, section]);

  useFocusEffect(useCallback(() => {
    void loadSection();
  }, [loadSection]));

  const chooseSection = (next: AdminSection) => {
    setSection(next);
    setPage(1);
    setSelectedReport(null);
    setSelectedAppeal(null);
    setNotice('');
  };

  const openReport = async (report: AdminReport) => {
    setBusy(true);
    setError('');
    try {
      const result = await requestJson<AdminReport>(`/admin/support-reports/${report.id}`);
      setSelectedReport(result.data);
      setReportStatus(result.data.status === 'pending' ? 'reviewed' : result.data.status);
      setAdminNote(result.data.admin_note ?? '');
    } catch (issue) {
      setError(issue instanceof Error ? issue.message : 'The report could not be opened.');
    } finally {
      setBusy(false);
    }
  };

  const openAppeal = async (appeal: AdminAppeal) => {
    setBusy(true);
    setError('');
    try {
      const result = await requestJson<AdminAppeal>(`/admin/suspension-appeals/${appeal.id}`);
      setSelectedAppeal(result.data);
      setAppealStatus(result.data.status);
      setAdminNote(result.data.admin_note ?? '');
    } catch (issue) {
      setError(issue instanceof Error ? issue.message : 'The appeal could not be opened.');
    } finally {
      setBusy(false);
    }
  };

  const updateRole = async (user: AdminUser, role: AdminUser['role']) => {
    setBusy(true);
    setError('');
    setNotice('');
    try {
      await requestJson(`/admin/users/${user.id}/role`, {
        method: 'PATCH',
        body: JSON.stringify({ role }),
      });
      setUsers((current) => current.map((item) => item.id === user.id ? { ...item, role } : item));
      setNotice(`${user.name}'s role was updated.`);
    } catch (issue) {
      setError(issue instanceof Error ? issue.message : 'The account role could not be updated.');
    } finally {
      setBusy(false);
    }
  };

  const updateReport = async () => {
    if (!selectedReport) return;
    setBusy(true);
    setError('');
    setNotice('');
    try {
      const result = await requestJson<AdminReport>(`/admin/support-reports/${selectedReport.id}`, {
        method: 'PATCH',
        body: JSON.stringify({ status: reportStatus, admin_note: adminNote }),
      });
      setSelectedReport({ ...result.data, proof_images: selectedReport.proof_images });
      setNotice(result.message ?? 'The report has been updated.');
      await loadSection();
    } catch (issue) {
      setError(issue instanceof Error ? issue.message : 'The report could not be updated.');
    } finally {
      setBusy(false);
    }
  };

  const performReportAction = (action: 'suspend' | 'restore') => {
    if (!selectedReport) return;
    const user = selectedReport.reported_user;
    if (!user) return;
    confirmAction(
      action === 'suspend' ? 'Suspend account?' : 'Restore account?',
      action === 'suspend'
        ? 'This will block account activity and revoke its API sessions.'
        : 'This will remove the account suspension.',
      () => void (async () => {
        setBusy(true);
        setError('');
        try {
          const result = await requestJson<never>(`/admin/support-reports/${selectedReport.id}/${action}`, { method: 'POST' });
          setNotice(result.message ?? (action === 'suspend' ? 'The account was suspended.' : 'The account was restored.'));
          await openReport(selectedReport);
          await loadSection();
        } catch (issue) {
          setError(issue instanceof Error ? issue.message : 'The account action could not be completed.');
        } finally {
          setBusy(false);
        }
      })(),
    );
  };

  const updateAppeal = async () => {
    if (!selectedAppeal) return;
    setBusy(true);
    setError('');
    setNotice('');
    try {
      const result = await requestJson<AdminAppeal>(`/admin/suspension-appeals/${selectedAppeal.id}`, {
        method: 'PATCH',
        body: JSON.stringify({ status: appealStatus, admin_note: adminNote }),
      });
      setSelectedAppeal(result.data);
      setNotice(result.message ?? 'The suspension appeal has been updated.');
      await loadSection();
    } catch (issue) {
      setError(issue instanceof Error ? issue.message : 'The suspension appeal could not be updated.');
    } finally {
      setBusy(false);
    }
  };

  const title = section === 'users' ? 'Manage users' : section === 'reports' ? 'Support reports' : `${appealTab === 'active' ? 'Active' : 'Resolved'} appeals`;

  return (
    <SafeAreaView edges={['top', 'bottom']} style={styles.safeArea}>
      <View style={styles.app}>
        <StatusBar style={mode === 'dark' ? 'light' : 'dark'} />
        <View style={styles.header}>
          <Pressable onPress={() => router.back()} style={styles.backButton}>
            <Text style={styles.backButtonText}>‹  Back</Text>
          </Pressable>
          <Text style={styles.headerRouteTitle}>Admin tools</Text>
          <MenuButton />
        </View>
        <ScrollView contentContainerStyle={{ padding: 16, gap: 14 }}>
          <Text style={styles.formHeading}>{title}</Text>
          <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }}>
            <SectionButton active={section === 'reports'} label="Reports" onPress={() => chooseSection('reports')} />
            <SectionButton active={section === 'appeals'} label="Suspension appeals" onPress={() => chooseSection('appeals')} />
            <SectionButton active={section === 'users'} label="Users" onPress={() => chooseSection('users')} />
          </View>
          {section === 'appeals' ? (
            <View style={{ flexDirection: 'row', gap: 8 }}>
              <SectionButton active={appealTab === 'active'} label="Active" onPress={() => { setAppealTab('active'); setPage(1); setSelectedAppeal(null); }} />
              <SectionButton active={appealTab === 'resolved'} label="Resolved" onPress={() => { setAppealTab('resolved'); setPage(1); setSelectedAppeal(null); }} />
            </View>
          ) : null}
          {error ? <Text style={styles.formError}>{error}</Text> : null}
          {notice ? <Text style={styles.formSubheading}>{notice}</Text> : null}
          {loading ? <ActivityIndicator color={COLORS.goldDark} /> : null}

          {!loading && section === 'users' ? users.map((user) => (
            <View key={user.id} style={styles.formCard}>
              <Text style={styles.formHeading}>{user.name} · {user.role}</Text>
              <Text style={styles.formSubheading}>{user.email}</Text>
              <Text style={styles.formSubheading}>{user.account_identifier}</Text>
              {user.can_change_role ? (
                <View style={{ flexDirection: 'row', gap: 8, marginTop: 12 }}>
                  <SectionButton active={user.role === 'user'} disabled={busy} label="User" onPress={() => void updateRole(user, 'user')} />
                  <SectionButton active={user.role === 'admin'} disabled={busy} label="Admin" onPress={() => void updateRole(user, 'admin')} />
                </View>
              ) : <Text style={styles.formSubheading}>You cannot change your own role.</Text>}
            </View>
          )) : null}

          {!loading && section === 'reports' && !selectedReport ? reports.map((report) => (
            <Pressable key={report.id} onPress={() => void openReport(report)} style={styles.formCard}>
              <Text style={styles.formHeading}>{report.category_label} · {formatStatus(report.status)}</Text>
              <Text style={styles.formSubheading}>Reported: {report.reported_user?.name ?? 'Account unavailable'} · Submitted by {report.reporter?.name ?? 'Account unavailable'}</Text>
              <Text numberOfLines={3} style={{ color: COLORS.ink, fontSize: 13, lineHeight: 19, marginTop: 8 }}>{report.details}</Text>
              <Text style={styles.formSubheading}>{report.proof_count} image(s) · {formatDate(report.created_at)}</Text>
              <Text style={{ color: COLORS.goldDark, marginTop: 8 }}>Review report ›</Text>
            </Pressable>
          )) : null}

          {!loading && section === 'appeals' && !selectedAppeal ? appeals.map((appeal) => (
            <Pressable key={appeal.id} onPress={() => void openAppeal(appeal)} style={styles.formCard}>
              <Text style={styles.formHeading}>{appeal.user?.name ?? 'Account unavailable'} · {appeal.user?.account_identifier}</Text>
              <Text style={styles.formSubheading}>{formatStatus(appeal.status)} · {appeal.user?.email} · {formatDate(appeal.created_at)}</Text>
              <Text numberOfLines={3} style={{ color: COLORS.ink, fontSize: 13, lineHeight: 19, marginTop: 8 }}>{appeal.message}</Text>
              <Text style={{ color: COLORS.goldDark, marginTop: 8 }}>Review appeal ›</Text>
            </Pressable>
          )) : null}

          {selectedReport && section === 'reports' ? (
            <View style={styles.formCard}>
              <Pressable onPress={() => setSelectedReport(null)}><Text style={{ color: COLORS.goldDark }}>‹ Back to reports</Text></Pressable>
              <Text style={[styles.formHeading, { marginTop: 12 }]}>Report #{selectedReport.id} · {selectedReport.category_label}</Text>
              <Text style={styles.formSubheading}>Reported: {selectedReport.reported_user?.name ?? 'Not applicable'} · {selectedReport.reported_user?.email ?? ''}</Text>
              <Text style={styles.formSubheading}>Submitted by: {selectedReport.reporter?.name ?? selectedReport.contact_name ?? 'Account unavailable'} · {selectedReport.reporter?.email ?? selectedReport.contact_email ?? ''}</Text>
              <Text style={styles.fieldLabel}>Report details</Text>
              <Text style={{ color: COLORS.ink, lineHeight: 20 }}>{selectedReport.details}</Text>
              <Text style={styles.fieldLabel}>Proof images</Text>
              {selectedReport.proof_images?.map((source, index) => (
                <Image key={index} source={{ uri: source }} style={{ height: 190, marginTop: 8, borderRadius: 8 }} resizeMode="contain" />
              ))}
              {selectedReport.reported_user ? (
                <Pressable disabled={busy} onPress={() => performReportAction(selectedReport.reported_user!.is_suspended ? 'restore' : 'suspend')} style={[styles.primaryButton, { marginTop: 16 }]}>
                  <Text style={styles.primaryButtonText}>{selectedReport.reported_user.is_suspended ? 'Restore reported account' : 'Suspend reported account'}</Text>
                </Pressable>
              ) : null}
              <Text style={styles.fieldLabel}>Review outcome</Text>
              <OptionButtons
                options={[['reviewed', 'Under review'], ['resolved', 'Resolved'], ['dismissed', 'Dismissed']]}
                selected={reportStatus}
                onSelect={setReportStatus}
              />
              <Text style={styles.fieldLabel}>Note for the reporter</Text>
              <TextInput maxLength={2000} multiline onChangeText={setAdminNote} style={[styles.formInput, styles.multilineInput]} value={adminNote} />
              <Pressable disabled={busy} onPress={() => void updateReport()} style={[styles.primaryButton, busy && styles.disabledButton]}>
                <Text style={styles.primaryButtonText}>{busy ? 'Saving…' : 'Save review'}</Text>
              </Pressable>
            </View>
          ) : null}

          {selectedAppeal && section === 'appeals' ? (
            <View style={styles.formCard}>
              <Pressable onPress={() => setSelectedAppeal(null)}><Text style={{ color: COLORS.goldDark }}>‹ Back to appeals</Text></Pressable>
              <Text style={[styles.formHeading, { marginTop: 12 }]}>Appeal #{selectedAppeal.id} · {selectedAppeal.user?.name ?? 'Account unavailable'}</Text>
              <Text style={styles.formSubheading}>{selectedAppeal.user?.account_identifier} · {selectedAppeal.user?.email}</Text>
              <Text style={styles.fieldLabel}>Suspension reason</Text>
              <Text style={{ color: COLORS.ink, lineHeight: 20 }}>{selectedAppeal.user?.suspension_reason || 'No suspension reason is currently recorded.'}</Text>
              {selectedAppeal.previous_appeal ? (
                <>
                  <Text style={styles.fieldLabel}>Previous appeal #{selectedAppeal.previous_appeal.id}</Text>
                  <Text style={{ color: COLORS.ink, lineHeight: 20 }}>{selectedAppeal.previous_appeal.message}</Text>
                  <Text style={styles.formSubheading}>Previous support response: {selectedAppeal.previous_appeal.admin_note || 'No response was included.'}</Text>
                </>
              ) : null}
              <Text style={styles.fieldLabel}>{selectedAppeal.previous_appeal ? 'Counter-appeal message' : 'Appeal message'}</Text>
              <Text style={{ color: COLORS.ink, lineHeight: 20 }}>{selectedAppeal.message}</Text>
              {selectedAppeal.proof_image ? <Image source={{ uri: selectedAppeal.proof_image }} style={{ height: 190, marginTop: 10, borderRadius: 8 }} resizeMode="contain" /> : null}
              {['pending', 'under_review'].includes(selectedAppeal.status) ? (
                <>
                  <Text style={styles.fieldLabel}>Review decision</Text>
                  <OptionButtons
                    options={[['under_review', 'Under review'], ['approved', 'Approve and restore'], ['denied', 'Deny appeal']]}
                    selected={appealStatus}
                    onSelect={setAppealStatus}
                  />
                  <Text style={styles.fieldLabel}>Message to account holder</Text>
                  <TextInput maxLength={2000} multiline onChangeText={setAdminNote} style={[styles.formInput, styles.multilineInput]} value={adminNote} />
                  <Pressable disabled={busy} onPress={() => void updateAppeal()} style={[styles.primaryButton, busy && styles.disabledButton]}>
                    <Text style={styles.primaryButtonText}>{busy ? 'Saving…' : 'Save review'}</Text>
                  </Pressable>
                </>
              ) : selectedAppeal.admin_note ? (
                <Text style={styles.formSubheading}>Support update: {selectedAppeal.admin_note}</Text>
              ) : null}
            </View>
          ) : null}

          {!loading && !((section === 'users' && users.length) || (section === 'reports' && (reports.length || selectedReport)) || (section === 'appeals' && (appeals.length || selectedAppeal))) ? (
            <View style={styles.formCard}><Text style={styles.formSubheading}>No {section === 'reports' ? 'support reports' : section === 'appeals' ? appealTab + ' appeals' : 'accounts'} found.</Text></View>
          ) : null}

          {!loading && !selectedReport && !selectedAppeal ? (
            <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
              <Pressable disabled={page <= 1} onPress={() => setPage((current) => Math.max(1, current - 1))}><Text style={{ color: page <= 1 ? COLORS.muted : COLORS.goldDark }}>‹ Previous</Text></Pressable>
              <Text style={styles.formSubheading}>Page {meta.current_page} of {meta.last_page} · {meta.total} total</Text>
              <Pressable disabled={page >= meta.last_page} onPress={() => setPage((current) => Math.min(meta.last_page, current + 1))}><Text style={{ color: page >= meta.last_page ? COLORS.muted : COLORS.goldDark }}>Next ›</Text></Pressable>
            </View>
          ) : null}
        </ScrollView>
      </View>
    </SafeAreaView>
  );
}

function SectionButton({ active, disabled, label, onPress }: { active: boolean; disabled?: boolean; label: string; onPress: () => void }) {
  return (
    <Pressable disabled={disabled} onPress={onPress} style={[styles.choiceChip, active && styles.choiceChipSelected, disabled && styles.disabledButton]}>
      <Text style={[styles.choiceChipText, active && styles.choiceChipTextSelected]}>{label}</Text>
    </Pressable>
  );
}

function OptionButtons({ options, selected, onSelect }: {
  options: [string, string][];
  selected: string;
  onSelect: (value: string) => void;
}) {
  return (
    <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 8 }}>
      {options.map(([value, label]) => (
        <SectionButton active={selected === value} key={value} label={label} onPress={() => onSelect(value)} />
      ))}
    </View>
  );
}

function confirmAction(title: string, message: string, confirm: () => void) {
  if (Platform.OS === 'web') {
    if (globalThis.confirm(`${title}\n\n${message}`)) confirm();
    return;
  }
  Alert.alert(title, message, [
    { text: 'Cancel', style: 'cancel' },
    { text: 'Continue', style: 'destructive', onPress: confirm },
  ]);
}

function formatStatus(status: string): string {
  return status.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function formatDate(value: string): string {
  return new Date(value).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
}
