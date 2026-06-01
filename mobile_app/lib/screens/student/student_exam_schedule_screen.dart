import 'dart:ui';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../services/api_service.dart';
import '../../services/student_data_cache.dart';
import '../../services/student_service.dart';
import '../../widgets/clinic_header.dart';

class ExamScheduleScreen extends StatefulWidget {
  const ExamScheduleScreen({super.key});

  @override
  State<ExamScheduleScreen> createState() => _ExamScheduleScreenState();
}

class _ExamScheduleScreenState extends State<ExamScheduleScreen>
    with SingleTickerProviderStateMixin {
  static const _oski = Color(0xFF1D4ED8);
  static const _test = Color(0xFF15803D);
  static const _past = Color(0xFFB45309);

  List<dynamic> _exams = [];
  bool _loading = true;
  DateTime _focusedMonth = DateTime.now();
  DateTime? _selectedDate;
  bool _showHint = true;
  late AnimationController _hintAnim;

  @override
  void initState() {
    super.initState();
    _hintAnim = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 300),
      value: 0.0,
    );
    _loadExams();
  }

  Future<void> _loadExams({bool force = false}) async {
    try {
      final res = await StudentDataCache().getOrFetch(
        key: StudentDataCache.kExamSchedule,
        fetcher: () => StudentService(ApiService()).getExamSchedule(),
        force: force,
      );
      if (mounted) {
        setState(() {
          _exams = (res?['data'] as List<dynamic>?) ?? [];
          _loading = false;
        });
        _showHintBanner();
      }
    } catch (_) {
      if (mounted) {
        setState(() => _loading = false);
        _showHintBanner();
      }
    }
  }

  void _showHintBanner() {
    if (!mounted || !_showHint) return;
    _hintAnim.forward();
    Future.delayed(const Duration(seconds: 3), _dismissHint);
  }

  void _dismissHint() {
    if (!mounted || !_showHint) return;
    _hintAnim.reverse().then((_) {
      if (mounted) setState(() => _showHint = false);
    });
  }

  @override
  void dispose() {
    _hintAnim.dispose();
    super.dispose();
  }

  List<dynamic> _examsForDate(String dateStr) =>
      _exams.where((e) => e['date']?.toString() == dateStr).toList();

  @override
  Widget build(BuildContext context) {
    final selectedDateStr = _selectedDate != null
        ? DateFormat('yyyy-MM-dd').format(_selectedDate!)
        : null;
    final selectedExams =
        selectedDateStr != null ? _examsForDate(selectedDateStr) : <dynamic>[];

    return Scaffold(
      backgroundColor: ClinicTheme.bgOf(context),
      body: Column(
        children: [
          ClinicHeader(
            overline: 'FOYDALI',
            title: 'Imtihon sanalari',
            onBack: () => Navigator.pop(context),
          ),
          Expanded(
            child: GestureDetector(
              onTap: () {
                if (_showHint) _dismissHint();
              },
              behavior: HitTestBehavior.translucent,
              child: _loading
                  ? const Center(child: CircularProgressIndicator())
                  : Stack(
                      children: [
                        Column(
                          children: [
                            Container(
                              margin: const EdgeInsets.fromLTRB(14, 14, 14, 0),
                              decoration: BoxDecoration(
                                color: ClinicTheme.surfaceOf(context),
                                borderRadius: BorderRadius.circular(16),
                                border: Border.all(
                                    color: ClinicTheme.dividerOf(context), width: 1),
                                boxShadow: ClinicTheme.cardShadow,
                              ),
                              child: Column(
                                children: [
                                  _buildMonthHeader(),
                                  _buildWeekDayHeaders(),
                                  _buildCalendarGrid(),
                                  const SizedBox(height: 8),
                                ],
                              ),
                            ),
                            const SizedBox(height: 12),
                            Padding(
                              padding: const EdgeInsets.symmetric(horizontal: 18),
                              child: Wrap(
                                spacing: 14,
                                runSpacing: 6,
                                children: [
                                  _legendDot(_oski, 'OSKI'),
                                  _legendDot(_test, 'Test'),
                                  _legendDot(_past, 'O\'tgan'),
                                ],
                              ),
                            ),
                            const SizedBox(height: 12),
                            Expanded(
                              child: RefreshIndicator(
                                onRefresh: () => _loadExams(force: true),
                                child: selectedExams.isEmpty
                                    ? ListView(
                                        padding: EdgeInsets.zero,
                                        children: [
                                          SizedBox(
                                            height: 200,
                                            child: Center(
                                              child: Text(
                                                _selectedDate == null
                                                    ? 'Sanani tanlang'
                                                    : 'Bu kunda imtihon yo\'q',
                                                style: TextStyle(
                                                    fontSize: 14,
                                                    color: ClinicTheme.mutedOf(context)),
                                              ),
                                            ),
                                          ),
                                        ],
                                      )
                                    : ListView.separated(
                                        padding:
                                            const EdgeInsets.fromLTRB(14, 0, 14, 24),
                                        itemCount: selectedExams.length,
                                        separatorBuilder: (_, __) =>
                                            const SizedBox(height: 10),
                                        itemBuilder: (_, i) =>
                                            _buildExamTile(selectedExams[i]),
                                      ),
                              ),
                            ),
                          ],
                        ),
                        if (_showHint)
                          Positioned.fill(
                            child: FadeTransition(
                              opacity: _hintAnim,
                              child: GestureDetector(
                                onTap: _dismissHint,
                                child: BackdropFilter(
                                  filter: ImageFilter.blur(sigmaX: 3, sigmaY: 3),
                                  child: Container(
                                    color: Colors.black.withOpacity(0.3),
                                    alignment: Alignment.center,
                                    child: GestureDetector(
                                      onTap: () {},
                                      child: Container(
                                        width: 260,
                                        padding: const EdgeInsets.symmetric(
                                            horizontal: 20, vertical: 26),
                                        decoration: BoxDecoration(
                                          color: ClinicTheme.surfaceOf(context),
                                          borderRadius: BorderRadius.circular(16),
                                          boxShadow: [
                                            BoxShadow(
                                              color: Colors.black.withOpacity(0.15),
                                              blurRadius: 24,
                                              offset: const Offset(0, 8),
                                            ),
                                          ],
                                        ),
                                        child: Column(
                                          mainAxisSize: MainAxisSize.min,
                                          children: [
                                            Container(
                                              width: 52,
                                              height: 52,
                                              decoration: const BoxDecoration(
                                                color: ClinicTheme.teal,
                                                shape: BoxShape.circle,
                                              ),
                                              child: const Icon(
                                                Icons.touch_app_rounded,
                                                color: Colors.white,
                                                size: 26,
                                              ),
                                            ),
                                            const SizedBox(height: 14),
                                            Text(
                                              'Belgilangan kunlarga\nbosib ko\'ring',
                                              textAlign: TextAlign.center,
                                              style: TextStyle(
                                                fontSize: 15,
                                                fontWeight: FontWeight.w800,
                                                color: ClinicTheme.inkOf(context),
                                                height: 1.4,
                                              ),
                                            ),
                                            const SizedBox(height: 6),
                                            Text(
                                              'Imtihon ma\'lumotlarini ko\'rish uchun',
                                              textAlign: TextAlign.center,
                                              style: TextStyle(
                                                fontSize: 12,
                                                color: ClinicTheme.mutedOf(context),
                                              ),
                                            ),
                                          ],
                                        ),
                                      ),
                                    ),
                                  ),
                                ),
                              ),
                            ),
                          ),
                      ],
                    ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildMonthHeader() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(14, 12, 6, 4),
      child: Row(
        children: [
          Text(
            DateFormat('MMMM yyyy').format(_focusedMonth),
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w800,
              color: ClinicTheme.inkOf(context),
            ),
          ),
          const Spacer(),
          IconButton(
            icon: Icon(Icons.chevron_left_rounded,
                color: ClinicTheme.mutedOf(context)),
            onPressed: () {
              setState(() {
                _focusedMonth =
                    DateTime(_focusedMonth.year, _focusedMonth.month - 1);
                _selectedDate = null;
              });
            },
          ),
          IconButton(
            icon: Icon(Icons.chevron_right_rounded,
                color: ClinicTheme.mutedOf(context)),
            onPressed: () {
              setState(() {
                _focusedMonth =
                    DateTime(_focusedMonth.year, _focusedMonth.month + 1);
                _selectedDate = null;
              });
            },
          ),
        ],
      ),
    );
  }

  Widget _buildWeekDayHeaders() {
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
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                        color: ClinicTheme.mutedOf(context),
                      ),
                    ),
                  ),
                ))
            .toList(),
      ),
    );
  }

  Widget _buildCalendarGrid() {
    final ink = ClinicTheme.inkOf(context);
    final muted = ClinicTheme.mutedOf(context);
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

    final today = DateTime(
        DateTime.now().year, DateTime.now().month, DateTime.now().day);

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
      final isPast = date.isBefore(today);

      Color? cellBg;
      if (isSelected) {
        cellBg = ClinicTheme.teal;
      } else if (hasExam && isPast) {
        cellBg = _past.withOpacity(0.14);
      } else if (hasOski && !isPast) {
        cellBg = _oski.withOpacity(0.12);
      } else if (hasTest && !isPast) {
        cellBg = _test.withOpacity(0.12);
      }

      cells.add(
        GestureDetector(
          onTap: () => setState(() => _selectedDate = date),
          child: Container(
            margin: const EdgeInsets.all(2),
            decoration: BoxDecoration(
              color: cellBg ?? Colors.transparent,
              borderRadius: BorderRadius.circular(10),
              border: isToday && !isSelected
                  ? Border.all(color: ClinicTheme.teal, width: 1.5)
                  : null,
            ),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(
                  '$day',
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: hasExam || isToday
                        ? FontWeight.w800
                        : FontWeight.w500,
                    color: isSelected
                        ? Colors.white
                        : hasExam
                            ? ink
                            : muted,
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
                                : isPast
                                    ? _past
                                    : _oski,
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
                                : isPast
                                    ? _past
                                    : _test,
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

  Widget _buildExamTile(dynamic exam) {
    final ink = ClinicTheme.inkOf(context);
    final muted = ClinicTheme.mutedOf(context);
    final subject = exam['subject_name']?.toString() ?? '';
    final examType = exam['exam_type']?.toString() ?? '';
    final timeStr = exam['time']?.toString() ?? '';
    final isOski = examType == 'OSKI';
    final dateStr = exam['date']?.toString() ?? '';
    String daysLeft = '';
    bool isPast = false;
    try {
      final date = DateTime.parse(dateStr);
      final now = DateTime(
          DateTime.now().year, DateTime.now().month, DateTime.now().day);
      final diff = date.difference(now).inDays;
      isPast = diff < 0;
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

    final color = isPast ? _past : (isOski ? _oski : _test);

    return Container(
      decoration: BoxDecoration(
        color: ClinicTheme.surfaceOf(context),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: ClinicTheme.dividerOf(context), width: 1),
        boxShadow: ClinicTheme.cardShadow,
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(14),
        child: IntrinsicHeight(
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Container(width: 4, color: color),
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.all(13),
                  child: Row(
                    children: [
                      Container(
                        width: 42,
                        height: 42,
                        decoration: BoxDecoration(
                          color: color,
                          borderRadius: BorderRadius.circular(11),
                        ),
                        child: Icon(
                          isOski
                              ? Icons.record_voice_over_rounded
                              : Icons.quiz_rounded,
                          color: Colors.white,
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
                                fontSize: 13.5,
                                fontWeight: FontWeight.w800,
                                color: ink,
                              ),
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                            ),
                            const SizedBox(height: 5),
                            Row(
                              children: [
                                Container(
                                  padding: const EdgeInsets.symmetric(
                                      horizontal: 7, vertical: 2),
                                  decoration: BoxDecoration(
                                    color: color,
                                    borderRadius: BorderRadius.circular(5),
                                  ),
                                  child: Text(
                                    examType,
                                    style: const TextStyle(
                                      fontSize: 10,
                                      fontWeight: FontWeight.w800,
                                      color: Colors.white,
                                    ),
                                  ),
                                ),
                                if (timeStr.isNotEmpty) ...[
                                  const SizedBox(width: 10),
                                  Icon(Icons.access_time_rounded,
                                      size: 13, color: muted),
                                  const SizedBox(width: 3),
                                  Text(
                                    timeStr,
                                    style: TextStyle(
                                      fontSize: 12,
                                      fontWeight: FontWeight.w700,
                                      color: ink,
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
                            fontWeight: FontWeight.w800,
                            color: color,
                          ),
                        ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _legendDot(Color color, String label) {
    return Row(
      mainAxisSize: MainAxisSize.min,
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
            fontWeight: FontWeight.w600,
            color: ClinicTheme.mutedOf(context),
          ),
        ),
      ],
    );
  }
}
