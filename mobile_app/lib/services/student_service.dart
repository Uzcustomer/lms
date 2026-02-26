import 'dart:typed_data';
import '../config/api_config.dart';
import 'api_service.dart';

class StudentService {
  final ApiService _api;

  StudentService(this._api);

  Future<Map<String, dynamic>> getDashboard() async {
    return await _api.get(ApiConfig.studentDashboard);
  }

  Future<Map<String, dynamic>> getProfile() async {
    return await _api.get(ApiConfig.studentProfile);
  }

  Future<Map<String, dynamic>> getSchedule({String? semesterId, String? weekId}) async {
    final params = <String, String>{};
    if (semesterId != null) params['semester_id'] = semesterId;
    if (weekId != null) params['week_id'] = weekId;
    return await _api.get(ApiConfig.studentSchedule, queryParams: params);
  }

  Future<Map<String, dynamic>> getSubjects({String? semesterCode}) async {
    final params = <String, String>{};
    if (semesterCode != null) params['semester_code'] = semesterCode;
    return await _api.get(ApiConfig.studentSubjects, queryParams: params);
  }

  Future<Map<String, dynamic>> getSubjectGrades(int subjectId) async {
    return await _api.get('${ApiConfig.studentSubjects}/$subjectId/grades');
  }

  Future<Map<String, dynamic>> getAttendance({String? semesterCode}) async {
    final params = <String, String>{};
    if (semesterCode != null) params['semester_code'] = semesterCode;
    return await _api.get(ApiConfig.studentAttendance, queryParams: params);
  }

  Future<Map<String, dynamic>> getPendingLessons() async {
    return await _api.get(ApiConfig.studentPendingLessons);
  }

  Future<Map<String, dynamic>> saveTelegram(String telegramUsername) async {
    return await _api.post(
      ApiConfig.studentSaveTelegram,
      {'telegram_username': telegramUsername},
      auth: true,
    );
  }

  Future<Map<String, dynamic>> checkTelegramVerification() async {
    return await _api.get(ApiConfig.studentCheckTelegram);
  }

  Future<Map<String, dynamic>> getExcuseRequests() async {
    return await _api.get(ApiConfig.studentExcuseRequests);
  }

  Future<Map<String, dynamic>> createExcuseRequest({
    required String type,
    required String subjectName,
    required String reason,
    required Uint8List fileBytes,
    required String fileName,
  }) async {
    return await _api.uploadFileWithFields(
      ApiConfig.studentExcuseRequests,
      fileBytes: fileBytes,
      fileName: fileName,
      fields: {
        'type': type,
        'subject_name': subjectName,
        'reason': reason,
      },
    );
  }
}
