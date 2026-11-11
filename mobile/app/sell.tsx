import * as ImagePicker from 'expo-image-picker';
import { router, useLocalSearchParams } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useEffect, useRef, useState } from 'react';
import { SafeAreaView } from 'react-native-safe-area-context';
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
import { COLORS, CATEGORY_ICONS, ChoiceChip, Label, styles } from '../App';
import { getAuthToken, Listing, ListingType, requestJson } from '../src/lib/api';
import { MenuButton } from '../src/components/NavigationDrawer';

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
  const [image, setImage] = useState<ImagePicker.ImagePickerAsset | null>(null);
  const [removeImage, setRemoveImage] = useState(false);
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
        imageUrl.current = current.image_path ? '' : current.image_url ?? '';
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

  const pickImage = async () => {
    try {
      const permission = await ImagePicker.requestMediaLibraryPermissionsAsync();
      if (!permission.granted) {
        Alert.alert('Photo access needed', 'Allow PASABUY to access your photos to add a listing photo.');
        return;
      }

      const result = await ImagePicker.launchImageLibraryAsync({
        mediaTypes: ['images'],
        allowsEditing: true,
        aspect: [4, 3],
        quality: 0.85,
      });

      if (result.canceled) return;

      const selectedImage = result.assets[0];
      if (selectedImage.mimeType && !['image/jpeg', 'image/png', 'image/webp'].includes(selectedImage.mimeType)) {
        setError('Choose a JPG, PNG, or WebP photo.');
        return;
      }
      if (selectedImage.fileSize && selectedImage.fileSize > 2 * 1024 * 1024) {
        setError('Choose a photo that is no larger than 2 MB.');
        return;
      }

      setError('');
      setImage(selectedImage);
      setRemoveImage(false);
    } catch (issue) {
      setError(issue instanceof Error ? issue.message : 'Your photo could not be selected.');
    }
  };

  const submitListing = async () => {
    setError('');
    setPosting(true);

    try {
      const form = new FormData();
      if (editId) form.append('_method', 'PUT');
      form.append('title', title.current);
      form.append('description', description.current);
      form.append('price', String(Number(price.current)));
      form.append('type', type);
      form.append('rent_period', type === 'rent' ? rentPeriod : '');
      form.append('category', category);
      form.append('location', location.current);
      form.append('image_url', imageUrl.current.trim());
      form.append('remove_image', removeImage ? '1' : '0');
      form.append('condition', condition);
      if (image) {
        if (Platform.OS === 'web' && image.file) {
          form.append('image', image.file);
        } else {
          form.append('image', {
            uri: image.uri,
            name: image.fileName ?? 'listing-photo.jpg',
            type: image.mimeType ?? 'image/jpeg',
          } as unknown as Blob);
        }
      }

      const result = await requestJson<Listing>(editId ? `/my-listings/${editId}` : '/listings', {
        method: 'POST',
        body: form,
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
        <MenuButton />
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
                    {['hour', 'day', 'week', 'month'].map((period) => (
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

            <Label>Listing photo (JPG, PNG, or WebP; up to 2 MB)</Label>
            <Pressable
              accessibilityRole="button"
              accessibilityLabel={image ? 'Change listing photo' : 'Choose listing photo'}
              onPress={() => void pickImage()}
              style={{ minHeight: 150, alignItems: 'center', justifyContent: 'center', borderWidth: 1, borderColor: COLORS.line, borderRadius: 8, backgroundColor: '#f8f6f0', overflow: 'hidden' }}
            >
              {image ? (
                <Image source={{ uri: image.uri }} style={{ width: '100%', height: 150 }} resizeMode="cover" />
              ) : listing?.image_url && !removeImage ? (
                <Image source={{ uri: listing.image_url }} style={{ width: '100%', height: 150 }} resizeMode="cover" />
              ) : (
                <Text style={{ color: COLORS.muted, fontSize: 12 }}>Tap to choose a photo</Text>
              )}
            </Pressable>
            {image ? (
              <Pressable onPress={() => setImage(null)} style={{ marginTop: 6 }}>
                <Text style={{ color: COLORS.goldDark, fontSize: 12 }}>Clear selected photo</Text>
              </Pressable>
            ) : null}
            {listing?.image_path && !removeImage ? (
              <Pressable
                onPress={() => { setRemoveImage(true); setImage(null); }}
                style={{ marginTop: 6 }}
              >
                <Text style={{ color: COLORS.goldDark, fontSize: 12 }}>Remove current uploaded photo</Text>
              </Pressable>
            ) : null}

            <Label>Or a photo link (optional)</Label>
            <TextInput
              autoCapitalize="none"
              defaultValue={listing?.image_path ? '' : listing?.image_url ?? undefined}
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
