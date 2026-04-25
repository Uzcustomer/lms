import 'dart:ui';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/theme.dart';
import '../../l10n/app_localizations.dart';
import '../../providers/student_provider.dart';
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
    final txt = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;
    final sub = isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;
    final statusBarH = MediaQuery.of(context).padding.top;

    final provider = context.watch<StudentProvider>();
    final fullName = provider.profile?['full_name']?.toString() ?? '';
    final firstName = fullName.split(' ').length > 1
        ? fullName.split(' ')[1]
        : fullName.split(' ').first;

    final services = [
      _ServiceCard(
        icon: Icons.calculate_outlined,
        title: 'GPA Kalkulyator',
        subtitle: 'GPA hisoblash va prognoz',
        color: const Color(0xFF00897B),
        screen: const GpaCalculatorScreen(),
      ),
      _ServiceCard(
        icon: Icons.bar_chart_rounded,
        title: 'Davomat statistikasi',
        subtitle: 'Davomat va baholar jadvali',
        color: const Color(0xFF43A047),
        screen: const AttendanceStatsScreen(),
      ),
      _ServiceCard(
        icon: Icons.leaderboard_rounded,
        title: 'Talabalar reytingi',
        subtitle: 'Guruh va yo\'nalish reytingi',
        color: const Color(0xFF7C4DFF),
        screen: const StudentRatingScreen(),
      ),
      _ServiceCard(
        icon: Icons.chat_rounded,
        title: 'Guruh chati',
        subtitle: 'Guruh a\'zolari bilan yozishing',
        color: const Color(0xFF0097A7),
        screen: const ChatContactsScreen(),
      ),
      _ServiceCard(
        icon: Icons.fact_check_outlined,
        title: 'Elektron xizmatlar',
        subtitle: 'Sababli ariza va boshqa xizmatlar',
        color: const Color(0xFF4A6CF7),
        screen: const StudentServicesScreen(),
      ),
      _ServiceCard(
        icon: Icons.library_books_outlined,
        title: 'Kutubxona',
        subtitle: 'Elektron darsliklar',
        color: const Color(0xFFE65100),
        screen: const LibraryWebViewScreen(),
      ),
    ];

    return Scaffold(
      body: Container(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            stops: const [0.0, 0.25, 0.5, 0.75, 1.0],
            colors: isDark
                ? const [Color(0xFF0D0221), Color(0xFF150638), Color(0xFF1B0A3C), Color(0xFF150638), Color(0xFF0D0221)]
                : const [Color(0xFFF0F2FF), Color(0xFFE8ECFF), Color(0xFFE0E8F8), Color(0xFFE8ECFF), Color(0xFFF0F2FF)],
          ),
        ),
        child: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SizedBox(height: statusBarH + 12),

              // Header
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 20),
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Salom, $firstName',
                            style: TextStyle(fontSize: 14, color: sub, fontWeight: FontWeight.w500),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            l.useful,
                            style: TextStyle(fontSize: 26, fontWeight: FontWeight.w800, color: txt),
                          ),
                        ],
                      ),
                    ),
                    Container(
                      width: 42,
                      height: 42,
                      decoration: BoxDecoration(
                        color: isDark ? Colors.white.withOpacity(0.1) : Colors.white.withOpacity(0.7),
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: Icon(Icons.search_rounded, color: sub, size: 22),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 20),

              // Featured banner — Imtihon sanalari
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: GestureDetector(
                  onTap: () => Navigator.push(
                    context,
                    MaterialPageRoute(builder: (_) => const ExamScheduleScreen()),
                  ),
                  child: Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                        colors: [Color(0xFFE53935), Color(0xFFFF7043)],
                      ),
                      borderRadius: BorderRadius.circular(20),
                      boxShadow: [
                        BoxShadow(
                          color: const Color(0xFFE53935).withOpacity(0.3),
                          blurRadius: 16,
                          offset: const Offset(0, 6),
                        ),
                      ],
                    ),
                    child: Stack(
                      children: [
                        Positioned(
                          right: 0,
                          top: 0,
                          bottom: 0,
                          child: Icon(
                            Icons.calendar_month_rounded,
                            size: 64,
                            color: Colors.white.withOpacity(0.2),
                          ),
                        ),
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                              decoration: BoxDecoration(
                                color: Colors.white.withOpacity(0.2),
                                borderRadius: BorderRadius.circular(6),
                              ),
                              child: const Text(
                                'ENG MUHIM',
                                style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: Colors.white, letterSpacing: 0.5),
                              ),
                            ),
                            const SizedBox(height: 10),
                            const Text(
                              'Imtihon sanalari',
                              style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: Colors.white),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              'OSKI va Test kunlari',
                              style: TextStyle(fontSize: 13, color: Colors.white.withOpacity(0.85)),
                            ),
                            const SizedBox(height: 14),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                              decoration: BoxDecoration(
                                color: Colors.white,
                                borderRadius: BorderRadius.circular(10),
                              ),
                              child: const Text(
                                'Kirish',
                                style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: Color(0xFFE53935)),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
              ),

              const SizedBox(height: 20),

              // Services grid
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: GridView.builder(
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 2,
                    mainAxisSpacing: 12,
                    crossAxisSpacing: 12,
                    childAspectRatio: 0.95,
                  ),
                  itemCount: services.length,
                  itemBuilder: (_, i) => _buildCard(context, services[i], isDark, txt, sub),
                ),
              ),

              const SizedBox(height: 100),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildCard(BuildContext context, _ServiceCard item, bool isDark, Color txt, Color sub) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(18),
      child: BackdropFilter(
        filter: ImageFilter.blur(sigmaX: 16, sigmaY: 16),
        child: Container(
          decoration: BoxDecoration(
            color: isDark ? Colors.white.withOpacity(0.08) : Colors.white.withOpacity(0.65),
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: isDark ? Colors.white.withOpacity(0.12) : Colors.white.withOpacity(0.8)),
          ),
          child: Material(
            color: Colors.transparent,
            child: InkWell(
              onTap: () => Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => item.screen),
              ),
              borderRadius: BorderRadius.circular(18),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: 46,
                      height: 46,
                      decoration: BoxDecoration(
                        color: item.color.withOpacity(isDark ? 0.2 : 0.1),
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: Icon(item.icon, color: item.color, size: 24),
                    ),
                    const Spacer(),
                    Text(
                      item.title,
                      style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: txt),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 3),
                    Text(
                      item.subtitle,
                      style: TextStyle(fontSize: 11, color: sub),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                ),
              ),
            ),
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
