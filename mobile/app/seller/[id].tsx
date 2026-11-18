import { router, useLocalSearchParams } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useEffect, useState } from 'react';
import { SafeAreaView } from 'react-native-safe-area-context';
import {
  ActivityIndicator,
  Image,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { COLORS, formatMobilePrice, styles } from '../../App';
import { clearAuthToken, getAuthToken, Listing, requestJson } from '../../src/lib/api';
import { MenuButton } from '../../src/components/NavigationDrawer';

type SellerReview = {
  id: number;
  rating: number;
  body: string;
  created_at: string;
  reviewer: {
    id: number;
    name: string;
    campus: string | null;
    avatar_url: string | null;
  };
};

type SellerProfile = {
  id: number;
  name: string;
  bio: string | null;
  campus: string | null;
  avatar_url: string | null;
  can_review: boolean;
  average_rating: number | null;
  reviews_count: number;
  listings: Listing[];
  reviews: SellerReview[];
  my_review: { id: number; rating: number; body: string } | null;
};

export default function SellerProfileScreen() {
  const params = useLocalSearchParams<{ id: string }>();
  const id = Array.isArray(params.id) ? params.id[0] : params.id;
  const [seller, setSeller] = useState<SellerProfile | null>(null);
  const [rating, setRating] = useState(0);
  const [body, setBody] = useState('');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  useEffect(() => {
    let active = true;

    void (async () => {
      try {
        if (!await getAuthToken()) {
          router.replace('/account');
          return;
        }

        const result = await requestJson<SellerProfile>(`/sellers/${encodeURIComponent(id)}`);
        if (!active) return;
        setSeller(result.data);
        setRating(result.data.my_review?.rating ?? 0);
        setBody(result.data.my_review?.body ?? '');
      } catch (issue) {
        const detail = issue instanceof Error ? issue.message : 'This seller profile could not be loaded.';
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

    return () => { active = false; };
  }, [id]);

  const submitReview = async () => {
    if (!seller) return;
    setError('');
    setNotice('');
    setSaving(true);

    try {
      await requestJson(`/sellers/${seller.id}/reviews`, {
        method: 'POST',
        body: JSON.stringify({ rating, body }),
      });
      const result = await requestJson<SellerProfile>(`/sellers/${seller.id}`);
      setSeller(result.data);
      setRating(result.data.my_review?.rating ?? 0);
      setBody(result.data.my_review?.body ?? '');
      setNotice('Your review has been saved.');
    } catch (issue) {
      const detail = issue instanceof Error ? issue.message : 'Your review could not be saved.';
      if (detail.includes('(401)') || detail.includes('(Unauthenticated')) {
        await clearAuthToken();
        router.replace('/account');
        return;
      }
      setError(detail);
    } finally {
      setSaving(false);
    }
  };

  return (
    <SafeAreaView edges={['top', 'bottom']} style={styles.safeArea}>
      <View style={styles.app}>
        <StatusBar style="light" />
        <View style={styles.topBar}>
          <Text style={styles.topBarText}>✳  STUDENT SELLER</Text>
          <Text style={styles.topBarLocation}>CAMPUS COMMUNITY</Text>
        </View>
        <View style={styles.header}>
          <Pressable onPress={() => router.back()} style={styles.backButton}>
            <Text style={styles.backButtonText}>‹  Back</Text>
          </Pressable>
          <Text style={styles.headerRouteTitle}>Seller profile</Text>
          <MenuButton />
        </View>

        {loading ? (
          <View style={styles.loadingState}>
            <ActivityIndicator color={COLORS.goldDark} />
            <Text style={styles.helperText}>Opening seller profile...</Text>
          </View>
        ) : error && !seller ? (
          <View style={styles.emptyState}>
            <Text style={styles.emptyTitle}>Could not open this profile.</Text>
            <Text style={styles.emptyDescription}>{error}</Text>
            <Pressable onPress={() => router.back()} style={styles.primaryButton}>
              <Text style={styles.primaryButtonText}>Back to finds</Text>
            </Pressable>
          </View>
        ) : seller ? (
          <ScrollView
            contentContainerStyle={sellerStyles.content}
            keyboardShouldPersistTaps="handled"
            showsVerticalScrollIndicator={false}
          >
            <View style={sellerStyles.profileCard}>
              <View style={sellerStyles.avatar}>
                {seller.avatar_url ? (
                  <Image source={{ uri: seller.avatar_url }} style={sellerStyles.avatarImage} />
                ) : (
                  <Text style={sellerStyles.avatarInitial}>{seller.name.slice(0, 1).toUpperCase()}</Text>
                )}
              </View>
              <View style={sellerStyles.profileCopy}>
                <Text style={sellerStyles.eyebrow}>PASABUY STUDENT SELLER</Text>
                <Text style={sellerStyles.name}>{seller.name}</Text>
                <Text style={sellerStyles.muted}>{seller.campus ?? 'Campus community member'}</Text>
                {seller.bio ? <Text style={sellerStyles.bio}>{seller.bio}</Text> : null}
                <Text style={sellerStyles.stats}>
                  {seller.listings.length} active {seller.listings.length === 1 ? 'listing' : 'listings'}
                  {'  ·  '}
                  {seller.average_rating === null
                    ? 'No reviews yet'
                    : `★ ${seller.average_rating.toFixed(1)} (${seller.reviews_count})`}
                </Text>
              </View>
            </View>

            <View style={sellerStyles.sectionHeading}>
              <Text style={sellerStyles.eyebrow}>SHARED BY {seller.name.toUpperCase()}</Text>
              <Text style={sellerStyles.sectionTitle}>Active listings</Text>
            </View>
            {seller.listings.length ? seller.listings.map((listing) => (
              <Pressable
                key={listing.id}
                accessibilityRole="button"
                onPress={() => router.push({ pathname: '/listing/[slug]', params: { slug: listing.slug } })}
                style={sellerStyles.listingCard}
              >
                {listing.image_url ? (
                  <Image source={{ uri: listing.image_url }} style={sellerStyles.listingImage} />
                ) : (
                  <View style={[sellerStyles.listingImage, sellerStyles.listingPlaceholder]}>
                    <Text style={sellerStyles.listingPlaceholderText}>✦</Text>
                  </View>
                )}
                <View style={sellerStyles.listingCopy}>
                  <Text numberOfLines={1} style={sellerStyles.listingTitle}>{listing.title}</Text>
                  <Text style={sellerStyles.muted}>{listing.category}  ·  {listing.location}</Text>
                  <Text style={sellerStyles.listingPrice}>
                    {formatMobilePrice(listing.price)}
                    {listing.type === 'rent' && listing.rent_period ? ` / ${listing.rent_period}` : ''}
                  </Text>
                </View>
              </Pressable>
            )) : (
              <Text style={sellerStyles.emptyMessage}>There are no active listings right now.</Text>
            )}

            <View style={sellerStyles.sectionHeading}>
              <Text style={sellerStyles.eyebrow}>STUDENT FEEDBACK</Text>
              <Text style={sellerStyles.sectionTitle}>Reviews &amp; ratings</Text>
            </View>

            {seller.can_review ? (
              <View style={sellerStyles.reviewForm}>
                <Text style={sellerStyles.formTitle}>
                  {seller.my_review ? 'Update your review' : 'Share your experience'}
                </Text>
                <Text style={sellerStyles.muted}>Your rating helps other students know what to expect.</Text>
                <Text style={sellerStyles.fieldLabel}>Your rating</Text>
                <View style={sellerStyles.stars}>
                  {[1, 2, 3, 4, 5].map((star) => (
                    <Pressable
                      key={star}
                      accessibilityRole="radio"
                      accessibilityLabel={`${star} ${star === 1 ? 'star' : 'stars'}`}
                      accessibilityState={{ checked: rating === star }}
                      onPress={() => setRating(star)}
                      style={sellerStyles.starButton}
                    >
                      <Text style={[sellerStyles.star, star <= rating && sellerStyles.starSelected]}>★</Text>
                    </Pressable>
                  ))}
                </View>
                <Text style={sellerStyles.fieldLabel}>Your review</Text>
                <TextInput
                  accessibilityLabel="Your review"
                  maxLength={1000}
                  multiline
                  onChangeText={setBody}
                  placeholder="How was your experience with this seller?"
                  placeholderTextColor="#9699a4"
                  style={sellerStyles.reviewInput}
                  textAlignVertical="top"
                  value={body}
                />
                {error ? <Text accessibilityRole="alert" style={sellerStyles.error}>{error}</Text> : null}
                {notice ? <Text accessibilityRole="status" style={sellerStyles.notice}>{notice}</Text> : null}
                <Pressable
                  accessibilityRole="button"
                  disabled={saving || rating === 0 || !body.trim()}
                  onPress={() => void submitReview()}
                  style={[styles.primaryButton, (saving || rating === 0 || !body.trim()) && sellerStyles.disabledButton]}
                >
                  <Text style={styles.primaryButtonText}>{saving ? 'Saving...' : seller.my_review ? 'Update review' : 'Post review'}</Text>
                </Pressable>
              </View>
            ) : null}

            {seller.reviews.length ? seller.reviews.map((review) => (
              <View key={review.id} style={sellerStyles.reviewCard}>
                <View style={sellerStyles.reviewHeading}>
                  <Text style={sellerStyles.reviewName}>{review.reviewer.name}</Text>
                  <Text style={sellerStyles.muted}>{new Date(review.created_at).toLocaleDateString()}</Text>
                </View>
                {review.reviewer.campus ? <Text style={sellerStyles.muted}>{review.reviewer.campus}</Text> : null}
                <Text accessibilityLabel={`${review.rating} out of 5 stars`} style={sellerStyles.reviewStars}>
                  {'★'.repeat(review.rating)}{'☆'.repeat(5 - review.rating)}
                </Text>
                <Text style={sellerStyles.reviewBody}>{review.body}</Text>
              </View>
            )) : (
              <Text style={sellerStyles.emptyMessage}>No reviews yet. Be the first to share feedback.</Text>
            )}
            <Text style={sellerStyles.disclaimer}>
              Reviews are from PASABUY members and are not independently verified as completed transactions.
            </Text>
          </ScrollView>
        ) : null}
      </View>
    </SafeAreaView>
  );
}

const sellerStyles = StyleSheet.create({
  content: { padding: 17, paddingBottom: 36 },
  profileCard: { flexDirection: 'row', alignItems: 'flex-start', gap: 14, padding: 16, borderWidth: 1, borderColor: COLORS.line, borderRadius: 12, backgroundColor: COLORS.white },
  avatar: { width: 62, height: 62, flex: 0, alignItems: 'center', justifyContent: 'center', overflow: 'hidden', borderRadius: 31, backgroundColor: COLORS.paleGold },
  avatarImage: { width: '100%', height: '100%' },
  avatarInitial: { color: COLORS.navy, fontSize: 24, fontWeight: '700' },
  profileCopy: { flex: 1, minWidth: 0 },
  eyebrow: { color: '#858b99', fontSize: 8, fontWeight: '700', letterSpacing: 1 },
  name: { marginTop: 5, color: COLORS.navy, fontSize: 21, fontWeight: '700' },
  muted: { marginTop: 4, color: COLORS.muted, fontSize: 10, lineHeight: 16 },
  bio: { marginTop: 9, color: COLORS.ink, fontSize: 11, lineHeight: 17 },
  stats: { marginTop: 11, color: COLORS.goldDark, fontSize: 10, fontWeight: '700' },
  sectionHeading: { marginTop: 27, marginBottom: 11 },
  sectionTitle: { marginTop: 4, color: COLORS.navy, fontSize: 17, fontWeight: '700' },
  listingCard: { flexDirection: 'row', overflow: 'hidden', marginBottom: 9, borderWidth: 1, borderColor: COLORS.line, borderRadius: 10, backgroundColor: COLORS.white },
  listingImage: { width: 96, height: 100 },
  listingPlaceholder: { alignItems: 'center', justifyContent: 'center', backgroundColor: '#f0eee4' },
  listingPlaceholderText: { color: COLORS.goldDark, fontSize: 27 },
  listingCopy: { flex: 1, justifyContent: 'center', gap: 3, paddingHorizontal: 11 },
  listingTitle: { color: COLORS.navy, fontSize: 12, fontWeight: '700' },
  listingPrice: { marginTop: 4, color: COLORS.navy, fontSize: 12, fontWeight: '700' },
  emptyMessage: { padding: 15, borderWidth: 1, borderColor: COLORS.line, borderRadius: 9, backgroundColor: COLORS.white, color: COLORS.muted, fontSize: 11 },
  reviewForm: { padding: 15, borderWidth: 1, borderColor: COLORS.line, borderRadius: 10, backgroundColor: COLORS.white },
  formTitle: { color: COLORS.navy, fontSize: 15, fontWeight: '700' },
  fieldLabel: { marginTop: 13, marginBottom: 6, color: COLORS.ink, fontSize: 10, fontWeight: '700' },
  stars: { flexDirection: 'row', gap: 7 },
  starButton: { paddingVertical: 3, paddingRight: 4 },
  star: { color: '#d7d4ca', fontSize: 28 },
  starSelected: { color: '#c68e21' },
  reviewInput: { minHeight: 100, padding: 11, borderWidth: 1, borderColor: COLORS.line, borderRadius: 8, color: COLORS.ink, fontSize: 11, lineHeight: 17 },
  error: { marginTop: 8, color: '#a13d31', fontSize: 10 },
  notice: { marginTop: 8, color: COLORS.green, fontSize: 10 },
  disabledButton: { opacity: 0.55 },
  reviewCard: { marginTop: 9, padding: 14, borderWidth: 1, borderColor: COLORS.line, borderRadius: 10, backgroundColor: COLORS.white },
  reviewHeading: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 8 },
  reviewName: { color: COLORS.navy, fontSize: 11, fontWeight: '700' },
  reviewStars: { marginTop: 7, color: '#c68e21', fontSize: 15, letterSpacing: 1 },
  reviewBody: { marginTop: 6, color: COLORS.ink, fontSize: 11, lineHeight: 17 },
  disclaimer: { marginTop: 12, color: COLORS.muted, fontSize: 9, lineHeight: 15 },
});
