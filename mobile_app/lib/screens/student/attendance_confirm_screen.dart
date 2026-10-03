import 'dart:async';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import '../../services/api_service.dart';
import '../../services/attendance_service.dart';
import '../../services/beacon_service.dart';
import '../../widgets/clinic_header.dart';
import '../../l10n/app_localizations.dart';

/// "Davomat": the attendance windows this student was prompted for, each
/// with one button. Pressing it checks the room beacon in the background
/// (strong enough = in the room), takes a selfie, and sends both to the
/// server, which matches the face against the student's approved photo.
/// No signal readouts - the student sees only the result.
class AttendanceConfirmScreen extends StatefulWidget {
  const AttendanceConfirmScreen({super.key});

  @override
  State<AttendanceConfirmScreen> createState() => _AttendanceConfirmScreenState();
}

class _AttendanceConfirmScreenState extends State<AttendanceConfirmScreen> {
  /// Weakest signal that still counts as "in the room". Matches the server.
  static const int minRssi = -75;

  /// How long the button waits for the beacon before giving up.
  static const _listenFor = Duration(seconds: 6);

  final _service = AttendanceService();
  final _beacons = BeaconService();

  List<PendingAttendance> _pending = const [];
  bool _loading = true;
  String? _error;
  int? _confirming;
  Timer? _tick;

  @override
  void initState() {
    super.initState();
    AttendanceWatcher.pending.addListener(_onPending);
    // Keeps the countdown moving.
    _tick = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted) setState(() {});
    });
    _load();
  }

  void _onPending() {
    if (mounted) setState(() => _pending = AttendanceWatcher.pending.value);
  }

  @override
  void dispose() {
    AttendanceWatcher.pending.removeListener(_onPending);
    _tick?.cancel();
    super.dispose();
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
      _error = AppLocalizations.current.networkError;
    }
    if (!mounted) return;
    setState(() => _loading = false);
  }

  // ── Confirm ─────────────────────────────────────────

  Future<void> _confirm(PendingAttendance p) async {
    final beacon = p.beacon;
    if (beacon == null) {
      _snack(context.l10n.pick(
          uz: 'Bu xona uchun beacon sozlanmagan.',
          ru: 'Для этой аудитории маяк не настроен.',
          en: 'No beacon is configured for this room.'), error: true);
      return;
    }

    setState(() => _confirming = p.sessionId);
    try {
      // 1. Bluetooth: on, permitted, and the room's beacon heard loud
      //    enough. The student never sees a number - just pass or fail.
      final rssi = await _hearBeacon(beacon);
      if (rssi == null) return;

      // 2. Selfie, matched on the server against the approved LMS photo.
      File? selfie;
      if (p.requireFace) {
        selfie = await _takeSelfie();
        if (selfie == null) {
          _snack(context.l10n.pick(
              uz: 'Davomat uchun yuzingizni suratga olish kerak.',
              ru: 'Для переклички нужно сфотографировать лицо.',
              en: 'A selfie is required to confirm attendance.'), error: true);
          return;
        }
      }

      // 3. Server: beacon, signal, face - all checked again there.
      final res = await _service.confirm(
        p.sessionId,
        uuid: beacon.uuid,
        major: beacon.major,
        minor: beacon.minor,
        rssi: rssi,
        photo: selfie,
      );
      if (!mounted) return;
      _snack(res['message']?.toString() ??
          context.l10n.pick(uz: 'Davomat tasdiqlandi.', ru: 'Присутствие подтверждено.', en: 'Attendance confirmed.'));
      await _load();
    } on ApiException catch (e) {
      if (mounted) _snack(e.message, error: true);
    } catch (_) {
      if (mounted) _snack(AppLocalizations.current.retryError, error: true);
    } finally {
      if (mounted) setState(() => _confirming = null);
    }
  }

  /// Checks Bluetooth, then scans for up to [_listenFor] for the room's
  /// beacon at [minRssi] or stronger. Returns the signal it accepted, or
  /// null after telling the student what was wrong.
  Future<int?> _hearBeacon(BeaconInfo beacon) async {
    final readiness = await _beacons.prepare();
    if (!mounted) return null;
    if (readiness != BeaconReadiness.ready) {
      await _explainReadiness(readiness);
      return null;
    }

    // Scan only now, only for this beacon. Several readings go into a
    // median, since one BLE packet can be 10-20 dB off.
    final tracker = BeaconSignalTracker();
    final sub = _beacons.range([beacon.uuid]).listen(tracker.add, onError: (_) {});
    final key = beacon.key;
    try {
      final deadline = DateTime.now().add(_listenFor);
      while (DateTime.now().isBefore(deadline)) {
        final stats = tracker.statsFor(key);
        if (stats != null && stats.count >= 3 && stats.median >= minRssi) {
          return stats.median;
        }
        await Future.delayed(const Duration(milliseconds: 500));
        if (!mounted) return null;
      }
    } finally {
      await sub.cancel();
    }

    final stats = tracker.statsFor(key);
    _snack(
      stats == null
          ? context.l10n.pick(
              uz: 'Xona signali topilmadi. Dars xonasida ekaningizga ishonch hosil qiling.',
              ru: 'Сигнал аудитории не найден. Убедитесь, что вы в аудитории.',
              en: 'Room signal not found. Make sure you are in the classroom.')
          : context.l10n.pick(
              uz: 'Signal juda kuchsiz. Xona ichiga kiring va qayta urinib ko\'ring.',
              ru: 'Сигнал слишком слабый. Войдите в аудиторию и попробуйте снова.',
              en: 'Signal too weak. Move inside the room and try again.'),
      error: true,
    );
    return null;
  }

  Future<void> _explainReadiness(BeaconReadiness r) async {
    final l = context.l10n;
    final (text, action, onTap) = switch (r) {
      BeaconReadiness.bluetoothOff => (
          l.pick(uz: 'Bluetooth o\'chiq. Davomat uchun uni yoqing.', ru: 'Bluetooth выключен. Включите его для переклички.', en: 'Bluetooth is off. Turn it on to confirm attendance.'),
          l.bluetoothSettings,
          _beacons.openBluetoothSettings,
        ),
      BeaconReadiness.permissionDenied => (
          l.pick(uz: 'Davomat uchun Bluetooth va joylashuv ruxsati kerak. Ilova GPS ishlatmaydi — ruxsat faqat xona signalini eshitish uchun.', ru: 'Для переклички нужны разрешения Bluetooth и геолокации. GPS не используется — только сигнал аудитории.', en: 'Bluetooth and location permissions are required. GPS is not used — only the room signal.'),
          l.permissionGrant,
          _beacons.openAppPermissionSettings,
        ),
      BeaconReadiness.locationOff => (
          l.pick(uz: 'Joylashuv xizmati o\'chiq. Android Bluetooth signalini faqat u yoqilganda eshitadi — GPS ishlatilmaydi.', ru: 'Геолокация выключена. Android слышит Bluetooth-сигнал только при включённой геолокации — GPS не используется.', en: 'Location services are off. Android only hears Bluetooth signals while they are on — GPS is not used.'),
          l.pick(uz: 'Joylashuvni yoqish', ru: 'Включить геолокацию', en: 'Turn on location'),
          _beacons.openLocationSettings,
        ),
      _ => (
          l.pick(uz: 'Bu qurilma Bluetooth skanerlashni qo\'llab-quvvatlamaydi.', ru: 'Это устройство не поддерживает сканирование Bluetooth.', en: 'This device does not support Bluetooth scanning.'),
          null,
          null,
        ),
    };
    await showDialog<void>(
      context: context,
      builder: (ctx) => AlertDialog(
        icon: const Icon(Icons.bluetooth_disabled, size: 40),
        content: Text(text, textAlign: TextAlign.center),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: Text(l.close)),
          if (action != null)
            ElevatedButton(
              onPressed: () {
                Navigator.pop(ctx);
                onTap?.call();
              },
              child: Text(action),
            ),
        ],
      ),
    );
  }

  /// Front camera only - no gallery, so an old photo cannot be picked.
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
      if (mounted) {
        _snack(context.l10n.pick(uz: 'Kamerani ochib bo\'lmadi. Ruxsatlarni tekshiring.', ru: 'Не удалось открыть камеру. Проверьте разрешения.', en: 'Could not open the camera. Check permissions.'), error: true);
      }
      return null;
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
          ClinicHeader(title: context.l10n.attendance, onBack: () => Navigator.of(context).pop()),
          Expanded(
            child: RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(14, 14, 14, 30),
                children: [
                  if (_loading)
                    const Padding(
                      padding: EdgeInsets.only(top: 40),
                      child: Center(child: CircularProgressIndicator()),
                    )
                  else if (_error != null)
                    _message(Icons.wifi_off_rounded, _error!)
                  else if (_pending.isEmpty)
                    _message(
                        Icons.event_available_outlined,
                        context.l10n.pick(
                            uz: 'Hozir ochiq davomat yo\'q.\nO\'qituvchi davomatni boshlaganda, xonada bo\'lsangiz shu yerda ko\'rinadi.',
                            ru: 'Сейчас открытой переклички нет.\nКогда преподаватель начнёт, она появится здесь, если вы в аудитории.',
                            en: 'No attendance is open right now.\nWhen the teacher starts one, it appears here if you are in the room.'))
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

  Widget _sessionCard(PendingAttendance p) {
    final left = p.timeLeft;
    final busy = _confirming == p.sessionId;
    final l = context.l10n;

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: ClinicTheme.surfaceOf(context),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: ClinicTheme.dividerOf(context)),
        boxShadow: ClinicTheme.cardShadowOf(context),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(p.subjectName,
              style: TextStyle(fontSize: 15.5, fontWeight: FontWeight.w800, color: ClinicTheme.inkOf(context))),
          const SizedBox(height: 4),
          Text(
            [p.lessonPairName, p.auditoriumName].where((s) => s != null && s.isNotEmpty).join(' · '),
            style: TextStyle(fontSize: 12.5, color: ClinicTheme.mutedOf(context)),
          ),
          const SizedBox(height: 12),
          if (p.isPresent)
            Row(
              children: [
                Icon(Icons.check_circle, color: ClinicTheme.greenOf(context), size: 20),
                const SizedBox(width: 8),
                Text(l.confirmed,
                    style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: ClinicTheme.greenOf(context))),
              ],
            )
          else ...[
            Row(
              children: [
                Icon(Icons.timer_outlined, size: 18, color: ClinicTheme.mutedOf(context)),
                const SizedBox(width: 6),
                Text(
                  l.pick(uz: 'Tasdiqlash uchun qoldi', ru: 'Осталось на подтверждение', en: 'Time left to confirm'),
                  style: TextStyle(fontSize: 12.5, color: ClinicTheme.mutedOf(context)),
                ),
                const Spacer(),
                Text(
                  '${left.inMinutes.toString().padLeft(2, '0')}:${(left.inSeconds % 60).toString().padLeft(2, '0')}',
                  style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                      fontFeatures: const [FontFeature.tabularFigures()],
                      color: left.inMinutes < 2 ? ClinicTheme.redOf(context) : ClinicTheme.inkOf(context)),
                ),
              ],
            ),
            const SizedBox(height: 14),
            SizedBox(
              width: double.infinity,
              height: 48,
              child: ElevatedButton.icon(
                onPressed: !busy && left > Duration.zero ? () => _confirm(p) : null,
                style: ElevatedButton.styleFrom(
                  backgroundColor: ClinicTheme.primaryOf(context),
                  foregroundColor: Colors.white,
                  disabledBackgroundColor: ClinicTheme.dividerOf(context),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                icon: busy
                    ? const SizedBox(
                        width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                    : const Icon(Icons.how_to_reg),
                label: Text(
                  busy ? l.checking : l.pick(uz: 'Davomatni tasdiqlash', ru: 'Подтвердить присутствие', en: 'Confirm attendance'),
                  style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}
