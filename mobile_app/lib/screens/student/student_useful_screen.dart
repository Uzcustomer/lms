import 'package:flutter/material.dart';
import '../../config/theme.dart';
import '../../l10n/app_localizations.dart';
import 'student_services_screen.dart';
import 'student_exam_schedule_screen.dart';
import 'attendance_stats_screen.dart';
import 'gpa_calculator_screen.dart';
import 'student_rating_screen.dart';
import 'chat_contacts_screen.dart';
import 'library_webview_screen.dart';

class StudentUsefulScreen extends StatelessWidget {
  const StudentUsefulScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bg = isDark ? AppTheme.darkBackground : const Color(0xFFF0F4F8);
    final txt = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;
    final sub = isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;

    final services = [
      _ServiceCard(
        icon: Icons.fact_check_outlined,
        title: 'Elektron xizmatlar',
        subtitle: 'Sababli ariza va boshqa xizmatlar',
        gradient: const [Color(0xFF4A6CF7), Color(0xFF6C63FF)],
        screen: const StudentServicesScreen(),
      ),
      _ServiceCard(
        icon: Icons.event_note_rounded,
        title: 'Imtihon sanalari',
        subtitle: 'OSKI va Test kunlari',
        gradient: const [Color(0xFFE53935), Color(0xFFFF5252)],
        screen: const ExamScheduleScreen(),
      ),
      _ServiceCard(
        icon: Icons.bar_chart_rounded,
        title: 'Davomat statistikasi',
        subtitle: 'Davomat va baholar jadvali',
        gradient: const [Color(0xFF43A047), Color(0xFF66BB6A)],
        screen: const AttendanceStatsScreen(),
      ),
      _ServiceCard(
        icon: Icons.calculate_outlined,
        title: 'GPA Kalkulyator',
        subtitle: 'GPA hisoblash va prognoz',
        gradient: const [Color(0xFF00897B), Color(0xFF26A69A)],
        screen: const GpaCalculatorScreen(),
      ),
      _ServiceCard(
        icon: Icons.leaderboard_rounded,
        title: 'Talabalar reytingi',
        subtitle: 'Guruh va yo\'nalish reytingi',
        gradient: const [Color(0xFF7C4DFF), Color(0xFF9575CD)],
        screen: const StudentRatingScreen(),
      ),
      _ServiceCard(
        icon: Icons.chat_rounded,
        title: 'Guruh chati',
        subtitle: 'Guruh a\'zolari bilan yozishing',
        gradient: const [Color(0xFF0097A7), Color(0xFF00BCD4)],
        screen: const ChatContactsScreen(),
      ),
      _ServiceCard(
        icon: Icons.library_books_outlined,
        title: 'Kutubxona',
        subtitle: 'Elektron darsliklar',
        gradient: const [Color(0xFFE65100), Color(0xFFFF6D00)],
        screen: const LibraryWebViewScreen(),
      ),
    ];

    return Scaffold(
      backgroundColor: bg,
      body: CustomScrollView(
        slivers: [
          SliverAppBar(
            expandedHeight: 120,
            floating: false,
            pinned: true,
            automaticallyImplyLeading: false,
            backgroundColor: AppTheme.primaryLight,
            flexibleSpace: FlexibleSpaceBar(
              titlePadding: const EdgeInsets.only(left: 20, bottom: 14),
              title: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    width: 28,
                    height: 28,
                    decoration: BoxDecoration(
                      color: Colors.white.withOpacity(0.2),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: const Icon(Icons.apps_rounded,
                        color: Colors.white, size: 16),
                  ),
                  const SizedBox(width: 10),
                  Text(
                    l.useful,
                    style: const TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.w700,
                      color: Colors.white,
                    ),
                  ),
                ],
              ),
              background: Container(
                decoration: const BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [
                      AppTheme.primaryLight,
                      Color(0xFF1A237E),
                    ],
                  ),
                ),
                child: Stack(
                  children: [
                    Positioned(
                      right: -20,
                      top: -20,
                      child: Container(
                        width: 120,
                        height: 120,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: Colors.white.withOpacity(0.06),
                        ),
                      ),
                    ),
                    Positioned(
                      right: 40,
                      bottom: -30,
                      child: Container(
                        width: 80,
                        height: 80,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: Colors.white.withOpacity(0.04),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(14, 16, 14, 100),
            sliver: SliverGrid(
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 2,
                mainAxisSpacing: 12,
                crossAxisSpacing: 12,
                childAspectRatio: 0.92,
              ),
              delegate: SliverChildBuilderDelegate(
                (context, index) => _buildCard(
                    context, services[index], isDark, txt, sub),
                childCount: services.length,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildCard(BuildContext context, _ServiceCard item, bool isDark,
      Color txt, Color sub) {
    final cardBg = isDark ? AppTheme.darkCard : Colors.white;

    return Material(
      color: cardBg,
      borderRadius: BorderRadius.circular(18),
      elevation: isDark ? 0 : 2,
      shadowColor: item.gradient[0].withOpacity(0.15),
      child: InkWell(
        onTap: () => Navigator.push(
          context,
          MaterialPageRoute(builder: (_) => item.screen),
        ),
        borderRadius: BorderRadius.circular(18),
        child: Container(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(18),
            border: Border.all(
              color: isDark
                  ? item.gradient[0].withOpacity(0.15)
                  : item.gradient[0].withOpacity(0.08),
            ),
          ),
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 50,
                height: 50,
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: item.gradient,
                  ),
                  borderRadius: BorderRadius.circular(14),
                  boxShadow: [
                    BoxShadow(
                      color: item.gradient[0].withOpacity(0.3),
                      blurRadius: 8,
                      offset: const Offset(0, 3),
                    ),
                  ],
                ),
                child: Icon(item.icon, color: Colors.white, size: 26),
              ),
              const Spacer(),
              Text(
                item.title,
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w700,
                  color: txt,
                  height: 1.2,
                ),
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
              const SizedBox(height: 4),
              Text(
                item.subtitle,
                style: TextStyle(
                  fontSize: 11,
                  color: sub,
                  height: 1.3,
                ),
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  const Spacer(),
                  Container(
                    width: 28,
                    height: 28,
                    decoration: BoxDecoration(
                      color: item.gradient[0].withOpacity(isDark ? 0.15 : 0.08),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Icon(
                      Icons.arrow_forward_rounded,
                      size: 16,
                      color: item.gradient[0],
                    ),
                  ),
                ],
              ),
            ],
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
  final List<Color> gradient;
  final Widget screen;

  const _ServiceCard({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.gradient,
    required this.screen,
  });
}
