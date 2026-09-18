import 'dart:async';
import 'dart:io';
import 'package:flutter/foundation.dart';
import '../config/api_config.dart';
import 'api_service.dart';

/// One classroom beacon as the server knows it.
class BeaconInfo {
  final String uuid;
  final int major;
  final int minor;
  final String? auditoriumCode;
  final String? auditoriumName;

  const BeaconInfo({
    required this.uuid,
    required this.major,
    required this.minor,
    this.auditoriumCode,
    this.auditoriumName,
  });

  static BeaconInfo? fromJson(dynamic json) {
    if (json is! Map) return null;
    final uuid = json['uuid']?.toString();
    if (uuid == null || uuid.isEmpty) return null;
    return BeaconInfo(
      uuid: uuid.toLowerCase(),
      major: int.tryParse(json['major'].toString()) ?? 0,
      minor: int.tryParse(json['minor'].toString()) ?? 0,
      auditoriumCode: json['auditorium_code']?.toString(),
      auditoriumName: json['auditorium_name']?.toString(),
    );
  }

  String get key => '$uuid/$major/$minor';
}

/// An open attendance window the student may confirm.
class PendingAttendance {
  final int sessionId;
  final String subjectName;
  final String? lessonPairName;
  final String? auditoriumName;
  final DateTime? closesAt;
  final BeaconInfo? beacon;
  final bool requireFace; // confirm needs a selfie matched to the approved photo
  final String myStatus; // pending | present | absent

  const PendingAttendance({
    required this.sessionId,
    required this.subjectName,
    this.lessonPairName,
    this.auditoriumName,
    this.closesAt,
    this.beacon,
    this.requireFace = false,
    required this.myStatus,
  });

  static PendingAttendance? fromJson(dynamic json) {
    if (json is! Map) return null;
    final id = int.tryParse(json['session_id'].toString());
    if (id == null) return null;
    return PendingAttendance(
      sessionId: id,
      subjectName: json['subject_name']?.toString() ?? '',
      lessonPairName: json['lesson_pair_name']?.toString(),
      auditoriumName: json['auditorium_name']?.toString(),
      closesAt: DateTime.tryParse(json['closes_at']?.toString() ?? '')?.toLocal(),
      beacon: BeaconInfo.fromJson(json['beacon']),
      requireFace: json['require_face'] == true,
      myStatus: json['my_status']?.toString() ?? 'pending',
    );
  }

  bool get isPresent => myStatus == 'present';

  Duration get timeLeft {
    final c = closesAt;
    if (c == null) return Duration.zero;
    final d = c.difference(DateTime.now());
    return d.isNegative ? Duration.zero : d;
  }
}

class AttendanceService {
  final ApiService _api;

  AttendanceService([ApiService? api]) : _api = api ?? ApiService();

  // ── shared ──────────────────────────────────────────

  Future<void> registerDevice(
    String token, {
    required bool teacher,
    required String platform,
  }) {
    return _api.post(
      teacher ? ApiConfig.teacherDeviceToken : ApiConfig.studentDeviceToken,
      {'token': token, 'platform': platform},
      auth: true,
    );
  }

  // ── student ─────────────────────────────────────────

  /// Proximity UUIDs the phone should range for.
  Future<List<String>> beaconUuids() async {
    final res = await _api.get(ApiConfig.studentBeacons);
    final data = res['data'];
    final list = data is Map ? data['uuids'] : null;
    if (list is! List) return const [];
    return list.map((e) => e.toString().toLowerCase()).toSet().toList();
  }

  /// Reports sightings; returns the sessions the student can confirm now.
  Future<List<PendingAttendance>> reportPresence(
    List<Map<String, dynamic>> sightings, {
    String source = 'foreground',
  }) async {
    final res = await _api.post(
      ApiConfig.studentPresence,
      {'sightings': sightings, 'source': source},
      auth: true,
    );
    return _pendingList(res['pending']);
  }

  Future<List<PendingAttendance>> pending() async {
    final res = await _api.get(ApiConfig.studentAttendancePending);
    return _pendingList(res['data']);
  }

  Future<Map<String, dynamic>> confirm(
    int sessionId, {
    required String uuid,
    required int major,
    required int minor,
    int? rssi,
    File? photo,
  }) async {
    if (photo == null) {
      return _api.post(
        ApiConfig.studentAttendanceConfirm(sessionId),
        {'uuid': uuid, 'major': major, 'minor': minor, if (rssi != null) 'rssi': rssi},
        auth: true,
      );
    }
    return _api.multipartPost(
      ApiConfig.studentAttendanceConfirm(sessionId),
      {
        'uuid': uuid,
        'major': '$major',
        'minor': '$minor',
        if (rssi != null) 'rssi': '$rssi',
      },
      fileBytes: await photo.readAsBytes(),
      fileName: 'selfie.jpg',
      fileField: 'photo',
    );
  }

  Future<List<dynamic>> history() async {
    final res = await _api.get(ApiConfig.studentAttendanceHistory);
    return res['data'] as List<dynamic>? ?? const [];
  }

  List<PendingAttendance> _pendingList(dynamic raw) {
    if (raw is! List) return const [];
    return raw.map(PendingAttendance.fromJson).whereType<PendingAttendance>().toList();
  }

  // ── teacher ─────────────────────────────────────────

  Future<Map<String, dynamic>> teacherLessons({DateTime? date}) async {
    final res = await _api.get(
      ApiConfig.teacherAttendanceLessons,
      queryParams: date == null ? null : {'date': _ymd(date)},
    );
    return res['data'] as Map<String, dynamic>? ?? {};
  }

  Future<Map<String, dynamic>> startSession({
    required int subjectId,
    required String lessonPairCode,
    DateTime? date,
    int? windowMinutes,
    bool? requireFace,
  }) async {
    final res = await _api.post(
      ApiConfig.teacherAttendanceSessions,
      {
        'subject_id': subjectId,
        'lesson_pair_code': lessonPairCode,
        if (date != null) 'date': _ymd(date),
        if (windowMinutes != null) 'window_minutes': windowMinutes,
        if (requireFace != null) 'require_face': requireFace,
      },
      auth: true,
    );
    return res['data'] as Map<String, dynamic>? ?? {};
  }

  Future<Map<String, dynamic>> session(int id) async {
    final res = await _api.get(ApiConfig.teacherAttendanceSession(id));
    return res['data'] as Map<String, dynamic>? ?? {};
  }

  Future<Map<String, dynamic>> mark(int id, int studentId, String status) async {
    final res = await _api.post(
      '${ApiConfig.teacherAttendanceSession(id)}/mark',
      {'student_id': studentId, 'status': status},
      auth: true,
    );
    return res['data'] as Map<String, dynamic>? ?? {};
  }

  Future<Map<String, dynamic>> remind(int id) async {
    final res = await _api.post('${ApiConfig.teacherAttendanceSession(id)}/remind', {}, auth: true);
    return res['data'] as Map<String, dynamic>? ?? {};
  }

  Future<Map<String, dynamic>> close(int id) async {
    final res = await _api.post('${ApiConfig.teacherAttendanceSession(id)}/close', {}, auth: true);
    return res['data'] as Map<String, dynamic>? ?? {};
  }

  /// Deletes the session outright (confirmations included) so the lesson
  /// can be started again.
  Future<void> cancelSession(int id) async {
    await _api.delete(ApiConfig.teacherAttendanceSession(id));
  }

  static String _ymd(DateTime d) =>
      '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';
}

/// App-wide "is there an attendance window open for me?" state. Polled
/// once a minute while a student session is active and refreshed on push.
class AttendanceWatcher {
  static final ValueNotifier<List<PendingAttendance>> pending =
      ValueNotifier<List<PendingAttendance>>(const []);
  static Timer? _poll;

  static Future<void> refresh() async {
    try {
      pending.value = await AttendanceService().pending();
    } catch (_) {/* keep the last known state */}
  }

  static void start({Duration period = const Duration(minutes: 1)}) {
    _poll?.cancel();
    refresh();
    _poll = Timer.periodic(period, (_) => refresh());
  }

  static void stop() {
    _poll?.cancel();
    _poll = null;
    pending.value = const [];
  }
}
