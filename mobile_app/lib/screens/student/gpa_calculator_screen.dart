import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/theme.dart';
import '../../providers/student_provider.dart';

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

  static String _gradeLabel(int grade) {
    switch (grade) {
      case 5:
        return "A'lo";
      case 4:
        return 'Yaxshi';
      case 3:
        return 'Qoniqarli';
      case 2:
        return 'Qoniqarsiz';
      default:
        return '—';
    }
  }

  static Color _gradeColor(int grade) {
    switch (grade) {
      case 5:
        return const Color(0xFF4CAF50);
      case 4:
        return const Color(0xFF29B6F6);
      case 3:
        return const Color(0xFFFF9800);
      case 2:
        return const Color(0xFFE53935);
      default:
        return const Color(0xFF9E9E9E);
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bg = isDark ? AppTheme.darkBackground : const Color(0xFFF5F7FB);
    final card = isDark ? AppTheme.darkCard : Colors.white;
    final txt = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;
    final sub = isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;

    return Scaffold(
      backgroundColor: bg,
      appBar: AppBar(title: const Text('GPA Kalkulyator')),
      body: Consumer<StudentProvider>(
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

          // GPA hisoblash
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
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
              children: [
                // GPA card
                _buildGpaCard(gpa, graded.length, totalCredits.toInt(),
                    subjects.length, isDark),

                const SizedBox(height: 16),

                // Baho shkalasi
                _buildScaleRow(isDark, sub),

                const SizedBox(height: 16),

                // Baholangan fanlar
                if (graded.isNotEmpty) ...[
                  _sectionTitle('Baholangan fanlar', txt,
                      '${graded.length} ta'),
                  const SizedBox(height: 8),
                  ...graded.map(
                      (s) => _buildSubjectCard(s, card, txt, sub, isDark)),
                ],

                // Hali baholanmagan
                if (pending.isNotEmpty) ...[
                  const SizedBox(height: 16),
                  _sectionTitle('Hali baholanmagan', txt,
                      '${pending.length} ta'),
                  const SizedBox(height: 8),
                  ...pending.map(
                      (s) => _buildPendingCard(s, card, txt, sub, isDark)),
                ],
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _buildGpaCard(double gpa, int gradedCount, int totalCredits,
      int totalSubjects, bool isDark) {
    final gpaInt = gpa > 0 ? gpa : 0.0;
    final Color color;
    final String label;
    if (gpaInt >= 4.5) {
      color = const Color(0xFF4CAF50);
      label = "A'lo";
    } else if (gpaInt >= 3.5) {
      color = const Color(0xFF29B6F6);
      label = 'Yaxshi';
    } else if (gpaInt >= 2.5) {
      color = const Color(0xFFFF9800);
      label = 'Qoniqarli';
    } else if (gpaInt > 0) {
      color = const Color(0xFFE53935);
      label = 'Qoniqarsiz';
    } else {
      color = const Color(0xFF4A6CF7);
      label = '';
    }
    final canPass = gpaInt >= 2.4 && gradedCount > 0;

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [color, color.withOpacity(0.7)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: color.withOpacity(0.3),
            blurRadius: 20,
            offset: const Offset(0, 8),
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
                  const Text('Sizning GPA',
                      style: TextStyle(
                          fontSize: 13,
                          color: Colors.white70,
                          fontWeight: FontWeight.w500)),
                  const SizedBox(height: 2),
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Text(
                        gradedCount > 0
                            ? gpa.toStringAsFixed(2)
                            : '—',
                        style: const TextStyle(
                            fontSize: 40,
                            fontWeight: FontWeight.bold,
                            color: Colors.white,
                            height: 1.1),
                      ),
                      const Padding(
                        padding: EdgeInsets.only(bottom: 6, left: 3),
                        child: Text('/ 5.00',
                            style: TextStyle(
                                fontSize: 15, color: Colors.white54)),
                      ),
                    ],
                  ),
                  if (gradedCount > 0) ...[
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        _chip(label),
                        const SizedBox(width: 8),
                        Icon(
                          canPass
                              ? Icons.check_circle_rounded
                              : Icons.warning_rounded,
                          size: 15,
                          color: Colors.white70,
                        ),
                        const SizedBox(width: 4),
                        Text(
                          canPass ? 'Kursdan o\'tadi' : 'O\'tmaydi',
                          style: const TextStyle(
                              fontSize: 11,
                              color: Colors.white70,
                              fontWeight: FontWeight.w500),
                        ),
                      ],
                    ),
                  ],
                ],
              ),
              const Spacer(),
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: Colors.white.withOpacity(0.15),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Column(
                  children: [
                    Text('$gradedCount/$totalSubjects',
                        style: const TextStyle(
                            fontSize: 20,
                            fontWeight: FontWeight.bold,
                            color: Colors.white)),
                    const Text('fan',
                        style: TextStyle(
                            fontSize: 11, color: Colors.white70)),
                    const SizedBox(height: 8),
                    Text('$totalCredits',
                        style: const TextStyle(
                            fontSize: 20,
                            fontWeight: FontWeight.bold,
                            color: Colors.white)),
                    const Text('kredit',
                        style: TextStyle(
                            fontSize: 11, color: Colors.white70)),
                  ],
                ),
              ),
            ],
          ),
          if (gradedCount > 0) ...[
            const SizedBox(height: 14),
            // Formula
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: Colors.white.withOpacity(0.12),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Text(
                'GPA = Σ(kredit × baho) ÷ Σ(kredit) = '
                '${(gradedCount > 0 ? (gpa * totalCredits).toStringAsFixed(0) : "0")}'
                ' ÷ $totalCredits = ${gpa.toStringAsFixed(2)}',
                style: const TextStyle(
                    fontSize: 11,
                    color: Colors.white70,
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
        color: Colors.white.withOpacity(0.2),
        borderRadius: BorderRadius.circular(6),
      ),
      child: Text(text,
          style: const TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w700,
              color: Colors.white)),
    );
  }

  Widget _buildScaleRow(bool isDark, Color sub) {
    return Row(
      children: [
        _scaleItem(5, "A'lo", '86-100', const Color(0xFF4CAF50), isDark),
        const SizedBox(width: 6),
        _scaleItem(4, 'Yaxshi', '71-85', const Color(0xFF29B6F6), isDark),
        const SizedBox(width: 6),
        _scaleItem(3, 'Qon.', '56-70', const Color(0xFFFF9800), isDark),
        const SizedBox(width: 6),
        _scaleItem(2, 'Qon-siz', '0-55', const Color(0xFFE53935), isDark),
      ],
    );
  }

  Widget _scaleItem(
      int grade, String label, String range, Color color, bool isDark) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 8),
        decoration: BoxDecoration(
          color: color.withOpacity(isDark ? 0.12 : 0.08),
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: color.withOpacity(0.2)),
        ),
        child: Column(
          children: [
            Text('$grade',
                style: TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.bold,
                    color: color)),
            Text(label,
                style: TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.w600,
                    color: color)),
            Text(range,
                style: TextStyle(
                    fontSize: 9,
                    color: color.withOpacity(0.7))),
          ],
        ),
      ),
    );
  }

  Widget _sectionTitle(String title, Color txt, String count) {
    return Row(
      children: [
        Text(title,
            style: TextStyle(
                fontSize: 15,
                fontWeight: FontWeight.w700,
                color: txt)),
        const Spacer(),
        Text(count,
            style: TextStyle(
                fontSize: 12,
                color: txt.withOpacity(0.5),
                fontWeight: FontWeight.w500)),
      ],
    );
  }

  Widget _buildSubjectCard(_SubjectGpa s, Color card, Color txt,
      Color sub, bool isDark) {
    final color = _gradeColor(s.grade5);

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      decoration: BoxDecoration(
        color: card,
        borderRadius: BorderRadius.circular(14),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.03),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: IntrinsicHeight(
        child: Row(
          children: [
            // Left color bar
            Container(
              width: 5,
              decoration: BoxDecoration(
                color: color,
                borderRadius: const BorderRadius.only(
                  topLeft: Radius.circular(14),
                  bottomLeft: Radius.circular(14),
                ),
              ),
            ),
            // Content
            Expanded(
              child: Padding(
                padding:
                    const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                child: Row(
                  children: [
                    // Subject info
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(s.name,
                              style: TextStyle(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w600,
                                  color: txt),
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis),
                          const SizedBox(height: 4),
                          Row(
                            children: [
                              Icon(Icons.stars_rounded,
                                  size: 12, color: sub),
                              const SizedBox(width: 3),
                              Text('${s.credit.toInt()} kredit',
                                  style: TextStyle(
                                      fontSize: 11, color: sub)),
                              const SizedBox(width: 12),
                              if (s.jn != null) ...[
                                Text('JN ',
                                    style: TextStyle(
                                        fontSize: 10,
                                        color: sub,
                                        fontWeight: FontWeight.w600)),
                                Text('${s.jn!.toInt()}',
                                    style: TextStyle(
                                        fontSize: 11,
                                        color: txt,
                                        fontWeight: FontWeight.w600)),
                                const SizedBox(width: 8),
                              ],
                              if (s.mt != null) ...[
                                Text('MT ',
                                    style: TextStyle(
                                        fontSize: 10,
                                        color: sub,
                                        fontWeight: FontWeight.w600)),
                                Text('${s.mt!.toInt()}',
                                    style: TextStyle(
                                        fontSize: 11,
                                        color: txt,
                                        fontWeight: FontWeight.w600)),
                              ],
                            ],
                          ),
                        ],
                      ),
                    ),
                    // Grade badge
                    Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Container(
                          width: 42,
                          height: 42,
                          decoration: BoxDecoration(
                            color:
                                color.withOpacity(isDark ? 0.2 : 0.1),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          alignment: Alignment.center,
                          child: Text('${s.grade5}',
                              style: TextStyle(
                                  fontSize: 22,
                                  fontWeight: FontWeight.bold,
                                  color: color)),
                        ),
                        const SizedBox(height: 2),
                        Text('${s.total!.toInt()}%',
                            style: TextStyle(
                                fontSize: 10,
                                fontWeight: FontWeight.w600,
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
    );
  }

  Widget _buildPendingCard(_SubjectGpa s, Color card, Color txt,
      Color sub, bool isDark) {
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        color: card,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
            color: isDark ? Colors.white10 : Colors.grey.shade200),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(s.name,
                    style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w500,
                        color: txt.withOpacity(0.6)),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis),
                const SizedBox(height: 2),
                Text('${s.credit.toInt()} kredit',
                    style: TextStyle(fontSize: 11, color: sub)),
              ],
            ),
          ),
          Container(
            padding:
                const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            decoration: BoxDecoration(
              color: isDark ? Colors.white10 : Colors.grey.shade100,
              borderRadius: BorderRadius.circular(8),
            ),
            child: Text('Kutilmoqda',
                style: TextStyle(
                    fontSize: 11,
                    color: sub,
                    fontWeight: FontWeight.w500)),
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
