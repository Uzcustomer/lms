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

  // ── To'qroq ranglar ──

  static const cobalt = AuroraTheme(
    id: 'cobalt',
    label: 'Kobalt',
    baseLight: Color(0xFFE1E8F5),
    baseDark: Color(0xFF0B1230),
    gradientLight: [Color(0xFF6882C4), Color(0xFF8BA3DB), Color(0xFFADBDE6), Color(0xFFE1E8F5)],
    gradientDark: [Color(0xFF2544A8), Color(0xFF3B5CC0), Color(0xFF5474D4), Color(0xFF0B1230)],
    blobALight: Color(0xFF7B96D4),
    blobADark: Color(0xFF4466C0),
    blobBLight: Color(0xFF95ADE0),
    blobBDark: Color(0xFF5B7ED6),
  );

  static const jade = AuroraTheme(
    id: 'jade',
    label: 'Nefrit',
    baseLight: Color(0xFFDCF0E8),
    baseDark: Color(0xFF0A1C16),
    gradientLight: [Color(0xFF52B788), Color(0xFF74C9A0), Color(0xFF9BDBB8), Color(0xFFDCF0E8)],
    gradientDark: [Color(0xFF1B7A4E), Color(0xFF2D9B65), Color(0xFF40B07A), Color(0xFF0A1C16)],
    blobALight: Color(0xFF63C295),
    blobADark: Color(0xFF2D9B65),
    blobBLight: Color(0xFF84D4AC),
    blobBDark: Color(0xFF40B07A),
  );

  static const plum = AuroraTheme(
    id: 'plum',
    label: 'Olxo\'ri',
    baseLight: Color(0xFFEBDEF2),
    baseDark: Color(0xFF160C20),
    gradientLight: [Color(0xFFA366C4), Color(0xFFB888D4), Color(0xFFCEA8E4), Color(0xFFEBDEF2)],
    gradientDark: [Color(0xFF7B2EA0), Color(0xFF9244B8), Color(0xFFA85CCC), Color(0xFF160C20)],
    blobALight: Color(0xFFAE78C8),
    blobADark: Color(0xFF8838AC),
    blobBLight: Color(0xFFC498DA),
    blobBDark: Color(0xFF9E50C2),
  );

  static const coral = AuroraTheme(
    id: 'coral',
    label: 'Marjon',
    baseLight: Color(0xFFF5E0DD),
    baseDark: Color(0xFF1C0E0C),
    gradientLight: [Color(0xFFE07060), Color(0xFFE89080), Color(0xFFF0ACA0), Color(0xFFF5E0DD)],
    gradientDark: [Color(0xFFC23A2A), Color(0xFFD45040), Color(0xFFE06858), Color(0xFF1C0E0C)],
    blobALight: Color(0xFFE48070),
    blobADark: Color(0xFFCC4434),
    blobBLight: Color(0xFFEC9C8E),
    blobBDark: Color(0xFFD85C4C),
  );

  static const sapphire = AuroraTheme(
    id: 'sapphire',
    label: 'Sapfir',
    baseLight: Color(0xFFDAE2F4),
    baseDark: Color(0xFF0A0E24),
    gradientLight: [Color(0xFF5068BE), Color(0xFF7488D0), Color(0xFF9AAAE0), Color(0xFFDAE2F4)],
    gradientDark: [Color(0xFF2040A4), Color(0xFF3458BC), Color(0xFF4A70D0), Color(0xFF0A0E24)],
    blobALight: Color(0xFF6078C6),
    blobADark: Color(0xFF3050B4),
    blobBLight: Color(0xFF8498D6),
    blobBDark: Color(0xFF4868C6),
  );

  static const emerald = AuroraTheme(
    id: 'emerald',
    label: 'Zumrad',
    baseLight: Color(0xFFD6EEE0),
    baseDark: Color(0xFF081C10),
    gradientLight: [Color(0xFF38A06C), Color(0xFF5CB888), Color(0xFF88CEA8), Color(0xFFD6EEE0)],
    gradientDark: [Color(0xFF14744A), Color(0xFF228E5C), Color(0xFF30A870), Color(0xFF081C10)],
    blobALight: Color(0xFF4AAE7A),
    blobADark: Color(0xFF1C8254),
    blobBLight: Color(0xFF70C496),
    blobBDark: Color(0xFF2E9C68),
  );

  static const ruby = AuroraTheme(
    id: 'ruby',
    label: 'Yoqut',
    baseLight: Color(0xFFF2DDE2),
    baseDark: Color(0xFF1C0A10),
    gradientLight: [Color(0xFFCC4466), Color(0xFFDA6880), Color(0xFFE48EA0), Color(0xFFF2DDE2)],
    gradientDark: [Color(0xFFA82040), Color(0xFFBE3454), Color(0xFFD04C6A), Color(0xFF1C0A10)],
    blobALight: Color(0xFFD45878),
    blobADark: Color(0xFFB42C4C),
    blobBLight: Color(0xFFE07A94),
    blobBDark: Color(0xFFC64060),
  );

  static const amber = AuroraTheme(
    id: 'amber',
    label: 'Qahrabo',
    baseLight: Color(0xFFF4EADB),
    baseDark: Color(0xFF1C1408),
    gradientLight: [Color(0xFFD49830), Color(0xFFDEB058), Color(0xFFE8C880), Color(0xFFF4EADB)],
    gradientDark: [Color(0xFFB07818), Color(0xFFC48E28), Color(0xFFD4A438), Color(0xFF1C1408)],
    blobALight: Color(0xFFD8A440),
    blobADark: Color(0xFFB88220),
    blobBLight: Color(0xFFE4BC64),
    blobBDark: Color(0xFFC89830),
  );

  static const orchid = AuroraTheme(
    id: 'orchid',
    label: 'Orkideya',
    baseLight: Color(0xFFF0DCF0),
    baseDark: Color(0xFF180C1A),
    gradientLight: [Color(0xFFC050B0), Color(0xFFD070C0), Color(0xFFDC94D0), Color(0xFFF0DCF0)],
    gradientDark: [Color(0xFF982890), Color(0xFFAE3EA4), Color(0xFFC054B6), Color(0xFF180C1A)],
    blobALight: Color(0xFFC860B4),
    blobADark: Color(0xFFA43098),
    blobBLight: Color(0xFFD680C6),
    blobBDark: Color(0xFFB448A8),
  );

  static const steel = AuroraTheme(
    id: 'steel',
    label: 'Po\'lat',
    baseLight: Color(0xFFE0E4EC),
    baseDark: Color(0xFF10141C),
    gradientLight: [Color(0xFF6878A0), Color(0xFF8898B8), Color(0xFFA8B4CC), Color(0xFFE0E4EC)],
    gradientDark: [Color(0xFF384870), Color(0xFF4C6088), Color(0xFF60789C), Color(0xFF10141C)],
    blobALight: Color(0xFF7888AC),
    blobADark: Color(0xFF445C84),
    blobBLight: Color(0xFF98A4C0),
    blobBDark: Color(0xFF5C7498),
  );

  static const light = <AuroraTheme>[
    sunrise, ocean, forest, sunset, midnight,
    roseGold, arctic, lavender, peach, mint,
  ];

  static const deep = <AuroraTheme>[
    cobalt, jade, plum, coral, sapphire,
    emerald, ruby, amber, orchid, steel,
  ];

  static const all = <AuroraTheme>[
    ...light,
    ...deep,
  ];

  static AuroraTheme byId(String id) {
    for (final t in all) {
      if (t.id == id) return t;
    }
    return sunrise;
  }
}
