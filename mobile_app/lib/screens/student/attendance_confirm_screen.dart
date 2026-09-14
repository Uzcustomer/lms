import 'dart:async';
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../services/attendance_service.dart';
import '../../services/beacon_service.dart';
import '../../widgets/clinic_header.dart';

/// "Davomat": lists the open attendance windows for the student's group,
/// ranges the classroom beacons, and lets the student confirm once the
/// session's beacon is actually heard by this phone.
class AttendanceConfirmScreen extends StatefulWidget {
  const AttendanceConfirmScreen({super.key});

  @override
  State<AttendanceConfirmScreen> createState() => _AttendanceConfirmScreenState();
}

class _AttendanceConfirmScreenState extends State<AttendanceConfirmScreen>
    with WidgetsBindingObserver {
  final _service = AttendanceService();
  final _beacons = BeaconService();

  List<PendingAttendance> _pending = const [];
  bool _loading = true;
  String? _error;

  BeaconReadiness? _readiness;
  StreamSubscription<List<BeaconSighting>>? _ranging;
  final Map<String, BeaconSighting> _seen = {};
  Timer? _tick;
  Timer? _report;
  int? _confirming;

  static const _sightingTtl = Duration(seconds: 12);

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _tick = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted) setState(_expireSightings);
    });
    _report = Timer.periodic(const Duration(seconds: 20), (_) => _reportPresence());
    _load();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _tick?.cancel();
    _report?.cancel();
    _ranging?.cancel();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && _readiness != BeaconReadiness.ready) {
      _startScanning();
    }
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      _pending = await _service.pending();
      AttendanceWatcher.pending.value = _pending;
    } on ApiException catch (e) {
      _error = e.message;
    } catch (_) {
      _error = 'Tarmoq xatoligi. Internet aloqasini tekshiring.';
    }
    if (!mounted) return;
    setState(() => _loading = false);
    await _startScanning();
  }

  Future<void> _startScanning() async {
    final readiness = await _beacons.prepare();
    if (!mounted) return;
    setState(() => _readiness = readiness);
    if (readiness != BeaconReadiness.ready) return;

    List<String> uuids;
    try {
      uuids = await _service.beaconUuids();
    } catch (_) {
      uuids = const [];
    }
    await _ranging?.cancel();
    _ranging = _beacons.range(uuids).listen((sightings) {
      if (!mounted) return;
      setState(() {
        for (final s in sightings) {
          final prev = _seen[s.key];
          // Keep the strongest reading of the last cycle for display.
          if (prev == null || s.rssi >= prev.rssi || now().difference(prev.seenAt) > const Duration(seconds: 3)) {
            _seen[s.key] = s;
          }
        }
        _expireSightings();
      });
    }, onError: (_) {});
  }

  DateTime now() => DateTime.now();

  void _expireSightings() {
    final cutoff = DateTime.now().subtract(_sightingTtl);
    _seen.removeWhere((_, s) => s.seenAt.isBefore(cutoff));
  }

  Future<void> _reportPresence() async {
    if (_seen.isEmpty) return;
    try {
      final pending = await _service.reportPresence(
        _seen.values.map((s) => s.toJson()).toList(),
      );
      if (!mounted) return;
      setState(() => _pending = pending);
      AttendanceWatcher.pending.value = pending;
    } catch (_) {}
  }

  BeaconSighting? _sightingFor(PendingAttendance p) {
    final b = p.beacon;
    if (b == null) return null;
    return _seen[b.key];
  }

  Future<void> _confirm(PendingAttendance p) async {
    final sighting = _sightingFor(p);
    final beacon = p.beacon;
    if (sighting == null || beacon == null) return;

    setState(() => _confirming = p.sessionId);
    try {
      final res = await _service.confirm(
        p.sessionId,
        uuid: beacon.uuid,
        major: beacon.major,
        minor: beacon.minor,
        rssi: sighting.rssi,
      );
      if (!mounted) return;
      _snack(res['message']?.toString() ?? 'Davomat tasdiqlandi.');
      await _load();
    } on ApiException catch (e) {
      if (mounted) _snack(e.message, error: true);
    } catch (_) {
      if (mounted) _snack('Tarmoq xatoligi. Qayta urinib ko\'ring.', error: true);
    } finally {
      if (mounted) setState(() => _confirming = null);
    }
  }

  void _snack(String msg, {bool error = false}) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
      content: Text(msg),
      backgroundColor: error ? const Color(0xFFBE123C) : const Color(0xFF047857),
    ));
  }

  // ── UI ──────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: ClinicTheme.bgOf(context),
      body: Column(
        children: [
          ClinicHeader(title: 'Davomat', onBack: () => Navigator.of(context).pop()),
          Expanded(
            child: RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(14, 14, 14, 30),
                children: [
                  if (_readiness != null && _readiness != BeaconReadiness.ready) _readinessCard(),
                  if (_loading)
                    const Padding(
                      padding: EdgeInsets.only(top: 60),
                      child: Center(child: CircularProgressIndicator()),
                    )
                  else if (_error != null)
                    _message(Icons.wifi_off_rounded, _error!)
                  else if (_pending.isEmpty)
                    _message(Icons.event_available_outlined,
                        'Hozir ochiq davomat yo\'q.\nO\'qituvchi davomatni boshlaganda bu yerda ko\'rinadi.')
                  else
                    ..._pending.map(_sessionCard),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _message(IconData icon, String text) {
    return Padding(
      padding: const EdgeInsets.only(top: 60),
      child: Column(
        children: [
          Icon(icon, size: 52, color: ClinicTheme.faint),
          const SizedBox(height: 14),
          Text(text,
              textAlign: TextAlign.center,
              style: TextStyle(color: ClinicTheme.mutedOf(context), fontSize: 13.5, height: 1.4)),
        ],
      ),
    );
  }

  Widget _readinessCard() {
    final (text, action, onTap) = switch (_readiness!) {
      BeaconReadiness.bluetoothOff => (
          'Bluetooth o\'chiq. Xona signalini eshitish uchun uni yoqing.',
          'Bluetooth sozlamalari',
          _beacons.openBluetoothSettings,
        ),
      BeaconReadiness.permissionDenied => (
          'Bluetooth va joylashuv ruxsati kerak — busiz xona beacon\'i aniqlanmaydi.',
          'Ruxsat berish',
          _beacons.openAppPermissionSettings,
        ),
      _ => ('Bu qurilma BLE skanerlashni qo\'llab-quvvatlamaydi.', null, null),
    };

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFFFEF3C7),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFF59E0B).withOpacity(0.5)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(children: [
            const Icon(Icons.bluetooth_disabled, color: Color(0xFFB45309)),
            const SizedBox(width: 8),
            Expanded(
              child: Text(text,
                  style: const TextStyle(
                      color: Color(0xFF78350F), fontSize: 13, fontWeight: FontWeight.w600)),
            ),
          ]),
          if (action != null) ...[
            const SizedBox(height: 8),
            Align(
              alignment: Alignment.centerRight,
              child: TextButton(
                onPressed: () async {
                  await onTap?.call();
                  _startScanning();
                },
                child: Text(action),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _sessionCard(PendingAttendance p) {
    final sighting = _sightingFor(p);
    final inRoom = sighting != null;
    final left = p.timeLeft;
    final busy = _confirming == p.sessionId;
    final scanning = _readiness == BeaconReadiness.ready;

    final Color accent = p.isPresent
        ? const Color(0xFF047857)
        : inRoom
            ? ClinicTheme.teal
            : const Color(0xFFB45309);

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: ClinicTheme.surfaceOf(context),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: ClinicTheme.dividerOf(context)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(p.subjectName,
              style: TextStyle(
                  fontSize: 15.5, fontWeight: FontWeight.w800, color: ClinicTheme.inkOf(context))),
          const SizedBox(height: 4),
          Text(
            [p.lessonPairName, p.auditoriumName]
                .where((s) => s != null && s.isNotEmpty)
                .join(' · '),
            style: TextStyle(fontSize: 12.5, color: ClinicTheme.mutedOf(context)),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Icon(
                p.isPresent
                    ? Icons.check_circle
                    : inRoom
                        ? Icons.bluetooth_connected
                        : scanning
                            ? Icons.bluetooth_searching
                            : Icons.bluetooth_disabled,
                color: accent,
                size: 20,
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  p.isPresent
                      ? 'Tasdiqlangan'
                      : inRoom
                          ? 'Xona topildi (signal ${sighting.rssi} dBm)'
                          : scanning
                              ? 'Xona signali qidirilmoqda…'
                              : 'Skanerlash yoqilmagan',
                  style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: accent),
                ),
              ),
              if (!p.isPresent)
                Text(
                  '${left.inMinutes.toString().padLeft(2, '0')}:${(left.inSeconds % 60).toString().padLeft(2, '0')}',
                  style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w800,
                      fontFeatures: const [FontFeature.tabularFigures()],
                      color: left.inMinutes < 2 ? const Color(0xFFBE123C) : ClinicTheme.mutedOf(context)),
                ),
            ],
          ),
          if (!p.isPresent) ...[
            const SizedBox(height: 14),
            SizedBox(
              width: double.infinity,
              height: 46,
              child: ElevatedButton.icon(
                onPressed: inRoom && !busy && left > Duration.zero ? () => _confirm(p) : null,
                style: ElevatedButton.styleFrom(
                  backgroundColor: ClinicTheme.teal,
                  foregroundColor: Colors.white,
                  disabledBackgroundColor: ClinicTheme.dividerOf(context),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                icon: busy
                    ? const SizedBox(
                        width: 18, height: 18,
                        child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                    : const Icon(Icons.how_to_reg),
                label: Text(inRoom ? 'Davomatni tasdiqlash' : 'Xonaga kiring',
                    style: const TextStyle(fontWeight: FontWeight.w800)),
              ),
            ),
          ],
        ],
      ),
    );
  }
}
