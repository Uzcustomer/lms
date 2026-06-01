import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../config/api_config.dart';
import '../../l10n/app_localizations.dart';
import '../../providers/student_provider.dart';
import '../../services/api_service.dart';
import '../../widgets/clinic_header.dart';
import '../../widgets/loading_widget.dart';

class AbsenceExcuseDetailScreen extends StatefulWidget {
  final int excuseId;

  const AbsenceExcuseDetailScreen({super.key, required this.excuseId});

  @override
  State<AbsenceExcuseDetailScreen> createState() => _AbsenceExcuseDetailScreenState();
}

class _AbsenceExcuseDetailScreenState extends State<AbsenceExcuseDetailScreen> {
  Map<String, dynamic>? _excuse;
  bool _isLoading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadDetail();
  }

  Future<void> _loadDetail() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final provider = context.read<StudentProvider>();
      final response = await provider.getExcuseDetail(widget.excuseId);
      setState(() {
        _excuse = response['data'] as Map<String, dynamic>?;
      });
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      setState(() => _isLoading = false);
    }
  }

  Future<void> _downloadPdf() async {
    final url = '${ApiConfig.baseUrl}${ApiConfig.studentExcuses}/${widget.excuseId}/download-pdf';
    final apiService = ApiService();
    final authToken = await apiService.getToken();
    final uri = Uri.parse('$url?token=$authToken');
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  Color _statusColor(String status) {
    switch (status) {
      case 'approved':
        return const Color(0xFF047857);
      case 'rejected':
        return const Color(0xFFBE123C);
      default:
        return const Color(0xFFB45309);
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
    final cardColor = ClinicTheme.surfaceOf(context);
    final textColor = ClinicTheme.inkOf(context);
    final subColor = ClinicTheme.mutedOf(context);

    return Scaffold(
      backgroundColor: ClinicTheme.bgOf(context),
      body: Column(
        children: [
          ClinicHeader(
            overline: 'XIZMATLAR',
            title: l.absenceExcuse,
            onBack: () => Navigator.pop(context),
          ),
          Expanded(
            child: _isLoading
                ? const LoadingWidget()
                : _error != null
                    ? Center(child: Text(_error!, style: const TextStyle(color: Color(0xFFBE123C))))
                    : _excuse == null
                        ? Center(child: Text(l.noData))
                        : RefreshIndicator(
                            onRefresh: _loadDetail,
                            child: ListView(
                              padding: const EdgeInsets.all(14),
                              children: [
                                _buildStatusCard(cardColor, textColor, subColor, l),
                                const SizedBox(height: 12),
                                _buildInfoCard(cardColor, textColor, subColor, l),
                                const SizedBox(height: 12),
                                if (_excuse!['status'] == 'rejected' && _excuse!['rejection_reason'] != null)
                                  _buildRejectionCard(cardColor, l),
                                if (_excuse!['has_approved_pdf'] == true) ...[
                                  const SizedBox(height: 12),
                                  _buildPdfDownloadCard(cardColor, l),
                                ],
                                if (_excuse!['makeups'] != null && (_excuse!['makeups'] as List).isNotEmpty) ...[
                                  const SizedBox(height: 12),
                                  _buildMakeupsCard(cardColor, textColor, subColor, l),
                                ],
                              ],
                            ),
                          ),
          ),
        ],
      ),
    );
  }

  Widget _buildStatusCard(Color cardColor, Color textColor, Color subColor, AppLocalizations l) {
    final status = _excuse!['status'] ?? 'pending';
    final color = _statusColor(status);
    final icon = _statusIcon(status);

    return Container(
      decoration: BoxDecoration(
        color: color.withOpacity(0.08),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: color.withOpacity(0.25)),
      ),
      padding: const EdgeInsets.all(20),
      child: Row(
        children: [
          Icon(icon, size: 40, color: color),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _excuse!['status_label'] ?? '',
                  style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700, color: color),
                ),
                if (_excuse!['reviewed_by_name'] != null) ...[
                  const SizedBox(height: 4),
                  Text(
                    '${l.reviewedBy}: ${_excuse!['reviewed_by_name']}',
                    style: TextStyle(fontSize: 13, color: subColor),
                  ),
                ],
                if (_excuse!['reviewed_at'] != null)
                  Text(
                    _excuse!['reviewed_at'],
                    style: TextStyle(fontSize: 12, color: subColor),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildInfoCard(Color cardColor, Color textColor, Color subColor, AppLocalizations l) {
    return Container(
      decoration: BoxDecoration(
        color: cardColor,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: ClinicTheme.dividerOf(context), width: 1),
        boxShadow: ClinicTheme.cardShadow,
      ),
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _infoRow(Icons.description, l.selectReason, _excuse!['reason_label'] ?? '', textColor, subColor),
          Divider(height: 20, color: ClinicTheme.dividerOf(context)),
          _infoRow(Icons.numbers, l.docNumber, '#${_excuse!['doc_number'] ?? ''}', textColor, subColor),
          Divider(height: 20, color: ClinicTheme.dividerOf(context)),
          _infoRow(Icons.calendar_today, '${l.startDate} — ${l.endDate}',
              '${_excuse!['start_date']} — ${_excuse!['end_date']}', textColor, subColor),
          if (_excuse!['description'] != null && _excuse!['description'].toString().isNotEmpty) ...[
            Divider(height: 20, color: ClinicTheme.dividerOf(context)),
            _infoRow(Icons.notes, l.description, _excuse!['description'], textColor, subColor),
          ],
          Divider(height: 20, color: ClinicTheme.dividerOf(context)),
          _infoRow(Icons.attach_file, l.file, _excuse!['file_original_name'] ?? '', textColor, subColor),
        ],
      ),
    );
  }

  Widget _infoRow(IconData icon, String label, String value, Color textColor, Color subColor) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 18, color: subColor),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(label, style: TextStyle(fontSize: 12, color: subColor)),
              const SizedBox(height: 2),
              Text(value, style: TextStyle(fontSize: 14, fontWeight: FontWeight.w500, color: textColor)),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildRejectionCard(Color cardColor, AppLocalizations l) {
    const rose = Color(0xFFBE123C);
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: rose.withOpacity(0.06),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: rose.withOpacity(0.25)),
      ),
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.cancel, size: 18, color: rose),
              const SizedBox(width: 8),
              Text(l.rejectionReason, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: rose)),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            _excuse!['rejection_reason'],
            style: TextStyle(fontSize: 14, color: ClinicTheme.inkOf(context)),
          ),
        ],
      ),
    );
  }

  Widget _buildPdfDownloadCard(Color cardColor, AppLocalizations l) {
    return SizedBox(
      height: 52,
      child: ElevatedButton.icon(
        onPressed: _downloadPdf,
        icon: const Icon(Icons.picture_as_pdf),
        label: Text(l.downloadPdf, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w600)),
        style: ElevatedButton.styleFrom(
          backgroundColor: ClinicTheme.teal,
          foregroundColor: Colors.white,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        ),
      ),
    );
  }

  Widget _buildMakeupsCard(Color cardColor, Color textColor, Color subColor, AppLocalizations l) {
    final makeups = _excuse!['makeups'] as List;
    final accent = ClinicTheme.teal;

    return Container(
      decoration: BoxDecoration(
        color: cardColor,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: ClinicTheme.dividerOf(context), width: 1),
        boxShadow: ClinicTheme.cardShadow,
      ),
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.assignment_late, size: 20, color: accent),
              const SizedBox(width: 8),
              Text(l.missedAssessments, style: TextStyle(fontSize: 15, fontWeight: FontWeight.w600, color: textColor)),
            ],
          ),
          const SizedBox(height: 12),
          ...makeups.map((m) {
            final makeup = m as Map<String, dynamic>;
            return Container(
              margin: const EdgeInsets.only(bottom: 8),
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: accent.withOpacity(0.05),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: accent.withOpacity(0.18)),
              ),
              child: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                    decoration: BoxDecoration(
                      color: accent.withOpacity(0.12),
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Text(
                      (makeup['assessment_type'] ?? '').toString().toUpperCase(),
                      style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: accent),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(makeup['subject_name'] ?? '', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w500, color: textColor)),
                        Text(makeup['original_date'] ?? '', style: TextStyle(fontSize: 12, color: subColor)),
                      ],
                    ),
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
