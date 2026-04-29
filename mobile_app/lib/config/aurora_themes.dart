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

  Color base(bool isDark) => isDark ? baseDark : baseLight;
  List<Color> gradient(bool isDark) => isDark ? gradientDark : gradientLight;
  Color blobA(bool isDark) => isDark ? blobADark : blobALight;
  Color blobB(bool isDark) => isDark ? blobBDark : blobBLight;

  Color get swatch => gradientLight.length >= 2 ? gradientLight[1] : baseLight;
}

class AuroraThemes {
  static const sunrise = AuroraTheme(
    id: 'sunrise',
    label: 'Tong shafagi',
    baseLight: Color(0xFFFEF7F0),
    baseDark: Color(0xFF0B1020),
    gradientLight: [
      Color(0xFFC7D2FE),
      Color(0xFFFBCFE8),
      Color(0xFFFED7AA),
      Color(0xFFFEF7F0),
    ],
    gradientDark: [
      Color(0xFF6366F1),
      Color(0xFFA855F7),
      Color(0xFFEC4899),
      Color(0xFF0B1020),
    ],
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
    gradientLight: [
      Color(0xFFA5F3FC),
      Color(0xFFBAE6FD),
      Color(0xFFC7D2FE),
      Color(0xFFF0F9FF),
    ],
    gradientDark: [
      Color(0xFF0EA5E9),
      Color(0xFF6366F1),
      Color(0xFF8B5CF6),
      Color(0xFF0A1628),
    ],
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
    gradientLight: [
      Color(0xFFBBF7D0),
      Color(0xFFA7F3D0),
      Color(0xFFFEF3C7),
      Color(0xFFF0FDF4),
    ],
    gradientDark: [
      Color(0xFF10B981),
      Color(0xFF14B8A6),
      Color(0xFFA855F7),
      Color(0xFF0A1F14),
    ],
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
    gradientLight: [
      Color(0xFFFECACA),
      Color(0xFFFED7AA),
      Color(0xFFFEF08A),
      Color(0xFFFFF7ED),
    ],
    gradientDark: [
      Color(0xFFEF4444),
      Color(0xFFF97316),
      Color(0xFFEAB308),
      Color(0xFF1A0A0A),
    ],
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
    gradientLight: [
      Color(0xFFDDD6FE),
      Color(0xFFC7D2FE),
      Color(0xFFE0E7FF),
      Color(0xFFF5F3FF),
    ],
    gradientDark: [
      Color(0xFF4338CA),
      Color(0xFF7C3AED),
      Color(0xFFC026D3),
      Color(0xFF0A0A1F),
    ],
    blobALight: Color(0xFFC4B5FD),
    blobADark: Color(0xFF8B5CF6),
    blobBLight: Color(0xFFA5B4FC),
    blobBDark: Color(0xFF6366F1),
  );

  static const all = <AuroraTheme>[sunrise, ocean, forest, sunset, midnight];

  static AuroraTheme byId(String id) {
    return all.firstWhere((t) => t.id == id, orElse: () => sunrise);
  }
}
