/// Qayta o'qish ariza tizimi uchun model class'lar.
///
/// Backend (Laravel) JSON struktura'siga to'liq mos keladi.
/// API endpoint'lar: /api/v1/student/retake/*

/// Akademik qarzdor fan + joriy ariza holati.
class DebtSubject {
  final int subjectId;
  final String subjectName;
  final int semesterId;
  final String? semesterName;
  final String? educationYear;
  final double credit;
  final double? totalPoint;
  final String? grade;
  final String applicationStatus;
  final RetakeApplication? activeApplication;
  final bool isEligibleForNew;

  DebtSubject({
    required this.subjectId,
    required this.subjectName,
    required this.semesterId,
    required this.semesterName,
    required this.educationYear,
    required this.credit,
    required this.totalPoint,
    required this.grade,
    required this.applicationStatus,
    required this.activeApplication,
    required this.isEligibleForNew,
  });

  factory DebtSubject.fromJson(Map<String, dynamic> json) {
    return DebtSubject(
      subjectId: (json['subject_id'] as num).toInt(),
      subjectName: json['subject_name'] as String? ?? '',
      semesterId: (json['semester_id'] as num).toInt(),
      semesterName: json['semester_name'] as String?,
      educationYear: json['education_year'] as String?,
      credit: (json['credit'] as num?)?.toDouble() ?? 0.0,
      totalPoint: (json['total_point'] as num?)?.toDouble(),
      grade: json['grade']?.toString(),
      applicationStatus: json['application_status'] as String? ?? 'eligible',
      activeApplication: json['active_application'] != null
          ? RetakeApplication.fromJson(json['active_application'] as Map<String, dynamic>)
          : null,
      isEligibleForNew: json['is_eligible_for_new'] as bool? ?? true,
    );
  }

  /// Holat etiketi — talabaga ko'rsatish uchun.
  String get statusLabel {
    return switch (applicationStatus) {
      'eligible' => 'Tanlash mumkin',
      'pending_dean_registrar' => "Dekan va Registrator ofisi ko'rib chiqmoqda",
      'pending_registrar' => "Registrator ofisi ko'rib chiqmoqda (dekan tasdiqlagan)",
      'pending_dean' => "Dekan ko'rib chiqmoqda (registrator tasdiqlagan)",
      'pending_academic_dept' => "So'nggi bosqich — O'quv bo'limida kutishda",
      'approved' => 'Tasdiqlangan ✓',
      'rejected' => 'Rad etilgan',
      _ => applicationStatus,
    };
  }
}

/// Ariza qabul oynasi.
class RetakePeriod {
  final int id;
  final int specialtyId;
  final int course;
  final int semesterId;
  final DateTime startDate;
  final DateTime endDate;
  final bool isActive;
  final bool isUpcoming;
  final bool isClosed;
  final int daysLeft;

  RetakePeriod({
    required this.id,
    required this.specialtyId,
    required this.course,
    required this.semesterId,
    required this.startDate,
    required this.endDate,
    required this.isActive,
    required this.isUpcoming,
    required this.isClosed,
    required this.daysLeft,
  });

  factory RetakePeriod.fromJson(Map<String, dynamic> json) {
    return RetakePeriod(
      id: (json['id'] as num).toInt(),
      specialtyId: (json['specialty_id'] as num).toInt(),
      course: (json['course'] as num).toInt(),
      semesterId: (json['semester_id'] as num).toInt(),
      startDate: DateTime.parse(json['start_date'] as String),
      endDate: DateTime.parse(json['end_date'] as String),
      isActive: json['is_active'] as bool? ?? false,
      isUpcoming: json['is_upcoming'] as bool? ?? false,
      isClosed: json['is_closed'] as bool? ?? false,
      daysLeft: (json['days_left'] as num?)?.toInt() ?? 0,
    );
  }
}

/// Qayta o'qish guruhi (ariza tasdiqlangan bo'lsa).
class RetakeGroup {
  final int id;
  final String name;
  final DateTime startDate;
  final DateTime endDate;
  final String? teacherName;
  final String? status;

  RetakeGroup({
    required this.id,
    required this.name,
    required this.startDate,
    required this.endDate,
    required this.teacherName,
    required this.status,
  });

  factory RetakeGroup.fromJson(Map<String, dynamic> json) {
    return RetakeGroup(
      id: (json['id'] as num).toInt(),
      name: json['name'] as String? ?? '',
      startDate: DateTime.parse(json['start_date'] as String),
      endDate: DateTime.parse(json['end_date'] as String),
      teacherName: json['teacher_name'] as String?,
      status: json['status'] as String?,
    );
  }
}

/// Yakka ariza.
class RetakeApplication {
  final int id;
  final String applicationGroupId;
  final int subjectId;
  final String subjectName;
  final int semesterId;
  final String? semesterName;
  final double credit;
  final String? studentNote;
  final DateTime? submittedAt;
  final String finalStatus;
  final String stageDescription;
  final String? deanStatus;
  final String? registrarStatus;
  final String? academicDeptStatus;
  final String? rejectionReason;
  final bool hasVerificationCode;
  final bool hasTasdiqnoma;
  final RetakeGroup? retakeGroup;
  final List<RetakeApplicationLog> logs;

  RetakeApplication({
    required this.id,
    required this.applicationGroupId,
    required this.subjectId,
    required this.subjectName,
    required this.semesterId,
    required this.semesterName,
    required this.credit,
    required this.studentNote,
    required this.submittedAt,
    required this.finalStatus,
    required this.stageDescription,
    required this.deanStatus,
    required this.registrarStatus,
    required this.academicDeptStatus,
    required this.rejectionReason,
    required this.hasVerificationCode,
    required this.hasTasdiqnoma,
    required this.retakeGroup,
    required this.logs,
  });

  factory RetakeApplication.fromJson(Map<String, dynamic> json) {
    return RetakeApplication(
      id: (json['id'] as num).toInt(),
      applicationGroupId: json['application_group_id'] as String? ?? '',
      subjectId: (json['subject_id'] as num).toInt(),
      subjectName: json['subject_name'] as String? ?? '',
      semesterId: (json['semester_id'] as num).toInt(),
      semesterName: json['semester_name'] as String?,
      credit: (json['credit'] as num?)?.toDouble() ?? 0.0,
      studentNote: json['student_note'] as String?,
      submittedAt: json['submitted_at'] != null ? DateTime.tryParse(json['submitted_at'] as String) : null,
      finalStatus: json['final_status'] as String? ?? 'pending',
      stageDescription: json['stage_description'] as String? ?? '',
      deanStatus: json['dean_status'] as String?,
      registrarStatus: json['registrar_status'] as String?,
      academicDeptStatus: json['academic_dept_status'] as String?,
      rejectionReason: json['rejection_reason'] as String?,
      hasVerificationCode: json['has_verification_code'] as bool? ?? false,
      hasTasdiqnoma: json['has_tasdiqnoma'] as bool? ?? false,
      retakeGroup: json['retake_group'] != null
          ? RetakeGroup.fromJson(json['retake_group'] as Map<String, dynamic>)
          : null,
      logs: (json['logs'] as List<dynamic>?)
              ?.map((e) => RetakeApplicationLog.fromJson(e as Map<String, dynamic>))
              .toList() ??
          const [],
    );
  }
}

/// Audit log yozuvi.
class RetakeApplicationLog {
  final int? id;
  final String? action;
  final String? note;
  final DateTime? createdAt;

  RetakeApplicationLog({
    required this.id,
    required this.action,
    required this.note,
    required this.createdAt,
  });

  factory RetakeApplicationLog.fromJson(Map<String, dynamic> json) {
    return RetakeApplicationLog(
      id: (json['id'] as num?)?.toInt(),
      action: json['action'] as String?,
      note: json['note'] as String?,
      createdAt: json['created_at'] != null ? DateTime.tryParse(json['created_at'] as String) : null,
    );
  }
}
