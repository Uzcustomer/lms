import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../config/theme.dart';
import '../../l10n/app_localizations.dart';
import '../../services/api_service.dart';
import '../../services/student_service.dart';
import 'student_dashboard_screen.dart';
import 'student_grades_screen.dart';
import 'student_schedule_screen.dart';
import 'student_profile_screen.dart';
import 'student_services_screen.dart';

class StudentHomeScreen extends StatefulWidget {
  const StudentHomeScreen({super.key});

  @override
  State<StudentHomeScreen> createState() => _StudentHomeScreenState();
}

class _StudentHomeScreenState extends State<StudentHomeScreen> {
  int _currentIndex = 2;

  final _screens = const [
    StudentGradesScreen(),
    StudentScheduleScreen(),
    StudentDashboardScreen(),
    SizedBox(), // placeholder — Foydali opens modal
    StudentProfileScreen(),
  ];

  void _onTabTapped(int index) {
    if (index == 3) {
      _showUsefulModal();
      return;
    }
    if (index == _currentIndex) return;
    setState(() {
      _currentIndex = index;
    });
  }

  void _showUsefulModal() {
    final l = AppLocalizations.of(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      transitionAnimationController: AnimationController(
        vsync: Navigator.of(context),
        duration: const Duration(milliseconds: 400),
      ),
      builder: (ctx) {
        return _UsefulModal(isDark: isDark, l: l);
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    final navItems = [
      _NavItem(Icons.grade_outlined, Icons.grade, l.grades),
      _NavItem(Icons.calendar_today_outlined, Icons.calendar_today, l.schedule),
      _NavItem(Icons.dashboard_outlined, Icons.dashboard, l.home),
      _NavItem(Icons.apps_outlined, Icons.apps_rounded, l.useful),
      _NavItem(Icons.person_outline, Icons.person, l.profile),
    ];

    return Scaffold(
      extendBody: true,
      body: _screens[_currentIndex],
      bottomNavigationBar: SafeArea(
        child: Container(
          margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          decoration: BoxDecoration(
            color: AppTheme.primaryLight,
            borderRadius: BorderRadius.circular(28),
            boxShadow: [
              BoxShadow(
                color: AppTheme.primaryColor.withAlpha(40),
                blurRadius: 16,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(28),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 10),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceAround,
                children: List.generate(navItems.length, (index) {
                  final item = navItems[index];
                  final isActive = index == 3 ? false : _currentIndex == index;
                  return Expanded(
                    child: GestureDetector(
                      onTap: () => _onTabTapped(index),
                      behavior: HitTestBehavior.opaque,
                      child: _NavItemWidget(
                        isActive: isActive,
                        item: item,
                      ),
                    ),
                  );
                }),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _NavItem {
  final IconData icon;
  final IconData activeIcon;
  final String label;

  const _NavItem(this.icon, this.activeIcon, this.label);
}

class _NavItemWidget extends StatelessWidget {
  final bool isActive;
  final _NavItem item;

  const _NavItemWidget({
    required this.isActive,
    required this.item,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        AnimatedContainer(
          duration: const Duration(milliseconds: 250),
          padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(
            color: isActive
                ? Colors.white.withAlpha(20)
                : Colors.transparent,
            borderRadius: BorderRadius.circular(12),
          ),
          child: Icon(
            isActive ? item.activeIcon : item.icon,
            color: isActive ? const Color(0xFFFF9800) : Colors.white70,
            size: 26,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          item.label,
          style: TextStyle(
            fontSize: 10,
            fontWeight: isActive ? FontWeight.w700 : FontWeight.normal,
            color: isActive ? Colors.white : Colors.white70,
          ),
          overflow: TextOverflow.ellipsis,
        ),
      ],
    );
  }
}

class _UsefulModal extends StatefulWidget {
  final bool isDark;
  final AppLocalizations l;

  const _UsefulModal({required this.isDark, required this.l});

  @override
  State<_UsefulModal> createState() => _UsefulModalState();
}

class _UsefulModalState extends State<_UsefulModal>
    with SingleTickerProviderStateMixin {
  late AnimationController _animController;
  late Animation<Offset> _slideAnim;
  late Animation<double> _fadeAnim;
  List<dynamic> _exams = [];
  bool _examsLoading = true;

  @override
  void initState() {
    super.initState();
    _animController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 500),
    );
    _slideAnim = Tween<Offset>(
      begin: const Offset(0, 0.15),
      end: Offset.zero,
    ).animate(CurvedAnimation(
      parent: _animController,
      curve: Curves.easeOutCubic,
    ));
    _fadeAnim = Tween<double>(begin: 0, end: 1).animate(CurvedAnimation(
      parent: _animController,
      curve: Curves.easeOut,
    ));
    _animController.forward();
    _loadExams();
  }

  Future<void> _loadExams() async {
    try {
      final api = ApiService();
      final service = StudentService(api);
      final response = await service.getExamSchedule();
      if (mounted && response['success'] == true) {
        final all = response['data'] as List<dynamic>? ?? [];
        final today = DateFormat('yyyy-MM-dd').format(DateTime.now());
        setState(() {
          _exams = all.where((e) {
            final d = e['date']?.toString() ?? '';
            return d.compareTo(today) >= 0;
          }).toList();
          _examsLoading = false;
        });
      } else {
        if (mounted) setState(() => _examsLoading = false);
      }
    } catch (_) {
      if (mounted) setState(() => _examsLoading = false);
    }
  }

  @override
  void dispose() {
    _animController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final bgColor = widget.isDark ? AppTheme.darkCard : Colors.white;
    final textColor =
        widget.isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;
    final subColor =
        widget.isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;
    final divColor =
        widget.isDark ? AppTheme.darkDivider : const Color(0xFFEEEEEE);

    final services = [
      _ModalServiceItem(
        icon: Icons.fact_check_outlined,
        title: 'Elektron xizmatlar',
        subtitle: 'Sababli ariza va boshqa xizmatlar',
        color: const Color(0xFF4A6CF7),
        onTap: () {
          Navigator.pop(context);
          Navigator.push(
            context,
            MaterialPageRoute(
                builder: (_) => const StudentServicesScreen()),
          );
        },
      ),
      _ModalServiceItem(
        icon: Icons.calculate_outlined,
        title: 'GPA Kalkulyator',
        subtitle: 'GPA ni hisoblash va prognoz qilish',
        color: const Color(0xFF26A69A),
        onTap: () {},
        comingSoon: true,
      ),
      _ModalServiceItem(
        icon: Icons.smart_toy_outlined,
        title: 'AI Yordamchi',
        subtitle: 'Sun\'iy intellekt bilan suhbat',
        color: const Color(0xFF7C4DFF),
        onTap: () {},
        comingSoon: true,
      ),
      _ModalServiceItem(
        icon: Icons.library_books_outlined,
        title: 'Kutubxona',
        subtitle: 'Elektron darsliklar va resurslar',
        color: const Color(0xFFFF6D00),
        onTap: () {},
        comingSoon: true,
      ),
    ];

    return SlideTransition(
      position: _slideAnim,
      child: FadeTransition(
        opacity: _fadeAnim,
        child: DraggableScrollableSheet(
          initialChildSize: 0.65,
          minChildSize: 0.3,
          maxChildSize: 0.85,
          builder: (context, scrollController) {
            return Container(
              decoration: BoxDecoration(
                color: bgColor,
                borderRadius: const BorderRadius.vertical(
                  top: Radius.circular(24),
                ),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.1),
                    blurRadius: 20,
                    offset: const Offset(0, -4),
                  ),
                ],
              ),
              child: Column(
                children: [
                  // Handle bar
                  Container(
                    margin: const EdgeInsets.only(top: 12),
                    width: 40,
                    height: 4,
                    decoration: BoxDecoration(
                      color: divColor,
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                  // Title
                  Padding(
                    padding: const EdgeInsets.fromLTRB(20, 16, 20, 8),
                    child: Row(
                      children: [
                        Container(
                          width: 36,
                          height: 36,
                          decoration: BoxDecoration(
                            gradient: const LinearGradient(
                              colors: [
                                Color(0xFF4A6CF7),
                                Color(0xFF6C63FF),
                              ],
                            ),
                            borderRadius: BorderRadius.circular(10),
                          ),
                          child: const Icon(Icons.apps_rounded,
                              color: Colors.white, size: 20),
                        ),
                        const SizedBox(width: 12),
                        Text(
                          widget.l.useful,
                          style: TextStyle(
                            fontSize: 20,
                            fontWeight: FontWeight.bold,
                            color: textColor,
                          ),
                        ),
                        const Spacer(),
                        GestureDetector(
                          onTap: () => Navigator.pop(context),
                          child: Container(
                            width: 32,
                            height: 32,
                            decoration: BoxDecoration(
                              color: divColor,
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Icon(Icons.close_rounded,
                                size: 18, color: subColor),
                          ),
                        ),
                      ],
                    ),
                  ),
                  Divider(color: divColor, height: 1),

                  // Content
                  Expanded(
                    child: ListView(
                      controller: scrollController,
                      padding: const EdgeInsets.symmetric(
                          horizontal: 16, vertical: 12),
                      children: [
                        // Services
                        ...services.map((item) => Padding(
                          padding: const EdgeInsets.only(bottom: 10),
                          child: _buildServiceTile(
                              item, textColor, subColor, widget.isDark),
                        )),

                        // Exam dates section
                        const SizedBox(height: 10),
                        Row(
                          children: [
                            Icon(Icons.event_note_rounded,
                                size: 20, color: const Color(0xFFE53935)),
                            const SizedBox(width: 8),
                            Text(
                              'Imtihon sanalari',
                              style: TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.bold,
                                color: textColor,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 12),

                        if (_examsLoading)
                          const Center(
                            child: Padding(
                              padding: EdgeInsets.all(20),
                              child: SizedBox(
                                width: 24, height: 24,
                                child: CircularProgressIndicator(strokeWidth: 2),
                              ),
                            ),
                          )
                        else if (_exams.isEmpty)
                          Center(
                            child: Padding(
                              padding: const EdgeInsets.all(20),
                              child: Text(
                                'Hozircha imtihon sanalari yo\'q',
                                style: TextStyle(fontSize: 13, color: subColor),
                              ),
                            ),
                          )
                        else
                          ..._exams.map((exam) => _buildExamCard(
                              exam, textColor, subColor, widget.isDark)),

                        const SizedBox(height: 16),
                      ],
                    ),
                  ),
                ],
              ),
            );
          },
        ),
      ),
    );
  }

  Widget _buildServiceTile(_ModalServiceItem item, Color textColor,
      Color subColor, bool isDark) {
    final tileBg = isDark
        ? AppTheme.darkBackground
        : item.color.withOpacity(0.04);

    return Material(
      color: tileBg,
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: item.comingSoon ? null : item.onTap,
        borderRadius: BorderRadius.circular(14),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
          child: Row(
            children: [
              Container(
                width: 46,
                height: 46,
                decoration: BoxDecoration(
                  color: item.color.withOpacity(isDark ? 0.2 : 0.12),
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Icon(item.icon, color: item.color, size: 24),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Text(
                          item.title,
                          style: TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w600,
                            color: textColor,
                          ),
                        ),
                        if (item.comingSoon) ...[
                          const SizedBox(width: 8),
                          Container(
                            padding: const EdgeInsets.symmetric(
                                horizontal: 6, vertical: 2),
                            decoration: BoxDecoration(
                              color: item.color.withOpacity(0.12),
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: Text(
                              'Tez kunda',
                              style: TextStyle(
                                fontSize: 9,
                                fontWeight: FontWeight.w600,
                                color: item.color,
                              ),
                            ),
                          ),
                        ],
                      ],
                    ),
                    const SizedBox(height: 3),
                    Text(
                      item.subtitle,
                      style: TextStyle(
                        fontSize: 12,
                        color: subColor,
                      ),
                    ),
                  ],
                ),
              ),
              if (!item.comingSoon)
                Icon(Icons.chevron_right_rounded, color: subColor, size: 22),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildExamCard(dynamic exam, Color textColor, Color subColor, bool isDark) {
    final subject = exam['subject_name']?.toString() ?? '';
    final examType = exam['exam_type']?.toString() ?? '';
    final dateStr = exam['date']?.toString() ?? '';
    final timeStr = exam['time']?.toString() ?? '';

    String formattedDate = dateStr;
    String daysLeft = '';
    Color urgencyColor = const Color(0xFF4A6CF7);

    try {
      final date = DateTime.parse(dateStr);
      final now = DateTime(DateTime.now().year, DateTime.now().month, DateTime.now().day);
      final diff = date.difference(now).inDays;

      formattedDate = DateFormat('d-MMMM, EEEE').format(date);

      if (diff == 0) {
        daysLeft = 'Bugun';
        urgencyColor = const Color(0xFFE53935);
      } else if (diff == 1) {
        daysLeft = 'Ertaga';
        urgencyColor = const Color(0xFFE53935);
      } else if (diff <= 3) {
        daysLeft = '$diff kun qoldi';
        urgencyColor = const Color(0xFFFF6D00);
      } else {
        daysLeft = '$diff kun qoldi';
        urgencyColor = const Color(0xFF26A69A);
      }
    } catch (_) {}

    final cardBg = isDark ? AppTheme.darkBackground : Colors.white;

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: cardBg,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: urgencyColor.withOpacity(0.2),
          ),
          boxShadow: [
            BoxShadow(
              color: urgencyColor.withOpacity(0.06),
              blurRadius: 8,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Row(
          children: [
            // Date badge
            Container(
              width: 50,
              padding: const EdgeInsets.symmetric(vertical: 8),
              decoration: BoxDecoration(
                color: urgencyColor.withOpacity(isDark ? 0.2 : 0.1),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Column(
                children: [
                  Text(
                    _extractDay(dateStr),
                    style: TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.bold,
                      color: urgencyColor,
                    ),
                  ),
                  Text(
                    _extractMonth(dateStr),
                    style: TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.w600,
                      color: urgencyColor,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    subject,
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                      color: textColor,
                    ),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                        decoration: BoxDecoration(
                          color: urgencyColor.withOpacity(0.12),
                          borderRadius: BorderRadius.circular(4),
                        ),
                        child: Text(
                          examType,
                          style: TextStyle(
                            fontSize: 10,
                            fontWeight: FontWeight.w700,
                            color: urgencyColor,
                          ),
                        ),
                      ),
                      if (timeStr.isNotEmpty) ...[
                        const SizedBox(width: 8),
                        Icon(Icons.access_time_rounded, size: 12, color: subColor),
                        const SizedBox(width: 3),
                        Text(timeStr, style: TextStyle(fontSize: 11, color: subColor)),
                      ],
                    ],
                  ),
                ],
              ),
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(
                  daysLeft,
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    color: urgencyColor,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  String _extractDay(String dateStr) {
    try {
      return DateTime.parse(dateStr).day.toString();
    } catch (_) {
      return '';
    }
  }

  String _extractMonth(String dateStr) {
    try {
      const months = ['Yan', 'Fev', 'Mar', 'Apr', 'May', 'Iyn', 'Iyl', 'Avg', 'Sen', 'Okt', 'Noy', 'Dek'];
      return months[DateTime.parse(dateStr).month - 1];
    } catch (_) {
      return '';
    }
  }
}

class _ModalServiceItem {
  final IconData icon;
  final String title;
  final String subtitle;
  final Color color;
  final VoidCallback onTap;
  final bool comingSoon;

  const _ModalServiceItem({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.color,
    required this.onTap,
    this.comingSoon = false,
  });
}
