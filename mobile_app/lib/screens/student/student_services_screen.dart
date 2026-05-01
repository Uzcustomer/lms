import 'dart:ui';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/theme.dart';
import '../../config/aurora_themes.dart';
import '../../providers/settings_provider.dart';
import '../../l10n/app_localizations.dart';
import '../../utils/page_transitions.dart';
import '../../widgets/scale_tap.dart';
import 'absence_excuse_list_screen.dart';
import 'appeals_list_screen.dart';
import 'clubs_screen.dart';

class StudentServicesScreen extends StatelessWidget {
  const StudentServicesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final aurora = context.watch<SettingsProvider>().auroraTheme;
    final statusBarH = MediaQuery.of(context).padding.top;

    final services = [
      _ServiceItem(
        icon: Icons.description_outlined,
        title: l.absenceExcuse,
        subtitle: l.absenceExcuseDesc,
        color: AppTheme.primaryColor,
        onTap: () => Navigator.push(
          context,
          SlideFadePageRoute(builder: (_) => const AbsenceExcuseListScreen()),
        ),
      ),
      _ServiceItem(
        icon: Icons.groups_outlined,
        title: l.clubs,
        subtitle: l.clubsDesc,
        color: const Color(0xFF4F46E5),
        onTap: () => Navigator.push(
          context,
          SlideFadePageRoute(builder: (_) => const ClubsScreen()),
        ),
      ),
      _ServiceItem(
        icon: Icons.gavel_outlined,
        title: l.appeal,
        subtitle: l.appealDesc,
        color: const Color(0xFF7C3AED),
        onTap: () => Navigator.push(
          context,
          SlideFadePageRoute(builder: (_) => const AppealsListScreen()),
        ),
      ),
    ];

    return Scaffold(
      backgroundColor: auroraBase(aurora, isDark),
      body: Stack(
        children: [
          Positioned(
            top: -120,
            left: -100,
            child: _buildBlob(auroraBlobA(aurora, isDark).withOpacity(isDark ? 0.55 : 0.45)),
          ),
          Positioned(
            top: 200,
            right: -120,
            child: _buildBlob(auroraBlobB(aurora, isDark).withOpacity(isDark ? 0.55 : 0.45)),
          ),
          Column(
            children: [
              Container(
                padding: EdgeInsets.only(top: statusBarH, left: 4, right: 4),
                height: statusBarH + 64,
                decoration: const BoxDecoration(
                  color: Color(0xFF0A1A3A),
                  borderRadius: BorderRadius.only(
                    bottomLeft: Radius.circular(18),
                    bottomRight: Radius.circular(18),
                  ),
                ),
                child: Row(
                  children: [
                    IconButton(
                      icon: const Icon(Icons.arrow_back, color: Colors.white, size: 22),
                      onPressed: () => Navigator.pop(context),
                    ),
                    Expanded(
                      child: Text(
                        l.services,
                        style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: Colors.white),
                        textAlign: TextAlign.center,
                      ),
                    ),
                    const SizedBox(width: 48),
                  ],
                ),
              ),
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: GridView.builder(
                    gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                      crossAxisCount: 2,
                      crossAxisSpacing: 12,
                      mainAxisSpacing: 12,
                      childAspectRatio: 1.0,
                    ),
                    itemCount: services.length,
                    itemBuilder: (context, index) {
                      final item = services[index];
                      return _ServiceCard(item: item, isDark: isDark);
                    },
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildBlob(Color color) {
    return ImageFiltered(
      imageFilter: ImageFilter.blur(sigmaX: 24, sigmaY: 24),
      child: Container(
        width: 240,
        height: 240,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          gradient: RadialGradient(
            colors: [color, color.withOpacity(0)],
            stops: const [0.0, 0.7],
          ),
        ),
      ),
    );
  }
}

class _ServiceItem {
  final IconData icon;
  final String title;
  final String subtitle;
  final Color color;
  final VoidCallback onTap;

  const _ServiceItem({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.color,
    required this.onTap,
  });
}

class _ServiceCard extends StatelessWidget {
  final _ServiceItem item;
  final bool isDark;

  const _ServiceCard({required this.item, required this.isDark});

  @override
  Widget build(BuildContext context) {
    final textColor = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;
    final subColor = isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;
    final surface = isDark ? Colors.white.withOpacity(0.10) : Colors.white.withOpacity(0.7);
    final border = isDark ? Colors.white.withOpacity(0.12) : Colors.white.withOpacity(0.9);

    return ScaleTap(
      onTap: item.onTap,
      child: ClipRRect(
        borderRadius: BorderRadius.circular(20),
        child: BackdropFilter(
          filter: ImageFilter.blur(sigmaX: 20, sigmaY: 20),
          child: Container(
            decoration: BoxDecoration(
              color: surface,
              border: Border.all(color: border),
              borderRadius: BorderRadius.circular(20),
              boxShadow: [
                BoxShadow(
                  color: isDark
                      ? Colors.black.withOpacity(0.3)
                      : const Color(0xFF1A1340).withOpacity(0.06),
                  blurRadius: 24,
                  offset: const Offset(0, 8),
                ),
              ],
            ),
            child: Stack(
              children: [
                Positioned(
                  top: -50,
                  right: -50,
                  child: ImageFiltered(
                    imageFilter: ImageFilter.blur(sigmaX: 22, sigmaY: 22),
                    child: Container(
                      width: 140,
                      height: 140,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        gradient: RadialGradient(
                          colors: [
                            item.color.withOpacity(isDark ? 0.42 : 0.35),
                            item.color.withOpacity(0),
                          ],
                          stops: const [0.0, 0.7],
                        ),
                      ),
                    ),
                  ),
                ),
                Material(
                  color: Colors.transparent,
                  child: InkWell(
                    onTap: item.onTap,
                    borderRadius: BorderRadius.circular(20),
                    splashColor: item.color.withOpacity(0.12),
                    highlightColor: item.color.withOpacity(0.06),
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Container(
                            width: 56,
                            height: 56,
                            decoration: BoxDecoration(
                              gradient: LinearGradient(
                                begin: Alignment.topLeft,
                                end: Alignment.bottomRight,
                                colors: [
                                  item.color.withOpacity(isDark ? 0.40 : 0.18),
                                  item.color.withOpacity(isDark ? 0.20 : 0.08),
                                ],
                              ),
                              borderRadius: BorderRadius.circular(18),
                              border: Border.all(
                                color: item.color.withOpacity(isDark ? 0.40 : 0.25),
                              ),
                              boxShadow: [
                                BoxShadow(
                                  color: item.color.withOpacity(isDark ? 0.3 : 0.18),
                                  blurRadius: 14,
                                  offset: const Offset(0, 6),
                                ),
                              ],
                            ),
                            child: Icon(item.icon, size: 28, color: item.color),
                          ),
                          const SizedBox(height: 12),
                          Text(
                            item.title,
                            textAlign: TextAlign.center,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w700,
                              color: textColor,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            item.subtitle,
                            textAlign: TextAlign.center,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              fontSize: 11,
                              color: subColor,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
