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
  int _selectedDayIndex = -1; // -1 means auto-select today

  static const Map<int, String> _weekdayToUzName = {
    DateTime.monday: 'Dushanba',
    DateTime.tuesday: 'Seshanba',
    DateTime.wednesday: 'Chorshanba',
    DateTime.thursday: 'Payshanba',
    DateTime.friday: 'Juma',
    DateTime.saturday: 'Shanba',
    DateTime.sunday: 'Yakshanba',
  };

  static const Map<int, String> _weekdayShort = {
    DateTime.monday: 'Du',
    DateTime.tuesday: 'Se',
    DateTime.wednesday: 'Cho',
    DateTime.thursday: 'Pay',
    DateTime.friday: 'Ju',
    DateTime.saturday: 'Sha',
    DateTime.sunday: 'Ya',
  };

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<StudentProvider>().loadSchedule();
    });
  }

  List<DateTime> _getWeekDaysFromApi(Map<String, dynamic> schedule) {
    final weeks = schedule['weeks'] as List<dynamic>? ?? [];
    final selectedWeekId = schedule['selected_week_id'];

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

    final now = DateTime.now();
    final monday = now.subtract(Duration(days: now.weekday - 1));
    return List.generate(6, (i) => monday.add(Duration(days: i)));
  }

  List<dynamic> _getLessonsForDate(DateTime date, List<dynamic> dateSchedule, Map<String, dynamic> days) {
    final dateStr = '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';

    for (final entry in dateSchedule) {
      if (entry is Map<String, dynamic> && entry['date'] == dateStr) {
        return entry['lessons'] as List<dynamic>? ?? [];
      }
    }

    final uzName = _weekdayToUzName[date.weekday];
    if (uzName == null) return [];
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

  int _findCurrentWeekIndex(List<dynamic> weeks, dynamic selectedWeekId) {
    for (int i = 0; i < weeks.length; i++) {
      if (weeks[i] is Map<String, dynamic> && weeks[i]['id'] == selectedWeekId) {
        return i;
      }
    }
    return -1;
  }

  bool _isSameDay(DateTime a, DateTime b) {
    return a.year == b.year && a.month == b.month && a.day == b.day;
  }

  int _getInitialSelectedIndex(List<DateTime> weekDays) {
    if (_selectedDayIndex >= 0 && _selectedDayIndex < weekDays.length) {
      return _selectedDayIndex;
    }
    // Auto-select today if it's in the week, otherwise first day
    final now = DateTime.now();
    for (int i = 0; i < weekDays.length; i++) {
      if (_isSameDay(weekDays[i], now)) return i;
    }
    return 0;
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
          final activeIndex = _getInitialSelectedIndex(weekDays);
          final selectedDate = weekDays[activeIndex];
          final selectedLessons = _getLessonsForDate(selectedDate, dateSchedule, days);

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

                // Day selector strip
                _buildDaySelector(
                  context,
                  weekDays: weekDays,
                  activeIndex: activeIndex,
                  dateSchedule: dateSchedule,
                  days: days,
                  isDark: isDark,
                ),

                const SizedBox(height: 12),

                // Selected day header
                _buildSelectedDayHeader(
                  context,
                  date: selectedDate,
                  lessonsCount: selectedLessons.length,
                  isDark: isDark,
                  l: l,
                ),

                // Lessons for selected day
                if (selectedLessons.isEmpty)
                  Container(
                    margin: const EdgeInsets.symmetric(horizontal: 12),
                    padding: const EdgeInsets.symmetric(vertical: 40),
                    decoration: BoxDecoration(
                      color: isDark ? AppTheme.darkCard.withAlpha(120) : Colors.white,
                      borderRadius: const BorderRadius.vertical(bottom: Radius.circular(14)),
                    ),
                    child: Center(
                      child: Column(
                        children: [
                          Icon(Icons.event_busy_outlined, size: 48,
                              color: isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary),
                          const SizedBox(height: 12),
                          Text(
                            l.noLessons,
                            style: TextStyle(
                              fontSize: 15,
                              color: isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary,
                            ),
                          ),
                        ],
                      ),
                    ),
                  )
                else
                  ...selectedLessons.asMap().entries.map((entry) {
                    final index = entry.key;
                    final lesson = entry.value as Map<String, dynamic>;
                    final isLast = index == selectedLessons.length - 1;
                    return _LessonCard(
                      lesson: lesson,
                      isDark: isDark,
                      isLast: isLast,
                    );
                  }),
              ],
            ),
          );
        },
      ),
    );
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
      padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 10),
      decoration: BoxDecoration(
        color: AppTheme.primaryColor,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Row(
        children: [
          IconButton(
            icon: Icon(Icons.chevron_left,
                color: canGoPrev ? Colors.white : Colors.white.withAlpha(60)),
            onPressed: canGoPrev
                ? () {
                    setState(() => _selectedDayIndex = -1);
                    final prevWeek = weeks[currentIndex - 1] as Map<String, dynamic>;
                    provider.loadSchedule(weekId: prevWeek['id']?.toString());
                  }
                : null,
          ),
          Expanded(
            child: Text(
              weekLabel,
              style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w600, color: Colors.white),
              textAlign: TextAlign.center,
            ),
          ),
          IconButton(
            icon: Icon(Icons.chevron_right,
                color: canGoNext ? Colors.white : Colors.white.withAlpha(60)),
            onPressed: canGoNext
                ? () {
                    setState(() => _selectedDayIndex = -1);
                    final nextWeek = weeks[currentIndex + 1] as Map<String, dynamic>;
                    provider.loadSchedule(weekId: nextWeek['id']?.toString());
                  }
                : null,
          ),
        ],
      ),
    );
  }

  Widget _buildDaySelector(
    BuildContext context, {
    required List<DateTime> weekDays,
    required int activeIndex,
    required List<dynamic> dateSchedule,
    required Map<String, dynamic> days,
    required bool isDark,
  }) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 12),
      child: Row(
        children: weekDays.asMap().entries.map((entry) {
          final i = entry.key;
          final day = entry.value;
          final isToday = _isSameDay(day, DateTime.now());
          final isSelected = i == activeIndex;
          final hasLessons = _getLessonsForDate(day, dateSchedule, days).isNotEmpty;
          final shortName = _weekdayShort[day.weekday] ?? '';

          return Expanded(
            child: GestureDetector(
              onTap: () => setState(() => _selectedDayIndex = i),
              child: Container(
                margin: const EdgeInsets.symmetric(horizontal: 2),
                padding: const EdgeInsets.symmetric(vertical: 10),
                decoration: BoxDecoration(
                  color: isSelected
                      ? (isToday ? const Color(0xFFFF9800) : AppTheme.primaryColor)
                      : (isToday
                          ? const Color(0xFFFF9800).withAlpha(30)
                          : (isDark ? AppTheme.darkCard : Colors.white)),
                  borderRadius: BorderRadius.circular(12),
                  border: isToday && !isSelected
                      ? Border.all(color: const Color(0xFFFF9800), width: 1.5)
                      : null,
                ),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      shortName,
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                        color: isSelected
                            ? Colors.white
                            : (isToday
                                ? const Color(0xFFFF9800)
                                : (isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary)),
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '${day.day}',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                        color: isSelected
                            ? Colors.white
                            : (isToday
                                ? const Color(0xFFFF9800)
                                : (isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary)),
                      ),
                    ),
                    const SizedBox(height: 4),
                    // Dot indicator for days with lessons
                    Container(
                      width: 6,
                      height: 6,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: hasLessons
                            ? (isSelected ? Colors.white : const Color(0xFFFF9800))
                            : Colors.transparent,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          );
        }).toList(),
      ),
    );
  }

  Widget _buildSelectedDayHeader(
    BuildContext context, {
    required DateTime date,
    required int lessonsCount,
    required bool isDark,
    required AppLocalizations l,
  }) {
    final isToday = _isSameDay(date, DateTime.now());
    final dayName = _weekdayToUzName[date.weekday] ?? '';
    final dateStr = '${date.day.toString().padLeft(2, '0')}.${date.month.toString().padLeft(2, '0')}.${date.year}';

    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 12),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      decoration: BoxDecoration(
        color: isToday
            ? const Color(0xFFFF9800)
            : (isDark ? AppTheme.darkCard : AppTheme.primaryLight),
        borderRadius: const BorderRadius.vertical(top: Radius.circular(14)),
      ),
      child: Row(
        children: [
          Icon(isToday ? Icons.today : Icons.calendar_today, size: 16, color: Colors.white),
          const SizedBox(width: 8),
          Text(
            '$dayName, $dateStr',
            style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: Colors.white),
          ),
          if (isToday) ...[
            const SizedBox(width: 8),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
              decoration: BoxDecoration(
                color: Colors.white.withAlpha(40),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Text(l.today,
                  style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w600, color: Colors.white)),
            ),
          ],
          const Spacer(),
          Text(
            '$lessonsCount ${l.lessonUnit}',
            style: TextStyle(fontSize: 11, color: Colors.white.withAlpha(200)),
          ),
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
      margin: const EdgeInsets.symmetric(horizontal: 12),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: isDark ? AppTheme.darkCard : Colors.white,
        borderRadius: isLast
            ? const BorderRadius.vertical(bottom: Radius.circular(14))
            : null,
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
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: const Color(0xFFFF9800).withAlpha(isDark ? 40 : 25),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, size: 24, color: const Color(0xFFFF9800)),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  lesson['subject_name']?.toString() ?? '',
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: primaryText),
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
