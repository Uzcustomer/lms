import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:table_calendar/table_calendar.dart';
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
  DateTime _focusedDay = DateTime.now();
  DateTime _selectedDay = DateTime.now();
  CalendarFormat _calendarFormat = CalendarFormat.month;

  // Uzbek day name -> weekday number
  static const Map<String, int> _dayWeekday = {
    'Dushanba': DateTime.monday,
    'Seshanba': DateTime.tuesday,
    'Chorshanba': DateTime.wednesday,
    'Payshanba': DateTime.thursday,
    'Juma': DateTime.friday,
    'Shanba': DateTime.saturday,
    'Yakshanba': DateTime.sunday,
  };

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

  /// Get the set of weekday numbers that have lessons
  Set<int> _getScheduledWeekdays(Map<String, dynamic> days) {
    final result = <int>{};
    for (final entry in days.entries) {
      final dayName = entry.key;
      final lessons = entry.value as List<dynamic>? ?? [];
      if (lessons.isNotEmpty) {
        final weekday = _dayWeekday[dayName];
        if (weekday != null) result.add(weekday);
      }
    }
    return result;
  }

  /// Check if a given date falls on a scheduled weekday
  bool _hasLessonsOnDate(DateTime date, Set<int> scheduledWeekdays) {
    return scheduledWeekdays.contains(date.weekday);
  }

  /// Get lessons for a specific selected day
  List<dynamic> _getLessonsForDay(DateTime date, Map<String, dynamic> days) {
    final uzName = _weekdayToUzName[date.weekday];
    if (uzName == null) return [];
    final lessons = days[uzName] as List<dynamic>? ?? [];
    return lessons;
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
          IconButton(
            icon: const Icon(Icons.settings),
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
          final scheduledWeekdays = _getScheduledWeekdays(days);
          final selectedLessons = _getLessonsForDay(_selectedDay, days);

          return RefreshIndicator(
            onRefresh: () => provider.loadSchedule(),
            child: ListView(
              padding: const EdgeInsets.fromLTRB(0, 0, 0, 100),
              children: [
                // Calendar
                Container(
                  margin: const EdgeInsets.fromLTRB(12, 12, 12, 0),
                  decoration: BoxDecoration(
                    color: isDark ? AppTheme.darkCard : Colors.white,
                    borderRadius: BorderRadius.circular(16),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withAlpha(isDark ? 30 : 10),
                        blurRadius: 8,
                        offset: const Offset(0, 2),
                      ),
                    ],
                  ),
                  child: TableCalendar(
                    firstDay: DateTime.now().subtract(const Duration(days: 365)),
                    lastDay: DateTime.now().add(const Duration(days: 365)),
                    focusedDay: _focusedDay,
                    calendarFormat: _calendarFormat,
                    startingDayOfWeek: StartingDayOfWeek.monday,
                    selectedDayPredicate: (day) => isSameDay(_selectedDay, day),
                    onDaySelected: (selectedDay, focusedDay) {
                      setState(() {
                        _selectedDay = selectedDay;
                        _focusedDay = focusedDay;
                      });
                    },
                    onFormatChanged: (format) {
                      setState(() {
                        _calendarFormat = format;
                      });
                    },
                    onPageChanged: (focusedDay) {
                      _focusedDay = focusedDay;
                    },
                    calendarBuilders: CalendarBuilders(
                      // Orange marker for days that have lessons
                      markerBuilder: (context, date, events) {
                        if (_hasLessonsOnDate(date, scheduledWeekdays)) {
                          return Positioned(
                            bottom: 4,
                            child: Container(
                              width: 20,
                              height: 3,
                              decoration: BoxDecoration(
                                color: const Color(0xFFFF9800),
                                borderRadius: BorderRadius.circular(2),
                              ),
                            ),
                          );
                        }
                        return null;
                      },
                    ),
                    calendarStyle: CalendarStyle(
                      outsideDaysVisible: false,
                      todayDecoration: BoxDecoration(
                        color: AppTheme.primaryColor.withAlpha(40),
                        shape: BoxShape.circle,
                      ),
                      todayTextStyle: TextStyle(
                        color: isDark ? Colors.white : AppTheme.primaryColor,
                        fontWeight: FontWeight.bold,
                      ),
                      selectedDecoration: const BoxDecoration(
                        color: AppTheme.primaryColor,
                        shape: BoxShape.circle,
                      ),
                      selectedTextStyle: const TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.bold,
                      ),
                      defaultTextStyle: TextStyle(
                        color: isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary,
                      ),
                      weekendTextStyle: TextStyle(
                        color: isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary,
                      ),
                      cellMargin: const EdgeInsets.all(4),
                    ),
                    headerStyle: HeaderStyle(
                      formatButtonVisible: false,
                      titleCentered: true,
                      leftChevronIcon: Icon(
                        Icons.chevron_left,
                        color: isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary,
                      ),
                      rightChevronIcon: Icon(
                        Icons.chevron_right,
                        color: isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary,
                      ),
                      titleTextStyle: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w600,
                        color: isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary,
                      ),
                    ),
                    daysOfWeekStyle: DaysOfWeekStyle(
                      weekdayStyle: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                        color: isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary,
                      ),
                      weekendStyle: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                        color: isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary,
                      ),
                    ),
                  ),
                ),

                const SizedBox(height: 16),

                // Lessons for selected day
                if (selectedLessons.isEmpty)
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 32),
                    child: Center(
                      child: Column(
                        children: [
                          Icon(
                            Icons.event_busy_outlined,
                            size: 48,
                            color: isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary,
                          ),
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
                    final lesson = entry.value as Map<String, dynamic>;
                    return _LessonCard(
                      lesson: lesson,
                      isDark: isDark,
                      lessonUnitLabel: l.lessonUnit,
                    );
                  }),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _LessonCard extends StatelessWidget {
  final Map<String, dynamic> lesson;
  final bool isDark;
  final String lessonUnitLabel;

  const _LessonCard({
    required this.lesson,
    required this.isDark,
    required this.lessonUnitLabel,
  });

  @override
  Widget build(BuildContext context) {
    final secondaryText = isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;
    final primaryText = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;
    final accentColor = AppTheme.primaryColor;

    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: isDark ? AppTheme.darkCard : Colors.white,
        borderRadius: BorderRadius.circular(14),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withAlpha(isDark ? 25 : 8),
            blurRadius: 6,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Para number badge
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: accentColor.withAlpha(isDark ? 40 : 20),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(
                  lesson['lesson_pair_code']?.toString() ?? '',
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    color: accentColor,
                    fontSize: 16,
                  ),
                ),
                Text(
                  lessonUnitLabel,
                  style: TextStyle(
                    fontSize: 9,
                    color: accentColor.withAlpha(178),
                  ),
                ),
              ],
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
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    color: primaryText,
                  ),
                ),
                const SizedBox(height: 4),
                if (lesson['employee_name'] != null)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 4),
                    child: Text(
                      lesson['employee_name'].toString(),
                      style: TextStyle(fontSize: 12, color: secondaryText),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                Row(
                  children: [
                    if (lesson['auditorium_name'] != null) ...[
                      Icon(Icons.room_outlined, size: 13, color: secondaryText),
                      const SizedBox(width: 3),
                      Flexible(
                        child: Text(
                          lesson['auditorium_name'].toString(),
                          style: TextStyle(fontSize: 11, color: secondaryText),
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                    if (lesson['lesson_pair_start_time'] != null) ...[
                      const SizedBox(width: 10),
                      Icon(Icons.access_time, size: 13, color: secondaryText),
                      const SizedBox(width: 3),
                      Text(
                        '${lesson['lesson_pair_start_time']} - ${lesson['lesson_pair_end_time'] ?? ''}',
                        style: TextStyle(fontSize: 11, color: secondaryText),
                      ),
                    ],
                  ],
                ),
              ],
            ),
          ),
          // Training type
          if (lesson['training_type_name'] != null)
            Container(
              constraints: const BoxConstraints(maxWidth: 70),
              margin: const EdgeInsets.only(left: 6),
              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 3),
              decoration: BoxDecoration(
                color: const Color(0xFFFF9800).withAlpha(isDark ? 35 : 20),
                borderRadius: BorderRadius.circular(6),
              ),
              child: Text(
                lesson['training_type_name'].toString(),
                style: TextStyle(
                  fontSize: 10,
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
