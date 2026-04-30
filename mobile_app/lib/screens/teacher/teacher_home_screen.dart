import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/theme.dart';
import '../../l10n/app_localizations.dart';
import '../../providers/auth_provider.dart';
import 'teacher_dashboard_screen.dart';
import 'teacher_students_screen.dart';
import 'teacher_groups_screen.dart';
import 'teacher_services_screen.dart';
import 'teacher_profile_screen.dart';

class TeacherHomeScreen extends StatefulWidget {
  const TeacherHomeScreen({super.key});

  @override
  State<TeacherHomeScreen> createState() => _TeacherHomeScreenState();
}

class _TeacherHomeScreenState extends State<TeacherHomeScreen> {
  int _currentIndex = 0;
  int _previousIndex = 0;

  List<_NavItem> _getNavItems(String? activeRole, AppLocalizations l) {
    final items = <_NavItem>[
      _NavItem(Icons.dashboard_outlined, Icons.dashboard, l.home, 'dashboard'),
    ];

    // Jurnal - teachers, registrars, heads, subject responsible
    const jurnalRoles = [
      'superadmin', 'admin', 'kichik_admin', 'registrator_ofisi',
      'dekan', 'oqituvchi', 'kafedra_mudiri', 'fan_masuli',
    ];
    if (activeRole != null && jurnalRoles.contains(activeRole) && activeRole != 'oquv_bolimi' && activeRole != 'oquv_bolimi_boshligi') {
      items.add(_NavItem(Icons.edit_note_outlined, Icons.edit_note, 'Jurnal', 'journal'));
    }

    // Talabalar - most roles except test_markazi, oquv_bolimi, oquv_bolimi_boshligi
    if (activeRole != 'test_markazi' && activeRole != 'oquv_bolimi' && activeRole != 'oquv_bolimi_boshligi') {
      items.add(_NavItem(Icons.people_outline, Icons.people, l.students, 'students'));
    }

    // Guruhlar - for all roles (teacher's groups)
    items.add(_NavItem(Icons.groups_outlined, Icons.groups, l.groups, 'groups'));

    // Profile always last
    items.add(_NavItem(Icons.person_outline, Icons.person, l.profile, 'profile'));

    return items;
  }

  Widget _getScreen(String key) {
    switch (key) {
      case 'dashboard':
        return const TeacherDashboardScreen();
      case 'journal':
        return const TeacherServicesScreen(); // placeholder for now
      case 'students':
        return const TeacherStudentsScreen();
      case 'groups':
        return const TeacherGroupsScreen();
      case 'profile':
        return const TeacherProfileScreen();
      default:
        return const TeacherDashboardScreen();
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    return Consumer<AuthProvider>(
      builder: (context, auth, _) {
        final navItems = _getNavItems(auth.activeRole, l);

        // Reset index if out of bounds after role switch
        if (_currentIndex >= navItems.length) {
          _currentIndex = 0;
        }

        final goingRight = _currentIndex > _previousIndex;
        final slideBegin = Offset(goingRight ? 0.08 : -0.08, 0);

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
              child: _getScreen(navItems[_currentIndex].key),
            ),
          ),
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
                          onTap: () {
                            if (index != _currentIndex) {
                              setState(() {
                                _previousIndex = _currentIndex;
                                _currentIndex = index;
                              });
                            }
                          },
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
      },
    );
  }
}

class _NavItem {
  final IconData icon;
  final IconData activeIcon;
  final String label;
  final String key;

  const _NavItem(this.icon, this.activeIcon, this.label, this.key);
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
    return Column(
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
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 250),
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: widget.isActive ? Colors.white : Colors.transparent,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(
              widget.isActive ? widget.item.activeIcon : widget.item.icon,
              color: widget.isActive ? Colors.black : Colors.white,
              size: 22,
            ),
          ),
        ),
        const SizedBox(height: 2),
        Text(
          widget.item.label,
          style: TextStyle(
            fontSize: 10,
            fontWeight:
                widget.isActive ? FontWeight.w600 : FontWeight.normal,
            color: widget.isActive ? Colors.white : Colors.white70,
          ),
          overflow: TextOverflow.ellipsis,
        ),
      ],
    );
  }
}
