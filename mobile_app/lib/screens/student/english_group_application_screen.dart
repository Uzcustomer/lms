import 'dart:typed_data';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../l10n/app_localizations.dart';
import '../../services/api_service.dart';
import '../../services/english_group_application_service.dart';
import '../../widgets/clinic_header.dart';
import '../../widgets/loading_widget.dart';

class EnglishGroupApplicationScreen extends StatefulWidget {
  const EnglishGroupApplicationScreen({super.key});

  @override
  State<EnglishGroupApplicationScreen> createState() =>
      _EnglishGroupApplicationScreenState();
}

class _EnglishGroupApplicationScreenState
    extends State<EnglishGroupApplicationScreen> {
  final _service = EnglishGroupApplicationService(ApiService());
  final _phoneController = TextEditingController();

  bool _loading = true;
  bool _submitting = false;
  bool _resubmitMode = false;
  String? _error;
  String? _selectedLevel;
  Uint8List? _certificateBytes;
  String? _certificateFileName;
  Map<String, dynamic>? _data;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _phoneController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final res = await _service.getOverview();
      final data = (res['data'] as Map?)?.cast<String, dynamic>() ?? {};
      final student = (data['student'] as Map?)?.cast<String, dynamic>() ?? {};
      _phoneController.text = student['phone_number']?.toString() ?? '';
      if (!mounted) return;
      setState(() {
        _data = data;
        _loading = false;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _loading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _error = "Tarmoq xatoligi. Internet aloqasini tekshiring.";
        _loading = false;
      });
    }
  }

  Future<void> _pickCertificate() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: const ['pdf'],
      withData: true,
    );
    if (result == null || result.files.isEmpty) return;
    final file = result.files.single;
    if (file.bytes == null) return;
    setState(() {
      _certificateBytes = file.bytes;
      _certificateFileName = file.name;
    });
  }

  Future<void> _submit() async {
    final level = _selectedLevel;
    if (level == null || level.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            AppLocalizations.of(context).pick(
              uz: "Ingliz tili darajasini tanlang.",
              ru: "Выберите уровень английского языка.",
              en: "Select your English level.",
            ),
          ),
        ),
      );
      return;
    }

    setState(() => _submitting = true);
    try {
      final res = await _service.submit(
        englishLevel: level,
        phoneNumber: _phoneController.text,
        certificateBytes: _certificateBytes,
        certificateFileName: _certificateFileName,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(res['message']?.toString() ?? 'OK')),
      );
      setState(() {
        _resubmitMode = false;
        _selectedLevel = null;
        _certificateBytes = null;
        _certificateFileName = null;
      });
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message)),
      );
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text("So'rov yuborishda xatolik yuz berdi."),
        ),
      );
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _openCertificate(int id) async {
    final uri = Uri.parse(_service.certificateUrl(id));
    if (!await launchUrl(uri, mode: LaunchMode.externalApplication)) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            AppLocalizations.of(context).pick(
              uz: 'Sertifikatni ochib bo‘lmadi.',
              ru: 'Не удалось открыть сертификат.',
              en: 'Could not open the certificate.',
            ),
          ),
        ),
      );
    }
  }

  List<Map<String, dynamic>> _applications() {
    final list = (_data?['applications'] as List?) ?? const [];
    return list
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
  }

  Map<String, dynamic>? _latest() {
    final raw = _data?['latest'];
    if (raw is Map) return Map<String, dynamic>.from(raw);
    return null;
  }

  List<Map<String, dynamic>> _levels() {
    final list = (_data?['english_levels'] as List?) ?? const [];
    return list
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
  }

  bool _canResubmit() => _data?['can_resubmit'] == true;

  bool _showForm(Map<String, dynamic>? latest) {
    if (_resubmitMode) return true;
    return latest == null && _data?['can_submit'] == true;
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    return Scaffold(
      backgroundColor: const Color(0xFFF6F8FC),
      body: Column(
        children: [
          ClinicHeader(
            overline: l.services.toUpperCase(),
            title: l.pick(
              uz: 'Ingliz tili guruhi',
              ru: 'Группа английского языка',
              en: 'English group',
            ),
            onBack: () => Navigator.pop(context),
          ),
          Expanded(
            child: _loading
                ? const LoadingWidget()
                : _error != null
                    ? _ErrorState(message: _error!, onRetry: _load)
                    : RefreshIndicator(
                        onRefresh: _load,
                        child: _buildBody(context, l),
                      ),
          ),
        ],
      ),
    );
  }

  Widget _buildBody(BuildContext context, AppLocalizations l) {
    final student =
        (_data?['student'] as Map?)?.cast<String, dynamic>() ?? <String, dynamic>{};
    final latest = _latest();
    final apps = _applications();
    final showForm = _showForm(latest);

    return ListView(
      padding: const EdgeInsets.fromLTRB(18, 14, 18, 28),
      children: [
        _HeroCard(
          title: l.pick(
            uz: 'Ingliz tili guruhiga o‘tish uchun ariza',
            ru: 'Заявление на перевод в группу английского языка',
            en: 'Application to transfer to the English group',
          ),
          subtitle: l.pick(
            uz: "Ma'lumotlaringiz avtomatik to'ldiriladi. Ingliz tili darajangizni kiriting va sertifikat bo'lsa PDF yuklang.",
            ru: 'Ваши данные заполняются автоматически. Укажите уровень английского и при наличии прикрепите PDF-сертификат.',
            en: 'Your data is filled in automatically. Enter your English level and attach a PDF certificate if you have one.',
          ),
        ),
        const SizedBox(height: 14),
        if (showForm)
          _buildFormCard(context, l, student)
        else if (latest != null)
          _buildStatusCard(context, l, latest),
        if (apps.isNotEmpty) ...[
          const SizedBox(height: 14),
          _buildHistoryCard(context, l, apps),
        ],
      ],
    );
  }

  Widget _buildFormCard(
    BuildContext context,
    AppLocalizations l,
    Map<String, dynamic> student,
  ) {
    final levels = _levels();
    return _SectionCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _SectionTitle(
            title: l.pick(
              uz: 'Ariza formasi',
              ru: 'Форма заявления',
              en: 'Application form',
            ),
          ),
          const SizedBox(height: 12),
          _ReadOnlyField(
            label: 'F.I.SH',
            value: student['full_name']?.toString() ?? '-',
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: _ReadOnlyField(
                  label: l.faculty,
                  value: student['faculty_name']?.toString() ?? '-',
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _ReadOnlyField(
                  label: l.direction,
                  value: student['specialty_name']?.toString() ?? '-',
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: _ReadOnlyField(
                  label: l.course,
                  value: student['course_name']?.toString() ?? '-',
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _ReadOnlyField(
                  label: l.semester,
                  value: student['semester_name']?.toString() ?? '-',
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          _ReadOnlyField(
            label: l.group,
            value: student['group_name']?.toString() ?? '-',
          ),
          const SizedBox(height: 10),
          _InputField(
            label: l.pick(
              uz: 'Telefon raqam',
              ru: 'Номер телефона',
              en: 'Phone number',
            ),
            controller: _phoneController,
            hint: '+998...',
          ),
          const SizedBox(height: 10),
          _DropdownField(
            label: l.pick(
              uz: 'Ingliz tili darajasi',
              ru: 'Уровень английского языка',
              en: 'English level',
            ),
            value: _selectedLevel,
            items: levels
                .map(
                  (item) => DropdownMenuItem<String>(
                    value: item['value']?.toString(),
                    child: Text(item['label']?.toString() ?? ''),
                  ),
                )
                .toList(),
            onChanged: (value) => setState(() => _selectedLevel = value),
          ),
          const SizedBox(height: 10),
          _UploadTile(
            fileName: _certificateFileName,
            title: l.pick(
              uz: 'Til sertifikati',
              ru: 'Сертификат языка',
              en: 'Language certificate',
            ),
            subtitle: l.pick(
              uz: 'Ixtiyoriy. Faqat PDF, maksimum 2 MB.',
              ru: 'Необязательно. Только PDF, максимум 2 МБ.',
              en: 'Optional. PDF only, maximum 2 MB.',
            ),
            onTap: _pickCertificate,
            onClear: _certificateFileName == null
                ? null
                : () => setState(() {
                      _certificateBytes = null;
                      _certificateFileName = null;
                    }),
          ),
          const SizedBox(height: 14),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: _submitting ? null : _submit,
              style: ElevatedButton.styleFrom(
                backgroundColor: ClinicTheme.teal,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(
                  horizontal: 20,
                  vertical: 18,
                ),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(14),
                ),
              ),
              child: _submitting
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Colors.white,
                      ),
                    )
                  : Text(
                      l.pick(
                        uz: 'Arizani yuborish',
                        ru: 'Отправить заявление',
                        en: 'Submit application',
                      ),
                    ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildStatusCard(
    BuildContext context,
    AppLocalizations l,
    Map<String, dynamic> latest,
  ) {
    final status = latest['status']?.toString() ?? 'pending';
    final badgeColor = switch (status) {
      'approved' => const Color(0xFF059669),
      'rejected' => const Color(0xFFDC2626),
      _ => const Color(0xFFD97706),
    };

    return _SectionCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: _SectionTitle(
                  title: l.pick(
                    uz: 'Ariza holati',
                    ru: 'Статус заявления',
                    en: 'Application status',
                  ),
                  subtitle: l.pick(
                    uz: 'Yuborgan arizangizning joriy holati.',
                    ru: 'Текущее состояние вашей заявки.',
                    en: 'The current status of your application.',
                  ),
                ),
              ),
              _StatusChip(
                text: latest['status_label']?.toString() ?? status,
                color: badgeColor,
              ),
            ],
          ),
          const SizedBox(height: 12),
          _InfoRow(
            label: l.pick(
              uz: 'Telefon raqam',
              ru: 'Номер телефона',
              en: 'Phone number',
            ),
            value: latest['phone_number']?.toString() ?? '-',
          ),
          _InfoRow(
            label: l.pick(
              uz: 'Ingliz tili darajasi',
              ru: 'Уровень английского языка',
              en: 'English level',
            ),
            value: latest['english_level_label']?.toString() ?? '-',
          ),
          _InfoRow(
            label: l.pick(
              uz: 'Yuborilgan sana',
              ru: 'Дата отправки',
              en: 'Submitted at',
            ),
            value: latest['created_at']?.toString() ?? '-',
          ),
          if (latest['has_certificate'] == true) ...[
            const SizedBox(height: 8),
            TextButton.icon(
              onPressed: () => _openCertificate((latest['id'] as num).toInt()),
              icon: const Icon(Icons.picture_as_pdf_outlined),
              label: Text(
                l.pick(
                  uz: 'Sertifikatni ochish',
                  ru: 'Открыть сертификат',
                  en: 'Open certificate',
                ),
              ),
            ),
          ],
          if ((latest['rejection_reason_label']?.toString() ?? '').isNotEmpty) ...[
            const SizedBox(height: 8),
            _DangerBox(text: latest['rejection_reason_label']!.toString()),
          ],
          if ((latest['admin_note']?.toString() ?? '').isNotEmpty) ...[
            const SizedBox(height: 8),
            _DangerBox(text: latest['admin_note']!.toString()),
          ],
          if (_canResubmit()) ...[
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton(
                onPressed: () => setState(() => _resubmitMode = true),
                style: OutlinedButton.styleFrom(
                  foregroundColor: ClinicTheme.green,
                  side: const BorderSide(color: Color(0xFF10B981)),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(14),
                  ),
                  padding: const EdgeInsets.symmetric(vertical: 14),
                ),
                child: Text(
                  l.pick(
                    uz: 'Qayta ariza topshirish',
                    ru: 'Подать повторно',
                    en: 'Resubmit application',
                  ),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildHistoryCard(
    BuildContext context,
    AppLocalizations l,
    List<Map<String, dynamic>> apps,
  ) {
    return _SectionCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _SectionTitle(
            title: l.pick(
              uz: 'Oldingi arizalaringiz',
              ru: 'Ваши предыдущие заявления',
              en: 'Your previous applications',
            ),
          ),
          const SizedBox(height: 10),
          ...apps.map((app) {
            final status = app['status']?.toString() ?? 'pending';
            final color = switch (status) {
              'approved' => const Color(0xFF059669),
              'rejected' => const Color(0xFFDC2626),
              _ => const Color(0xFFD97706),
            };
            return Container(
              margin: const EdgeInsets.only(bottom: 10),
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: ClinicTheme.dividerOf(context)),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          app['english_level_label']?.toString() ?? '-',
                          style: TextStyle(
                            color: ClinicTheme.inkOf(context),
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          app['created_at']?.toString() ?? '-',
                          style: TextStyle(
                            color: ClinicTheme.mutedOf(context),
                            fontSize: 12,
                          ),
                        ),
                      ],
                    ),
                  ),
                  _StatusChip(
                    text: app['status_label']?.toString() ?? status,
                    color: color,
                  ),
                ],
              ),
            );
          }),
        ],
      ),
    );
  }
}

class _HeroCard extends StatelessWidget {
  final String title;
  final String subtitle;

  const _HeroCard({required this.title, required this.subtitle});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF0F766E), Color(0xFF10B981)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(22),
        boxShadow: ClinicTheme.cardShadow,
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 52,
            height: 52,
            decoration: BoxDecoration(
              color: Colors.white.withAlpha(28),
              borderRadius: BorderRadius.circular(16),
            ),
            child: const Icon(Icons.translate_rounded, color: Colors.white, size: 28),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 16,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  subtitle,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 12.5,
                    height: 1.45,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _SectionCard extends StatelessWidget {
  final Widget child;

  const _SectionCard({required this.child});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: ClinicTheme.surfaceOf(context),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: ClinicTheme.dividerOf(context)),
        boxShadow: ClinicTheme.cardShadow,
      ),
      child: child,
    );
  }
}

class _SectionTitle extends StatelessWidget {
  final String title;
  final String? subtitle;

  const _SectionTitle({required this.title, this.subtitle});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: TextStyle(
            color: ClinicTheme.inkOf(context),
            fontSize: 15,
            fontWeight: FontWeight.w900,
          ),
        ),
        if (subtitle != null) ...[
          const SizedBox(height: 4),
          Text(
            subtitle!,
            style: TextStyle(
              color: ClinicTheme.mutedOf(context),
              fontSize: 12.5,
              height: 1.4,
            ),
          ),
        ],
      ],
    );
  }
}

class _ReadOnlyField extends StatelessWidget {
  final String label;
  final String value;

  const _ReadOnlyField({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: TextStyle(
            color: ClinicTheme.mutedOf(context),
            fontSize: 11,
            fontWeight: FontWeight.w700,
          ),
        ),
        const SizedBox(height: 5),
        Container(
          width: double.infinity,
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 13),
          decoration: BoxDecoration(
            color: const Color(0xFFF8FAFC),
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: const Color(0xFFE2E8F0)),
          ),
          child: Text(
            value,
            style: TextStyle(
              color: ClinicTheme.inkOf(context),
              fontSize: 13,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ],
    );
  }
}

class _InputField extends StatelessWidget {
  final String label;
  final TextEditingController controller;
  final String hint;

  const _InputField({
    required this.label,
    required this.controller,
    required this.hint,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: TextStyle(
            color: ClinicTheme.mutedOf(context),
            fontSize: 11,
            fontWeight: FontWeight.w700,
          ),
        ),
        const SizedBox(height: 5),
        TextField(
          controller: controller,
          decoration: InputDecoration(
            hintText: hint,
            filled: true,
            fillColor: Colors.white,
            contentPadding:
                const EdgeInsets.symmetric(horizontal: 12, vertical: 13),
            enabledBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(14),
              borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(14),
              borderSide: const BorderSide(color: Color(0xFF0D9488), width: 1.4),
            ),
          ),
        ),
      ],
    );
  }
}

class _DropdownField extends StatelessWidget {
  final String label;
  final String? value;
  final List<DropdownMenuItem<String>> items;
  final ValueChanged<String?> onChanged;

  const _DropdownField({
    required this.label,
    required this.value,
    required this.items,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: TextStyle(
            color: ClinicTheme.mutedOf(context),
            fontSize: 11,
            fontWeight: FontWeight.w700,
          ),
        ),
        const SizedBox(height: 5),
        DropdownButtonFormField<String>(
          initialValue: value,
          items: items,
          onChanged: onChanged,
          decoration: InputDecoration(
            filled: true,
            fillColor: Colors.white,
            contentPadding:
                const EdgeInsets.symmetric(horizontal: 12, vertical: 13),
            enabledBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(14),
              borderSide: const BorderSide(color: Color(0xFFE2E8F0)),
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(14),
              borderSide: const BorderSide(color: Color(0xFF0D9488), width: 1.4),
            ),
          ),
        ),
      ],
    );
  }
}

class _UploadTile extends StatelessWidget {
  final String? fileName;
  final String title;
  final String subtitle;
  final VoidCallback onTap;
  final VoidCallback? onClear;

  const _UploadTile({
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
                    style: TextStyle(
                      color: ClinicTheme.mutedOf(context),
                      fontSize: 10.5,
                    ),
                  ),
                ],
              ),
            ),
            if (onClear != null)
              IconButton(
                onPressed: onClear,
                icon: Icon(
                  Icons.close_rounded,
                  color: ClinicTheme.mutedOf(context),
                  size: 18,
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _StatusChip extends StatelessWidget {
  final String text;
  final Color color;

  const _StatusChip({required this.text, required this.color});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: color.withAlpha(24),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        text,
        style: TextStyle(
          color: color,
          fontSize: 11,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  final String label;
  final String value;

  const _InfoRow({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        children: [
          SizedBox(
            width: 122,
            child: Text(
              label,
              style: TextStyle(
                color: ClinicTheme.mutedOf(context),
                fontSize: 12,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: TextStyle(
                color: ClinicTheme.inkOf(context),
                fontSize: 12.5,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _DangerBox extends StatelessWidget {
  final String text;

  const _DangerBox({required this.text});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFFEF2F2),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFFECACA)),
      ),
      child: Text(
        text,
        style: const TextStyle(
          color: Color(0xFFB91C1C),
          fontSize: 12.5,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

class _ErrorState extends StatelessWidget {
  final String message;
  final Future<void> Function() onRetry;

  const _ErrorState({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.error_outline_rounded,
                size: 46, color: ClinicTheme.mutedOf(context)),
            const SizedBox(height: 12),
            Text(
              message,
              textAlign: TextAlign.center,
              style: TextStyle(color: ClinicTheme.inkOf(context)),
            ),
            const SizedBox(height: 12),
            ElevatedButton(
              onPressed: onRetry,
              child: Text(l.reload),
            ),
          ],
        ),
      ),
    );
  }
}
