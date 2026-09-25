import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../config/accent_themes.dart';

class SettingsProvider extends ChangeNotifier {
  static const String _themeKey = 'theme_mode';
  static const String _localeKey = 'locale';
  static const String _accentKey = 'accent_theme';

  ThemeMode _themeMode = ThemeMode.light;
  Locale _locale = const Locale('uz');
  AccentTheme _accent = AccentThemes.teal;

  ThemeMode get themeMode => _themeMode;
  Locale get locale => _locale;
  String get languageCode => _locale.languageCode;
  AccentTheme get accent => _accent;

  SettingsProvider() {
    _loadSettings();
  }

  Future<void> _loadSettings() async {
    try {
      final prefs = await SharedPreferences.getInstance();

      final themeStr = prefs.getString(_themeKey) ?? 'light';
      _themeMode = themeStr == 'dark' ? ThemeMode.dark : ThemeMode.light;

      final localeStr = prefs.getString(_localeKey) ?? 'uz';
      _locale = Locale(localeStr);

      _accent = AccentThemes.byId(prefs.getString(_accentKey));
      AccentThemes.current = _accent;
    } catch (_) {
      // Keep defaults on failure
    }
    notifyListeners();
  }

  Future<void> setThemeMode(ThemeMode mode) async {
    _themeMode = mode;
    notifyListeners();
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_themeKey, mode == ThemeMode.dark ? 'dark' : 'light');
  }

  Future<void> toggleTheme() async {
    await setThemeMode(
      _themeMode == ThemeMode.dark ? ThemeMode.light : ThemeMode.dark,
    );
  }

  Future<void> setLocale(Locale locale) async {
    _locale = locale;
    notifyListeners();
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_localeKey, locale.languageCode);
  }

  Future<void> setAccent(AccentTheme theme) async {
    _accent = theme;
    AccentThemes.current = theme;
    notifyListeners();
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_accentKey, theme.id);
  }

  bool get isDark => _themeMode == ThemeMode.dark;
}
