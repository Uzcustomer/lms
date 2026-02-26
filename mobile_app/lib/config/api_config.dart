class ApiConfig {
  static const String baseUrl = 'https://mark.tashmedunitf.uz/api/v1';

  // Auth endpoints
  static const String studentLogin = '/student/login';
  static const String studentVerify2fa = '/student/verify-2fa';
  static const String studentResend2fa = '/student/resend-2fa';
  static const String teacherLogin = '/teacher/login';
  static const String teacherVerify2fa = '/teacher/verify-2fa';
  static const String teacherResend2fa = '/teacher/resend-2fa';

  // Common
  static const String me = '/me';
  static const String logout = '/logout';
  static const String imageProxy = '/image-proxy';

  // Student endpoints
  static const String studentDashboard = '/student/dashboard';
  static const String studentProfile = '/student/profile';
  static const String studentSchedule = '/student/schedule';
  static const String studentSubjects = '/student/subjects';
  static const String studentAttendance = '/student/attendance';
  static const String studentPendingLessons = '/student/pending-lessons';
  static const String studentSavePhone = '/student/complete-profile/phone';
  static const String studentSaveTelegram = '/student/complete-profile/telegram';
  static const String studentCheckTelegram = '/student/complete-profile/telegram/check';

  // Student excuse requests
  static const String studentExcuseRequests = '/student/excuse-requests';

  // Teacher endpoints
  static const String teacherDashboard = '/teacher/dashboard';
  static const String teacherProfile = '/teacher/profile';
  static const String teacherStudents = '/teacher/students';
  static const String teacherGroups = '/teacher/groups';
  static const String teacherSemesters = '/teacher/semesters';
  static const String teacherSubjects = '/teacher/subjects';
  static const String teacherGroupStudentGrades = '/teacher/group-student-grades';
  static const String teacherActiveSubjects = '/teacher/active-subjects';
  static const String teacherJournal = '/teacher/journal';
  static const String teacherSaveLessonGrade = '/teacher/grades/lesson';
  static const String teacherSaveMtGrade = '/teacher/grades/mt';
}
