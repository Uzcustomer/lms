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

  List<_NavItem> _getNavItems(String? activeRole, AppLocalizations l) {
    final items = <_NavItem>[
      _NavItem(Icons.dashboard_outlined, Icons.dashboard, l.home, 'dashboard'),
    ];

    // Jurnal - teachers, registrars, heads, subject responsible
    const jurnalRoles = [
      'superadmin', 'admin', 'kichik_admin', 'registrator_ofisi',
      'dekan', 'oqituvchi', 'kafedra_mudiri', 'fan_masuli',
    ];
    if (activeRole != null && jurnalRoles.contains(activeRole) && activeRole != 'oquv_bolimi') {
      items.add(_NavItem(Icons.edit_note_outlined, Icons.edit_note, 'Jurnal', 'journal'));
    }

    // Talabalar - most roles except test_markazi, oquv_bolimi
    if (activeRole != 'test_markazi' && activeRole != 'oquv_bolimi') {
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

        return Scaffold(
          extendBody: true,
          body: _getScreen(navItems[_currentIndex].key),
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
                              setState(() => _currentIndex = index);
                            }
                          },
                          behavior: HitTestBehavior.opaque,
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
                                  color: isActive
                                      ? Colors.black
                                      : Colors.white,
                                  size: 22,
                                ),
                              ),
                              const SizedBox(height: 2),
                              Text(
                                item.label,
                                style: TextStyle(
                                  fontSize: 10,
                                  fontWeight: isActive
                                      ? FontWeight.w600
                                      : FontWeight.normal,
                                  color: isActive
                                      ? Colors.white
                                      : Colors.white70,
                                ),
                                overflow: TextOverflow.ellipsis,
                              ),
                            ],
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
