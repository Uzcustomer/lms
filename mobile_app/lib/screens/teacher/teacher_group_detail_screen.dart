import 'package:flutter/material.dart';
import '../../config/theme.dart';
import '../../l10n/app_localizations.dart';
import '../../services/teacher_service.dart';
import '../../services/api_service.dart';
import '../../widgets/loading_widget.dart';
import 'teacher_journal_screen.dart';

class TeacherGroupDetailScreen extends StatefulWidget {
  final int groupId;
  final String groupName;
  final String? departmentName;

  const TeacherGroupDetailScreen({
    super.key,
    required this.groupId,
    required this.groupName,
    this.departmentName,
  });

  @override
  State<TeacherGroupDetailScreen> createState() => _TeacherGroupDetailScreenState();
}

class _TeacherGroupDetailScreenState extends State<TeacherGroupDetailScreen> {
  final TeacherService _service = TeacherService(ApiService());

  bool _isLoadingSemesters = true;
  bool _isLoadingSubjects = false;
  String? _error;

  List<Map<String, dynamic>> _semesters = [];
  List<Map<String, dynamic>> _subjects = [];
  int? _selectedSemesterId;
  String? _selectedSemesterCode;
  String? _selectedSemesterName;

  @override
  void initState() {
    super.initState();
    _loadSemesters();
  }

  Future<void> _loadSemesters() async {
    setState(() {
      _isLoadingSemesters = true;
      _error = null;
    });

    try {
      final response = await _service.getSemesters(groupId: widget.groupId);
      final data = (response['data'] as List<dynamic>?) ?? [];
      _semesters = data.cast<Map<String, dynamic>>();

      // Auto-select current semester or first
      Map<String, dynamic>? current;
      for (final s in _semesters) {
        if (s['current'] == true) {
          current = s;
          break;
        }
      }
      current ??= _semesters.isNotEmpty ? _semesters.first : null;

      if (current != null) {
        _selectedSemesterId = current['id'] is int
            ? current['id']
            : int.tryParse(current['id'].toString());
        _selectedSemesterCode = current['code']?.toString();
        _selectedSemesterName = current['name']?.toString();
        _loadSubjects();
      }

      setState(() => _isLoadingSemesters = false);
    } catch (e) {
      setState(() {
        _error = e.toString();
        _isLoadingSemesters = false;
      });
    }
  }

  Future<void> _loadSubjects() async {
    if (_selectedSemesterId == null) return;

    setState(() {
      _isLoadingSubjects = true;
      _subjects = [];
    });

    try {
      final response = await _service.getSubjects(
        groupId: widget.groupId,
        semesterId: _selectedSemesterId!,
      );
      final data = (response['data'] as List<dynamic>?) ?? [];
      setState(() {
        _subjects = data.cast<Map<String, dynamic>>();
        _isLoadingSubjects = false;
      });
    } catch (e) {
      setState(() {
        _error = e.toString();
        _isLoadingSubjects = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      backgroundColor: isDark ? AppTheme.darkBackground : AppTheme.backgroundColor,
      appBar: AppBar(
        title: Text(
          widget.groupName,
          style: const TextStyle(fontSize: 16),
        ),
        centerTitle: true,
      ),
      body: _isLoadingSemesters
          ? const LoadingWidget()
          : _error != null && _semesters.isEmpty
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(_error!),
                      const SizedBox(height: 16),
                      ElevatedButton(
                        onPressed: _loadSemesters,
                        child: Text(l.reload),
                      ),
                    ],
                  ),
                )
              : Column(
                  children: [
                    // Current semester info
                    if (_selectedSemesterName != null)
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.fromLTRB(16, 10, 16, 6),
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                          decoration: BoxDecoration(
                            color: AppTheme.primaryColor.withAlpha(isDark ? 30 : 15),
                            borderRadius: BorderRadius.circular(10),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              const Icon(Icons.calendar_today, size: 14, color: AppTheme.primaryColor),
                              const SizedBox(width: 6),
                              Text(
                                _selectedSemesterName!,
                                style: const TextStyle(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w600,
                                  color: AppTheme.primaryColor,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),

                    // Subjects list
                    Expanded(
                      child: _isLoadingSubjects
                          ? const LoadingWidget()
                          : _subjects.isEmpty
                              ? Center(
                                  child: Text(
                                    l.noSubjects,
                                    style: TextStyle(
                                      color: isDark
                                          ? AppTheme.darkTextSecondary
                                          : AppTheme.textSecondary,
                                    ),
                                  ),
                                )
                              : RefreshIndicator(
                                  onRefresh: _loadSubjects,
                                  child: ListView.builder(
                                    padding: const EdgeInsets.fromLTRB(12, 4, 12, 100),
                                    itemCount: _subjects.length,
                                    itemBuilder: (context, index) {
                                      final subject = _subjects[index];
                                      return _buildSubjectCard(subject, index, isDark);
                                    },
                                  ),
                                ),
                    ),
                  ],
                ),
    );
  }

  Widget _buildSubjectCard(Map<String, dynamic> subject, int index, bool isDark) {
    final name = subject['subject_name']?.toString() ?? '';
    final credit = subject['credit'];
    final subjectId = subject['subject_id']?.toString() ?? subject['id'].toString();

    final colors = [
      const Color(0xFF1565C0),
      const Color(0xFF2E7D32),
      const Color(0xFFE65100),
      const Color(0xFF7B1FA2),
      const Color(0xFFC62828),
      const Color(0xFF00695C),
      const Color(0xFFF9A825),
      const Color(0xFF6A1B9A),
    ];
    final color = colors[index % colors.length];

    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      color: isDark ? AppTheme.darkCard : Colors.white,
      child: ListTile(
        contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
        leading: Container(
          width: 44,
          height: 44,
          decoration: BoxDecoration(
            color: color.withAlpha(isDark ? 40 : 25),
            borderRadius: BorderRadius.circular(12),
          ),
          child: Icon(Icons.menu_book_rounded, size: 22, color: color),
        ),
        title: Text(
          name,
          style: TextStyle(
            fontWeight: FontWeight.w600,
            fontSize: 14,
            color: isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary,
          ),
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
        ),
        subtitle: credit != null
            ? Text(
                '$credit kredit',
                style: TextStyle(
                  fontSize: 12,
                  color: isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary,
                ),
              )
            : null,
        trailing: Icon(
          Icons.chevron_right,
          color: isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary,
        ),
        onTap: () {
          Navigator.push(
            context,
            MaterialPageRoute(
              builder: (_) => TeacherJournalScreen(
                groupId: widget.groupId,
                groupName: widget.groupName,
                subjectId: subjectId,
                semesterCode: _selectedSemesterCode ?? '',
                subjectName: name,
              ),
            ),
          );
        },
      ),
    );
  }
}
