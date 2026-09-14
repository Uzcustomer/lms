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
