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
  Map<String, dynamic>? _contract;
  List<dynamic>? _contractList;

  StudentProvider(this._service);

  bool get isLoading => _isLoading;
  String? get error => _error;
  Map<String, dynamic>? get dashboard => _dashboard;
  Map<String, dynamic>? get profile => _profile;
  Map<String, dynamic>? get schedule => _schedule;
  List<dynamic>? get subjects => _subjects;
  Map<String, dynamic>? get attendance => _attendance;
  List<dynamic>? get pendingLessons => _pendingLessons;
  Map<String, dynamic>? get contract => _contract;
  List<dynamic>? get contractList => _contractList;

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

  Future<void> loadContract() async {
    try {
      final response = await _service.getContract();
      _contract = response['data'] as Map<String, dynamic>?;
      _contractList = _contract?['contracts'] as List<dynamic>?;
    } on ApiException catch (e) {
      // Contract data is optional, don't set error
      _contract = null;
      _contractList = null;
    }
    notifyListeners();
  }

  Future<Map<String, dynamic>> saveTelegram(String telegramUsername) async {
    final response = await _service.saveTelegram(telegramUsername);
    return response;
  }

  Future<Map<String, dynamic>> checkTelegramVerification() async {
    final response = await _service.checkTelegramVerification();
    return response;
  }

  // Absence excuse methods
  List<dynamic>? _excuses;
  List<dynamic>? _excuseReasons;

  List<dynamic>? get excuses => _excuses;
  List<dynamic>? get excuseReasons => _excuseReasons;

  Future<void> loadExcuseReasons() async {
    try {
      final response = await _service.getExcuseReasons();
      _excuseReasons = response['data'] as List<dynamic>?;
    } on ApiException catch (e) {
      _error = e.message;
    }
    notifyListeners();
  }

  Future<void> loadExcuses() async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _service.getExcuses();
      _excuses = response['data'] as List<dynamic>?;
    } on ApiException catch (e) {
      _error = e.message;
    }

    _isLoading = false;
    notifyListeners();
  }

  Future<Map<String, dynamic>> getExcuseDetail(int id) async {
    return await _service.getExcuseDetail(id);
  }

  Future<List<dynamic>> getMissedAssessments(String startDate, String endDate) async {
    final response = await _service.getMissedAssessments(startDate, endDate);
    return response['data'] as List<dynamic>? ?? [];
  }

  Future<Map<String, dynamic>> submitExcuse({
    required String reason,
    required String docNumber,
    required String startDate,
    required String endDate,
    String? description,
    required Uint8List fileBytes,
    required String fileName,
  }) async {
    return await _service.submitExcuse(
      reason: reason,
      docNumber: docNumber,
      startDate: startDate,
      endDate: endDate,
      description: description,
      fileBytes: fileBytes,
      fileName: fileName,
    );
  }

  void clearData() {
    _dashboard = null;
    _profile = null;
    _schedule = null;
    _subjects = null;
    _attendance = null;
    _pendingLessons = null;
    _contract = null;
    _contractList = null;
    _excuses = null;
    _excuseReasons = null;
    notifyListeners();
  }
}
