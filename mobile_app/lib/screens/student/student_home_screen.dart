import 'package:flutter/material.dart';
import '../../config/theme.dart';
import '../../l10n/app_localizations.dart';
import 'student_dashboard_screen.dart';
import 'student_grades_screen.dart';
import 'student_schedule_screen.dart';
import 'student_services_screen.dart';
import 'student_profile_screen.dart';

class StudentHomeScreen extends StatefulWidget {
  const StudentHomeScreen({super.key});

  @override
  State<StudentHomeScreen> createState() => _StudentHomeScreenState();
}

class _StudentHomeScreenState extends State<StudentHomeScreen>
    with TickerProviderStateMixin {
  int _currentIndex = 0;
  late List<AnimationController> _animControllers;
  late List<Animation<double>> _scaleAnimations;

  final _screens = const [
    StudentDashboardScreen(),
    StudentGradesScreen(),
    StudentScheduleScreen(),
    StudentServicesScreen(),
    StudentProfileScreen(),
  ];

  @override
  void initState() {
    super.initState();
    _animControllers = List.generate(
      5,
      (index) => AnimationController(
        vsync: this,
        duration: const Duration(milliseconds: 300),
      ),
    );
    _scaleAnimations = _animControllers.map((controller) {
      return Tween<double>(begin: 1.0, end: 1.15).animate(
        CurvedAnimation(parent: controller, curve: Curves.easeOutBack),
      );
    }).toList();
    _animControllers[0].forward();
  }

  @override
  void dispose() {
    for (final c in _animControllers) {
      c.dispose();
    }
    super.dispose();
  }

  void _onTabTapped(int index) {
    if (index == _currentIndex) return;
    _animControllers[_currentIndex].reverse();
    _animControllers[index].forward();
    setState(() {
      _currentIndex = index;
    });
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    final navItems = [
      _NavItem(Icons.dashboard_outlined, Icons.dashboard, l.home),
      _NavItem(Icons.grade_outlined, Icons.grade, l.grades),
      _NavItem(Icons.calendar_today_outlined, Icons.calendar_today, l.schedule),
      _NavItem(Icons.miscellaneous_services_outlined, Icons.miscellaneous_services, l.services),
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
                  final isActive = _currentIndex == index;
                  return Expanded(
                    child: GestureDetector(
                      onTap: () => _onTabTapped(index),
                      behavior: HitTestBehavior.opaque,
                      child: _AnimatedNavItem(
                        animation: _scaleAnimations[index],
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

class _AnimatedNavItem extends AnimatedWidget {
  final bool isActive;
  final _NavItem item;

  const _AnimatedNavItem({
    required Animation<double> animation,
    required this.isActive,
    required this.item,
  }) : super(listenable: animation);

  @override
  Widget build(BuildContext context) {
    final anim = listenable as Animation<double>;
    final elevate = isActive ? -4.0 * (anim.value - 1.0) / 0.15 : 0.0;

    return Transform.translate(
      offset: Offset(0, elevate),
      child: Transform.scale(
        scale: anim.value,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            AnimatedContainer(
              duration: const Duration(milliseconds: 250),
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: isActive
                    ? Colors.white
                    : Colors.transparent,
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(
                isActive ? item.activeIcon : item.icon,
                color: isActive ? Colors.black : Colors.white,
                size: 22,
              ),
            ),
            const SizedBox(height: 2),
            Text(
              item.label,
              style: TextStyle(
                fontSize: 10,
                fontWeight: isActive ? FontWeight.w600 : FontWeight.normal,
                color: isActive ? Colors.white : Colors.white70,
              ),
              overflow: TextOverflow.ellipsis,
            ),
          ],
        ),
      ),
    );
  }
}
