import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'dart:ui';
import '../../config/theme.dart';
import '../../providers/student_provider.dart';
import '../../l10n/app_localizations.dart';
import '../../widgets/loading_widget.dart';
import 'student_home_screen.dart';

class StudentScheduleScreen extends StatefulWidget {
  const StudentScheduleScreen({super.key});

  @override
  State<StudentScheduleScreen> createState() => _StudentScheduleScreenState();
}

class _StudentScheduleScreenState extends State<StudentScheduleScreen> {
  int _selectedDayIndex = -1;

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
    DateTime.monday: 'DU',
    DateTime.tuesday: 'SE',
    DateTime.wednesday: 'CHO',
    DateTime.thursday: 'PAY',
    DateTime.friday: 'JU',
    DateTime.saturday: 'SHA',
    DateTime.sunday: 'YA',
  };

  static const List<Color> _timelineDotColors = [
    Color(0xFF43A047),
    Color(0xFFE91E63),
    Color(0xFFFF9800),
    Color(0xFF7C4DFF),
    Color(0xFF00BCD4),
    Color(0xFFE53935),
  ];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<StudentProvider>().loadSchedule();
    });
  }

  List<DateTime> _getWeekDaysFromApi(Map<String, dynamic> schedule) {
    final weeks = schedule['weeks'] as List<dynamic>? ?? [];
    final selectedWeekId = schedule['selected_week_id']?.toString();

    Map<String, dynamic>? selectedWeek;
    for (final w in weeks) {
      if (w is Map<String, dynamic> && w['id']?.toString() == selectedWeekId) {
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
    final selectedStr = selectedWeekId?.toString();
    for (int i = 0; i < weeks.length; i++) {
      if (weeks[i] is Map<String, dynamic> && weeks[i]['id']?.toString() == selectedStr) {
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
    final now = DateTime.now();
    for (int i = 0; i < weekDays.length; i++) {
      if (_isSameDay(weekDays[i], now)) return i;
    }
    return 0;
  }

  Widget _buildGlassCard({required Widget child, required bool isDark, double borderRadius = 20, Color? cardColor}) {
    final cc = cardColor ?? const Color(0xFF0D47A1);
    final surface = isDark ? Colors.white.withOpacity(0.10) : Colors.white.withOpacity(0.7);
    final border = isDark ? Colors.white.withOpacity(0.12) : Colors.white.withOpacity(0.9);
    return ClipRRect(
      borderRadius: BorderRadius.circular(borderRadius),
      child: BackdropFilter(
        filter: ImageFilter.blur(sigmaX: 20, sigmaY: 20),
        child: Container(
          decoration: BoxDecoration(
            color: surface,
            border: Border.all(color: border),
            borderRadius: BorderRadius.circular(borderRadius),
            boxShadow: [
              BoxShadow(
                color: isDark ? Colors.black.withOpacity(0.3) : const Color(0xFF1A1340).withOpacity(0.06),
                blurRadius: 24,
                offset: const Offset(0, 8),
              ),
            ],
          ),
          child: Stack(
            children: [
              Positioned(
                top: -20,
                right: -20,
                child: ImageFiltered(
                  imageFilter: ImageFilter.blur(sigmaX: 22, sigmaY: 22),
                  child: Container(
                    width: 140,
                    height: 140,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      gradient: RadialGradient(
                        colors: [cc.withOpacity(isDark ? 0.4 : 0.32), cc.withOpacity(0)],
                        stops: const [0.0, 0.7],
                      ),
                    ),
                  ),
                ),
              ),
              child,
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final statusBarH = MediaQuery.of(context).padding.top;

    return Scaffold(
      backgroundColor: isDark ? const Color(0xFF0B1020) : const Color(0xFFFEF7F0),
      body: Stack(
        children: [
          Positioned.fill(
            child: DecoratedBox(
              decoration: BoxDecoration(
                gradient: RadialGradient(
                  center: const Alignment(-1.0, -1.0),
                  radius: 1.4,
                  colors: isDark
                      ? const [Color(0xFF6366F1), Color(0xFFA855F7), Color(0xFFEC4899), Color(0xFF0B1020)]
                      : const [Color(0xFFC7D2FE), Color(0xFFFBCFE8), Color(0xFFFED7AA), Color(0xFFFEF7F0)],
                  stops: const [0.0, 0.35, 0.65, 1.0],
                ),
              ),
            ),
          ),
          Positioned(
            top: 180, right: -80,
            child: _buildBlob(isDark ? const Color(0xFFF472B6) : const Color(0xFFF9A8D4)),
          ),
          Positioned(
            top: 480, left: -80,
            child: _buildBlob(isDark ? const Color(0xFF60A5FA) : const Color(0xFFA5B4FC)),
          ),
          Consumer<StudentProvider>(
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
                    Icon(Icons.calendar_today_outlined, size: 64,
                        color: isDark ? Colors.white38 : AppTheme.textSecondary),
                    const SizedBox(height: 16),
                    Text(provider.error ?? l.scheduleNotFound,
                        style: TextStyle(color: isDark ? Colors.white70 : AppTheme.textPrimary)),
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
                padding: const EdgeInsets.only(bottom: 100),
                children: [
                  // Top bar
                  Container(
                    padding: EdgeInsets.only(top: statusBarH, left: 16, right: 4),
                    height: statusBarH + 64,
                    decoration: const BoxDecoration(
                      color: Color(0xFF0D47A1),
                      borderRadius: BorderRadius.only(
                        bottomLeft: Radius.circular(18),
                        bottomRight: Radius.circular(18),
                      ),
                    ),
                    child: Row(
                      children: [
                        GestureDetector(
                          onTap: () => StudentHomeScreen.switchToHome(context),
                          child: const Icon(Icons.account_balance, color: Colors.white, size: 24),
                        ),
                        const Spacer(),
                        Text(l.schedule, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700, color: Colors.white)),
                        const Spacer(),
                        IconButton(
                          icon: const Icon(Icons.notifications_outlined, color: Colors.white, size: 22),
                          onPressed: () {},
                        ),
                        IconButton(
                          icon: const Icon(Icons.settings_outlined, color: Colors.white, size: 22),
                          onPressed: () {},
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 16),

                  // Week navigator
                  _buildWeekNavigator(
                    context,
                    weeks: weeks,
                    currentIndex: currentWeekIndex,
                    weekLabel: weekLabel,
                    isDark: isDark,
                    provider: provider,
                  ),

                  const SizedBox(height: 14),

                  // Day selector
                  _buildDaySelector(
                    context,
                    weekDays: weekDays,
                    activeIndex: activeIndex,
                    dateSchedule: dateSchedule,
                    days: days,
                    isDark: isDark,
                  ),

                  const SizedBox(height: 14),

                  // Day header
                  _buildSelectedDayHeader(
                    context,
                    date: selectedDate,
                    lessonsCount: selectedLessons.length,
                    isDark: isDark,
                    l: l,
                  ),

                  const SizedBox(height: 12),

                  // Lessons timeline
                  if (selectedLessons.isEmpty)
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 16),
                      child: _buildGlassCard(
                        isDark: isDark,
                        borderRadius: 16,
                        cardColor: const Color(0xFF7C4DFF),
                        child: Padding(
                          padding: const EdgeInsets.symmetric(vertical: 40),
                          child: Center(
                            child: Column(
                              children: [
                                Icon(Icons.event_busy_outlined, size: 48,
                                    color: isDark ? Colors.white38 : AppTheme.textSecondary),
                                const SizedBox(height: 12),
                                Text(
                                  l.noLessons,
                                  style: TextStyle(
                                    fontSize: 15,
                                    color: isDark ? Colors.white54 : AppTheme.textSecondary,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ),
                    )
                  else
                    ...selectedLessons.asMap().entries.map((entry) {
                      final index = entry.key;
                      final lesson = entry.value as Map<String, dynamic>;
                      final isLast = index == selectedLessons.length - 1;
                      final dotColor = _timelineDotColors[index % _timelineDotColors.length];
                      return _buildTimelineLesson(lesson, index, isLast, dotColor, isDark);
                    }),
                ],
              ),
            );
          },
        ),
        ],
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
    final isLoading = provider.isLoading;
    final canGoPrev = !isLoading && currentIndex > 0;
    final canGoNext = !isLoading && currentIndex >= 0 && currentIndex < weeks.length - 1;

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: _buildGlassCard(
        isDark: isDark,
        borderRadius: 14,
        cardColor: const Color(0xFF1565C0),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 6),
          child: Row(
            children: [
              IconButton(
                icon: Icon(Icons.chevron_left_rounded,
                    color: canGoPrev
                        ? (isDark ? Colors.white : AppTheme.textPrimary)
                        : (isDark ? Colors.white24 : Colors.grey[400])),
                onPressed: canGoPrev
                    ? () {
                        setState(() => _selectedDayIndex = -1);
                        final prevWeek = weeks[currentIndex - 1] as Map<String, dynamic>;
                        provider.loadSchedule(weekId: prevWeek['id']?.toString());
                      }
                    : null,
              ),
              Expanded(
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    if (isLoading)
                      Padding(
                        padding: const EdgeInsets.only(right: 8),
                        child: SizedBox(
                          width: 14, height: 14,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: isDark ? Colors.white : AppTheme.primaryColor,
                          ),
                        ),
                      ),
                    Text(
                      weekLabel,
                      style: TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w700,
                        color: isDark ? Colors.white : AppTheme.textPrimary,
                      ),
                      textAlign: TextAlign.center,
                    ),
                  ],
                ),
              ),
              IconButton(
                icon: Icon(Icons.chevron_right_rounded,
                    color: canGoNext
                        ? (isDark ? Colors.white : AppTheme.textPrimary)
                        : (isDark ? Colors.white24 : Colors.grey[400])),
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
        ),
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
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
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
                margin: const EdgeInsets.symmetric(horizontal: 3),
                padding: const EdgeInsets.symmetric(vertical: 8),
                decoration: BoxDecoration(
                  gradient: isSelected
                      ? const LinearGradient(
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                          colors: [Color(0xFF7B2FF7), Color(0xFFE91E63)],
                        )
                      : null,
                  color: isSelected
                      ? null
                      : (isDark ? Colors.white.withOpacity(0.06) : Colors.white.withOpacity(0.7)),
                  borderRadius: BorderRadius.circular(14),
                  border: isToday && !isSelected
                      ? Border.all(color: const Color(0xFF7B2FF7), width: 1.5)
                      : null,
                ),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      shortName,
                      style: TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.w700,
                        color: isSelected
                            ? Colors.white.withOpacity(0.8)
                            : (isDark ? Colors.white54 : Colors.grey[600]),
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      '${day.day}',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w800,
                        color: isSelected
                            ? Colors.white
                            : (isDark ? Colors.white : AppTheme.textPrimary),
                      ),
                    ),
                    const SizedBox(height: 4),
                    Container(
                      width: 5,
                      height: 5,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: hasLessons
                            ? (isSelected ? Colors.white : const Color(0xFFE91E63))
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
    final dayName = _weekdayToUzName[date.weekday] ?? '';
    final dateStr = '${date.day.toString().padLeft(2, '0')}.${date.month.toString().padLeft(2, '0')}.${date.year}';

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            begin: Alignment.centerLeft,
            end: Alignment.centerRight,
            colors: [Color(0xFF4A3AFF), Color(0xFF7B2FF7), Color(0xFFE91E63)],
          ),
          borderRadius: BorderRadius.circular(16),
        ),
        child: Row(
          children: [
            const Icon(Icons.calendar_month_rounded, size: 18, color: Colors.white),
            const SizedBox(width: 8),
            Text(
              '$dayName, $dateStr',
              style: const TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w700,
                color: Colors.white,
              ),
            ),
            const Spacer(),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(
                color: Colors.white.withOpacity(0.2),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Text(
                '$lessonsCount para',
                style: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  color: Colors.white,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildTimelineLesson(
    Map<String, dynamic> lesson,
    int index,
    bool isLast,
    Color dotColor,
    bool isDark,
  ) {
    final startTime = lesson['lesson_pair_start_time']?.toString() ?? '';
    final endTime = lesson['lesson_pair_end_time']?.toString() ?? '';
    final subjectName = lesson['subject_name']?.toString() ?? '';
    final teacherName = lesson['employee_name']?.toString();
    final room = lesson['auditorium_name']?.toString();
    final trainingType = lesson['training_type_name']?.toString();

    final startShort = startTime.length >= 5 ? startTime.substring(0, 5) : startTime;
    final endShort = endTime.length >= 5 ? endTime.substring(0, 5) : endTime;

    Color typeBadgeColor;
    if (trainingType != null && (trainingType.toLowerCase().contains("ma'ruza") || trainingType.toLowerCase().contains('maruza'))) {
      typeBadgeColor = const Color(0xFFFF9800);
    } else {
      typeBadgeColor = const Color(0xFFE91E63);
    }

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: IntrinsicHeight(
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Timeline left side
            SizedBox(
              width: 50,
              child: Column(
                children: [
                  Text(
                    startShort,
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                      color: dotColor,
                    ),
                  ),
                  Text(
                    endShort,
                    style: TextStyle(
                      fontSize: 10,
                      color: isDark ? Colors.white38 : Colors.grey[500],
                    ),
                  ),
                ],
              ),
            ),

            // Timeline dot + line
            Column(
              children: [
                const SizedBox(height: 4),
                Container(
                  width: 12,
                  height: 12,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: dotColor,
                    boxShadow: [
                      BoxShadow(
                        color: dotColor.withOpacity(0.4),
                        blurRadius: 6,
                        spreadRadius: 1,
                      ),
                    ],
                  ),
                ),
                if (!isLast)
                  Expanded(
                    child: Container(
                      width: 2,
                      margin: const EdgeInsets.symmetric(vertical: 4),
                      color: isDark ? Colors.white.withOpacity(0.1) : Colors.grey.withOpacity(0.2),
                    ),
                  ),
              ],
            ),

            const SizedBox(width: 12),

            // Lesson card
            Expanded(
              child: Padding(
                padding: EdgeInsets.only(bottom: isLast ? 0 : 12),
                child: _buildGlassCard(
                  isDark: isDark,
                  borderRadius: 16,
                  cardColor: dotColor,
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: Text(
                                subjectName,
                                style: TextStyle(
                                  fontSize: 15,
                                  fontWeight: FontWeight.w700,
                                  color: isDark ? Colors.white : AppTheme.textPrimary,
                                ),
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                            if (trainingType != null) ...[
                              const SizedBox(width: 8),
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                decoration: BoxDecoration(
                                  color: typeBadgeColor.withOpacity(0.12),
                                  borderRadius: BorderRadius.circular(8),
                                  border: Border.all(color: typeBadgeColor.withOpacity(0.3)),
                                ),
                                child: Text(
                                  trainingType,
                                  style: TextStyle(
                                    fontSize: 10,
                                    fontWeight: FontWeight.w600,
                                    color: typeBadgeColor,
                                  ),
                                ),
                              ),
                            ],
                          ],
                        ),
                        const SizedBox(height: 8),
                        if (teacherName != null)
                          Padding(
                            padding: const EdgeInsets.only(bottom: 4),
                            child: Row(
                              children: [
                                Icon(Icons.person_outline_rounded, size: 14,
                                    color: isDark ? Colors.white54 : Colors.grey[600]),
                                const SizedBox(width: 4),
                                Expanded(
                                  child: Text(
                                    teacherName,
                                    style: TextStyle(
                                      fontSize: 12,
                                      color: isDark ? Colors.white54 : Colors.grey[600],
                                    ),
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        if (room != null && room.isNotEmpty)
                          Padding(
                            padding: const EdgeInsets.only(bottom: 4),
                            child: Row(
                              children: [
                                Icon(Icons.location_on_outlined, size: 14,
                                    color: isDark ? Colors.white54 : Colors.grey[600]),
                                const SizedBox(width: 4),
                                Text(
                                  room,
                                  style: TextStyle(
                                    fontSize: 12,
                                    color: isDark ? Colors.white54 : Colors.grey[600],
                                  ),
                                ),
                              ],
                            ),
                          ),
                        Row(
                          children: [
                            Icon(Icons.access_time_rounded, size: 14,
                                color: isDark ? Colors.white54 : Colors.grey[600]),
                            const SizedBox(width: 4),
                            Text(
                              '$startShort – $endShort',
                              style: TextStyle(
                                fontSize: 12,
                                color: isDark ? Colors.white54 : Colors.grey[600],
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildBlob(Color color) {
    return ImageFiltered(
      imageFilter: ImageFilter.blur(sigmaX: 24, sigmaY: 24),
      child: Container(
        width: 240,
        height: 240,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          gradient: RadialGradient(
            colors: [color, color.withOpacity(0)],
            stops: const [0.0, 0.7],
          ),
        ),
      ),
    );
  }
}
