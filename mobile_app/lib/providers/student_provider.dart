import 'dart:typed_data';
import 'package:flutter/material.dart';
import '../services/student_service.dart';
import '../services/api_service.dart';

class StudentProvider extends ChangeNotifier {
  final StudentService _service;

  bool _isLoading = false;
  String? _error;

  Map<String, dynamic>? _dashboard;
  Map<String, dynamic>? _profile;
  Map<String, dynamic>? _schedule;
  List<dynamic>? _subjects;
  Map<String, dynamic>? _attendance;
  List<dynamic>? _pendingLessons;
  List<dynamic>? _excuseRequests;

  StudentProvider(this._service);

  bool get isLoading => _isLoading;
  String? get error => _error;
  Map<String, dynamic>? get dashboard => _dashboard;
  Map<String, dynamic>? get profile => _profile;
  Map<String, dynamic>? get schedule => _schedule;
  List<dynamic>? get subjects => _subjects;
  Map<String, dynamic>? get attendance => _attendance;
  List<dynamic>? get pendingLessons => _pendingLessons;
  List<dynamic>? get excuseRequests => _excuseRequests;

  Future<void> loadDashboard() async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _service.getDashboard();
      _dashboard = response['data'] as Map<String, dynamic>?;
    } on ApiException catch (e) {
      _error = e.message;
    }

    _isLoading = false;
    notifyListeners();
  }

  Future<void> loadProfile() async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _service.getProfile();
      _profile = response['data'] as Map<String, dynamic>?;
    } on ApiException catch (e) {
      _error = e.message;
    }

    _isLoading = false;
    notifyListeners();
  }

  Future<void> loadSchedule({String? semesterId, String? weekId}) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _service.getSchedule(
        semesterId: semesterId,
        weekId: weekId,
      );
      _schedule = response['data'] as Map<String, dynamic>?;
    } on ApiException catch (e) {
      _error = e.message;
    }

    _isLoading = false;
    notifyListeners();
  }

  Future<void> loadSubjects({String? semesterCode}) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _service.getSubjects(semesterCode: semesterCode);
      _subjects = response['data'] as List<dynamic>?;
    } on ApiException catch (e) {
      _error = e.message;
    }

    _isLoading = false;
    notifyListeners();
  }

  Future<void> loadAttendance({String? semesterCode}) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _service.getAttendance(semesterCode: semesterCode);
      _attendance = response['data'] as Map<String, dynamic>?;
    } on ApiException catch (e) {
      _error = e.message;
    }

    _isLoading = false;
    notifyListeners();
  }

  Future<void> loadPendingLessons() async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _service.getPendingLessons();
      _pendingLessons = response['data'] as List<dynamic>?;
    } on ApiException catch (e) {
      _error = e.message;
    }

    _isLoading = false;
    notifyListeners();
  }

  Future<void> loadExcuseRequests() async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _service.getExcuseRequests();
      _excuseRequests = response['data'] as List<dynamic>?;
    } on ApiException catch (e) {
      _error = e.message;
    }

    _isLoading = false;
    notifyListeners();
  }

  Future<Map<String, dynamic>> createExcuseRequest({
    required String type,
    required String subjectName,
    required String reason,
    required Uint8List fileBytes,
    required String fileName,
  }) async {
    final response = await _service.createExcuseRequest(
      type: type,
      subjectName: subjectName,
      reason: reason,
      fileBytes: fileBytes,
      fileName: fileName,
    );
    // Reload excuse requests after creating
    await loadExcuseRequests();
    return response;
  }

  Future<Map<String, dynamic>> saveTelegram(String telegramUsername) async {
    final response = await _service.saveTelegram(telegramUsername);
    return response;
  }

  Future<Map<String, dynamic>> checkTelegramVerification() async {
    final response = await _service.checkTelegramVerification();
    return response;
  }

  void clearData() {
    _dashboard = null;
    _profile = null;
    _schedule = null;
    _subjects = null;
    _attendance = null;
    _pendingLessons = null;
    _excuseRequests = null;
    notifyListeners();
  }
}
