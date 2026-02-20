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

  // Convenience getters for common strings
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

  static const Map<String, Map<String, String>> _translations = {
    'uz': {
      'app_title': 'TDTU LMS',
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
    },
    'ru': {
      'app_title': 'ТДТУ LMS',
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
    },
    'en': {
      'app_title': 'TDTU LMS',
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
