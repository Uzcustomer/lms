import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../config/theme.dart';
import '../../services/api_service.dart';
import '../../services/student_service.dart';

class ExamScheduleScreen extends StatefulWidget {
  const ExamScheduleScreen({super.key});

  @override
  State<ExamScheduleScreen> createState() => _ExamScheduleScreenState();
}

class _ExamScheduleScreenState extends State<ExamScheduleScreen> {
  List<dynamic> _exams = [];
  bool _loading = true;
  DateTime _focusedMonth = DateTime.now();
  DateTime? _selectedDate;

  @override
  void initState() {
    super.initState();
    _loadExams();
  }

  Future<void> _loadExams() async {
    try {
      final api = ApiService();
      final service = StudentService(api);
      final response = await service.getExamSchedule();
      if (mounted && response['success'] == true) {
        setState(() {
          _exams = response['data'] as List<dynamic>? ?? [];
          _loading = false;
        });
      } else {
        if (mounted) setState(() => _loading = false);
      }
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  Set<String> get _examDates =>
      _exams.map((e) => e['date']?.toString() ?? '').toSet();

  List<dynamic> _examsForDate(String dateStr) =>
      _exams.where((e) => e['date']?.toString() == dateStr).toList();

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bgColor = isDark ? AppTheme.darkBackground : const Color(0xFFF5F7FB);
    final cardColor = isDark ? AppTheme.darkCard : Colors.white;
    final textColor = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;
    final subColor = isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;

    final selectedDateStr = _selectedDate != null
        ? DateFormat('yyyy-MM-dd').format(_selectedDate!)
        : null;
    final selectedExams =
        selectedDateStr != null ? _examsForDate(selectedDateStr) : <dynamic>[];

    return Scaffold(
      backgroundColor: bgColor,
      appBar: AppBar(
        title: const Text('Imtihon sanalari'),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : Column(
              children: [
                // Calendar
                Container(
                  margin: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                  decoration: BoxDecoration(
                    color: cardColor,
                    borderRadius: BorderRadius.circular(16),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withOpacity(0.04),
                        blurRadius: 10,
                        offset: const Offset(0, 2),
                      ),
                    ],
                  ),
                  child: Column(
                    children: [
                      _buildMonthHeader(textColor, subColor),
                      _buildWeekDayHeaders(subColor),
                      _buildCalendarGrid(textColor, subColor, isDark),
                      const SizedBox(height: 8),
                    ],
                  ),
                ),

                const SizedBox(height: 12),

                // Legend
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 20),
                  child: Row(
                    children: [
                      _legendDot(const Color(0xFFE53935), 'OSKI'),
                      const SizedBox(width: 16),
                      _legendDot(const Color(0xFF4A6CF7), 'Test'),
                    ],
                  ),
                ),

                const SizedBox(height: 12),

                // Selected date exams
                Expanded(
                  child: selectedExams.isEmpty
                      ? Center(
                          child: Text(
                            _selectedDate == null
                                ? 'Sanani tanlang'
                                : 'Bu kunda imtihon yo\'q',
                            style: TextStyle(fontSize: 14, color: subColor),
                          ),
                        )
                      : ListView.separated(
                          padding: const EdgeInsets.symmetric(horizontal: 16),
                          itemCount: selectedExams.length,
                          separatorBuilder: (_, __) =>
                              const SizedBox(height: 10),
                          itemBuilder: (_, i) => _buildExamTile(
                              selectedExams[i], textColor, subColor, isDark,
                              cardColor),
                        ),
                ),
              ],
            ),
    );
  }

  Widget _buildMonthHeader(Color textColor, Color subColor) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 8, 4),
      child: Row(
        children: [
          Text(
            DateFormat('MMMM yyyy').format(_focusedMonth),
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.bold,
              color: textColor,
            ),
          ),
          const Spacer(),
          IconButton(
            icon: Icon(Icons.chevron_left_rounded, color: subColor),
            onPressed: () {
              setState(() {
                _focusedMonth = DateTime(
                    _focusedMonth.year, _focusedMonth.month - 1);
                _selectedDate = null;
              });
            },
          ),
          IconButton(
            icon: Icon(Icons.chevron_right_rounded, color: subColor),
            onPressed: () {
              setState(() {
                _focusedMonth = DateTime(
                    _focusedMonth.year, _focusedMonth.month + 1);
                _selectedDate = null;
              });
            },
          ),
        ],
      ),
    );
  }

  Widget _buildWeekDayHeaders(Color subColor) {
    const days = ['Du', 'Se', 'Cho', 'Pa', 'Ju', 'Sha', 'Ya'];
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 12),
      child: Row(
        children: days
            .map((d) => Expanded(
                  child: Center(
                    child: Text(
                      d,
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                        color: subColor,
                      ),
                    ),
                  ),
                ))
            .toList(),
      ),
    );
  }

  Widget _buildCalendarGrid(Color textColor, Color subColor, bool isDark) {
    final year = _focusedMonth.year;
    final month = _focusedMonth.month;
    final firstDay = DateTime(year, month, 1);
    final lastDay = DateTime(year, month + 1, 0);
    final startWeekday = (firstDay.weekday - 1) % 7;
    final todayStr = DateFormat('yyyy-MM-dd').format(DateTime.now());

    final cells = <Widget>[];

    for (int i = 0; i < startWeekday; i++) {
      cells.add(const SizedBox());
    }

    for (int day = 1; day <= lastDay.day; day++) {
      final date = DateTime(year, month, day);
      final dateStr = DateFormat('yyyy-MM-dd').format(date);
      final isToday = dateStr == todayStr;
      final isSelected = _selectedDate != null &&
          DateFormat('yyyy-MM-dd').format(_selectedDate!) == dateStr;
      final dayExams = _examsForDate(dateStr);
      final hasOski = dayExams.any((e) => e['exam_type'] == 'OSKI');
      final hasTest = dayExams.any((e) => e['exam_type'] == 'Test');
      final hasExam = dayExams.isNotEmpty;

      cells.add(
        GestureDetector(
          onTap: () => setState(() => _selectedDate = date),
          child: Container(
            margin: const EdgeInsets.all(2),
            decoration: BoxDecoration(
              color: isSelected
                  ? const Color(0xFF4A6CF7)
                  : isToday
                      ? const Color(0xFF4A6CF7).withOpacity(0.08)
                      : Colors.transparent,
              borderRadius: BorderRadius.circular(10),
              border: isToday && !isSelected
                  ? Border.all(color: const Color(0xFF4A6CF7), width: 1.5)
                  : null,
            ),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(
                  '$day',
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight:
                        hasExam || isToday ? FontWeight.w700 : FontWeight.w400,
                    color: isSelected
                        ? Colors.white
                        : hasExam
                            ? textColor
                            : subColor,
                  ),
                ),
                if (hasExam)
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      if (hasOski)
                        Container(
                          width: 5,
                          height: 5,
                          margin: const EdgeInsets.only(top: 2, right: 1),
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            color: isSelected
                                ? Colors.white
                                : const Color(0xFFE53935),
                          ),
                        ),
                      if (hasTest)
                        Container(
                          width: 5,
                          height: 5,
                          margin: const EdgeInsets.only(top: 2, left: 1),
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            color: isSelected
                                ? Colors.white
                                : const Color(0xFF4A6CF7),
                          ),
                        ),
                    ],
                  ),
              ],
            ),
          ),
        ),
      );
    }

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 12),
      child: GridView.count(
        crossAxisCount: 7,
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        childAspectRatio: 1.1,
        children: cells,
      ),
    );
  }

  Widget _buildExamTile(dynamic exam, Color textColor, Color subColor,
      bool isDark, Color cardColor) {
    final subject = exam['subject_name']?.toString() ?? '';
    final examType = exam['exam_type']?.toString() ?? '';
    final timeStr = exam['time']?.toString() ?? '';
    final isOski = examType == 'OSKI';
    final color = isOski ? const Color(0xFFE53935) : const Color(0xFF4A6CF7);

    final dateStr = exam['date']?.toString() ?? '';
    String daysLeft = '';
    try {
      final date = DateTime.parse(dateStr);
      final now = DateTime(DateTime.now().year, DateTime.now().month, DateTime.now().day);
      final diff = date.difference(now).inDays;
      if (diff == 0) {
        daysLeft = 'Bugun';
      } else if (diff == 1) {
        daysLeft = 'Ertaga';
      } else if (diff > 0) {
        daysLeft = '$diff kun qoldi';
      } else {
        daysLeft = 'O\'tgan';
      }
    } catch (_) {}

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: cardColor,
        borderRadius: BorderRadius.circular(14),
        border: Border(
          left: BorderSide(color: color, width: 3.5),
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.03),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: color.withOpacity(isDark ? 0.2 : 0.1),
              borderRadius: BorderRadius.circular(11),
            ),
            child: Icon(
              isOski ? Icons.record_voice_over_rounded : Icons.quiz_rounded,
              color: color,
              size: 20,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  subject,
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    color: textColor,
                  ),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 4),
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 7, vertical: 2),
                      decoration: BoxDecoration(
                        color: color.withOpacity(0.12),
                        borderRadius: BorderRadius.circular(5),
                      ),
                      child: Text(
                        examType,
                        style: TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.w700,
                          color: color,
                        ),
                      ),
                    ),
                    if (timeStr.isNotEmpty) ...[
                      const SizedBox(width: 10),
                      Icon(Icons.access_time_rounded,
                          size: 13, color: subColor),
                      const SizedBox(width: 3),
                      Text(
                        timeStr,
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w500,
                          color: textColor,
                        ),
                      ),
                    ],
                  ],
                ),
              ],
            ),
          ),
          if (daysLeft.isNotEmpty)
            Text(
              daysLeft,
              style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w600,
                color: color,
              ),
            ),
        ],
      ),
    );
  }

  Widget _legendDot(Color color, String label) {
    return Row(
      children: [
        Container(
          width: 8,
          height: 8,
          decoration: BoxDecoration(shape: BoxShape.circle, color: color),
        ),
        const SizedBox(width: 5),
        Text(
          label,
          style: TextStyle(
            fontSize: 12,
            color: Theme.of(context).brightness == Brightness.dark
                ? AppTheme.darkTextSecondary
                : AppTheme.textSecondary,
          ),
        ),
      ],
    );
  }
}
