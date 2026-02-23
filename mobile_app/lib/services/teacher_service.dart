import '../config/api_config.dart';
import 'api_service.dart';

class TeacherService {
  final ApiService _api;

  TeacherService(this._api);

  Future<Map<String, dynamic>> getDashboard() async {
    return await _api.get(ApiConfig.teacherDashboard);
  }

  Future<Map<String, dynamic>> getProfile() async {
    return await _api.get(ApiConfig.teacherProfile);
  }

  Future<Map<String, dynamic>> getStudents({String? search, int page = 1}) async {
    final params = <String, String>{'page': page.toString()};
    if (search != null && search.isNotEmpty) params['search'] = search;
    return await _api.get(ApiConfig.teacherStudents, queryParams: params);
  }

  Future<Map<String, dynamic>> getGroups() async {
    return await _api.get(ApiConfig.teacherGroups);
  }

  Future<Map<String, dynamic>> getSemesters({required int groupId}) async {
    return await _api.get(ApiConfig.teacherSemesters, queryParams: {
      'group_id': groupId.toString(),
    });
  }

  Future<Map<String, dynamic>> getSubjects({
    required int groupId,
    required int semesterId,
  }) async {
    return await _api.get(ApiConfig.teacherSubjects, queryParams: {
      'group_id': groupId.toString(),
      'semester_id': semesterId.toString(),
    });
  }

  Future<Map<String, dynamic>> getStudentGrades({
    required int studentId,
    required int subjectId,
  }) async {
    return await _api.get(
      '${ApiConfig.teacherStudents}/$studentId/subjects/$subjectId/grades',
    );
  }

  Future<Map<String, dynamic>> getGroupStudentGrades({
    required int groupId,
    required int semesterId,
    required int subjectId,
  }) async {
    return await _api.get(ApiConfig.teacherGroupStudentGrades, queryParams: {
      'group_id': groupId.toString(),
      'semester_id': semesterId.toString(),
      'subject_id': subjectId.toString(),
    });
  }

  Future<Map<String, dynamic>> getJournal({
    required int groupId,
    required String subjectId,
    required String semesterCode,
  }) async {
    return await _api.get(ApiConfig.teacherJournal, queryParams: {
      'group_id': groupId.toString(),
      'subject_id': subjectId,
      'semester_code': semesterCode,
    });
  }

  Future<Map<String, dynamic>> saveOpenedLessonGrade({
    required String studentHemisId,
    required String subjectId,
    required String semesterCode,
    required String lessonDate,
    required String lessonPairCode,
    required double grade,
    required String groupHemisId,
  }) async {
    return await _api.post(ApiConfig.teacherSaveLessonGrade, {
      'student_hemis_id': studentHemisId,
      'subject_id': subjectId,
      'semester_code': semesterCode,
      'lesson_date': lessonDate,
      'lesson_pair_code': lessonPairCode,
      'grade': grade,
      'group_hemis_id': groupHemisId,
    }, auth: true);
  }

  Future<Map<String, dynamic>> saveMtGrade({
    required String studentHemisId,
    required String subjectId,
    required String semesterCode,
    required double grade,
    bool regrade = false,
  }) async {
    return await _api.post(ApiConfig.teacherSaveMtGrade, {
      'student_hemis_id': studentHemisId,
      'subject_id': subjectId,
      'semester_code': semesterCode,
      'grade': grade,
      'regrade': regrade,
    }, auth: true);
  }
}
