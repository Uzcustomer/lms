import 'dart:async';
import 'package:flutter/widgets.dart';
import 'attendance_service.dart';
import 'beacon_service.dart';

/// App-wide beacon scanning for a signed-in student, active whenever the
/// app is in the foreground — on any screen, not just "Davomat".
///
/// This is what makes the room model work: the teacher asks everyone to
/// open the app, the phones report which classroom beacon they hear, and
/// when the teacher starts the server prompts exactly those students. A
/// phone that picks up the beacon later (student came in late, or opened
/// the app late) reports it within seconds and is prompted then.
class PresenceScanner with WidgetsBindingObserver {
  PresenceScanner._();
  static final PresenceScanner instance = PresenceScanner._();

  /// Readings per beacon; the confirm screen and the site survey read it.
  final BeaconSignalTracker tracker = BeaconSignalTracker();

  /// Null until the first attempt; anything but `ready` means no scanning.
  final ValueNotifier<BeaconReadiness?> readiness = ValueNotifier<BeaconReadiness?>(null);

  /// Bumped on every scan cycle and prune so listeners can rebuild.
  final ValueNotifier<int> ticks = ValueNotifier<int>(0);

  static const _reportEvery = Duration(seconds: 15);
  // A few readings before the first report, so the median means something.
  static const _firstReportAfter = Duration(seconds: 3);

  final _beacons = BeaconService();
  final _service = AttendanceService();

  bool _active = false;
  bool _starting = false;
  bool _askedPermission = false;
  List<String>? _uuids;
  StreamSubscription<List<BeaconSighting>>? _ranging;
  Timer? _reportTimer;
  Timer? _pruneTimer;
  Timer? _arrivalTimer;
  bool _reporting = false;

  /// Student signed in.
  void start() {
    if (_active) return;
    _active = true;
    WidgetsBinding.instance.addObserver(this);
    _resume();
  }

  /// Signed out (or not a student).
  void stop() {
    if (!_active) return;
    _active = false;
    WidgetsBinding.instance.removeObserver(this);
    _pause();
    tracker.clear();
    readiness.value = null;
    _uuids = null;
    _askedPermission = false;
  }

  /// After the student fixed Bluetooth or permissions from a prompt.
  Future<void> retry() async {
    _pause();
    _askedPermission = false;
    await _resume();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _resume();
    } else if (state == AppLifecycleState.paused || state == AppLifecycleState.detached) {
      _pause();
    }
  }

  /// Sends what the phone hears now; the answer is the list of sessions this
  /// student may confirm, which drives the in-app banner.
  Future<void> report() async {
    final live = tracker.live;
    if (live.isEmpty || _reporting) return;
    _reporting = true;
    try {
      AttendanceWatcher.pending.value = await _service.reportPresence(
        live
            .map((s) => {
                  'uuid': s.uuid,
                  'major': s.major,
                  'minor': s.minor,
                  'rssi': s.median,
                  'seen_at': s.lastAt.toUtc().toIso8601String(),
                })
            .toList(),
      );
    } catch (_) {
      // offline — the next cycle tries again
    } finally {
      _reporting = false;
    }
  }

  Future<void> _resume() async {
    if (!_active || _starting || _ranging != null) return;
    _starting = true;
    try {
      final r = await _beacons.prepare(request: !_askedPermission);
      _askedPermission = true;
      if (!_active) return;
      readiness.value = r;
      if (r != BeaconReadiness.ready) return;

      try {
        _uuids ??= await _service.beaconUuids();
      } catch (_) {/* retried on the next resume */}
      final uuids = _uuids;
      // No beacons configured (or not reachable): nothing to listen for.
      if (!_active || uuids == null || uuids.isEmpty) return;

      _ranging = _beacons.range(uuids).listen(_onSightings, onError: (_) {});
      _reportTimer = Timer.periodic(_reportEvery, (_) => report());
      _pruneTimer = Timer.periodic(const Duration(seconds: 1), (_) {
        tracker.prune();
        ticks.value++;
      });
    } finally {
      _starting = false;
    }
  }

  void _pause() {
    _ranging?.cancel();
    _ranging = null;
    _reportTimer?.cancel();
    _reportTimer = null;
    _pruneTimer?.cancel();
    _pruneTimer = null;
    _arrivalTimer?.cancel();
    _arrivalTimer = null;
  }

  void _onSightings(List<BeaconSighting> sightings) {
    if (sightings.isEmpty) return;
    final arrived = sightings.any((s) => tracker.statsFor(s.key) == null);
    tracker.add(sightings);
    ticks.value++;
    // A beacon that was not heard a moment ago: report soon instead of
    // waiting for the next cycle, so a late arrival is prompted quickly.
    if (arrived && _arrivalTimer == null) {
      _arrivalTimer = Timer(_firstReportAfter, () {
        _arrivalTimer = null;
        report();
      });
    }
  }
}
