import 'dart:typed_data';
import '../config/api_config.dart';
import '../models/retake_models.dart';
import 'api_service.dart';

class RetakeService {
  final ApiService _api;

  RetakeService(this._api);

  /// GET /api/v1/student/retake/curriculum
  ///
  /// Talabaning akademik qarzdor fanlari va har biri uchun joriy ariza holati.
  Future<List<DebtSubject>> getDebts() async {
    final res = await _api.get(ApiConfig.studentRetakeCurriculum);
    final list = (res['data'] as List<dynamic>?) ?? const [];
    return list.map((e) => DebtSubject.fromJson(e as Map<String, dynamic>)).toList();
  }

  /// GET /api/v1/student/retake/period/active
  ///
  /// Joriy / yaqinlashayotgan / yopilgan oyna haqida ma'lumot.
  /// state: 'active' | 'upcoming' | 'closed' | 'no_period'
  Future<({RetakePeriod? period, String state, String? message})> getActivePeriod() async {
    final res = await _api.get(ApiConfig.studentRetakeActivePeriod);
    final data = res['data'] as Map<String, dynamic>?;
    return (
      period: data != null ? RetakePeriod.fromJson(data) : null,
      state: res['state'] as String? ?? 'no_period',
      message: res['message'] as String?,
    );
  }

  /// POST /api/v1/student/retake/applications  (multipart)
  ///
  /// Yangi ko'p fanli ariza yuborish.
  ///
  /// [subjects] — har element {subject_id: int, semester_id: int}
  /// [receiptBytes] + [receiptFileName] — kvitansiya fayli
  /// [studentNote] — ixtiyoriy izoh (max 500 belgi)
  Future<List<RetakeApplication>> submit({
    required List<Map<String, int>> subjects,
    required Uint8List receiptBytes,
    required String receiptFileName,
    String? studentNote,
  }) async {
    final fields = <String, String>{};
    for (var i = 0; i < subjects.length; i++) {
      final s = subjects[i];
      fields['subjects[$i][subject_id]'] = s['subject_id'].toString();
      fields['subjects[$i][semester_id]'] = s['semester_id'].toString();
    }
    if (studentNote != null && studentNote.trim().isNotEmpty) {
      fields['student_note'] = studentNote.trim();
    }

    final res = await _api.multipartPost(
      ApiConfig.studentRetakeApplications,
      fields,
      fileBytes: receiptBytes,
      fileName: receiptFileName,
      fileField: 'receipt',
    );

    final apps = ((res['data'] as Map<String, dynamic>?)?['applications'] as List<dynamic>?) ?? const [];
    return apps.map((e) => RetakeApplication.fromJson(e as Map<String, dynamic>)).toList();
  }

  /// GET /api/v1/student/retake/applications
  ///
  /// Talabaning barcha arizalari (eng yangidan).
  Future<List<RetakeApplication>> listApplications({String? applicationGroupId}) async {
    final params = <String, String>{};
    if (applicationGroupId != null) params['application_group_id'] = applicationGroupId;
    final res = await _api.get(ApiConfig.studentRetakeApplications, queryParams: params);
    final list = (res['data'] as List<dynamic>?) ?? const [];
    return list.map((e) => RetakeApplication.fromJson(e as Map<String, dynamic>)).toList();
  }

  /// GET /api/v1/student/retake/applications/{id}
  Future<RetakeApplication> getApplication(int id) async {
    final res = await _api.get('${ApiConfig.studentRetakeApplications}/$id');
    return RetakeApplication.fromJson(res['data'] as Map<String, dynamic>);
  }
}
