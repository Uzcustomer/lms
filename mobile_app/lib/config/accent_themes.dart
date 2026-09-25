import 'package:flutter/material.dart';

/// A colour scheme the student picks in Settings. It drives more than the
/// hero cards: the active tab, the header icon, the day strip in the
/// timetable, tile icons in "Foydali" and "Xizmatlar" - anything that was
/// a fixed brand colour before.
///
/// [start]/[end] make the hero gradient. [primary] is the interactive
/// colour (tabs, links, selected states) and [primaryDark] its dark-mode
/// lift, since a colour that reads on white often disappears on navy.
/// [palette] is the per-tile spread, in a fixed order so a given tile keeps
/// its colour across schemes.
class AccentTheme {
  final String id;
  final Color start;
  final Color end;
  final Color primary;
  final Color primaryDark;
  final List<Color> palette;

  const AccentTheme({
    required this.id,
    required this.start,
    required this.end,
    required this.primary,
    required this.primaryDark,
    required this.palette,
  });

  List<Color> get gradient => [start, end];

  /// Interactive colour for the current brightness.
  Color primaryOf(bool isDark) => isDark ? primaryDark : primary;

  /// Colour for tile [i]; wraps around when there are more tiles than hues.
  Color tile(int i) => palette[i % palette.length];
}

class AccentThemes {
  static const teal = AccentTheme(
    id: 'teal',
    start: Color(0xFF0D9488),
    end: Color(0xFF1E3A8A),
    primary: Color(0xFF0D9488),
    primaryDark: Color(0xFF2DD4BF),
    palette: [
      Color(0xFF0D9488), Color(0xFF0369A1), Color(0xFF7C3AED),
      Color(0xFF0891B2), Color(0xFF059669), Color(0xFFC2410C),
    ],
  );

  static const blue = AccentTheme(
    id: 'blue',
    start: Color(0xFF2563EB),
    end: Color(0xFF1E1B4B),
    primary: Color(0xFF2563EB),
    primaryDark: Color(0xFF60A5FA),
    palette: [
      Color(0xFF2563EB), Color(0xFF4338CA), Color(0xFF0891B2),
      Color(0xFF7C3AED), Color(0xFF0369A1), Color(0xFF1D4ED8),
    ],
  );

  static const violet = AccentTheme(
    id: 'violet',
    start: Color(0xFF7C3AED),
    end: Color(0xFF312E81),
    primary: Color(0xFF7C3AED),
    primaryDark: Color(0xFFA78BFA),
    palette: [
      Color(0xFF7C3AED), Color(0xFFA21CAF), Color(0xFF4338CA),
      Color(0xFFC026D3), Color(0xFF6D28D9), Color(0xFF9333EA),
    ],
  );

  static const rose = AccentTheme(
    id: 'rose',
    start: Color(0xFFE11D48),
    end: Color(0xFF831843),
    primary: Color(0xFFE11D48),
    primaryDark: Color(0xFFFB7185),
    palette: [
      Color(0xFFE11D48), Color(0xFFDB2777), Color(0xFFBE123C),
      Color(0xFFC2410C), Color(0xFFA21CAF), Color(0xFF9F1239),
    ],
  );

  static const emerald = AccentTheme(
    id: 'emerald',
    start: Color(0xFF059669),
    end: Color(0xFF064E3B),
    primary: Color(0xFF059669),
    primaryDark: Color(0xFF34D399),
    palette: [
      Color(0xFF059669), Color(0xFF0D9488), Color(0xFF15803D),
      Color(0xFF0891B2), Color(0xFF65A30D), Color(0xFF047857),
    ],
  );

  static const amber = AccentTheme(
    id: 'amber',
    start: Color(0xFFF59E0B),
    end: Color(0xFF7C2D12),
    primary: Color(0xFFB45309),
    primaryDark: Color(0xFFFBBF24),
    palette: [
      Color(0xFFB45309), Color(0xFFC2410C), Color(0xFFD97706),
      Color(0xFFA16207), Color(0xFFEA580C), Color(0xFF92400E),
    ],
  );

  static const graphite = AccentTheme(
    id: 'graphite',
    start: Color(0xFF475569),
    end: Color(0xFF0F172A),
    primary: Color(0xFF475569),
    primaryDark: Color(0xFF94A3B8),
    palette: [
      Color(0xFF475569), Color(0xFF334155), Color(0xFF64748B),
      Color(0xFF1E293B), Color(0xFF52525B), Color(0xFF3F3F46),
    ],
  );

  // Three brighter ones: vivid starts, still deep enough at the end for
  // white text on the hero.
  static const sky = AccentTheme(
    id: 'sky',
    start: Color(0xFF0EA5E9),
    end: Color(0xFF1D4ED8),
    primary: Color(0xFF0284C7),
    primaryDark: Color(0xFF38BDF8),
    palette: [
      Color(0xFF0284C7), Color(0xFF2563EB), Color(0xFF0891B2),
      Color(0xFF0D9488), Color(0xFF4F46E5), Color(0xFF0369A1),
    ],
  );

  static const orange = AccentTheme(
    id: 'orange',
    start: Color(0xFFF97316),
    end: Color(0xFFB91C1C),
    primary: Color(0xFFEA580C),
    primaryDark: Color(0xFFFB923C),
    palette: [
      Color(0xFFEA580C), Color(0xFFDC2626), Color(0xFFD97706),
      Color(0xFFDB2777), Color(0xFFC2410C), Color(0xFFB45309),
    ],
  );

  static const fuchsia = AccentTheme(
    id: 'fuchsia',
    start: Color(0xFFD946EF),
    end: Color(0xFF6D28D9),
    primary: Color(0xFFC026D3),
    primaryDark: Color(0xFFE879F9),
    palette: [
      Color(0xFFC026D3), Color(0xFF9333EA), Color(0xFFDB2777),
      Color(0xFF7C3AED), Color(0xFFA21CAF), Color(0xFFE11D48),
    ],
  );

  static const all = [teal, blue, violet, rose, emerald, amber, graphite, sky, orange, fuchsia];

  static String labelOf(String id, String Function({required String uz, required String ru, required String en}) pick) {
    return switch (id) {
      'blue' => pick(uz: 'Ko\'k', ru: 'Синий', en: 'Blue'),
      'violet' => pick(uz: 'Binafsha', ru: 'Фиолетовый', en: 'Violet'),
      'rose' => pick(uz: 'Qizil', ru: 'Красный', en: 'Rose'),
      'emerald' => pick(uz: 'Zumrad', ru: 'Изумрудный', en: 'Emerald'),
      'amber' => pick(uz: 'Sariq', ru: 'Янтарный', en: 'Amber'),
      'graphite' => pick(uz: 'Grafit', ru: 'Графит', en: 'Graphite'),
      'sky' => pick(uz: 'Osmon', ru: 'Небесный', en: 'Sky'),
      'orange' => pick(uz: 'Apelsin', ru: 'Оранжевый', en: 'Orange'),
      'fuchsia' => pick(uz: 'Pushti', ru: 'Фуксия', en: 'Fuchsia'),
      _ => pick(uz: 'Feruza', ru: 'Бирюзовый', en: 'Teal'),
    };
  }

  static AccentTheme byId(String? id) =>
      all.firstWhere((t) => t.id == id, orElse: () => teal);

  /// The one in effect. SettingsProvider sets it; MaterialApp sits under a
  /// Consumer<SettingsProvider>, so the whole tree rebuilds on change.
  static AccentTheme current = teal;
}
