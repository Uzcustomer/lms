import 'package:flutter/material.dart';

/// Colour of the hero cards - the gradient panels with the shine sweep
/// (profile card on the dashboard, grades summary, profile header, ...) -
/// and of their glow. Picked by the student in Settings; the text on
/// every hero is white, so any of these reads in light and dark alike.
class AccentTheme {
  final String id;
  final Color start;
  final Color end;

  const AccentTheme({required this.id, required this.start, required this.end});

  List<Color> get gradient => [start, end];
}

class AccentThemes {
  static const teal = AccentTheme(id: 'teal', start: Color(0xFF0D9488), end: Color(0xFF1E3A8A));
  static const blue = AccentTheme(id: 'blue', start: Color(0xFF2563EB), end: Color(0xFF1E1B4B));
  static const violet = AccentTheme(id: 'violet', start: Color(0xFF7C3AED), end: Color(0xFF312E81));
  static const rose = AccentTheme(id: 'rose', start: Color(0xFFE11D48), end: Color(0xFF831843));
  static const emerald = AccentTheme(id: 'emerald', start: Color(0xFF059669), end: Color(0xFF064E3B));
  static const amber = AccentTheme(id: 'amber', start: Color(0xFFF59E0B), end: Color(0xFF7C2D12));
  static const graphite = AccentTheme(id: 'graphite', start: Color(0xFF475569), end: Color(0xFF0F172A));

  static const all = [teal, blue, violet, rose, emerald, amber, graphite];

  static AccentTheme byId(String? id) =>
      all.firstWhere((t) => t.id == id, orElse: () => teal);

  /// The one in effect. SettingsProvider sets it; MaterialApp sits under a
  /// Consumer<SettingsProvider>, so the whole tree rebuilds on change.
  static AccentTheme current = teal;
}
