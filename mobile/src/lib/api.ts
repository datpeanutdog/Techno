import Constants from 'expo-constants';
import { Platform } from 'react-native';
import * as SecureStore from 'expo-secure-store';

export type ListingType = 'sale' | 'rent';
export type PresenceStatus = 'online' | 'away' | 'offline';

export type UserProfile = {
  id: number;
  account_identifier: string;
  name: string;
  email: string;
  role: 'admin' | 'user';
  bio: string | null;
  campus: string | null;
  avatar_url: string | null;
  average_rating?: number | null;
  reviews_count?: number;
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
  image_path: string | null;
  seller_name: string;
  seller_initials: string;
  seller_rating: string | number;
  condition: string | null;
  created_at: string;
  user?: Pick<UserProfile, 'id' | 'name' | 'bio' | 'campus' | 'avatar_url'> & {
    received_reviews_count?: number;
    received_reviews_avg_rating?: number | string | null;
  } | null;
};

export type Conversation = {
  id: number;
  listing: Pick<Listing, 'id' | 'title' | 'slug' | 'type' | 'price' | 'rent_period' | 'image_url'>;
  other_participant: Pick<UserProfile, 'id' | 'name' | 'campus' | 'avatar_url'> & {
    presence_status: PresenceStatus;
  };
  latest_message: Pick<ChatMessage, 'body' | 'created_at'> | null;
  unread_messages_count: number;
};

export type ChatMessage = {
  id: number;
  sender_id: number;
  sender_name: string;
  sender_avatar_url: string | null;
  body: string;
  created_at: string;
  read_at: string | null;
};

export type FetchRequest = {
  id: number | string;
  item_name: string;
  details: string;
  budget: string | number | null;
  delivery_location: string;
  created_at: string;
  expires_at: string;
  expires_at_display: string;
  delivery_deadline: string;
  delivery_deadline_display: string;
  status: 'open' | 'accepted' | 'delivered' | 'completed';
  can_accept: boolean;
  accepted_at: string | null;
  delivered_at: string | null;
  confirmed_at: string | null;
  requester: { id: number; name: string };
  accepted_by: { id: number; name: string } | null;
};

export type FetchDashboard = {
  accepted: FetchRequest[];
  posted: FetchRequest[];
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
  meta?: {
    current_page: number;
    last_page: number;
    total: number;
  };
  available_ids?: (number | string)[];
};

export class ApiError extends Error {
  constructor(message: string, public readonly status: number) {
    super(message);
    this.name = 'ApiError';
  }
}

export function isApiErrorStatus(issue: unknown, status: number): issue is ApiError {
  return issue instanceof ApiError && issue.status === status;
}

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

export async function requestJson<T>(
  path: string,
  init?: RequestInit,
  timeoutMs = REQUEST_TIMEOUT_MS,
): Promise<ApiResponse<T>> {
  let response: Response;
  let result: ApiResponse<T>;
  const token = await getAuthToken();
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), timeoutMs);
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
        `Pasabuy took too long to respond. Check that Laravel is running on port 8000 and your phone can reach this address:\n\n${API_BASE_URL}`,
      );
    }

    throw new Error(
      `Can't reach Pasabuy right now. Check that Laravel is running and your phone is on the same Wi-Fi.\n\nAPI: ${API_BASE_URL}`,
    );
  } finally {
    clearTimeout(timeout);
  }

  if (controller.signal.aborted) {
    throw new Error(
      `Pasabuy took too long to respond. Check that Laravel is running on port 8000 and your phone can reach this address:\n\n${API_BASE_URL}`,
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