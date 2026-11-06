import * as ImagePicker from 'expo-image-picker';
import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';
import { useCallback, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Image,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  Text,
  TextInput,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { COLORS, styles } from '../App';
import {
  clearAuthToken,
  getAuthToken,
  Listing,
  requestJson,
  saveAuthToken,
  UserProfile,
} from '../src/lib/api';

type AccountMode = 'login' | 'register' | 'forgot';

export default function AccountScreen() {
  const { returnTo } = useLocalSearchParams<{ returnTo?: string }>();
  const [user, setUser] = useState<UserProfile | null>(null);
  const [listings, setListings] = useState<Listing[]>([]);
  const [mode, setMode] = useState<AccountMode>('login');
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [avatar, setAvatar] = useState<ImagePicker.ImagePickerAsset | null>(null);
  const name = useRef('');
  const email = useRef('');
  const password = useRef('');
  const confirmation = useRef('');
  const bio = useRef('');
  const campus = useRef('');
  const avatarUrl = useRef('');

  const loadAccount = useCallback(async () => {
    if (!await getAuthToken()) {
      setUser(null);
      setListings([]);
      return;
    }

    try {
      const [profile, mine] = await Promise.all([
        requestJson<UserProfile>('/auth/me'),
        requestJson<Listing[]>('/my-listings'),
      ]);
      setUser(profile.data);
      name.current = profile.data.name;
      email.current = profile.data.email;
      bio.current = profile.data.bio ?? '';
      campus.current = profile.data.campus ?? '';
      setListings(mine.data);
    } catch (issue) {
      const detail = issue instanceof Error ? issue.message : 'Your account could not be loaded.';
      if (detail.includes('(401)') || detail.includes('(Unauthenticated')) {
        await clearAuthToken();
        setUser(null);
        setListings([]);
        return;
      }
      setError(detail);
    }
  }, []);

  useFocusEffect(useCallback(() => {
    void loadAccount();
  }, [loadAccount]));

  const submitAccountForm = async () => {
    setBusy(true);
    setError('');
    setMessage('');
    try {
      if (mode === 'forgot') {
        const result = await requestJson<never>('/auth/forgot-password', {
          method: 'POST',
          body: JSON.stringify({ email: email.current }),
        });
        setMessage(result.message ?? 'If an account exists for that email, a reset link is on its way.');
        return;
      }

      const path = mode === 'register' ? '/auth/register' : '/auth/login';
      const credentials = mode === 'register'
        ? {
            name: name.current,
            email: email.current,
            password: password.current,
            password_confirmation: confirmation.current,
          }
        : { email: email.current, password: password.current };
      const result = await requestJson<UserProfile>(path, {
        method: 'POST',
        body: JSON.stringify(credentials),
      });

      if (!result.token) {
        throw new Error('PASABUY could not start a secure session. Please try again.');
      }

      await saveAuthToken(result.token);
      setUser(result.data);
      setNameFromProfile(result.data);
      setMessage(mode === 'register' ? result.message ?? 'Your account is ready.' : 'You are signed in.');
      await loadAccount();
      if (returnTo === 'sell') {
        router.replace('/sell');
      } else if (returnTo === 'messages') {
        router.replace('/messages');
      } else {
        router.replace('/');
      }
    } catch (issue) {
      setError(issue instanceof Error ? issue.message : 'The account request could not be completed.');
    } finally {
      setBusy(false);
    }
  };

  const setNameFromProfile = (profile: UserProfile) => {
    name.current = profile.name;
    email.current = profile.email;
    bio.current = profile.bio ?? '';
    campus.current = profile.campus ?? '';
    avatarUrl.current = profile.avatar_url ?? '';
  };

  const saveProfile = async () => {
    if (!user) return;
    setBusy(true);
    setError('');
    setMessage('');
    try {
      const form = new FormData();
      form.append('_method', 'PUT');
      form.append('name', name.current);
      form.append('bio', bio.current);
      form.append('campus', campus.current);
      form.append('avatar_url', avatarUrl.current);
      if (avatar) {
        if (Platform.OS === 'web' && avatar.file) {
          form.append('avatar', avatar.file);
        } else {
          const file = {
            uri: avatar.uri,
            name: avatar.fileName ?? 'profile-photo.jpg',
            type: avatar.mimeType ?? 'image/jpeg',
          };
          form.append('avatar', file as unknown as Blob);
        }
      }
      const result = await requestJson<UserProfile>('/auth/profile', {
        method: 'POST',
        body: form,
      });
      setUser(result.data);
      setAvatar(null);
      setNameFromProfile(result.data);
      setMessage('Your profile has been updated.');
    } catch (issue) {
      setError(issue instanceof Error ? issue.message : 'Your profile could not be saved.');
    } finally {
      setBusy(false);
    }
  };

  const pickAvatar = async () => {
    const permission = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!permission.granted) {
      Alert.alert('Photo access needed', 'Allow PASABUY to access your photos to choose a profile picture.');
      return;
    }

    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ['images'],
      allowsEditing: true,
      aspect: [1, 1],
      quality: 0.85,
    });
    if (!result.canceled) setAvatar(result.assets[0]);
  };

  const logout = async () => {
    setBusy(true);
    try {
      await requestJson<never>('/auth/logout', { method: 'POST' });
      setMessage('You are signed out.');
    } catch (issue) {
      setError(issue instanceof Error ? issue.message : 'The server could not sign you out.');
    } finally {
      await clearAuthToken();
      setUser(null);
      setListings([]);
      setBusy(false);
    }
  };

  const confirmRemoveListing = (listing: Listing) => {
    if (Platform.OS === 'web') {
      if (globalThis.confirm(`Remove "${listing.title}" from the marketplace?`)) {
        void removeListing(listing.id);
      }
      return;
    }

    Alert.alert('Remove listing?', `“${listing.title}” will no longer appear in the marketplace.`, [
      { text: 'Keep it', style: 'cancel' },
      { text: 'Remove', style: 'destructive', onPress: () => { void removeListing(listing.id); } },
    ]);
  };

  const removeListing = async (id: number) => {
    setBusy(true);
    setError('');
    try {
      await requestJson<never>(`/my-listings/${id}`, { method: 'DELETE' });
      setListings((current) => current.filter((listing) => listing.id !== id));
      setMessage('Listing removed.');
    } catch (issue) {
      setError(issue instanceof Error ? issue.message : 'The listing could not be removed.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <SafeAreaView edges={['top', 'bottom']} style={styles.safeArea}>
      <View style={styles.app}>
        <View style={styles.topBar}>
          <Text style={styles.topBarText}>✳  YOUR PASABUY ACCOUNT</Text>
          <Text style={styles.topBarLocation}>CAMPUS COMMUNITY</Text>
        </View>
        <View style={styles.header}>
          {user ? (
            <>
              <Pressable onPress={() => router.back()} style={styles.backButton}>
                <Text style={styles.backButtonText}>‹  Back</Text>
              </Pressable>
              <Text style={styles.headerRouteTitle}>Your account</Text>
              <View style={styles.routeHeaderSpacer} />
            </>
          ) : (
            <View style={styles.brand}>
              <View style={styles.brandIcon}>
                <Text style={styles.brandIconText}>✓</Text>
              </View>
              <View>
                <Text style={styles.brandName}>pasa<Text style={styles.brandNameAccent}>buy</Text></Text>
                <Text style={styles.brandCaption}>YOUR CAMPUS MARKETPLACE</Text>
              </View>
            </View>
          )}
        </View>
        <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={styles.contentArea}>
          <ScrollView contentContainerStyle={{ padding: 20, gap: 16 }} keyboardShouldPersistTaps="handled">
            {error ? <Text style={styles.formError}>{error}</Text> : null}
            {message ? <Text style={styles.formSubheading}>{message}</Text> : null}
            {user ? (
              <>
                <View style={styles.formCard}>
                  <Text style={styles.formHeading}>Your student profile</Text>
                  <Text style={styles.formSubheading}>Manage your profile and campus listings.</Text>
                  <Pressable accessibilityRole="button" onPress={() => void pickAvatar()} style={{ alignSelf: 'flex-start', marginVertical: 16 }}>
                    {avatar ? (
                      <Image source={{ uri: avatar.uri }} style={{ width: 76, height: 76, borderRadius: 38 }} />
                    ) : user.avatar_url ? (
                      <Image source={{ uri: user.avatar_url }} style={{ width: 76, height: 76, borderRadius: 38 }} />
                    ) : (
                      <View style={{ width: 76, height: 76, borderRadius: 38, backgroundColor: COLORS.paleGold, alignItems: 'center', justifyContent: 'center' }}>
                        <Text style={{ color: COLORS.navy, fontSize: 28, fontWeight: '700' }}>{user.name.slice(0, 1).toUpperCase()}</Text>
                      </View>
                    )}
                    <Text style={{ color: COLORS.goldDark, fontSize: 12, marginTop: 6 }}>Change photo</Text>
                  </Pressable>
                  <Text style={styles.fieldLabel}>Display name</Text>
                  <TextInput defaultValue={user.name} onChangeText={(value) => { name.current = value; }} style={styles.formInput} />
                  <Text style={styles.fieldLabel}>Email</Text>
                  <TextInput editable={false} value={user.email} style={styles.formInput} />
                  <Text style={styles.fieldLabel}>Campus</Text>
                  <TextInput defaultValue={user.campus ?? ''} onChangeText={(value) => { campus.current = value; }} placeholder="Your school or campus" style={styles.formInput} />
                  <Text style={styles.fieldLabel}>Short bio</Text>
                  <TextInput defaultValue={user.bio ?? ''} maxLength={500} multiline onChangeText={(value) => { bio.current = value; }} placeholder="A little about you" style={[styles.formInput, styles.multilineInput]} />
                  <Text style={styles.fieldLabel}>Or photo link</Text>
                  <TextInput autoCapitalize="none" defaultValue={user.avatar_url ?? ''} keyboardType="url" onChangeText={(value) => { avatarUrl.current = value; }} placeholder="https://..." style={styles.formInput} />
                  <Pressable disabled={busy} onPress={() => void saveProfile()} style={styles.primaryButton}>
                    <Text style={styles.primaryButtonText}>{busy ? 'Saving…' : 'Save profile'}</Text>
                  </Pressable>
                  <Pressable disabled={busy} onPress={() => router.replace('/')} style={[styles.primaryButton, { marginTop: 10 }]}>
                    <Text style={styles.primaryButtonText}>Continue to campus hub</Text>
                  </Pressable>
                  <Pressable disabled={busy} onPress={() => void logout()} style={[styles.primaryButton, { marginTop: 10, backgroundColor: COLORS.paleBlue }]}>
                    <Text style={[styles.primaryButtonText, { color: COLORS.navy }]}>Log out</Text>
                  </Pressable>
                </View>

                <View style={styles.formCard}>
                  <Text style={styles.formHeading}>Your listings</Text>
                  {listings.length ? listings.map((listing) => (
                    <View key={listing.id} style={{ borderTopWidth: 1, borderTopColor: COLORS.line, paddingVertical: 14 }}>
                      <Text style={{ color: COLORS.navy, fontWeight: '700' }}>{listing.title}</Text>
                      <Text style={{ color: COLORS.muted, fontSize: 12, marginTop: 3 }}>{listing.type === 'rent' ? 'Rent' : 'Sale'} · {listing.location}</Text>
                      <View style={{ flexDirection: 'row', gap: 10, marginTop: 10 }}>
                        <Pressable onPress={() => router.push({ pathname: '/sell', params: { editId: listing.id } })} style={styles.choiceChip}>
                          <Text style={styles.choiceChipText}>Edit</Text>
                        </Pressable>
                        <Pressable disabled={busy} onPress={() => confirmRemoveListing(listing)} style={styles.choiceChip}>
                          <Text style={styles.choiceChipText}>Remove</Text>
                        </Pressable>
                      </View>
                    </View>
                  )) : <Text style={styles.formSubheading}>You haven’t listed anything yet.</Text>}
                </View>
              </>
            ) : (
              <View style={styles.formCard}>
                <Text style={styles.formHeading}>{mode === 'register' ? 'Create your account' : mode === 'forgot' ? 'Reset your password' : 'Log in to PASABUY'}</Text>
                <Text style={styles.formSubheading}>Your account lets you post and manage school-related items.</Text>
                {mode === 'register' ? (
                  <>
                    <Text style={styles.fieldLabel}>Display name</Text>
                    <TextInput autoCapitalize="words" onChangeText={(value) => { name.current = value; }} placeholder="Your name" style={styles.formInput} />
                  </>
                ) : null}
                <Text style={styles.fieldLabel}>Email</Text>
                <TextInput autoCapitalize="none" keyboardType="email-address" onChangeText={(value) => { email.current = value; }} placeholder="you@example.com" style={styles.formInput} />
                {mode !== 'forgot' ? (
                  <>
                    <Text style={styles.fieldLabel}>Password</Text>
                    <TextInput onChangeText={(value) => { password.current = value; }} secureTextEntry style={styles.formInput} />
                  </>
                ) : null}
                {mode === 'register' ? (
                  <>
                    <Text style={styles.fieldLabel}>Confirm password</Text>
                    <TextInput onChangeText={(value) => { confirmation.current = value; }} secureTextEntry style={styles.formInput} />
                  </>
                ) : null}
                <Pressable disabled={busy} onPress={() => void submitAccountForm()} style={[styles.primaryButton, busy && styles.disabledButton]}>
                  {busy ? <ActivityIndicator color="#fff" /> : <Text style={styles.primaryButtonText}>{mode === 'register' ? 'Create account' : mode === 'forgot' ? 'Send reset link' : 'Log in'}</Text>}
                </Pressable>
                <View style={{ alignItems: 'center', gap: 12, marginTop: 18 }}>
                  <Pressable onPress={() => { setError(''); setMode(mode === 'register' ? 'login' : 'register'); }}>
                    <Text style={{ color: COLORS.goldDark }}>{mode === 'register' ? 'Already have an account? Log in' : 'New here? Create an account'}</Text>
                  </Pressable>
                  {mode === 'login' ? (
                    <Pressable onPress={() => { setError(''); setMode('forgot'); }}>
                      <Text style={{ color: COLORS.muted }}>Forgot your password?</Text>
                    </Pressable>
                  ) : null}
                  {mode === 'forgot' ? (
                    <Pressable onPress={() => setMode('login')}>
                      <Text style={{ color: COLORS.muted }}>Back to log in</Text>
                    </Pressable>
                  ) : null}
                </View>
              </View>
            )}
          </ScrollView>
        </KeyboardAvoidingView>
      </View>
    </SafeAreaView>
  );
}
