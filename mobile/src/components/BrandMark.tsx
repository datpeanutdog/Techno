import { View } from 'react-native';
import { COLORS } from '../lib/theme';

export function BrandMark() {
  return (
    <View style={styles.mark}>
      <View style={styles.handle} />
      <View style={styles.bag} />
      <View style={styles.checkShort} />
      <View style={styles.checkLong} />
    </View>
  );
}

const styles = {
  mark: { width: 32, height: 32 },
  handle: {
    position: 'absolute' as const,
    top: 3,
    left: 10,
    width: 12,
    height: 10,
    borderWidth: 1.5,
    borderBottomWidth: 0,
    borderColor: COLORS.navy,
    borderTopLeftRadius: 6,
    borderTopRightRadius: 6,
  },
  bag: {
    position: 'absolute' as const,
    left: 4,
    top: 10,
    width: 24,
    height: 19,
    borderRadius: 2,
    backgroundColor: COLORS.navy,
  },
  checkShort: {
    position: 'absolute' as const,
    left: 10,
    top: 19,
    width: 5,
    height: 1.5,
    borderRadius: 1,
    backgroundColor: COLORS.white,
    transform: [{ rotate: '45deg' }],
  },
  checkLong: {
    position: 'absolute' as const,
    left: 13,
    top: 18,
    width: 9,
    height: 1.5,
    borderRadius: 1,
    backgroundColor: COLORS.white,
    transform: [{ rotate: '-45deg' }],
  },
};
