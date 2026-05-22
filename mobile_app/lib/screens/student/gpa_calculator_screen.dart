import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/student_provider.dart';
import '../../widgets/clinic_header.dart';

class GpaCalculatorScreen extends StatefulWidget {
  const GpaCalculatorScreen({super.key});

  @override
  State<GpaCalculatorScreen> createState() => _GpaCalculatorScreenState();
}

class _GpaCalculatorScreenState extends State<GpaCalculatorScreen> {
  @override
  void initState() {
    super.initState();
    final provider = context.read<StudentProvider>();
    if (provider.subjects == null) {
      provider.loadSubjects();
    }
  }

  static double? _toDouble(dynamic v) {
    if (v == null) return null;
    if (v is num) return v.toDouble();
    return double.tryParse(v.toString());
  }

  static int _scoreTo5(num? score) {
    if (score == null) return 0;
    final s = score.toDouble();
    if (s >= 86) return 5;
    if (s >= 71) return 4;
    if (s >= 56) return 3;
    return 2;
  }

  static Color _gradeColor(int grade) {
    switch (grade) {
      case 5:
        return const Color(0xFF15803D);
      case 4:
        return const Color(0xFF1D4ED8);
      case 3:
        return const Color(0xFFB45309);
      case 2:
        return const Color(0xFFBE123C);
      default:
        return const Color(0xFF64748B);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: ClinicTheme.bgOf(context),
      body: Column(
        children: [
          ClinicHeader(
            overline: 'FOYDALI',
            title: 'GPA Kalkulyator',
            onBack: () => Navigator.pop(context),
          ),
          Expanded(
            child: Consumer<StudentProvider>(
              builder: (context, provider, _) {
                if (provider.isLoading || provider.subjects == null) {
                  return const Center(child: CircularProgressIndicator());
                }

                final subjects = provider.subjects!;
                final graded = <_SubjectGpa>[];
                final pending = <_SubjectGpa>[];

                for (final s in subjects) {
                  final name = s['subject_name']?.toString() ?? '';
                  final creditRaw = s['credit'];
                  final credit = creditRaw is num
                      ? creditRaw.toDouble()
                      : double.tryParse(creditRaw?.toString() ?? '') ?? 0;
                  final grades = s['grades'] as Map<String, dynamic>? ?? {};
                  final jn = _toDouble(grades['jn']);
                  final mt = _toDouble(grades['mt']);
                  final total = _toDouble(grades['total']);

                  final entry = _SubjectGpa(
                    name: name,
                    credit: credit,
                    jn: jn?.toDouble(),
                    mt: mt?.toDouble(),
                    total: total?.toDouble(),
                    grade5: total != null ? _scoreTo5(total) : 0,
                  );

                  if (total != null) {
                    graded.add(entry);
                  } else {
                    pending.add(entry);
                  }
                }

                double totalPoints = 0;
                double totalCredits = 0;
                for (final s in graded) {
                  totalPoints += s.grade5 * s.credit;
                  totalCredits += s.credit;
                }
                final gpa = totalCredits > 0 ? totalPoints / totalCredits : 0.0;

                return RefreshIndicator(
                  onRefresh: () => provider.loadSubjects(),
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(14, 14, 14, 24),
                    children: [
                      _buildGpaCard(gpa, graded.length, totalCredits.toInt(),
                          subjects.length),
                      const SizedBox(height: 16),
                      _buildScaleRow(),
                      const SizedBox(height: 16),
                      if (graded.isNotEmpty) ...[
                        _sectionTitle('Baholangan fanlar', '${graded.length} ta'),
                        const SizedBox(height: 10),
                        ...graded.map(_buildSubjectCard),
                      ],
                      if (pending.isNotEmpty) ...[
                        const SizedBox(height: 16),
                        _sectionTitle('Hali baholanmagan', '${pending.length} ta'),
                        const SizedBox(height: 10),
                        ...pending.map(_buildPendingCard),
                      ],
                    ],
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildGpaCard(double gpa, int gradedCount, int totalCredits,
      int totalSubjects) {
    final Color color;
    final String label;
    if (gpa >= 4.5) {
      color = const Color(0xFF15803D);
      label = "A'lo";
    } else if (gpa >= 3.5) {
      color = const Color(0xFF1D4ED8);
      label = 'Yaxshi';
    } else if (gpa >= 2.5) {
      color = const Color(0xFFB45309);
      label = 'Qoniqarli';
    } else if (gpa > 0) {
      color = const Color(0xFFBE123C);
      label = 'Qoniqarsiz';
    } else {
      color = const Color(0xFF0F766E);
      label = '';
    }
    final canPass = gpa >= 2.4 && gradedCount > 0;
    final dark = Color.lerp(color, const Color(0xFF0F172A), 0.38)!;

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [color, dark],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(18),
        boxShadow: [
          BoxShadow(
            color: color.withOpacity(0.35),
            blurRadius: 16,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Column(
        children: [
          Row(
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('SIZNING GPA',
                      style: TextStyle(
                          fontSize: 10,
                          letterSpacing: 0.5,
                          color: Colors.white.withOpacity(0.8),
                          fontWeight: FontWeight.w700)),
                  const SizedBox(height: 3),
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Text(
                        gradedCount > 0 ? gpa.toStringAsFixed(2) : '—',
                        style: const TextStyle(
                            fontSize: 40,
                            fontWeight: FontWeight.w900,
                            color: Colors.white,
                            height: 1.1),
                      ),
                      Padding(
                        padding: const EdgeInsets.only(bottom: 6, left: 3),
                        child: Text('/ 5.00',
                            style: TextStyle(
                                fontSize: 14, color: Colors.white.withOpacity(0.6))),
                      ),
                    ],
                  ),
                  if (gradedCount > 0) ...[
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        if (label.isNotEmpty) ...[
                          _chip(label),
                          const SizedBox(width: 8),
                        ],
                        Icon(
                          canPass
                              ? Icons.check_circle_rounded
                              : Icons.warning_rounded,
                          size: 15,
                          color: Colors.white.withOpacity(0.85),
                        ),
                        const SizedBox(width: 4),
                        Text(
                          canPass ? 'Kursdan o\'tadi' : 'O\'tmaydi',
                          style: TextStyle(
                              fontSize: 11,
                              color: Colors.white.withOpacity(0.85),
                              fontWeight: FontWeight.w600),
                        ),
                      ],
                    ),
                  ],
                ],
              ),
              const Spacer(),
              Container(
                padding: const EdgeInsets.all(13),
                decoration: BoxDecoration(
                  color: Colors.white.withOpacity(0.16),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Column(
                  children: [
                    Text('$gradedCount/$totalSubjects',
                        style: const TextStyle(
                            fontSize: 19,
                            fontWeight: FontWeight.w900,
                            color: Colors.white)),
                    Text('fan',
                        style: TextStyle(
                            fontSize: 10, color: Colors.white.withOpacity(0.75))),
                    const SizedBox(height: 7),
                    Text('$totalCredits',
                        style: const TextStyle(
                            fontSize: 19,
                            fontWeight: FontWeight.w900,
                            color: Colors.white)),
                    Text('kredit',
                        style: TextStyle(
                            fontSize: 10, color: Colors.white.withOpacity(0.75))),
                  ],
                ),
              ),
            ],
          ),
          if (gradedCount > 0) ...[
            const SizedBox(height: 14),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: Colors.white.withOpacity(0.13),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Text(
                'GPA = Σ(kredit × baho) ÷ Σ(kredit) = '
                '${(gpa * totalCredits).toStringAsFixed(0)} ÷ $totalCredits '
                '= ${gpa.toStringAsFixed(2)}',
                style: TextStyle(
                    fontSize: 11,
                    color: Colors.white.withOpacity(0.85),
                    fontFamily: 'monospace'),
                textAlign: TextAlign.center,
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _chip(String text) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: Colors.white.withOpacity(0.22),
        borderRadius: BorderRadius.circular(6),
      ),
      child: Text(text,
          style: const TextStyle(
              fontSize: 11, fontWeight: FontWeight.w800, color: Colors.white)),
    );
  }

  Widget _buildScaleRow() {
    return Row(
      children: [
        _scaleItem(5, "A'lo", '86-100', const Color(0xFF15803D)),
        const SizedBox(width: 8),
        _scaleItem(4, 'Yaxshi', '71-85', const Color(0xFF1D4ED8)),
        const SizedBox(width: 8),
        _scaleItem(3, 'Qon.', '56-70', const Color(0xFFB45309)),
        const SizedBox(width: 8),
        _scaleItem(2, 'Qon-siz', '0-55', const Color(0xFFBE123C)),
      ],
    );
  }

  Widget _scaleItem(int grade, String label, String range, Color color) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 9),
        decoration: BoxDecoration(
          color: color,
          borderRadius: BorderRadius.circular(11),
        ),
        child: Column(
          children: [
            Text('$grade',
                style: const TextStyle(
                    fontSize: 20, fontWeight: FontWeight.w900, color: Colors.white)),
            Text(label,
                style: const TextStyle(
                    fontSize: 10, fontWeight: FontWeight.w700, color: Colors.white)),
            Text(range,
                style: TextStyle(
                    fontSize: 9, color: Colors.white.withOpacity(0.8))),
          ],
        ),
      ),
    );
  }

  Widget _sectionTitle(String title, String count) {
    return Row(
      children: [
        Text(title,
            style: TextStyle(
                fontSize: 15,
                fontWeight: FontWeight.w800,
                color: ClinicTheme.inkOf(context))),
        const Spacer(),
        Text(count,
            style: TextStyle(
                fontSize: 12,
                color: ClinicTheme.mutedOf(context),
                fontWeight: FontWeight.w600)),
      ],
    );
  }

  Widget _buildSubjectCard(_SubjectGpa s) {
    final color = _gradeColor(s.grade5);
    final ink = ClinicTheme.inkOf(context);
    final muted = ClinicTheme.mutedOf(context);

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
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
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                  child: Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(s.name,
                                style: TextStyle(
                                    fontSize: 13,
                                    fontWeight: FontWeight.w700,
                                    color: ink),
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis),
                            const SizedBox(height: 5),
                            Row(
                              children: [
                                Icon(Icons.stars_rounded, size: 12, color: muted),
                                const SizedBox(width: 3),
                                Text('${s.credit.toInt()} kredit',
                                    style: TextStyle(fontSize: 11, color: muted)),
                                const SizedBox(width: 12),
                                if (s.jn != null) ...[
                                  Text('JN ',
                                      style: TextStyle(
                                          fontSize: 10,
                                          color: muted,
                                          fontWeight: FontWeight.w700)),
                                  Text('${s.jn!.toInt()}',
                                      style: TextStyle(
                                          fontSize: 11,
                                          color: ink,
                                          fontWeight: FontWeight.w800)),
                                  const SizedBox(width: 8),
                                ],
                                if (s.mt != null) ...[
                                  Text('MT ',
                                      style: TextStyle(
                                          fontSize: 10,
                                          color: muted,
                                          fontWeight: FontWeight.w700)),
                                  Text('${s.mt!.toInt()}',
                                      style: TextStyle(
                                          fontSize: 11,
                                          color: ink,
                                          fontWeight: FontWeight.w800)),
                                ],
                              ],
                            ),
                          ],
                        ),
                      ),
                      Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Container(
                            width: 42,
                            height: 42,
                            decoration: BoxDecoration(
                              color: color,
                              borderRadius: BorderRadius.circular(11),
                            ),
                            alignment: Alignment.center,
                            child: Text('${s.grade5}',
                                style: const TextStyle(
                                    fontSize: 21,
                                    fontWeight: FontWeight.w900,
                                    color: Colors.white)),
                          ),
                          const SizedBox(height: 2),
                          Text('${s.total!.toInt()}%',
                              style: TextStyle(
                                  fontSize: 10,
                                  fontWeight: FontWeight.w800,
                                  color: color)),
                        ],
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

  Widget _buildPendingCard(_SubjectGpa s) {
    final ink = ClinicTheme.inkOf(context);
    final muted = ClinicTheme.mutedOf(context);
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 10),
      decoration: BoxDecoration(
        color: ClinicTheme.surfaceOf(context),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: ClinicTheme.dividerOf(context), width: 1),
        boxShadow: ClinicTheme.cardShadow,
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(s.name,
                    style: TextStyle(
                        fontSize: 13, fontWeight: FontWeight.w700, color: ink),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis),
                const SizedBox(height: 2),
                Text('${s.credit.toInt()} kredit',
                    style: TextStyle(fontSize: 11, color: muted)),
              ],
            ),
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
            decoration: BoxDecoration(
              color: const Color(0xFFB45309),
              borderRadius: BorderRadius.circular(7),
            ),
            child: const Text('Kutilmoqda',
                style: TextStyle(
                    fontSize: 10, color: Colors.white, fontWeight: FontWeight.w800)),
          ),
        ],
      ),
    );
  }
}

class _SubjectGpa {
  final String name;
  final double credit;
  final double? jn;
  final double? mt;
  final double? total;
  final int grade5;

  _SubjectGpa({
    required this.name,
    required this.credit,
    this.jn,
    this.mt,
    this.total,
    required this.grade5,
  });
}
