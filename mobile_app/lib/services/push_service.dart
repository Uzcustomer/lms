import 'dart:convert';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'attendance_service.dart';

/// A push the user tapped (or one that arrived while the app was open).
class PushEvent {
  final String type;
  final Map<String, String> data;
  const PushEvent({required this.type, required this.data});
}

/// Runs in a separate isolate when a push arrives and the app is in the
/// background or killed. The server sends "attendance_opened" without a
/// notification block (so only students seen in the room get a loud one);
/// here we surface it quietly so the student can still open the app and
/// confirm if they are in the room.
@pragma('vm:entry-point')
Future<void> pushBackgroundHandler(RemoteMessage message) async {
  if (message.notification != null) return; // FCM already showed it
  if (message.data['type'] != 'attendance_opened') return;
  try {
    await Firebase.initializeApp();
    await PushService._ensureLocalInit();
    await PushService._showLocal(
      id: message.messageId?.hashCode ?? DateTime.now().millisecondsSinceEpoch ~/ 1000,
      title: 'Davomat boshlandi',
      body: PushService._openedBody(message.data),
      payload: jsonEncode(message.data),
    );
  } catch (_) {}
}

class PushService {
  /// Set when the user taps a push (or a local notification). The session
  /// effects widget in main.dart routes it and resets it to null.
  static final ValueNotifier<PushEvent?> lastTap = ValueNotifier<PushEvent?>(null);

  static final FlutterLocalNotificationsPlugin _local = FlutterLocalNotificationsPlugin();
  static const AndroidNotificationChannel _channel = AndroidNotificationChannel(
    'attendance',
    'Davomat',
    description: 'Davomatni tasdiqlash xabarlari',
    importance: Importance.high,
  );

  static bool _firebaseReady = false;
  static bool _localReady = false;
  static bool _listening = false;
  static bool _isTeacher = false;

  /// Call once from main() before runApp. Safe to fail (app works without push).
  static Future<void> initFirebase() async {
    if (kIsWeb) return;
    try {
      await Firebase.initializeApp();
      FirebaseMessaging.onBackgroundMessage(pushBackgroundHandler);
      _firebaseReady = true;
    } catch (e) {
      debugPrint('Firebase init skipped: $e');
    }
  }

  /// Call after login: asks permission, registers the token with our API
  /// and starts listening. Idempotent.
  static Future<void> startSession({required bool isTeacher}) async {
    if (!_firebaseReady) return;
    _isTeacher = isTeacher;
    await _ensureLocalInit();

    final messaging = FirebaseMessaging.instance;
    try {
      await messaging.requestPermission(alert: true, badge: true, sound: true);
      await _local
          .resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>()
          ?.requestNotificationsPermission();
    } catch (_) {}

    try {
      await _register(await messaging.getToken());
    } catch (e) {
      debugPrint('FCM token unavailable: $e');
    }

    if (_listening) return;
    _listening = true;
    messaging.onTokenRefresh.listen(_register);
    FirebaseMessaging.onMessage.listen(_onForeground);
    FirebaseMessaging.onMessageOpenedApp.listen((m) => _emitTap(jsonEncode(m.data)));
    final initial = await messaging.getInitialMessage();
    if (initial != null) _emitTap(jsonEncode(initial.data));
  }

  static Future<void> _register(String? token) async {
    if (token == null || token.isEmpty) return;
    try {
      await AttendanceService().registerDevice(
        token,
        teacher: _isTeacher,
        platform: defaultTargetPlatform == TargetPlatform.iOS ? 'ios' : 'android',
      );
    } catch (e) {
      debugPrint('Device token registration failed: $e');
    }
  }

  static Future<void> _ensureLocalInit() async {
    if (_localReady) return;
    await _local.initialize(
      const InitializationSettings(
        android: AndroidInitializationSettings('@mipmap/ic_launcher'),
        iOS: DarwinInitializationSettings(),
      ),
      onDidReceiveNotificationResponse: (response) => _emitTap(response.payload),
    );
    await _local
        .resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(_channel);
    _localReady = true;
  }

  /// App is open: FCM does not show anything by itself, so mirror the push
  /// as a local notification and refresh the in-app attendance state.
  static Future<void> _onForeground(RemoteMessage message) async {
    final type = message.data['type']?.toString() ?? '';
    if (type.startsWith('attendance')) {
      AttendanceWatcher.refresh();
    }

    final n = message.notification;
    final title = n?.title ?? (type == 'attendance_opened' ? 'Davomat boshlandi' : null);
    if (title == null) return;
    await _showLocal(
      id: message.messageId?.hashCode ?? DateTime.now().millisecondsSinceEpoch ~/ 1000,
      title: title,
      body: n?.body ?? _openedBody(message.data),
      payload: jsonEncode(message.data),
    );
  }

  static String _openedBody(Map<String, dynamic> data) {
    final subject = data['subject_name']?.toString() ?? '';
    final room = data['auditorium_name']?.toString() ?? '';
    final where = [subject, room].where((s) => s.isNotEmpty).join(' — ');
    return '$where. Xonada bo\'lsangiz ilovani ochib tasdiqlang.';
  }

  static Future<void> _showLocal({
    required int id,
    required String title,
    required String body,
    String? payload,
  }) {
    return _local.show(
      id & 0x7fffffff,
      title,
      body,
      NotificationDetails(
        android: AndroidNotificationDetails(
          _channel.id,
          _channel.name,
          channelDescription: _channel.description,
          importance: Importance.high,
          priority: Priority.high,
        ),
        iOS: const DarwinNotificationDetails(),
      ),
      payload: payload,
    );
  }

  static void _emitTap(String? payload) {
    if (payload == null || payload.isEmpty) return;
    try {
      final raw = jsonDecode(payload);
      if (raw is! Map) return;
      final data = raw.map((k, v) => MapEntry(k.toString(), v?.toString() ?? ''));
      lastTap.value = PushEvent(type: data['type'] ?? '', data: data);
    } catch (_) {}
  }
}
