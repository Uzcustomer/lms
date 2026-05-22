import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/theme.dart';
import '../../providers/student_provider.dart';
import '../../l10n/app_localizations.dart';
import '../../widgets/loading_widget.dart';
import '../../widgets/settings_sheet.dart';
import '../../widgets/notification_bell.dart';
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

  // ── Clinic-calm palette ──────────────────────────────
  static const _calmInk = Color(0xFF0F172A);
  static const _calmMuted = Color(0xFF64748B);
  static const _calmFaint = Color(0xFF94A3B8);
  static const _calmTeal = Color(0xFF0D9488);
  static const _calmGreen = Color(0xFF047857);
  static const _calmLine = Color(0xFFE2E8F0);

  Color get _ink => Theme.of(context).brightness == Brightness.dark
      ? Colors.white
      : _calmInk;
  Color get _muted => Theme.of(context).brightness == Brightness.dark
      ? AppTheme.darkTextSecondary
      : _calmMuted;
  Color get _surface => Theme.of(context).brightness == Brightness.dark
      ? AppTheme.darkCard
      : Colors.white;
  Color get _divider => Theme.of(context).brightness == Brightness.dark
      ? Colors.white.withOpacity(0.08)
      : _calmLine;

  List<BoxShadow> get _cardShadow => [
        BoxShadow(
          color: const Color(0xFF0F172A).withOpacity(0.14),
          blurRadius: 5,
          offset: const Offset(0, 2),
        ),
      ];

  Widget _calmCard({required Widget child, EdgeInsets? padding, double radius = 14}) {
    return Container(
      width: double.infinity,
      padding: padding,
      decoration: BoxDecoration(
        color: _surface,
        borderRadius: BorderRadius.circular(radius),
        border: Border.all(color: _divider, width: 1),
        boxShadow: _cardShadow,
      ),
      child: child,
    );
  }

  /// White card with a coloured strip down its left edge.
  Widget _accentCard({required Color accent, required Widget child}) {
    return Container(
      decoration: BoxDecoration(
        color: _surface,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: _divider, width: 1),
        boxShadow: _cardShadow,
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(14),
        child: IntrinsicHeight(
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Container(width: 4, color: accent),
              Expanded(child: child),
            ],
          ),
        ),
      ),
    );
  }

  /// Bright accent colour for a lesson type.
  Color _typeColor(String? type) {
    final t = (type ?? '').toLowerCase();
    if (t.contains("ma'ruza") || t.contains('maruza')) return const Color(0xFF2563EB);
    if (t.contains('oski')) return const Color(0xFFEA580C);
    if (t.contains('seminar')) return const Color(0xFF7C3AED);
    if (t.contains('amaliy')) return const Color(0xFF059669);
    if (t.contains('laborator')) return const Color(0xFF0891B2);
    if (t.contains('test')) return const Color(0xFFDB2777);
    return const Color(0xFF4F46E5);
  }

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
    final dateStr =
        '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';

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

  int? _parseMinutes(String time) {
    final parts = time.split(':');
    if (parts.length < 2) return null;
    final h = int.tryParse(parts[0]);
    final m = int.tryParse(parts[1]);
    if (h == null || m == null) return null;
    return h * 60 + m;
  }

  String _formatDuration(int minutes) {
    final h = minutes ~/ 60;
    final m = minutes % 60;
    if (h > 0 && m > 0) return '$h soat $m daqiqa';
    if (h > 0) return '$h soat';
    return '$m daqiqa';
  }

  /// Sum of every lesson's start→end span, formatted.
  String _totalDuration(List<dynamic> lessons) {
    var total = 0;
    for (final l in lessons) {
      if (l is! Map<String, dynamic>) continue;
      final s = _parseMinutes(l['lesson_pair_start_time']?.toString() ?? '');
      final e = _parseMinutes(l['lesson_pair_end_time']?.toString() ?? '');
      if (s != null && e != null && e > s) total += e - s;
    }
    return _formatDuration(total);
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      backgroundColor: isDark ? AppTheme.darkBackground : Colors.white,
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
                  Icon(Icons.calendar_today_outlined, size: 64, color: _muted),
                  const SizedBox(height: 16),
                  Text(provider.error ?? l.scheduleNotFound,
                      style: TextStyle(color: _ink)),
                  const SizedBox(height: 16),
                  ElevatedButton(
                    onPressed: () => provider.loadSchedule(),
                    child: Text(l.reload),
                  ),
                ],
              ),
            );
          }

          final daysRaw = schedule['days'];
          final days = daysRaw is Map<String, dynamic> ? daysRaw : <String, dynamic>{};
          final dateSchedule = schedule['schedule'] as List<dynamic>? ?? [];
          final weeks = schedule['weeks'] as List<dynamic>? ?? [];
          final selectedWeekId = schedule['selected_week_id'];
          final weekLabel = schedule['week_label']?.toString() ?? '';
          final weekDays = _getWeekDaysFromApi(schedule);
          final currentWeekIndex = _findCurrentWeekIndex(weeks, selectedWeekId);
          final activeIndex = _getInitialSelectedIndex(weekDays);
          final selectedDate = weekDays[activeIndex];
          final selectedLessons =
              List<dynamic>.from(_getLessonsForDate(selectedDate, dateSchedule, days));
          // Sort lessons by start time so the timeline reads top-to-bottom.
          selectedLessons.sort((a, b) {
            final sa = _parseMinutes(
                    (a is Map ? a['lesson_pair_start_time'] : null)?.toString() ?? '') ??
                0;
            final sb = _parseMinutes(
                    (b is Map ? b['lesson_pair_start_time'] : null)?.toString() ?? '') ??
                0;
            return sa.compareTo(sb);
          });

          return RefreshIndicator(
            onRefresh: () => provider.loadSchedule(),
            child: ListView(
              padding: EdgeInsets.zero,
              children: [
                _buildHeader(context, l),
                Padding(
                  padding: const EdgeInsets.fromLTRB(14, 12, 14, 100),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _buildWeekNavigator(
                        weeks: weeks,
                        currentIndex: currentWeekIndex,
                        weekLabel: weekLabel,
                        provider: provider,
                      ),
                      const SizedBox(height: 12),
                      _buildDaySelector(
                        weekDays: weekDays,
                        activeIndex: activeIndex,
                        dateSchedule: dateSchedule,
                        days: days,
                      ),
                      const SizedBox(height: 12),
                      _buildSelectedDayHeader(
                        date: selectedDate,
                        lessons: selectedLessons,
                      ),
                      const SizedBox(height: 12),
                      if (selectedLessons.isEmpty)
                        _buildEmptyState(l)
                      else
                        ...selectedLessons.asMap().entries.map((entry) {
                          final index = entry.key;
                          final lesson = entry.value as Map<String, dynamic>;
                          final isLast = index == selectedLessons.length - 1;
                          return _buildTimelineLesson(
                            lesson,
                            index,
                            isLast,
                            _isSameDay(selectedDate, DateTime.now()),
                          );
                        }),
                    ],
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }

  // ── Header ───────────────────────────────────────────
  Widget _buildHeader(BuildContext context, AppLocalizations l) {
    final statusBarH = MediaQuery.of(context).padding.top;
    return Container(
      padding: EdgeInsets.fromLTRB(14, statusBarH + 10, 14, 12),
      decoration: BoxDecoration(
        color: _surface,
        border: Border(bottom: BorderSide(color: _divider, width: 1)),
      ),
      child: Row(
        children: [
          _headerIconButton(
            child: IconButton(
              padding: EdgeInsets.zero,
              icon: Icon(Icons.arrow_back_rounded, color: _ink, size: 20),
              onPressed: () => StudentHomeScreen.switchToHome(context),
            ),
          ),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'JADVAL',
                  style: TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.w600,
                    letterSpacing: 0.5,
                    color: _muted,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  'Haftalik dars jadvali',
                  style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: _ink),
                ),
              ],
            ),
          ),
          _headerIconButton(child: NotificationBell(iconColor: _ink, iconSize: 18)),
          const SizedBox(width: 8),
          _headerIconButton(
            child: IconButton(
              padding: EdgeInsets.zero,
              icon: Icon(Icons.settings_outlined, color: _ink, size: 18),
              onPressed: () => showSettingsSheet(context),
            ),
          ),
        ],
      ),
    );
  }

  Widget _headerIconButton({required Widget child}) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Container(
      width: 38,
      height: 38,
      decoration: BoxDecoration(
        color: isDark ? Colors.white.withOpacity(0.06) : const Color(0xFFF1F5F9),
        borderRadius: BorderRadius.circular(11),
      ),
      child: child,
    );
  }

  // ── Week navigator ───────────────────────────────────
  Widget _buildWeekNavigator({
    required List<dynamic> weeks,
    required int currentIndex,
    required String weekLabel,
    required StudentProvider provider,
  }) {
    final isLoading = provider.isLoading;
    final canGoPrev = !isLoading && currentIndex > 0;
    final canGoNext =
        !isLoading && currentIndex >= 0 && currentIndex < weeks.length - 1;

    return _calmCard(
      padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 4),
      child: Row(
        children: [
          _navArrow(
            icon: Icons.chevron_left_rounded,
            enabled: canGoPrev,
            onTap: () {
              setState(() => _selectedDayIndex = -1);
              final prevWeek = weeks[currentIndex - 1] as Map<String, dynamic>;
              provider.loadSchedule(weekId: prevWeek['id']?.toString());
            },
          ),
          Expanded(
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                if (isLoading)
                  Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: SizedBox(
                      width: 14,
                      height: 14,
                      child: CircularProgressIndicator(strokeWidth: 2, color: _calmTeal),
                    ),
                  ),
                Flexible(
                  child: Text(
                    weekLabel,
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                      color: _ink,
                    ),
                    textAlign: TextAlign.center,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ],
            ),
          ),
          _navArrow(
            icon: Icons.chevron_right_rounded,
            enabled: canGoNext,
            onTap: () {
              setState(() => _selectedDayIndex = -1);
              final nextWeek = weeks[currentIndex + 1] as Map<String, dynamic>;
              provider.loadSchedule(weekId: nextWeek['id']?.toString());
            },
          ),
        ],
      ),
    );
  }

  Widget _navArrow({
    required IconData icon,
    required bool enabled,
    required VoidCallback onTap,
  }) {
    return GestureDetector(
      onTap: enabled ? onTap : null,
      child: Container(
        width: 36,
        height: 36,
        decoration: BoxDecoration(
          color: enabled ? _calmTeal : _divider,
          borderRadius: BorderRadius.circular(9),
        ),
        child: Icon(icon, size: 22, color: enabled ? Colors.white : _calmFaint),
      ),
    );
  }

  // ── Day selector ─────────────────────────────────────
  Widget _buildDaySelector({
    required List<DateTime> weekDays,
    required int activeIndex,
    required List<dynamic> dateSchedule,
    required Map<String, dynamic> days,
  }) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Row(
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
              margin: EdgeInsets.only(right: i < weekDays.length - 1 ? 7 : 0),
              padding: const EdgeInsets.symmetric(vertical: 9),
              decoration: BoxDecoration(
                color: isSelected
                    ? _calmTeal
                    : (isDark ? Colors.white.withOpacity(0.04) : Colors.white),
                borderRadius: BorderRadius.circular(13),
                border: Border.all(
                  color: isSelected
                      ? _calmTeal
                      : (isToday ? _calmTeal : _divider),
                  width: isToday && !isSelected ? 1.5 : 1,
                ),
                boxShadow: isSelected ? _cardShadow : null,
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    shortName,
                    style: TextStyle(
                      fontSize: 9.5,
                      fontWeight: FontWeight.w700,
                      letterSpacing: 0.3,
                      color: isSelected ? Colors.white.withOpacity(0.85) : _muted,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    '${day.day}',
                    style: TextStyle(
                      fontSize: 17,
                      fontWeight: FontWeight.w900,
                      color: isSelected ? Colors.white : _ink,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Container(
                    width: 5,
                    height: 5,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: hasLessons
                          ? (isSelected ? Colors.white : _calmGreen)
                          : Colors.transparent,
                    ),
                  ),
                ],
              ),
            ),
          ),
        );
      }).toList(),
    );
  }

  // ── Selected-day header ──────────────────────────────
  Widget _buildSelectedDayHeader({
    required DateTime date,
    required List<dynamic> lessons,
  }) {
    final dayName = _weekdayToUzName[date.weekday] ?? '';
    final dateStr =
        '${date.day.toString().padLeft(2, '0')}.${date.month.toString().padLeft(2, '0')}.${date.year}';
    final isToday = _isSameDay(date, DateTime.now());

    return _accentCard(
      accent: _calmTeal,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
        child: Row(
          children: [
            Container(
              width: 36,
              height: 36,
              decoration: BoxDecoration(
                color: _calmTeal,
                borderRadius: BorderRadius.circular(10),
              ),
              child: const Icon(Icons.calendar_month_rounded, size: 20, color: Colors.white),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '$dayName, $dateStr',
                    style: TextStyle(
                        fontSize: 13.5, fontWeight: FontWeight.w800, color: _ink),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    lessons.isEmpty
                        ? 'Dars yo\'q'
                        : '${lessons.length} ta para · ${_totalDuration(lessons)}',
                    style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.w500, color: _muted),
                  ),
                ],
              ),
            ),
            if (isToday)
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
                decoration: BoxDecoration(
                  color: _calmGreen,
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 6,
                      height: 6,
                      decoration: const BoxDecoration(
                          color: Colors.white, shape: BoxShape.circle),
                    ),
                    const SizedBox(width: 5),
                    const Text(
                      'HOZIR',
                      style: TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.w800,
                        letterSpacing: 0.3,
                        color: Colors.white,
                      ),
                    ),
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildEmptyState(AppLocalizations l) {
    return _calmCard(
      padding: const EdgeInsets.symmetric(vertical: 44),
      child: Center(
        child: Column(
          children: [
            Icon(Icons.event_busy_outlined, size: 46, color: _calmFaint),
            const SizedBox(height: 12),
            Text(
              l.noLessons,
              style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: _muted),
            ),
          ],
        ),
      ),
    );
  }

  // ── Timeline lesson ──────────────────────────────────
  Widget _buildTimelineLesson(
    Map<String, dynamic> lesson,
    int index,
    bool isLast,
    bool isToday,
  ) {
    final startTime = lesson['lesson_pair_start_time']?.toString() ?? '';
    final endTime = lesson['lesson_pair_end_time']?.toString() ?? '';
    final rawName = lesson['subject_name']?.toString() ?? '';
    final teacherName = lesson['employee_name']?.toString();
    final room = lesson['auditorium_name']?.toString();
    final trainingType = lesson['training_type_name']?.toString();

    final startShort = startTime.length >= 5 ? startTime.substring(0, 5) : startTime;
    final endShort = endTime.length >= 5 ? endTime.substring(0, 5) : endTime;

    // Split a compound subject name into a title and a subtitle line.
    String title = rawName;
    String? subtitle;
    final dotIdx = rawName.indexOf('. ');
    if (dotIdx > 0 && dotIdx < rawName.length - 2) {
      title = rawName.substring(0, dotIdx);
      subtitle = rawName.substring(dotIdx + 2);
    }

    final accent = _typeColor(trainingType);

    // Is this lesson happening right now?
    var isCurrent = false;
    if (isToday) {
      final s = _parseMinutes(startTime);
      final e = _parseMinutes(endTime);
      final now = DateTime.now();
      final nowMin = now.hour * 60 + now.minute;
      if (s != null && e != null && nowMin >= s && nowMin < e) isCurrent = true;
    }

    final card = Padding(
      padding: const EdgeInsets.all(13),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              if (trainingType != null && trainingType.isNotEmpty)
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 3),
                  decoration: BoxDecoration(
                    color: accent,
                    borderRadius: BorderRadius.circular(7),
                  ),
                  child: Text(
                    trainingType,
                    style: const TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.w800,
                      color: Colors.white,
                    ),
                  ),
                ),
              const Spacer(),
              Text(
                'PARA ${index + 1}',
                style: TextStyle(
                  fontSize: 9.5,
                  fontWeight: FontWeight.w800,
                  letterSpacing: 0.4,
                  color: _calmFaint,
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            title,
            style: TextStyle(fontSize: 14.5, fontWeight: FontWeight.w800, color: _ink),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
          ),
          if (subtitle != null) ...[
            const SizedBox(height: 2),
            Text(
              subtitle,
              style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.w500, color: _muted),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
          ],
          const SizedBox(height: 8),
          Row(
            children: [
              if (room != null && room.isNotEmpty) ...[
                Icon(Icons.location_on_outlined, size: 13, color: _muted),
                const SizedBox(width: 3),
                Flexible(
                  child: Text(
                    room,
                    style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.w600, color: _muted),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ],
              if (room != null && room.isNotEmpty && teacherName != null && teacherName.isNotEmpty)
                Text(' · ', style: TextStyle(fontSize: 11.5, color: _calmFaint)),
              if (teacherName != null && teacherName.isNotEmpty) ...[
                Icon(Icons.person_outline_rounded, size: 13, color: _muted),
                const SizedBox(width: 3),
                Flexible(
                  child: Text(
                    teacherName,
                    style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.w600, color: _muted),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ],
            ],
          ),
        ],
      ),
    );

    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Time column
          SizedBox(
            width: 46,
            child: Padding(
              padding: const EdgeInsets.only(top: 2),
              child: Column(
                children: [
                  Text(
                    startShort,
                    style: TextStyle(
                        fontSize: 12.5, fontWeight: FontWeight.w800, color: _ink),
                  ),
                  const SizedBox(height: 1),
                  Text(
                    endShort,
                    style: TextStyle(fontSize: 10, fontWeight: FontWeight.w600, color: _calmFaint),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(width: 8),
          // Timeline dot + line
          Column(
            children: [
              const SizedBox(height: 3),
              Container(
                width: isCurrent ? 14 : 12,
                height: isCurrent ? 14 : 12,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: isCurrent ? _calmGreen : _surface,
                  border: Border.all(
                    color: isCurrent ? _calmGreen : accent,
                    width: isCurrent ? 0 : 2.4,
                  ),
                  boxShadow: isCurrent
                      ? [
                          BoxShadow(
                            color: _calmGreen.withOpacity(0.4),
                            blurRadius: 6,
                            spreadRadius: 1,
                          ),
                        ]
                      : null,
                ),
              ),
              if (!isLast)
                Expanded(
                  child: Container(
                    width: 2,
                    margin: const EdgeInsets.symmetric(vertical: 3),
                    color: _divider,
                  ),
                ),
            ],
          ),
          const SizedBox(width: 11),
          // Lesson card
          Expanded(
            child: Padding(
              padding: EdgeInsets.only(bottom: isLast ? 0 : 12),
              child: isCurrent
                  ? _accentCard(accent: _calmGreen, child: card)
                  : _calmCard(child: card),
            ),
          ),
        ],
      ),
    );
  }
}
