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

  Future<Map<String, dynamic>> getSchedule({String? semesterCode, String? week}) async {
    final params = <String, String>{};
    if (semesterCode != null) params['semester_code'] = semesterCode;
    if (week != null) params['week'] = week;
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

  Future<Map<String, dynamic>> getAbsenceExcuseReasons() async {
    return await _api.get(ApiConfig.studentAbsenceExcuseReasons);
  }

  Future<Map<String, dynamic>> getAbsenceExcuses() async {
    return await _api.get(ApiConfig.studentAbsenceExcuses);
  }

  Future<Map<String, dynamic>> storeAbsenceExcuse({
    required String reason,
    required String startDate,
    required String endDate,
    String? description,
    required dynamic fileBytes,
    required String fileName,
  }) async {
    return await _api.uploadFileWithFields(
      ApiConfig.studentAbsenceExcuses,
      fileBytes,
      fileName,
      fields: {
        'reason': reason,
        'start_date': startDate,
        'end_date': endDate,
        if (description != null && description.isNotEmpty) 'description': description,
      },
    );
  }

  Future<Map<String, dynamic>> getAbsenceExcuse(int id) async {
    return await _api.get('${ApiConfig.studentAbsenceExcuses}/$id');
  }
}
