import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:file_picker/file_picker.dart';
import '../../config/theme.dart';
import '../../config/api_config.dart';
import '../../providers/student_provider.dart';
import '../../services/api_service.dart';
import '../../l10n/app_localizations.dart';
import '../../widgets/loading_widget.dart';
import 'student_journal_screen.dart';

class StudentGradesScreen extends StatefulWidget {
  const StudentGradesScreen({super.key});

  @override
  State<StudentGradesScreen> createState() => _StudentGradesScreenState();
}

class _StudentGradesScreenState extends State<StudentGradesScreen> {
  int _expandedIndex = 0;
  bool _isUploading = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<StudentProvider>().loadSubjects();
    });
  }

  static const List<Color> _cardColors = [
    Color(0xFFE3F2FD), // JN - light blue
    Color(0xFFE8F5E9), // MT - light green
    Color(0xFFFFF3E0), // ON - light orange
    Color(0xFFF3E5F5), // OSKI - light purple
    Color(0xFFFCE4EC), // TEST - light pink
    Color(0xFFE0F2F1), // YN - light teal
  ];

  static const List<Color> _cardTextColors = [
    Color(0xFF1565C0), // JN
    Color(0xFF2E7D32), // MT
    Color(0xFFE65100), // ON
    Color(0xFF7B1FA2), // OSKI
    Color(0xFFC62828), // TEST
    Color(0xFF00695C), // YN
  ];

  static const List<IconData> _cardIcons = [
    Icons.assignment,
    Icons.menu_book,
    Icons.quiz,
    Icons.school,
    Icons.fact_check,
    Icons.emoji_events,
  ];

  // Accent colors for each subject accordion icon
  static const List<Color> _subjectAccentColors = [
    Color(0xFF1565C0), // blue
    Color(0xFF2E7D32), // green
    Color(0xFFE65100), // orange
    Color(0xFF7B1FA2), // purple
    Color(0xFFC62828), // red
    Color(0xFF00695C), // teal
    Color(0xFFF9A825), // amber
    Color(0xFF6A1B9A), // deep purple
  ];

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      backgroundColor: isDark ? AppTheme.darkBackground : AppTheme.backgroundColor,
      appBar: AppBar(
        title: Text(l.grades),
        centerTitle: true,
        leading: Navigator.canPop(context)
            ? IconButton(
                icon: const Icon(Icons.arrow_back),
                onPressed: () => Navigator.pop(context),
              )
            : const Padding(
                padding: EdgeInsets.all(12),
                child: Icon(Icons.account_balance, size: 28),
              ),
        actions: [
          IconButton(
            icon: const Icon(Icons.notifications_outlined),
            onPressed: () {},
          ),
        ],
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
                    Icon(Icons.school_outlined, size: 64,
                        color: isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary),
                    const SizedBox(height: 16),
                    Text(
                      provider.error ?? l.get('no_subjects'),
                      style: TextStyle(
                          color: isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary),
                    ),
                    const SizedBox(height: 16),
                    ElevatedButton(
                      onPressed: () => provider.loadSubjects(),
                      child: Text(l.reload),
                    ),
                  ],
                ),
              );
            }

            return RefreshIndicator(
              onRefresh: () => provider.loadSubjects(),
              child: ListView.separated(
                padding: const EdgeInsets.fromLTRB(12, 12, 12, 100),
                itemCount: subjects.length,
                separatorBuilder: (context, index) => const SizedBox(height: 8),
                itemBuilder: (context, index) {
                  final subject = subjects[index] as Map<String, dynamic>;
                  return _buildSubjectAccordion(context, subject, index, isDark, l);
                },
              ),
            );
          },
        ),
    );
  }

  Widget _buildSubjectAccordion(
    BuildContext context,
    Map<String, dynamic> subject,
    int index,
    bool isDark,
    AppLocalizations l,
  ) {
    final isExpanded = _expandedIndex == index;
    final grades = subject['grades'] as Map<String, dynamic>? ?? {};
    final cardColor = isDark ? AppTheme.darkCard : Colors.white;
    final textColor = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;
    final accentColor = _subjectAccentColors[index % _subjectAccentColors.length];

    final headerColor = isExpanded
        ? (isDark ? AppTheme.darkSurface : AppTheme.primaryColor)
        : cardColor;
    final headerTextColor = isExpanded ? Colors.white : textColor;
    final arrowColor = isExpanded
        ? Colors.white70
        : (isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary);

    return Container(
      decoration: BoxDecoration(
        color: cardColor,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withAlpha(isDark ? 30 : 10),
            blurRadius: 6,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        children: [
          InkWell(
            onTap: () {
              setState(() {
                _expandedIndex = isExpanded ? -1 : index;
              });
            },
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 200),
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
              decoration: BoxDecoration(
                color: headerColor,
                borderRadius: isExpanded
                    ? const BorderRadius.vertical(top: Radius.circular(16))
                    : BorderRadius.circular(16),
              ),
              child: Row(
                children: [
                  // Big icon with bg color
                  Container(
                    width: 44,
                    height: 44,
                    decoration: BoxDecoration(
                      color: accentColor.withAlpha(isDark ? 40 : 25),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Icon(
                      Icons.menu_book_rounded,
                      size: 24,
                      color: accentColor,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      subject['subject_name']?.toString() ?? '',
                      style: TextStyle(
                        fontWeight: FontWeight.w600,
                        fontSize: 14,
                        color: headerTextColor,
                      ),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  const SizedBox(width: 8),
                  AnimatedRotation(
                    turns: isExpanded ? 0.5 : 0,
                    duration: const Duration(milliseconds: 200),
                    child: Icon(
                      Icons.keyboard_arrow_down,
                      color: arrowColor,
                    ),
                  ),
                ],
              ),
            ),
          ),
          AnimatedCrossFade(
            firstChild: const SizedBox(width: double.infinity),
            secondChild: _buildExpandedContent(context, subject, grades, isDark, l),
            crossFadeState: isExpanded ? CrossFadeState.showSecond : CrossFadeState.showFirst,
            duration: const Duration(milliseconds: 200),
          ),
        ],
      ),
    );
  }

  Widget _buildExpandedContent(
    BuildContext context,
    Map<String, dynamic> subject,
    Map<String, dynamic> grades,
    bool isDark,
    AppLocalizations l,
  ) {
    final divColor = isDark ? AppTheme.darkDivider : AppTheme.dividerColor;

    final gradeEntries = [
      {'key': 'jn', 'label': 'JN', 'fullLabel': 'Joriy nazorat'},
      {'key': 'mt', 'label': 'MT', 'fullLabel': 'Mustaqil ta\'lim'},
      {'key': 'on', 'label': 'ON', 'fullLabel': 'Oraliq nazorat'},
      {'key': 'oski', 'label': 'OSKI', 'fullLabel': 'OSKI'},
      {'key': 'test', 'label': 'TEST', 'fullLabel': 'Test'},
      {'key': 'total', 'label': 'YN', 'fullLabel': 'Yakuniy'},
    ];

    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 0, 12, 14),
      child: Column(
        children: [
          Divider(color: divColor, height: 1),
          const SizedBox(height: 8),

          // Grade cards - 3 columns (compact)
          GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 3,
              crossAxisSpacing: 5,
              mainAxisSpacing: 5,
              childAspectRatio: 1.6,
            ),
            itemCount: gradeEntries.length,
            itemBuilder: (context, i) {
              final entry = gradeEntries[i];
              final value = grades[entry['key']];
              return _buildGradeCard(
                label: entry['label'] as String,
                value: value,
                color: _cardColors[i],
                textColor: _cardTextColors[i],
                isDark: isDark,
              );
            },
          ),

          const SizedBox(height: 6),

          // MT submission info (moved up)
          if (subject['mt_submission'] != null) ...[
            _buildMtInfo(context, subject['mt_submission'] as Map<String, dynamic>, isDark, l),
            const SizedBox(height: 6),
          ],

          // Buttons
          Row(
            children: [
              Expanded(
                child: ElevatedButton.icon(
                  onPressed: _canUploadMT(subject) && !_isUploading
                      ? () => _uploadMT(context, subject)
                      : null,
                  icon: _isUploading
                      ? const SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                        )
                      : Icon(
                          _hasMtSubmission(subject) ? Icons.refresh : Icons.upload_file,
                          size: 18,
                        ),
                  label: Text(
                    _isUploading
                        ? l.get('uploading')
                        : _hasMtSubmission(subject)
                            ? l.get('mt_reupload')
                            : l.get('mt_upload'),
                    style: const TextStyle(fontSize: 13),
                  ),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF2E7D32),
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                    padding: const EdgeInsets.symmetric(vertical: 10),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: ElevatedButton.icon(
                  onPressed: () {
                    final subjectId = subject['subject_id'];
                    final subjectName = subject['subject_name']?.toString() ?? '';
                    if (subjectId != null) {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => StudentJournalScreen(
                            subjectId: subjectId is int ? subjectId : int.parse(subjectId.toString()),
                            subjectName: subjectName,
                          ),
                        ),
                      );
                    }
                  },
                  icon: const Icon(Icons.info_outline, size: 18),
                  label: Text(l.get('details'), style: const TextStyle(fontSize: 13)),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.primaryColor,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                    padding: const EdgeInsets.symmetric(vertical: 10),
                  ),
                ),
              ),
            ],
          ),

          const SizedBox(height: 8),

          // Davomat (moved to bottom)
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: isDark ? AppTheme.darkSurface : const Color(0xFFF5F5F5),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      l.get('attendance'),
                      style: TextStyle(
                        fontWeight: FontWeight.w600,
                        fontSize: 13,
                        color: isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary,
                      ),
                    ),
                    Text(
                      '${_getAttendancePercent(subject).toStringAsFixed(1)}%',
                      style: TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 14,
                        color: _getAttendanceColor(_getAttendancePercent(subject)),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 6),
                ClipRRect(
                  borderRadius: BorderRadius.circular(4),
                  child: LinearProgressIndicator(
                    value: _getAttendancePercent(subject) / 100,
                    backgroundColor: isDark ? AppTheme.darkDivider : const Color(0xFFE0E0E0),
                    valueColor: AlwaysStoppedAnimation<Color>(
                      _getAttendanceColor(_getAttendancePercent(subject)),
                    ),
                    minHeight: 6,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  '${l.get("absent_hours_label")}: ${subject['absent_hours'] ?? 0} / ${subject['auditorium_hours'] ?? 0} ${l.get("hours")}',
                  style: TextStyle(
                    fontSize: 11,
                    color: isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildGradeCard({
    required String label,
    required dynamic value,
    required Color color,
    required Color textColor,
    required bool isDark,
  }) {
    final displayValue = value?.toString() ?? '-';

    return Container(
      decoration: BoxDecoration(
        color: isDark ? color.withAlpha(30) : color,
        borderRadius: BorderRadius.circular(10),
      ),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Text(
            label,
            style: TextStyle(
              fontSize: 10,
              fontWeight: FontWeight.w600,
              color: textColor,
            ),
          ),
          const SizedBox(height: 1),
          Text(
            displayValue,
            style: TextStyle(
              fontSize: 17,
              fontWeight: FontWeight.bold,
              color: textColor,
            ),
          ),
        ],
      ),
    );
  }

  double _getAttendancePercent(Map<String, dynamic> subject) {
    final davPercent = (subject['dav_percent'] is num)
        ? (subject['dav_percent'] as num).toDouble()
        : 0.0;
    return (100 - davPercent).clamp(0.0, 100.0);
  }

  Color _getAttendanceColor(double percent) {
    if (percent >= 85) return AppTheme.successColor;
    if (percent >= 70) return AppTheme.warningColor;
    return AppTheme.errorColor;
  }

  bool _canUploadMT(Map<String, dynamic> subject) {
    final mt = subject['mt_submission'] as Map<String, dynamic>?;
    if (mt == null) return false;
    return mt['can_submit'] == true;
  }

  bool _hasMtSubmission(Map<String, dynamic> subject) {
    final mt = subject['mt_submission'] as Map<String, dynamic>?;
    if (mt == null) return false;
    return mt['has_submission'] == true;
  }

  Widget _buildMtInfo(BuildContext context, Map<String, dynamic> mt, bool isDark, AppLocalizations l) {
    final hasSubmission = mt['has_submission'] == true;
    final isOverdue = mt['is_overdue'] == true;
    final gradeLocked = mt['grade_locked'] == true;
    final grade = mt['grade'];
    final deadline = mt['deadline']?.toString() ?? '';
    final fileName = mt['file_name']?.toString();
    final remaining = mt['remaining_attempts'] ?? 0;

    Color statusColor;
    String statusText;
    IconData statusIcon;

    if (gradeLocked) {
      statusColor = AppTheme.successColor;
      statusText = '${l.get("mt_graded")}: $grade';
      statusIcon = Icons.check_circle;
    } else if (hasSubmission && grade != null) {
      statusColor = AppTheme.warningColor;
      statusText = '${l.get("mt_graded")}: $grade (${l.get("mt_remaining")}: $remaining)';
      statusIcon = Icons.warning;
    } else if (hasSubmission) {
      statusColor = AppTheme.accentColor;
      statusText = l.get('mt_uploaded');
      statusIcon = Icons.cloud_done;
    } else if (isOverdue) {
      statusColor = AppTheme.errorColor;
      statusText = l.get('mt_overdue');
      statusIcon = Icons.error;
    } else {
      statusColor = AppTheme.textSecondary;
      statusText = l.get('mt_not_uploaded');
      statusIcon = Icons.cloud_upload_outlined;
    }

    return Container(
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: statusColor.withAlpha(isDark ? 30 : 20),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: statusColor.withAlpha(60)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(statusIcon, size: 16, color: statusColor),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  statusText,
                  style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: statusColor),
                ),
              ),
            ],
          ),
          const SizedBox(height: 4),
          Text(
            '${l.get("mt_deadline")}: $deadline ${mt['deadline_time'] ?? ''}',
            style: TextStyle(fontSize: 11, color: isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary),
          ),
          if (fileName != null)
            Padding(
              padding: const EdgeInsets.only(top: 2),
              child: Text(
                fileName,
                style: TextStyle(fontSize: 11, color: isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ),
        ],
      ),
    );
  }

  Future<void> _uploadMT(BuildContext context, Map<String, dynamic> subject) async {
    try {
      final result = await FilePicker.platform.pickFiles(
        type: FileType.custom,
        allowedExtensions: ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'zip', 'rar'],
        withData: true,
      );

      if (result == null || result.files.isEmpty) return;

      final file = result.files.first;
      if (file.bytes == null) return;

      setState(() => _isUploading = true);

      final apiService = ApiService();
      final subjectId = subject['subject_id'];

      await apiService.uploadFile(
        '${ApiConfig.studentSubjects}/$subjectId/mt-upload',
        file.bytes!,
        file.name,
      );

      if (mounted) {
        setState(() => _isUploading = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(AppLocalizations.of(context).get('upload_success')),
            backgroundColor: AppTheme.successColor,
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        setState(() => _isUploading = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(e.toString()),
            backgroundColor: AppTheme.errorColor,
          ),
        );
      }
    }
  }
}
