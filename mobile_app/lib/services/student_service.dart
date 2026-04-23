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

  Future<Map<String, dynamic>> getContract() async {
    return await _api.get(ApiConfig.studentContract);
  }

  Future<Map<String, dynamic>> getExamSchedule() async {
    return await _api.get(ApiConfig.studentExamSchedule);
  }

  Future<Map<String, dynamic>> getRating({String filter = 'group'}) async {
    return await _api.get(ApiConfig.studentRating, queryParams: {'filter': filter});
  }

  // Absence excuse methods
  Future<Map<String, dynamic>> getExcuseReasons() async {
    return await _api.get(ApiConfig.studentExcuseReasons);
  }

  Future<Map<String, dynamic>> getExcuses() async {
    return await _api.get(ApiConfig.studentExcuses);
  }

  Future<Map<String, dynamic>> getExcuseDetail(int id) async {
    return await _api.get('${ApiConfig.studentExcuses}/$id');
  }

  Future<Map<String, dynamic>> getMissedAssessments(String startDate, String endDate) async {
    return await _api.post(
      ApiConfig.studentExcuseMissedAssessments,
      {'start_date': startDate, 'end_date': endDate},
      auth: true,
    );
  }

  Future<Map<String, dynamic>> submitExcuse({
    required String reason,
    required String docNumber,
    required String startDate,
    required String endDate,
    String? description,
    required Uint8List fileBytes,
    required String fileName,
    List<Map<String, dynamic>>? makeupDates,
  }) async {
    final fields = <String, String>{
      'reason': reason,
      'doc_number': docNumber,
      'start_date': startDate,
      'end_date': endDate,
    };
    if (description != null && description.isNotEmpty) {
      fields['description'] = description;
    }
    if (makeupDates != null) {
      for (int i = 0; i < makeupDates.length; i++) {
        final m = makeupDates[i];
        fields['makeup_dates[$i][subject_name]'] = m['subject_name'] ?? '';
        fields['makeup_dates[$i][subject_id]'] = m['subject_id'] ?? '';
        fields['makeup_dates[$i][assessment_type]'] = m['assessment_type'] ?? '';
        fields['makeup_dates[$i][assessment_type_code]'] = m['assessment_type_code'] ?? '';
        fields['makeup_dates[$i][original_date]'] = m['original_date'] ?? '';
        if (m['assessment_type'] == 'jn') {
          fields['makeup_dates[$i][makeup_start]'] = m['makeup_start'] ?? '';
          fields['makeup_dates[$i][makeup_end]'] = m['makeup_end'] ?? '';
          fields['makeup_dates[$i][makeup_date]'] = '';
        } else {
          fields['makeup_dates[$i][makeup_date]'] = m['makeup_date'] ?? '';
          fields['makeup_dates[$i][makeup_start]'] = '';
          fields['makeup_dates[$i][makeup_end]'] = '';
        }
      }
    }
    return await _api.multipartPost(
      ApiConfig.studentExcuses,
      fields,
      fileBytes: fileBytes,
      fileName: fileName,
    );
  }

  // Chat methods
  Future<Map<String, dynamic>> getChatContacts() async {
    return await _api.get(ApiConfig.chatContacts);
  }

  Future<Map<String, dynamic>> getChatMessages(int contactId) async {
    return await _api.get('${ApiConfig.chatMessages}/$contactId');
  }

  Future<Map<String, dynamic>> sendChatMessage(int receiverId, String message) async {
    return await _api.post(
      ApiConfig.chatSend,
      {'receiver_id': receiverId, 'message': message},
      auth: true,
    );
  }

  Future<Map<String, dynamic>> getGroupMessages() async {
    return await _api.get(ApiConfig.chatGroup);
  }

  Future<Map<String, dynamic>> sendGroupMessage(String message) async {
    return await _api.post(
      ApiConfig.chatGroupSend,
      {'message': message},
      auth: true,
    );
  }
}
