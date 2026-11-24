import { StatusBar } from 'expo-status-bar';
import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { SafeAreaView } from 'react-native-safe-area-context';
import {
  ActivityIndicator,
  Image,
  Platform,
  Pressable,
  RefreshControl,
  ScrollView,
  Text,
  TextInput,
  View,
} from 'react-native';
import {
  Listing,
  ListingType,
  clearAuthToken,
  getAuthToken,
  requestJson,
  UserProfile,
} from './src/lib/api';
import { MenuButton } from './src/components/NavigationDrawer';
import { BookmarkIcon } from './src/components/BookmarkIcon';
import { BrandMark } from './src/components/BrandMark';
import { COLORS, createThemedStyles, getActiveTheme, useAppTheme } from './src/lib/theme';

export { COLORS } from './src/lib/theme';

type Tab = 'buy' | 'rent' | 'saved';
type ListingSort = 'latest' | 'price_low' | 'price_high';

const LISTING_SORT_OPTIONS: { value: ListingSort; label: string }[] = [
  { value: 'latest', label: 'Just added' },
  { value: 'price_low', label: 'Price: low to high' },
  { value: 'price_high', label: 'Price: high to low' },
];

const LIGHT_CONDITION_TONES: Record<string, { backgroundColor: string; color: string }> = {
  'Brand new': { backgroundColor: '#e6efe5', color: '#3d6244' },
  'Like new': { backgroundColor: '#f6edd6', color: '#795b1a' },
  Good: { backgroundColor: '#eee7d9', color: '#705a3d' },
  Fair: { backgroundColor: '#f1e2d9', color: '#80503f' },
};

export const CATEGORIES = [
  'Books & Notes',
  'School Supplies',
  'Tech & Calculators',
  'Uniforms',
  'Dorm Essentials',
  'Campus Services',
];

export function getConditionTone(condition: string) {
  if (getActiveTheme() === 'dark') {
    const darkConditionTones: Record<string, { backgroundColor: string; color: string }> = {
      'Brand new': { backgroundColor: '#304536', color: '#a7d7a8' },
      'Like new': { backgroundColor: '#4a3d27', color: '#f1cf78' },
      Good: { backgroundColor: '#3d3328', color: '#d2bda0' },
      Fair: { backgroundColor: '#463129', color: '#e0b19b' },
    };
    return darkConditionTones[condition] ?? darkConditionTones.Good;
  }

  return LIGHT_CONDITION_TONES[condition] ?? LIGHT_CONDITION_TONES.Good;
}

export function formatMobilePrice(price: string | number) {
  return `₱${Number(price).toLocaleString('en-PH', {
    maximumFractionDigits: 2,
  })}`;
}

export function formatListedTime(createdAt: string) {
  const created = new Date(createdAt);
  const elapsedSeconds = Math.max(0, Math.floor((Date.now() - created.getTime()) / 1000));

  if (elapsedSeconds < 60) return 'Listed just now';
  if (elapsedSeconds < 3600) return `Listed ${Math.floor(elapsedSeconds / 60)}m ago`;
  if (elapsedSeconds < 86400) return `Listed ${Math.floor(elapsedSeconds / 3600)}h ago`;
  if (elapsedSeconds < 604800) return `Listed ${Math.floor(elapsedSeconds / 86400)}d ago`;

  return `Listed ${created.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })}`;
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
            <Text style={styles.placeholderText}>No photo</Text>
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
        <BookmarkIcon saved={saved} />
      </Pressable>
      <Pressable onPress={onOpen} style={styles.cardContent}>
        <View style={styles.cardMetaRow}>
          <Text numberOfLines={1} style={styles.cardCategory}>{listing.category}</Text>
          {listing.condition ? (
            <View style={[styles.cardConditionBadge, { backgroundColor: getConditionTone(listing.condition).backgroundColor }]}>
              <Text style={[styles.cardCondition, { color: getConditionTone(listing.condition).color }]}>{listing.condition}</Text>
            </View>
          ) : null}
        </View>
        <Text numberOfLines={2} style={styles.cardTitle}>{listing.title}</Text>
        <Text numberOfLines={1} style={styles.cardLocation}>{listing.location}</Text>
        <Text style={styles.cardListedTime}>{formatListedTime(listing.created_at)}</Text>
        <Text numberOfLines={1} style={styles.cardSeller}>
          {listing.user?.name ?? listing.seller_name}
        </Text>
        <View style={styles.cardBottom}>
          <Text numberOfLines={1} style={styles.cardPrice}>
            {formatMobilePrice(listing.price)}
            {listing.type === 'rent' && listing.rent_period ? (
              <Text style={styles.pricePeriod}> / {listing.rent_period}</Text>
            ) : null}
          </Text>
          <Text style={styles.rating}>
            {listing.user
              ? listing.user.received_reviews_count
                ? `★ ${Number(listing.user.received_reviews_avg_rating).toFixed(1)}`
                : 'New seller'
              : `★ ${Number(listing.seller_rating).toFixed(1)}`}
          </Text>
        </View>
      </Pressable>
    </View>
  );
}

export default function App() {
  const { mode: themeMode } = useAppTheme();
  const params = useLocalSearchParams<{ mode?: string; view?: string }>();
  const routerMode = Array.isArray(params.mode) ? params.mode[0] : params.mode;
  const routerView = Array.isArray(params.view) ? params.view[0] : params.view;
  const [tab, setTab] = useState<Tab>(
    routerView === 'saved' ? 'saved' : routerMode === 'rent' ? 'rent' : 'buy',
  );
  const [authChecked, setAuthChecked] = useState(false);
  const mode: ListingType = tab === 'rent' ? 'rent' : 'sale';

  const [listings, setListings] = useState<Listing[]>([]);
  const [categories, setCategories] = useState(CATEGORIES);
  const [selectedCategory, setSelectedCategory] = useState<string | null>(null);
  const [categoryFilterOpen, setCategoryFilterOpen] = useState(false);
  const [sortFilterOpen, setSortFilterOpen] = useState(false);
  const [sort, setSort] = useState<ListingSort>('latest');
  const [savedIds, setSavedIds] = useState<number[]>([]);
  const [query, setQuery] = useState('');
  const [location, setLocation] = useState('');
  const [loading, setLoading] = useState(true);
  const [loadingMore, setLoadingMore] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [currentPage, setCurrentPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [totalListings, setTotalListings] = useState(0);
  const [loadError, setLoadError] = useState('');
  const [notice, setNotice] = useState('');

  useEffect(() => {
    if (routerView === 'saved') {
      setTab('saved');
    } else if (routerMode === 'rent') {
      setTab('rent');
    } else if (routerMode === 'sale' || routerMode === 'buy') {
      setTab('buy');
    }
  }, [routerMode, routerView]);

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

  const loadListings = useCallback(async (requestedPage = 1, append = false) => {
    if (append) {
      setLoadingMore(true);
    } else {
      setLoading(true);
      setLoadError('');
      setNotice('');
    }

    try {
      const searchParams = new URLSearchParams({ sort, page: String(requestedPage) });
      if (tab === 'saved') {
        searchParams.set('view', 'saved');
      } else {
        searchParams.set('mode', mode);
      }
      if (query.trim()) searchParams.set('q', query.trim());
      if (location.trim()) searchParams.set('location', location.trim());
      if (selectedCategory) searchParams.set('category', selectedCategory);

      const result = await requestJson<Listing[]>(`/listings?${searchParams.toString()}`);

      setListings((current) => append ? [...current, ...result.data] : result.data);
      setCurrentPage(result.meta?.current_page ?? requestedPage);
      setLastPage(result.meta?.last_page ?? 1);
      setTotalListings(result.meta?.total ?? result.data.length);
      if (result.categories) setCategories(result.categories);
    } catch (error) {
      const detail = error instanceof Error ? error.message : 'Something went wrong while loading listings.';
      if (append) {
        setNotice(detail);
      } else {
        setLoadError(detail);
      }
    } finally {
      if (append) {
        setLoadingMore(false);
      } else {
        setLoading(false);
      }
      setRefreshing(false);
    }
  }, [location, mode, query, selectedCategory, sort, tab]);

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

      void (async () => {
        try {
          const result = await requestJson<number[]>('/saved-listings');
          if (active) setSavedIds(result.data);
        } catch {
          if (active) setNotice('Saved items could not be loaded.');
        }
      })();

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
    setCategoryFilterOpen(false);
    setSortFilterOpen(false);
    if (nextTab !== tab) setLoading(true);
    setTab(nextTab);
  };

  const toggleFavorite = async (listingId: number) => {
    const wasSaved = savedIds.includes(listingId);
    const next = wasSaved
      ? savedIds.filter((id) => id !== listingId)
      : [...savedIds, listingId];

    try {
      await requestJson<{ saved: boolean; listing_id: number }>(
        `/saved-listings/${listingId}`,
        { method: wasSaved ? 'DELETE' : 'POST' },
      );
      setSavedIds(next);
    } catch (issue) {
      setNotice(issue instanceof Error ? issue.message : 'Could not update your saved find.');
    }
  };

  const openListing = (listing: Listing) => {
    router.push({ pathname: '/listing/[slug]', params: { slug: listing.slug } });
  };

  const renderListings = () => (
    <>
      {tab !== 'saved' ? (
        <>
          <View style={styles.searchFiltersRow}>
            <View style={[styles.searchBox, styles.searchBoxCompact]}>
              <TextInput
                accessibilityLabel="Search school listings"
                onChangeText={setQuery}
                placeholder="Search campus listings"
                placeholderTextColor={COLORS.muted}
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

            <Pressable
              accessibilityLabel={selectedCategory ? `Filter by category: ${selectedCategory}` : 'Filter by category'}
              accessibilityRole="button"
              accessibilityState={{ expanded: categoryFilterOpen }}
              onPress={() => {
                setSortFilterOpen(false);
                setCategoryFilterOpen((open) => !open);
              }}
              style={styles.categoryFilterButton}
            >
              <View style={styles.categoryFilterCopy}>
                <Text style={styles.categoryFilterLabel}>CATEGORY</Text>
                <Text numberOfLines={1} style={styles.categoryFilterValue}>
                  {selectedCategory ?? 'All categories'}
                </Text>
              </View>
            </Pressable>
          </View>

          {categoryFilterOpen ? (
            <ScrollView contentContainerStyle={styles.categoryRow} horizontal showsHorizontalScrollIndicator={false}>
              <ChoiceChip
                label="All categories"
                selected={selectedCategory === null}
                onPress={() => {
                  setSelectedCategory(null);
                  setCategoryFilterOpen(false);
                }}
              />
              {categories.map((category) => (
                <ChoiceChip
                  key={category}
                  label={category}
                  selected={selectedCategory === category}
                  onPress={() => {
                    setSelectedCategory(category);
                    setCategoryFilterOpen(false);
                  }}
                />
              ))}
            </ScrollView>
          ) : null}

          <View style={[styles.searchBox, styles.locationBox]}>
            <TextInput
              accessibilityLabel="Filter by campus"
              onChangeText={setLocation}
              placeholder="Campus or pickup area"
              placeholderTextColor={COLORS.muted}
              returnKeyType="search"
              style={styles.searchInput}
              value={location}
            />
          </View>

          <View style={styles.sectionTitleRow}>
            <View>
              <Text style={styles.sectionTitle}>
                {mode === 'rent' ? 'Good things to rent' : 'Campus finds for you'}
              </Text>
            </View>
            <Pressable
              accessibilityLabel={`Sort listings: ${LISTING_SORT_OPTIONS.find((option) => option.value === sort)?.label}`}
              accessibilityRole="button"
              accessibilityState={{ expanded: sortFilterOpen }}
              onPress={() => {
                setCategoryFilterOpen(false);
                setSortFilterOpen((open) => !open);
              }}
              style={styles.sortButton}
            >
              <Text style={styles.sortLabel}>Sort by</Text>
              <Text numberOfLines={1} style={styles.sortValue}>
                {LISTING_SORT_OPTIONS.find((option) => option.value === sort)?.label}
              </Text>
            </Pressable>
          </View>
          {sortFilterOpen ? (
            <View style={styles.sortOptions}>
              {LISTING_SORT_OPTIONS.map((option) => (
                <ChoiceChip
                  key={option.value}
                  label={option.label}
                  selected={sort === option.value}
                  onPress={() => {
                    setSort(option.value);
                    setSortFilterOpen(false);
                  }}
                />
              ))}
            </View>
          ) : null}
        </>
      ) : null}

      {notice ? <Text style={styles.inlineNotice}>{notice}</Text> : null}

      {loading ? (
        <View style={styles.loadingState}>
          <ActivityIndicator color={COLORS.goldDark} />
          <Text style={styles.helperText}>Finding good things...</Text>
        </View>
      ) : loadError ? (
        <View style={styles.emptyState}>
          <Text style={styles.emptyTitle}>Can't reach campus just yet.</Text>
          <Text style={styles.emptyDescription}>{loadError}</Text>
          <Pressable style={styles.primaryButton} onPress={() => void loadListings()}>
            <Text style={styles.primaryButtonText}>Try again</Text>
          </Pressable>
        </View>
      ) : visibleListings.length ? (
        <>
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
          {currentPage < lastPage ? (
            <View style={styles.pagination}>
              <Text style={styles.paginationCount}>{visibleListings.length} of {totalListings} finds</Text>
              <Pressable
                accessibilityRole="button"
                disabled={loadingMore}
                onPress={() => void loadListings(currentPage + 1, true)}
                style={[styles.paginationButton, loadingMore && styles.paginationButtonDisabled]}
              >
                {loadingMore ? (
                  <ActivityIndicator color={COLORS.white} size="small" />
                ) : (
                  <Text style={styles.paginationButtonText}>Load more</Text>
                )}
              </Pressable>
            </View>
          ) : null}
        </>
      ) : (
        <View style={styles.emptyState}>
          <Text style={styles.emptyTitle}>
            {tab === 'saved' ? 'No saved finds yet.' : 'No campus finds just yet.'}
          </Text>
          <Text style={styles.emptyDescription}>
            {tab === 'saved'
              ? 'Bookmark a listing and it will be waiting here.'
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
      <StatusBar style={themeMode === 'dark' ? 'light' : 'dark'} />

      <View style={styles.header}>
        <Pressable
          accessibilityLabel="Pasabuy home"
          onPress={() => changeTab('buy')}
          style={styles.brand}
        >
          <BrandMark />
          <Text style={styles.brandName}>Pasa<Text style={styles.brandNameAccent}>buy</Text></Text>
        </Pressable>
        <View style={{ flexDirection: 'row', gap: 8 }}>
          <MenuButton />
          <Pressable
            accessibilityLabel="List a school item"
            accessibilityRole="button"
            onPress={() => router.push('/sell')}
            style={styles.headerAddButton}
          >
            <Text style={styles.headerAddText}>List an item</Text>
          </Pressable>
        </View>
      </View>

      {tab !== 'saved' ? (
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
              <View style={styles.modeTabCopy}>
                <Text style={[styles.modeTabText, tab === modeTab && styles.modeTabTextActive]}>
                  {modeTab === 'buy' ? 'Buy' : 'Rent'}
                </Text>
                <Text style={[styles.modeTabDescription, tab === modeTab && styles.modeTabDescriptionActive]}>
                  {modeTab === 'buy' ? 'Find something to keep' : 'Borrow it for a while'}
                </Text>
              </View>
            </Pressable>
          ))}
        </View>
      ) : null}

      <View style={styles.pageHeading}>
        <Text style={styles.pageTitle}>
          {tab === 'buy' ? 'Find your kind of campus essential' : tab === 'saved' ? 'Save for later' : screenTitle}
        </Text>
        <Text style={styles.pageSubtitle}>
          {tab === 'saved'
            ? `${totalListings} ${totalListings === 1 ? 'listing' : 'listings'} saved on this device.`
            : 'Find and share school essentials with fellow USTP students.'}
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

      </View>
    </SafeAreaView>
  );
}

export const styles = createThemedStyles({
  app: { flex: 1, backgroundColor: COLORS.cream },
  safeArea: { flex: 1, backgroundColor: COLORS.cream },
  contentArea: { flex: 1 },
  header: { height: 68, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingHorizontal: 20, borderBottomWidth: 1, borderBottomColor: COLORS.line, backgroundColor: COLORS.white },
  brand: { flexDirection: 'row', alignItems: 'center', gap: 9 },
  brandName: { color: COLORS.navy, fontSize: 21, fontWeight: '800', letterSpacing: -1.3 },
  brandNameAccent: { color: COLORS.goldDark },
  headerAddButton: { minHeight: 40, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 12, borderRadius: 8, backgroundColor: COLORS.navy },
  headerAddText: { color: COLORS.white, fontSize: 12, fontWeight: '700' },
  modeTabs: { flexDirection: 'row', gap: 10, paddingHorizontal: 17, paddingVertical: 10, backgroundColor: COLORS.cream },
  modeTab: { flex: 1, minWidth: 0, minHeight: 62, justifyContent: 'center', paddingHorizontal: 12, borderWidth: 1, borderColor: COLORS.line, borderRadius: 10, backgroundColor: COLORS.white },
  modeTabActive: { borderWidth: 2, borderColor: '#c99a38', backgroundColor: COLORS.paleGold, elevation: 2, shadowColor: COLORS.navy, shadowOffset: { width: 0, height: 1 }, shadowOpacity: 0.1, shadowRadius: 4 },
  modeTabCopy: { gap: 3 },
  modeTabText: { color: COLORS.muted, fontSize: 12, fontWeight: '600' },
  modeTabTextActive: { color: COLORS.navy, fontWeight: '800' },
  modeTabDescription: { color: COLORS.muted, fontSize: 10 },
  modeTabDescriptionActive: { color: COLORS.ink },
  pageHeading: { paddingHorizontal: 20, paddingTop: 17, paddingBottom: 12 },
  pageTitle: { color: COLORS.navy, fontSize: 19, fontWeight: '700', letterSpacing: -0.5 },
  pageSubtitle: { marginTop: 4, color: COLORS.muted, fontSize: 12 },
  headerRouteTitle: { color: COLORS.navy, fontSize: 15, fontWeight: '700' },
  routeHeaderSpacer: { width: 52 },
  pageScroll: { flex: 1 },
  mainScrollContent: { flexGrow: 1, paddingBottom: 10 },
  searchFiltersRow: { flexDirection: 'row', alignItems: 'center', gap: 8, marginHorizontal: 17 },
  searchBox: { minHeight: 45, flexDirection: 'row', alignItems: 'center', gap: 10, marginHorizontal: 17, paddingHorizontal: 12, borderWidth: 1, borderColor: COLORS.line, borderRadius: 11, backgroundColor: COLORS.white },
  searchBoxCompact: { flex: 1, marginHorizontal: 0, paddingHorizontal: 10 },
  locationBox: { minHeight: 40, marginTop: 8 },
  searchInput: { flex: 1, paddingVertical: 8, color: COLORS.ink, fontSize: 15 },
  clearSearch: { paddingHorizontal: 4, color: COLORS.muted, fontSize: 20 },
  categoryFilterButton: { width: '42%', minHeight: 48, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 6, paddingHorizontal: 10, borderWidth: 1, borderColor: COLORS.line, borderRadius: 11, backgroundColor: COLORS.white },
  categoryFilterCopy: { flex: 1, minWidth: 0 },
  categoryFilterLabel: { color: COLORS.muted, fontSize: 10, fontWeight: '700', letterSpacing: 0.4 },
  categoryFilterValue: { marginTop: 2, color: COLORS.navy, fontSize: 12, fontWeight: '600' },
  categoryRow: { gap: 7, paddingHorizontal: 17, paddingVertical: 12 },
  choiceChip: { minHeight: 44, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 12, borderWidth: 1, borderColor: COLORS.line, borderRadius: 18, backgroundColor: COLORS.white },
  choiceChipSelected: { borderWidth: 2, borderColor: '#c99a38', backgroundColor: COLORS.paleGold },
  choiceChipText: { color: COLORS.muted, fontSize: 12, fontWeight: '600' },
  choiceChipTextSelected: { color: COLORS.navy, fontWeight: '700' },
  sectionTitleRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginHorizontal: 18, marginTop: 4, marginBottom: 10 },
  sectionTitle: { color: COLORS.navy, fontSize: 15, fontWeight: '700', letterSpacing: -0.2 },
  sortButton: { width: 136, minHeight: 40, justifyContent: 'center', paddingHorizontal: 10, borderWidth: 1, borderColor: COLORS.line, borderRadius: 8, backgroundColor: COLORS.white },
  sortLabel: { color: COLORS.muted, fontSize: 9, fontWeight: '700', letterSpacing: 0.4, textTransform: 'uppercase' },
  sortValue: { marginTop: 1, color: COLORS.navy, fontSize: 11, fontWeight: '600' },
  sortOptions: { flexDirection: 'row', flexWrap: 'wrap', gap: 7, marginHorizontal: 17, marginBottom: 10 },
  inlineNotice: { marginHorizontal: 18, marginBottom: 8, color: COLORS.green, fontSize: 12 },
  listingGrid: { paddingHorizontal: 17, paddingBottom: 18 },
  pagination: { alignItems: 'center', gap: 9, paddingHorizontal: 17, paddingBottom: 20 },
  paginationCount: { color: COLORS.muted, fontSize: 12 },
  paginationButton: { minHeight: 42, minWidth: 140, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 18, borderRadius: 8, backgroundColor: COLORS.navy },
  paginationButtonDisabled: { opacity: 0.65 },
  paginationButtonText: { color: COLORS.white, fontSize: 13, fontWeight: '700' },
  listingRow: { flexDirection: 'row', gap: 11, marginBottom: 11 },
  listingCard: { flex: 1, minWidth: 0, overflow: 'hidden', borderWidth: 1, borderColor: COLORS.line, borderRadius: 12, backgroundColor: COLORS.white, elevation: 1, shadowColor: COLORS.navy, shadowOffset: { width: 0, height: 2 }, shadowOpacity: 0.07, shadowRadius: 5 },
  cardSpacer: { flex: 1 },
  cardImageWrap: { height: 156, overflow: 'hidden', backgroundColor: COLORS.paleNeutral },
  cardImage: { width: '100%', height: '100%', objectFit: 'cover' },
  cardImagePlaceholder: { width: '100%', height: '100%' },
  imagePlaceholder: { alignItems: 'center', justifyContent: 'center', backgroundColor: '#f2eddf' },
  placeholderText: { color: COLORS.muted, fontSize: 12 },
  typeBadge: { position: 'absolute', top: 8, left: 8, paddingHorizontal: 7, paddingVertical: 5, borderRadius: 5, backgroundColor: COLORS.paleGold },
  typeBadgeText: { color: '#77570f', fontSize: 10, fontWeight: '800', letterSpacing: 0.3 },
  rentBadge: { backgroundColor: COLORS.paleNeutral },
  rentBadgeText: { color: COLORS.navy },
  favoriteButton: { position: 'absolute', top: 7, right: 7, width: 40, height: 40, alignItems: 'center', justifyContent: 'center', borderRadius: 20, backgroundColor: '#fffdf8ed' },
  cardContent: { paddingHorizontal: 9, paddingTop: 9, paddingBottom: 9 },
  cardMetaRow: { minHeight: 13, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 4 },
  cardCategory: { flex: 1, color: COLORS.muted, fontSize: 10, fontWeight: '700', letterSpacing: 0.3 },
  cardConditionBadge: { flexShrink: 0, paddingHorizontal: 6, paddingVertical: 4, borderRadius: 10 },
  cardCondition: { fontSize: 10, fontWeight: '700' },
  cardTitle: { minHeight: 36, marginTop: 5, color: COLORS.ink, fontSize: 13, fontWeight: '700', lineHeight: 18 },
  cardLocation: { marginTop: 4, color: COLORS.muted, fontSize: 11 },
  cardListedTime: { marginTop: 4, color: COLORS.muted, fontSize: 10 },
  cardSeller: { marginTop: 4, color: COLORS.muted, fontSize: 10, fontWeight: '600' },
  cardBottom: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 5, marginTop: 9, paddingTop: 7, borderTopWidth: 1, borderTopColor: COLORS.line },
  cardPrice: { flexShrink: 1, color: COLORS.navy, fontSize: 12, fontWeight: '800' },
  pricePeriod: { color: COLORS.muted, fontSize: 10, fontWeight: '400' },
  rating: { color: COLORS.muted, fontSize: 10 },
  loadingState: { alignItems: 'center', gap: 9, paddingVertical: 48 },
  helperText: { color: COLORS.muted, fontSize: 12 },
  emptyState: { alignItems: 'center', paddingHorizontal: 30, paddingVertical: 43 },
  emptyTitle: { marginTop: 13, color: COLORS.navy, fontSize: 15, fontWeight: '700', textAlign: 'center' },
  emptyDescription: { marginTop: 7, color: COLORS.muted, fontSize: 13, lineHeight: 19, textAlign: 'center' },
  primaryButton: { minHeight: 48, alignItems: 'center', justifyContent: 'center', marginTop: 15, paddingHorizontal: 20, borderRadius: 9, backgroundColor: COLORS.navy },
  primaryButtonText: { color: '#fff', fontSize: 14, fontWeight: '700' },
  disabledButton: { opacity: 0.65 },
  detailContent: { paddingHorizontal: 17, paddingBottom: 22 },
  backButton: { alignSelf: 'flex-start', minHeight: 44, justifyContent: 'center', marginBottom: 6, paddingVertical: 5 },
  backButtonText: { color: COLORS.goldDark, fontSize: 13, fontWeight: '600' },
  detailImageWrap: { position: 'relative', height: 255, overflow: 'hidden', borderRadius: 12, backgroundColor: COLORS.paleNeutral },
  detailImage: { width: '100%', height: '100%', backgroundColor: COLORS.paleNeutral, objectFit: 'cover' },
  detailPanel: { paddingTop: 15 },
  detailTopline: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  detailCategory: { color: COLORS.muted, fontSize: 11, fontWeight: '700', letterSpacing: 0.5 },
  detailTitle: { marginTop: 8, color: COLORS.navy, fontSize: 22, fontWeight: '700', letterSpacing: -0.7 },
  detailPrice: { marginTop: 6, color: COLORS.goldDark, fontSize: 23, fontWeight: '800' },
  detailLocation: { marginTop: 6, color: COLORS.muted, fontSize: 13 },
  detailListedTime: { marginTop: 5, color: COLORS.muted, fontSize: 12 },
  divider: { height: 1, marginVertical: 15, backgroundColor: COLORS.line },
  detailHeading: { color: COLORS.ink, fontSize: 15, fontWeight: '700' },
  detailDescription: { marginTop: 7, color: COLORS.muted, fontSize: 14, lineHeight: 21 },
  conditionPill: { alignSelf: 'flex-start', marginTop: 10, paddingHorizontal: 9, paddingVertical: 6, borderRadius: 14 },
  conditionPillText: { fontSize: 12, fontWeight: '700' },
  sellerCard: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 17, padding: 11, borderWidth: 1, borderColor: COLORS.line, borderRadius: 10, backgroundColor: COLORS.white },
  sellerAvatar: { width: 36, height: 36, alignItems: 'center', justifyContent: 'center', borderRadius: 20, backgroundColor: COLORS.paleGold },
  sellerAvatarImage: { width: 36, height: 36, borderRadius: 20 },
  sellerInitials: { color: COLORS.goldDark, fontSize: 14, fontWeight: '800' },
  sellerCopy: { flex: 1 },
  sellerName: { color: COLORS.ink, fontSize: 14, fontWeight: '700' },
  sellerSubline: { marginTop: 3, color: COLORS.muted, fontSize: 11 },
  detailBookmarkButton: { width: 44, height: 44, alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: COLORS.line, borderRadius: 22, backgroundColor: COLORS.white },
  safetyNote: { flexDirection: 'row', gap: 9, marginTop: 11, padding: 11, borderRadius: 9, backgroundColor: COLORS.paleGold },
  safetyIcon: { color: COLORS.goldDark, fontSize: 14 },
  safetyText: { flex: 1, color: COLORS.muted, fontSize: 12, lineHeight: 18 },
  detailActions: { gap: 9, marginTop: 16 },
  secondaryAction: { backgroundColor: COLORS.paleNeutral },
  secondaryActionText: { color: COLORS.navy },
  detailBrowseButton: { alignItems: 'center', paddingVertical: 15 },
  detailBrowseText: { color: COLORS.navy, fontSize: 13, fontWeight: '700' },
  fetchHeading: { gap: 4, paddingBottom: 2 },
  fetchEyebrow: { color: COLORS.goldDark, fontSize: 11, fontWeight: '700', letterSpacing: 0.8 },
  fetchCreateButton: { marginTop: 0 },
  fetchFeedStatus: { color: COLORS.muted, fontSize: 12 },
  fetchDashboardSection: { gap: 10, paddingTop: 6 },
  fetchDashboardHeading: { color: COLORS.navy, fontSize: 18, fontWeight: '700' },
  fetchDashboardCard: { gap: 10, padding: 15, borderWidth: 1, borderColor: COLORS.line, borderRadius: 12, backgroundColor: COLORS.white },
  fetchJobStatus: { overflow: 'hidden', paddingHorizontal: 9, paddingVertical: 6, borderRadius: 16, backgroundColor: '#e6efe5', color: COLORS.green, fontSize: 11, fontWeight: '700' },
  fetchActionButton: { alignItems: 'center', justifyContent: 'center', minHeight: 44, marginTop: 2, paddingHorizontal: 16, borderRadius: 9, backgroundColor: COLORS.navy },
  fetchDashboardNote: { color: COLORS.muted, fontSize: 12, lineHeight: 18 },
  fetchDeadlineWarning: { color: '#a34d3f', fontSize: 12, fontWeight: '600', lineHeight: 18 },
  fetchRequestCard: { padding: 16, borderWidth: 1, borderColor: COLORS.line, borderRadius: 12, backgroundColor: COLORS.white },
  fetchRequestHeading: { flexDirection: 'row', alignItems: 'flex-start', justifyContent: 'space-between', gap: 12 },
  fetchRequestTitle: { color: COLORS.navy, fontSize: 16, fontWeight: '700' },
  fetchRequester: { marginTop: 5, color: COLORS.muted, fontSize: 12 },
  fetchBudget: { overflow: 'hidden', paddingHorizontal: 9, paddingVertical: 6, borderRadius: 16, backgroundColor: COLORS.paleGold, color: COLORS.goldDark, fontSize: 11, fontWeight: '700' },
  fetchDetails: { marginTop: 14, color: COLORS.ink, fontSize: 13, lineHeight: 20 },
  fetchMeta: { color: COLORS.muted, fontSize: 11, lineHeight: 17 },
  fetchLocation: { color: COLORS.ink, fontWeight: '700' },
  fetchTimeRow: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'space-between', gap: 6, marginTop: 10, paddingTop: 10, borderTopWidth: 1, borderTopColor: COLORS.line },
  fetchExpiry: { color: COLORS.goldDark, fontSize: 11, fontWeight: '700' },
  fetchEmptyState: { alignItems: 'center', gap: 3, paddingVertical: 30, paddingHorizontal: 16 },
  sellContent: { paddingHorizontal: 17, paddingBottom: 25 },
  sellIntro: { paddingVertical: 7 },
  sellTitle: { marginTop: 5, color: COLORS.navy, fontSize: 25, fontWeight: '700', letterSpacing: -0.8 },
  sellTitleAccent: { color: COLORS.goldDark, fontFamily: Platform.OS === 'ios' ? 'Georgia' : 'serif', fontStyle: 'italic' },
  sellDescription: { maxWidth: 340, marginTop: 5, color: COLORS.muted, fontSize: 13, lineHeight: 19 },
  formCard: { marginTop: 13, padding: 15, borderWidth: 1, borderColor: COLORS.line, borderRadius: 12, backgroundColor: COLORS.white },
  formHeading: { color: COLORS.navy, fontSize: 18, fontWeight: '700' },
  formSubheading: { marginTop: 4, color: COLORS.muted, fontSize: 12, lineHeight: 18 },
  fieldLabel: { marginTop: 14, marginBottom: 7, color: COLORS.navy, fontSize: 13, fontWeight: '700' },
  required: { color: COLORS.goldDark },
  formInput: { minHeight: 48, paddingHorizontal: 12, paddingVertical: 11, borderWidth: 1, borderColor: COLORS.line, borderRadius: 8, backgroundColor: COLORS.white, color: COLORS.ink, fontSize: 16 },
  multilineInput: { minHeight: 100 },
  segmentRow: { flexDirection: 'row', gap: 8 },
  formRow: { flexDirection: 'row', gap: 10 },
  formHalf: { flex: 1, minWidth: 0 },
  priceInput: { minHeight: 48, flexDirection: 'row', alignItems: 'center', gap: 7, paddingHorizontal: 10, borderWidth: 1, borderColor: COLORS.line, borderRadius: 8, backgroundColor: COLORS.white },
  currencySign: { color: COLORS.goldDark, fontSize: 16 },
  priceField: { flex: 1, paddingVertical: 8, color: COLORS.ink, fontSize: 16 },
  periodRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 4 },
  formCategoryScroll: { marginRight: -15 },
  formCategoryRow: { flexDirection: 'row', gap: 6, paddingRight: 15 },
  formError: { marginTop: 10, color: '#a34d3f', fontSize: 12, lineHeight: 18 },
  postButton: { marginTop: 21 },
  formFinePrint: { marginTop: 9, color: COLORS.muted, fontSize: 11, lineHeight: 17, textAlign: 'center' },
  tabPressed: { opacity: 0.78, transform: [{ scale: 0.97 }] },
});
