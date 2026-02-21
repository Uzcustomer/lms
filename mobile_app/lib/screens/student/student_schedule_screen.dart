import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/theme.dart';
import '../../providers/student_provider.dart';
import '../../l10n/app_localizations.dart';
import '../../widgets/loading_widget.dart';

class StudentScheduleScreen extends StatefulWidget {
  const StudentScheduleScreen({super.key});

  @override
  State<StudentScheduleScreen> createState() => _StudentScheduleScreenState();
}

class _StudentScheduleScreenState extends State<StudentScheduleScreen> {
  // Reverse: weekday number -> Uzbek day name
  static const Map<int, String> _weekdayToUzName = {
    DateTime.monday: 'Dushanba',
    DateTime.tuesday: 'Seshanba',
    DateTime.wednesday: 'Chorshanba',
    DateTime.thursday: 'Payshanba',
    DateTime.friday: 'Juma',
    DateTime.saturday: 'Shanba',
    DateTime.sunday: 'Yakshanba',
  };

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<StudentProvider>().loadSchedule();
    });
  }

  /// Parse the selected week's start/end dates from API response
  List<DateTime> _getWeekDaysFromApi(Map<String, dynamic> schedule) {
    final weeks = schedule['weeks'] as List<dynamic>? ?? [];
    final selectedWeekId = schedule['selected_week_id'];

    // Find selected week
    Map<String, dynamic>? selectedWeek;
    for (final w in weeks) {
      if (w is Map<String, dynamic> && w['id'] == selectedWeekId) {
        selectedWeek = w;
        break;
      }
    }

    if (selectedWeek != null) {
      final startDate = DateTime.tryParse(selectedWeek['start_date']?.toString() ?? '');
      final endDate = DateTime.tryParse(selectedWeek['end_date']?.toString() ?? '');
      if (startDate != null && endDate != null) {
        final days = <DateTime>[];
        var current = startDate;
        while (!current.isAfter(endDate)) {
          days.add(current);
          current = current.add(const Duration(days: 1));
        }
        return days;
      }
    }

    // Fallback: generate Mon-Sat of current week
    final now = DateTime.now();
    final monday = now.subtract(Duration(days: now.weekday - 1));
    return List.generate(6, (i) => monday.add(Duration(days: i)));
  }

  /// Get lessons for a specific date from the schedule array
  List<dynamic> _getLessonsForDate(DateTime date, List<dynamic> dateSchedule, Map<String, dynamic> days) {
    final dateStr = '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';

    // Try exact date match from the schedule array
    for (final entry in dateSchedule) {
      if (entry is Map<String, dynamic> && entry['date'] == dateStr) {
        return entry['lessons'] as List<dynamic>? ?? [];
      }
    }

    // Fallback to day name matching from the days map
    final uzName = _weekdayToUzName[date.weekday];
    if (uzName == null) return [];
    // Normalize keys
    for (final entry in days.entries) {
      final key = entry.key.isNotEmpty
          ? entry.key[0].toUpperCase() + entry.key.substring(1).toLowerCase()
          : entry.key;
      if (key == uzName) {
        return entry.value as List<dynamic>? ?? [];
      }
    }
    return [];
  }

  /// Find the index of current week in weeks list
  int _findCurrentWeekIndex(List<dynamic> weeks, dynamic selectedWeekId) {
    for (int i = 0; i < weeks.length; i++) {
      if (weeks[i] is Map<String, dynamic> && weeks[i]['id'] == selectedWeekId) {
        return i;
      }
    }
    return -1;
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      backgroundColor: isDark ? AppTheme.darkBackground : AppTheme.backgroundColor,
      appBar: AppBar(
        title: Text(l.schedule),
        centerTitle: true,
        leading: Navigator.canPop(context)
            ? IconButton(
                icon: const Icon(Icons.arrow_back),
                onPressed: () => Navigator.pop(context),
              )
            : const Padding(
                padding: EdgeInsets.all(12),
                child: Icon(Icons.account_balance, size: 28),
              ),
        actions: [
          IconButton(
            icon: const Icon(Icons.notifications_outlined),
            onPressed: () {},
          ),
        ],
      ),
      body: Consumer<StudentProvider>(
        builder: (context, provider, _) {
          if (provider.isLoading && provider.schedule == null) {
            return const LoadingWidget();
          }

          final schedule = provider.schedule;
          if (schedule == null) {
            return Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.calendar_today_outlined,
                      size: 64, color: isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary),
                  const SizedBox(height: 16),
                  Text(provider.error ?? l.scheduleNotFound),
                  const SizedBox(height: 16),
                  ElevatedButton(
                    onPressed: () => provider.loadSchedule(),
                    child: Text(l.reload),
                  ),
                ],
              ),
            );
          }

          final days = schedule['days'] as Map<String, dynamic>? ?? {};
          final dateSchedule = schedule['schedule'] as List<dynamic>? ?? [];
          final weeks = schedule['weeks'] as List<dynamic>? ?? [];
          final selectedWeekId = schedule['selected_week_id'];
          final weekLabel = schedule['week_label']?.toString() ?? '';
          final weekDays = _getWeekDaysFromApi(schedule);
          final currentWeekIndex = _findCurrentWeekIndex(weeks, selectedWeekId);

          return RefreshIndicator(
            onRefresh: () => provider.loadSchedule(),
            child: ListView(
              padding: const EdgeInsets.fromLTRB(0, 0, 0, 100),
              children: [
                // Week navigation
                _buildWeekNavigator(
                  context,
                  weeks: weeks,
                  currentIndex: currentWeekIndex,
                  weekLabel: weekLabel,
                  isDark: isDark,
                  provider: provider,
                ),

                const SizedBox(height: 8),

                // All days of the week with lessons
                ...weekDays.map((day) {
                  final dayLessons = _getLessonsForDate(day, dateSchedule, days);
                  final isToday = _isSameDay(day, DateTime.now());
                  final dayName = _weekdayToUzName[day.weekday] ?? '';
                  final dateStr = '${day.day.toString().padLeft(2, '0')}.${day.month.toString().padLeft(2, '0')}';

                  return _buildDaySection(
                    context,
                    dayName: dayName,
                    dateStr: dateStr,
                    lessons: dayLessons,
                    isToday: isToday,
                    isDark: isDark,
                    l: l,
                  );
                }),
              ],
            ),
          );
        },
      ),
    );
  }

  bool _isSameDay(DateTime a, DateTime b) {
    return a.year == b.year && a.month == b.month && a.day == b.day;
  }

  Widget _buildWeekNavigator(
    BuildContext context, {
    required List<dynamic> weeks,
    required int currentIndex,
    required String weekLabel,
    required bool isDark,
    required StudentProvider provider,
  }) {
    final canGoPrev = currentIndex > 0;
    final canGoNext = currentIndex < weeks.length - 1;

    return Container(
      margin: const EdgeInsets.fromLTRB(12, 12, 12, 0),
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 12),
      decoration: BoxDecoration(
        color: AppTheme.primaryColor,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Row(
        children: [
          // Previous week
          IconButton(
            icon: Icon(
              Icons.chevron_left,
              color: canGoPrev ? Colors.white : Colors.white.withAlpha(60),
            ),
            onPressed: canGoPrev
                ? () {
                    final prevWeek = weeks[currentIndex - 1] as Map<String, dynamic>;
                    provider.loadSchedule(weekId: prevWeek['id']?.toString());
                  }
                : null,
          ),

          // Week label
          Expanded(
            child: Column(
              children: [
                const Icon(Icons.calendar_today, color: Colors.white, size: 18),
                const SizedBox(height: 4),
                Text(
                  weekLabel,
                  style: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w600,
                    color: Colors.white,
                  ),
                  textAlign: TextAlign.center,
                ),
              ],
            ),
          ),

          // Next week
          IconButton(
            icon: Icon(
              Icons.chevron_right,
              color: canGoNext ? Colors.white : Colors.white.withAlpha(60),
            ),
            onPressed: canGoNext
                ? () {
                    final nextWeek = weeks[currentIndex + 1] as Map<String, dynamic>;
                    provider.loadSchedule(weekId: nextWeek['id']?.toString());
                  }
                : null,
          ),
        ],
      ),
    );
  }

  Widget _buildDaySection(
    BuildContext context, {
    required String dayName,
    required String dateStr,
    required List<dynamic> lessons,
    required bool isToday,
    required bool isDark,
    required AppLocalizations l,
  }) {
    final headerColor = isToday
        ? const Color(0xFFFF9800)
        : (isDark ? AppTheme.darkCard : AppTheme.primaryLight);

    return Container(
      margin: const EdgeInsets.fromLTRB(12, 4, 12, 4),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(14),
        border: isToday
            ? Border.all(color: const Color(0xFFFF9800), width: 1.5)
            : null,
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        children: [
          // Day header
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            color: headerColor,
            child: Row(
              children: [
                Icon(
                  isToday ? Icons.today : Icons.calendar_today,
                  size: 16,
                  color: Colors.white,
                ),
                const SizedBox(width: 8),
                Text(
                  '$dayName, $dateStr',
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    color: Colors.white,
                  ),
                ),
                if (isToday) ...[
                  const SizedBox(width: 8),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
                    decoration: BoxDecoration(
                      color: Colors.white.withAlpha(40),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Text(
                      l.today,
                      style: const TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.w600,
                        color: Colors.white,
                      ),
                    ),
                  ),
                ],
                const Spacer(),
                Text(
                  '${lessons.length} ${l.lessonUnit}',
                  style: TextStyle(
                    fontSize: 11,
                    color: Colors.white.withAlpha(200),
                  ),
                ),
              ],
            ),
          ),

          // Lessons
          if (lessons.isEmpty)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(vertical: 16),
              color: isDark ? AppTheme.darkCard.withAlpha(120) : Colors.white,
              child: Center(
                child: Text(
                  l.noLessons,
                  style: TextStyle(
                    fontSize: 13,
                    color: isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary,
                  ),
                ),
              ),
            )
          else
            ...lessons.asMap().entries.map((entry) {
              final index = entry.key;
              final lesson = entry.value as Map<String, dynamic>;
              final isLast = index == lessons.length - 1;
              return _LessonCard(
                lesson: lesson,
                isDark: isDark,
                isLast: isLast,
              );
            }),
        ],
      ),
    );
  }
}

class _LessonCard extends StatelessWidget {
  final Map<String, dynamic> lesson;
  final bool isDark;
  final bool isLast;

  const _LessonCard({
    required this.lesson,
    required this.isDark,
    this.isLast = false,
  });

  IconData _getLessonIcon(String? trainingType) {
    final type = (trainingType ?? '').toLowerCase();
    if (type.contains("ma'ruza") || type.contains('maruza') || type.contains('lektsiya')) {
      return Icons.menu_book_rounded;
    } else if (type.contains('amaliyot') || type.contains('praktika')) {
      return Icons.computer_rounded;
    } else if (type.contains('seminar')) {
      return Icons.groups_rounded;
    } else if (type.contains('laborator')) {
      return Icons.science_rounded;
    }
    return Icons.auto_stories_rounded;
  }

  @override
  Widget build(BuildContext context) {
    final secondaryText = isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;
    final primaryText = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;
    final trainingType = lesson['training_type_name']?.toString();
    final icon = _getLessonIcon(trainingType);

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: isDark ? AppTheme.darkCard : Colors.white,
        border: isLast
            ? null
            : Border(
                bottom: BorderSide(
                  color: isDark ? AppTheme.darkDivider : const Color(0xFFEEEEEE),
                  width: 0.5,
                ),
              ),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Lesson icon
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: const Color(0xFFFF9800).withAlpha(isDark ? 40 : 25),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(
              icon,
              size: 24,
              color: const Color(0xFFFF9800),
            ),
          ),
          const SizedBox(width: 12),
          // Subject info
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  lesson['subject_name']?.toString() ?? '',
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: primaryText,
                  ),
                ),
                const SizedBox(height: 3),
                if (lesson['employee_name'] != null)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 3),
                    child: Row(
                      children: [
                        Icon(Icons.person_outline, size: 12, color: secondaryText),
                        const SizedBox(width: 3),
                        Expanded(
                          child: Text(
                            lesson['employee_name'].toString(),
                            style: TextStyle(fontSize: 11, color: secondaryText),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ],
                    ),
                  ),
                Row(
                  children: [
                    if (lesson['auditorium_name'] != null &&
                        lesson['auditorium_name'].toString().isNotEmpty) ...[
                      Icon(Icons.room_outlined, size: 12, color: secondaryText),
                      const SizedBox(width: 2),
                      Flexible(
                        child: Text(
                          lesson['auditorium_name'].toString(),
                          style: TextStyle(fontSize: 10, color: secondaryText),
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                    if (lesson['lesson_pair_start_time'] != null) ...[
                      const SizedBox(width: 8),
                      Icon(Icons.access_time, size: 12, color: secondaryText),
                      const SizedBox(width: 2),
                      Text(
                        '${lesson['lesson_pair_start_time']} - ${lesson['lesson_pair_end_time'] ?? ''}',
                        style: TextStyle(fontSize: 10, color: secondaryText),
                      ),
                    ],
                  ],
                ),
              ],
            ),
          ),
          // Training type badge
          if (trainingType != null)
            Container(
              constraints: const BoxConstraints(maxWidth: 60),
              margin: const EdgeInsets.only(left: 4),
              padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
              decoration: BoxDecoration(
                color: const Color(0xFFFF9800).withAlpha(isDark ? 35 : 20),
                borderRadius: BorderRadius.circular(6),
              ),
              child: Text(
                trainingType,
                style: TextStyle(
                  fontSize: 9,
                  color: isDark ? const Color(0xFFFFB74D) : const Color(0xFFE65100),
                  fontWeight: FontWeight.w500,
                ),
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                textAlign: TextAlign.center,
              ),
            ),
        ],
      ),
    );
  }
}
