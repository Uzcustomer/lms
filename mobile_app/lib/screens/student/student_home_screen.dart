import 'package:flutter/material.dart';
import '../../config/theme.dart';
import '../../l10n/app_localizations.dart';
import 'student_dashboard_screen.dart';
import 'student_grades_screen.dart';
import 'student_schedule_screen.dart';
import 'student_profile_screen.dart';
import 'student_useful_screen.dart';

class StudentHomeScreen extends StatefulWidget {
  const StudentHomeScreen({super.key});

  static void switchToHome(BuildContext context) {
    final state = context.findAncestorStateOfType<_StudentHomeScreenState>();
    state?._onTabTapped(2);
  }

  static void switchToGrades(BuildContext context) {
    final state = context.findAncestorStateOfType<_StudentHomeScreenState>();
    state?._onTabTapped(0);
  }

  /// Notifier the dashboard sets when the user taps a subject card so the
  /// Grades screen scrolls to that subject's card after the tab switch.
  static final ValueNotifier<int?> pendingSubjectScroll =
      ValueNotifier<int?>(null);

  /// Switch to the Grades tab and scroll to the given subject's card.
  static void openSubject(BuildContext context, int subjectId) {
    pendingSubjectScroll.value = subjectId;
    switchToGrades(context);
  }

  @override
  State<StudentHomeScreen> createState() => _StudentHomeScreenState();
}

class _StudentHomeScreenState extends State<StudentHomeScreen> {
  int _currentIndex = 2;
  int _previousIndex = 2;

  final _screens = const [
    StudentGradesScreen(),
    StudentScheduleScreen(),
    StudentDashboardScreen(),
    StudentUsefulScreen(),
    StudentProfileScreen(),
  ];

  void _onTabTapped(int index) {
    if (index == _currentIndex) return;
    setState(() {
      _previousIndex = _currentIndex;
      _currentIndex = index;
    });
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

    final goingRight = _currentIndex > _previousIndex;
    final slideBegin = Offset(goingRight ? 0.08 : -0.08, 0);
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final navBg = isDark ? AppTheme.darkCard : Colors.white;
    final navBorder = isDark ? Colors.white12 : const Color(0xFFE2E8F0);

    return Scaffold(
      extendBody: true,
      body: AnimatedSwitcher(
        duration: const Duration(milliseconds: 380),
        switchInCurve: Curves.easeOutCubic,
        switchOutCurve: Curves.easeInCubic,
        transitionBuilder: (child, animation) {
          return FadeTransition(
            opacity: animation,
            child: SlideTransition(
              position: Tween<Offset>(
                begin: slideBegin,
                end: Offset.zero,
              ).animate(animation),
              child: child,
            ),
          );
        },
        child: KeyedSubtree(
          key: ValueKey<int>(_currentIndex),
          child: _screens[_currentIndex],
        ),
      ),
      bottomNavigationBar: Container(
        decoration: BoxDecoration(
          color: navBg,
          border: Border(top: BorderSide(color: navBorder, width: 1)),
          boxShadow: [
            BoxShadow(
              color: const Color(0xFF0F172A).withOpacity(0.06),
              blurRadius: 8,
              offset: const Offset(0, -2),
            ),
          ],
        ),
        child: SafeArea(
          top: false,
          child: Row(
            children: List.generate(navItems.length, (index) {
              final item = navItems[index];
              final isActive = _currentIndex == index;
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
    );
  }
}

class _NavItem {
  final IconData icon;
  final IconData activeIcon;
  final String label;

  const _NavItem(this.icon, this.activeIcon, this.label);
}

class _NavItemWidget extends StatefulWidget {
  final bool isActive;
  final _NavItem item;

  const _NavItemWidget({
    required this.isActive,
    required this.item,
  });

  @override
  State<_NavItemWidget> createState() => _NavItemWidgetState();
}

class _NavItemWidgetState extends State<_NavItemWidget>
    with SingleTickerProviderStateMixin {
  late final AnimationController _bounceController;
  late final Animation<double> _scale;

  @override
  void initState() {
    super.initState();
    _bounceController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 420),
    );
    _scale = TweenSequence<double>([
      TweenSequenceItem(tween: Tween(begin: 1.0, end: 0.82), weight: 25),
      TweenSequenceItem(tween: Tween(begin: 0.82, end: 1.18), weight: 35),
      TweenSequenceItem(tween: Tween(begin: 1.18, end: 1.0), weight: 40),
    ]).animate(CurvedAnimation(
      parent: _bounceController,
      curve: Curves.easeOutCubic,
    ));
    if (widget.isActive) {
      _bounceController.value = 1.0;
    }
  }

  @override
  void didUpdateWidget(covariant _NavItemWidget oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.isActive && !oldWidget.isActive) {
      _bounceController.forward(from: 0);
    }
  }

  @override
  void dispose() {
    _bounceController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    const activeColor = Color(0xFF0D9488);
    final inactiveColor = isDark ? Colors.white60 : const Color(0xFF94A3B8);
    final color = widget.isActive ? activeColor : inactiveColor;

    return Container(
      height: 66,
      decoration: widget.isActive
          ? const BoxDecoration(
              border: Border(
                top: BorderSide(color: activeColor, width: 2.5),
                left: BorderSide(color: activeColor, width: 2.5),
                right: BorderSide(color: activeColor, width: 2.5),
              ),
            )
          : null,
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        mainAxisSize: MainAxisSize.min,
        children: [
          AnimatedBuilder(
            animation: _scale,
            builder: (context, child) {
              return Transform.scale(
                scale: widget.isActive ? _scale.value : 1.0,
                child: child,
              );
            },
            child: Icon(
              widget.isActive ? widget.item.activeIcon : widget.item.icon,
              color: color,
              size: 25,
            ),
          ),
          const SizedBox(height: 5),
          Text(
            widget.item.label,
            style: TextStyle(
              fontSize: 10.5,
              fontWeight: widget.isActive ? FontWeight.w700 : FontWeight.w500,
              color: color,
            ),
            overflow: TextOverflow.ellipsis,
          ),
        ],
      ),
    );
  }
}

