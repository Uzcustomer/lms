import 'dart:async';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';
import '../../services/api_service.dart';
import '../../services/attendance_service.dart';
import '../../services/beacon_service.dart';
import '../../widgets/clinic_header.dart';

/// "Davomat": lists the open attendance windows for the student's group,
/// ranges the classroom beacons, and lets the student confirm once the
/// session's beacon is actually heard by this phone.
///
/// Doubles as the site-survey tool: the signal card shows the median (not
/// the peak) of a rolling window per beacon and can record a fixed-length
/// sample at a labelled spot, which is how the RSSI threshold for a room
/// gets chosen.
class AttendanceConfirmScreen extends StatefulWidget {
  const AttendanceConfirmScreen({super.key});

  @override
  State<AttendanceConfirmScreen> createState() => _AttendanceConfirmScreenState();
}

class _AttendanceConfirmScreenState extends State<AttendanceConfirmScreen>
    with WidgetsBindingObserver {
  final _service = AttendanceService();
  final _beacons = BeaconService();
  final _tracker = BeaconSignalTracker();

  List<PendingAttendance> _pending = const [];
  bool _loading = true;
  String? _error;

  BeaconReadiness? _readiness;
  StreamSubscription<List<BeaconSighting>>? _ranging;
  Timer? _tick;
  Timer? _report;
  int? _confirming;

  // Site survey
  static const _recordFor = Duration(seconds: 30);
  final _spotController = TextEditingController();
  DateTime? _recordingStartedAt;
  List<BeaconSignalStats> _survey = const [];
  String _surveyLabel = '';

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _tick = Timer.periodic(const Duration(seconds: 1), (_) {
      if (!mounted) return;
      setState(_tracker.prune);
      _finishRecordingIfDue();
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
    _spotController.dispose();
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
      setState(() => _tracker.add(sightings));
    }, onError: (_) {});
  }

  Future<void> _reportPresence() async {
    final live = _tracker.live;
    if (live.isEmpty) return;
    try {
      final pending = await _service.reportPresence(
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
      if (!mounted) return;
      setState(() => _pending = pending);
      AttendanceWatcher.pending.value = pending;
    } catch (_) {}
  }

  BeaconSignalStats? _statsFor(PendingAttendance p) {
    final b = p.beacon;
    return b == null ? null : _tracker.statsFor(b.key);
  }

  Future<void> _confirm(PendingAttendance p) async {
    final stats = _statsFor(p);
    final beacon = p.beacon;
    if (stats == null || beacon == null) return;

    setState(() => _confirming = p.sessionId);
    try {
      // Second layer: a selfie, matched on the server against the student's
      // approved LMS photo — the same one the Face ID login uses.
      File? selfie;
      if (p.requireFace) {
        selfie = await _takeSelfie();
        if (selfie == null) {
          if (mounted) _snack("Davomat uchun yuzingizni suratga olish kerak.", error: true);
          return;
        }
      }

      final res = await _service.confirm(
        p.sessionId,
        uuid: beacon.uuid,
        major: beacon.major,
        minor: beacon.minor,
        rssi: stats.median,
        photo: selfie,
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

  /// Front camera only — no gallery, so an old photo cannot be picked.
  Future<File?> _takeSelfie() async {
    try {
      final picked = await ImagePicker().pickImage(
        source: ImageSource.camera,
        preferredCameraDevice: CameraDevice.front,
        imageQuality: 85,
        maxWidth: 1280,
        maxHeight: 1280,
      );
      return picked == null ? null : File(picked.path);
    } catch (_) {
      if (mounted) _snack("Kamerani ochib bo'lmadi. Ruxsatlarni tekshiring.", error: true);
      return null;
    }
  }

  void _snack(String msg, {bool error = false}) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
      content: Text(msg),
      backgroundColor: error ? const Color(0xFFBE123C) : const Color(0xFF047857),
    ));
  }

  // ── Site survey ─────────────────────────────────────

  void _startRecording() {
    setState(() {
      _survey = const [];
      _surveyLabel = _spotController.text.trim();
      _recordingStartedAt = DateTime.now();
      _tracker.startRecording();
    });
  }

  void _finishRecordingIfDue() {
    final startedAt = _recordingStartedAt;
    if (startedAt == null) return;
    if (DateTime.now().difference(startedAt) < _recordFor) {
      setState(() {}); // tick the countdown
      return;
    }
    setState(() {
      _survey = _tracker.stopRecording();
      _recordingStartedAt = null;
    });
  }

  void _cancelRecording() {
    _tracker.stopRecording();
    setState(() => _recordingStartedAt = null);
  }

  void _copySurvey() {
    final label = _surveyLabel.isEmpty ? 'Nuqta' : _surveyLabel;
    final lines = <String>['$label — ${_recordFor.inSeconds}s'];
    for (final s in _survey) {
      lines.add('${s.major}/${s.minor}: mediana ${s.median} dBm · '
          '10% ${s.percentile(10)} · 90% ${s.percentile(90)} · '
          'min ${s.min} · max ${s.max} · ${s.count} ta');
    }
    Clipboard.setData(ClipboardData(text: lines.join('\n')));
    _snack('Natija nusxalandi.');
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
                  if (_readiness == BeaconReadiness.ready) ...[
                    _signalCard(),
                    if (_survey.isNotEmpty) _surveyCard(),
                  ],
                  if (_loading)
                    const Padding(
                      padding: EdgeInsets.only(top: 40),
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
      padding: const EdgeInsets.only(top: 40),
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

  /// Live signal per beacon — median of the rolling window, with the spread
  /// that produced it, plus the recorder.
  Widget _signalCard() {
    final live = _tracker.live;
    final recording = _recordingStartedAt != null;
    final left = recording
        ? _recordFor - DateTime.now().difference(_recordingStartedAt!)
        : Duration.zero;

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: ClinicTheme.surfaceOf(context),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: ClinicTheme.dividerOf(context)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.bluetooth_searching, size: 18, color: ClinicTheme.teal),
              const SizedBox(width: 8),
              Text('Signal — ${_tracker.window.inSeconds}s mediana',
                  style: TextStyle(
                      fontSize: 13, fontWeight: FontWeight.w800, color: ClinicTheme.inkOf(context))),
              const Spacer(),
              const SizedBox(
                width: 12,
                height: 12,
                child: CircularProgressIndicator(strokeWidth: 2, color: ClinicTheme.teal),
              ),
            ],
          ),
          const SizedBox(height: 12),
          if (live.isEmpty)
            Text('Hech qanday beacon eshitilmayapti',
                style: TextStyle(fontSize: 12.5, color: ClinicTheme.mutedOf(context)))
          else
            ...live.map(_signalRow),
          const Divider(height: 24),
          if (recording)
            Row(
              children: [
                const Icon(Icons.fiber_manual_record, color: Color(0xFFBE123C), size: 18),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'Yozilmoqda… ${left.inSeconds}s qoldi — telefonni qimirlatmasdan turing',
                    style: TextStyle(
                        fontSize: 12.5, fontWeight: FontWeight.w700, color: ClinicTheme.inkOf(context)),
                  ),
                ),
                TextButton(onPressed: _cancelRecording, child: const Text('Bekor')),
              ],
            )
          else ...[
            TextField(
              controller: _spotController,
              style: const TextStyle(fontSize: 13),
              decoration: InputDecoration(
                isDense: true,
                labelText: 'Nuqta nomi',
                hintText: 'masalan: oxirgi qator / eshik oldi / koridor',
                hintStyle: TextStyle(fontSize: 12, color: ClinicTheme.faint),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
              ),
            ),
            const SizedBox(height: 10),
            SizedBox(
              width: double.infinity,
              height: 42,
              child: ElevatedButton.icon(
                onPressed: live.isEmpty ? null : _startRecording,
                style: ElevatedButton.styleFrom(
                  backgroundColor: ClinicTheme.blue,
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(11)),
                ),
                icon: const Icon(Icons.fiber_manual_record, size: 18),
                label: Text('${_recordFor.inSeconds} soniya yozib olish',
                    style: const TextStyle(fontWeight: FontWeight.w800)),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _signalRow(BeaconSignalStats s) {
    final strength = ((s.median + 100) / 60).clamp(0.0, 1.0);
    final color = s.median >= -75
        ? const Color(0xFF047857)
        : s.median >= -88
            ? const Color(0xFFB45309)
            : const Color(0xFFBE123C);

    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              SizedBox(
                width: 92,
                child: Text('${s.major} / ${s.minor}',
                    style: TextStyle(
                        fontSize: 12.5,
                        fontWeight: FontWeight.w700,
                        fontFeatures: const [FontFeature.tabularFigures()],
                        color: ClinicTheme.inkOf(context))),
              ),
              Expanded(
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(4),
                  child: LinearProgressIndicator(
                    value: strength,
                    minHeight: 8,
                    backgroundColor: ClinicTheme.dividerOf(context),
                    color: color,
                  ),
                ),
              ),
              const SizedBox(width: 10),
              SizedBox(
                width: 72,
                child: Text('${s.median} dBm',
                    textAlign: TextAlign.right,
                    style: TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w800,
                        fontFeatures: const [FontFeature.tabularFigures()],
                        color: color)),
              ),
            ],
          ),
          Padding(
            padding: const EdgeInsets.only(left: 92, top: 3),
            child: Text(
              'min ${s.min} · max ${s.max} · ${s.count} ta o\'lchov',
              style: TextStyle(
                  fontSize: 11,
                  fontFeatures: const [FontFeature.tabularFigures()],
                  color: ClinicTheme.mutedOf(context)),
            ),
          ),
        ],
      ),
    );
  }

  Widget _surveyCard() {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFFF0FDFA),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: ClinicTheme.teal.withOpacity(0.4)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.assignment_turned_in_outlined, size: 18, color: ClinicTheme.teal),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  '${_surveyLabel.isEmpty ? 'Natija' : _surveyLabel} — ${_recordFor.inSeconds}s',
                  style: const TextStyle(
                      fontSize: 13, fontWeight: FontWeight.w800, color: ClinicTheme.ink),
                ),
              ),
              IconButton(
                onPressed: _copySurvey,
                icon: const Icon(Icons.copy_rounded, size: 18),
                tooltip: 'Nusxalash',
                color: ClinicTheme.teal,
              ),
              IconButton(
                onPressed: () => setState(() => _survey = const []),
                icon: const Icon(Icons.close_rounded, size: 18),
                color: ClinicTheme.muted,
              ),
            ],
          ),
          const SizedBox(height: 4),
          ..._survey.map((s) => Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('${s.major} / ${s.minor}',
                        style: const TextStyle(
                            fontSize: 12, fontWeight: FontWeight.w700, color: ClinicTheme.muted)),
                    const SizedBox(height: 2),
                    Text('Mediana ${s.median} dBm',
                        style: const TextStyle(
                            fontSize: 20,
                            fontWeight: FontWeight.w800,
                            fontFeatures: [FontFeature.tabularFigures()],
                            color: ClinicTheme.ink)),
                    const SizedBox(height: 2),
                    Text(
                      '10% ${s.percentile(10)} · 90% ${s.percentile(90)} · '
                      'min ${s.min} · max ${s.max} · ${s.count} ta',
                      style: const TextStyle(
                          fontSize: 11.5,
                          fontFeatures: [FontFeature.tabularFigures()],
                          color: ClinicTheme.muted),
                    ),
                  ],
                ),
              )),
          Text(
            'Xona ichida "10%" qiymatiga, koridorda "90%" qiymatiga qarang — '
            'chegara shu ikkisining orasida bo\'ladi.',
            style: TextStyle(fontSize: 11, height: 1.35, color: ClinicTheme.muted.withOpacity(0.9)),
          ),
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
    final stats = _statsFor(p);
    final inRoom = stats != null;
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
                      ? (inRoom ? 'Tasdiqlangan · ${stats.median} dBm' : 'Tasdiqlangan')
                      : inRoom
                          ? 'Xona topildi · ${stats.median} dBm'
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
            if (p.requireFace) ...[
              const SizedBox(height: 8),
              Row(
                children: [
                  Icon(Icons.face_retouching_natural, size: 16, color: ClinicTheme.mutedOf(context)),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      "Tasdiqlashda old kamera ochiladi — yuzingiz LMS'dagi rasmingiz bilan solishtiriladi.",
                      style: TextStyle(fontSize: 11.5, color: ClinicTheme.mutedOf(context)),
                    ),
                  ),
                ],
              ),
            ],
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
                    : Icon(p.requireFace ? Icons.camera_front : Icons.how_to_reg),
                label: Text(
                    !inRoom
                        ? 'Xonaga kiring'
                        : busy
                            ? 'Tekshirilmoqda…'
                            : p.requireFace
                            ? 'Yuz bilan tasdiqlash'
                            : 'Davomatni tasdiqlash',
                    style: const TextStyle(fontWeight: FontWeight.w800)),
              ),
            ),
          ],
        ],
      ),
    );
  }
}
