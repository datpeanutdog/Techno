import Constants from 'expo-constants';
import { Platform } from 'react-native';
import * as SecureStore from 'expo-secure-store';

export type ListingType = 'sale' | 'rent';

export type UserProfile = {
  id: number;
  name: string;
  email: string;
  bio: string | null;
  campus: string | null;
  avatar_url: string | null;
};

export type Listing = {
  id: number;
  title: string;
  slug: string;
  description: string;
  price: string | number;
  type: ListingType;
  rent_period: string | null;
  category: string;
  location: string;
  image_url: string | null;
  seller_name: string;
  seller_initials: string;
  seller_rating: string | number;
  condition: string | null;
  user?: Pick<UserProfile, 'id' | 'name' | 'bio' | 'campus' | 'avatar_url'> | null;
};

export type Conversation = {
  id: number;
  listing: Pick<Listing, 'id' | 'title' | 'slug' | 'type' | 'price' | 'rent_period' | 'image_url'>;
  other_participant: Pick<UserProfile, 'id' | 'name' | 'campus' | 'avatar_url'>;
  latest_message: Pick<ChatMessage, 'body' | 'created_at'> | null;
  unread_messages_count: number;
};

export type ChatMessage = {
  id: number;
  sender_id: number;
  body: string;
  created_at: string;
};

export type ConversationThread = Conversation & {
  messages: ChatMessage[];
};

type ApiResponse<T> = {
  data: T;
  token?: string;
  categories?: string[];
  message?: string;
  errors?: Record<string, string[]>;
};

const expoHost = Constants.expoConfig?.hostUri?.split(':')[0];
const configuredApiUrl = process.env.EXPO_PUBLIC_API_URL?.trim().replace(/\/+$/, '');

export const API_BASE_URL =
  configuredApiUrl ||
  (expoHost ? `http://${expoHost}:8000/api` : 'http://127.0.0.1:8000/api');

const REQUEST_TIMEOUT_MS = 15_000;

// FIXED: Removed colons because expo-secure-store only allows alphanumeric, '.', '-', and '_'
const AUTH_TOKEN_KEY = 'pasabuy_mobile_token';
let cachedAuthToken: string | null | undefined;

export async function getAuthToken(): Promise<string | null> {
  if (cachedAuthToken !== undefined) return cachedAuthToken;
  if (Platform.OS === 'web') {
    cachedAuthToken = typeof localStorage === 'undefined' ? null : localStorage.getItem(AUTH_TOKEN_KEY);
    return cachedAuthToken;
  }

  cachedAuthToken = await SecureStore.getItemAsync(AUTH_TOKEN_KEY);
  return cachedAuthToken;
}

export async function saveAuthToken(token: string): Promise<void> {
  if (Platform.OS === 'web') {
    localStorage.setItem(AUTH_TOKEN_KEY, token);
    cachedAuthToken = token;
    return;
  }

  await SecureStore.setItemAsync(AUTH_TOKEN_KEY, token);
  cachedAuthToken = token;
}

export async function clearAuthToken(): Promise<void> {
  if (Platform.OS === 'web') {
    localStorage.removeItem(AUTH_TOKEN_KEY);
    cachedAuthToken = null;
    return;
  }

  await SecureStore.deleteItemAsync(AUTH_TOKEN_KEY);
  cachedAuthToken = null;
}

export async function requestJson<T>(path: string, init?: RequestInit): Promise<ApiResponse<T>> {
  let response: Response;
  let result: ApiResponse<T>;
  const token = await getAuthToken();
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS);
  const isFormData = typeof FormData !== 'undefined' && init?.body instanceof FormData;

  try {
    response = await fetch(`${API_BASE_URL}${path}`, {
      ...init,
      signal: controller.signal,
      headers: {
        Accept: 'application/json',
        ...(init?.body && !isFormData ? { 'Content-Type': 'application/json' } : {}),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...init?.headers,
      },
    });
    result = (await response.json().catch(() => ({}))) as ApiResponse<T>;
  } catch {
    if (controller.signal.aborted) {
      throw new Error(
        `PASABUY took too long to respond. Check that Laravel is running on port 8000 and your phone can reach this address:\n\n${API_BASE_URL}`,
      );
    }

    throw new Error(
      `Can't reach PASABUY right now. Check that Laravel is running and your phone is on the same Wi-Fi.\n\nAPI: ${API_BASE_URL}`,
    );
  } finally {
    clearTimeout(timeout);
  }

  if (controller.signal.aborted) {
    throw new Error(
      `PASABUY took too long to respond. Check that Laravel is running on port 8000 and your phone can reach this address:\n\n${API_BASE_URL}`,
    );
  }

  if (!response.ok) {
    const validationMessage = result.errors
      ? Object.values(result.errors).flat().join('\n')
      : null;
    throw new Error(validationMessage ?? result.message ?? `Request failed (${response.status}).`);
  }

  return result;
}

export function formatPrice(price: string | number) {
  return `₱${Number(price).toLocaleString('en-PH', {
    maximumFractionDigits: 2,
  })}`;
}

// FIXED: Cleaned up colons here as well in case this key gets passed to SecureStore later
export const FAVORITES_KEY = 'pasabuy_mobile_favorites';