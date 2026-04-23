import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/theme.dart';
import '../../providers/student_provider.dart';
import '../../services/api_service.dart';
import '../../services/student_service.dart';

class AttendanceStatsScreen extends StatefulWidget {
  const AttendanceStatsScreen({super.key});

  @override
  State<AttendanceStatsScreen> createState() => _AttendanceStatsScreenState();
}

class _AttendanceStatsScreenState extends State<AttendanceStatsScreen> {
  List<dynamic> _subjects = [];
  bool _loadingSubjects = true;
  int? _selectedSubjectId;
  String _selectedSubjectName = '';

  List<dynamic> _grades = [];
  List<dynamic> _scheduleDates = [];
  bool _loadingGrades = false;

  @override
  void initState() {
    super.initState();
    _loadSubjects();
  }

  Future<void> _loadSubjects() async {
    final provider = context.read<StudentProvider>();
    if (provider.subjects != null && provider.subjects!.isNotEmpty) {
      setState(() {
        _subjects = provider.subjects!;
        _loadingSubjects = false;
      });
      return;
    }
    try {
      await provider.loadSubjects();
      if (mounted) {
        setState(() {
          _subjects = provider.subjects ?? [];
          _loadingSubjects = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _loadingSubjects = false);
    }
  }

  Future<void> _loadGrades(int subjectId) async {
    setState(() => _loadingGrades = true);
    try {
      final api = ApiService();
      final service = StudentService(api);
      final res = await service.getSubjectGrades(subjectId);
      if (mounted) {
        final data = res['data'] as Map<String, dynamic>? ?? {};
        setState(() {
          _grades = (data['grades'] as List<dynamic>? ?? []);
          _scheduleDates = (data['schedule_dates'] as List<dynamic>? ?? []);
          _loadingGrades = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _loadingGrades = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bg = isDark ? AppTheme.darkBackground : const Color(0xFFF0F4F8);
    final txt = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;
    final sub = isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;

    return Scaffold(
      backgroundColor: bg,
      appBar: AppBar(title: const Text('Davomat statistikasi')),
      body: _loadingSubjects
          ? const Center(child: CircularProgressIndicator())
          : _subjects.isEmpty
              ? Center(
                  child: Text('Fanlar topilmadi',
                      style: TextStyle(color: sub, fontSize: 15)))
              : Column(
                  children: [
                    _buildSubjectDropdown(isDark, txt, sub),
                    if (_selectedSubjectId != null)
                      Expanded(
                        child: _loadingGrades
                            ? const Center(child: CircularProgressIndicator())
                            : _buildGradeTable(isDark, txt, sub),
                      ),
                    if (_selectedSubjectId == null)
                      Expanded(
                        child: Center(
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(Icons.touch_app_outlined,
                                  size: 48, color: sub.withOpacity(0.5)),
                              const SizedBox(height: 12),
                              Text('Fanni tanlang',
                                  style: TextStyle(
                                      color: sub,
                                      fontSize: 15,
                                      fontWeight: FontWeight.w500)),
                            ],
                          ),
                        ),
                      ),
                  ],
                ),
    );
  }

  Widget _buildSubjectDropdown(bool isDark, Color txt, Color sub) {
    final cardBg = isDark ? AppTheme.darkCard : Colors.white;
    final borderColor = isDark ? Colors.white12 : Colors.grey.shade300;

    return Container(
      margin: const EdgeInsets.fromLTRB(14, 12, 14, 8),
      padding: const EdgeInsets.symmetric(horizontal: 14),
      decoration: BoxDecoration(
        color: cardBg,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: borderColor),
      ),
      child: DropdownButtonHideUnderline(
        child: DropdownButton<int>(
          value: _selectedSubjectId,
          hint: Text('Fanni tanlang',
              style: TextStyle(color: sub, fontSize: 14)),
          isExpanded: true,
          icon: Icon(Icons.keyboard_arrow_down_rounded, color: sub),
          dropdownColor: cardBg,
          borderRadius: BorderRadius.circular(14),
          style: TextStyle(fontSize: 14, color: txt),
          items: _subjects.map<DropdownMenuItem<int>>((s) {
            final id = s['subject_id'] as int? ?? 0;
            final name = s['subject_name']?.toString() ?? '';
            final davPercent = _toDouble(s['dav_percent']);
            final hasAbsence = davPercent > 0;

            return DropdownMenuItem<int>(
              value: id,
              child: Row(
                children: [
                  Expanded(
                    child: Text(name,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                            fontSize: 13.5,
                            fontWeight: _selectedSubjectId == id
                                ? FontWeight.w600
                                : FontWeight.w400,
                            color: txt)),
                  ),
                  if (hasAbsence)
                    Container(
                      margin: const EdgeInsets.only(left: 8),
                      padding: const EdgeInsets.symmetric(
                          horizontal: 6, vertical: 2),
                      decoration: BoxDecoration(
                        color: AppTheme.errorColor.withOpacity(0.1),
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: Text('${davPercent.toStringAsFixed(0)}%',
                          style: const TextStyle(
                              fontSize: 10,
                              fontWeight: FontWeight.w600,
                              color: AppTheme.errorColor)),
                    ),
                ],
              ),
            );
          }).toList(),
          onChanged: (id) {
            if (id == null) return;
            final s = _subjects.firstWhere(
                (s) => (s['subject_id'] as int? ?? 0) == id,
                orElse: () => null);
            setState(() {
              _selectedSubjectId = id;
              _selectedSubjectName = s?['subject_name']?.toString() ?? '';
            });
            _loadGrades(id);
          },
        ),
      ),
    );
  }

  Widget _buildGradeTable(bool isDark, Color txt, Color sub) {
    final borderColor = isDark ? AppTheme.darkDivider : const Color(0xFFE0E0E0);
    final cellBg = isDark ? AppTheme.darkCard : Colors.white;

    final dayRows = _buildDayRows();

    if (dayRows.isEmpty) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.event_note_outlined,
                size: 48, color: sub.withOpacity(0.5)),
            const SizedBox(height: 12),
            Text('Ma\'lumot topilmadi',
                style: TextStyle(color: sub, fontSize: 14)),
          ],
        ),
      );
    }

    int totalDays = 0;
    int absentDays = 0;
    int retakeDays = 0;
    for (final day in dayRows) {
      if (day['type'] == 'no_data') continue;
      totalDays++;
      if (day['type'] == 'absent') absentDays++;
      if (day['type'] == 'retake') retakeDays++;
    }
    final attendedDays = totalDays - absentDays;

    return Column(
      children: [
        _buildStatsRow(
            totalDays, attendedDays, absentDays, retakeDays, isDark, txt),
        Expanded(
          child: ListView.builder(
            padding: const EdgeInsets.fromLTRB(14, 0, 14, 20),
            itemCount: dayRows.length,
            itemBuilder: (_, i) =>
                _buildDayRow(dayRows[i], borderColor, cellBg, txt, sub, isDark),
          ),
        ),
      ],
    );
  }

  Widget _buildStatsRow(int total, int attended, int absent, int retake,
      bool isDark, Color txt) {
    final percent = total > 0 ? (attended / total * 100) : 100.0;
    final percentColor = percent >= 85
        ? AppTheme.successColor
        : percent >= 70
            ? AppTheme.warningColor
            : AppTheme.errorColor;

    return Container(
      margin: const EdgeInsets.fromLTRB(14, 4, 14, 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [
            percentColor.withOpacity(0.1),
            percentColor.withOpacity(0.05)
          ],
        ),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: percentColor.withOpacity(0.2)),
      ),
      child: Row(
        children: [
          Container(
            width: 50,
            height: 50,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: percentColor.withOpacity(0.15),
            ),
            child: Center(
              child: Text(
                '${percent.round()}%',
                style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w800,
                    color: percentColor),
              ),
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(_selectedSubjectName,
                    style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                        color: txt),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis),
                const SizedBox(height: 4),
                Wrap(
                  spacing: 6,
                  runSpacing: 4,
                  children: [
                    _statChip('Jami', '$total', Colors.blueGrey),
                    _statChip('Bor', '$attended', AppTheme.successColor),
                    _statChip('NB', '$absent', AppTheme.errorColor),
                    if (retake > 0)
                      _statChip('Retake', '$retake', AppTheme.warningColor),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _statChip(String label, String value, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: color.withOpacity(0.12),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text('$label: $value',
          style: TextStyle(
              fontSize: 10.5, fontWeight: FontWeight.w600, color: color)),
    );
  }

  List<Map<String, dynamic>> _buildDayRows() {
    final gradeMap = <String, List<Map<String, dynamic>>>{};

    for (final g in _grades) {
      final dateRaw = g['lesson_date']?.toString() ?? '';
      if (dateRaw.length < 10) continue;
      final dateKey = dateRaw.substring(0, 10);
      final ttCode = g['training_type_code'];
      if (ttCode == 99 || ttCode == 100 || ttCode == 101 || ttCode == 102) {
        continue;
      }
      gradeMap
          .putIfAbsent(dateKey, () => [])
          .add(Map<String, dynamic>.from(g));
    }

    final dateSet = <String>{};
    for (final s in _scheduleDates) {
      final dateRaw = s['lesson_date']?.toString() ?? '';
      if (dateRaw.length < 10) continue;
      final ttCode = s['training_type_code'];
      if (ttCode == 99 || ttCode == 100 || ttCode == 101 || ttCode == 102) {
        continue;
      }
      dateSet.add(dateRaw.substring(0, 10));
    }
    for (final k in gradeMap.keys) {
      dateSet.add(k);
    }

    final sortedDates = dateSet.toList()..sort();
    final dayRows = <Map<String, dynamic>>[];

    for (final dateKey in sortedDates) {
      final dayGrades = gradeMap[dateKey];

      if (dayGrades == null || dayGrades.isEmpty) {
        dayRows.add({
          'date': dateKey,
          'type': 'no_data',
          'grade': null,
          'retake_grade': null,
        });
        continue;
      }

      final gradeValues = <num>[];
      bool hasAbsent = false;
      bool hasRetake = false;
      num? retakeVal;

      for (final g in dayGrades) {
        final reason = g['reason']?.toString();
        final status = g['status']?.toString();
        final grade = g['grade'];
        final retakeGrade = g['retake_grade'];

        if (reason == 'absent' && (grade == null || grade == 0)) {
          hasAbsent = true;
          if (retakeGrade != null && retakeGrade is num && retakeGrade > 0) {
            hasRetake = true;
            retakeVal = retakeGrade;
          }
        } else if (status == 'pending' && reason == 'low_grade' && grade is num) {
          gradeValues.add(grade);
        } else if (status == 'pending') {
          continue;
        } else if (retakeGrade != null && retakeGrade is num && retakeGrade > 0) {
          gradeValues.add(retakeGrade);
        } else if (grade != null && grade is num && grade > 0) {
          gradeValues.add(grade);
        }
      }

      String type;
      num? avgGrade;

      if (hasAbsent && gradeValues.isEmpty) {
        if (hasRetake) {
          type = 'retake';
          avgGrade = retakeVal;
        } else {
          type = 'absent';
        }
      } else if (gradeValues.isNotEmpty) {
        type = 'graded';
        avgGrade = (gradeValues.reduce((a, b) => a + b) / gradeValues.length).round();
      } else {
        type = 'no_data';
      }

      dayRows.add({
        'date': dateKey,
        'type': type,
        'grade': avgGrade,
        'retake_grade': hasRetake ? retakeVal : null,
      });
    }

    return dayRows;
  }

  Widget _buildDayRow(Map<String, dynamic> day, Color borderColor,
      Color cellBg, Color txt, Color sub, bool isDark) {
    final dateKey = day['date'] as String;
    final type = day['type'] as String;
    final grade = day['grade'];
    final retakeGrade = day['retake_grade'];

    String dateStr;
    String weekDay;
    try {
      final dt = DateTime.parse(dateKey);
      dateStr =
          '${dt.day.toString().padLeft(2, '0')}.${dt.month.toString().padLeft(2, '0')}';
      const days = ['Du', 'Se', 'Cho', 'Pa', 'Ju', 'Sha', 'Ya'];
      weekDay = days[dt.weekday - 1];
    } catch (_) {
      dateStr = dateKey;
      weekDay = '';
    }

    return Container(
      margin: const EdgeInsets.only(bottom: 6),
      decoration: BoxDecoration(
        color: cellBg,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: type == 'absent'
              ? AppTheme.errorColor.withOpacity(0.3)
              : type == 'retake'
                  ? AppTheme.warningColor.withOpacity(0.3)
                  : borderColor,
        ),
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        child: Row(
          children: [
            SizedBox(
              width: 42,
              child: Column(
                children: [
                  Text(dateStr,
                      style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                          color: txt)),
                  Text(weekDay,
                      style: TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.w500,
                          color: sub)),
                ],
              ),
            ),
            Container(
              width: 1,
              height: 36,
              margin: const EdgeInsets.symmetric(horizontal: 10),
              color: borderColor,
            ),
            Expanded(
              child: _buildDayStatus(type, txt, sub),
            ),
            const SizedBox(width: 8),
            _buildGradeCell(type, grade, retakeGrade, isDark, sub),
          ],
        ),
      ),
    );
  }

  Widget _buildDayStatus(String type, Color txt, Color sub) {
    if (type == 'absent') {
      return Row(
        children: [
          Container(
            width: 8,
            height: 8,
            decoration: const BoxDecoration(
              color: AppTheme.errorColor,
              shape: BoxShape.circle,
            ),
          ),
          const SizedBox(width: 8),
          Text('Kelmagan',
              style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w500,
                  color: AppTheme.errorColor)),
        ],
      );
    }
    if (type == 'retake') {
      return Row(
        children: [
          Container(
            width: 8,
            height: 8,
            decoration: const BoxDecoration(
              color: AppTheme.warningColor,
              shape: BoxShape.circle,
            ),
          ),
          const SizedBox(width: 8),
          Text('Retake',
              style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w500,
                  color: AppTheme.warningColor)),
        ],
      );
    }
    if (type == 'graded') {
      return Row(
        children: [
          Container(
            width: 8,
            height: 8,
            decoration: const BoxDecoration(
              color: AppTheme.successColor,
              shape: BoxShape.circle,
            ),
          ),
          const SizedBox(width: 8),
          Text('Qatnashgan',
              style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w500,
                  color: txt)),
        ],
      );
    }
    return Text('—', style: TextStyle(fontSize: 13, color: sub));
  }

  Widget _buildGradeCell(
      String type, dynamic grade, dynamic retakeGrade, bool isDark, Color sub) {
    if (type == 'retake') {
      return SizedBox(
        width: 48,
        height: 48,
        child: CustomPaint(
          painter: _DiagonalCellPainter(
            nbText: 'NB',
            gradeText: retakeGrade is num
                ? (retakeGrade % 1 == 0
                    ? retakeGrade.toInt().toString()
                    : retakeGrade.toStringAsFixed(1))
                : retakeGrade?.toString() ?? '',
            isDark: isDark,
          ),
        ),
      );
    }

    if (type == 'absent') {
      return Container(
        width: 48,
        height: 36,
        decoration: BoxDecoration(
          color: AppTheme.errorColor.withOpacity(0.1),
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: AppTheme.errorColor.withOpacity(0.3)),
        ),
        alignment: Alignment.center,
        child: const Text('NB',
            style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w800,
                color: AppTheme.errorColor)),
      );
    }

    if (type == 'no_data') {
      return Container(
        width: 48,
        height: 36,
        decoration: BoxDecoration(
          color: Colors.grey.withOpacity(0.05),
          borderRadius: BorderRadius.circular(10),
        ),
        alignment: Alignment.center,
        child: Text('—', style: TextStyle(fontSize: 14, color: sub)),
      );
    }

    final gradeVal = grade is num
        ? grade.toDouble()
        : double.tryParse(grade?.toString() ?? '') ?? 0;
    final gradeColor = gradeVal >= 86
        ? AppTheme.successColor
        : gradeVal >= 71
            ? const Color(0xFF1E88E5)
            : gradeVal >= 56
                ? AppTheme.warningColor
                : gradeVal > 0
                    ? AppTheme.errorColor
                    : sub;

    final displayText = gradeVal % 1 == 0
        ? gradeVal.toInt().toString()
        : gradeVal.toStringAsFixed(1);

    return Container(
      width: 48,
      height: 36,
      decoration: BoxDecoration(
        color: gradeColor.withOpacity(0.1),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: gradeColor.withOpacity(0.3)),
      ),
      alignment: Alignment.center,
      child: Text(displayText,
          style: TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w800,
              color: gradeColor)),
    );
  }

  double _toDouble(dynamic v) {
    if (v is num) return v.toDouble();
    if (v is String) return double.tryParse(v) ?? 0;
    return 0;
  }
}

class _DiagonalCellPainter extends CustomPainter {
  final String nbText;
  final String gradeText;
  final bool isDark;

  _DiagonalCellPainter({
    required this.nbText,
    required this.gradeText,
    required this.isDark,
  });

  @override
  void paint(Canvas canvas, Size size) {
    final rect = RRect.fromRectAndRadius(
        Rect.fromLTWH(0, 0, size.width, size.height),
        const Radius.circular(10));

    final bgPaint = Paint()
      ..color = AppTheme.warningColor.withOpacity(0.1);
    canvas.drawRRect(rect, bgPaint);

    final borderPaint = Paint()
      ..color = AppTheme.warningColor.withOpacity(0.3)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1;
    canvas.drawRRect(rect, borderPaint);

    canvas.save();
    canvas.clipRRect(rect);

    final linePaint = Paint()
      ..color = AppTheme.warningColor.withOpacity(0.4)
      ..strokeWidth = 1;
    canvas.drawLine(
        Offset(0, size.height), Offset(size.width, 0), linePaint);

    final nbPainter = TextPainter(
      text: TextSpan(
        text: nbText,
        style: TextStyle(
          fontSize: 10,
          fontWeight: FontWeight.w700,
          color: AppTheme.errorColor.withOpacity(0.7),
        ),
      ),
      textDirection: TextDirection.ltr,
    )..layout();
    nbPainter.paint(
        canvas, Offset(size.width * 0.08, size.height * 0.55));

    final gradePainter = TextPainter(
      text: TextSpan(
        text: gradeText,
        style: const TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w800,
          color: AppTheme.successColor,
        ),
      ),
      textDirection: TextDirection.ltr,
    )..layout();
    gradePainter.paint(
        canvas,
        Offset(size.width - gradePainter.width - size.width * 0.1,
            size.height * 0.08));

    canvas.restore();
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
