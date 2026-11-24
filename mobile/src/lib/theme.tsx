import AsyncStorage from '@react-native-async-storage/async-storage';
import { createContext, ReactNode, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { StyleSheet } from 'react-native';

export type ThemeMode = 'light' | 'dark';

const THEME_STORAGE_KEY = 'pasabuy_mobile_theme';

const lightColors = {
  navy: '#4a382c',
  navyDeep: '#33251d',
  gold: '#dfa13b',
  goldDark: '#845b2b',
  cream: '#f4ede2',
  white: '#fffdf8',
  ink: '#3e3027',
  muted: '#7f7468',
  line: '#e7ddcf',
  green: '#496d58',
  paleGold: '#f5efdf',
  paleNeutral: '#efe5d8',
  placeholder: '#9699a4',
};

const darkColors: typeof lightColors = {
  navy: '#f0e5d8',
  navyDeep: '#2b241d',
  gold: '#dfa13b',
  goldDark: '#e5b763',
  cream: '#171411',
  white: '#24211d',
  ink: '#f1e8dd',
  muted: '#b3a698',
  line: '#4a4036',
  green: '#9bd5a5',
  paleGold: '#4a3c28',
  paleNeutral: '#38312a',
  placeholder: '#aaa7a2',
};

const darkColorMap: Record<string, string> = {
  '#25834b': '#78c68c',
  '#33251d': '#2b241d',
  '#33251d80': '#00000099',
  '#3d6244': '#a7d7a8',
  '#3e3027': '#f1e8dd',
  '#4a382c': '#f0e5d8',
  '#705a3d': '#d2bda0',
  '#766b5f': '#b3a698',
  '#77570f': '#f1ce7b',
  '#795b1a': '#f1cf78',
  '#7f7468': '#b3a698',
  '#80503f': '#e0b19b',
  '#845b2b': '#e5b763',
  '#8a8177': '#b3a698',
  '#9699a4': '#aaa7a2',
  '#a0453e': '#ff9a8b',
  '#a13d31': '#ff9a8b',
  '#a34d3f': '#ff9a8b',
  '#c18a24': '#e8ba5b',
  '#c68e21': '#dfad52',
  '#c9595f': '#ed777f',
  '#d7d4ca': '#6d675f',
  '#e5d6c8': '#e5d6c8',
  '#e6efe5': '#304536',
  '#e7ddcf': '#4a4036',
  '#eee7d9': '#3d3328',
  '#efe5d8': '#38312a',
  '#f0eee4': '#302a23',
  '#f1e2d9': '#463129',
  '#f2eddf': '#302a23',
  '#f4ede2': '#171411',
  '#f5efdf': '#4a3c28',
  '#f6edd6': '#4a3d27',
  '#f8f6f0': '#2b241d',
  '#fffdf8': '#24211d',
  '#fffdf8ed': '#24211ded',
};

let activeTheme: ThemeMode = 'light';

type ThemeContextValue = {
  mode: ThemeMode;
  setMode: (mode: ThemeMode) => Promise<void>;
  error: string;
};

const ThemeContext = createContext<ThemeContextValue | null>(null);

export function ThemeProvider({ children }: { children: ReactNode }) {
  const [mode, setModeState] = useState<ThemeMode>('light');
  const [error, setError] = useState('');

  useEffect(() => {
    let active = true;
    void AsyncStorage.getItem(THEME_STORAGE_KEY).then((savedMode) => {
      if (!active || (savedMode !== 'light' && savedMode !== 'dark')) return;
      activeTheme = savedMode;
      setModeState(savedMode);
    }).catch((issue: unknown) => {
      console.error('Unable to load the saved appearance preference.', issue);
      if (active) setError('Your saved appearance preference could not be loaded.');
    });

    return () => {
      active = false;
    };
  }, []);

  const setMode = useCallback(async (nextMode: ThemeMode) => {
    setError('');
    try {
      await AsyncStorage.setItem(THEME_STORAGE_KEY, nextMode);
      activeTheme = nextMode;
      setModeState(nextMode);
    } catch (issue) {
      console.error('Unable to save the appearance preference.', issue);
      setError('Your appearance preference could not be saved. Please try again.');
      throw issue;
    }
  }, []);

  const value = useMemo(() => ({ mode, setMode, error }), [error, mode, setMode]);

  return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>;
}

export function useAppTheme() {
  const context = useContext(ThemeContext);
  if (!context) {
    throw new Error('useAppTheme must be used within a ThemeProvider.');
  }
  return context;
}

export const COLORS = new Proxy(lightColors, {
  get(_target, key: keyof typeof lightColors) {
    return (activeTheme === 'dark' ? darkColors : lightColors)[key];
  },
});

export function getActiveTheme(): ThemeMode {
  return activeTheme;
}

export function createThemedStyles<T extends Parameters<typeof StyleSheet.create>[0]>(
  definitions: T & Parameters<typeof StyleSheet.create>[0],
): Readonly<T> {
  const baseStyles = StyleSheet.create(definitions);
  const cache = new WeakMap<object, Partial<Record<ThemeMode, object>>>();

  return new Proxy(baseStyles, {
    get(target, key: string | symbol, receiver) {
      const style = Reflect.get(target, key, receiver);
      if (!style || typeof style !== 'object') return style;

      const cachedStyles = cache.get(style) ?? {};
      if (cachedStyles[activeTheme]) return cachedStyles[activeTheme];

      const themedStyle = Object.fromEntries(
        Object.entries(style).map(([property, value]) => [
          property,
          typeof value === 'string' && /color|tint/i.test(property)
            ? activeTheme === 'dark' ? darkColorMap[value.toLowerCase()] ?? value : value
            : value,
        ]),
      );
      cachedStyles[activeTheme] = themedStyle;
      cache.set(style, cachedStyles);

      return themedStyle;
    },
  });
}
