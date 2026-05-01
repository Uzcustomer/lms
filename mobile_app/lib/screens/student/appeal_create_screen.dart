import 'dart:typed_data';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/theme.dart';
import '../../config/aurora_themes.dart';
import '../../providers/settings_provider.dart';
import '../../services/api_service.dart';
import '../../services/student_service.dart';

class AppealCreateScreen extends StatefulWidget {
  const AppealCreateScreen({super.key});

  @override
  State<AppealCreateScreen> createState() => _AppealCreateScreenState();
}

class _AppealCreateScreenState extends State<AppealCreateScreen> {
  final _service = StudentService(ApiService());
  final _formKey = GlobalKey<FormState>();
  final _reasonCtrl = TextEditingController();

  List<dynamic> _grades = [];
  bool _loading = true;
  String? _loadError;

  Map<String, dynamic>? _selectedGrade;
  Uint8List? _fileBytes;
  String? _fileName;

  bool _submitting = false;
  String? _submitError;

  @override
  void initState() {
    super.initState();
    _loadGrades();
  }

  @override
  void dispose() {
    _reasonCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadGrades() async {
    setState(() { _loading = true; _loadError = null; });
    try {
      final res = await _service.getAppealAvailableGrades();
      if (!mounted) return;
      setState(() {
        _grades = res['data'] as List? ?? [];
        _loading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _loadError = "Baholarni yuklashda xatolik";
        _loading = false;
      });
    }
  }

  Future<void> _pickFile() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['pdf', 'jpg', 'jpeg', 'png'],
      withData: true,
    );
    if (result != null && result.files.single.bytes != null) {
      setState(() {
        _fileBytes = result.files.single.bytes;
        _fileName = result.files.single.name;
      });
    }
  }

  Future<void> _submit() async {
    setState(() => _submitError = null);
    if (_selectedGrade == null) {
      setState(() => _submitError = "Bahoni tanlang");
      return;
    }
    if (!(_formKey.currentState?.validate() ?? false)) return;

    setState(() => _submitting = true);
    try {
      final res = await _service.submitAppeal(
        studentGradeId: _selectedGrade!['id'] as int,
        reason: _reasonCtrl.text.trim(),
        fileBytes: _fileBytes,
        fileName: _fileName,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(res['message'] ?? 'Apellyatsiya topshirildi'),
          backgroundColor: const Color(0xFF16A34A),
        ),
      );
      Navigator.pop(context, true);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _submitError = e.message);
    } catch (_) {
      if (!mounted) return;
      setState(() => _submitError = "Xatolik yuz berdi");
    } finally {
      if (mounted) setState(() => _submitting = false);
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
              color: Color(0xFF0A1A3A),
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
                    'Yangi apellyatsiya',
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
                : _loadError != null
                    ? Center(
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(_loadError!, style: TextStyle(color: subColor)),
                            const SizedBox(height: 12),
                            TextButton(onPressed: _loadGrades, child: const Text('Qayta yuklash')),
                          ],
                        ),
                      )
                    : Form(
                        key: _formKey,
                        child: ListView(
                          padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
                          children: [
                            Container(
                              padding: const EdgeInsets.all(12),
                              decoration: BoxDecoration(
                                color: const Color(0xFF7C3AED).withAlpha(15),
                                border: Border.all(color: const Color(0xFF7C3AED).withAlpha(60)),
                                borderRadius: BorderRadius.circular(12),
                              ),
                              child: const Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Icon(Icons.info_outline, size: 16, color: Color(0xFF7C3AED)),
                                  SizedBox(width: 8),
                                  Expanded(
                                    child: Text(
                                      "Faqat oxirgi 24 soat ichida qo'yilgan baholarga apellyatsiya topshirish mumkin.",
                                      style: TextStyle(fontSize: 11, color: Color(0xFF7C3AED), height: 1.4),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            const SizedBox(height: 16),
                            Text('Bahoni tanlang',
                                style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: textColor)),
                            const SizedBox(height: 8),
                            if (_grades.isEmpty)
                              Container(
                                padding: const EdgeInsets.all(16),
                                decoration: BoxDecoration(
                                  color: cardColor,
                                  borderRadius: BorderRadius.circular(12),
                                  border: Border.all(color: isDark ? Colors.white10 : const Color(0xFFE2E8F0)),
                                ),
                                child: Center(
                                  child: Text(
                                    "Apellyatsiya qilish mumkin bo'lgan baho topilmadi",
                                    style: TextStyle(fontSize: 12, color: subColor),
                                  ),
                                ),
                              )
                            else
                              ..._grades.map((g) {
                                final grade = g as Map<String, dynamic>;
                                final canAppeal = grade['can_appeal'] == true;
                                final isSelected = _selectedGrade?['id'] == grade['id'];
                                final gradeVal = (grade['grade'] as num?) ?? 0;

                                return Padding(
                                  padding: const EdgeInsets.only(bottom: 8),
                                  child: Material(
                                    color: cardColor,
                                    borderRadius: BorderRadius.circular(12),
                                    child: InkWell(
                                      borderRadius: BorderRadius.circular(12),
                                      onTap: canAppeal
                                          ? () => setState(() => _selectedGrade = grade)
                                          : null,
                                      child: Container(
                                        padding: const EdgeInsets.all(12),
                                        decoration: BoxDecoration(
                                          borderRadius: BorderRadius.circular(12),
                                          border: Border.all(
                                            color: isSelected
                                                ? const Color(0xFF7C3AED)
                                                : isDark
                                                    ? Colors.white10
                                                    : const Color(0xFFE2E8F0),
                                            width: isSelected ? 1.6 : 1,
                                          ),
                                        ),
                                        child: Column(
                                          crossAxisAlignment: CrossAxisAlignment.start,
                                          children: [
                                            Row(
                                              crossAxisAlignment: CrossAxisAlignment.start,
                                              children: [
                                                Container(
                                                  width: 18,
                                                  height: 18,
                                                  margin: const EdgeInsets.only(top: 2),
                                                  decoration: BoxDecoration(
                                                    shape: BoxShape.circle,
                                                    border: Border.all(
                                                      color: isSelected
                                                          ? const Color(0xFF7C3AED)
                                                          : subColor.withAlpha(120),
                                                      width: 1.6,
                                                    ),
                                                    color: isSelected
                                                        ? const Color(0xFF7C3AED)
                                                        : Colors.transparent,
                                                  ),
                                                  child: isSelected
                                                      ? const Icon(Icons.check, size: 12, color: Colors.white)
                                                      : null,
                                                ),
                                                const SizedBox(width: 10),
                                                Expanded(
                                                  child: Text(
                                                    grade['subject_name'] ?? '',
                                                    style: TextStyle(
                                                      fontSize: 13,
                                                      fontWeight: FontWeight.w700,
                                                      color: textColor,
                                                    ),
                                                  ),
                                                ),
                                                Text(
                                                  '${gradeVal.toStringAsFixed(gradeVal == gradeVal.toInt() ? 0 : 1)}',
                                                  style: TextStyle(
                                                    fontSize: 16,
                                                    fontWeight: FontWeight.w800,
                                                    color: _gradeColor(gradeVal),
                                                  ),
                                                ),
                                              ],
                                            ),
                                            const SizedBox(height: 6),
                                            Padding(
                                              padding: const EdgeInsets.only(left: 28),
                                              child: Wrap(
                                                spacing: 8,
                                                runSpacing: 4,
                                                children: [
                                                  Container(
                                                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                                    decoration: BoxDecoration(
                                                      color: subColor.withAlpha(20),
                                                      borderRadius: BorderRadius.circular(5),
                                                    ),
                                                    child: Text(
                                                      grade['training_type_name'] ?? '',
                                                      style: TextStyle(
                                                        fontSize: 10,
                                                        fontWeight: FontWeight.w600,
                                                        color: subColor,
                                                      ),
                                                    ),
                                                  ),
                                                  if (grade['employee_name'] != null)
                                                    Text(
                                                      grade['employee_name'],
                                                      style: TextStyle(fontSize: 10, color: subColor),
                                                    ),
                                                  Text(
                                                    grade['graded_at'] ?? '',
                                                    style: TextStyle(fontSize: 10, color: subColor),
                                                  ),
                                                  Container(
                                                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                                    decoration: BoxDecoration(
                                                      color: canAppeal
                                                          ? const Color(0xFF16A34A).withAlpha(20)
                                                          : const Color(0xFF94A3B8).withAlpha(40),
                                                      borderRadius: BorderRadius.circular(5),
                                                    ),
                                                    child: Text(
                                                      canAppeal ? 'Apellyatsiya mumkin' : 'Muddat tugagan',
                                                      style: TextStyle(
                                                        fontSize: 10,
                                                        fontWeight: FontWeight.w700,
                                                        color: canAppeal
                                                            ? const Color(0xFF16A34A)
                                                            : const Color(0xFF64748B),
                                                      ),
                                                    ),
                                                  ),
                                                ],
                                              ),
                                            ),
                                          ],
                                        ),
                                      ),
                                    ),
                                  ),
                                );
                              }),
                            const SizedBox(height: 16),
                            Text('Apellyatsiya sababi',
                                style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: textColor)),
                            const SizedBox(height: 8),
                            Container(
                              decoration: BoxDecoration(
                                color: cardColor,
                                borderRadius: BorderRadius.circular(12),
                                border: Border.all(color: isDark ? Colors.white10 : const Color(0xFFE2E8F0)),
                              ),
                              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                              child: TextFormField(
                                controller: _reasonCtrl,
                                maxLines: 5,
                                maxLength: 2000,
                                style: TextStyle(fontSize: 13, color: textColor),
                                decoration: InputDecoration(
                                  isDense: true,
                                  contentPadding: EdgeInsets.zero,
                                  border: InputBorder.none,
                                  hintText: "Sabab kamida 20 ta belgidan iborat bo'lishi kerak",
                                  hintStyle: TextStyle(color: subColor.withAlpha(150), fontSize: 12),
                                  counterStyle: TextStyle(fontSize: 10, color: subColor),
                                ),
                                validator: (v) {
                                  if (v == null || v.trim().length < 20) {
                                    return "Kamida 20 ta belgi";
                                  }
                                  return null;
                                },
                              ),
                            ),
                            const SizedBox(height: 16),
                            Text('Hujjat (ixtiyoriy)',
                                style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: textColor)),
                            const SizedBox(height: 8),
                            InkWell(
                              borderRadius: BorderRadius.circular(12),
                              onTap: _pickFile,
                              child: Container(
                                padding: const EdgeInsets.all(14),
                                decoration: BoxDecoration(
                                  color: cardColor,
                                  borderRadius: BorderRadius.circular(12),
                                  border: Border.all(
                                    color: _fileName != null
                                        ? const Color(0xFF7C3AED)
                                        : isDark
                                            ? Colors.white10
                                            : const Color(0xFFE2E8F0),
                                    style: _fileName == null ? BorderStyle.solid : BorderStyle.solid,
                                  ),
                                ),
                                child: Row(
                                  children: [
                                    Icon(
                                      _fileName != null ? Icons.attach_file : Icons.upload_file_outlined,
                                      size: 20,
                                      color: _fileName != null ? const Color(0xFF7C3AED) : subColor,
                                    ),
                                    const SizedBox(width: 10),
                                    Expanded(
                                      child: Text(
                                        _fileName ?? 'PDF, JPG, PNG (maks 5MB)',
                                        style: TextStyle(
                                          fontSize: 12,
                                          fontWeight: _fileName != null ? FontWeight.w600 : FontWeight.w400,
                                          color: _fileName != null ? textColor : subColor,
                                        ),
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                    ),
                                    if (_fileName != null)
                                      GestureDetector(
                                        onTap: () => setState(() {
                                          _fileBytes = null;
                                          _fileName = null;
                                        }),
                                        child: Icon(Icons.close, size: 18, color: subColor),
                                      ),
                                  ],
                                ),
                              ),
                            ),
                            if (_submitError != null) ...[
                              const SizedBox(height: 14),
                              Container(
                                padding: const EdgeInsets.all(12),
                                decoration: BoxDecoration(
                                  color: const Color(0xFFDC2626).withAlpha(15),
                                  border: Border.all(color: const Color(0xFFDC2626).withAlpha(60)),
                                  borderRadius: BorderRadius.circular(10),
                                ),
                                child: Text(
                                  _submitError!,
                                  style: const TextStyle(fontSize: 12, color: Color(0xFFB91C1C)),
                                ),
                              ),
                            ],
                            const SizedBox(height: 18),
                            InkWell(
                              borderRadius: BorderRadius.circular(14),
                              onTap: _submitting ? null : _submit,
                              child: Container(
                                padding: const EdgeInsets.symmetric(vertical: 14),
                                decoration: BoxDecoration(
                                  gradient: const LinearGradient(
                                    begin: Alignment.topLeft,
                                    end: Alignment.bottomRight,
                                    colors: [Color(0xFF8B5CF6), Color(0xFF7C3AED)],
                                  ),
                                  borderRadius: BorderRadius.circular(14),
                                  boxShadow: [
                                    BoxShadow(
                                      color: const Color(0xFF7C3AED).withAlpha(70),
                                      blurRadius: 16,
                                      offset: const Offset(0, 6),
                                    ),
                                  ],
                                ),
                                child: Row(
                                  mainAxisAlignment: MainAxisAlignment.center,
                                  children: [
                                    if (_submitting) ...[
                                      const SizedBox(
                                        width: 16,
                                        height: 16,
                                        child: CircularProgressIndicator(
                                          strokeWidth: 2.4,
                                          valueColor: AlwaysStoppedAnimation(Colors.white),
                                        ),
                                      ),
                                      const SizedBox(width: 10),
                                    ],
                                    Text(
                                      _submitting ? 'Yuborilmoqda…' : 'Apellyatsiya topshirish',
                                      style: const TextStyle(
                                        fontSize: 14,
                                        fontWeight: FontWeight.w700,
                                        color: Colors.white,
                                        letterSpacing: 0.3,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
          ),
        ],
      ),
    );
  }
}
