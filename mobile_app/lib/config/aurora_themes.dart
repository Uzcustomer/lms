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

const _defaultBase = Color(0xFFA8B4CC);
const _defaultBaseDark = Color(0xFF10141C);
const _defaultGradient = [Color(0xFFA8B4CC), Color(0xFFA8B4CC), Color(0xFFA8B4CC), Color(0xFFA8B4CC)];
const _defaultGradientDark = [Color(0xFF10141C), Color(0xFF10141C), Color(0xFF10141C), Color(0xFF10141C)];

Color auroraBase(AuroraTheme? t, bool isDark) =>
    isDark ? (t?.baseDark ?? _defaultBaseDark) : (t?.baseLight ?? _defaultBase);

List<Color> auroraGradient(AuroraTheme? t, bool isDark) =>
    isDark ? (t?.gradientDark ?? _defaultGradientDark) : (t?.gradientLight ?? _defaultGradient);

Color auroraBlobA(AuroraTheme? t, bool isDark) =>
    isDark ? (t?.blobADark ?? const Color(0xFF445C84)) : (t?.blobALight ?? const Color(0xFF7888AC));

Color auroraBlobB(AuroraTheme? t, bool isDark) =>
    isDark ? (t?.blobBDark ?? const Color(0xFF5C7498)) : (t?.blobBLight ?? const Color(0xFF98A4C0));

class AuroraThemes {
  static const steel = AuroraTheme(
    id: 'steel',
    label: 'Po\'lat',
    baseLight: Color(0xFFA8B4CC),
    baseDark: Color(0xFF10141C),
    gradientLight: [Color(0xFFA8B4CC), Color(0xFFA8B4CC), Color(0xFFA8B4CC), Color(0xFFA8B4CC)],
    gradientDark: [Color(0xFF10141C), Color(0xFF10141C), Color(0xFF10141C), Color(0xFF10141C)],
    blobALight: Color(0xFFA8B4CC),
    blobADark: Color(0xFF10141C),
    blobBLight: Color(0xFFA8B4CC),
    blobBDark: Color(0xFF10141C),
  );

  static AuroraTheme byId(String id) => steel;
}
