import { View } from 'react-native';
import { COLORS } from '../lib/theme';

export function BookmarkIcon({ saved }: { saved: boolean }) {
  const color = saved ? '#c9595f' : COLORS.muted;

  return (
    <View style={styles.icon}>
      {saved ? (
        <>
          <View style={[styles.fill, { backgroundColor: color }]} />
          <View style={[styles.cutout, { borderBottomColor: COLORS.white }]} />
        </>
      ) : (
        <>
          <View style={[styles.top, { backgroundColor: color }]} />
          <View style={[styles.left, { backgroundColor: color }]} />
          <View style={[styles.right, { backgroundColor: color }]} />
          <View style={[styles.leftNotch, { backgroundColor: color }]} />
          <View style={[styles.rightNotch, { backgroundColor: color }]} />
        </>
      )}
    </View>
  );
}

const styles = {
  icon: { width: 16, height: 20 },
  fill: {
    position: 'absolute' as const,
    top: 1,
    left: 2,
    width: 12,
    height: 17,
    borderTopLeftRadius: 2,
    borderTopRightRadius: 2,
  },
  cutout: {
    position: 'absolute' as const,
    left: 3,
    bottom: 2,
    width: 0,
    height: 0,
    borderLeftWidth: 5,
    borderRightWidth: 5,
    borderBottomWidth: 6,
    borderLeftColor: 'transparent',
    borderRightColor: 'transparent',
  },
  top: { position: 'absolute' as const, left: 2, top: 1, width: 12, height: 1.5 },
  left: { position: 'absolute' as const, left: 2, top: 1, width: 1.5, height: 16 },
  right: { position: 'absolute' as const, right: 2, top: 1, width: 1.5, height: 16 },
  leftNotch: {
    position: 'absolute' as const,
    left: 2,
    top: 14,
    width: 7,
    height: 1.5,
    transform: [{ rotate: '-35deg' }],
  },
  rightNotch: {
    position: 'absolute' as const,
    left: 7,
    top: 14,
    width: 7,
    height: 1.5,
    transform: [{ rotate: '35deg' }],
  },
};
