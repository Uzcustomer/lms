import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../config/theme.dart';
import '../l10n/app_localizations.dart';
import '../providers/settings_provider.dart';
import '../services/biometric_service.dart';

/// Shared settings bottom sheet — theme + language.
/// Used by the settings icon in every screen header.
void showSettingsSheet(BuildContext context) {
  final l = AppLocalizations.of(context);
  final settings = context.read<SettingsProvider>();

  showModalBottomSheet(
    context: context,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
    ),
    builder: (ctx) {
      return StatefulBuilder(
        builder: (ctx, setSheetState) {
          final isDark = settings.isDark;
          return Padding(
            padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Center(
                  child: Container(
                    width: 40,
                    height: 4,
                    decoration: BoxDecoration(
                      color: Colors.grey[400],
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                ),
                const SizedBox(height: 20),
                Text(
                  l.settings,
                  style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 20),

                // Theme toggle
                Text(
                  l.theme,
                  style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    _buildThemeOption(
                      ctx,
                      icon: Icons.light_mode,
                      label: l.lightMode,
                      isSelected: !isDark,
                      onTap: () {
                        settings.setThemeMode(ThemeMode.light);
                        setSheetState(() {});
                      },
                    ),
                    const SizedBox(width: 12),
                    _buildThemeOption(
                      ctx,
                      icon: Icons.dark_mode,
                      label: l.darkMode,
                      isSelected: isDark,
                      onTap: () {
                        settings.setThemeMode(ThemeMode.dark);
                        setSheetState(() {});
                      },
                    ),
                  ],
                ),
                const SizedBox(height: 20),

                // Language selection
                Text(
                  l.language,
                  style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    _buildLangOption(ctx, 'UZ', l.uzbek, 'uz', settings),
                    const SizedBox(width: 8),
                    _buildLangOption(ctx, 'RU', l.russian, 'ru', settings),
                    const SizedBox(width: 8),
                    _buildLangOption(ctx, 'EN', l.english, 'en', settings),
                  ],
                ),
                const _BiometricTile(),
                const SizedBox(height: 24),
              ],
            ),
          );
        },
      );
    },
  );
}

Widget _buildThemeOption(
  BuildContext context, {
  required IconData icon,
  required String label,
  required bool isSelected,
  required VoidCallback onTap,
}) {
  final isDk = Theme.of(context).brightness == Brightness.dark;
  final unselectedBg = isDk ? AppTheme.darkSurface : Colors.grey[200];
  final unselectedFg = isDk ? AppTheme.darkTextSecondary : Colors.grey[600];

  return Expanded(
    child: GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 14),
        decoration: BoxDecoration(
          color: isSelected ? AppTheme.primaryColor : unselectedBg,
          borderRadius: BorderRadius.circular(14),
        ),
        child: Column(
          children: [
            Icon(icon, color: isSelected ? Colors.white : unselectedFg, size: 28),
            const SizedBox(height: 6),
            Text(
              label,
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: isSelected ? Colors.white : unselectedFg,
              ),
            ),
          ],
        ),
      ),
    ),
  );
}

Widget _buildLangOption(
  BuildContext context,
  String code,
  String label,
  String langCode,
  SettingsProvider settings,
) {
  final isSelected = settings.languageCode == langCode;
  final isDk = Theme.of(context).brightness == Brightness.dark;
  final unselectedBg = isDk ? AppTheme.darkSurface : Colors.grey[200];
  final unselectedBorder = isDk ? AppTheme.darkBorderColor : Colors.grey[300]!;
  final unselectedCodeColor = isDk ? AppTheme.darkTextPrimary : Colors.grey[700];
  final unselectedLabelColor = isDk ? AppTheme.darkTextSecondary : Colors.grey[500];

  return Expanded(
    child: GestureDetector(
      onTap: () {
        settings.setLocale(Locale(langCode));
        Navigator.pop(context);
      },
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 12),
        decoration: BoxDecoration(
          color: isSelected ? AppTheme.primaryColor : unselectedBg,
          borderRadius: BorderRadius.circular(14),
          border: isSelected ? null : Border.all(color: unselectedBorder),
        ),
        child: Column(
          children: [
            Text(
              code,
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.bold,
                color: isSelected ? Colors.white : unselectedCodeColor,
              ),
            ),
            const SizedBox(height: 2),
            Text(
              label,
              style: TextStyle(
                fontSize: 11,
                color: isSelected ? Colors.white70 : unselectedLabelColor,
              ),
            ),
          ],
        ),
      ),
    ),
  );
}

/// Settings row to enable/disable biometric (Face ID / fingerprint) login.
/// Hidden entirely when the device has no biometrics.
class _BiometricTile extends StatefulWidget {
  const _BiometricTile();

  @override
  State<_BiometricTile> createState() => _BiometricTileState();
}

class _BiometricTileState extends State<_BiometricTile> {
  final _bio = BiometricService();
  bool? _available;
  bool _enabled = false;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final available = await _bio.isAvailable();
    final enabled = await _bio.isEnabled();
    if (mounted) {
      setState(() {
        _available = available;
        _enabled = enabled;
      });
    }
  }

  Future<void> _toggle(bool value) async {
    if (_busy) return;
    setState(() => _busy = true);
    if (value) {
      final ok = await _bio.authenticate(
        reason: 'Tasdiqlash uchun barmoq izi yoki Face ID',
      );
      if (ok) {
        await _bio.setEnabled(true);
        if (mounted) setState(() => _enabled = true);
      }
    } else {
      await _bio.setEnabled(false);
      await _bio.clearCredentials();
      if (mounted) setState(() => _enabled = false);
    }
    if (mounted) setState(() => _busy = false);
  }

  @override
  Widget build(BuildContext context) {
    if (_available != true) return const SizedBox.shrink();
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const SizedBox(height: 20),
        const Text(
          'Xavfsizlik',
          style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600),
        ),
        const SizedBox(height: 8),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
          decoration: BoxDecoration(
            color: isDark ? Colors.white.withOpacity(0.05)
                : const Color(0xFFF1F5F9),
            borderRadius: BorderRadius.circular(12),
          ),
          child: Row(
            children: [
              const Icon(Icons.fingerprint_rounded,
                  color: Color(0xFF0D9488), size: 24),
              const SizedBox(width: 12),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Barmoq izi / Face ID',
                      style: TextStyle(
                          fontSize: 13.5, fontWeight: FontWeight.w600),
                    ),
                    SizedBox(height: 1),
                    Text(
                      'Ilovaga tez va xavfsiz kirish',
                      style: TextStyle(fontSize: 11.5, color: Colors.grey),
                    ),
                  ],
                ),
              ),
              Switch(
                value: _enabled,
                onChanged: _busy ? null : _toggle,
                activeColor: const Color(0xFF0D9488),
              ),
            ],
          ),
        ),
      ],
    );
  }
}
