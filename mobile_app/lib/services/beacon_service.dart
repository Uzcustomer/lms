import 'dart:async';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:dchs_flutter_beacon/dchs_flutter_beacon.dart';
import 'package:permission_handler/permission_handler.dart';

/// One beacon heard by the phone.
class BeaconSighting {
  final String uuid;
  final int major;
  final int minor;
  final int rssi;
  final DateTime seenAt;

  const BeaconSighting({
    required this.uuid,
    required this.major,
    required this.minor,
    required this.rssi,
    required this.seenAt,
  });

  String get key => '$uuid/$major/$minor';

  Map<String, dynamic> toJson() => {
        'uuid': uuid,
        'major': major,
        'minor': minor,
        'rssi': rssi,
        'seen_at': seenAt.toUtc().toIso8601String(),
      };
}

/// Summary of the readings collected for one beacon over some window.
///
/// RSSI swings by 10–20 dB from packet to packet (multipath, the three BLE
/// advertising channels, bodies in the way), so a single reading says very
/// little. Everything that decides "is this phone in the room" — the live
/// display, the value sent on confirm, and the site survey — goes through
/// the median instead.
class BeaconSignalStats {
  final String uuid;
  final int major;
  final int minor;
  final List<int> _rssi;
  final DateTime firstAt;
  final DateTime lastAt;

  const BeaconSignalStats._({
    required this.uuid,
    required this.major,
    required this.minor,
    required List<int> rssi,
    required this.firstAt,
    required this.lastAt,
  }) : _rssi = rssi;

  /// [readings] must be sorted ascending by RSSI.
  factory BeaconSignalStats.from(String key, List<BeaconSighting> readings) {
    final sorted = readings.map((r) => r.rssi).toList()..sort();
    final first = readings.first;
    return BeaconSignalStats._(
      uuid: first.uuid,
      major: first.major,
      minor: first.minor,
      rssi: sorted,
      firstAt: readings.map((r) => r.seenAt).reduce((a, b) => a.isBefore(b) ? a : b),
      lastAt: readings.map((r) => r.seenAt).reduce((a, b) => a.isAfter(b) ? a : b),
    );
  }

  String get key => '$uuid/$major/$minor';
  int get count => _rssi.length;
  int get min => _rssi.first;
  int get max => _rssi.last;
  int get median => percentile(50);
  Duration get span => lastAt.difference(firstAt);

  /// Nearest-rank percentile, e.g. `percentile(10)` is the value 90 % of
  /// readings are stronger than — the one to compare against a threshold
  /// if you want nine in ten confirmations inside the room to pass.
  int percentile(int p) {
    if (_rssi.isEmpty) return 0;
    final rank = ((p / 100) * (_rssi.length - 1)).round().clamp(0, _rssi.length - 1);
    return _rssi[rank];
  }
}

/// Collects sightings per beacon: a rolling window for the live view and,
/// on demand, a fixed-length recording for a site survey.
class BeaconSignalTracker {
  final Duration window;
  final Map<String, List<BeaconSighting>> _live = {};
  final Map<String, List<BeaconSighting>> _recording = {};
  bool _isRecording = false;

  BeaconSignalTracker({this.window = const Duration(seconds: 15)});

  bool get isRecording => _isRecording;

  void add(Iterable<BeaconSighting> sightings) {
    for (final s in sightings) {
      _live.putIfAbsent(s.key, () => []).add(s);
      if (_isRecording) _recording.putIfAbsent(s.key, () => []).add(s);
    }
    prune();
  }

  /// Drops readings that fell out of the rolling window.
  void prune() {
    final cutoff = DateTime.now().subtract(window);
    _live.removeWhere((_, list) {
      list.removeWhere((s) => s.seenAt.isBefore(cutoff));
      return list.isEmpty;
    });
  }

  /// Live stats, strongest first.
  List<BeaconSignalStats> get live {
    final stats = _live.entries.map((e) => BeaconSignalStats.from(e.key, e.value)).toList();
    stats.sort((a, b) => b.median.compareTo(a.median));
    return stats;
  }

  BeaconSignalStats? statsFor(String key) {
    final readings = _live[key];
    return readings == null || readings.isEmpty ? null : BeaconSignalStats.from(key, readings);
  }

  void startRecording() {
    _recording.clear();
    _isRecording = true;
  }

  /// Ends the recording and returns what was captured, strongest first.
  List<BeaconSignalStats> stopRecording() {
    _isRecording = false;
    final stats = _recording.entries
        .where((e) => e.value.isNotEmpty)
        .map((e) => BeaconSignalStats.from(e.key, e.value))
        .toList();
    stats.sort((a, b) => b.median.compareTo(a.median));
    _recording.clear();
    return stats;
  }

  void clear() {
    _live.clear();
    _recording.clear();
    _isRecording = false;
  }
}

/// Why scanning cannot start — shown to the student with a fix action.
enum BeaconReadiness { ready, unsupported, permissionDenied, bluetoothOff }

/// Thin wrapper over flutter_beacon: permissions, Bluetooth state, ranging.
class BeaconService {
  static final BeaconService _instance = BeaconService._();
  factory BeaconService() => _instance;
  BeaconService._();

  bool _initialized = false;

  Future<BeaconReadiness> prepare() async {
    if (kIsWeb) return BeaconReadiness.unsupported;

    // BLE scanning needs location on every Android version and the two
    // Bluetooth runtime permissions on Android 12+ (no-ops elsewhere).
    final statuses = await [
      Permission.locationWhenInUse,
      Permission.bluetoothScan,
      Permission.bluetoothConnect,
    ].request();
    final location = statuses[Permission.locationWhenInUse];
    final scan = statuses[Permission.bluetoothScan];
    if (location?.isGranted != true && location?.isLimited != true) {
      return BeaconReadiness.permissionDenied;
    }
    if (scan?.isPermanentlyDenied == true || scan?.isDenied == true) {
      return BeaconReadiness.permissionDenied;
    }

    if (!_initialized) {
      try {
        _initialized = await flutterBeacon.initializeScanning;
      } catch (_) {
        return BeaconReadiness.unsupported;
      }
      if (!_initialized) return BeaconReadiness.unsupported;
    }

    try {
      final state = await flutterBeacon.bluetoothState;
      if (state == BluetoothState.stateOff) return BeaconReadiness.bluetoothOff;
    } catch (_) {/* some devices don't report; try ranging anyway */}

    return BeaconReadiness.ready;
  }

  Future<void> openBluetoothSettings() async {
    try {
      await flutterBeacon.openBluetoothSettings;
    } catch (_) {}
  }

  Future<void> openAppPermissionSettings() => openAppSettings();

  /// Continuous ranging for the given proximity UUIDs (all beacons when empty,
  /// Android only). Emits every scan cycle (~1 s).
  Stream<List<BeaconSighting>> range(List<String> uuids) {
    final regions = uuids.isEmpty
        ? [Region(identifier: 'tdtu-all')]
        : uuids
            .map((u) => Region(identifier: 'tdtu-$u', proximityUUID: u.toUpperCase()))
            .toList();

    return flutterBeacon.ranging(regions).map((result) {
      final now = DateTime.now();
      return result.beacons
          .map((b) => BeaconSighting(
                uuid: b.proximityUUID.toLowerCase(),
                major: b.major,
                minor: b.minor,
                rssi: b.rssi,
                seenAt: now,
              ))
          .toList();
    });
  }
}
