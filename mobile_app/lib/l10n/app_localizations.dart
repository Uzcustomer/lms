import 'package:flutter/material.dart';

class AppLocalizations {
  final Locale locale;

  AppLocalizations(this.locale);

  static AppLocalizations of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations) ??
        AppLocalizations(const Locale('uz'));
  }

  static const LocalizationsDelegate<AppLocalizations> delegate =
      _AppLocalizationsDelegate();

  String get(String key) {
    return _translations[locale.languageCode]?[key] ??
        _translations['uz']?[key] ??
        key;
  }

  // Convenience getters
  String get appTitle => get('app_title');
  String get home => get('home');
  String get grades => get('grades');
  String get schedule => get('schedule');
  String get profile => get('profile');
  String get settings => get('settings');
  String get theme => get('theme');
  String get language => get('language');
  String get darkMode => get('dark_mode');
  String get lightMode => get('light_mode');
  String get uzbek => get('uzbek');
  String get russian => get('russian');
  String get english => get('english');
  String get gpa => get('gpa');
  String get avgGrade => get('avg_grade');
  String get debts => get('debts');
  String get absences => get('absences');
  String get recentGrades => get('recent_grades');
  String get pending => get('pending');
  String get enrollmentYear => get('enrollment_year');
  String get educationYear => get('education_year');
  String get semester => get('semester');
  String get course => get('course');
  String get fillProfile => get('fill_profile');
  String get noData => get('no_data');
  String get reload => get('reload');
  String get logout => get('logout');
  String get logoutConfirm => get('logout_confirm');
  String get cancel => get('cancel');
  String get faculty => get('faculty');
  String get direction => get('direction');
  String get group => get('group');
  String get gender => get('gender');
  String get male => get('male');
  String get female => get('female');
  String get educationType => get('education_type');
  String get educationForm => get('education_form');
  String get province => get('province');
  String get district => get('district');
  String get personalInfo => get('personal_info');
  String get academicInfo => get('academic_info');
  String get level => get('level');
  String get greeting => get('greeting');
  String get services => get('services');
  String get tuitionFee => get('tuition_fee');
  String get paymentForm => get('payment_form');
  String get paid => get('paid');
  String get remaining => get('remaining');
  String get deadline => get('deadline');
  String get contractStudent => get('contract_student');
  String get grantStudent => get('grant_student');
  String get attendance => get('attendance');
  String get details => get('details');
  String get uploading => get('uploading');
  String get uploadSuccess => get('upload_success');
  String get absentHoursLabel => get('absent_hours_label');
  String get hours => get('hours');
  String get noSubjects => get('no_subjects');
  String get mtReupload => get('mt_reupload');
  String get mtGraded => get('mt_graded');
  String get mtUploaded => get('mt_uploaded');
  String get mtOverdue => get('mt_overdue');
  String get mtNotUploaded => get('mt_not_uploaded');
  String get mtDeadline => get('mt_deadline');
  String get mtRemaining => get('mt_remaining');
  String get mtUpload => get('mt_upload');

  // New getters
  String get lmsSubtitle => get('lms_subtitle');
  String get student => get('student');
  String get teacher => get('teacher');
  String get loginLabel => get('login_label');
  String get loginHint => get('login_hint');
  String get loginRequired => get('login_required');
  String get password => get('password');
  String get passwordHint => get('password_hint');
  String get passwordRequired => get('password_required');
  String get signIn => get('sign_in');
  String get verification => get('verification');
  String get telegramVerification => get('telegram_verification');
  String get telegramCodeHint => get('telegram_code_hint');
  String get verify => get('verify');
  String get resendCode => get('resend_code');
  String get resendIn => get('resend_in');
  String get telegramNotConnected => get('telegram_not_connected');
  String get connectTelegram => get('connect_telegram');
  String get telegramUsername => get('telegram_username');
  String get telegramUsernameHint => get('telegram_username_hint');
  String get telegramSave => get('telegram_save');
  String get telegramVerificationCode => get('telegram_verification_code');
  String get telegramSendCodeToBot => get('telegram_send_code_to_bot');
  String get telegramOpenBot => get('telegram_open_bot');
  String get telegramVerified => get('telegram_verified');
  String get telegramChecking => get('telegram_checking');
  String get telegramDaysLeft => get('telegram_days_left');
  String get profileNotFound => get('profile_not_found');
  String get scheduleNotFound => get('schedule_not_found');
  String get noScheduleThisWeek => get('no_schedule_this_week');
  String get noLessons => get('no_lessons');
  String get today => get('today');
  String get lessonUnit => get('lesson_unit');
  String get practicalClasses => get('practical_classes');
  String get lectures => get('lectures');
  String get selfStudy => get('self_study');
  String get average => get('average');
  String get currentControl => get('current_control');
  String get midtermControl => get('midterm_control');
  String get finalGrade => get('final_grade');
  String get totalGrades => get('total_grades');
  String get students => get('students');
  String get groups => get('groups');
  String get groupsNotFound => get('groups_not_found');
  String get studentsNotFound => get('students_not_found');
  String get searchStudent => get('search_student');
  String get workInfo => get('work_info');
  String get department => get('department');
  String get position => get('position');

  static const Map<String, Map<String, String>> _translations = {
    'uz': {
      'app_title': 'Tashmedunitf Lms',
      'home': 'Bosh sahifa',
      'grades': 'Baholar',
      'schedule': 'Jadval',
      'profile': 'Profil',
      'settings': 'Sozlamalar',
      'theme': 'Mavzu',
      'language': 'Til',
      'dark_mode': 'Qorong\'u rejim',
      'light_mode': 'Yorug\' rejim',
      'uzbek': 'O\'zbekcha',
      'russian': 'Ruscha',
      'english': 'Inglizcha',
      'gpa': 'GPA',
      'avg_grade': 'O\'rtacha baho',
      'debts': 'Qarzlar',
      'absences': 'Darsga kelmagan',
      'recent_grades': 'So\'nggi baholar',
      'pending': 'Kutilmoqda',
      'enrollment_year': 'Kirgan yili',
      'education_year': 'O\'quv yili',
      'semester': 'Semestr',
      'course': 'Kurs',
      'fill_profile': 'Profilni to\'ldirish',
      'no_data': 'Ma\'lumot topilmadi',
      'reload': 'Qayta yuklash',
      'logout': 'Chiqish',
      'logout_confirm': 'Haqiqatan ham chiqmoqchimisiz?',
      'cancel': 'Bekor qilish',
      'faculty': 'Fakultet',
      'direction': 'Yo\'nalish',
      'group': 'Guruh',
      'gender': 'Jinsi',
      'male': 'Erkak',
      'female': 'Ayol',
      'education_type': 'Ta\'lim shakli',
      'education_form': 'Ta\'lim turi',
      'province': 'Viloyat',
      'district': 'Tuman',
      'personal_info': 'Shaxsiy ma\'lumotlar',
      'academic_info': 'Akademik ma\'lumotlar',
      'level': 'Bosqich',
      'greeting': 'Assalomu alaykum!',
      'attendance': 'Davomat',
      'mt_upload': 'MT yuklash',
      'details': 'Batafsil',
      'uploading': 'Yuklanmoqda...',
      'upload_success': 'Fayl muvaffaqiyatli yuklandi',
      'absent_hours_label': 'Sababsiz soatlar',
      'hours': 'soat',
      'no_subjects': 'Fanlar topilmadi',
      'mt_reupload': 'Qayta yuklash',
      'mt_graded': 'Baholangan',
      'mt_uploaded': 'Yuklangan',
      'mt_overdue': 'Muddat tugagan',
      'mt_not_uploaded': 'Yuklanmagan',
      'mt_deadline': 'Muddat',
      'mt_remaining': 'Qolgan urinish',
      'services': 'Xizmatlar',
      'tuition_fee': 'Shartnoma turi',
      'payment_form': 'To\'lov shakli',
      'paid': 'To\'langan',
      'remaining': 'Qoldiq',
      'deadline': 'Muddat',
      'contract_student': 'Kontrakt',
      'grant_student': 'Grant',
      'lms_subtitle': 'Ta\'lim boshqaruv tizimi',
      'student': 'Talaba',
      'teacher': 'O\'qituvchi',
      'login_label': 'Login',
      'login_hint': 'Student ID yoki login',
      'login_required': 'Login kiriting',
      'password': 'Parol',
      'password_hint': 'Parolingizni kiriting',
      'password_required': 'Parol kiriting',
      'sign_in': 'Kirish',
      'verification': 'Tasdiqlash',
      'telegram_verification': 'Telegram tasdiqlash',
      'telegram_code_hint': 'Telegram botga yuborilgan 6 xonali kodni kiriting',
      'verify': 'Tasdiqlash',
      'resend_code': 'Kodni qayta yuborish',
      'resend_in': 'Qayta yuborish',
      'telegram_not_connected': 'Telegram ulangan emas',
      'connect_telegram': 'Telegram ulash',
      'telegram_username': 'Telegram username',
      'telegram_username_hint': '@username',
      'telegram_save': 'Saqlash',
      'telegram_verification_code': 'Tasdiqlash kodi',
      'telegram_send_code_to_bot': 'Ushbu kodni Telegram botga yuboring',
      'telegram_open_bot': 'Botni ochish',
      'telegram_verified': 'Telegram tasdiqlangan',
      'telegram_checking': 'Tekshirilmoqda...',
      'telegram_days_left': 'kun qoldi',
      'profile_not_found': 'Profil topilmadi',
      'schedule_not_found': 'Jadval topilmadi',
      'no_schedule_this_week': 'Bu hafta uchun jadval topilmadi',
      'no_lessons': 'Dars yo\'q',
      'today': 'Bugun',
      'lesson_unit': 'para',
      'practical_classes': 'Amaliy mashg\'ulotlar',
      'lectures': 'Ma\'ruzalar',
      'self_study': 'Mustaqil ta\'lim',
      'average': 'O\'rt',
      'current_control': 'Joriy nazorat',
      'midterm_control': 'Oraliq nazorat',
      'final_grade': 'Yakuniy',
      'total_grades': 'Jami baholar',
      'students': 'Talabalar',
      'groups': 'Guruhlar',
      'groups_not_found': 'Guruhlar topilmadi',
      'students_not_found': 'Talabalar topilmadi',
      'search_student': 'Talaba qidirish...',
      'work_info': 'Ish ma\'lumotlari',
      'department': 'Kafedra',
      'position': 'Lavozim',
      'phone': 'Telefon',
    },
    'ru': {
      'app_title': 'Tashmedunitf Lms',
      'home': 'Главная',
      'grades': 'Оценки',
      'schedule': 'Расписание',
      'profile': 'Профиль',
      'settings': 'Настройки',
      'theme': 'Тема',
      'language': 'Язык',
      'dark_mode': 'Тёмный режим',
      'light_mode': 'Светлый режим',
      'uzbek': 'Узбекский',
      'russian': 'Русский',
      'english': 'Английский',
      'gpa': 'GPA',
      'avg_grade': 'Средний балл',
      'debts': 'Задолженности',
      'absences': 'Пропуски',
      'recent_grades': 'Последние оценки',
      'pending': 'Ожидается',
      'enrollment_year': 'Год поступления',
      'education_year': 'Учебный год',
      'semester': 'Семестр',
      'course': 'Курс',
      'fill_profile': 'Заполнить профиль',
      'no_data': 'Данные не найдены',
      'reload': 'Обновить',
      'logout': 'Выход',
      'logout_confirm': 'Вы действительно хотите выйти?',
      'cancel': 'Отмена',
      'faculty': 'Факультет',
      'direction': 'Направление',
      'group': 'Группа',
      'gender': 'Пол',
      'male': 'Мужской',
      'female': 'Женский',
      'education_type': 'Форма обучения',
      'education_form': 'Вид обучения',
      'province': 'Область',
      'district': 'Район',
      'personal_info': 'Личные данные',
      'academic_info': 'Академические данные',
      'level': 'Уровень',
      'greeting': 'Здравствуйте!',
      'attendance': 'Посещаемость',
      'mt_upload': 'Загрузить СР',
      'details': 'Подробнее',
      'uploading': 'Загрузка...',
      'upload_success': 'Файл успешно загружен',
      'absent_hours_label': 'Пропущенные часы',
      'hours': 'ч.',
      'no_subjects': 'Предметы не найдены',
      'mt_reupload': 'Перезагрузить',
      'mt_graded': 'Оценено',
      'mt_uploaded': 'Загружено',
      'mt_overdue': 'Срок истёк',
      'mt_not_uploaded': 'Не загружено',
      'mt_deadline': 'Срок',
      'mt_remaining': 'Осталось попыток',
      'services': 'Услуги',
      'tuition_fee': 'Тип договора',
      'payment_form': 'Форма оплаты',
      'paid': 'Оплачено',
      'remaining': 'Остаток',
      'deadline': 'Срок',
      'contract_student': 'Контракт',
      'grant_student': 'Грант',
      'lms_subtitle': 'Система управления обучением',
      'student': 'Студент',
      'teacher': 'Преподаватель',
      'login_label': 'Логин',
      'login_hint': 'ID студента или логин',
      'login_required': 'Введите логин',
      'password': 'Пароль',
      'password_hint': 'Введите пароль',
      'password_required': 'Введите пароль',
      'sign_in': 'Войти',
      'verification': 'Подтверждение',
      'telegram_verification': 'Подтверждение через Telegram',
      'telegram_code_hint': 'Введите 6-значный код из Telegram бота',
      'verify': 'Подтвердить',
      'resend_code': 'Отправить код повторно',
      'resend_in': 'Повторная отправка',
      'telegram_not_connected': 'Telegram не подключён',
      'connect_telegram': 'Подключить Telegram',
      'telegram_username': 'Telegram username',
      'telegram_username_hint': '@username',
      'telegram_save': 'Сохранить',
      'telegram_verification_code': 'Код подтверждения',
      'telegram_send_code_to_bot': 'Отправьте этот код Telegram боту',
      'telegram_open_bot': 'Открыть бота',
      'telegram_verified': 'Telegram подтверждён',
      'telegram_checking': 'Проверка...',
      'telegram_days_left': 'дней осталось',
      'profile_not_found': 'Профиль не найден',
      'schedule_not_found': 'Расписание не найдено',
      'no_schedule_this_week': 'Расписание на эту неделю не найдено',
      'no_lessons': 'Нет занятий',
      'today': 'Сегодня',
      'lesson_unit': 'пара',
      'practical_classes': 'Практические занятия',
      'lectures': 'Лекции',
      'self_study': 'Самостоятельная работа',
      'average': 'Ср.',
      'current_control': 'Текущий контроль',
      'midterm_control': 'Промежуточный контроль',
      'final_grade': 'Итоговая',
      'total_grades': 'Всего оценок',
      'students': 'Студенты',
      'groups': 'Группы',
      'groups_not_found': 'Группы не найдены',
      'students_not_found': 'Студенты не найдены',
      'search_student': 'Поиск студента...',
      'work_info': 'Рабочие данные',
      'department': 'Кафедра',
      'position': 'Должность',
      'phone': 'Телефон',
    },
    'en': {
      'app_title': 'Tashmedunitf Lms',
      'home': 'Home',
      'grades': 'Grades',
      'schedule': 'Schedule',
      'profile': 'Profile',
      'settings': 'Settings',
      'theme': 'Theme',
      'language': 'Language',
      'dark_mode': 'Dark mode',
      'light_mode': 'Light mode',
      'uzbek': 'Uzbek',
      'russian': 'Russian',
      'english': 'English',
      'gpa': 'GPA',
      'avg_grade': 'Average grade',
      'debts': 'Debts',
      'absences': 'Absences',
      'recent_grades': 'Recent grades',
      'pending': 'Pending',
      'enrollment_year': 'Enrollment year',
      'education_year': 'Academic year',
      'semester': 'Semester',
      'course': 'Course',
      'fill_profile': 'Complete profile',
      'no_data': 'No data found',
      'reload': 'Reload',
      'logout': 'Log out',
      'logout_confirm': 'Are you sure you want to log out?',
      'cancel': 'Cancel',
      'faculty': 'Faculty',
      'direction': 'Major',
      'group': 'Group',
      'gender': 'Gender',
      'male': 'Male',
      'female': 'Female',
      'education_type': 'Education type',
      'education_form': 'Education form',
      'province': 'Province',
      'district': 'District',
      'personal_info': 'Personal info',
      'academic_info': 'Academic info',
      'level': 'Level',
      'greeting': 'Welcome!',
      'attendance': 'Attendance',
      'mt_upload': 'Upload IW',
      'details': 'Details',
      'uploading': 'Uploading...',
      'upload_success': 'File uploaded successfully',
      'absent_hours_label': 'Absent hours',
      'hours': 'hrs',
      'no_subjects': 'No subjects found',
      'mt_reupload': 'Reupload',
      'mt_graded': 'Graded',
      'mt_uploaded': 'Uploaded',
      'mt_overdue': 'Overdue',
      'mt_not_uploaded': 'Not uploaded',
      'mt_deadline': 'Deadline',
      'mt_remaining': 'Remaining attempts',
      'services': 'Services',
      'tuition_fee': 'Contract Type',
      'payment_form': 'Payment form',
      'paid': 'Paid',
      'remaining': 'Remaining',
      'deadline': 'Deadline',
      'contract_student': 'Contract',
      'grant_student': 'Grant',
      'lms_subtitle': 'Learning Management System',
      'student': 'Student',
      'teacher': 'Teacher',
      'login_label': 'Login',
      'login_hint': 'Student ID or login',
      'login_required': 'Enter login',
      'password': 'Password',
      'password_hint': 'Enter your password',
      'password_required': 'Enter password',
      'sign_in': 'Sign in',
      'verification': 'Verification',
      'telegram_verification': 'Telegram verification',
      'telegram_code_hint': 'Enter the 6-digit code sent to your Telegram bot',
      'verify': 'Verify',
      'resend_code': 'Resend code',
      'resend_in': 'Resend in',
      'telegram_not_connected': 'Telegram not connected',
      'connect_telegram': 'Connect Telegram',
      'telegram_username': 'Telegram username',
      'telegram_username_hint': '@username',
      'telegram_save': 'Save',
      'telegram_verification_code': 'Verification code',
      'telegram_send_code_to_bot': 'Send this code to the Telegram bot',
      'telegram_open_bot': 'Open bot',
      'telegram_verified': 'Telegram verified',
      'telegram_checking': 'Checking...',
      'telegram_days_left': 'days left',
      'profile_not_found': 'Profile not found',
      'schedule_not_found': 'Schedule not found',
      'no_schedule_this_week': 'No schedule found for this week',
      'no_lessons': 'No lessons',
      'today': 'Today',
      'lesson_unit': 'lesson',
      'practical_classes': 'Practical classes',
      'lectures': 'Lectures',
      'self_study': 'Self-study',
      'average': 'Avg',
      'current_control': 'Current control',
      'midterm_control': 'Midterm control',
      'final_grade': 'Final',
      'total_grades': 'Total grades',
      'students': 'Students',
      'groups': 'Groups',
      'groups_not_found': 'No groups found',
      'students_not_found': 'No students found',
      'search_student': 'Search student...',
      'work_info': 'Work info',
      'department': 'Department',
      'position': 'Position',
      'phone': 'Phone',
    },
  };

  static const List<Locale> supportedLocales = [
    Locale('uz'),
    Locale('ru'),
    Locale('en'),
  ];
}

class _AppLocalizationsDelegate
    extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  bool isSupported(Locale locale) {
    return ['uz', 'ru', 'en'].contains(locale.languageCode);
  }

  @override
  Future<AppLocalizations> load(Locale locale) async {
    return AppLocalizations(locale);
  }

  @override
  bool shouldReload(covariant LocalizationsDelegate<AppLocalizations> old) =>
      false;
}
