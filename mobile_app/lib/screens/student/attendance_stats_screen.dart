import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../l10n/app_localizations.dart';
import '../../providers/student_provider.dart';
import '../../services/api_service.dart';
import '../../services/student_service.dart';
import '../../utils/page_transitions.dart';
import '../../widgets/clinic_header.dart';

const _green = Color(0xFF15803D);
const _blue = Color(0xFF1D4ED8);
const _amber = Color(0xFFB45309);
const _red = Color(0xFFBE123C);

/// Green from 85 % attendance, amber from 70 %, red below.
Color _attendanceColor(num percent) =>
    percent >= 85 ? _green : (percent >= 70 ? _amber : _red);

int _toInt(dynamic v) {
  if (v is int) return v;
  if (v is num) return v.round();
  if (v is String) return int.tryParse(v) ?? 0;
  return 0;
}

/// "Davomat statistikasi": every subject of the semester at a glance —
/// lessons held so far against lessons missed — and, on tap, the day-by-day
/// journal for that subject.
class AttendanceStatsScreen extends StatefulWidget {
  const AttendanceStatsScreen({super.key});

  @override
  State<AttendanceStatsScreen> createState() => _AttendanceStatsScreenState();
}

class _AttendanceStatsScreenState extends State<AttendanceStatsScreen> {
  List<dynamic> _subjects = const [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load({bool force = false}) async {
    final provider = context.read<StudentProvider>();
    try {
      if (force || provider.subjects == null || provider.subjects!.isEmpty) {
        await provider.loadSubjects(force: force);
      }
    } catch (_) {/* show whatever is cached */}
    if (!mounted) return;
    setState(() {
      _subjects = provider.subjects ?? const [];
      _loading = false;
    });
  }

  void _open(Map<String, dynamic> subject) {
    Navigator.of(context).push(
      SlideFadePageRoute(builder: (_) => _SubjectAttendanceScreen(subject: subject)),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final muted = ClinicTheme.mutedOf(context);

    return Scaffold(
      backgroundColor: ClinicTheme.bgOf(context),
      body: Column(
        children: [
          ClinicHeader(
            overline: l.useful.toUpperCase(),
            title: l.pick(
              uz: 'Davomat statistikasi',
              ru: 'Статистика посещаемости',
              en: 'Attendance statistics',
            ),
            onBack: () => Navigator.pop(context),
          ),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _subjects.isEmpty
                    ? Center(
                        child: Text(l.noSubjects,
                            style: TextStyle(color: muted, fontSize: 15)),
                      )
                    : RefreshIndicator(
                        onRefresh: () => _load(force: true),
                        child: ListView(
                          physics: const AlwaysScrollableScrollPhysics(),
                          padding: const EdgeInsets.fromLTRB(14, 14, 14, 24),
                          children: [
                            _summaryCard(),
                            const SizedBox(height: 6),
                            ..._subjects
                                .whereType<Map>()
                                .map((s) => _subjectCard(Map<String, dynamic>.from(s))),
                          ],
                        ),
                      ),
          ),
        ],
      ),
    );
  }

  /// Semester total across all subjects.
  Widget _summaryCard() {
    final l = AppLocalizations.of(context);
    var total = 0;
    var absent = 0;
    for (final s in _subjects.whereType<Map>()) {
      total += _toInt(s['lessons_total']);
      absent += _toInt(s['lessons_absent']);
    }
    final percent = total > 0 ? ((total - absent) / total * 100).round() : null;
    final color = percent == null ? ClinicTheme.faint : _attendanceColor(percent);

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFF0D9488), Color(0xFF1E3A8A)],
        ),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  l.pick(uz: 'Semestr bo\'yicha', ru: 'За семестр', en: 'This semester'),
                  style: TextStyle(
                      fontSize: 11.5,
                      fontWeight: FontWeight.w600,
                      letterSpacing: 0.6,
                      color: Colors.white.withValues(alpha: 0.85)),
                ),
                const SizedBox(height: 6),
                Text(
                  _lessonsLine(total, absent),
                  style: const TextStyle(
                      fontSize: 14.5, fontWeight: FontWeight.w700, color: Colors.white),
                ),
                const SizedBox(height: 10),
                _ratioBar(total, absent, trackColor: Colors.white.withValues(alpha: 0.25), fillColor: Colors.white),
              ],
            ),
          ),
          const SizedBox(width: 14),
          _percentBadge(percent, color: color, size: 58, fontSize: 15),
        ],
      ),
    );
  }

  Widget _subjectCard(Map<String, dynamic> s) {
    final name = s['subject_name']?.toString() ?? '';
    final total = _toInt(s['lessons_total']);
    final absent = _toInt(s['lessons_absent']);
    final percent = s['attendance_percent'] == null ? null : _toInt(s['attendance_percent']);
    final color = percent == null ? ClinicTheme.faint : _attendanceColor(percent);
    final ink = ClinicTheme.inkOf(context);
    final muted = ClinicTheme.mutedOf(context);

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      decoration: BoxDecoration(
        color: ClinicTheme.surfaceOf(context),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: absent > 0 && percent != null && percent < 85 ? color : ClinicTheme.dividerOf(context),
          width: absent > 0 && percent != null && percent < 85 ? 1.5 : 1,
        ),
        boxShadow: ClinicTheme.cardShadow,
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(14),
          onTap: () => _open(s),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(13, 12, 10, 12),
            child: Row(
              children: [
                _percentBadge(percent, color: color, size: 48, fontSize: 12.5),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(name,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                              fontSize: 14, fontWeight: FontWeight.w700, color: ink, height: 1.25)),
                      const SizedBox(height: 4),
                      Text(_lessonsLine(total, absent),
                          style: TextStyle(fontSize: 12, color: absent > 0 ? color : muted, fontWeight: FontWeight.w600)),
                      const SizedBox(height: 8),
                      _ratioBar(total, absent, trackColor: ClinicTheme.dividerOf(context), fillColor: color),
                    ],
                  ),
                ),
                const SizedBox(width: 6),
                Icon(Icons.chevron_right_rounded, color: ClinicTheme.faint),
              ],
            ),
          ),
        ),
      ),
    );
  }

  /// "14 dars · 2 qoldirilgan" — the comparison the percentage is made of.
  String _lessonsLine(int total, int absent) {
    final l = AppLocalizations.of(context);
    if (total == 0) {
      return l.pick(uz: 'Hali dars bo\'lmagan', ru: 'Занятий ещё не было', en: 'No lessons yet');
    }
    final lessons = l.pick(uz: '$total dars', ru: '$total занятий', en: '$total lessons');
    final missed = absent == 0
        ? l.pick(uz: 'qoldirilmagan', ru: 'без пропусков', en: 'none missed')
        : l.pick(uz: '$absent qoldirilgan', ru: '$absent пропущено', en: '$absent missed');
    return '$lessons · $missed';
  }

  Widget _percentBadge(int? percent, {required Color color, required double size, required double fontSize}) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(shape: BoxShape.circle, color: color),
      alignment: Alignment.center,
      child: Text(
        percent == null ? '—' : '$percent%',
        style: TextStyle(fontSize: fontSize, fontWeight: FontWeight.w900, color: Colors.white),
      ),
    );
  }

  /// Attended share of all lessons, as a thin bar.
  Widget _ratioBar(int total, int absent, {required Color trackColor, required Color fillColor}) {
    final ratio = total > 0 ? (total - absent) / total : 0.0;
    return ClipRRect(
      borderRadius: BorderRadius.circular(3),
      child: SizedBox(
        height: 5,
        child: Stack(
          children: [
            Container(color: trackColor),
            FractionallySizedBox(
              widthFactor: ratio.clamp(0.0, 1.0),
              child: Container(color: fillColor),
            ),
          ],
        ),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────
// Detail: one subject's journal, day by day
// ─────────────────────────────────────────────────────────────────────

class _SubjectAttendanceScreen extends StatefulWidget {
  final Map<String, dynamic> subject;
  const _SubjectAttendanceScreen({required this.subject});

  @override
  State<_SubjectAttendanceScreen> createState() => _SubjectAttendanceScreenState();
}

class _SubjectAttendanceScreenState extends State<_SubjectAttendanceScreen> {
  List<dynamic> _grades = const [];
  bool _loading = true;

  int get _subjectId => _toInt(widget.subject['subject_id']);

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final res = await StudentService(ApiService()).getSubjectGrades(_subjectId);
      final data = res['data'] as Map<String, dynamic>? ?? {};
      if (mounted) setState(() => _grades = data['grades'] as List<dynamic>? ?? const []);
    } catch (_) {/* keep what we have */}
    if (mounted) setState(() => _loading = false);
  }

  Color _gradeColor(double v) {
    if (v >= 86) return _green;
    if (v >= 71) return _blue;
    if (v >= 56) return _amber;
    return _red;
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Scaffold(
      backgroundColor: ClinicTheme.bgOf(context),
      body: Column(
        children: [
          ClinicHeader(
            overline: l.pick(uz: 'DAVOMAT', ru: 'ПОСЕЩАЕМОСТЬ', en: 'ATTENDANCE'),
            title: widget.subject['subject_name']?.toString() ?? '',
            onBack: () => Navigator.pop(context),
          ),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : RefreshIndicator(onRefresh: _load, child: _buildContent()),
          ),
        ],
      ),
    );
  }

  List<_DayData> _buildDays() {
    final rawMap = <String, List<_PairGrade>>{};

    for (final g in _grades) {
      final ttCode = g['training_type_code'];
      if (ttCode == 11 ||
          ttCode == 99 ||
          ttCode == 100 ||
          ttCode == 101 ||
          ttCode == 102 ||
          ttCode == 103) {
        continue;
      }

      final dateRaw = g['lesson_date']?.toString() ?? '';
      if (dateRaw.length < 10) continue;
      final dateKey = dateRaw.substring(0, 10);

      final pairRaw = g['lesson_pair_name']?.toString() ?? '';
      final pairNum = _extractPairNum(pairRaw);
      final reason = g['reason']?.toString();
      final status = g['status']?.toString();
      final grade = g['grade'];
      final retakeGrade = g['retake_grade'];

      _CellType type;
      double? value;

      if (reason == 'absent' && (grade == null || grade == 0)) {
        if (retakeGrade != null && retakeGrade is num && retakeGrade > 0) {
          type = _CellType.retake;
          value = retakeGrade.toDouble();
        } else {
          type = _CellType.absent;
        }
      } else if (status == 'pending' && reason != 'low_grade') {
        type = _CellType.empty;
      } else if (retakeGrade != null && retakeGrade is num && retakeGrade > 0) {
        type = _CellType.graded;
        value = retakeGrade.toDouble();
      } else if (grade != null && grade is num) {
        type = _CellType.graded;
        value = grade.toDouble();
      } else {
        type = _CellType.empty;
      }

      final key = '$dateKey|$pairNum';
      rawMap.putIfAbsent(key, () => []);
      rawMap[key]!.add(_PairGrade(pairNum, type, value));
    }

    final dayMap = <String, List<_PairGrade>>{};
    for (final entry in rawMap.entries) {
      final dateKey = entry.key.split('|')[0];
      final items = entry.value;
      dayMap.putIfAbsent(dateKey, () => []);
      dayMap[dateKey]!.add(_mergePairGrades(items));
    }

    final sorted = dayMap.keys.toList()..sort();
    return sorted.map((dateKey) {
      final pairs = dayMap[dateKey]!;
      pairs.sort((a, b) => a.pair.compareTo(b.pair));
      return _DayData(dateKey, pairs);
    }).toList();
  }

  _PairGrade _mergePairGrades(List<_PairGrade> items) {
    final pair = items.first.pair;
    final graded = items
        .where((p) => p.type == _CellType.graded && p.value != null)
        .toList();
    if (graded.isNotEmpty) {
      final avg =
          graded.map((p) => p.value!).reduce((a, b) => a + b) / graded.length;
      return _PairGrade(pair, _CellType.graded, avg);
    }
    final retake = items
        .where((p) => p.type == _CellType.retake && p.value != null)
        .toList();
    if (retake.isNotEmpty) {
      return _PairGrade(pair, _CellType.retake, retake.last.value);
    }
    if (items.any((p) => p.type == _CellType.absent)) {
      return _PairGrade(pair, _CellType.absent, null);
    }
    return _PairGrade(pair, _CellType.empty, null);
  }

  Widget _buildContent() {
    final days = _buildDays();

    if (days.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          const SizedBox(height: 120),
          Icon(Icons.event_note_outlined, size: 48, color: ClinicTheme.faint),
          const SizedBox(height: 12),
          Text('Ma\'lumot topilmadi',
              textAlign: TextAlign.center,
              style: TextStyle(color: ClinicTheme.mutedOf(context), fontSize: 14)),
        ],
      );
    }

    double gradeSum = 0;
    int gradeCount = 0;
    for (final d in days) {
      for (final p in d.pairs) {
        if (p.type == _CellType.empty || p.type == _CellType.absent) continue;
        if (p.value != null) {
          gradeSum += p.value!;
          gradeCount++;
        }
      }
    }
    final avgGrade = gradeCount > 0 ? gradeSum / gradeCount : 0.0;

    // Same numbers as the list card, so the two screens never disagree.
    final total = _toInt(widget.subject['lessons_total']);
    final absent = _toInt(widget.subject['lessons_absent']);

    return ListView.builder(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(14, 4, 14, 20),
      itemCount: days.length + 1,
      itemBuilder: (_, i) => i == 0
          ? _buildStatsBar(total, total - absent, absent, avgGrade)
          : _buildDayCard(days[i - 1]),
    );
  }

  Widget _buildStatsBar(int total, int attended, int absent, double avgGrade) {
    final percent = total > 0 ? (attended / total * 100).round() : null;
    final percentColor = percent == null ? ClinicTheme.faint : _attendanceColor(percent);

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
      decoration: BoxDecoration(
        color: ClinicTheme.surfaceOf(context),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: ClinicTheme.dividerOf(context), width: 1),
        boxShadow: ClinicTheme.cardShadow,
      ),
      child: Row(
        children: [
          Container(
            width: 46,
            height: 46,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: percentColor,
            ),
            child: Center(
              child: Text(percent == null ? '—' : '$percent%',
                  style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w900,
                      color: Colors.white)),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Wrap(
              spacing: 6,
              runSpacing: 6,
              children: [
                _chip('Jami', '$total', ClinicTheme.mutedOf(context)),
                _chip('Bor', '$attended', _green),
                _chip('NB', '$absent', _red),
                if (avgGrade > 0)
                  _chip('O\'rtacha', avgGrade.toStringAsFixed(1), _blue),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _chip(String label, String value, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text('$label: $value',
          style: const TextStyle(
              fontSize: 10.5, fontWeight: FontWeight.w800, color: Colors.white)),
    );
  }

  Widget _buildDayCard(_DayData day) {
    final muted = ClinicTheme.mutedOf(context);
    final hasAbsent = day.pairs.any((p) => p.type == _CellType.absent);
    final hasRetake = day.pairs.any((p) => p.type == _CellType.retake);

    String dateStr;
    String weekDay;
    try {
      final dt = DateTime.parse(day.date);
      dateStr =
          '${dt.day.toString().padLeft(2, '0')}.${dt.month.toString().padLeft(2, '0')}';
      const wds = [
        'Dushanba',
        'Seshanba',
        'Chorshanba',
        'Payshanba',
        'Juma',
        'Shanba',
        'Yakshanba'
      ];
      weekDay = wds[dt.weekday - 1];
    } catch (_) {
      dateStr = day.date;
      weekDay = '';
    }

    final gradedPairs = day.pairs
        .where((p) =>
            p.type == _CellType.graded || p.type == _CellType.retake)
        .toList();
    double? dayAvg;
    if (gradedPairs.isNotEmpty) {
      final vals =
          gradedPairs.where((p) => p.value != null).map((p) => p.value!);
      if (vals.isNotEmpty) {
        dayAvg = vals.reduce((a, b) => a + b) / vals.length;
      }
    }

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      decoration: BoxDecoration(
        color: ClinicTheme.surfaceOf(context),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: hasAbsent
              ? _red
              : hasRetake
                  ? _amber
                  : ClinicTheme.dividerOf(context),
          width: hasAbsent || hasRetake ? 1.5 : 1,
        ),
        boxShadow: ClinicTheme.cardShadow,
      ),
      child: Padding(
        padding: const EdgeInsets.all(13),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
                  decoration: BoxDecoration(
                    color: ClinicTheme.teal,
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(dateStr,
                      style: const TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w800,
                          color: Colors.white)),
                ),
                const SizedBox(width: 10),
                Text(weekDay,
                    style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                        color: muted)),
                const Spacer(),
                if (dayAvg != null) _buildAvgBadge(dayAvg),
              ],
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: day.pairs.map(_buildPairChip).toList(),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildAvgBadge(double avg) {
    final color = _gradeColor(avg);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(avg.toStringAsFixed(1),
          style: const TextStyle(
              fontSize: 12, fontWeight: FontWeight.w900, color: Colors.white)),
    );
  }

  Widget _buildPairChip(_PairGrade p) {
    final muted = ClinicTheme.mutedOf(context);

    if (p.type == _CellType.retake) {
      return Column(
        children: [
          SizedBox(
            width: 68,
            height: 36,
            child: CustomPaint(
              painter: _DiagonalCellPainter(
                gradeText: p.value != null
                    ? (p.value! % 1 == 0
                        ? p.value!.toInt().toString()
                        : p.value!.toStringAsFixed(1))
                    : '',
              ),
            ),
          ),
          const SizedBox(height: 2),
          Text('${p.pair}-juftlik', style: TextStyle(fontSize: 9.5, color: muted)),
        ],
      );
    }

    Color bgColor;
    String displayText;
    Color textColor;

    if (p.type == _CellType.absent) {
      bgColor = _red;
      displayText = 'NB';
      textColor = Colors.white;
    } else if (p.type == _CellType.empty) {
      bgColor = ClinicTheme.dividerOf(context);
      displayText = '—';
      textColor = ClinicTheme.faint;
    } else {
      final v = p.value ?? 0;
      bgColor = v > 0 ? _gradeColor(v) : ClinicTheme.dividerOf(context);
      displayText = v % 1 == 0 ? v.toInt().toString() : v.toStringAsFixed(1);
      textColor = v > 0 ? Colors.white : ClinicTheme.faint;
    }

    return Column(
      children: [
        Container(
          width: 68,
          height: 36,
          decoration: BoxDecoration(
            color: bgColor,
            borderRadius: BorderRadius.circular(10),
          ),
          alignment: Alignment.center,
          child: Text(displayText,
              style: TextStyle(
                  fontSize: 15, fontWeight: FontWeight.w900, color: textColor)),
        ),
        const SizedBox(height: 2),
        Text('${p.pair}-juftlik', style: TextStyle(fontSize: 9.5, color: muted)),
      ],
    );
  }

  String _extractPairNum(String raw) {
    final match = RegExp(r'(\d+)').firstMatch(raw);
    return match?.group(1) ?? raw;
  }
}

enum _CellType { empty, graded, absent, retake }

class _PairGrade {
  final String pair;
  final _CellType type;
  final double? value;
  _PairGrade(this.pair, this.type, this.value);
}

class _DayData {
  final String date;
  final List<_PairGrade> pairs;
  _DayData(this.date, this.pairs);
}

/// A pair cell that was missed then retaken — red lower-left triangle (NB)
/// and a green upper-right triangle with the retake grade.
class _DiagonalCellPainter extends CustomPainter {
  final String gradeText;

  _DiagonalCellPainter({required this.gradeText});

  @override
  void paint(Canvas canvas, Size size) {
    final rect = RRect.fromRectAndRadius(
        Rect.fromLTWH(0, 0, size.width, size.height),
        const Radius.circular(10));
    canvas.save();
    canvas.clipRRect(rect);

    // Lower-left triangle (NB).
    final lower = Path()
      ..moveTo(0, 0)
      ..lineTo(0, size.height)
      ..lineTo(size.width, size.height)
      ..close();
    canvas.drawPath(lower, Paint()..color = _red);

    // Upper-right triangle (retake grade).
    final upper = Path()
      ..moveTo(0, 0)
      ..lineTo(size.width, 0)
      ..lineTo(size.width, size.height)
      ..close();
    canvas.drawPath(upper, Paint()..color = _green);

    canvas.drawLine(
      Offset(0, size.height),
      Offset(size.width, 0),
      Paint()
        ..color = Colors.white.withValues(alpha: 0.6)
        ..strokeWidth = 1,
    );

    final nbPainter = TextPainter(
      text: const TextSpan(
        text: 'NB',
        style: TextStyle(
            fontSize: 9, fontWeight: FontWeight.w800, color: Colors.white),
      ),
      textDirection: TextDirection.ltr,
    )..layout();
    nbPainter.paint(canvas, Offset(4, size.height - nbPainter.height - 3));

    final gradePainter = TextPainter(
      text: TextSpan(
        text: gradeText,
        style: const TextStyle(
            fontSize: 12, fontWeight: FontWeight.w900, color: Colors.white),
      ),
      textDirection: TextDirection.ltr,
    )..layout();
    gradePainter.paint(canvas, Offset(size.width - gradePainter.width - 4, 3));

    canvas.restore();
  }

  @override
  bool shouldRepaint(covariant _DiagonalCellPainter old) => old.gradeText != gradeText;
}
