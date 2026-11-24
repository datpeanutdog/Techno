import { router } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useEffect, useState } from 'react';
import {
  ActivityIndicator,
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
import { FetchRequest, getAuthToken, requestJson } from '../src/lib/api';
import { MenuButton } from '../src/components/NavigationDrawer';
import { useAppTheme } from '../src/lib/theme';

const DURATION_UNITS = ['minutes', 'hours', 'days'] as const;
type DurationUnit = (typeof DURATION_UNITS)[number];

export default function FetchCreateScreen() {
  const { mode } = useAppTheme();
  const [itemName, setItemName] = useState('');
  const [details, setDetails] = useState('');
  const [budget, setBudget] = useState('');
  const [location, setLocation] = useState('');
  const [durationValue, setDurationValue] = useState('30');
  const [durationUnit, setDurationUnit] = useState<DurationUnit>('minutes');
  const [deliveryDurationValue, setDeliveryDurationValue] = useState('2');
  const [deliveryDurationUnit, setDeliveryDurationUnit] = useState<DurationUnit>('hours');
  const [posting, setPosting] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    let active = true;

    void getAuthToken()
      .then((token) => {
        if (active && !token) {
          router.replace({ pathname: '/account', params: { returnTo: 'fetch' } });
        }
      })
      .catch((issue: unknown) => {
        if (active) {
          setError(issue instanceof Error ? issue.message : 'Your account could not be verified.');
        }
      });

    return () => {
      active = false;
    };
  }, []);

  const postRequest = async () => {
    setPosting(true);
    setError('');

    try {
      await requestJson<FetchRequest>('/fetch/requests', {
        method: 'POST',
        body: JSON.stringify({
          item_name: itemName.trim(),
          details: details.trim(),
          budget: budget.trim() || null,
          delivery_location: location.trim(),
          duration_value: Number(durationValue),
          duration_unit: durationUnit,
          delivery_duration_value: Number(deliveryDurationValue),
          delivery_duration_unit: deliveryDurationUnit,
        }),
      });
      router.replace('/fetch');
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
          <Text style={styles.headerRouteTitle}>Post a fetch request</Text>
          <MenuButton />
        </View>
        <KeyboardAvoidingView
          behavior={Platform.OS === 'ios' ? 'padding' : undefined}
          style={styles.contentArea}
        >
          <ScrollView
            contentContainerStyle={styles.sellContent}
            keyboardShouldPersistTaps="handled"
            showsVerticalScrollIndicator={false}
          >
            <View style={styles.formCard}>
              <Text style={styles.formHeading}>Share what you need</Text>
              <Text style={styles.formSubheading}>Use a campus pickup point rather than your home address.</Text>
              {error ? <Text style={styles.formError}>{error}</Text> : null}
              <Text style={styles.fieldLabel}>Item you need *</Text>
              <TextInput
                maxLength={90}
                onChangeText={setItemName}
                placeholder="e.g. Bluebook and black pens"
                placeholderTextColor={COLORS.placeholder}
                style={styles.formInput}
                value={itemName}
              />
              <Text style={styles.fieldLabel}>Details *</Text>
              <TextInput
                maxLength={1000}
                multiline
                onChangeText={setDetails}
                placeholder="Add helpful details like size, quantity, or preferred brand."
                placeholderTextColor={COLORS.placeholder}
                style={[styles.formInput, styles.multilineInput]}
                textAlignVertical="top"
                value={details}
              />
              <Text style={styles.fieldLabel}>Budget (optional)</Text>
              <TextInput
                keyboardType="decimal-pad"
                onChangeText={setBudget}
                placeholder="₱"
                placeholderTextColor={COLORS.placeholder}
                style={styles.formInput}
                value={budget}
              />
              <Text style={styles.fieldLabel}>Campus delivery spot *</Text>
              <TextInput
                maxLength={100}
                onChangeText={setLocation}
                placeholder="e.g. Main library entrance"
                placeholderTextColor={COLORS.placeholder}
                style={styles.formInput}
                value={location}
              />
              <Text style={styles.fieldLabel}>How long should it stay live? *</Text>
              <View style={{ flexDirection: 'row', gap: 8 }}>
                <TextInput
                  keyboardType="number-pad"
                  onChangeText={setDurationValue}
                  placeholder="30"
                  placeholderTextColor={COLORS.placeholder}
                  style={[styles.formInput, { flex: 1 }]}
                  value={durationValue}
                />
                {DURATION_UNITS.map((unit) => (
                  <Pressable
                    accessibilityRole="button"
                    accessibilityState={{ selected: durationUnit === unit }}
                    key={unit}
                    onPress={() => setDurationUnit(unit)}
                    style={[styles.choiceChip, durationUnit === unit && styles.choiceChipSelected]}
                  >
                    <Text style={[styles.choiceChipText, durationUnit === unit && styles.choiceChipTextSelected]}>
                      {unit}
                    </Text>
                  </Pressable>
                ))}
              </View>
              <Text style={styles.formSubheading}>Choose 5 minutes to 1 day.</Text>
              <Text style={styles.fieldLabel}>Delivery deadline after posting *</Text>
              <View style={{ flexDirection: 'row', gap: 8 }}>
                <TextInput
                  keyboardType="number-pad"
                  onChangeText={setDeliveryDurationValue}
                  placeholder="2"
                  placeholderTextColor={COLORS.placeholder}
                  style={[styles.formInput, { flex: 1 }]}
                  value={deliveryDurationValue}
                />
                {DURATION_UNITS.map((unit) => (
                  <Pressable
                    accessibilityRole="button"
                    accessibilityState={{ selected: deliveryDurationUnit === unit }}
                    key={`delivery-${unit}`}
                    onPress={() => setDeliveryDurationUnit(unit)}
                    style={[styles.choiceChip, deliveryDurationUnit === unit && styles.choiceChipSelected]}
                  >
                    <Text style={[styles.choiceChipText, deliveryDurationUnit === unit && styles.choiceChipTextSelected]}>
                      {unit}
                    </Text>
                  </Pressable>
                ))}
              </View>
              <Text style={styles.formSubheading}>Choose 5 minutes to 30 days, later than the live listing expiration.</Text>
              <Pressable
                accessibilityRole="button"
                disabled={posting}
                onPress={() => void postRequest()}
                style={[styles.primaryButton, posting && styles.disabledButton]}
              >
                {posting ? <ActivityIndicator color="#fff" /> : <Text style={styles.primaryButtonText}>Post request</Text>}
              </Pressable>
            </View>
          </ScrollView>
        </KeyboardAvoidingView>
      </View>
    </SafeAreaView>
  );
}
