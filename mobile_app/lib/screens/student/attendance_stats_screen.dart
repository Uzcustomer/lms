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
                            : _buildTable(isDark, txt, sub),
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

  Widget _buildTable(bool isDark, Color txt, Color sub) {
    final filtered = <Map<String, dynamic>>[];
    for (final g in _grades) {
      final ttCode = g['training_type_code'];
      if (ttCode == 99 || ttCode == 100 || ttCode == 101 || ttCode == 102) {
        continue;
      }
      filtered.add(Map<String, dynamic>.from(g));
    }

    if (filtered.isEmpty) {
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

    final allPairs = <String>{};
    final dayData = <String, Map<String, _CellData>>{};

    for (final g in filtered) {
      final dateRaw = g['lesson_date']?.toString() ?? '';
      if (dateRaw.length < 10) continue;
      final dateKey = dateRaw.substring(0, 10);
      final pair = g['lesson_pair_name']?.toString() ?? '?';
      allPairs.add(pair);

      final reason = g['reason']?.toString();
      final status = g['status']?.toString();
      final grade = g['grade'];
      final retakeGrade = g['retake_grade'];

      _CellData cell;
      if (reason == 'absent' && (grade == null || grade == 0)) {
        if (retakeGrade != null && retakeGrade is num && retakeGrade > 0) {
          cell = _CellData.retake(retakeGrade.toDouble());
        } else {
          cell = _CellData.absent();
        }
      } else if (status == 'pending' && reason != 'low_grade') {
        cell = _CellData.empty();
      } else if (retakeGrade != null && retakeGrade is num && retakeGrade > 0) {
        cell = _CellData.graded(retakeGrade.toDouble());
      } else if (grade != null && grade is num) {
        cell = _CellData.graded(grade.toDouble());
      } else {
        cell = _CellData.empty();
      }

      dayData.putIfAbsent(dateKey, () => {});
      final existing = dayData[dateKey]![pair];
      if (existing == null || existing.type == _CellType.empty) {
        dayData[dateKey]![pair] = cell;
      } else if (cell.type == _CellType.graded && existing.type == _CellType.graded) {
        dayData[dateKey]![pair] = _CellData.graded(
            ((existing.value! + cell.value!) / 2).roundToDouble());
      }
    }

    final sortedPairs = allPairs.toList()
      ..sort((a, b) {
        final ai = int.tryParse(a) ?? 999;
        final bi = int.tryParse(b) ?? 999;
        return ai.compareTo(bi);
      });

    final sortedDates = dayData.keys.toList()..sort();

    final borderColor = isDark ? AppTheme.darkDivider : const Color(0xFFDEE2E6);
    final headerBg = isDark ? const Color(0xFF1A1A2E) : const Color(0xFFEDF0F7);
    final cellBg = isDark ? AppTheme.darkCard : Colors.white;

    int totalGraded = 0;
    int totalAbsent = 0;
    double gradeSum = 0;
    int gradeCount = 0;

    for (final dateKey in sortedDates) {
      for (final pair in sortedPairs) {
        final cell = dayData[dateKey]?[pair];
        if (cell == null || cell.type == _CellType.empty) continue;
        if (cell.type == _CellType.absent) {
          totalAbsent++;
        } else {
          totalGraded++;
          if (cell.value != null) {
            gradeSum += cell.value!;
            gradeCount++;
          }
        }
      }
    }
    final totalAll = totalGraded + totalAbsent;
    final attendPercent = totalAll > 0 ? ((totalGraded / totalAll) * 100) : 100.0;
    final avgGrade = gradeCount > 0 ? (gradeSum / gradeCount) : 0.0;

    return Column(
      children: [
        _buildStatsBar(totalAll, totalGraded, totalAbsent, attendPercent,
            avgGrade, isDark, txt),
        Expanded(
          child: SingleChildScrollView(
            child: SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: _buildDataTable(sortedDates, sortedPairs, dayData,
                  borderColor, headerBg, cellBg, txt, sub, isDark),
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildStatsBar(int total, int attended, int absent, double percent,
      double avgGrade, bool isDark, Color txt) {
    final percentColor = percent >= 85
        ? AppTheme.successColor
        : percent >= 70
            ? AppTheme.warningColor
            : AppTheme.errorColor;

    return Container(
      margin: const EdgeInsets.fromLTRB(14, 4, 14, 10),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        gradient: LinearGradient(colors: [
          percentColor.withOpacity(0.1),
          percentColor.withOpacity(0.04),
        ]),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: percentColor.withOpacity(0.2)),
      ),
      child: Row(
        children: [
          Container(
            width: 46,
            height: 46,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: percentColor.withOpacity(0.15),
            ),
            child: Center(
              child: Text('${percent.round()}%',
                  style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w800,
                      color: percentColor)),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Wrap(
              spacing: 6,
              runSpacing: 4,
              children: [
                _chip('Jami', '$total', Colors.blueGrey),
                _chip('Bor', '$attended', AppTheme.successColor),
                _chip('NB', '$absent', AppTheme.errorColor),
                if (avgGrade > 0)
                  _chip('O\'rtacha', avgGrade.toStringAsFixed(1),
                      const Color(0xFF1E88E5)),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _chip(String label, String value, Color color) {
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

  Widget _buildDataTable(
      List<String> dates,
      List<String> pairs,
      Map<String, Map<String, _CellData>> dayData,
      Color borderColor,
      Color headerBg,
      Color cellBg,
      Color txt,
      Color sub,
      bool isDark) {
    const double dateColWidth = 80;
    const double pairColWidth = 56;
    const double avgColWidth = 60;

    final columns = <DataColumn>[
      DataColumn(
        label: SizedBox(
          width: dateColWidth,
          child: Text('Sana',
              style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  color: txt)),
        ),
      ),
      ...pairs.map((p) => DataColumn(
            label: SizedBox(
              width: pairColWidth,
              child: Center(
                child: Text('$p-juft',
                    style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                        color: txt)),
              ),
            ),
          )),
      DataColumn(
        label: SizedBox(
          width: avgColWidth,
          child: Center(
            child: Text('O\'rtacha',
                style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    color: txt)),
          ),
        ),
      ),
    ];

    final rows = <DataRow>[];
    for (final dateKey in dates) {
      String dateStr;
      String weekDay;
      try {
        final dt = DateTime.parse(dateKey);
        dateStr =
            '${dt.day.toString().padLeft(2, '0')}.${dt.month.toString().padLeft(2, '0')}';
        const wds = ['Du', 'Se', 'Cho', 'Pa', 'Ju', 'Sha', 'Ya'];
        weekDay = wds[dt.weekday - 1];
      } catch (_) {
        dateStr = dateKey;
        weekDay = '';
      }

      final cells = <DataCell>[];

      cells.add(DataCell(SizedBox(
        width: dateColWidth,
        child: Row(
          children: [
            Text(dateStr,
                style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                    color: txt)),
            const SizedBox(width: 4),
            Text(weekDay,
                style: TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.w500,
                    color: sub)),
          ],
        ),
      )));

      double rowSum = 0;
      int rowCount = 0;
      bool hasAbsent = false;

      for (final pair in pairs) {
        final cell = dayData[dateKey]?[pair];
        cells.add(DataCell(SizedBox(
          width: pairColWidth,
          child: Center(child: _buildCell(cell, isDark, sub)),
        )));
        if (cell != null && cell.type == _CellType.graded && cell.value != null) {
          rowSum += cell.value!;
          rowCount++;
        }
        if (cell != null && cell.type == _CellType.absent) {
          hasAbsent = true;
        }
      }

      final rowAvg = rowCount > 0 ? (rowSum / rowCount) : null;
      cells.add(DataCell(SizedBox(
        width: avgColWidth,
        child: Center(child: _buildAvgCell(rowAvg, hasAbsent, isDark, sub)),
      )));

      rows.add(DataRow(
        color: WidgetStatePropertyAll(
            hasAbsent ? AppTheme.errorColor.withOpacity(0.03) : cellBg),
        cells: cells,
      ));
    }

    return DataTable(
      headingRowColor: WidgetStatePropertyAll(headerBg),
      dataRowMinHeight: 44,
      dataRowMaxHeight: 52,
      horizontalMargin: 12,
      columnSpacing: 4,
      headingRowHeight: 44,
      border: TableBorder.all(color: borderColor, width: 0.5),
      columns: columns,
      rows: rows,
    );
  }

  Widget _buildCell(_CellData? cell, bool isDark, Color sub) {
    if (cell == null || cell.type == _CellType.empty) {
      return Text('—', style: TextStyle(fontSize: 13, color: sub));
    }

    if (cell.type == _CellType.absent) {
      return Container(
        width: 40,
        height: 30,
        decoration: BoxDecoration(
          color: AppTheme.errorColor.withOpacity(0.1),
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: AppTheme.errorColor.withOpacity(0.3)),
        ),
        alignment: Alignment.center,
        child: const Text('NB',
            style: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w800,
                color: AppTheme.errorColor)),
      );
    }

    if (cell.type == _CellType.retake) {
      return SizedBox(
        width: 42,
        height: 42,
        child: CustomPaint(
          painter: _DiagonalCellPainter(
            nbText: 'NB',
            gradeText: cell.value != null
                ? (cell.value! % 1 == 0
                    ? cell.value!.toInt().toString()
                    : cell.value!.toStringAsFixed(1))
                : '',
            isDark: isDark,
          ),
        ),
      );
    }

    final v = cell.value ?? 0;
    final color = v >= 86
        ? AppTheme.successColor
        : v >= 71
            ? const Color(0xFF1E88E5)
            : v >= 56
                ? AppTheme.warningColor
                : v > 0
                    ? AppTheme.errorColor
                    : sub;

    final text =
        v % 1 == 0 ? v.toInt().toString() : v.toStringAsFixed(1);

    return Container(
      width: 40,
      height: 30,
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: color.withOpacity(0.3)),
      ),
      alignment: Alignment.center,
      child: Text(text,
          style: TextStyle(
              fontSize: 13, fontWeight: FontWeight.w800, color: color)),
    );
  }

  Widget _buildAvgCell(double? avg, bool hasAbsent, bool isDark, Color sub) {
    if (avg == null && !hasAbsent) {
      return Text('—', style: TextStyle(fontSize: 13, color: sub));
    }
    if (avg == null && hasAbsent) {
      return const Text('NB',
          style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w700,
              color: AppTheme.errorColor));
    }

    final v = avg!;
    final color = v >= 86
        ? AppTheme.successColor
        : v >= 71
            ? const Color(0xFF1E88E5)
            : v >= 56
                ? AppTheme.warningColor
                : AppTheme.errorColor;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
      decoration: BoxDecoration(
        color: color.withOpacity(0.12),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(v.toStringAsFixed(1),
          style: TextStyle(
              fontSize: 12, fontWeight: FontWeight.w800, color: color)),
    );
  }

  double _toDouble(dynamic v) {
    if (v is num) return v.toDouble();
    if (v is String) return double.tryParse(v) ?? 0;
    return 0;
  }
}

enum _CellType { empty, graded, absent, retake }

class _CellData {
  final _CellType type;
  final double? value;

  _CellData._(this.type, this.value);
  factory _CellData.empty() => _CellData._(_CellType.empty, null);
  factory _CellData.graded(double v) => _CellData._(_CellType.graded, v);
  factory _CellData.absent() => _CellData._(_CellType.absent, null);
  factory _CellData.retake(double v) => _CellData._(_CellType.retake, v);
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
        const Radius.circular(8));

    canvas.drawRRect(
        rect, Paint()..color = AppTheme.warningColor.withOpacity(0.1));
    canvas.drawRRect(
        rect,
        Paint()
          ..color = AppTheme.warningColor.withOpacity(0.3)
          ..style = PaintingStyle.stroke
          ..strokeWidth = 1);

    canvas.save();
    canvas.clipRRect(rect);
    canvas.drawLine(Offset(0, size.height), Offset(size.width, 0),
        Paint()
          ..color = AppTheme.warningColor.withOpacity(0.4)
          ..strokeWidth = 1);

    final nbPainter = TextPainter(
      text: TextSpan(
          text: nbText,
          style: TextStyle(
              fontSize: 9,
              fontWeight: FontWeight.w700,
              color: AppTheme.errorColor.withOpacity(0.7))),
      textDirection: TextDirection.ltr,
    )..layout();
    nbPainter.paint(canvas, Offset(3, size.height * 0.55));

    final gradePainter = TextPainter(
      text: TextSpan(
          text: gradeText,
          style: const TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w800,
              color: AppTheme.successColor)),
      textDirection: TextDirection.ltr,
    )..layout();
    gradePainter.paint(
        canvas,
        Offset(size.width - gradePainter.width - 3, size.height * 0.05));

    canvas.restore();
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
