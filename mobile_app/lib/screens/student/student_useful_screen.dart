import 'dart:ui';
import 'package:flutter/material.dart';
import '../../config/theme.dart';
import '../../l10n/app_localizations.dart';
import '../../utils/page_transitions.dart';
import 'student_services_screen.dart';
import 'student_exam_schedule_screen.dart';
import 'attendance_stats_screen.dart';
import 'gpa_calculator_screen.dart';
import 'student_rating_screen.dart';
import 'chat_contacts_screen.dart';
import 'library_webview_screen.dart';
import 'student_home_screen.dart';

class StudentUsefulScreen extends StatelessWidget {
  const StudentUsefulScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final ink = isDark ? const Color(0xFFF4EEFF) : const Color(0xFF1A1340);
    final sub = ink.withOpacity(0.5);
    final statusBarH = MediaQuery.of(context).padding.top;

    final services = [
      _ServiceCard(
        icon: Icons.calculate_outlined,
        title: 'GPA Kalkulyator',
        subtitle: 'GPA hisoblash va prognoz',
        color: const Color(0xFF14B8A6),
        screen: const GpaCalculatorScreen(),
      ),
      _ServiceCard(
        icon: Icons.bar_chart_rounded,
        title: 'Davomat statistikasi',
        subtitle: 'Davomat va baholar jadvali',
        color: const Color(0xFF10B981),
        screen: const AttendanceStatsScreen(),
      ),
      _ServiceCard(
        icon: Icons.emoji_events_outlined,
        title: 'Talabalar reytingi',
        subtitle: 'Guruh va yo\'nalish reytingi',
        color: const Color(0xFF8B5CF6),
        screen: const StudentRatingScreen(),
      ),
      _ServiceCard(
        icon: Icons.chat_bubble_outline_rounded,
        title: 'Guruh chati',
        subtitle: 'Guruh a\'zolari bilan yozishing',
        color: const Color(0xFF0EA5E9),
        screen: const ChatContactsScreen(),
      ),
      _ServiceCard(
        icon: Icons.grid_view_rounded,
        title: 'Imtihon sanalari',
        subtitle: 'OSKI va Test kunlari',
        color: const Color(0xFF6366F1),
        screen: const ExamScheduleScreen(),
      ),
      _ServiceCard(
        icon: Icons.menu_book_rounded,
        title: 'Kutubxona',
        subtitle: 'Elektron darsliklar',
        color: const Color(0xFFF97316),
        screen: const LibraryWebViewScreen(),
      ),
    ];

    return Scaffold(
      backgroundColor: isDark ? const Color(0xFF0B1020) : const Color(0xFFFEF7F0),
      body: Stack(
        children: [
          // Aurora background
          Positioned.fill(
            child: DecoratedBox(
              decoration: BoxDecoration(
                gradient: RadialGradient(
                  center: const Alignment(-1.0, -1.0),
                  radius: 1.4,
                  colors: isDark
                      ? const [Color(0xFF6366F1), Color(0xFFA855F7), Color(0xFFEC4899), Color(0xFF0B1020)]
                      : const [Color(0xFFC7D2FE), Color(0xFFFBCFE8), Color(0xFFFED7AA), Color(0xFFFEF7F0)],
                  stops: const [0.0, 0.35, 0.65, 1.0],
                ),
              ),
            ),
          ),
          // Soft blobs
          Positioned(
            top: 180,
            right: -80,
            child: _Blob(color: isDark ? const Color(0xFFF472B6) : const Color(0xFFF9A8D4)),
          ),
          Positioned(
            top: 480,
            left: -80,
            child: _Blob(color: isDark ? const Color(0xFF60A5FA) : const Color(0xFFA5B4FC)),
          ),

          // Content
          SingleChildScrollView(
            padding: const EdgeInsets.only(bottom: 100),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Top bar
                Container(
                  padding: EdgeInsets.only(top: statusBarH, left: 16, right: 4),
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
                      GestureDetector(
                        onTap: () => StudentHomeScreen.switchToHome(context),
                        child: const Icon(Icons.account_balance, color: Colors.white, size: 24),
                      ),
                      const Spacer(),
                      Text(l.useful, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700, color: Colors.white)),
                      const Spacer(),
                      IconButton(
                        icon: const Icon(Icons.notifications_outlined, color: Colors.white, size: 22),
                        onPressed: () {},
                      ),
                      IconButton(
                        icon: const Icon(Icons.settings_outlined, color: Colors.white, size: 22),
                        onPressed: () {},
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: 14),

                // Services hero banner
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  child: GestureDetector(
                    onTap: () => Navigator.push(
                      context,
                      SlideFadePageRoute(builder: (_) => const StudentServicesScreen()),
                    ),
                    child: _ServicesHero(),
                  ),
                ),

                const SizedBox(height: 14),

                // Services grid
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  child: GridView.builder(
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    itemCount: services.length,
                    gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                      crossAxisCount: 2,
                      crossAxisSpacing: 12,
                      mainAxisSpacing: 12,
                      mainAxisExtent: 140,
                    ),
                    itemBuilder: (_, i) => _GlassTile(
                      item: services[i],
                      isDark: isDark,
                      ink: ink,
                      muted: sub,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

// ---------- Hero card ----------
class _ServicesHero extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(22),
      child: Container(
        width: double.infinity,
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [Color(0xFF7C4DFF), Color(0xFFAB47BC), Color(0xFFFF7043)],
          ),
          border: Border.all(color: Colors.white.withOpacity(0.3)),
          borderRadius: BorderRadius.circular(22),
          boxShadow: [
            BoxShadow(
              color: const Color(0xFF7C4DFF).withOpacity(0.3),
              blurRadius: 16,
              offset: const Offset(0, 6),
            ),
          ],
        ),
        padding: const EdgeInsets.all(18),
        child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
                  decoration: BoxDecoration(
                    color: Colors.black.withOpacity(0.22),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: const Text(
                    'XIZMATLAR',
                    style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700, letterSpacing: 1.2, color: Colors.white),
                  ),
                ),
                const SizedBox(height: 10),
                const Text(
                  'Elektron xizmatlar',
                  style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800, letterSpacing: -0.5, color: Colors.white),
                ),
                const SizedBox(height: 4),
                Text(
                  'Sababli ariza · Ma\'lumotnoma · Xizmatlar',
                  style: TextStyle(fontSize: 12.5, color: Colors.white.withOpacity(0.9)),
                ),
                const SizedBox(height: 14),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 9),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(11),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withOpacity(0.15),
                        blurRadius: 12,
                        offset: const Offset(0, 4),
                      ),
                    ],
                  ),
                  child: const Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text('Kirish', style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700, color: Color(0xFF7C4DFF))),
                      SizedBox(width: 6),
                      Icon(Icons.chevron_right_rounded, size: 16, color: Color(0xFF7C4DFF)),
                    ],
                  ),
                ),
              ],
            ),
          ),
      );
    }
  }

// ---------- Glass tile ----------
class _GlassTile extends StatelessWidget {
  final _ServiceCard item;
  final bool isDark;
  final Color ink;
  final Color muted;
  const _GlassTile({required this.item, required this.isDark, required this.ink, required this.muted});

  @override
  Widget build(BuildContext context) {
    final surface = isDark ? Colors.white.withOpacity(0.10) : Colors.white.withOpacity(0.7);
    final border = isDark ? Colors.white.withOpacity(0.12) : Colors.white.withOpacity(0.9);

    return ClipRRect(
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
          child: Material(
            color: Colors.transparent,
            child: InkWell(
              onTap: () => Navigator.push(
                context,
                SlideFadePageRoute(builder: (_) => item.screen),
              ),
              borderRadius: BorderRadius.circular(20),
              child: Stack(
                children: [
                  // hue glow — inside ClipRRect so it gets clipped
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
                            colors: [item.color.withOpacity(isDark ? 0.4 : 0.32), item.color.withOpacity(0)],
                            stops: const [0.0, 0.7],
                          ),
                        ),
                      ),
                    ),
                  ),
                  Padding(
                    padding: const EdgeInsets.all(14),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Container(
                          width: 42,
                          height: 42,
                          decoration: BoxDecoration(
                            color: item.color.withOpacity(isDark ? 0.20 : 0.12),
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: item.color.withOpacity(0.33)),
                            boxShadow: [
                              BoxShadow(
                                color: item.color.withOpacity(0.2),
                                blurRadius: 14,
                                offset: const Offset(0, 4),
                              ),
                            ],
                          ),
                          child: Icon(item.icon, size: 20, color: isDark ? Colors.white : item.color),
                        ),
                        const SizedBox(height: 10),
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              item.title,
                              style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700, height: 1.25, letterSpacing: -0.2, color: ink),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                            const SizedBox(height: 4),
                            Text(
                              item.subtitle,
                              style: TextStyle(fontSize: 11, height: 1.35, color: muted),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

// ---------- Decorative blob ----------
class _Blob extends StatelessWidget {
  final Color color;
  const _Blob({required this.color});

  @override
  Widget build(BuildContext context) {
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

class _ServiceCard {
  final IconData icon;
  final String title;
  final String subtitle;
  final Color color;
  final Widget screen;

  const _ServiceCard({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.color,
    required this.screen,
  });
}
