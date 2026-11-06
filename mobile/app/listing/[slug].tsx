import AsyncStorage from '@react-native-async-storage/async-storage';
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
import { COLORS, CATEGORY_ICONS, styles } from '../../App';
import {
  clearAuthToken,
  FAVORITES_KEY,
  getAuthToken,
  Listing,
  formatPrice,
  requestJson,
  UserProfile,
} from '../../src/lib/api';
import { MenuButton } from '../../src/components/NavigationDrawer';

export default function ListingDetailScreen() {
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

        const [result, favorites, profile] = await Promise.all([
          requestJson<Listing>(`/listings/${encodeURIComponent(slug)}`),
          AsyncStorage.getItem(FAVORITES_KEY),
          requestJson<UserProfile>('/auth/me'),
        ]);
        if (!active) return;
        setListing(result.data);
        setCurrentUserId(profile.data.id);
        if (favorites) setSavedIds(JSON.parse(favorites) as number[]);
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

    const next = savedIds.includes(listing.id)
      ? savedIds.filter((id) => id !== listing.id)
      : [...savedIds, listing.id];

    setSavedIds(next);

    try {
      await AsyncStorage.setItem(FAVORITES_KEY, JSON.stringify(next));
    } catch {
      setError('Could not save your favorite on this device.');
    }
  };

  return (
    <SafeAreaView edges={['top', 'bottom']} style={styles.safeArea}>
      <View style={styles.app}>
      <StatusBar style="light" />
      <View style={styles.topBar}>
        <Text style={styles.topBarText}>✳  A GOOD FIND, UP CLOSE</Text>
        <Text style={styles.topBarLocation}>CAMPUS FINDS</Text>
      </View>
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
            <View style={styles.divider} />
            <Text style={styles.detailHeading}>A little more about it</Text>
            <Text style={styles.detailDescription}>{listing.description}</Text>
            {listing.condition ? <Text style={styles.conditionPill}>{listing.condition}</Text> : null}
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
                <Text style={styles.sellerSubline}>{listing.user?.campus ?? 'Student seller'}  ·  ★ {Number(listing.seller_rating).toFixed(1)}</Text>
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
            {listing.user?.bio ? <Text style={styles.detailDescription}>{listing.user.bio}</Text> : null}
            {error ? <Text style={styles.formError}>{error}</Text> : null}
            <View style={styles.safetyNote}>
              <Text style={styles.safetyIcon}>✦</Text>
              <Text style={styles.safetyText}>
                Meet in a public campus spot, check the item, and agree on the details before paying or borrowing.
              </Text>
            </View>
            {listing.user && listing.user.id !== currentUserId ? (
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
            ) : null}
          </View>
        </ScrollView>
      ) : null}
      </View>
    </SafeAreaView>
  );
}
