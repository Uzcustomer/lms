import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:file_picker/file_picker.dart';
import 'package:intl/intl.dart';
import 'package:table_calendar/table_calendar.dart';
import '../../l10n/app_localizations.dart';
import '../../providers/student_provider.dart';
import '../../services/api_service.dart';
import '../../services/sababli_retake_plan.dart';
import '../../widgets/clinic_header.dart';

class AbsenceExcuseCreateScreen extends StatefulWidget {
  const AbsenceExcuseCreateScreen({super.key});

  @override
  State<AbsenceExcuseCreateScreen> createState() => _AbsenceExcuseCreateScreenState();
}
class _AbsenceExcuseCreateScreenState extends State<AbsenceExcuseCreateScreen> {
  final _formKey = GlobalKey<FormState>();
  final _docNumberController = TextEditingController();

  String? _selectedReason;
  DateTime? _startDate;
  DateTime? _endDate;
  Uint8List? _fileBytes;
  String? _fileName;
  bool _isSubmitting = false;
  String? _submitError;

  List<dynamic> _reasons = [];
  Map<String, dynamic>? _selectedReasonData;
  bool _noDayLimit = false;

  List<Map<String, dynamic>> _missedAssessments = [];
  bool _isLoadingAssessments = false;
  bool _assessmentsLoaded = false;
  int _excuseDays = 0;

  // Hujjat topshirish muddati (veb formadagi checkDeadline)
  String? _deadlineWarning;
  bool _deadlineExpired = false;
  // Sanalar boshqa (kutilayotgan/tasdiqlangan) arizaga tushsa — server matni
  String? _overlapError;

  // Qayta topshirish jadvali: avtomatik kunlarni server qo'yadi (makeup_date)
  SababliRetakePlan? _plan;
  // Talaba ko'chirgan jadval (indeks => 'yyyy-MM-dd', MT siz); null — avtomatik
  Map<int, String>? _manual;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadReasons();
    });
  }

  Future<void> _loadReasons() async {
    final provider = context.read<StudentProvider>();
    await provider.loadExcuseReasons();
    if (mounted) {
      setState(() {
        _reasons = provider.excuseReasons ?? [];
        _noDayLimit = provider.excuseNoDayLimit;
      });
    }
  }

  int? get _maxDays => _selectedReasonData?['max_days'] as int?;

  void _resetAssessments() {
    _missedAssessments = [];
    _assessmentsLoaded = false;
    _overlapError = null;
    _plan = null;
    _manual = null;
  }

  void _clearDates() {
    setState(() {
      _startDate = null;
      _endDate = null;
      _excuseDays = 0;
      _deadlineWarning = null;
      _deadlineExpired = false;
      _resetAssessments();
    });
  }

  void _onReasonChanged(String? val) {
    setState(() {
      _selectedReason = val;
      _selectedReasonData = _reasons.firstWhere((r) => r['key'] == val) as Map<String, dynamic>?;
    });
    // Tanlangan oraliq yangi sabab chegarasidan katta bo'lsa — sanalar qaytadan tanlanadi
    final max = _maxDays;
    if (_startDate != null && _endDate != null && max != null
        && SababliRetakePlan.countNonSundays(_startDate!, _endDate!) > max) {
      _clearDates();
    }
  }

  void _checkDeadline() {
    _deadlineWarning = null;
    _deadlineExpired = false;
    if (_noDayLimit || _endDate == null) return;
    final diff = SababliRetakePlan.daysSinceEnd(_endDate!, DateTime.now());
    if (diff == null) return;
    if (diff > 10) {
      _deadlineExpired = true;
      _deadlineWarning = context.l10n.pick(
        uz: "Muddati o'tgan ($diff kun). 10 kun ichida ariza topshirish kerak edi.",
        ru: 'Срок истёк ($diff дн.). Заявление нужно было подать в течение 10 дней.',
        en: 'The deadline has passed ($diff days). The application had to be filed within 10 days.',
      );
    } else {
      _deadlineWarning = context.l10n.pick(
        uz: 'Ariza topshirish uchun ${10 - diff} kun qoldi.',
        ru: 'До окончания срока подачи осталось ${10 - diff} дн.',
        en: '${10 - diff} days left to file the application.',
      );
    }
  }

  Future<void> _pickDateRange() async {
    if (_selectedReason == null) {
      setState(() => _submitError = context.l10n.pick(uz: 'Avval sababni tanlang', ru: 'Сначала выберите причину', en: 'Choose the reason first'));
      return;
    }
    final now = DateTime.now();
    final today = DateTime(now.year, now.month, now.day);
    final result = await showModalBottomSheet<DateTimeRange>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _CalendarPicker(
        firstDate: today.subtract(Duration(days: _noDayLimit ? 365 : 60)),
        // Kelajakdagi kunlar tanlanmaydi (veb forma kabi)
        lastDate: today,
        initialStart: _startDate,
        initialEnd: _endDate,
      ),
    );
    if (result == null || !mounted) return;

    final max = _maxDays;
    final days = SababliRetakePlan.countNonSundays(result.start, result.end);
    if (max != null && days > max) {
      setState(() => _submitError = context.l10n.pick(
            uz: 'Tanlangan sabab uchun maksimum $max kun ruxsat etiladi. Siz $days kun belgiladingiz.',
            ru: 'Для выбранной причины допускается максимум $max дн. Вы выбрали $days дн.',
            en: 'The chosen reason allows at most $max days. You selected $days days.',
          ));
      return;
    }

    setState(() {
      _startDate = result.start;
      _endDate = result.end;
      _excuseDays = days;
      _submitError = null;
      _resetAssessments();
      _checkDeadline();
    });
    if (!_deadlineExpired) _fetchMissedAssessments();
  }

  Future<void> _fetchMissedAssessments() async {
    setState(() {
      _isLoadingAssessments = true;
      _resetAssessments();
    });

    try {
      final provider = context.read<StudentProvider>();
      final dateFormat = DateFormat('yyyy-MM-dd');
      final response = await provider.getMissedAssessments(
        dateFormat.format(_startDate!),
        dateFormat.format(_endDate!),
      );
      if (mounted) {
        final items = (response['data'] as List<dynamic>? ?? [])
            .map((a) => Map<String, dynamic>.from(a as Map))
            .toList();
        final today = SababliRetakePlan.format(DateTime.now());
        setState(() {
          _missedAssessments = items;
          _excuseDays = response['excuse_days'] as int? ?? _excuseDays;
          final overlap = response['overlap'];
          _overlapError = overlap is String && overlap.isNotEmpty ? overlap : null;
          _plan = SababliRetakePlan(
            items: items,
            retakeStart: response['retake_start'] as String? ?? today,
            retakeUntil: response['retake_until'] as String? ?? today,
            maxPerDay: response['max_per_day'] as int? ?? 3,
          );
          _assessmentsLoaded = true;
        });
      }
    } on ApiException catch (e) {
      if (mounted) {
        setState(() => _submitError = e.message);
      }
    } catch (e) {
      if (mounted) {
        setState(() => _submitError = e.toString());
      }
    } finally {
      if (mounted) {
        setState(() => _isLoadingAssessments = false);
      }
    }
  }

  Future<void> _pickFile() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['pdf', 'jpg', 'jpeg'],
      withData: true,
    );
    if (result != null && result.files.single.bytes != null) {
      setState(() {
        _fileBytes = result.files.single.bytes;
        _fileName = result.files.single.name;
      });
    }
  }

  bool get _canSubmit =>
      _selectedReason != null && _startDate != null && _endDate != null && !_deadlineExpired && _overlapError == null;

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    if (_startDate == null || _endDate == null) {
      setState(() => _submitError = AppLocalizations.current.pick(uz: 'Sanalarni tanlang', ru: 'Выберите даты', en: 'Select the dates'));
      return;
    }
    if (_fileBytes == null) {
      setState(() => _submitError = AppLocalizations.current.pick(uz: 'Fayl yuklang', ru: 'Загрузите файл', en: 'Upload a file'));
      return;
    }
    if (!_canSubmit) return;

    setState(() {
      _isSubmitting = true;
      _submitError = null;
    });

    // Har bir nazorat — sana serverda qayta hisoblanadi (ko'chirilgan bo'lsa — tekshiriladi)
    final plan = _plan?.plan(_manual) ?? const <int, String>{};
    final makeupDates = <Map<String, String>>[];
    for (int i = 0; i < _missedAssessments.length; i++) {
      final a = _missedAssessments[i];
      final submitted = !SababliRetakePlan.isPending(a);
      makeupDates.add({
        'subject_name': (a['subject_name'] ?? '').toString(),
        'subject_id': (a['subject_id'] ?? '').toString(),
        'assessment_type': (a['assessment_type'] ?? '').toString(),
        'assessment_type_code': (a['assessment_type_code'] ?? '').toString(),
        'original_date': (a['original_date'] ?? '').toString(),
        'makeup_date': plan[i] ?? '',
        'jn_submitted': submitted ? '1' : '0',
        'is_makeup_period': a['is_makeup_period'] == true ? '1' : '0',
        'already_submitted': submitted ? '1' : '0',
      });
    }

    try {
      final provider = context.read<StudentProvider>();
      await provider.submitExcuse(
        reason: _selectedReason!,
        docNumber: _docNumberController.text,
        startDate: DateFormat('yyyy-MM-dd').format(_startDate!),
        endDate: DateFormat('yyyy-MM-dd').format(_endDate!),
        fileBytes: _fileBytes!,
        fileName: _fileName!,
        makeupDates: makeupDates.isEmpty ? null : makeupDates,
        retakeManual: _manual != null,
      );
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(AppLocalizations.of(context).excuseSubmitted),
            backgroundColor: ClinicTheme.greenFillOf(context),
          ),
        );
        Navigator.pop(context, true);
      }
    } on ApiException catch (e) {
      setState(() => _submitError = e.message);
    } catch (e) {
      setState(() => _submitError = e.toString());
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  String _getTypeLabel(String type) {
    switch (type) {
      case 'jn': return 'Joriy nazorat';
      case 'mt': return 'Mustaqil ta\'lim';
      case 'oski': return 'YN (OSKE)';
      case 'test': return 'YN (Test)';
      default: return type.toUpperCase();
    }
  }

  Color _assessmentColor(String type) {
    switch (type) {
      case 'jn':
        return ClinicTheme.tealOf(context);
      case 'mt':
        return Colors.orange;
      case 'oski':
        return Colors.purple;
      case 'test':
        return Colors.teal;
      default:
        return ClinicTheme.tealOf(context);
    }
  }

  String _fmt(String ds) => DateFormat('dd.MM.yyyy').format(SababliRetakePlan.parse(ds));

  String _weekday(String ds, {bool short = false}) {
    final wd = SababliRetakePlan.parse(ds).weekday; // 1 — dushanba
    final names = context.l10n.pick(
      uz: 'Dushanba|Seshanba|Chorshanba|Payshanba|Juma|Shanba|Yakshanba',
      ru: 'Понедельник|Вторник|Среда|Четверг|Пятница|Суббота|Воскресенье',
      en: 'Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday',
    ).split('|');
    final name = names[wd - 1];
    return short ? name.substring(0, 3) : name;
  }

  /// Sana tartibidagi jadval qatorlari (veb formadagi `schedule`)
  List<({int idx, String date, bool moved})> _scheduleRows() {
    final plan = _plan!;
    final current = plan.plan(_manual);
    final auto = plan.autoPlan;
    const order = {'jn': 0, 'oski': 1, 'mt': 2, 'test': 3};
    final rows = current.entries.map((e) {
      final type = _missedAssessments[e.key]['assessment_type'];
      return (idx: e.key, date: e.value, moved: type != 'mt' && e.value != auto[e.key]);
    }).toList();
    rows.sort((x, y) {
      final c = x.date.compareTo(y.date);
      if (c != 0) return c;
      return (order[_missedAssessments[x.idx]['assessment_type']] ?? 9)
          .compareTo(order[_missedAssessments[y.idx]['assessment_type']] ?? 9);
    });
    return rows;
  }

  Future<void> _moveDay(int idx) async {
    final plan = _plan!;
    final candidates = plan.candidateDays(idx, _manual);
    final a = _missedAssessments[idx];
    final picked = await showModalBottomSheet<String>(
      context: context,
      backgroundColor: ClinicTheme.surfaceOf(context),
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (ctx) {
        final textColor = ClinicTheme.inkOf(ctx);
        final subColor = ClinicTheme.mutedOf(ctx);
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 12),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(a['subject_name'] ?? '', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: textColor)),
                const SizedBox(height: 4),
                Text(
                  ctx.l10n.pick(
                    uz: "Bo'sh kunlar (shu kuni retake qiladigan fanlar soni):",
                    ru: 'Свободные дни (сколько предметов пересдаётся в этот день):',
                    en: 'Free days (subjects retaken on that day):',
                  ),
                  style: TextStyle(fontSize: 12, color: subColor),
                ),
                const SizedBox(height: 10),
                if (candidates.isEmpty)
                  Text(
                    ctx.l10n.pick(
                      uz: "Ko'chirish uchun bo'sh kun yo'q — retake kunlari soni dars qoldirilgan kunlar soniga teng, ular to'lgan.",
                      ru: 'Свободных дней нет — дней пересдачи столько же, сколько пропущенных, и все они заняты.',
                      en: 'No free day to move to — there are as many retake days as missed days, and they are full.',
                    ),
                    style: TextStyle(fontSize: 13, color: subColor),
                  )
                else
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: candidates.map((c) => InkWell(
                      onTap: () => Navigator.pop(ctx, c.date),
                      borderRadius: BorderRadius.circular(10),
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(color: ClinicTheme.tealOf(ctx).withAlpha(70)),
                        ),
                        child: Text(
                          '${_fmt(c.date)} ${_weekday(c.date, short: true)}  ${c.load}/${plan.maxPerDay}',
                          style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w600, color: textColor),
                        ),
                      ),
                    )).toList(),
                  ),
              ],
            ),
          ),
        );
      },
    );
    if (picked == null || !mounted) return;
    final next = plan.moveTo(idx, picked, _manual);
    if (next != null) setState(() => _manual = next);
  }

  @override
  void dispose() {
    _docNumberController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final cardColor = ClinicTheme.surfaceOf(context);
    final textColor = ClinicTheme.inkOf(context);
    final subColor = ClinicTheme.mutedOf(context);
    final fieldFill = isDark ? Colors.white.withOpacity(0.04) : Colors.white;
    final fieldBorder = isDark ? Colors.white24 : const Color(0xFFCBD5E1);
    final dateFormat = DateFormat('dd.MM.yyyy');

    return Scaffold(
      backgroundColor: ClinicTheme.bgOf(context),
      body: Form(
        key: _formKey,
        child: Column(
          children: [
            ClinicHeader(
              overline: 'XIZMATLAR',
              title: l.newExcuse,
              onBack: () => Navigator.pop(context),
            ),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.fromLTRB(14, 14, 14, 14),
                children: [
            // Reason dropdown
            _SectionLabel(l.selectReason, color: textColor),
            DropdownButtonFormField<String>(
              value: _selectedReason,
              isExpanded: true,
              icon: Icon(Icons.expand_more_rounded, color: ClinicTheme.tealOf(context)),
              decoration: InputDecoration(
                filled: true,
                fillColor: fieldFill,
                prefixIcon: Icon(Icons.list_alt_rounded, color: ClinicTheme.tealOf(context), size: 22),
                hintText: l.selectReason,
                hintStyle: TextStyle(color: subColor, fontWeight: FontWeight.w500),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: BorderSide(color: fieldBorder, width: 1.2),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: BorderSide(color: ClinicTheme.tealOf(context), width: 1.8),
                ),
                contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              ),
              items: _reasons.map((r) {
                final reason = r as Map<String, dynamic>;
                return DropdownMenuItem<String>(
                  value: reason['key'] as String,
                  child: Text(reason['label'] as String,
                      style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: textColor)),
                );
              }).toList(),
              onChanged: _onReasonChanged,
              validator: (val) => val == null ? l.selectReason : null,
            ),
            if (_selectedReasonData != null) ...[
              const SizedBox(height: 6),
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: ClinicTheme.tealFillOf(context).withAlpha(20),
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: ClinicTheme.tealOf(context).withAlpha(80)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Icon(Icons.info_outline, size: 16, color: ClinicTheme.tealOf(context)),
                        const SizedBox(width: 6),
                        Text(l.requiredDocument, style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: ClinicTheme.tealOf(context))),
                      ],
                    ),
                    const SizedBox(height: 3),
                    Text(
                      _selectedReasonData!['document'] ?? '',
                      style: TextStyle(fontSize: 12.5, color: textColor, fontWeight: FontWeight.w500),
                    ),
                    if (_selectedReasonData!['max_days'] != null) ...[
                      const SizedBox(height: 4),
                      Text(
                        '${l.maxDays}: ${_selectedReasonData!['max_days']}',
                        style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: subColor),
                      ),
                    ],
                    if (_selectedReasonData!['note'] != null) ...[
                      const SizedBox(height: 2),
                      Text(
                        _selectedReasonData!['note'],
                        style: TextStyle(fontSize: 11, color: subColor, fontStyle: FontStyle.italic),
                      ),
                    ],
                  ],
                ),
              ),
            ],

            const SizedBox(height: 12),

            // Doc number
            _SectionLabel(l.docNumber, color: textColor),
            TextFormField(
              controller: _docNumberController,
              style: TextStyle(color: textColor, fontWeight: FontWeight.w600, fontSize: 14),
              decoration: InputDecoration(
                hintText: l.docNumber,
                hintStyle: TextStyle(color: subColor, fontWeight: FontWeight.w500),
                prefixIcon: Icon(Icons.tag_rounded, color: ClinicTheme.tealOf(context), size: 22),
                filled: true,
                fillColor: fieldFill,
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: BorderSide(color: fieldBorder, width: 1.2),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: BorderSide(color: ClinicTheme.tealOf(context), width: 1.8),
                ),
                contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
              ),
              validator: (val) => val == null || val.isEmpty ? l.docNumber : null,
            ),

            const SizedBox(height: 12),

            // File picker
            _SectionLabel(l.selectFile, color: textColor),
            InkWell(
              onTap: _pickFile,
              borderRadius: BorderRadius.circular(12),
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 16),
                decoration: BoxDecoration(
                  color: _fileName != null
                      ? ClinicTheme.greenFillOf(context).withAlpha(20)
                      : fieldFill,
                  border: Border.all(
                    color: _fileName != null ? ClinicTheme.greenOf(context) : fieldBorder,
                    width: _fileName != null ? 1.8 : 1.2,
                  ),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(
                      _fileName != null ? Icons.check_circle_rounded : Icons.upload_file_rounded,
                      color: _fileName != null ? ClinicTheme.greenOf(context) : ClinicTheme.tealOf(context),
                      size: 22,
                    ),
                    const SizedBox(width: 8),
                    Flexible(
                      child: Text(
                        _fileName ?? l.selectFile,
                        style: TextStyle(
                          color: _fileName != null ? ClinicTheme.greenOf(context) : textColor,
                          fontSize: 13.5,
                          fontWeight: FontWeight.w600,
                        ),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ),
              ),
            ),

            const SizedBox(height: 12),

            // Date range
            _SectionLabel(l.startDate + ' — ' + l.endDate, color: textColor),
            InkWell(
              onTap: _pickDateRange,
              borderRadius: BorderRadius.circular(12),
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
                decoration: BoxDecoration(
                  color: fieldFill,
                  border: Border.all(color: fieldBorder, width: 1.2),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: _startDate != null && _endDate != null
                    ? Row(
                        children: [
                          Icon(Icons.calendar_today_rounded, size: 20, color: ClinicTheme.tealOf(context)),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Text(
                              '${dateFormat.format(_startDate!)} — ${dateFormat.format(_endDate!)} (${context.l10n.pick(uz: '$_excuseDays kun', ru: '$_excuseDays дн.', en: '$_excuseDays days')})',
                              style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: textColor),
                            ),
                          ),
                          GestureDetector(
                            onTap: _clearDates,
                            child: Text(l.clear, style: TextStyle(fontSize: 12, color: ClinicTheme.tealOf(context), fontWeight: FontWeight.w700)),
                          ),
                          const SizedBox(width: 8),
                          Icon(Icons.edit_calendar_rounded, size: 20, color: ClinicTheme.tealOf(context)),
                        ],
                      )
                    : Row(
                        children: [
                          Icon(Icons.calendar_today_rounded, size: 20, color: ClinicTheme.tealOf(context)),
                          const SizedBox(width: 10),
                          Text(
                            '${l.startDate} — ${l.endDate}',
                            style: TextStyle(fontSize: 13.5, color: subColor, fontWeight: FontWeight.w500),
                          ),
                          const Spacer(),
                          Icon(Icons.edit_calendar_rounded, size: 20, color: ClinicTheme.tealOf(context)),
                        ],
                      ),
              ),
            ),

            if (_deadlineWarning != null) ...[
              const SizedBox(height: 8),
              _Notice(
                text: _deadlineWarning!,
                color: _deadlineExpired ? ClinicTheme.redOf(context) : ClinicTheme.amberOf(context),
              ),
            ],

            if (_overlapError != null) ...[
              const SizedBox(height: 8),
              _Notice(text: _overlapError!, color: ClinicTheme.redOf(context)),
            ],

            // Missed assessments section
            if (_isLoadingAssessments) ...[
              const SizedBox(height: 12),
              Container(
                decoration: BoxDecoration(color: cardColor, borderRadius: BorderRadius.circular(14)),
                padding: const EdgeInsets.all(20),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: ClinicTheme.tealOf(context),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Text(l.loadingAssessments, style: TextStyle(fontSize: 13, color: subColor)),
                  ],
                ),
              ),
            ],

            if (_assessmentsLoaded && _plan != null && _plan!.plan(_manual).isEmpty) ...[
              const SizedBox(height: 12),
              Container(
                decoration: BoxDecoration(
                  color: ClinicTheme.greenFillOf(context).withAlpha(15),
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: ClinicTheme.greenOf(context).withAlpha(50)),
                ),
                padding: const EdgeInsets.all(16),
                child: Row(
                  children: [
                    Icon(Icons.check_circle_outline, size: 20, color: ClinicTheme.greenOf(context)),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        l.noMissedAssessments,
                        style: TextStyle(fontSize: 13, color: ClinicTheme.greenOf(context)),
                      ),
                    ),
                  ],
                ),
              ),
            ],

            if (_assessmentsLoaded && _plan != null && _plan!.plan(_manual).isNotEmpty) ...[
              const SizedBox(height: 12),
              _buildRetakeSchedule(cardColor, textColor, subColor, l),
            ],

            if (_submitError != null) ...[
              const SizedBox(height: 8),
              _Notice(text: _submitError!, color: ClinicTheme.redOf(context)),
            ],

            const SizedBox(height: 16),

            // Submit button — teal→blue gradient with shadow
            Opacity(
              opacity: _canSubmit ? 1 : 0.5,
              child: Material(
                color: Colors.transparent,
                child: InkWell(
                  onTap: _isSubmitting || !_canSubmit ? null : _submit,
                  borderRadius: BorderRadius.circular(14),
                  child: Ink(
                    height: 52,
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        begin: Alignment.centerLeft,
                        end: Alignment.centerRight,
                        colors: [ClinicTheme.tealFillOf(context), ClinicTheme.blue],
                      ),
                      borderRadius: BorderRadius.circular(14),
                      boxShadow: [
                        BoxShadow(
                          color: ClinicTheme.tealOf(context).withOpacity(0.30),
                          blurRadius: 10,
                          offset: const Offset(0, 4),
                        ),
                      ],
                    ),
                    child: Center(
                      child: _isSubmitting
                          ? const SizedBox(
                              width: 22, height: 22,
                              child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.4),
                            )
                          : Text(
                              l.submitExcuse,
                              style: const TextStyle(
                                fontSize: 15.5,
                                fontWeight: FontWeight.w800,
                                color: Colors.white,
                                letterSpacing: 0.2,
                              ),
                            ),
                    ),
                  ),
                ),
              ),
            ),

                  const SizedBox(height: 24),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  /// Qayta topshirish jadvali — veb formadagi kabi sana tartibida; kunni ko'chirish mumkin
  Widget _buildRetakeSchedule(Color cardColor, Color textColor, Color subColor, AppLocalizations l) {
    final rows = _scheduleRows();
    final daysCount = rows.map((r) => r.date).toSet().length;

    return Container(
      decoration: BoxDecoration(color: cardColor, borderRadius: BorderRadius.circular(14)),
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.assignment_late, size: 20, color: ClinicTheme.amberOf(context)),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  l.missedAssessments,
                  style: TextStyle(fontSize: 15, fontWeight: FontWeight.w600, color: textColor),
                ),
              ),
              Text(
                '$daysCount',
                style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: ClinicTheme.tealOf(context)),
              ),
              const SizedBox(width: 4),
              Text(context.l10n.pick(uz: 'kun', ru: 'дн.', en: 'days'), style: TextStyle(fontSize: 12, color: subColor)),
            ],
          ),
          const SizedBox(height: 4),
          Text(
            context.l10n.pick(
              uz: "Qayta topshirish kunlari avtomatik belgilanadi — bir fandan kuniga bitta retake, bir kunda ko'pi bilan 3 ta fan. Kunni o'zgartirish uchun taqvim belgisini bosing.",
              ru: 'Дни пересдачи назначаются автоматически — по предмету не больше одной пересдачи в день, в день не больше 3 предметов. Чтобы сменить день, нажмите на значок календаря.',
              en: 'Retake days are set automatically — one retake per subject a day, at most 3 subjects a day. Tap the calendar icon to change a day.',
            ),
            style: TextStyle(fontSize: 11, color: subColor, fontStyle: FontStyle.italic),
          ),
          if (_manual != null)
            Align(
              alignment: Alignment.centerRight,
              child: TextButton(
                onPressed: () => setState(() => _manual = null),
                child: Text(
                  context.l10n.pick(uz: 'Avtomatik jadvalga qaytarish', ru: 'Вернуть автоматическое расписание', en: 'Back to the automatic schedule'),
                  style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700, color: ClinicTheme.tealOf(context)),
                ),
              ),
            )
          else
            const SizedBox(height: 10),
          ...rows.map((r) => _buildScheduleRow(r, textColor, subColor)),
        ],
      ),
    );
  }

  Widget _buildScheduleRow(({int idx, String date, bool moved}) row, Color textColor, Color subColor) {
    final a = _missedAssessments[row.idx];
    final type = a['assessment_type'] as String? ?? '';
    final color = _assessmentColor(type);
    final original = a['original_date'] as String? ?? '';
    final label = _getTypeLabel(type) +
        (type == 'jn' && original.isNotEmpty
            ? ' — ${_fmt(original)} ${context.l10n.pick(uz: 'darsi', ru: 'занятие', en: 'lesson')}'
            : '');

    return Container(
      margin: const EdgeInsets.only(bottom: 6),
      padding: const EdgeInsets.fromLTRB(12, 10, 6, 10),
      decoration: BoxDecoration(
        color: color.withAlpha(10),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: color.withAlpha(40)),
      ),
      child: Row(
        children: [
          SizedBox(
            width: 84,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(_fmt(row.date), style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700, color: textColor)),
                Text(_weekday(row.date), style: TextStyle(fontSize: 11, color: subColor)),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(a['subject_name'] ?? '', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: textColor)),
                const SizedBox(height: 4),
                Wrap(
                  spacing: 6,
                  runSpacing: 4,
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(color: color.withAlpha(25), borderRadius: BorderRadius.circular(6)),
                      child: Text(label, style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: color)),
                    ),
                    if (row.moved)
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                        decoration: BoxDecoration(color: ClinicTheme.amberOf(context).withAlpha(30), borderRadius: BorderRadius.circular(6)),
                        child: Text(
                          context.l10n.pick(uz: "ko'chirildi", ru: 'перенесено', en: 'moved'),
                          style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: ClinicTheme.amberOf(context)),
                        ),
                      ),
                  ],
                ),
              ],
            ),
          ),
          // MT kun band qilmaydi — muddati fanning oxirgi retake kuni
          if (type != 'mt')
            IconButton(
              onPressed: () => _moveDay(row.idx),
              icon: Icon(Icons.edit_calendar_rounded, size: 20, color: ClinicTheme.tealOf(context)),
              tooltip: context.l10n.pick(uz: "Boshqa kunga ko'chirish", ru: 'Перенести на другой день', en: 'Move to another day'),
            )
          else
            const SizedBox(width: 48),
        ],
      ),
    );
  }
}

/// Rangli ogohlantirish qatori (muddat, ustma-ust ariza, xato)
class _Notice extends StatelessWidget {
  final String text;
  final Color color;
  const _Notice({required this.text, required this.color});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: color.withAlpha(25),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: color.withAlpha(80)),
      ),
      child: Row(
        children: [
          Icon(Icons.error_outline_rounded, color: color, size: 20),
          const SizedBox(width: 8),
          Expanded(child: Text(text, style: TextStyle(color: color, fontSize: 13, fontWeight: FontWeight.w600))),
        ],
      ),
    );
  }
}

class _CalendarPicker extends StatefulWidget {
  final DateTime firstDate;
  final DateTime lastDate;
  final DateTime? initialStart;
  final DateTime? initialEnd;

  const _CalendarPicker({
    required this.firstDate,
    required this.lastDate,
    this.initialStart,
    this.initialEnd,
  });

  @override
  State<_CalendarPicker> createState() => _CalendarPickerState();
}

class _CalendarPickerState extends State<_CalendarPicker> {
  late DateTime _focusedDay;
  DateTime? _rangeStart;
  DateTime? _rangeEnd;

  @override
  void initState() {
    super.initState();
    _rangeStart = widget.initialStart;
    _rangeEnd = widget.initialEnd;
    _focusedDay = widget.initialStart ?? DateTime.now();
    if (_focusedDay.isBefore(widget.firstDate)) _focusedDay = widget.firstDate;
    if (_focusedDay.isAfter(widget.lastDate)) _focusedDay = widget.lastDate;
  }

  bool get _canConfirm => _rangeStart != null && _rangeEnd != null;

  @override
  Widget build(BuildContext context) {
    final textColor = ClinicTheme.inkOf(context);
    final subColor = ClinicTheme.mutedOf(context);

    return Container(
      decoration: BoxDecoration(
        color: ClinicTheme.surfaceOf(context),
        borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Center(
            child: Container(
              margin: const EdgeInsets.only(top: 12, bottom: 4),
              width: 36,
              height: 4,
              decoration: BoxDecoration(
                color: subColor.withAlpha(80),
                borderRadius: BorderRadius.circular(2),
              ),
            ),
          ),
          TableCalendar(
            firstDay: widget.firstDate,
            lastDay: widget.lastDate,
            focusedDay: _focusedDay,
            startingDayOfWeek: StartingDayOfWeek.monday,
            rangeStartDay: _rangeStart,
            rangeEndDay: _rangeEnd,
            rangeSelectionMode: RangeSelectionMode.enforced,
            onRangeSelected: (start, end, focusedDay) {
              setState(() {
                _rangeStart = start;
                _rangeEnd = end;
                _focusedDay = focusedDay;
              });
            },
            onPageChanged: (focusedDay) => _focusedDay = focusedDay,
            rowHeight: 42,
            daysOfWeekHeight: 28,
            calendarStyle: CalendarStyle(
              outsideDaysVisible: false,
              cellMargin: const EdgeInsets.all(4),
              rangeHighlightColor: ClinicTheme.tealOf(context).withAlpha(30),
              rangeStartDecoration: BoxDecoration(
                color: ClinicTheme.tealFillOf(context),
                shape: BoxShape.circle,
              ),
              rangeEndDecoration: BoxDecoration(
                color: ClinicTheme.tealFillOf(context),
                shape: BoxShape.circle,
              ),
              withinRangeTextStyle: TextStyle(color: textColor),
              selectedDecoration: BoxDecoration(
                color: ClinicTheme.tealFillOf(context),
                shape: BoxShape.circle,
              ),
              todayDecoration: BoxDecoration(
                color: Colors.transparent,
                shape: BoxShape.circle,
                border: Border.all(color: ClinicTheme.tealOf(context), width: 1.5),
              ),
              todayTextStyle: TextStyle(
                color: ClinicTheme.tealOf(context),
                fontWeight: FontWeight.w600,
              ),
              defaultTextStyle: TextStyle(color: textColor, fontSize: 14),
              weekendTextStyle: TextStyle(color: subColor, fontSize: 14),
              disabledTextStyle: TextStyle(
                color: subColor.withAlpha(80),
                fontSize: 14,
              ),
            ),
            headerStyle: HeaderStyle(
              formatButtonVisible: false,
              titleCentered: true,
              titleTextStyle: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w600,
                color: textColor,
              ),
              leftChevronIcon: Icon(Icons.chevron_left, color: textColor, size: 24),
              rightChevronIcon: Icon(Icons.chevron_right, color: textColor, size: 24),
              headerPadding: const EdgeInsets.symmetric(vertical: 8),
            ),
            daysOfWeekStyle: DaysOfWeekStyle(
              weekdayStyle: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w600,
                color: subColor,
              ),
              weekendStyle: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w600,
                color: subColor.withAlpha(150),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
            child: SafeArea(
              child: SizedBox(
                width: double.infinity,
                height: 46,
                child: ElevatedButton(
                  onPressed: _canConfirm ? _confirm : null,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: ClinicTheme.tealFillOf(context),
                    foregroundColor: Colors.white,
                    disabledBackgroundColor: ClinicTheme.tealOf(context).withAlpha(60),
                    disabledForegroundColor: Colors.white54,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  child: Text(
                    context.l10n.pick(uz: 'Tanlash', ru: 'Выбрать', en: 'Select'),
                    style: TextStyle(fontSize: 15, fontWeight: FontWeight.w600),
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  void _confirm() {
    if (_rangeStart != null && _rangeEnd != null) {
      Navigator.pop(context, DateTimeRange(start: _rangeStart!, end: _rangeEnd!));
    }
  }
}

/// Compact section label rendered above each form input.
class _SectionLabel extends StatelessWidget {
  final String text;
  final Color color;
  const _SectionLabel(this.text, {required this.color});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(left: 4, bottom: 6, top: 2),
      child: Text(
        text,
        style: TextStyle(
          fontSize: 12.5,
          fontWeight: FontWeight.w700,
          color: color,
          letterSpacing: 0.1,
        ),
      ),
    );
  }
}
