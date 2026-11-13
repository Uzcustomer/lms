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

  // Notifications
  Future<Map<String, dynamic>> getNotifications({int page = 1, int perPage = 30, bool unreadOnly = false}) async {
    return await _api.get(ApiConfig.studentNotifications, queryParams: {
      'page': page.toString(),
      'per_page': perPage.toString(),
      if (unreadOnly) 'unread_only': '1',
    });
  }

  Future<Map<String, dynamic>> getNotificationsUnreadCount() async {
    return await _api.get(ApiConfig.studentNotificationsUnreadCount);
  }

  Future<Map<String, dynamic>> markNotificationRead(int id) async {
    return await _api.post('${ApiConfig.studentNotifications}/$id/read', const {}, auth: true);
  }

  Future<Map<String, dynamic>> markAllNotificationsRead() async {
    return await _api.post(ApiConfig.studentNotificationsReadAll, const {}, auth: true);
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
    required Uint8List fileBytes,
    required String fileName,
    List<Map<String, String>>? makeupDates,
    bool retakeManual = false,
  }) async {
    final fields = <String, String>{
      'reason': reason,
      'doc_number': docNumber,
      'start_date': startDate,
      'end_date': endDate,
      // Talaba kunlarni ko'chirgan — server shu jadvalni qoidalar bo'yicha tekshiradi
      'retake_manual': retakeManual ? '1' : '0',
    };
    if (makeupDates != null) {
      for (int i = 0; i < makeupDates.length; i++) {
        makeupDates[i].forEach((key, value) => fields['makeup_dates[$i][$key]'] = value);
      }
    }
    return await _api.multipartPost(
      ApiConfig.studentExcuses,
      fields,
      fileBytes: fileBytes,
      fileName: fileName,
    );
  }

  // Retake application methods
  Future<Map<String, dynamic>> getRetakeApplications() async {
    return await _api.get(ApiConfig.studentRetake);
  }

  Future<Map<String, dynamic>> getRetakeJournal() async {
    return await _api.get(ApiConfig.studentRetakeJournal);
  }

  Future<Map<String, dynamic>> submitRetakeApplication({
    required List<Map<String, dynamic>> subjects,
    required Uint8List receiptBytes,
    required String receiptFileName,
    String? comment,
  }) async {
    final fields = <String, String>{};
    for (int i = 0; i < subjects.length; i++) {
      fields['subjects[$i][subject_id]'] =
          subjects[i]['subject_id']?.toString() ?? '';
      fields['subjects[$i][semester_id]'] =
          subjects[i]['semester_id']?.toString() ?? '';
    }
    if (comment != null && comment.trim().isNotEmpty) {
      fields['comment'] = comment.trim();
    }

    return await _api.multipartPost(
      ApiConfig.studentRetake,
      fields,
      fileBytes: receiptBytes,
      fileName: receiptFileName,
      fileField: 'receipt',
    );
  }

  Future<Map<String, dynamic>> uploadRetakePayment({
    required int groupId,
    required Uint8List paymentBytes,
    required String paymentFileName,
  }) async {
    return await _api.multipartPost(
      '${ApiConfig.studentRetake}/groups/$groupId/payment',
      const {},
      fileBytes: paymentBytes,
      fileName: paymentFileName,
      fileField: 'payment',
    );
  }

  Future<Map<String, dynamic>> uploadRetakeMustaqil({
    required int applicationId,
    required Uint8List fileBytes,
    required String fileName,
    String? comment,
  }) async {
    return await _api.multipartPost(
      '${ApiConfig.studentRetakeJournal}/$applicationId/mustaqil',
      {
        if (comment != null && comment.trim().isNotEmpty)
          'comment': comment.trim(),
      },
      fileBytes: fileBytes,
      fileName: fileName,
    );
  }

  // Club methods
  Future<Map<String, dynamic>> getClubs() async {
    return await _api.get(ApiConfig.studentClubs);
  }

  Future<Map<String, dynamic>> getMyClubs() async {
    return await _api.get(ApiConfig.studentMyClubs);
  }

  Future<Map<String, dynamic>> joinClub({
    required String clubName,
    String? clubPlace,
    String? clubDay,
    String? clubTime,
    String? kafedraName,
  }) async {
    return await _api.post(
      ApiConfig.studentJoinClub,
      {
        'club_name': clubName,
        if (clubPlace != null) 'club_place': clubPlace,
        if (clubDay != null) 'club_day': clubDay,
        if (clubTime != null) 'club_time': clubTime,
        if (kafedraName != null) 'kafedra_name': kafedraName,
      },
      auth: true,
    );
  }

  Future<Map<String, dynamic>> cancelClub(String clubName) async {
    return await _api.post(
      ApiConfig.studentCancelClub,
      {'club_name': clubName},
      auth: true,
    );
  }

  // Exam appeal methods
  Future<Map<String, dynamic>> getAppeals() async {
    return await _api.get(ApiConfig.studentAppeals);
  }

  Future<Map<String, dynamic>> getAppealAvailableGrades() async {
    return await _api.get(ApiConfig.studentAppealAvailableGrades);
  }

  Future<Map<String, dynamic>> getAppealDetail(int id) async {
    return await _api.get('${ApiConfig.studentAppeals}/$id');
  }

  Future<Map<String, dynamic>> submitAppeal({
    required int studentGradeId,
    required String reason,
    Uint8List? fileBytes,
    String? fileName,
  }) async {
    final fields = <String, String>{
      'student_grade_id': studentGradeId.toString(),
      'reason': reason,
    };
    return await _api.multipartPost(
      ApiConfig.studentAppeals,
      fields,
      fileBytes: fileBytes,
      fileName: fileName,
    );
  }

  Future<Map<String, dynamic>> addAppealComment({
    required int appealId,
    required String comment,
    Uint8List? fileBytes,
    String? fileName,
  }) async {
    final fields = <String, String>{'comment': comment};
    return await _api.multipartPost(
      '${ApiConfig.studentAppeals}/$appealId/comment',
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
