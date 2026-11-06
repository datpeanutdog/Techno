import AsyncStorage from '@react-native-async-storage/async-storage';
import { StatusBar } from 'expo-status-bar';
import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';
import { useCallback, useMemo, useState } from 'react';
import { SafeAreaView } from 'react-native-safe-area-context';
import {
  ActivityIndicator,
  Image,
  Platform,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import {
  FAVORITES_KEY,
  Listing,
  ListingType,
  clearAuthToken,
  getAuthToken,
  requestJson,
  UserProfile,
} from './src/lib/api';

type Tab = 'buy' | 'rent' | 'saved';

export const COLORS = {
  navy: '#202348',
  navyDeep: '#171a39',
  gold: '#e9aa31',
  goldDark: '#92630d',
  cream: '#fcfaf5',
  white: '#fffefa',
  ink: '#292c43',
  muted: '#777d8c',
  line: '#e9e7df',
  green: '#496d58',
  paleGold: '#f5efdf',
  paleBlue: '#e9ebf3',
};

export const CATEGORY_ICONS: Record<string, string> = {
  'Books & Notes': '▤',
  'School Supplies': '✎',
  'Tech & Calculators': '▦',
  Uniforms: '♧',
  'Dorm Essentials': '⌂',
  'Campus Services': '✦',
};

const DEFAULT_CATEGORIES = Object.keys(CATEGORY_ICONS);

export function formatMobilePrice(price: string | number) {
  return `₱${Number(price).toLocaleString('en-PH', {
    maximumFractionDigits: 2,
  })}`;
}

export function Label({ children, required = false }: { children: string; required?: boolean }) {
  return (
    <Text style={styles.fieldLabel}>
      {children}
      {required ? <Text style={styles.required}> *</Text> : null}
    </Text>
  );
}

export function ChoiceChip({
  label,
  selected,
  onPress,
}: {
  label: string;
  selected: boolean;
  onPress: () => void;
}) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityState={{ selected }}
      onPress={onPress}
      style={[styles.choiceChip, selected && styles.choiceChipSelected]}
    >
      <Text style={[styles.choiceChipText, selected && styles.choiceChipTextSelected]}>{label}</Text>
    </Pressable>
  );
}

function ListingCard({
  listing,
  saved,
  onOpen,
  onFavorite,
}: {
  listing: Listing;
  saved: boolean;
  onOpen: () => void;
  onFavorite: () => void;
}) {
  return (
    <View style={styles.listingCard}>
      <Pressable accessibilityRole="button" onPress={onOpen} style={styles.cardImageWrap}>
        {listing.image_url ? (
          <Image source={{ uri: listing.image_url }} style={styles.cardImage} />
        ) : (
          <View style={[styles.cardImagePlaceholder, styles.imagePlaceholder]}>
            <Text style={styles.placeholderIcon}>{CATEGORY_ICONS[listing.category] ?? '✦'}</Text>
          </View>
        )}
        <View style={[styles.typeBadge, listing.type === 'rent' && styles.rentBadge]}>
          <Text style={[styles.typeBadgeText, listing.type === 'rent' && styles.rentBadgeText]}>
            {listing.type === 'rent' ? 'FOR RENT' : 'FOR SALE'}
          </Text>
        </View>
      </Pressable>
      <Pressable
        accessibilityLabel={saved ? 'Remove saved item' : 'Save item'}
        accessibilityRole="button"
        accessibilityState={{ selected: saved }}
        onPress={onFavorite}
        style={styles.favoriteButton}
      >
        <Text style={[styles.favoriteIcon, saved && styles.favoriteIconSaved]}>{saved ? '♥' : '♡'}</Text>
      </Pressable>
      <Pressable onPress={onOpen} style={styles.cardContent}>
        <View style={styles.cardMetaRow}>
          <Text numberOfLines={1} style={styles.cardCategory}>{listing.category}</Text>
          {listing.condition ? <Text style={styles.cardCondition}>{listing.condition}</Text> : null}
        </View>
        <Text numberOfLines={2} style={styles.cardTitle}>{listing.title}</Text>
        <Text numberOfLines={1} style={styles.cardLocation}>⌖  {listing.location}</Text>
        <View style={styles.cardBottom}>
          <Text numberOfLines={1} style={styles.cardPrice}>
            {formatMobilePrice(listing.price)}
            {listing.type === 'rent' && listing.rent_period ? (
              <Text style={styles.pricePeriod}> / {listing.rent_period}</Text>
            ) : null}
          </Text>
          <Text style={styles.rating}>★ {Number(listing.seller_rating).toFixed(1)}</Text>
        </View>
      </Pressable>
    </View>
  );
}

export default function App() {
  const params = useLocalSearchParams<{ mode?: string; view?: string }>();
  const routerMode = Array.isArray(params.mode) ? params.mode[0] : params.mode;
  const routerView = Array.isArray(params.view) ? params.view[0] : params.view;
  const [tab, setTab] = useState<Tab>(
    routerView === 'saved' ? 'saved' : routerMode === 'rent' ? 'rent' : 'buy',
  );
  const [authChecked, setAuthChecked] = useState(false);
  const mode: ListingType = tab === 'rent' ? 'rent' : 'sale';

  const [listings, setListings] = useState<Listing[]>([]);
  const [categories, setCategories] = useState(DEFAULT_CATEGORIES);
  const [selectedCategory, setSelectedCategory] = useState<string | null>(null);
  const [savedIds, setSavedIds] = useState<number[]>([]);
  const [query, setQuery] = useState('');
  const [location, setLocation] = useState('');
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [loadError, setLoadError] = useState('');
  const [notice, setNotice] = useState('');

  useFocusEffect(
    useCallback(() => {
      let active = true;
      setAuthChecked(false);

      void (async () => {
        const token = await getAuthToken();
        if (!active) return;

        if (!token) {
          router.replace('/account');
          return;
        }

        try {
          await requestJson<UserProfile>('/auth/me');
          if (active) setAuthChecked(true);
        } catch (issue) {
          const detail = issue instanceof Error ? issue.message : '';
          if (detail.includes('(401)') || detail.includes('(Unauthenticated')) {
            await clearAuthToken();
            if (active) router.replace('/account');
            return;
          }
          if (active) setAuthChecked(true);
        }
      })();

      return () => { active = false; };
    }, []),
  );

  const loadListings = useCallback(async () => {
    setLoadError('');

    try {
      const modes: ListingType[] = tab === 'saved' ? ['sale', 'rent'] : [mode];
      const results = await Promise.all(modes.map((listingMode) => {
        const searchParams = new URLSearchParams({ mode: listingMode });
        if (query.trim()) searchParams.set('q', query.trim());
        if (location.trim()) searchParams.set('location', location.trim());
        if (selectedCategory) searchParams.set('category', selectedCategory);

        return requestJson<Listing[]>(`/listings?${searchParams.toString()}`);
      }));

      setListings(results.flatMap((result) => result.data));
      if (results[0]?.categories) setCategories(results[0].categories);
    } catch (error) {
      setLoadError(error instanceof Error ? error.message : 'Something went wrong while loading listings.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [location, mode, query, selectedCategory, tab]);

  useFocusEffect(
    useCallback(() => {
      if (!authChecked) return;
      const timeout = setTimeout(() => {
        void loadListings();
      }, query || location ? 280 : 0);

      return () => clearTimeout(timeout);
    }, [authChecked, loadListings, query, location]),
  );

  useFocusEffect(
    useCallback(() => {
      let active = true;

      AsyncStorage.getItem(FAVORITES_KEY)
        .then((value) => {
          if (active && value) setSavedIds(JSON.parse(value) as number[]);
        })
        .catch(() => {
          if (active) setNotice('Saved items could not be loaded on this device.');
        });

      return () => {
        active = false;
      };
    }, []),
  );

  const visibleListings = useMemo(() => {
    if (tab !== 'saved') return listings;

    return listings.filter((listing) => savedIds.includes(listing.id));
  }, [listings, savedIds, tab]);

  const changeTab = (nextTab: Tab) => {
    setNotice('');
    if (nextTab !== tab) setLoading(true);
    setTab(nextTab);
  };

  const toggleFavorite = async (listingId: number) => {
    const next = savedIds.includes(listingId)
      ? savedIds.filter((id) => id !== listingId)
      : [...savedIds, listingId];

    setSavedIds(next);

    try {
      await AsyncStorage.setItem(FAVORITES_KEY, JSON.stringify(next));
    } catch {
      setNotice('Could not save your favorite on this device.');
    }
  };

  const openListing = (listing: Listing) => {
    router.push({ pathname: '/listing/[slug]', params: { slug: listing.slug } });
  };

  const renderListings = () => (
    <>
      {tab !== 'saved' ? (
        <>
          <View style={styles.searchBox}>
            <Text style={styles.searchGlyph}>⌕</Text>
            <TextInput
              accessibilityLabel="Search school listings"
              onChangeText={setQuery}
              placeholder="Books, calculators, supplies..."
              placeholderTextColor="#9699a4"
              returnKeyType="search"
              style={styles.searchInput}
              value={query}
            />
            {query ? (
              <Pressable accessibilityLabel="Clear search" onPress={() => setQuery('')}>
                <Text style={styles.clearSearch}>×</Text>
              </Pressable>
            ) : null}
          </View>

          <View style={[styles.searchBox, styles.locationBox]}>
            <Text style={styles.locationGlyph}>⌖</Text>
            <TextInput
              accessibilityLabel="Filter by campus"
              onChangeText={setLocation}
              placeholder="Campus or pickup area"
              placeholderTextColor="#9699a4"
              returnKeyType="search"
              style={styles.searchInput}
              value={location}
            />
          </View>

          <ScrollView contentContainerStyle={styles.categoryRow} horizontal showsHorizontalScrollIndicator={false}>
            <ChoiceChip label="Everything" selected={selectedCategory === null} onPress={() => setSelectedCategory(null)} />
            {categories.map((category) => (
              <ChoiceChip
                key={category}
                label={`${CATEGORY_ICONS[category] ?? '✦'}  ${category}`}
                selected={selectedCategory === category}
                onPress={() => setSelectedCategory(category)}
              />
            ))}
          </ScrollView>

          <View style={styles.sectionTitleRow}>
            <View>
              <Text style={styles.sectionEyebrow}>{mode === 'rent' ? 'BORROW FOR CLASS' : 'STUDENT-TO-STUDENT'}</Text>
              <Text style={styles.sectionTitle}>
                {mode === 'rent' ? 'Good things to rent' : 'Campus finds for you'}
              </Text>
            </View>
            <Pressable
              accessibilityLabel="Refresh listings"
              onPress={() => {
                setRefreshing(true);
                void loadListings();
              }}
              style={styles.refreshButton}
            >
              <Text style={styles.refreshIcon}>↻</Text>
            </Pressable>
          </View>
        </>
      ) : (
        <View style={styles.sectionTitleRow}>
          <View>
            <Text style={styles.sectionEyebrow}>YOUR LITTLE WISHLIST</Text>
            <Text style={styles.sectionTitle}>Saved campus finds</Text>
          </View>
          <Text style={styles.savedCount}>{savedIds.length} saved</Text>
        </View>
      )}

      {notice ? <Text style={styles.inlineNotice}>{notice}</Text> : null}

      {loading ? (
        <View style={styles.loadingState}>
          <ActivityIndicator color={COLORS.goldDark} />
          <Text style={styles.helperText}>Finding good things...</Text>
        </View>
      ) : loadError ? (
        <View style={styles.emptyState}>
          <Text style={styles.emptyIcon}>⌁</Text>
          <Text style={styles.emptyTitle}>Can't reach campus just yet.</Text>
          <Text style={styles.emptyDescription}>{loadError}</Text>
          <Pressable style={styles.primaryButton} onPress={() => void loadListings()}>
            <Text style={styles.primaryButtonText}>Try again</Text>
          </Pressable>
        </View>
      ) : visibleListings.length ? (
        <View style={styles.listingGrid}>
          {Array.from({ length: Math.ceil(visibleListings.length / 2) }, (_, rowIndex) => (
            <View key={rowIndex} style={styles.listingRow}>
              {visibleListings.slice(rowIndex * 2, rowIndex * 2 + 2).map((listing) => (
                <ListingCard
                  key={listing.id}
                  listing={listing}
                  saved={savedIds.includes(listing.id)}
                  onOpen={() => openListing(listing)}
                  onFavorite={() => void toggleFavorite(listing.id)}
                />
              ))}
              {visibleListings.length % 2 === 1 && rowIndex === Math.floor(visibleListings.length / 2) ? (
                <View style={styles.cardSpacer} />
              ) : null}
            </View>
          ))}
        </View>
      ) : (
        <View style={styles.emptyState}>
          <Text style={styles.emptyIcon}>{tab === 'saved' ? '♡' : '⌕'}</Text>
          <Text style={styles.emptyTitle}>
            {tab === 'saved' ? 'No saved finds yet.' : 'No campus finds just yet.'}
          </Text>
          <Text style={styles.emptyDescription}>
            {tab === 'saved'
              ? 'Tap the heart on something you like and it will be waiting here.'
              : 'Try a different search or category. Your next school-day essential is out there.'}
          </Text>
        </View>
      )}
    </>
  );

  const screenTitle = tab === 'saved'
    ? 'Saved finds'
    : tab === 'rent'
      ? 'Things to rent'
      : 'Find a campus essential';

  if (!authChecked) {
    return (
      <SafeAreaView edges={['top', 'bottom']} style={styles.safeArea}>
        <View style={styles.loadingState}>
          <ActivityIndicator color={COLORS.goldDark} />
          <Text style={styles.helperText}>Opening your campus hub...</Text>
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView edges={['top', 'bottom']} style={styles.safeArea}>
      <View style={styles.app}>
      <StatusBar style="light" />
      <View style={styles.topBar}>
        <Text style={styles.topBarText}>✳  GOOD FINDS, SECOND SEMESTERS</Text>
        <Text style={styles.topBarLocation}>CAMPUS LIFE, SHARED</Text>
      </View>

      <View style={styles.header}>
        <Pressable
          accessibilityLabel="PASABUY home"
          onPress={() => changeTab('buy')}
          style={styles.brand}
        >
          <View style={styles.brandIcon}>
            <Text style={styles.brandIconText}>✓</Text>
          </View>
          <View>
            <Text style={styles.brandName}>pasa<Text style={styles.brandNameAccent}>buy</Text></Text>
            <Text style={styles.brandCaption}>YOUR CAMPUS MARKETPLACE</Text>
          </View>
        </Pressable>
        <View style={{ flexDirection: 'row', gap: 8 }}>
          <Pressable
            accessibilityLabel="Open your messages"
            onPress={() => router.push('/messages')}
            style={[styles.headerAddButton, { backgroundColor: COLORS.paleBlue }]}
          >
            <Text style={[styles.headerAddText, { color: COLORS.navy, fontSize: 17 }]}>✉</Text>
          </Pressable>
          <Pressable
            accessibilityLabel="Open your account"
            onPress={() => router.push('/account')}
            style={[styles.headerAddButton, { backgroundColor: COLORS.navy }]}
          >
            <Text style={[styles.headerAddText, { color: COLORS.white, fontSize: 17 }]}>☺</Text>
          </Pressable>
          <Pressable
            accessibilityLabel="List a school item"
            onPress={() => router.push('/sell')}
            style={styles.headerAddButton}
          >
            <Text style={styles.headerAddText}>＋</Text>
          </Pressable>
        </View>
      </View>

      <View style={styles.modeTabs}>
        {(['buy', 'rent'] as Tab[]).map((modeTab) => (
          <Pressable
            key={modeTab}
            accessibilityRole="tab"
            accessibilityState={{ selected: tab === modeTab }}
            onPress={() => changeTab(modeTab)}
            style={({ pressed }) => [
              styles.modeTab,
              tab === modeTab && styles.modeTabActive,
              pressed && styles.tabPressed,
            ]}
          >
            <Text style={[styles.modeTabText, tab === modeTab && styles.modeTabTextActive]}>
              {modeTab === 'buy' ? 'Buy' : 'Rent'}
            </Text>
          </Pressable>
        ))}
        <Text style={styles.modeTabTag}>STUDENT TO STUDENT</Text>
      </View>

      <View style={styles.pageHeading}>
        <Text style={styles.pageTitle}>{screenTitle}</Text>
        <Text style={styles.pageSubtitle}>
          {tab === 'saved'
            ? 'Your shortlist of good things'
            : 'Books, supplies & little school-day saves'}
        </Text>
      </View>

      <ScrollView
        contentContainerStyle={styles.mainScrollContent}
        keyboardShouldPersistTaps="handled"
        refreshControl={
          <RefreshControl
            colors={[COLORS.goldDark]}
            onRefresh={() => {
              setRefreshing(true);
              void loadListings();
            }}
            refreshing={refreshing}
            tintColor={COLORS.goldDark}
          />
        }
        showsVerticalScrollIndicator={false}
        style={styles.pageScroll}
      >
        {renderListings()}
      </ScrollView>

      <View style={styles.bottomBar}>
        {([
          { id: 'buy' as const, label: 'Browse', icon: '⌕' },
          { id: 'rent' as const, label: 'Rent', icon: '↗' },
          { id: 'saved' as const, label: 'Saved', icon: savedIds.length ? '♥' : '♡' },
          { id: 'sell' as const, label: 'Sell', icon: '＋' },
        ]).map((item) => (
          <Pressable
            key={item.id}
            accessibilityRole="tab"
            accessibilityState={{ selected: tab === item.id }}
            onPress={() => item.id === 'sell' ? router.push('/sell') : changeTab(item.id)}
            style={({ pressed }) => [
              styles.bottomTab,
              tab === item.id && styles.bottomTabActive,
              item.id === 'sell' && styles.bottomTabSell,
              pressed && styles.tabPressed,
            ]}
          >
            <Text
              style={[
                styles.bottomIcon,
                tab === item.id && styles.bottomIconActive,
                item.id === 'sell' && styles.bottomIconSell,
              ]}
            >
              {item.icon}
            </Text>
            <Text
              style={[
                styles.bottomLabel,
                tab === item.id && styles.bottomLabelActive,
                item.id === 'sell' && styles.bottomLabelSell,
              ]}
            >
              {item.label}
            </Text>
          </Pressable>
        ))}
      </View>
      </View>
    </SafeAreaView>
  );
}

export const styles = StyleSheet.create({
  app: { flex: 1, backgroundColor: COLORS.cream },
  safeArea: { flex: 1, backgroundColor: COLORS.navyDeep },
  contentArea: { flex: 1 },
  topBar: { height: 30, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 17, backgroundColor: COLORS.navyDeep },
  topBarText: { color: '#f4e7c8', fontSize: 8, fontWeight: '700', letterSpacing: 0.9 },
  topBarLocation: { color: '#d3d2e0', fontSize: 7, fontWeight: '600', letterSpacing: 0.7 },
  header: { height: 68, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 20, borderBottomWidth: 1, borderBottomColor: COLORS.line, backgroundColor: COLORS.white },
  brand: { flexDirection: 'row', alignItems: 'center', gap: 9 },
  brandIcon: { width: 35, height: 35, alignItems: 'center', justifyContent: 'center', borderRadius: 11, backgroundColor: COLORS.navy },
  brandIconText: { color: COLORS.gold, fontSize: 20, fontWeight: '800' },
  brandName: { color: COLORS.navy, fontSize: 21, fontWeight: '800', letterSpacing: -1.3 },
  brandNameAccent: { color: COLORS.goldDark },
  brandCaption: { marginTop: 1, color: '#7b7f90', fontSize: 7, fontWeight: '700', letterSpacing: 1 },
  headerAddButton: { width: 37, height: 37, alignItems: 'center', justifyContent: 'center', borderRadius: 12, backgroundColor: COLORS.gold },
  headerAddText: { color: COLORS.navy, fontSize: 23, fontWeight: '400', lineHeight: 27 },
  modeTabs: { minHeight: 60, flexDirection: 'row', alignItems: 'center', paddingHorizontal: 17, backgroundColor: COLORS.cream },
  modeTab: { minWidth: 65, minHeight: 38, alignItems: 'center', justifyContent: 'center', marginRight: 7, paddingHorizontal: 14, borderWidth: 1, borderColor: COLORS.line, borderRadius: 10, backgroundColor: COLORS.white },
  modeTabActive: { borderColor: '#d8c99e', backgroundColor: COLORS.paleGold, elevation: 2, shadowColor: COLORS.navy, shadowOffset: { width: 0, height: 1 }, shadowOpacity: 0.1, shadowRadius: 4 },
  modeTabText: { color: '#7e8290', fontSize: 12, fontWeight: '600' },
  modeTabTextActive: { color: COLORS.navy, fontWeight: '800' },
  modeTabTag: { marginLeft: 'auto', alignSelf: 'center', color: '#8a8b94', fontSize: 7, fontWeight: '700', letterSpacing: 0.9 },
  pageHeading: { paddingHorizontal: 20, paddingTop: 17, paddingBottom: 12 },
  pageTitle: { color: COLORS.navy, fontSize: 19, fontWeight: '700', letterSpacing: -0.5 },
  pageSubtitle: { marginTop: 4, color: COLORS.muted, fontSize: 10 },
  headerRouteTitle: { color: COLORS.navy, fontSize: 12, fontWeight: '700' },
  routeHeaderSpacer: { width: 52 },
  pageScroll: { flex: 1 },
  mainScrollContent: { flexGrow: 1, paddingBottom: 10 },
  searchBox: { minHeight: 45, flexDirection: 'row', alignItems: 'center', gap: 10, marginHorizontal: 17, paddingHorizontal: 12, borderWidth: 1, borderColor: COLORS.line, borderRadius: 11, backgroundColor: COLORS.white },
  locationBox: { minHeight: 40, marginTop: 8 },
  searchGlyph: { color: COLORS.goldDark, fontSize: 24, lineHeight: 28 },
  locationGlyph: { color: '#78819a', fontSize: 20 },
  searchInput: { flex: 1, paddingVertical: 8, color: COLORS.ink, fontSize: 11 },
  clearSearch: { paddingHorizontal: 4, color: COLORS.muted, fontSize: 20 },
  categoryRow: { gap: 7, paddingHorizontal: 17, paddingVertical: 12 },
  choiceChip: { minHeight: 32, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 12, borderWidth: 1, borderColor: COLORS.line, borderRadius: 18, backgroundColor: COLORS.white },
  choiceChipSelected: { borderColor: '#c4ae7b', backgroundColor: COLORS.paleGold },
  choiceChipText: { color: '#666c7d', fontSize: 9, fontWeight: '600' },
  choiceChipTextSelected: { color: COLORS.navy, fontWeight: '700' },
  sectionTitleRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginHorizontal: 18, marginTop: 4, marginBottom: 10 },
  sectionEyebrow: { color: '#878b97', fontSize: 7, fontWeight: '700', letterSpacing: 1.15 },
  sectionTitle: { marginTop: 3, color: COLORS.navy, fontSize: 15, fontWeight: '700', letterSpacing: -0.2 },
  refreshButton: { width: 32, height: 32, alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: COLORS.line, borderRadius: 10, backgroundColor: COLORS.white },
  refreshIcon: { color: COLORS.navy, fontSize: 20 },
  savedCount: { color: COLORS.muted, fontSize: 9 },
  inlineNotice: { marginHorizontal: 18, marginBottom: 8, color: COLORS.green, fontSize: 10 },
  listingGrid: { paddingHorizontal: 17, paddingBottom: 18 },
  listingRow: { flexDirection: 'row', gap: 11, marginBottom: 11 },
  listingCard: { flex: 1, minWidth: 0, overflow: 'hidden', borderWidth: 1, borderColor: COLORS.line, borderRadius: 11, backgroundColor: COLORS.white },
  cardSpacer: { flex: 1 },
  cardImageWrap: { height: 136, overflow: 'hidden', backgroundColor: '#efede6' },
  cardImage: { width: '100%', height: '100%', objectFit: 'cover' },
  cardImagePlaceholder: { width: '100%', height: '100%' },
  imagePlaceholder: { alignItems: 'center', justifyContent: 'center', backgroundColor: '#f2eddf' },
  placeholderIcon: { color: COLORS.goldDark, fontSize: 36 },
  typeBadge: { position: 'absolute', top: 8, left: 8, paddingHorizontal: 7, paddingVertical: 5, borderRadius: 5, backgroundColor: '#faf0d9' },
  typeBadgeText: { color: '#77570f', fontSize: 7, fontWeight: '800', letterSpacing: 0.4 },
  rentBadge: { backgroundColor: COLORS.paleBlue },
  rentBadgeText: { color: COLORS.navy },
  favoriteButton: { position: 'absolute', top: 7, right: 7, width: 29, height: 29, alignItems: 'center', justifyContent: 'center', borderRadius: 16, backgroundColor: '#fffefaed' },
  favoriteIcon: { color: '#5d6272', fontSize: 18, lineHeight: 21 },
  favoriteIconSaved: { color: '#c9595f' },
  cardContent: { paddingHorizontal: 9, paddingTop: 9, paddingBottom: 9 },
  cardMetaRow: { minHeight: 13, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 4 },
  cardCategory: { flex: 1, color: '#727789', fontSize: 7, fontWeight: '700', letterSpacing: 0.4 },
  cardCondition: { color: '#999ca5', fontSize: 7 },
  cardTitle: { minHeight: 31, marginTop: 5, color: '#34374e', fontSize: 11, fontWeight: '700', lineHeight: 15 },
  cardLocation: { marginTop: 4, color: '#858a97', fontSize: 8 },
  cardBottom: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 5, marginTop: 9, paddingTop: 7, borderTopWidth: 1, borderTopColor: '#f0eee8' },
  cardPrice: { flexShrink: 1, color: COLORS.navy, fontSize: 12, fontWeight: '800' },
  pricePeriod: { color: '#858a97', fontSize: 8, fontWeight: '400' },
  rating: { color: '#797d88', fontSize: 8 },
  loadingState: { alignItems: 'center', gap: 9, paddingVertical: 48 },
  helperText: { color: COLORS.muted, fontSize: 10 },
  emptyState: { alignItems: 'center', paddingHorizontal: 30, paddingVertical: 43 },
  emptyIcon: { width: 46, height: 46, borderRadius: 24, backgroundColor: COLORS.paleGold, color: COLORS.goldDark, fontSize: 30, lineHeight: 46, textAlign: 'center' },
  emptyTitle: { marginTop: 13, color: COLORS.navy, fontSize: 15, fontWeight: '700', textAlign: 'center' },
  emptyDescription: { marginTop: 7, color: COLORS.muted, fontSize: 10, lineHeight: 16, textAlign: 'center' },
  primaryButton: { minHeight: 43, alignItems: 'center', justifyContent: 'center', marginTop: 15, paddingHorizontal: 20, borderRadius: 9, backgroundColor: COLORS.navy },
  primaryButtonText: { color: '#fff', fontSize: 11, fontWeight: '700' },
  disabledButton: { opacity: 0.65 },
  detailContent: { paddingHorizontal: 17, paddingBottom: 22 },
  backButton: { alignSelf: 'flex-start', marginBottom: 12, paddingVertical: 5 },
  backButtonText: { color: COLORS.goldDark, fontSize: 10, fontWeight: '600' },
  detailImage: { width: '100%', height: 255, borderRadius: 12, backgroundColor: '#efede6', objectFit: 'cover' },
  detailPanel: { paddingTop: 15 },
  detailTopline: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  detailCategory: { color: '#727789', fontSize: 8, fontWeight: '700', letterSpacing: 0.7 },
  detailRentLabel: { color: COLORS.navy },
  detailTitle: { marginTop: 8, color: COLORS.navy, fontSize: 22, fontWeight: '700', letterSpacing: -0.7 },
  detailPrice: { marginTop: 6, color: COLORS.goldDark, fontSize: 21, fontWeight: '800' },
  detailLocation: { marginTop: 6, color: '#838895', fontSize: 10 },
  divider: { height: 1, marginVertical: 15, backgroundColor: COLORS.line },
  detailHeading: { color: COLORS.ink, fontSize: 12, fontWeight: '700' },
  detailDescription: { marginTop: 7, color: '#707687', fontSize: 11, lineHeight: 18 },
  conditionPill: { alignSelf: 'flex-start', marginTop: 10, paddingHorizontal: 9, paddingVertical: 6, borderRadius: 14, backgroundColor: COLORS.paleGold, color: '#725516', fontSize: 9, fontWeight: '600' },
  sellerCard: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 17, padding: 11, borderWidth: 1, borderColor: COLORS.line, borderRadius: 10, backgroundColor: COLORS.white },
  sellerAvatar: { width: 36, height: 36, alignItems: 'center', justifyContent: 'center', borderRadius: 20, backgroundColor: COLORS.paleGold },
  sellerAvatarImage: { width: 36, height: 36, borderRadius: 20 },
  sellerInitials: { color: COLORS.goldDark, fontSize: 10, fontWeight: '800' },
  sellerCopy: { flex: 1 },
  sellerName: { color: COLORS.ink, fontSize: 11, fontWeight: '700' },
  sellerSubline: { marginTop: 3, color: COLORS.muted, fontSize: 8 },
  detailHeart: { color: '#636879', fontSize: 23 },
  safetyNote: { flexDirection: 'row', gap: 9, marginTop: 11, padding: 11, borderRadius: 9, backgroundColor: '#f5f2e9' },
  safetyIcon: { color: COLORS.goldDark, fontSize: 14 },
  safetyText: { flex: 1, color: '#727887', fontSize: 9, lineHeight: 15 },
  sellContent: { paddingHorizontal: 17, paddingBottom: 25 },
  sellIntro: { paddingVertical: 7 },
  sellSparkle: { width: 34, height: 34, alignItems: 'center', justifyContent: 'center', borderRadius: 12, backgroundColor: COLORS.paleGold },
  sellSparkleText: { color: COLORS.goldDark, fontSize: 20 },
  sellEyebrow: { marginTop: 11, color: COLORS.goldDark, fontSize: 8, fontWeight: '800', letterSpacing: 1 },
  sellTitle: { marginTop: 5, color: COLORS.navy, fontSize: 25, fontWeight: '700', letterSpacing: -0.8 },
  sellTitleAccent: { color: COLORS.goldDark, fontFamily: Platform.OS === 'ios' ? 'Georgia' : 'serif', fontStyle: 'italic' },
  sellDescription: { maxWidth: 340, marginTop: 5, color: COLORS.muted, fontSize: 10, lineHeight: 16 },
  formCard: { marginTop: 13, padding: 15, borderWidth: 1, borderColor: COLORS.line, borderRadius: 12, backgroundColor: COLORS.white },
  formHeading: { color: COLORS.navy, fontSize: 15, fontWeight: '700' },
  formSubheading: { marginTop: 4, color: COLORS.muted, fontSize: 9 },
  fieldLabel: { marginTop: 13, marginBottom: 6, color: '#4f5466', fontSize: 9, fontWeight: '700' },
  required: { color: COLORS.goldDark },
  formInput: { minHeight: 41, paddingHorizontal: 10, paddingVertical: 9, borderWidth: 1, borderColor: COLORS.line, borderRadius: 7, backgroundColor: '#fffefa', color: COLORS.ink, fontSize: 10 },
  multilineInput: { minHeight: 78 },
  segmentRow: { flexDirection: 'row', gap: 8 },
  formRow: { flexDirection: 'row', gap: 10 },
  formHalf: { flex: 1, minWidth: 0 },
  priceInput: { minHeight: 41, flexDirection: 'row', alignItems: 'center', gap: 7, paddingHorizontal: 10, borderWidth: 1, borderColor: COLORS.line, borderRadius: 7, backgroundColor: '#fffefa' },
  currencySign: { color: COLORS.goldDark, fontSize: 12 },
  priceField: { flex: 1, paddingVertical: 8, color: COLORS.ink, fontSize: 10 },
  periodRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 4 },
  formCategoryScroll: { marginRight: -15 },
  formCategoryRow: { flexDirection: 'row', gap: 6, paddingRight: 15 },
  formError: { marginTop: 10, color: '#a34d3f', fontSize: 9, lineHeight: 14 },
  postButton: { marginTop: 21 },
  formFinePrint: { marginTop: 9, color: '#888d99', fontSize: 8, textAlign: 'center' },
  bottomBar: { minHeight: 72, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 8, paddingHorizontal: 14, paddingVertical: 9, backgroundColor: COLORS.cream },
  bottomTab: { flex: 1, minWidth: 0, minHeight: 52, alignItems: 'center', justifyContent: 'center', gap: 2, borderWidth: 1, borderColor: COLORS.line, borderRadius: 12, backgroundColor: COLORS.white },
  bottomTabActive: { borderColor: '#d8c99e', backgroundColor: COLORS.paleGold },
  bottomTabSell: { borderColor: COLORS.gold, backgroundColor: COLORS.gold },
  tabPressed: { opacity: 0.78, transform: [{ scale: 0.97 }] },
  bottomIcon: { color: '#858a97', fontSize: 19, lineHeight: 23 },
  bottomIconActive: { color: COLORS.navy },
  bottomIconSell: { color: COLORS.navy },
  bottomLabel: { color: '#858a97', fontSize: 8, fontWeight: '600' },
  bottomLabelActive: { color: COLORS.navy, fontWeight: '700' },
  bottomLabelSell: { color: COLORS.navy, fontWeight: '700' },
});
