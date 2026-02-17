import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/theme.dart';
import '../../providers/student_provider.dart';
import '../../widgets/loading_widget.dart';

class StudentGradesScreen extends StatefulWidget {
  const StudentGradesScreen({super.key});

  @override
  State<StudentGradesScreen> createState() => _StudentGradesScreenState();
}

class _StudentGradesScreenState extends State<StudentGradesScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<StudentProvider>().loadSubjects();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.backgroundColor,
      appBar: AppBar(
        title: const Text('Baholar'),
      ),
      body: Consumer<StudentProvider>(
        builder: (context, provider, _) {
          if (provider.isLoading && provider.subjects == null) {
            return const LoadingWidget();
          }

          final subjects = provider.subjects;
          if (subjects == null || subjects.isEmpty) {
            return Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Icon(Icons.school_outlined, size: 64, color: AppTheme.textSecondary),
                  const SizedBox(height: 16),
                  Text(
                    provider.error ?? 'Fanlar topilmadi',
                    style: const TextStyle(color: AppTheme.textSecondary),
                  ),
                  const SizedBox(height: 16),
                  ElevatedButton(
                    onPressed: () => provider.loadSubjects(),
                    child: const Text('Qayta yuklash'),
                  ),
                ],
              ),
            );
          }

          return RefreshIndicator(
            onRefresh: () => provider.loadSubjects(),
            child: ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: subjects.length,
              itemBuilder: (context, index) {
                final subject = subjects[index] as Map<String, dynamic>;
                return _SubjectGradeCard(subject: subject);
              },
            ),
          );
        },
      ),
    );
  }
}

class _SubjectGradeCard extends StatelessWidget {
  final Map<String, dynamic> subject;

  const _SubjectGradeCard({required this.subject});

  @override
  Widget build(BuildContext context) {
    final grades = subject['grades'] as Map<String, dynamic>? ?? {};

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: ExpansionTile(
        tilePadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
        childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
        leading: CircleAvatar(
          backgroundColor: AppTheme.primaryColor.withAlpha(25),
          child: Text(
            _getAverageGrade(grades),
            style: const TextStyle(
              fontWeight: FontWeight.bold,
              color: AppTheme.primaryColor,
              fontSize: 14,
            ),
          ),
        ),
        title: Text(
          subject['subject_name']?.toString() ?? '',
          style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
        ),
        subtitle: Text(
          subject['employee_name']?.toString() ?? '',
          style: const TextStyle(fontSize: 12, color: AppTheme.textSecondary),
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
        children: [
          const Divider(),
          if (grades['jn'] != null)
            _GradeRow(label: 'JN (Amaliyot)', value: grades['jn']),
          if (grades['mt'] != null)
            _GradeRow(label: 'MT (Mustaqil ta\'lim)', value: grades['mt']),
          if (grades['on'] != null)
            _GradeRow(label: 'ON (Oraliq nazorat)', value: grades['on']),
          if (grades['oski'] != null)
            _GradeRow(label: 'OSKI', value: grades['oski']),
          if (grades['test'] != null)
            _GradeRow(label: 'Test', value: grades['test']),
          if (grades['total'] != null) ...[
            const Divider(),
            _GradeRow(
              label: 'Jami',
              value: grades['total'],
              isBold: true,
            ),
          ],
        ],
      ),
    );
  }

  String _getAverageGrade(Map<String, dynamic> grades) {
    if (grades['total'] != null) return grades['total'].toString();
    return '-';
  }
}

class _GradeRow extends StatelessWidget {
  final String label;
  final dynamic value;
  final bool isBold;

  const _GradeRow({
    required this.label,
    required this.value,
    this.isBold = false,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: TextStyle(
              fontWeight: isBold ? FontWeight.bold : FontWeight.normal,
              color: isBold ? AppTheme.textPrimary : AppTheme.textSecondary,
            ),
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
            decoration: BoxDecoration(
              color: _getColor(value).withAlpha(25),
              borderRadius: BorderRadius.circular(8),
            ),
            child: Text(
              value?.toString() ?? '-',
              style: TextStyle(
                fontWeight: FontWeight.bold,
                color: _getColor(value),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Color _getColor(dynamic val) {
    if (val == null) return AppTheme.textSecondary;
    final v = val is num ? val.toDouble() : double.tryParse(val.toString()) ?? 0;
    if (v >= 86) return AppTheme.successColor;
    if (v >= 71) return AppTheme.primaryColor;
    if (v >= 56) return AppTheme.warningColor;
    return AppTheme.errorColor;
  }
}
