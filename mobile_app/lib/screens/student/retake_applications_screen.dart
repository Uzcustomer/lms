import 'dart:typed_data';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../l10n/app_localizations.dart';
import '../../services/api_service.dart';
import '../../services/student_service.dart';
import '../../widgets/clinic_header.dart';

class RetakeApplicationsScreen extends StatefulWidget {
  const RetakeApplicationsScreen({super.key});

  @override
  State<RetakeApplicationsScreen> createState() => _RetakeApplicationsScreenState();
}

class _RetakeApplicationsScreenState extends State<RetakeApplicationsScreen> {
  final _service = StudentService(ApiService());
  final _commentCtrl = TextEditingController();
  final _money = NumberFormat.decimalPattern('uz_UZ');

  Map<String, dynamic>? _data;
  final List<Map<String, dynamic>> _selected = [];
  Uint8List? _receiptBytes;
  String? _receiptFileName;
  bool _loading = true;
  bool _submitting = false;
  int? _uploadingPaymentGroupId;
  int? _uploadingMustaqilApplicationId;
  String? _error;

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

  List<Map<String, dynamic>> get _debts => _listOfMaps(_data?['debts']);
  List<Map<String, dynamic>> get _history => _listOfMaps(_data?['history']);
  List<Map<String, dynamic>> get _journal => _listOfMaps(_data?['journal']);
  List<Map<String, dynamic>> get _awaitingPayment =>
      _listOfMaps(_data?['groups_awaiting_payment']);
  List<Map<String, dynamic>> get _paymentVerifying =>
      _listOfMaps(_data?['groups_payment_verifying']);
  Map<String, dynamic>? get _window => _data?['window'] is Map
      ? Map<String, dynamic>.from(_data!['window'] as Map)
      : null;
  Map<String, dynamic> get _settings => _data?['settings'] is Map
      ? Map<String, dynamic>.from(_data!['settings'] as Map)
      : <String, dynamic>{};

  int get _remainingSlots => (_settings['remaining_slots'] as num?)?.toInt() ?? 0;
  double get _creditPrice => (_settings['credit_price'] as num?)?.toDouble() ?? 0;
  double get _totalCredits => _selected.fold(
        0,
        (sum, subject) => sum + ((subject['credit'] as num?)?.toDouble() ?? 0),
      );
  double get _totalAmount => _totalCredits * _creditPrice;
  int get _selectedCurrentCount =>
      _selected.where((subject) => subject['is_current_semester'] == true).length;

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final res = await _service.getRetakeApplications();
      if (!mounted) return;
      setState(() {
        _data = Map<String, dynamic>.from(res['data'] as Map? ?? {});
        _selected.removeWhere((s) {
          final matching = _debts.where((d) =>
              d['subject_id']?.toString() == s['subject_id']?.toString() &&
              d['semester_id']?.toString() == s['semester_id']?.toString());
          return matching.isEmpty || matching.first['is_active'] == true;
        });
      });
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (_) {
      if (mounted) {
        setState(() => _error = AppLocalizations.of(context).pick(
              uz: 'Ma\'lumotlarni yuklashda xatolik',
              ru: 'Ошибка загрузки данных',
              en: 'Error loading data',
            ));
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  List<Map<String, dynamic>> _listOfMaps(dynamic value) {
    return (value as List? ?? const [])
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
  }

  bool _isSelected(Map<String, dynamic> debt) {
    return _selected.any((s) =>
        s['subject_id']?.toString() == debt['subject_id']?.toString() &&
        s['semester_id']?.toString() == debt['semester_id']?.toString());
  }

  bool _canSelect(Map<String, dynamic> debt) {
    if (_window?['is_open'] != true) return false;
    if (debt['is_active'] == true) return false;
    if (_isSelected(debt)) return true;
    if (debt['is_current_semester'] == true &&
        _selectedCurrentCount >= _remainingSlots) {
      return false;
    }
    return true;
  }

  void _toggleDebt(Map<String, dynamic> debt) {
    if (!_canSelect(debt)) return;
    setState(() {
      final index = _selected.indexWhere((s) =>
          s['subject_id']?.toString() == debt['subject_id']?.toString() &&
          s['semester_id']?.toString() == debt['semester_id']?.toString());
      if (index >= 0) {
        _selected.removeAt(index);
      } else {
        _selected.add(debt);
      }
    });
  }

  Future<void> _pickReceipt() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'],
      withData: true,
    );
    if (result?.files.single.bytes == null) return;
    setState(() {
      _receiptBytes = result!.files.single.bytes;
      _receiptFileName = result.files.single.name;
    });
  }

  Future<void> _submitApplication() async {
    if (_selected.isEmpty) return;
    if (_receiptBytes == null || _receiptFileName == null) {
      _showSnack(AppLocalizations.of(context).pick(
        uz: 'Dekanat tasdig\'idagi faylni yuklang',
        ru: 'Загрузите файл, подтвержденный деканатом',
        en: 'Upload the file approved by the dean\'s office',
      ), error: true);
      return;
    }

    setState(() => _submitting = true);
    try {
      await _service.submitRetakeApplication(
        subjects: _selected,
        receiptBytes: _receiptBytes!,
        receiptFileName: _receiptFileName!,
        comment: _commentCtrl.text,
      );
      if (!mounted) return;
      Navigator.pop(context);
      _showSnack('Ariza muvaffaqiyatli yuborildi');
      setState(() {
        _selected.clear();
        _receiptBytes = null;
        _receiptFileName = null;
        _commentCtrl.clear();
      });
      await _load();
    } on ApiException catch (e) {
      if (mounted) _showSnack(e.message, error: true);
    } catch (_) {
      if (mounted) _showSnack('Ariza yuborishda xatolik', error: true);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _pickAndUploadPayment(Map<String, dynamic> group) async {
    final groupId = (group['id'] as num?)?.toInt();
    if (groupId == null) return;

    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['pdf', 'jpg', 'jpeg', 'png'],
      withData: true,
    );
    if (result?.files.single.bytes == null) return;

    setState(() => _uploadingPaymentGroupId = groupId);
    try {
      await _service.uploadRetakePayment(
        groupId: groupId,
        paymentBytes: result!.files.single.bytes!,
        paymentFileName: result.files.single.name,
      );
      if (!mounted) return;
      _showSnack('To\'lov cheki yuborildi');
      await _load();
    } on ApiException catch (e) {
      if (mounted) _showSnack(e.message, error: true);
    } catch (_) {
      if (mounted) _showSnack('To\'lov chekini yuborishda xatolik', error: true);
    } finally {
      if (mounted) setState(() => _uploadingPaymentGroupId = null);
    }
  }

  Future<void> _pickAndUploadMustaqil(Map<String, dynamic> journal) async {
    final applicationId = (journal['id'] as num?)?.toInt();
    if (applicationId == null) return;

    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'zip', 'rar'],
      withData: true,
    );
    if (result?.files.single.bytes == null) return;

    setState(() => _uploadingMustaqilApplicationId = applicationId);
    try {
      await _service.uploadRetakeMustaqil(
        applicationId: applicationId,
        fileBytes: result!.files.single.bytes!,
        fileName: result.files.single.name,
      );
      if (!mounted) return;
      _showSnack('Mustaqil ta\'lim fayli yuklandi');
      await _load();
    } on ApiException catch (e) {
      if (mounted) _showSnack(e.message, error: true);
    } catch (_) {
      if (mounted) _showSnack('Mustaqil faylni yuklashda xatolik', error: true);
    } finally {
      if (mounted) setState(() => _uploadingMustaqilApplicationId = null);
    }
  }

  Future<void> _openUrl(String? url) async {
    if (url == null || url.isEmpty) return;
    final uri = Uri.parse(url);
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  void _showSnack(String message, {bool error = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: error ? const Color(0xFFBE123C) : ClinicTheme.green,
      ),
    );
  }

  String _moneyText(num? value) => '${_money.format((value ?? 0).round())} UZS';

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Scaffold(
      backgroundColor: ClinicTheme.bgOf(context),
      body: Column(
        children: [
          ClinicHeader(
            overline: l.services.toUpperCase(),
            title: l.pick(
              uz: 'Qayta o\'qish arizasi',
              ru: 'Заявка на пересдачу',
              en: 'Retake application',
            ),
            onBack: () => Navigator.pop(context),
            actions: [
              ClinicIconButton(icon: Icons.refresh_rounded, onTap: _load),
            ],
          ),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _error != null
                    ? _ErrorState(message: _error!, onRetry: _load)
                    : RefreshIndicator(
                        onRefresh: _load,
                        child: ListView(
                          padding: const EdgeInsets.fromLTRB(14, 14, 14, 120),
                          children: [
                            _buildHero(),
                            const SizedBox(height: 12),
                             _buildWindowCard(),
                             const SizedBox(height: 12),
                             _buildPaymentAlerts(),
                             if (_journal.isNotEmpty) ...[
                               _buildJournal(),
                               const SizedBox(height: 14),
                             ],
                             _buildDebtList(),
                             const SizedBox(height: 14),
                             _buildHistory(),
                          ],
                        ),
                      ),
          ),
        ],
      ),
      bottomNavigationBar: _selected.isEmpty ? null : _buildBottomBar(),
    );
  }

  Widget _buildHero() {
    final l = AppLocalizations.of(context);
    return ShinySweep(
      radius: 20,
      child: Container(
        padding: const EdgeInsets.all(18),
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [Color(0xFF0F766E), Color(0xFF1E3A8A)],
          ),
        ),
        child: Row(
          children: [
            Container(
              width: 52,
              height: 52,
              decoration: BoxDecoration(
                color: Colors.white.withAlpha(35),
                borderRadius: BorderRadius.circular(16),
              ),
              child: const Icon(Icons.school_outlined, color: Colors.white, size: 28),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    l.pick(uz: 'Qayta o\'qish', ru: 'Пересдача', en: 'Retake'),
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 20,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    l.pick(
                      uz: 'Qarzdor fanlar uchun ariza yuboring va holatini kuzating',
                      ru: 'Подайте заявку по задолженным предметам и отслеживайте статус',
                      en: 'Apply for debt subjects and track the status',
                    ),
                    style: TextStyle(color: Colors.white.withAlpha(225), fontSize: 12),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildWindowCard() {
    final l = AppLocalizations.of(context);
    final window = _window;
    if (window == null) {
      return _InfoCard(
        icon: Icons.event_busy_outlined,
        title: l.pick(
          uz: 'Qabul oynasi ochilmagan',
          ru: 'Окно приема не открыто',
          en: 'Application window is not open',
        ),
        message: l.pick(
          uz: 'Sizning yo\'nalishingiz va kursingiz uchun ariza oynasi hali ochilmagan.',
          ru: 'Для вашего направления и курса окно подачи заявок пока не открыто.',
          en: 'The application window for your major and course is not open yet.',
        ),
        color: const Color(0xFFB45309),
      );
    }

    final isOpen = window['is_open'] == true;
    return _InfoCard(
      icon: isOpen ? Icons.event_available_outlined : Icons.event_busy_outlined,
      title: isOpen
          ? l.pick(uz: 'Ariza qabul ochiq', ru: 'Прием заявок открыт', en: 'Applications are open')
          : l.pick(uz: 'Ariza qabul yopiq', ru: 'Прием заявок закрыт', en: 'Applications are closed'),
      message:
          '${window['semester_name'] ?? ''}\n${window['start_date'] ?? '-'} -> ${window['end_date'] ?? '-'}',
      color: isOpen ? ClinicTheme.green : const Color(0xFFB45309),
      trailing: isOpen
          ? '${_remainingSlots.toString()} slot'
          : (window['status']?.toString() ?? ''),
    );
  }

  Widget _buildPaymentAlerts() {
    final l = AppLocalizations.of(context);
    final cards = <Widget>[];
    for (final group in _awaitingPayment) {
      final rejected = group['payment_verification_status'] == 'rejected';
      cards.add(_ActionCard(
        icon: Icons.receipt_long_outlined,
        title: rejected
            ? l.pick(uz: 'To\'lov cheki rad etildi', ru: 'Платежный чек отклонен', en: 'Payment receipt rejected')
            : l.pick(uz: 'To\'lov chekini yuklang', ru: 'Загрузите платежный чек', en: 'Upload payment receipt'),
        message: rejected
            ? (group['payment_rejection_reason']?.toString() ??
                l.pick(uz: 'Qayta yuklash kerak', ru: 'Нужно загрузить повторно', en: 'Please reupload'))
            : l.pick(
                uz: 'Dekan va registrator tasdiqlagan. Jarayon davom etishi uchun to\'lov chekini yuboring.',
                ru: 'Декан и регистратор подтвердили. Для продолжения отправьте платежный чек.',
                en: 'Approved by the dean and registrar. Upload the payment receipt to continue.',
              ),
        color: const Color(0xFFB45309),
        buttonText: _uploadingPaymentGroupId == group['id']
            ? l.uploading
            : l.pick(uz: 'Chek yuklash', ru: 'Загрузить чек', en: 'Upload receipt'),
        onTap: _uploadingPaymentGroupId == null ? () => _pickAndUploadPayment(group) : null,
      ));
      cards.add(const SizedBox(height: 10));
    }

    for (final group in _paymentVerifying) {
      cards.add(_InfoCard(
        icon: Icons.hourglass_top_rounded,
        title: l.pick(uz: 'To\'lov cheki tekshirilmoqda', ru: 'Платежный чек проверяется', en: 'Payment receipt is being checked'),
        message: l.pick(
          uz: 'Registrator ofisi chekingizni tekshirmoqda.\nYuklangan: ${group['payment_uploaded_at'] ?? '-'}',
          ru: 'Офис регистратора проверяет ваш чек.\nЗагружено: ${group['payment_uploaded_at'] ?? '-'}',
          en: 'The registrar office is checking your receipt.\nUploaded: ${group['payment_uploaded_at'] ?? '-'}',
        ),
        color: ClinicTheme.blue,
      ));
      cards.add(const SizedBox(height: 10));
    }

    return Column(children: cards);
  }

  Widget _buildJournal() {
    final l = AppLocalizations.of(context);
    final journal = _journal;
    return _SectionCard(
      title: l.pick(uz: 'Qayta o\'qish jurnali', ru: 'Журнал пересдачи', en: 'Retake journal'),
      subtitle: l.pick(uz: '${journal.length} ta fan', ru: '${journal.length} предметов', en: '${journal.length} subjects'),
      child: Column(
        children: journal
            .map(
              (item) => _RetakeJournalCard(
                item: item,
                uploading: _uploadingMustaqilApplicationId == item['id'],
                onOpen: _openUrl,
                onUpload: () => _pickAndUploadMustaqil(item),
              ),
            )
            .toList(),
      ),
    );
  }

  Widget _buildDebtList() {
    final l = AppLocalizations.of(context);
    final debts = _debts;
    final textColor = ClinicTheme.inkOf(context);
    final subColor = ClinicTheme.mutedOf(context);

    return _SectionCard(
      title: l.pick(uz: 'Qarzdor fanlar', ru: 'Предметы с задолженностью', en: 'Debt subjects'),
      subtitle: l.pick(uz: '${debts.length} ta fan', ru: '${debts.length} предметов', en: '${debts.length} subjects'),
      child: debts.isEmpty
          ? Padding(
              padding: const EdgeInsets.symmetric(vertical: 22),
              child: Column(
                children: [
                  const Icon(Icons.verified_rounded, color: ClinicTheme.green, size: 42),
                  const SizedBox(height: 8),
                  Text(
                    l.pick(
                      uz: 'Akademik qarzdorlik mavjud emas',
                      ru: 'Академической задолженности нет',
                      en: 'No academic debt',
                    ),
                    style: TextStyle(color: textColor, fontWeight: FontWeight.w700),
                  ),
                ],
              ),
            )
          : Column(
              children: debts.map((debt) {
                final selected = _isSelected(debt);
                final canSelect = _canSelect(debt);
                final active = debt['is_active'] == true;
                return Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(14),
                    onTap: canSelect ? () => _toggleDebt(debt) : null,
                    child: Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: selected
                            ? ClinicTheme.teal.withAlpha(18)
                            : ClinicTheme.surfaceOf(context),
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(
                          color: selected ? ClinicTheme.teal : ClinicTheme.dividerOf(context),
                          width: selected ? 1.4 : 1,
                        ),
                      ),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Container(
                            width: 22,
                            height: 22,
                            margin: const EdgeInsets.only(top: 2),
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              color: selected ? ClinicTheme.teal : Colors.transparent,
                              border: Border.all(
                                color: selected
                                    ? ClinicTheme.teal
                                    : canSelect
                                        ? ClinicTheme.faint
                                        : ClinicTheme.dividerOf(context),
                                width: 1.5,
                              ),
                            ),
                            child: selected
                                ? const Icon(Icons.check, size: 15, color: Colors.white)
                                : null,
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  debt['subject_name']?.toString() ?? '',
                                  style: TextStyle(
                                    fontSize: 13,
                                    fontWeight: FontWeight.w800,
                                    color: active ? subColor : textColor,
                                  ),
                                ),
                                const SizedBox(height: 5),
                                Wrap(
                                  spacing: 6,
                                  runSpacing: 5,
                                  children: [
                                    _MiniPill(text: debt['semester_name']?.toString() ?? '-'),
                                    _MiniPill(
                                      text:
                                          '${((debt['credit'] as num?)?.toDouble() ?? 0).toStringAsFixed(1)} kr',
                                    ),
                                    if (debt['grade'] != null)
                                      _MiniPill(text: '${l.grades}: ${debt['grade']}'),
                                  ],
                                ),
                                if (active) ...[
                                  const SizedBox(height: 6),
                                  Text(
                                    debt['active_status']?.toString() ??
                                        l.pick(uz: 'Ariza mavjud', ru: 'Заявка уже есть', en: 'Application exists'),
                                    style: const TextStyle(
                                      fontSize: 11,
                                      color: Color(0xFFB45309),
                                      fontWeight: FontWeight.w700,
                                    ),
                                  ),
                                ],
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                );
              }).toList(),
            ),
    );
  }

  Widget _buildHistory() {
    final l = AppLocalizations.of(context);
    final history = _history;
    if (history.isEmpty) {
      return _SectionCard(
        title: l.pick(uz: 'Mening arizalarim', ru: 'Мои заявки', en: 'My applications'),
        subtitle: l.pick(uz: 'Hali ariza yo\'q', ru: 'Заявок пока нет', en: 'No applications yet'),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 18),
          child: Text(
            l.pick(
              uz: 'Ariza yuborganingizdan keyin uning holati shu yerda ko\'rinadi.',
              ru: 'После отправки заявки ее статус будет отображаться здесь.',
              en: 'After you submit an application, its status will appear here.',
            ),
            textAlign: TextAlign.center,
            style: TextStyle(color: ClinicTheme.mutedOf(context), fontSize: 12),
          ),
        ),
      );
    }

    return _SectionCard(
      title: l.pick(uz: 'Mening arizalarim', ru: 'Мои заявки', en: 'My applications'),
      subtitle: l.pick(uz: '${history.length} ta ariza', ru: '${history.length} заявок', en: '${history.length} applications'),
      child: Column(
        children: history.map((group) => _HistoryCard(
              group: group,
              moneyText: _moneyText((group['receipt_amount'] as num?) ?? 0),
              onOpen: _openUrl,
            )).toList(),
      ),
    );
  }

  Widget _buildBottomBar() {
    final l = AppLocalizations.of(context);
    return SafeArea(
      child: Container(
        padding: const EdgeInsets.fromLTRB(14, 10, 14, 12),
        decoration: BoxDecoration(
          color: ClinicTheme.surfaceOf(context),
          border: Border(top: BorderSide(color: ClinicTheme.dividerOf(context))),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withAlpha(18),
              blurRadius: 16,
              offset: const Offset(0, -4),
            ),
          ],
        ),
        child: Row(
          children: [
            Expanded(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    l.pick(
                      uz: '${_selected.length} fan, ${_totalCredits.toStringAsFixed(1)} kredit',
                      ru: '${_selected.length} предметов, ${_totalCredits.toStringAsFixed(1)} кредитов',
                      en: '${_selected.length} subjects, ${_totalCredits.toStringAsFixed(1)} credits',
                    ),
                    style: TextStyle(
                      fontSize: 13,
                      color: ClinicTheme.inkOf(context),
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    _moneyText(_totalAmount),
                    style: const TextStyle(
                      fontSize: 12,
                      color: ClinicTheme.blue,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ),
            ElevatedButton.icon(
              onPressed: _showSubmitSheet,
              icon: const Icon(Icons.send_rounded, size: 18),
              label: Text(l.pick(uz: 'Yuborish', ru: 'Отправить', en: 'Submit')),
              style: ElevatedButton.styleFrom(
                backgroundColor: ClinicTheme.teal,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _showSubmitSheet() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, sheetSetState) {
            final l = AppLocalizations.of(context);
            return Padding(
              padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
              child: Container(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 20),
                decoration: BoxDecoration(
                  color: ClinicTheme.surfaceOf(context),
                  borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
                ),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Center(
                      child: Container(
                        width: 42,
                        height: 4,
                        decoration: BoxDecoration(
                          color: ClinicTheme.dividerOf(context),
                          borderRadius: BorderRadius.circular(999),
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),
                    Text(
                      l.pick(uz: 'Arizani yuborish', ru: 'Отправка заявки', en: 'Submit application'),
                      style: TextStyle(
                        color: ClinicTheme.inkOf(context),
                        fontSize: 18,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 10),
                    ..._selected.map((s) => Padding(
                          padding: const EdgeInsets.only(bottom: 6),
                          child: Row(
                            children: [
                              const Icon(Icons.check_circle, color: ClinicTheme.teal, size: 16),
                              const SizedBox(width: 8),
                              Expanded(
                                child: Text(
                                  '${s['semester_name']} - ${s['subject_name']}',
                                  style: TextStyle(
                                    color: ClinicTheme.inkOf(context),
                                    fontSize: 12,
                                    fontWeight: FontWeight.w600,
                                  ),
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ),
                              Text(
                                '${((s['credit'] as num?)?.toDouble() ?? 0).toStringAsFixed(1)} kr',
                                style: TextStyle(color: ClinicTheme.mutedOf(context), fontSize: 11),
                              ),
                            ],
                          ),
                        )),
                    const SizedBox(height: 10),
                    _FilePickerTile(
                      fileName: _receiptFileName,
                      title: l.pick(
                        uz: 'Dekanat tasdig\'idagi tushuntirish xati',
                        ru: 'Объяснительная, подтвержденная деканатом',
                        en: 'Explanation letter approved by the dean\'s office',
                      ),
                      subtitle: 'PDF, DOC, DOCX, JPG, PNG',
                      onTap: () async {
                        await _pickReceipt();
                        sheetSetState(() {});
                      },
                      onClear: _receiptFileName == null
                          ? null
                          : () {
                              setState(() {
                                _receiptBytes = null;
                                _receiptFileName = null;
                              });
                              sheetSetState(() {});
                            },
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: _commentCtrl,
                      maxLines: 3,
                      maxLength: 500,
                      decoration: InputDecoration(
                        hintText: l.description,
                        filled: true,
                        fillColor: ClinicTheme.dividerOf(context).withAlpha(35),
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(14),
                          borderSide: BorderSide.none,
                        ),
                        counterStyle: TextStyle(color: ClinicTheme.mutedOf(context), fontSize: 10),
                      ),
                    ),
                    const SizedBox(height: 10),
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton(
                        onPressed: _submitting ? null : _submitApplication,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: ClinicTheme.teal,
                          foregroundColor: Colors.white,
                          padding: const EdgeInsets.symmetric(vertical: 14),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                        ),
                        child: _submitting
                            ? const SizedBox(
                                width: 18,
                                height: 18,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2.4,
                                  valueColor: AlwaysStoppedAnimation(Colors.white),
                                ),
                              )
                            : Text(
                                l.pick(
                                  uz: 'Ariza yuborish',
                                  ru: 'Отправить заявку',
                                  en: 'Submit application',
                                ),
                                style: const TextStyle(fontWeight: FontWeight.w800),
                              ),
                      ),
                    ),
                  ],
                ),
              ),
            );
          },
        );
      },
    );
  }
}

class _RetakeJournalCard extends StatelessWidget {
  final Map<String, dynamic> item;
  final bool uploading;
  final Future<void> Function(String? url) onOpen;
  final VoidCallback onUpload;

  const _RetakeJournalCard({
    required this.item,
    required this.uploading,
    required this.onOpen,
    required this.onUpload,
  });

  @override
  Widget build(BuildContext context) {
    final group = item['retake_group'] is Map
        ? Map<String, dynamic>.from(item['retake_group'] as Map)
        : <String, dynamic>{};
    final mustaqil = item['mustaqil'] is Map
        ? Map<String, dynamic>.from(item['mustaqil'] as Map)
        : <String, dynamic>{};
    final dailyGrades = (item['daily_grades'] as List? ?? const [])
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .toList();
    final phones = (group['teacher_phones'] as List? ?? const [])
        .map((e) => e.toString())
        .where((e) => e.trim().isNotEmpty)
        .toList();

    final canUpload = mustaqil['can_upload'] == true;
    final hasSubmission = mustaqil['exists'] == true;
    final fileUrl = mustaqil['file_url']?.toString();
    final assessment = item['assessment_type_label']?.toString() ?? '-';
    final showOske = assessment.contains('OSKE') || item['oske_score'] != null;
    final showTest = assessment.contains('TEST') || item['test_score'] != null;
    final red = const Color(0xFFDC2626);

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: ClinicTheme.surfaceOf(context),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: red.withAlpha(38)),
        boxShadow: [
          BoxShadow(
            color: red.withAlpha(10),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(14),
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [Color(0xFFDC2626), Color(0xFF991B1B)],
              ),
              borderRadius: BorderRadius.vertical(top: Radius.circular(18)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Text(
                        item['subject_name']?.toString() ?? 'Fan',
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 14,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                    _StatusPill(
                      text: item['is_editable'] == true
                          ? 'Davom etmoqda'
                          : (group['status_label']?.toString() ?? 'Jurnal'),
                      color: Colors.white,
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 6,
                  runSpacing: 6,
                  children: [
                    _LightPill(text: item['semester_name']?.toString() ?? '-'),
                    _LightPill(text: group['name']?.toString() ?? 'Guruh'),
                    _LightPill(text: assessment),
                  ],
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _JournalInfoLine(
                  icon: Icons.person_outline_rounded,
                  text: group['teacher_name']?.toString() ?? 'O\'qituvchi biriktirilmagan',
                ),
                const SizedBox(height: 5),
                _JournalInfoLine(
                  icon: Icons.event_note_outlined,
                  text:
                      '${_dateText(group['start_date'])} -> ${_dateText(group['end_date'])}',
                ),
                if (phones.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 6,
                    runSpacing: 6,
                    children: phones
                        .map(
                          (phone) => _LinkChip(
                            text: phone,
                            onTap: () => onOpen('tel:${_cleanPhone(phone)}'),
                          ),
                        )
                        .toList(),
                  ),
                ],
                const SizedBox(height: 12),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    _ScoreTile(label: 'JN', value: _gradeText(item['joriy_score'])),
                    _ScoreTile(label: 'MT', value: _gradeText(mustaqil['grade'])),
                    if (showOske)
                      _ScoreTile(label: 'OSKE', value: _gradeText(item['oske_score'])),
                    if (showTest)
                      _ScoreTile(label: 'TEST', value: _gradeText(item['test_score'])),
                    _ScoreTile(
                      label: 'Yakuniy',
                      value: _gradeText(item['final_grade_value']),
                      accent: ClinicTheme.blue,
                    ),
                  ],
                ),
                if (dailyGrades.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  Text(
                    'Kunlik baholar',
                    style: TextStyle(
                      color: ClinicTheme.inkOf(context),
                      fontSize: 12,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 7),
                  Wrap(
                    spacing: 6,
                    runSpacing: 6,
                    children: dailyGrades
                        .map(
                          (grade) => _MiniPill(
                            text:
                                '${_dateText(grade['date'])}: ${_gradeText(grade['grade'])}',
                          ),
                        )
                        .toList(),
                  ),
                ],
                const SizedBox(height: 12),
                Container(
                  padding: const EdgeInsets.all(11),
                  decoration: BoxDecoration(
                    color: ClinicTheme.dividerOf(context).withAlpha(28),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          const Icon(Icons.cloud_done_outlined, color: ClinicTheme.teal, size: 18),
                          const SizedBox(width: 7),
                          Expanded(
                            child: Text(
                              'Mustaqil ta\'lim',
                              style: TextStyle(
                                color: ClinicTheme.inkOf(context),
                                fontSize: 12,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ),
                          _StatusPill(
                            text: _mustaqilStatus(mustaqil),
                            color: _mustaqilColor(mustaqil),
                          ),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Text(
                        hasSubmission
                            ? '${mustaqil['file_name'] ?? 'Fayl'} · ${mustaqil['submitted_at'] ?? '-'}'
                            : 'Hali fayl yuklanmagan',
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: ClinicTheme.mutedOf(context),
                          fontSize: 11,
                          height: 1.3,
                        ),
                      ),
                      if (mustaqil['teacher_comment'] != null) ...[
                        const SizedBox(height: 5),
                        Text(
                          'Izoh: ${mustaqil['teacher_comment']}',
                          style: TextStyle(
                            color: ClinicTheme.mutedOf(context),
                            fontSize: 11,
                            height: 1.3,
                          ),
                        ),
                      ],
                      const SizedBox(height: 9),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: [
                          if (fileUrl != null)
                            _LinkChip(text: 'Faylni ochish', onTap: () => onOpen(fileUrl)),
                          if (canUpload)
                            ActionChip(
                              onPressed: uploading ? null : onUpload,
                              avatar: uploading
                                  ? const SizedBox(
                                      width: 14,
                                      height: 14,
                                      child: CircularProgressIndicator(strokeWidth: 2),
                                    )
                                  : const Icon(Icons.upload_file_rounded, size: 15),
                              label: Text(
                                uploading
                                    ? 'Yuklanmoqda...'
                                    : hasSubmission
                                        ? 'Qayta yuklash'
                                        : 'Mustaqil yuklash',
                              ),
                              labelStyle: const TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.w800,
                              ),
                              backgroundColor: ClinicTheme.teal.withAlpha(18),
                              side: BorderSide(color: ClinicTheme.teal.withAlpha(45)),
                            ),
                        ],
                      ),
                      if (canUpload) ...[
                        const SizedBox(height: 7),
                        Text(
                          'Urinish: ${mustaqil['attempt_count'] ?? 0}/${mustaqil['max_attempts'] ?? 3}. '
                          '60+ baho olinsa qayta yuklash yopiladi.',
                          style: const TextStyle(
                            color: Color(0xFFB45309),
                            fontSize: 10.5,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  String _gradeText(dynamic value) {
    if (value == null) return '-';
    final n = value is num ? value.toDouble() : double.tryParse(value.toString());
    if (n == null) return value.toString();
    if (n == n.roundToDouble()) return n.round().toString();
    return n.toStringAsFixed(1);
  }

  String _dateText(dynamic value) {
    final raw = value?.toString();
    if (raw == null || raw.isEmpty) return '-';
    try {
      return DateFormat('dd.MM.yyyy').format(DateTime.parse(raw));
    } catch (_) {
      return raw;
    }
  }

  String _cleanPhone(String phone) => phone.replaceAll(RegExp(r'[^+\d]'), '');

  String _mustaqilStatus(Map<String, dynamic> mustaqil) {
    if (mustaqil['is_passed'] == true) return 'O\'tdi';
    if (mustaqil['is_exhausted'] == true) return 'Urinish tugadi';
    if (mustaqil['grade'] != null) return 'Baholangan';
    if (mustaqil['exists'] == true) return 'Tekshirilmoqda';
    return 'Yuklanmagan';
  }

  Color _mustaqilColor(Map<String, dynamic> mustaqil) {
    if (mustaqil['is_passed'] == true) return ClinicTheme.green;
    if (mustaqil['is_exhausted'] == true) return const Color(0xFFBE123C);
    if (mustaqil['grade'] != null) return const Color(0xFFB45309);
    if (mustaqil['exists'] == true) return ClinicTheme.blue;
    return ClinicTheme.muted;
  }
}

class _LightPill extends StatelessWidget {
  final String text;

  const _LightPill({required this.text});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: Colors.white.withAlpha(38),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        text,
        style: const TextStyle(
          color: Colors.white,
          fontSize: 10,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

class _JournalInfoLine extends StatelessWidget {
  final IconData icon;
  final String text;

  const _JournalInfoLine({required this.icon, required this.text});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(icon, color: ClinicTheme.mutedOf(context), size: 16),
        const SizedBox(width: 7),
        Expanded(
          child: Text(
            text,
            style: TextStyle(
              color: ClinicTheme.mutedOf(context),
              fontSize: 11.5,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ],
    );
  }
}

class _ScoreTile extends StatelessWidget {
  final String label;
  final String value;
  final Color accent;

  const _ScoreTile({
    required this.label,
    required this.value,
    this.accent = ClinicTheme.teal,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 72,
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 9),
      decoration: BoxDecoration(
        color: accent.withAlpha(16),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: accent.withAlpha(45)),
      ),
      child: Column(
        children: [
          Text(
            value,
            style: TextStyle(
              color: value == '-' ? ClinicTheme.mutedOf(context) : accent,
              fontSize: 17,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            style: TextStyle(
              color: ClinicTheme.mutedOf(context),
              fontSize: 9.5,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}

class _HistoryCard extends StatelessWidget {
  final Map<String, dynamic> group;
  final String moneyText;
  final Future<void> Function(String? url) onOpen;

  const _HistoryCard({
    required this.group,
    required this.moneyText,
    required this.onOpen,
  });

  @override
  Widget build(BuildContext context) {
    final apps = (group['applications'] as List? ?? const [])
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .toList();
    final docxUrl = group['docx_url']?.toString();
    final certificateUrl = group['certificate_uz_url']?.toString();

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: ClinicTheme.surfaceOf(context),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: ClinicTheme.dividerOf(context)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  '${apps.length} fan - $moneyText',
                  style: TextStyle(
                    color: ClinicTheme.inkOf(context),
                    fontWeight: FontWeight.w900,
                    fontSize: 13,
                  ),
                ),
              ),
              Text(
                group['created_at']?.toString() ?? '',
                style: TextStyle(color: ClinicTheme.mutedOf(context), fontSize: 10),
              ),
            ],
          ),
          if (group['session_name'] != null) ...[
            const SizedBox(height: 3),
            Text(
              'Sessiya: ${group['session_name']}',
              style: TextStyle(color: ClinicTheme.mutedOf(context), fontSize: 11),
            ),
          ],
          const SizedBox(height: 10),
          ...apps.map((app) => _ApplicationRow(app: app)),
          if (docxUrl != null || certificateUrl != null) ...[
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              children: [
                if (docxUrl != null)
                  _LinkChip(text: 'DOCX', onTap: () => onOpen(docxUrl)),
                if (certificateUrl != null)
                  _LinkChip(text: 'Ruxsatnoma PDF', onTap: () => onOpen(certificateUrl)),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

class _ApplicationRow extends StatelessWidget {
  final Map<String, dynamic> app;

  const _ApplicationRow({required this.app});

  @override
  Widget build(BuildContext context) {
    final status = app['final_status']?.toString() ?? 'pending';
    final color = switch (status) {
      'approved' => ClinicTheme.green,
      'rejected' => const Color(0xFFBE123C),
      _ => const Color(0xFFB45309),
    };
    final retakeGroup = app['retake_group'] is Map
        ? Map<String, dynamic>.from(app['retake_group'] as Map)
        : null;

    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Text(
                  '${app['semester_name'] ?? ''} - ${app['subject_name'] ?? ''}',
                  style: TextStyle(
                    color: ClinicTheme.inkOf(context),
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              _StatusPill(text: app['display_status']?.toString() ?? status, color: color),
            ],
          ),
          if (retakeGroup != null) ...[
            const SizedBox(height: 4),
            Text(
              '${retakeGroup['name'] ?? 'Guruh'} - ${retakeGroup['teacher_name'] ?? 'O\'qituvchi'}',
              style: TextStyle(color: ClinicTheme.mutedOf(context), fontSize: 11),
            ),
            Text(
              '${retakeGroup['start_date'] ?? '-'} -> ${retakeGroup['end_date'] ?? '-'}',
              style: TextStyle(color: ClinicTheme.mutedOf(context), fontSize: 11),
            ),
          ],
          if (app['rejection_reason'] != null) ...[
            const SizedBox(height: 4),
            Text(
              'Sabab: ${app['rejection_reason']}',
              style: const TextStyle(color: Color(0xFFBE123C), fontSize: 11),
            ),
          ],
        ],
      ),
    );
  }
}

class _SectionCard extends StatelessWidget {
  final String title;
  final String subtitle;
  final Widget child;

  const _SectionCard({
    required this.title,
    required this.subtitle,
    required this.child,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: ClinicTheme.surfaceOf(context),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: ClinicTheme.dividerOf(context)),
        boxShadow: ClinicTheme.cardShadow,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  title,
                  style: TextStyle(
                    color: ClinicTheme.inkOf(context),
                    fontWeight: FontWeight.w900,
                    fontSize: 15,
                  ),
                ),
              ),
              Text(
                subtitle,
                style: TextStyle(color: ClinicTheme.mutedOf(context), fontSize: 11),
              ),
            ],
          ),
          const SizedBox(height: 12),
          child,
        ],
      ),
    );
  }
}

class _InfoCard extends StatelessWidget {
  final IconData icon;
  final String title;
  final String message;
  final Color color;
  final String? trailing;

  const _InfoCard({
    required this.icon,
    required this.title,
    required this.message,
    required this.color,
    this.trailing,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: color.withAlpha(14),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: color.withAlpha(55)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: color, size: 24),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: TextStyle(
                    color: ClinicTheme.inkOf(context),
                    fontSize: 13,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  message,
                  style: TextStyle(
                    color: ClinicTheme.mutedOf(context),
                    fontSize: 11.5,
                    height: 1.35,
                  ),
                ),
              ],
            ),
          ),
          if (trailing != null)
            _StatusPill(text: trailing!, color: color),
        ],
      ),
    );
  }
}

class _ActionCard extends StatelessWidget {
  final IconData icon;
  final String title;
  final String message;
  final Color color;
  final String buttonText;
  final VoidCallback? onTap;

  const _ActionCard({
    required this.icon,
    required this.title,
    required this.message,
    required this.color,
    required this.buttonText,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: color.withAlpha(14),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: color.withAlpha(55)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, color: color, size: 24),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  title,
                  style: TextStyle(
                    color: ClinicTheme.inkOf(context),
                    fontSize: 13,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 6),
          Text(
            message,
            style: TextStyle(color: ClinicTheme.mutedOf(context), fontSize: 11.5, height: 1.35),
          ),
          const SizedBox(height: 10),
          Align(
            alignment: Alignment.centerRight,
            child: ElevatedButton(
              onPressed: onTap,
              style: ElevatedButton.styleFrom(
                backgroundColor: color,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
              child: Text(buttonText),
            ),
          ),
        ],
      ),
    );
  }
}

class _FilePickerTile extends StatelessWidget {
  final String? fileName;
  final String title;
  final String subtitle;
  final VoidCallback onTap;
  final VoidCallback? onClear;

  const _FilePickerTile({
    required this.fileName,
    required this.title,
    required this.subtitle,
    required this.onTap,
    this.onClear,
  });

  @override
  Widget build(BuildContext context) {
    final picked = fileName != null;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.all(13),
        decoration: BoxDecoration(
          color: ClinicTheme.dividerOf(context).withAlpha(35),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: picked ? ClinicTheme.teal : ClinicTheme.dividerOf(context),
          ),
        ),
        child: Row(
          children: [
            Icon(
              picked ? Icons.attach_file_rounded : Icons.upload_file_outlined,
              color: picked ? ClinicTheme.teal : ClinicTheme.mutedOf(context),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    picked ? fileName! : title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: ClinicTheme.inkOf(context),
                      fontWeight: FontWeight.w800,
                      fontSize: 12,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    subtitle,
                    style: TextStyle(color: ClinicTheme.mutedOf(context), fontSize: 10.5),
                  ),
                ],
              ),
            ),
            if (onClear != null)
              IconButton(
                onPressed: onClear,
                icon: Icon(Icons.close_rounded, color: ClinicTheme.mutedOf(context), size: 18),
              ),
          ],
        ),
      ),
    );
  }
}

class _MiniPill extends StatelessWidget {
  final String text;

  const _MiniPill({required this.text});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
      decoration: BoxDecoration(
        color: ClinicTheme.dividerOf(context).withAlpha(45),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        text,
        style: TextStyle(
          color: ClinicTheme.mutedOf(context),
          fontSize: 10,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

class _StatusPill extends StatelessWidget {
  final String text;
  final Color color;

  const _StatusPill({required this.text, required this.color});

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(maxWidth: 130),
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 4),
      decoration: BoxDecoration(
        color: color.withAlpha(18),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        text,
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
        textAlign: TextAlign.center,
        style: TextStyle(color: color, fontSize: 9.5, fontWeight: FontWeight.w800),
      ),
    );
  }
}

class _LinkChip extends StatelessWidget {
  final String text;
  final VoidCallback onTap;

  const _LinkChip({required this.text, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return ActionChip(
      onPressed: onTap,
      avatar: const Icon(Icons.open_in_new_rounded, size: 15),
      label: Text(text),
      labelStyle: const TextStyle(fontSize: 11, fontWeight: FontWeight.w800),
      backgroundColor: ClinicTheme.blue.withAlpha(18),
      side: BorderSide(color: ClinicTheme.blue.withAlpha(35)),
    );
  }
}

class _ErrorState extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;

  const _ErrorState({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.wifi_off_rounded, color: Color(0xFFBE123C), size: 42),
            const SizedBox(height: 10),
            Text(
              message,
              textAlign: TextAlign.center,
              style: TextStyle(color: ClinicTheme.mutedOf(context), fontSize: 13),
            ),
            const SizedBox(height: 12),
            OutlinedButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh_rounded),
              label: const Text('Qayta yuklash'),
            ),
          ],
        ),
      ),
    );
  }
}
