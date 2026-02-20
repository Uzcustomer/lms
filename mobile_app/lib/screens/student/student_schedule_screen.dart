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
  // Uzbek -> Latin day name mapping
  static const Map<String, String> _dayNamesLatin = {
    'Dushanba': 'Monday',
    'Seshanba': 'Tuesday',
    'Chorshanba': 'Wednesday',
    'Payshanba': 'Thursday',
    'Juma': 'Friday',
    'Shanba': 'Saturday',
    'Yakshanba': 'Sunday',
  };

  static const Map<String, int> _dayWeekday = {
    'Dushanba': DateTime.monday,
    'Seshanba': DateTime.tuesday,
    'Chorshanba': DateTime.wednesday,
    'Payshanba': DateTime.thursday,
    'Juma': DateTime.friday,
    'Shanba': DateTime.saturday,
    'Yakshanba': DateTime.sunday,
  };

  // Different light colors for each day's card
  static const List<Color> _dayCardColors = [
    Color(0xFFE3F2FD), // Monday - light blue
    Color(0xFFE8F5E9), // Tuesday - light green
    Color(0xFFFFF3E0), // Wednesday - light orange
    Color(0xFFF3E5F5), // Thursday - light purple
    Color(0xFFFCE4EC), // Friday - light pink
    Color(0xFFE0F2F1), // Saturday - light teal
    Color(0xFFFFF8E1), // Sunday - light amber
  ];

  // Header colors for each day
  static const List<Color> _dayHeaderColors = [
    Color(0xFF1565C0), // Monday - blue
    Color(0xFF2E7D32), // Tuesday - green
    Color(0xFFE65100), // Wednesday - orange
    Color(0xFF7B1FA2), // Thursday - purple
    Color(0xFFC62828), // Friday - red/pink
    Color(0xFF00695C), // Saturday - teal
    Color(0xFFF9A825), // Sunday - amber
  ];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<StudentProvider>().loadSchedule();
    });
  }

  String _getDateForDay(int targetWeekday) {
    final now = DateTime.now();
    final currentWeekday = now.weekday;
    final diff = targetWeekday - currentWeekday;
    final date = now.add(Duration(days: diff));
    return '${date.day.toString().padLeft(2, '0')}.${date.month.toString().padLeft(2, '0')}';
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

          if (days.isEmpty) {
            return Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.calendar_today_outlined,
                      size: 64, color: isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary),
                  const SizedBox(height: 16),
                  Text(
                    l.noScheduleThisWeek,
                    style: TextStyle(
                      color: isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary,
                    ),
                  ),
                ],
              ),
            );
          }

          return RefreshIndicator(
            onRefresh: () => provider.loadSchedule(),
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
              children: [
                if (schedule['week_label'] != null)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 16),
                    child: Text(
                      schedule['week_label'].toString(),
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.bold,
                          ),
                      textAlign: TextAlign.center,
                    ),
                  ),
                ...days.entries.toList().asMap().entries.map((mapEntry) {
                  final dayIndex = mapEntry.key;
                  final entry = mapEntry.value;
                  final dayName = entry.key;
                  final lessons = entry.value as List<dynamic>? ?? [];
                  return _DayScheduleCard(
                    dayName: dayName,
                    latinName: _dayNamesLatin[dayName] ?? dayName,
                    date: _getDateForDay(_dayWeekday[dayName] ?? 1),
                    lessons: lessons,
                    cardColor: _dayCardColors[dayIndex % _dayCardColors.length],
                    headerColor: _dayHeaderColors[dayIndex % _dayHeaderColors.length],
                    isDark: isDark,
                    todayLabel: l.today,
                    noLessonsLabel: l.noLessons,
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

class _DayScheduleCard extends StatelessWidget {
  final String dayName;
  final String latinName;
  final String date;
  final List<dynamic> lessons;
  final Color cardColor;
  final Color headerColor;
  final bool isDark;
  final String todayLabel;
  final String noLessonsLabel;
  final String lessonUnitLabel;

  const _DayScheduleCard({
    required this.dayName,
    required this.latinName,
    required this.date,
    required this.lessons,
    required this.cardColor,
    required this.headerColor,
    required this.isDark,
    required this.todayLabel,
    required this.noLessonsLabel,
    required this.lessonUnitLabel,
  });

  @override
  Widget build(BuildContext context) {
    final isToday = _isToday(dayName);
    final bodyBg = isDark ? AppTheme.darkCard : cardColor.withAlpha(80);
    final secondaryText = isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;
    final primaryText = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: isToday
            ? BorderSide(color: headerColor, width: 2)
            : BorderSide.none,
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Day header with distinct color
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            decoration: BoxDecoration(
              color: headerColor.withAlpha(isToday ? 40 : 25),
            ),
            child: Row(
              children: [
                Icon(
                  Icons.calendar_today,
                  size: 18,
                  color: headerColor,
                ),
                const SizedBox(width: 8),
                Text(
                  '$latinName, $date',
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 15,
                    color: headerColor,
                  ),
                ),
                if (isToday) ...[
                  const SizedBox(width: 8),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                    decoration: BoxDecoration(
                      color: headerColor,
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Text(
                      todayLabel,
                      style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w600),
                    ),
                  ),
                ],
              ],
            ),
          ),
          // Lessons
          Container(
            color: bodyBg,
            child: lessons.isEmpty
                ? Padding(
                    padding: const EdgeInsets.all(16),
                    child: Text(
                      noLessonsLabel,
                      style: TextStyle(color: secondaryText),
                    ),
                  )
                : Column(
                    children: lessons.map((lesson) {
                      final l = lesson as Map<String, dynamic>;
                      return Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            // Para number
                            Container(
                              width: 44,
                              height: 44,
                              decoration: BoxDecoration(
                                color: headerColor.withAlpha(25),
                                borderRadius: BorderRadius.circular(12),
                              ),
                              child: Column(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Text(
                                    l['lesson_pair_code']?.toString() ?? '',
                                    style: TextStyle(
                                      fontWeight: FontWeight.bold,
                                      color: headerColor,
                                      fontSize: 16,
                                    ),
                                  ),
                                  Text(
                                    lessonUnitLabel,
                                    style: TextStyle(
                                      fontSize: 9,
                                      color: headerColor.withAlpha(178),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            const SizedBox(width: 10),
                            // Subject info
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    l['subject_name']?.toString() ?? '',
                                    maxLines: 2,
                                    overflow: TextOverflow.ellipsis,
                                    style: TextStyle(
                                      fontSize: 13,
                                      fontWeight: FontWeight.w600,
                                      color: primaryText,
                                    ),
                                  ),
                                  const SizedBox(height: 2),
                                  if (l['employee_name'] != null)
                                    Text(
                                      l['employee_name'].toString(),
                                      style: TextStyle(fontSize: 11, color: secondaryText),
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  const SizedBox(height: 2),
                                  Row(
                                    children: [
                                      if (l['auditorium_name'] != null) ...[
                                        Icon(Icons.room_outlined, size: 12, color: secondaryText),
                                        const SizedBox(width: 2),
                                        Flexible(
                                          child: Text(
                                            l['auditorium_name'].toString(),
                                            style: TextStyle(fontSize: 11, color: secondaryText),
                                            overflow: TextOverflow.ellipsis,
                                          ),
                                        ),
                                      ],
                                      if (l['lesson_pair_start_time'] != null) ...[
                                        const SizedBox(width: 8),
                                        Icon(Icons.access_time, size: 12, color: secondaryText),
                                        const SizedBox(width: 2),
                                        Text(
                                          '${l['lesson_pair_start_time']} - ${l['lesson_pair_end_time'] ?? ''}',
                                          style: TextStyle(fontSize: 11, color: secondaryText),
                                        ),
                                      ],
                                    ],
                                  ),
                                ],
                              ),
                            ),
                            // Training type - constrained width to prevent overflow
                            if (l['training_type_name'] != null)
                              Container(
                                constraints: const BoxConstraints(maxWidth: 70),
                                margin: const EdgeInsets.only(left: 6),
                                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                decoration: BoxDecoration(
                                  color: headerColor.withAlpha(20),
                                  borderRadius: BorderRadius.circular(6),
                                ),
                                child: Text(
                                  l['training_type_name'].toString(),
                                  style: TextStyle(
                                    fontSize: 10,
                                    color: headerColor,
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
                    }).toList(),
                  ),
          ),
        ],
      ),
    );
  }

  bool _isToday(String dayName) {
    final weekDays = {
      'Dushanba': DateTime.monday,
      'Seshanba': DateTime.tuesday,
      'Chorshanba': DateTime.wednesday,
      'Payshanba': DateTime.thursday,
      'Juma': DateTime.friday,
      'Shanba': DateTime.saturday,
      'Yakshanba': DateTime.sunday,
    };
    return weekDays[dayName] == DateTime.now().weekday;
  }
}
