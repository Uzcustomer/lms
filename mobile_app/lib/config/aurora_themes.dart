import 'package:flutter/material.dart';

class AuroraTheme {
  final String id;
  final String label;
  final Color baseLight;
  final Color baseDark;
  final List<Color> gradientLight;
  final List<Color> gradientDark;
  final Color blobALight;
  final Color blobADark;
  final Color blobBLight;
  final Color blobBDark;

  const AuroraTheme({
    required this.id,
    required this.label,
    required this.baseLight,
    required this.baseDark,
    required this.gradientLight,
    required this.gradientDark,
    required this.blobALight,
    required this.blobADark,
    required this.blobBLight,
    required this.blobBDark,
  });
}

const _defaultBase = Color(0xFFFEF7F0);
const _defaultBaseDark = Color(0xFF0B1020);
const _defaultGradient = [Color(0xFFC7D2FE), Color(0xFFFBCFE8), Color(0xFFFED7AA), Color(0xFFFEF7F0)];
const _defaultGradientDark = [Color(0xFF6366F1), Color(0xFFA855F7), Color(0xFFEC4899), Color(0xFF0B1020)];

Color auroraBase(AuroraTheme? t, bool isDark) =>
    isDark ? (t?.baseDark ?? _defaultBaseDark) : (t?.baseLight ?? _defaultBase);

List<Color> auroraGradient(AuroraTheme? t, bool isDark) =>
    isDark ? (t?.gradientDark ?? _defaultGradientDark) : (t?.gradientLight ?? _defaultGradient);

Color auroraBlobA(AuroraTheme? t, bool isDark) =>
    isDark ? (t?.blobADark ?? const Color(0xFFF472B6)) : (t?.blobALight ?? const Color(0xFFF9A8D4));

Color auroraBlobB(AuroraTheme? t, bool isDark) =>
    isDark ? (t?.blobBDark ?? const Color(0xFF60A5FA)) : (t?.blobBLight ?? const Color(0xFFA5B4FC));

class AuroraThemes {
  static const sunrise = AuroraTheme(
    id: 'sunrise',
    label: 'Tong shafagi',
    baseLight: Color(0xFFFEF7F0),
    baseDark: Color(0xFF0B1020),
    gradientLight: [Color(0xFFC7D2FE), Color(0xFFFBCFE8), Color(0xFFFED7AA), Color(0xFFFEF7F0)],
    gradientDark: [Color(0xFF6366F1), Color(0xFFA855F7), Color(0xFFEC4899), Color(0xFF0B1020)],
    blobALight: Color(0xFFF9A8D4),
    blobADark: Color(0xFFF472B6),
    blobBLight: Color(0xFFA5B4FC),
    blobBDark: Color(0xFF60A5FA),
  );

  static const ocean = AuroraTheme(
    id: 'ocean',
    label: 'Okean',
    baseLight: Color(0xFFF0F9FF),
    baseDark: Color(0xFF0A1628),
    gradientLight: [Color(0xFFA5F3FC), Color(0xFFBAE6FD), Color(0xFFC7D2FE), Color(0xFFF0F9FF)],
    gradientDark: [Color(0xFF0EA5E9), Color(0xFF6366F1), Color(0xFF8B5CF6), Color(0xFF0A1628)],
    blobALight: Color(0xFF7DD3FC),
    blobADark: Color(0xFF38BDF8),
    blobBLight: Color(0xFFA7F3D0),
    blobBDark: Color(0xFF34D399),
  );

  static const forest = AuroraTheme(
    id: 'forest',
    label: 'Yashil o\'rmon',
    baseLight: Color(0xFFF0FDF4),
    baseDark: Color(0xFF0A1F14),
    gradientLight: [Color(0xFFBBF7D0), Color(0xFFA7F3D0), Color(0xFFFEF3C7), Color(0xFFF0FDF4)],
    gradientDark: [Color(0xFF10B981), Color(0xFF14B8A6), Color(0xFFA855F7), Color(0xFF0A1F14)],
    blobALight: Color(0xFF86EFAC),
    blobADark: Color(0xFF34D399),
    blobBLight: Color(0xFFFCD34D),
    blobBDark: Color(0xFFEAB308),
  );

  static const sunset = AuroraTheme(
    id: 'sunset',
    label: 'Quyosh botishi',
    baseLight: Color(0xFFFFF7ED),
    baseDark: Color(0xFF1A0A0A),
    gradientLight: [Color(0xFFFECACA), Color(0xFFFED7AA), Color(0xFFFEF08A), Color(0xFFFFF7ED)],
    gradientDark: [Color(0xFFEF4444), Color(0xFFF97316), Color(0xFFEAB308), Color(0xFF1A0A0A)],
    blobALight: Color(0xFFFCA5A5),
    blobADark: Color(0xFFF87171),
    blobBLight: Color(0xFFFDBA74),
    blobBDark: Color(0xFFFB923C),
  );

  static const midnight = AuroraTheme(
    id: 'midnight',
    label: 'Yarim tun',
    baseLight: Color(0xFFF5F3FF),
    baseDark: Color(0xFF0A0A1F),
    gradientLight: [Color(0xFFDDD6FE), Color(0xFFC7D2FE), Color(0xFFE0E7FF), Color(0xFFF5F3FF)],
    gradientDark: [Color(0xFF4338CA), Color(0xFF7C3AED), Color(0xFFC026D3), Color(0xFF0A0A1F)],
    blobALight: Color(0xFFC4B5FD),
    blobADark: Color(0xFF8B5CF6),
    blobBLight: Color(0xFFA5B4FC),
    blobBDark: Color(0xFF6366F1),
  );

  static const roseGold = AuroraTheme(
    id: 'rose_gold',
    label: 'Oltin atirgul',
    baseLight: Color(0xFFFFF1F2),
    baseDark: Color(0xFF1A0F10),
    gradientLight: [Color(0xFFFDA4AF), Color(0xFFFECDD3), Color(0xFFFFE4E6), Color(0xFFFFF1F2)],
    gradientDark: [Color(0xFFF43F5E), Color(0xFFFB7185), Color(0xFF9F1239), Color(0xFF1A0F10)],
    blobALight: Color(0xFFFB7185),
    blobADark: Color(0xFFE11D48),
    blobBLight: Color(0xFFFECDD3),
    blobBDark: Color(0xFFFDA4AF),
  );

  static const arctic = AuroraTheme(
    id: 'arctic',
    label: 'Arktika muzi',
    baseLight: Color(0xFFF0F9FF),
    baseDark: Color(0xFF0C1524),
    gradientLight: [Color(0xFFE0F2FE), Color(0xFFBAE6FD), Color(0xFFE0F2FE), Color(0xFFF0F9FF)],
    gradientDark: [Color(0xFF0284C7), Color(0xFF0369A1), Color(0xFF075985), Color(0xFF0C1524)],
    blobALight: Color(0xFF7DD3FC),
    blobADark: Color(0xFF0EA5E9),
    blobBLight: Color(0xFFBAE6FD),
    blobBDark: Color(0xFF38BDF8),
  );

  static const lavender = AuroraTheme(
    id: 'lavender',
    label: 'Lavanda',
    baseLight: Color(0xFFFAF5FF),
    baseDark: Color(0xFF120A20),
    gradientLight: [Color(0xFFE9D5FF), Color(0xFFD8B4FE), Color(0xFFF3E8FF), Color(0xFFFAF5FF)],
    gradientDark: [Color(0xFF9333EA), Color(0xFFA855F7), Color(0xFF7E22CE), Color(0xFF120A20)],
    blobALight: Color(0xFFD8B4FE),
    blobADark: Color(0xFFA855F7),
    blobBLight: Color(0xFFC084FC),
    blobBDark: Color(0xFF9333EA),
  );

  static const peach = AuroraTheme(
    id: 'peach',
    label: 'Shaftoli',
    baseLight: Color(0xFFFFF7ED),
    baseDark: Color(0xFF1C1008),
    gradientLight: [Color(0xFFFDBA74), Color(0xFFFED7AA), Color(0xFFFFEDD5), Color(0xFFFFF7ED)],
    gradientDark: [Color(0xFFF97316), Color(0xFFEA580C), Color(0xFFC2410C), Color(0xFF1C1008)],
    blobALight: Color(0xFFFDBA74),
    blobADark: Color(0xFFFB923C),
    blobBLight: Color(0xFFFED7AA),
    blobBDark: Color(0xFFF97316),
  );

  static const mint = AuroraTheme(
    id: 'mint',
    label: 'Yalpiz',
    baseLight: Color(0xFFF0FDFA),
    baseDark: Color(0xFF0A1A18),
    gradientLight: [Color(0xFF99F6E4), Color(0xFFA7F3D0), Color(0xFFCCFBF1), Color(0xFFF0FDFA)],
    gradientDark: [Color(0xFF14B8A6), Color(0xFF0D9488), Color(0xFF0F766E), Color(0xFF0A1A18)],
    blobALight: Color(0xFF5EEAD4),
    blobADark: Color(0xFF2DD4BF),
    blobBLight: Color(0xFF99F6E4),
    blobBDark: Color(0xFF14B8A6),
  );

  static const all = <AuroraTheme>[
    sunrise, ocean, forest, sunset, midnight,
    roseGold, arctic, lavender, peach, mint,
  ];

  static AuroraTheme byId(String id) {
    for (final t in all) {
      if (t.id == id) return t;
    }
    return sunrise;
  }
}
