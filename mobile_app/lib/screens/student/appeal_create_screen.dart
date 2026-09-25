import 'dart:typed_data';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../services/student_service.dart';
import '../../widgets/clinic_header.dart';
import '../../l10n/app_localizations.dart';

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
        _loadError = AppLocalizations.current.pick(uz: 'Baholarni yuklashda xatolik', ru: 'Ошибка загрузки оценок', en: 'Failed to load grades');
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
      setState(() => _submitError = AppLocalizations.current.pick(uz: 'Bahoni tanlang', ru: 'Выберите оценку', en: 'Select a grade'));
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
          backgroundColor: ClinicTheme.greenFillOf(context),
        ),
      );
      Navigator.pop(context, true);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _submitError = e.message);
    } catch (_) {
      if (!mounted) return;
      setState(() => _submitError = AppLocalizations.current.genericError);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Color _gradeColor(num grade) {
    if (grade >= 86) return ClinicTheme.greenOf(context);
    if (grade >= 71) return ClinicTheme.blue;
    if (grade >= 60) return const Color(0xFFB45309);
    return const Color(0xFFBE123C);
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final cardColor = ClinicTheme.surfaceOf(context);
    final textColor = ClinicTheme.inkOf(context);
    final subColor = ClinicTheme.mutedOf(context);

    return Scaffold(
      backgroundColor: ClinicTheme.bgOf(context),
      body: Column(
        children: [
          ClinicHeader(
            overline: context.l10n.services.toUpperCase(),
            title: context.l10n.pick(uz: 'Yangi apellyatsiya', ru: 'Новая апелляция', en: 'New appeal'),
            onBack: () => Navigator.pop(context),
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
                            TextButton(onPressed: _loadGrades, child: Text(context.l10n.reload)),
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
                                color: ClinicTheme.tealFillOf(context).withAlpha(15),
                                border: Border.all(color: ClinicTheme.tealOf(context).withAlpha(60)),
                                borderRadius: BorderRadius.circular(12),
                              ),
                              child: Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Icon(Icons.info_outline, size: 16, color: ClinicTheme.tealOf(context)),
                                  SizedBox(width: 8),
                                  Expanded(
                                    child: Text(
                                      context.l10n.pick(uz: 'Faqat oxirgi 24 soat ichida qo\'yilgan baholarga apellyatsiya topshirish mumkin.', ru: 'Апелляцию можно подать только на оценки, выставленные за последние 24 часа.', en: 'Appeals can only be filed for grades given in the last 24 hours.'),
                                      style: TextStyle(fontSize: 11, color: ClinicTheme.tealOf(context), height: 1.4),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            const SizedBox(height: 16),
                            Text(context.l10n.pick(uz: 'Bahoni tanlang', ru: 'Выберите оценку', en: 'Select a grade'),
                                style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: textColor)),
                            const SizedBox(height: 8),
                            if (_grades.isEmpty)
                              Container(
                                padding: const EdgeInsets.all(16),
                                decoration: BoxDecoration(
                                  color: cardColor,
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: Center(
                                  child: Text(
                                    context.l10n.pick(uz: 'Apellyatsiya qilish mumkin bo\'lgan baho topilmadi', ru: 'Нет оценок, доступных для апелляции', en: 'No grades eligible for appeal'),
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
                                                ? ClinicTheme.tealOf(context)
                                                : isDark
                                                    ? Colors.white10
                                                    : ClinicTheme.dividerOf(context),
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
                                                          ? ClinicTheme.tealOf(context)
                                                          : subColor.withAlpha(120),
                                                      width: 1.6,
                                                    ),
                                                    color: isSelected
                                                        ? ClinicTheme.tealFillOf(context)
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
                                                          ? ClinicTheme.greenFillOf(context).withAlpha(20)
                                                          : ClinicTheme.faint.withAlpha(40),
                                                      borderRadius: BorderRadius.circular(5),
                                                    ),
                                                    child: Text(
                                                      canAppeal ? context.l10n.pick(uz: 'Apellyatsiya mumkin', ru: 'Можно обжаловать', en: 'Can appeal') : context.l10n.pick(uz: 'Muddat tugagan', ru: 'Срок истёк', en: 'Deadline passed'),
                                                      style: TextStyle(
                                                        fontSize: 10,
                                                        fontWeight: FontWeight.w700,
                                                        color: canAppeal
                                                            ? ClinicTheme.greenOf(context)
                                                            : ClinicTheme.muted,
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
                            Text(context.l10n.pick(uz: 'Apellyatsiya sababi', ru: 'Причина апелляции', en: 'Reason for appeal'),
                                style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: textColor)),
                            const SizedBox(height: 8),
                            Container(
                              decoration: BoxDecoration(
                                color: cardColor,
                                borderRadius: BorderRadius.circular(12),
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
                                  hintText: context.l10n.pick(uz: 'Sabab kamida 20 ta belgidan iborat bo\'lishi kerak', ru: 'Причина должна содержать не менее 20 символов', en: 'The reason must be at least 20 characters'),
                                  hintStyle: TextStyle(color: subColor.withAlpha(150), fontSize: 12),
                                  counterStyle: TextStyle(fontSize: 10, color: subColor),
                                ),
                                validator: (v) {
                                  if (v == null || v.trim().length < 20) {
                                    return context.l10n.pick(uz: 'Kamida 20 ta belgi', ru: 'Минимум 20 символов', en: 'At least 20 characters');
                                  }
                                  return null;
                                },
                              ),
                            ),
                            const SizedBox(height: 16),
                            Text(context.l10n.pick(uz: 'Hujjat (ixtiyoriy)', ru: 'Документ (необязательно)', en: 'Document (optional)'),
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
                                        ? ClinicTheme.tealOf(context)
                                        : isDark
                                            ? Colors.white10
                                            : ClinicTheme.dividerOf(context),
                                    style: _fileName == null ? BorderStyle.solid : BorderStyle.solid,
                                  ),
                                ),
                                child: Row(
                                  children: [
                                    Icon(
                                      _fileName != null ? Icons.attach_file : Icons.upload_file_outlined,
                                      size: 20,
                                      color: _fileName != null ? ClinicTheme.tealOf(context) : subColor,
                                    ),
                                    const SizedBox(width: 10),
                                    Expanded(
                                      child: Text(
                                        _fileName ?? context.l10n.pick(uz: 'PDF, JPG, PNG (maks 5MB)', ru: 'PDF, JPG, PNG (макс. 5 МБ)', en: 'PDF, JPG, PNG (max 5MB)'),
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
                                  color: const Color(0xFFBE123C).withAlpha(15),
                                  border: Border.all(color: ClinicTheme.redOf(context).withAlpha(60)),
                                  borderRadius: BorderRadius.circular(10),
                                ),
                                child: Text(
                                  _submitError!,
                                  style: TextStyle(fontSize: 12, color: ClinicTheme.redOf(context)),
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
                                  gradient: LinearGradient(
                                    begin: Alignment.topLeft,
                                    end: Alignment.bottomRight,
                                    colors: [ClinicTheme.tealFillOf(context), ClinicTheme.blue],
                                  ),
                                  borderRadius: BorderRadius.circular(14),
                                  boxShadow: [
                                    BoxShadow(
                                      color: ClinicTheme.tealOf(context).withAlpha(70),
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
                                      _submitting ? context.l10n.sending : context.l10n.pick(uz: 'Apellyatsiya topshirish', ru: 'Подать апелляцию', en: 'Submit appeal'),
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
