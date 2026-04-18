import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/theme.dart';
import '../../l10n/app_localizations.dart';
import '../../providers/student_provider.dart';
import '../../widgets/loading_widget.dart';
import 'absence_excuse_create_screen.dart';
import 'absence_excuse_detail_screen.dart';

class AbsenceExcuseListScreen extends StatefulWidget {
  const AbsenceExcuseListScreen({super.key});

  @override
  State<AbsenceExcuseListScreen> createState() => _AbsenceExcuseListScreenState();
}

class _AbsenceExcuseListScreenState extends State<AbsenceExcuseListScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<StudentProvider>().loadExcuses();
    });
  }

  Color _statusColor(String status) {
    switch (status) {
      case 'approved':
        return AppTheme.successColor;
      case 'rejected':
        return AppTheme.errorColor;
      default:
        return AppTheme.warningColor;
    }
  }

  IconData _statusIcon(String status) {
    switch (status) {
      case 'approved':
        return Icons.check_circle;
      case 'rejected':
        return Icons.cancel;
      default:
        return Icons.hourglass_empty;
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bgColor = isDark ? AppTheme.darkBackground : AppTheme.backgroundColor;

    return Scaffold(
      backgroundColor: bgColor,
      appBar: AppBar(
        title: Text(l.absenceExcuse),
        centerTitle: true,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () async {
          final result = await Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => const AbsenceExcuseCreateScreen()),
          );
          if (result == true && mounted) {
            context.read<StudentProvider>().loadExcuses();
          }
        },
        backgroundColor: AppTheme.primaryColor,
        foregroundColor: Colors.white,
        icon: const Icon(Icons.add),
        label: Text(l.newExcuse),
      ),
      body: Consumer<StudentProvider>(
        builder: (context, provider, _) {
          if (provider.isLoading && provider.excuses == null) {
            return const LoadingWidget();
          }

          final excuses = provider.excuses ?? [];

          if (excuses.isEmpty) {
            return Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.description_outlined, size: 64,
                      color: isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary),
                  const SizedBox(height: 16),
                  Text(l.noExcuses,
                      style: TextStyle(
                        fontSize: 16,
                        color: isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary,
                      )),
                ],
              ),
            );
          }

          return RefreshIndicator(
            onRefresh: () => provider.loadExcuses(),
            child: ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: excuses.length,
              itemBuilder: (context, index) {
                final excuse = excuses[index] as Map<String, dynamic>;
                return _ExcuseCard(
                  excuse: excuse,
                  isDark: isDark,
                  statusColor: _statusColor(excuse['status'] ?? ''),
                  statusIcon: _statusIcon(excuse['status'] ?? ''),
                  onTap: () async {
                    await Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => AbsenceExcuseDetailScreen(excuseId: excuse['id'] as int),
                      ),
                    );
                    if (mounted) {
                      context.read<StudentProvider>().loadExcuses();
                    }
                  },
                );
              },
            ),
          );
        },
      ),
    );
  }
}

class _ExcuseCard extends StatelessWidget {
  final Map<String, dynamic> excuse;
  final bool isDark;
  final Color statusColor;
  final IconData statusIcon;
  final VoidCallback onTap;

  const _ExcuseCard({
    required this.excuse,
    required this.isDark,
    required this.statusColor,
    required this.statusIcon,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final cardColor = isDark ? AppTheme.darkCard : AppTheme.surfaceColor;
    final textColor = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;
    final subColor = isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;

    return Card(
      color: cardColor,
      margin: const EdgeInsets.only(bottom: 12),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      elevation: isDark ? 0 : 2,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      excuse['reason_label'] ?? '',
                      style: TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w600,
                        color: textColor,
                      ),
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(
                      color: statusColor.withAlpha(25),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(statusIcon, size: 14, color: statusColor),
                        const SizedBox(width: 4),
                        Text(
                          excuse['status_label'] ?? '',
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                            color: statusColor,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              Row(
                children: [
                  Icon(Icons.calendar_today, size: 14, color: subColor),
                  const SizedBox(width: 6),
                  Text(
                    '${excuse['start_date']} — ${excuse['end_date']}',
                    style: TextStyle(fontSize: 13, color: subColor),
                  ),
                ],
              ),
              const SizedBox(height: 4),
              Row(
                children: [
                  Icon(Icons.access_time, size: 14, color: subColor),
                  const SizedBox(width: 6),
                  Text(
                    excuse['created_at'] ?? '',
                    style: TextStyle(fontSize: 12, color: subColor),
                  ),
                  if (excuse['doc_number'] != null) ...[
                    const Spacer(),
                    Text(
                      '#${excuse['doc_number']}',
                      style: TextStyle(fontSize: 12, color: subColor),
                    ),
                  ],
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
