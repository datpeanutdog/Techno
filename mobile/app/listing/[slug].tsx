import { router, useLocalSearchParams } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useEffect, useState } from 'react';
import { SafeAreaView } from 'react-native-safe-area-context';
import {
  ActivityIndicator,
  Image,
  Pressable,
  ScrollView,
  Text,
  View,
} from 'react-native';
import { COLORS, CATEGORY_ICONS, formatListedTime, getConditionTone, styles } from '../../App';
import {
  clearAuthToken,
  getAuthToken,
  Listing,
  formatPrice,
  requestJson,
  UserProfile,
} from '../../src/lib/api';
import { MenuButton } from '../../src/components/NavigationDrawer';
import { useAppTheme } from '../../src/lib/theme';

export default function ListingDetailScreen() {
  const { mode } = useAppTheme();
  const params = useLocalSearchParams<{ slug: string }>();
  const slug = Array.isArray(params.slug) ? params.slug[0] : params.slug;
  const [listing, setListing] = useState<Listing | null>(null);
  const [currentUserId, setCurrentUserId] = useState<number | null>(null);
  const [savedIds, setSavedIds] = useState<number[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    let active = true;

    void (async () => {
      try {
        if (!await getAuthToken()) {
          router.replace('/account');
          return;
        }

        const [result, savedResult, profile] = await Promise.all([
          requestJson<Listing>(`/listings/${encodeURIComponent(slug)}`),
          requestJson<number[]>('/saved-listings'),
          requestJson<UserProfile>('/auth/me'),
        ]);
        if (!active) return;
        setListing(result.data);
        setCurrentUserId(profile.data.id);
        setSavedIds(savedResult.data);
      } catch (issue) {
        const detail = issue instanceof Error ? issue.message : 'Could not load this listing.';
        if (detail.includes('(401)') || detail.includes('(Unauthenticated')) {
          await clearAuthToken();
          router.replace('/account');
          return;
        }
        if (active) setError(detail);
      } finally {
        if (active) setLoading(false);
      }
    })();

    return () => {
      active = false;
    };
  }, [slug]);

  const toggleFavorite = async () => {
    if (!listing) return;

    const wasSaved = savedIds.includes(listing.id);
    const next = wasSaved
      ? savedIds.filter((id) => id !== listing.id)
      : [...savedIds, listing.id];

    try {
      await requestJson<{ saved: boolean; listing_id: number }>(
        `/saved-listings/${listing.id}`,
        { method: wasSaved ? 'DELETE' : 'POST' },
      );
      setSavedIds(next);
    } catch (issue) {
      setError(issue instanceof Error ? issue.message : 'Could not save your favorite.');
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
        <Text style={styles.headerRouteTitle}>Listing details</Text>
        <MenuButton />
      </View>

      {loading ? (
        <View style={styles.loadingState}>
          <ActivityIndicator color={COLORS.goldDark} />
          <Text style={styles.helperText}>Opening this campus find...</Text>
        </View>
      ) : error && !listing ? (
        <View style={styles.emptyState}>
          <Text style={styles.emptyTitle}>Could not open this listing.</Text>
          <Text style={styles.emptyDescription}>{error}</Text>
          <Pressable onPress={() => router.back()} style={styles.primaryButton}>
            <Text style={styles.primaryButtonText}>Back to finds</Text>
          </Pressable>
        </View>
      ) : listing ? (
        <ScrollView contentContainerStyle={styles.detailContent} showsVerticalScrollIndicator={false}>
          {listing.image_url ? (
            <Image source={{ uri: listing.image_url }} style={styles.detailImage} />
          ) : (
            <View style={[styles.detailImage, styles.imagePlaceholder]}>
              <Text style={styles.placeholderIcon}>{CATEGORY_ICONS[listing.category] ?? '✦'}</Text>
            </View>
          )}
          <View style={styles.detailPanel}>
            <View style={styles.detailTopline}>
              <Text style={styles.detailCategory}>{listing.category.toUpperCase()}</Text>
              <Text style={[styles.typeBadgeText, listing.type === 'rent' && styles.detailRentLabel]}>
                {listing.type === 'rent' ? 'FOR RENT' : 'FOR SALE'}
              </Text>
            </View>
            <Text style={styles.detailTitle}>{listing.title}</Text>
            <Text style={styles.detailPrice}>
              {formatPrice(listing.price)}
              {listing.type === 'rent' && listing.rent_period ? (
                <Text style={styles.pricePeriod}> / {listing.rent_period}</Text>
              ) : null}
            </Text>
            <Text style={styles.detailLocation}>⌖  {listing.location}</Text>
            <Text style={styles.detailListedTime}>{formatListedTime(listing.created_at)}</Text>
            <View style={styles.divider} />
            <Text style={styles.detailHeading}>A little more about it</Text>
            <Text style={styles.detailDescription}>{listing.description}</Text>
            {listing.condition ? (
              <View style={[styles.conditionPill, { backgroundColor: getConditionTone(listing.condition).backgroundColor }]}>
                <Text style={[styles.conditionPillText, { color: getConditionTone(listing.condition).color }]}>{listing.condition}</Text>
              </View>
            ) : null}
            <View style={styles.sellerCard}>
              <View style={styles.sellerAvatar}>
                {listing.user?.avatar_url ? (
                  <Image source={{ uri: listing.user.avatar_url }} style={styles.sellerAvatarImage} />
                ) : (
                  <Text style={styles.sellerInitials}>{listing.seller_initials}</Text>
                )}
              </View>
              <View style={styles.sellerCopy}>
                <Text style={styles.sellerName}>{listing.user?.name ?? listing.seller_name}</Text>
                <Text style={styles.sellerSubline}>
                  {listing.user?.campus ?? 'Student seller'}  ·  {listing.user
                    ? listing.user.received_reviews_count
                      ? `★ ${Number(listing.user.received_reviews_avg_rating).toFixed(1)} (${listing.user.received_reviews_count})`
                      : 'No reviews yet'
                    : `★ ${Number(listing.seller_rating).toFixed(1)}`}
                </Text>
              </View>
              <Pressable
                accessibilityLabel={savedIds.includes(listing.id) ? 'Remove saved item' : 'Save item'}
                onPress={() => void toggleFavorite()}
              >
                <Text style={[styles.detailHeart, savedIds.includes(listing.id) && styles.favoriteIconSaved]}>
                  {savedIds.includes(listing.id) ? '♥' : '♡'}
                </Text>
              </Pressable>
            </View>
            {listing.user ? (
              <Pressable
                accessibilityRole="button"
                onPress={() => router.push({ pathname: '/seller/[id]', params: { id: String(listing.user?.id) } })}
                style={{ alignSelf: 'flex-start', paddingVertical: 8 }}
              >
                <Text style={{ color: COLORS.goldDark, fontSize: 12, fontWeight: '700' }}>
                  View seller profile &amp; reviews →
                </Text>
              </Pressable>
            ) : null}
            {listing.user?.bio ? <Text style={styles.detailDescription}>{listing.user.bio}</Text> : null}
            {error ? <Text style={styles.formError}>{error}</Text> : null}
            <View style={styles.safetyNote}>
              <Text style={styles.safetyIcon}>✦</Text>
              <Text style={styles.safetyText}>
                Meet in a public campus spot, check the item, and agree on the details before paying or borrowing.
              </Text>
            </View>
            {listing.user && listing.user.id !== currentUserId ? (
              <>
                <Pressable
                  accessibilityRole="button"
                  onPress={() => router.push({
                    pathname: '/messages/new',
                    params: { listingSlug: listing.slug },
                  })}
                  style={styles.primaryButton}
                >
                  <Text style={styles.primaryButtonText}>Message seller privately</Text>
                </Pressable>
                <Pressable
                  accessibilityRole="button"
                  onPress={() => router.push({
                    pathname: '/support',
                    params: {
                      reportedUserId: String(listing.user?.id),
                      reportedUserName: listing.user?.name ?? listing.seller_name,
                    },
                  })}
                  style={[styles.primaryButton, { backgroundColor: COLORS.paleNeutral }]}
                >
                  <Text style={[styles.primaryButtonText, { color: COLORS.navy }]}>Report this seller</Text>
                </Pressable>
              </>
            ) : null}
          </View>
        </ScrollView>
      ) : null}
      </View>
    </SafeAreaView>
  );
}
