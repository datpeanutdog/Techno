import { router, useLocalSearchParams } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useEffect, useRef, useState } from 'react';
import { SafeAreaView } from 'react-native-safe-area-context';
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
import { COLORS, CATEGORY_ICONS, ChoiceChip, Label, styles } from '../App';
import { getAuthToken, Listing, ListingType, requestJson } from '../src/lib/api';

const categories = Object.keys(CATEGORY_ICONS);

export default function SellScreen() {
  const { editId: rawEditId } = useLocalSearchParams<{ editId?: string }>();
  const editId = typeof rawEditId === 'string' ? rawEditId : null;
  const title = useRef('');
  const description = useRef('');
  const price = useRef('');
  const [type, setType] = useState<ListingType>('sale');
  const [rentPeriod, setRentPeriod] = useState('day');
  const [category, setCategory] = useState(categories[0]);
  const location = useRef('');
  const imageUrl = useRef('');
  const [condition, setCondition] = useState('');
  const [posting, setPosting] = useState(false);
  const [loadingListing, setLoadingListing] = useState(true);
  const [listing, setListing] = useState<Listing | null>(null);
  const [error, setError] = useState('');

  useEffect(() => {
    let active = true;
    void (async () => {
      if (!await getAuthToken()) {
        router.replace({ pathname: '/account', params: { returnTo: 'sell' } });
        return;
      }

      if (!editId) {
        if (active) setLoadingListing(false);
        return;
      }

      try {
        const result = await requestJson<Listing>(`/my-listings/${editId}`);
        if (!active) return;
        const current = result.data;
        setListing(current);
        title.current = current.title;
        description.current = current.description;
        price.current = String(current.price);
        location.current = current.location;
        imageUrl.current = current.image_url ?? '';
        setType(current.type);
        setRentPeriod(current.rent_period ?? 'day');
        setCategory(current.category);
        setCondition(current.condition ?? '');
      } catch (issue) {
        if (active) setError(issue instanceof Error ? issue.message : 'This listing could not be loaded.');
      } finally {
        if (active) setLoadingListing(false);
      }
    })();

    return () => { active = false; };
  }, [editId]);

  const submitListing = async () => {
    setError('');
    setPosting(true);

    try {
      const result = await requestJson<Listing>(editId ? `/my-listings/${editId}` : '/listings', {
        method: editId ? 'PUT' : 'POST',
        body: JSON.stringify({
          title: title.current,
          description: description.current,
          price: Number(price.current),
          type,
          rent_period: type === 'rent' ? rentPeriod : null,
          category,
          location: location.current,
          image_url: imageUrl.current.trim() || null,
          condition: condition || null,
        }),
      });

      router.replace({ pathname: '/listing/[slug]', params: { slug: result.data.slug } });
    } catch (issue) {
      setError(issue instanceof Error ? issue.message : 'Your listing could not be posted.');
    } finally {
      setPosting(false);
    }
  };

  return (
    <SafeAreaView edges={['top', 'bottom']} style={styles.safeArea}>
      <View style={styles.app}>
      <StatusBar style="light" />
      <View style={styles.topBar}>
        <Text style={styles.topBarText}>✳  PASS IT ON TO YOUR CAMPUS</Text>
        <Text style={styles.topBarLocation}>SELL · RENT</Text>
      </View>
      <View style={styles.header}>
        <Pressable onPress={() => router.back()} style={styles.backButton}>
          <Text style={styles.backButtonText}>‹  Back</Text>
        </Pressable>
        <Text style={styles.headerRouteTitle}>{editId ? 'Edit listing' : 'New listing'}</Text>
        <View style={styles.routeHeaderSpacer} />
      </View>

      <KeyboardAvoidingView
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        keyboardVerticalOffset={Platform.OS === 'ios' ? 45 : 0}
        style={styles.contentArea}
      >
        {loadingListing ? (
          <View style={{ flex: 1, alignItems: 'center', justifyContent: 'center' }}>
            <ActivityIndicator color={COLORS.goldDark} />
          </View>
        ) : <ScrollView
          contentContainerStyle={styles.sellContent}
          keyboardShouldPersistTaps="handled"
          showsVerticalScrollIndicator={false}
        >
          <View style={styles.sellIntro}>
            <View style={styles.sellSparkle}>
              <Text style={styles.sellSparkleText}>✳</Text>
            </View>
            <Text style={styles.sellEyebrow}>MAKE CAMPUS LIFE EASIER</Text>
            <Text style={styles.sellTitle}>Pass a good find <Text style={styles.sellTitleAccent}>on.</Text></Text>
            <Text style={styles.sellDescription}>
              A textbook, calculator, or dorm essential could be exactly what another student needs.
            </Text>
          </View>

          <View style={styles.formCard}>
            <Text style={styles.formHeading}>Share with your campus</Text>
            <Text style={styles.formSubheading}>School-related listings only, please.</Text>
            {error ? <Text style={styles.formError}>{error}</Text> : null}

            <Label required>What are you listing?</Label>
            <TextInput
              maxLength={90}
              defaultValue={listing?.title}
              onChangeText={(value) => { title.current = value; }}
              placeholder="e.g. Biology textbook, lab coat"
              placeholderTextColor="#9699a4"
              style={styles.formInput}
            />

            <Label required>A few more details</Label>
            <TextInput
              multiline
              defaultValue={listing?.description}
              onChangeText={(value) => { description.current = value; }}
              placeholder="Condition, class, size, campus pickup details..."
              placeholderTextColor="#9699a4"
              style={[styles.formInput, styles.multilineInput]}
              textAlignVertical="top"
            />

            <Label required>Would you like to sell or rent?</Label>
            <View style={styles.segmentRow}>
              {(['sale', 'rent'] as ListingType[]).map((listingType) => (
                <ChoiceChip
                  key={listingType}
                  label={listingType === 'sale' ? 'Sell it' : 'Rent it out'}
                  selected={type === listingType}
                  onPress={() => setType(listingType)}
                />
              ))}
            </View>

            <View style={styles.formRow}>
              <View style={styles.formHalf}>
                <Label required>Price in pesos</Label>
                <View style={styles.priceInput}>
                  <Text style={styles.currencySign}>₱</Text>
                  <TextInput
                    keyboardType="decimal-pad"
                    defaultValue={listing ? String(listing.price) : undefined}
                    onChangeText={(value) => { price.current = value; }}
                    placeholder="0"
                    placeholderTextColor="#9699a4"
                    style={styles.priceField}
                  />
                </View>
              </View>
              {type === 'rent' ? (
                <View style={styles.formHalf}>
                  <Label required>Price per</Label>
                  <View style={styles.periodRow}>
                    {['day', 'week', 'month'].map((period) => (
                      <ChoiceChip
                        key={period}
                        label={period}
                        selected={rentPeriod === period}
                        onPress={() => setRentPeriod(period)}
                      />
                    ))}
                  </View>
                </View>
              ) : null}
            </View>

            <Label required>School category</Label>
            <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.formCategoryScroll}>
              <View style={styles.formCategoryRow}>
                {categories.map((item) => (
                  <ChoiceChip
                    key={item}
                    label={`${CATEGORY_ICONS[item]}  ${item}`}
                    selected={category === item}
                    onPress={() => setCategory(item)}
                  />
                ))}
              </View>
            </ScrollView>

            <Label required>Campus or pickup area</Label>
            <TextInput
              maxLength={100}
              defaultValue={listing?.location}
              onChangeText={(value) => { location.current = value; }}
              placeholder="e.g. Main campus, Science building"
              placeholderTextColor="#9699a4"
              style={styles.formInput}
            />

            <Label>Photo link (optional)</Label>
            <TextInput
              autoCapitalize="none"
              defaultValue={listing?.image_url ?? undefined}
              keyboardType="url"
              onChangeText={(value) => { imageUrl.current = value; }}
              placeholder="https://..."
              placeholderTextColor="#9699a4"
              style={styles.formInput}
            />

            <Label>Condition (optional)</Label>
            <View style={styles.segmentRow}>
              {['Brand new', 'Like new', 'Good', 'Fair'].map((item) => (
                <ChoiceChip
                  key={item}
                  label={item}
                  selected={condition === item}
                  onPress={() => setCondition(condition === item ? '' : item)}
                />
              ))}
            </View>

            <Pressable
              accessibilityRole="button"
              disabled={posting}
              onPress={() => void submitListing()}
              style={[styles.primaryButton, styles.postButton, posting && styles.disabledButton]}
            >
              {posting ? (
                <ActivityIndicator color="#fff" />
              ) : (
                <Text style={styles.primaryButtonText}>{editId ? 'Save listing changes  →' : 'Post my campus find  →'}</Text>
              )}
            </Pressable>
            <Text style={styles.formFinePrint}>Meet in a public place and arrange a safe campus handoff.</Text>
          </View>
        </ScrollView>}
      </KeyboardAvoidingView>
      </View>
    </SafeAreaView>
  );
}
