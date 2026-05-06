import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/theme.dart';
import '../../config/aurora_themes.dart';
import '../../providers/settings_provider.dart';
import '../../services/api_service.dart';
import '../../services/student_service.dart';
import '../../utils/page_transitions.dart';
import 'appeal_create_screen.dart';
import 'appeal_detail_screen.dart';

class AppealsListScreen extends StatefulWidget {
  const AppealsListScreen({super.key});

  @override
  State<AppealsListScreen> createState() => _AppealsListScreenState();
}

class _AppealsListScreenState extends State<AppealsListScreen> {
  final _service = StudentService(ApiService());
  List<dynamic> _appeals = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    setState(() { _loading = true; _error = null; });
    try {
      final res = await _service.getAppeals();
      if (!mounted) return;
      setState(() {
        _appeals = res['data'] as List? ?? [];
        _loading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _error = "Ma'lumotlarni yuklashda xatolik";
        _loading = false;
      });
    }
  }

  Color _statusColor(String status) {
    switch (status) {
      case 'approved':
        return const Color(0xFF16A34A);
      case 'rejected':
        return const Color(0xFFDC2626);
      case 'reviewing':
        return const Color(0xFF2563EB);
      default:
        return const Color(0xFFF59E0B);
    }
  }

  Color _gradeColor(num grade) {
    if (grade >= 86) return const Color(0xFF16A34A);
    if (grade >= 71) return const Color(0xFF2563EB);
    if (grade >= 60) return const Color(0xFFF59E0B);
    return const Color(0xFFDC2626);
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final aurora = context.watch<SettingsProvider>().auroraTheme;
    final statusBarH = MediaQuery.of(context).padding.top;
    final cardColor = isDark ? AppTheme.darkCard : Colors.white;
    final textColor = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;
    final subColor = isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;

    return Scaffold(
      backgroundColor: auroraBase(aurora, isDark),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () async {
          final result = await Navigator.push(
            context,
            SlideFadePageRoute(builder: (_) => const AppealCreateScreen()),
          );
          if (result == true && mounted) _loadData();
        },
        backgroundColor: const Color(0xFF7C3AED),
        foregroundColor: Colors.white,
        icon: const Icon(Icons.add),
        label: const Text('Yangi apellyatsiya', style: TextStyle(fontWeight: FontWeight.w700)),
      ),
      body: Column(
        children: [
          Container(
            padding: EdgeInsets.only(top: statusBarH, left: 4, right: 4),
            height: statusBarH + 64,
            decoration: const BoxDecoration(
              color: Color(0xFF1E3A8A),
              borderRadius: BorderRadius.only(
                bottomLeft: Radius.circular(18),
                bottomRight: Radius.circular(18),
              ),
            ),
            child: Row(
              children: [
                IconButton(
                  icon: const Icon(Icons.arrow_back, color: Colors.white, size: 22),
                  onPressed: () => Navigator.pop(context),
                ),
                const Expanded(
                  child: Text(
                    'Apellyatsiya',
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: Colors.white),
                    textAlign: TextAlign.center,
                  ),
                ),
                const SizedBox(width: 48),
              ],
            ),
          ),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _error != null
                    ? Center(
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(_error!, style: TextStyle(color: subColor)),
                            const SizedBox(height: 12),
                            TextButton(onPressed: _loadData, child: const Text('Qayta yuklash')),
                          ],
                        ),
                      )
                    : _appeals.isEmpty
                        ? Center(
                            child: Column(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Icon(Icons.gavel_outlined, size: 64, color: subColor.withAlpha(80)),
                                const SizedBox(height: 12),
                                Text(
                                  "Hali apellyatsiya topshirilmagan",
                                  style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: subColor),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  "24 soat ichidagi baholarga apellyatsiya topshirish mumkin",
                                  textAlign: TextAlign.center,
                                  style: TextStyle(fontSize: 12, color: subColor.withAlpha(160)),
                                ),
                              ],
                            ),
                          )
                        : RefreshIndicator(
                            onRefresh: _loadData,
                            child: ListView.builder(
                              padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
                              itemCount: _appeals.length,
                              itemBuilder: (context, index) {
                                final appeal = _appeals[index] as Map<String, dynamic>;
                                final status = appeal['status'] as String? ?? 'pending';
                                final color = _statusColor(status);
                                final grade = (appeal['current_grade'] as num?) ?? 0;
                                final newGrade = appeal['new_grade'] as num?;

                                return Padding(
                                  padding: const EdgeInsets.only(bottom: 12),
                                  child: Material(
                                    color: cardColor,
                                    borderRadius: BorderRadius.circular(14),
                                    child: InkWell(
                                      borderRadius: BorderRadius.circular(14),
                                      onTap: () async {
                                        final id = appeal['id'] as int;
                                        final result = await Navigator.push(
                                          context,
                                          SlideFadePageRoute(
                                            builder: (_) => AppealDetailScreen(appealId: id),
                                          ),
                                        );
                                        if (result == true && mounted) _loadData();
                                      },
                                      child: Container(
                                        padding: const EdgeInsets.all(14),
                                        decoration: BoxDecoration(
                                          borderRadius: BorderRadius.circular(14),
                                          border: Border.all(color: isDark ? Colors.white10 : const Color(0xFFE2E8F0)),
                                          boxShadow: [
                                            BoxShadow(
                                              color: isDark
                                                  ? Colors.black.withAlpha(40)
                                                  : const Color(0xFF0F1B3D).withAlpha(8),
                                              blurRadius: 10,
                                              offset: const Offset(0, 4),
                                            ),
                                          ],
                                        ),
                                        child: Column(
                                          crossAxisAlignment: CrossAxisAlignment.start,
                                          children: [
                                            Row(
                                              children: [
                                                Expanded(
                                                  child: Text(
                                                    appeal['subject_name'] ?? '',
                                                    style: TextStyle(
                                                      fontSize: 14,
                                                      fontWeight: FontWeight.w700,
                                                      color: textColor,
                                                    ),
                                                  ),
                                                ),
                                                Container(
                                                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                                  decoration: BoxDecoration(
                                                    color: color.withAlpha(20),
                                                    border: Border.all(color: color.withAlpha(80)),
                                                    borderRadius: BorderRadius.circular(8),
                                                  ),
                                                  child: Text(
                                                    appeal['status_label'] ?? '',
                                                    style: TextStyle(
                                                      fontSize: 11,
                                                      fontWeight: FontWeight.w700,
                                                      color: color,
                                                    ),
                                                  ),
                                                ),
                                              ],
                                            ),
                                            const SizedBox(height: 8),
                                            Row(
                                              children: [
                                                Container(
                                                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                                  decoration: BoxDecoration(
                                                    color: subColor.withAlpha(20),
                                                    borderRadius: BorderRadius.circular(6),
                                                  ),
                                                  child: Text(
                                                    appeal['training_type_name'] ?? '',
                                                    style: TextStyle(
                                                      fontSize: 11,
                                                      fontWeight: FontWeight.w600,
                                                      color: subColor,
                                                    ),
                                                  ),
                                                ),
                                                const SizedBox(width: 8),
                                                Text(
                                                  '${grade.toStringAsFixed(grade == grade.toInt() ? 0 : 1)} ball',
                                                  style: TextStyle(
                                                    fontSize: 12,
                                                    fontWeight: FontWeight.w700,
                                                    color: _gradeColor(grade),
                                                  ),
                                                ),
                                                if (newGrade != null) ...[
                                                  const SizedBox(width: 6),
                                                  Icon(Icons.arrow_forward, size: 12, color: subColor),
                                                  const SizedBox(width: 6),
                                                  Text(
                                                    '${newGrade.toStringAsFixed(newGrade == newGrade.toInt() ? 0 : 1)} ball',
                                                    style: TextStyle(
                                                      fontSize: 12,
                                                      fontWeight: FontWeight.w700,
                                                      color: _gradeColor(newGrade),
                                                    ),
                                                  ),
                                                ],
                                              ],
                                            ),
                                            if (appeal['employee_name'] != null) ...[
                                              const SizedBox(height: 6),
                                              Row(
                                                children: [
                                                  Icon(Icons.person_outline, size: 13, color: subColor),
                                                  const SizedBox(width: 4),
                                                  Expanded(
                                                    child: Text(
                                                      appeal['employee_name'],
                                                      style: TextStyle(fontSize: 11, color: subColor),
                                                    ),
                                                  ),
                                                ],
                                              ),
                                            ],
                                            const SizedBox(height: 6),
                                            Text(
                                              'Topshirilgan: ${appeal['created_at'] ?? ''}',
                                              style: TextStyle(fontSize: 10, color: subColor.withAlpha(150)),
                                            ),
                                          ],
                                        ),
                                      ),
                                    ),
                                  ),
                                );
                              },
                            ),
                          ),
          ),
        ],
      ),
    );
  }
}
