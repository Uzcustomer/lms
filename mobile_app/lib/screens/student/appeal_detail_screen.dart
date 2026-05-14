import 'dart:typed_data';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/theme.dart';
import '../../config/aurora_themes.dart';
import '../../providers/settings_provider.dart';
import '../../services/api_service.dart';
import '../../services/student_service.dart';

class AppealDetailScreen extends StatefulWidget {
  final int appealId;
  const AppealDetailScreen({super.key, required this.appealId});

  @override
  State<AppealDetailScreen> createState() => _AppealDetailScreenState();
}

class _AppealDetailScreenState extends State<AppealDetailScreen> {
  final _service = StudentService(ApiService());
  final _commentCtrl = TextEditingController();

  Map<String, dynamic>? _appeal;
  bool _loading = true;
  String? _error;

  Uint8List? _fileBytes;
  String? _fileName;
  bool _submittingComment = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _commentCtrl.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final res = await _service.getAppealDetail(widget.appealId);
      if (!mounted) return;
      setState(() {
        _appeal = res['data'] as Map<String, dynamic>?;
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

  Future<void> _pickFile() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'zip'],
      withData: true,
    );
    if (result != null && result.files.single.bytes != null) {
      setState(() {
        _fileBytes = result.files.single.bytes;
        _fileName = result.files.single.name;
      });
    }
  }

  Future<void> _submitComment() async {
    final text = _commentCtrl.text.trim();
    if (text.length < 3) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Izoh kamida 3 ta belgidan iborat bo\'lsin')),
      );
      return;
    }
    setState(() => _submittingComment = true);
    try {
      await _service.addAppealComment(
        appealId: widget.appealId,
        comment: text,
        fileBytes: _fileBytes,
        fileName: _fileName,
      );
      if (!mounted) return;
      _commentCtrl.clear();
      setState(() {
        _fileBytes = null;
        _fileName = null;
      });
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message), backgroundColor: const Color(0xFFDC2626)),
      );
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Xatolik yuz berdi'), backgroundColor: Color(0xFFDC2626)),
      );
    } finally {
      if (mounted) setState(() => _submittingComment = false);
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
                  onPressed: () => Navigator.pop(context, true),
                ),
                const Expanded(
                  child: Text(
                    'Apellyatsiya tafsilotlari',
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
                : _error != null || _appeal == null
                    ? Center(
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(_error ?? 'Topilmadi', style: TextStyle(color: subColor)),
                            const SizedBox(height: 12),
                            TextButton(onPressed: _load, child: const Text('Qayta yuklash')),
                          ],
                        ),
                      )
                    : _buildContent(cardColor, textColor, subColor, isDark),
          ),
          if (_appeal != null) _buildCommentBar(cardColor, textColor, subColor, isDark),
        ],
      ),
    );
  }

  Widget _buildContent(Color cardColor, Color textColor, Color subColor, bool isDark) {
    final appeal = _appeal!;
    final status = appeal['status'] as String? ?? 'pending';
    final color = _statusColor(status);
    final grade = (appeal['current_grade'] as num?) ?? 0;
    final newGrade = appeal['new_grade'] as num?;
    final comments = (appeal['comments'] as List?) ?? [];

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 16),
        children: [
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: color.withAlpha(15),
              border: Border.all(color: color.withAlpha(60)),
              borderRadius: BorderRadius.circular(14),
            ),
            child: Row(
              children: [
                Icon(
                  status == 'approved'
                      ? Icons.check_circle
                      : status == 'rejected'
                          ? Icons.cancel
                          : status == 'reviewing'
                              ? Icons.search
                              : Icons.hourglass_empty,
                  color: color,
                  size: 22,
                ),
                const SizedBox(width: 10),
                Text(
                  appeal['status_label'] ?? '',
                  style: TextStyle(fontSize: 14, fontWeight: FontWeight.w800, color: color),
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),
          _buildCard(
            cardColor,
            isDark,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  appeal['subject_name'] ?? '',
                  style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: textColor),
                ),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 8,
                  runSpacing: 6,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                        color: subColor.withAlpha(20),
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: Text(
                        appeal['training_type_name'] ?? '',
                        style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: subColor),
                      ),
                    ),
                    Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          '${grade.toStringAsFixed(grade == grade.toInt() ? 0 : 1)}',
                          style: TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w800,
                            color: _gradeColor(grade),
                          ),
                        ),
                        if (newGrade != null) ...[
                          const SizedBox(width: 6),
                          Icon(Icons.arrow_forward, size: 14, color: subColor),
                          const SizedBox(width: 6),
                          Text(
                            '${newGrade.toStringAsFixed(newGrade == newGrade.toInt() ? 0 : 1)}',
                            style: TextStyle(
                              fontSize: 16,
                              fontWeight: FontWeight.w800,
                              color: _gradeColor(newGrade),
                            ),
                          ),
                        ],
                      ],
                    ),
                  ],
                ),
                if (appeal['employee_name'] != null) ...[
                  const SizedBox(height: 10),
                  _infoRow(Icons.person_outline, appeal['employee_name'], subColor),
                ],
                if (appeal['exam_date'] != null) ...[
                  const SizedBox(height: 6),
                  _infoRow(Icons.calendar_today_outlined, appeal['exam_date'], subColor),
                ],
                const SizedBox(height: 6),
                _infoRow(Icons.access_time_outlined, 'Topshirilgan: ${appeal['created_at'] ?? ''}', subColor),
              ],
            ),
          ),
          const SizedBox(height: 12),
          _buildCard(
            cardColor,
            isDark,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Apellyatsiya sababi',
                    style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: subColor)),
                const SizedBox(height: 8),
                Text(
                  appeal['reason'] ?? '',
                  style: TextStyle(fontSize: 13, color: textColor, height: 1.5),
                ),
                if (appeal['has_file'] == true) ...[
                  const SizedBox(height: 10),
                  Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: const Color(0xFF7C3AED).withAlpha(15),
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: const Color(0xFF7C3AED).withAlpha(40)),
                    ),
                    child: Row(
                      children: [
                        const Icon(Icons.attach_file, size: 16, color: Color(0xFF7C3AED)),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            appeal['file_original_name'] ?? 'Fayl',
                            style: const TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
                              color: Color(0xFF7C3AED),
                            ),
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ],
            ),
          ),
          if (appeal['review_comment'] != null) ...[
            const SizedBox(height: 12),
            _buildCard(
              cardColor,
              isDark,
              accentColor: color,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Tekshiruv natijasi',
                    style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: color),
                  ),
                  const SizedBox(height: 6),
                  if (appeal['reviewed_by_name'] != null)
                    Text(
                      '${appeal['reviewed_by_name']} • ${appeal['reviewed_at'] ?? ''}',
                      style: TextStyle(fontSize: 11, color: subColor),
                    ),
                  const SizedBox(height: 8),
                  Text(
                    appeal['review_comment'],
                    style: TextStyle(fontSize: 13, color: textColor, height: 1.5),
                  ),
                ],
              ),
            ),
          ],
          const SizedBox(height: 16),
          Text(
            'Izohlar (${comments.length})',
            style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: textColor),
          ),
          const SizedBox(height: 8),
          if (comments.isEmpty)
            Text(
              'Hozircha izoh yo\'q',
              style: TextStyle(fontSize: 12, color: subColor),
            )
          else
            ...comments.map((c) => _buildComment(c as Map<String, dynamic>, cardColor, textColor, subColor, isDark)),
          const SizedBox(height: 16),
        ],
      ),
    );
  }

  Widget _buildComment(Map<String, dynamic> comment, Color cardColor, Color textColor, Color subColor, bool isDark) {
    final isAdmin = comment['user_type'] == 'admin';
    final accent = isAdmin ? const Color(0xFF2563EB) : const Color(0xFF7C3AED);

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 32,
            height: 32,
            decoration: BoxDecoration(
              color: accent.withAlpha(30),
              borderRadius: BorderRadius.circular(10),
            ),
            alignment: Alignment.center,
            child: Text(
              isAdmin ? 'A' : 'T',
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w800,
                color: accent,
              ),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: cardColor,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: isDark ? AppTheme.darkBorderColor : const Color(0xFFE2E8F0)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          comment['user_name'] ?? '',
                          style: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                            color: accent,
                          ),
                        ),
                      ),
                      Text(
                        comment['created_at'] ?? '',
                        style: TextStyle(fontSize: 10, color: subColor.withAlpha(150)),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Text(
                    comment['comment'] ?? '',
                    style: TextStyle(fontSize: 12, color: textColor, height: 1.5),
                  ),
                  if (comment['has_file'] == true) ...[
                    const SizedBox(height: 6),
                    Row(
                      children: [
                        Icon(Icons.attach_file, size: 13, color: subColor),
                        const SizedBox(width: 4),
                        Expanded(
                          child: Text(
                            comment['file_original_name'] ?? 'Fayl',
                            style: TextStyle(fontSize: 11, color: subColor),
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ],
                    ),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildCommentBar(Color cardColor, Color textColor, Color subColor, bool isDark) {
    return Container(
      padding: EdgeInsets.fromLTRB(12, 8, 12, 8 + MediaQuery.of(context).padding.bottom),
      decoration: BoxDecoration(
        color: cardColor,
        boxShadow: [
          BoxShadow(
            color: isDark ? Colors.black.withAlpha(60) : const Color(0xFF0F1B3D).withAlpha(15),
            blurRadius: 12,
            offset: const Offset(0, -3),
          ),
        ],
      ),
      child: Column(
        children: [
          if (_fileName != null)
            Container(
              margin: const EdgeInsets.only(bottom: 6),
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
              decoration: BoxDecoration(
                color: const Color(0xFF7C3AED).withAlpha(15),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: const Color(0xFF7C3AED).withAlpha(40)),
              ),
              child: Row(
                children: [
                  const Icon(Icons.attach_file, size: 14, color: Color(0xFF7C3AED)),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      _fileName!,
                      style: const TextStyle(fontSize: 11, color: Color(0xFF7C3AED), fontWeight: FontWeight.w600),
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  GestureDetector(
                    onTap: () => setState(() {
                      _fileBytes = null;
                      _fileName = null;
                    }),
                    child: Icon(Icons.close, size: 16, color: subColor),
                  ),
                ],
              ),
            ),
          Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              IconButton(
                icon: Icon(Icons.attach_file, color: subColor, size: 22),
                onPressed: _pickFile,
              ),
              Expanded(
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  decoration: BoxDecoration(
                    color: isDark ? Colors.white10 : const Color(0xFFF1F5F9),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: TextField(
                    controller: _commentCtrl,
                    maxLines: 4,
                    minLines: 1,
                    style: TextStyle(fontSize: 13, color: textColor),
                    decoration: InputDecoration(
                      isDense: true,
                      contentPadding: EdgeInsets.zero,
                      border: InputBorder.none,
                      hintText: 'Izoh yozish…',
                      hintStyle: TextStyle(fontSize: 12, color: subColor.withAlpha(170)),
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 6),
              GestureDetector(
                onTap: _submittingComment ? null : _submitComment,
                child: Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                      colors: [Color(0xFF8B5CF6), Color(0xFF7C3AED)],
                    ),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  alignment: Alignment.center,
                  child: _submittingComment
                      ? const SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            valueColor: AlwaysStoppedAnimation(Colors.white),
                          ),
                        )
                      : const Icon(Icons.send, size: 18, color: Colors.white),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildCard(Color cardColor, bool isDark, {required Widget child, Color? accentColor}) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: cardColor,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: accentColor != null
              ? accentColor.withAlpha(60)
              : isDark
                  ? Colors.white10
                  : const Color(0xFFE2E8F0),
        ),
        boxShadow: [
          BoxShadow(
            color: isDark ? Colors.black.withAlpha(40) : const Color(0xFF0F1B3D).withAlpha(8),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: child,
    );
  }

  Widget _infoRow(IconData icon, String text, Color color) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 14, color: color),
        const SizedBox(width: 6),
        Expanded(
          child: Text(text, style: TextStyle(fontSize: 12, color: color, height: 1.3)),
        ),
      ],
    );
  }
}
