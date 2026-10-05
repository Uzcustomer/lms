import 'dart:async';
import 'dart:io';
import 'dart:ui' show ImageFilter;
import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../services/attendance_service.dart';
import '../../services/beacon_service.dart';
import '../../widgets/clinic_header.dart';
import '../../l10n/app_localizations.dart';
import 'attendance_face_screen.dart';

/// Opens the attendance prompt as a centred modal over a blurred copy of
/// whatever the student was looking at.
Future<void> showAttendanceConfirm(BuildContext context) {
  return showGeneralDialog<void>(
    context: context,
    useRootNavigator: true,
    barrierDismissible: true,
    barrierLabel: 'attendance',
    barrierColor: Colors.black.withValues(alpha: 0.30),
    transitionDuration: const Duration(milliseconds: 240),
    pageBuilder: (_, _, _) => const AttendanceConfirmScreen(),
    transitionBuilder: (_, anim, _, child) {
      final t = Curves.easeOutCubic.transform(anim.value);
      return BackdropFilter(
        filter: ImageFilter.blur(sigmaX: 12 * t, sigmaY: 12 * t),
        child: FadeTransition(
          opacity: anim,
          child: ScaleTransition(
            scale: Tween<double>(begin: 0.94, end: 1).animate(anim),
            child: child,
          ),
        ),
      );
    },
  );
}

/// "Davomat": the attendance windows this student was prompted for, each
/// with one button, shown as a modal card (see [showAttendanceConfirm]).
/// It listens for the room beacons the whole time it is open and shows the
/// live signal on each card; pressing the button requires that signal to
/// be strong enough (= in the room), then opens the in-app face frame,
/// which sends the selfie with the beacon data to the server for matching
/// against the student's approved photo.
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

  // Result strip inside the modal - a SnackBar would land on the blurred
  // page underneath.
  String? _notice;
  bool _noticeError = false;
  Timer? _noticeTimer;

  // Live beacon scan while the screen is open: feeds the dBm readout on
  // each card and the check behind the confirm button.
  final _tracker = BeaconSignalTracker();
  StreamSubscription<List<BeaconSighting>>? _scan;
  List<String> _scanUuids = const [];
  BeaconReadiness? _readiness;

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
    _noticeTimer?.cancel();
    _scan?.cancel();
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
    await _startScan();
  }

  /// Ranges for every beacon among the open sessions. Safe to call again:
  /// restarts only when the beacon set changed or the scan is not running.
  Future<BeaconReadiness> _startScan({bool request = true}) async {
    final uuids = _pending
        .where((p) => !p.isPresent)
        .map((p) => p.beacon?.uuid)
        .whereType<String>()
        .toSet()
        .toList()
      ..sort();
    if (uuids.isEmpty) {
      await _scan?.cancel();
      _scan = null;
      return _readiness = BeaconReadiness.ready;
    }

    final readiness = await _beacons.prepare(request: request);
    if (!mounted) return readiness;
    setState(() => _readiness = readiness);
    if (readiness != BeaconReadiness.ready) return readiness;

    if (_scan != null && _scanUuids.join(',') == uuids.join(',')) return readiness;
    await _scan?.cancel();
    _tracker.clear();
    _scanUuids = uuids;
    _scan = _beacons.range(uuids).listen(_tracker.add, onError: (_) {});
    return readiness;
  }

  /// Live median for a session's beacon, null while nothing is heard.
  BeaconSignalStats? _signalFor(PendingAttendance p) {
    final b = p.beacon;
    if (b == null) return null;
    _tracker.prune();
    return _tracker.statsFor(b.key);
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

      // 2. Server: beacon, signal, face - all checked again there.
      Future<Map<String, dynamic>> send(File? selfie) => _service.confirm(
            p.sessionId,
            uuid: beacon.uuid,
            major: beacon.major,
            minor: beacon.minor,
            rssi: rssi,
            photo: selfie,
          );

      if (p.requireFace) {
        // The face frame shows the approved photo and the live camera, sends
        // the shot together with the beacon data, and shows the verdict.
        if (!mounted) return;
        final res = await Navigator.of(context).push<Map<String, dynamic>>(
          MaterialPageRoute(
            builder: (_) => AttendanceFaceScreen(subjectName: p.subjectName, submit: send),
          ),
        );
        if (!mounted) return;
        if (res != null) {
          _snack(res['message']?.toString() ??
              context.l10n.pick(uz: 'Davomat tasdiqlandi.', ru: 'Присутствие подтверждено.', en: 'Attendance confirmed.'));
        }
        await _load();
        return;
      }

      final res = await send(null);
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
    final readiness = await _startScan();
    if (!mounted) return null;
    if (readiness != BeaconReadiness.ready) {
      await _explainReadiness(readiness);
      return null;
    }

    // The screen-wide scan is already running; wait for enough readings.
    // Several go into a median, since one BLE packet can be 10-20 dB off.
    final key = beacon.key;
    final deadline = DateTime.now().add(_listenFor);
    while (DateTime.now().isBefore(deadline)) {
      _tracker.prune();
      final stats = _tracker.statsFor(key);
      if (stats != null && stats.count >= 3 && stats.median >= minRssi) {
        return stats.median;
      }
      await Future.delayed(const Duration(milliseconds: 500));
      if (!mounted) return null;
    }

    _tracker.prune();
    final stats = _tracker.statsFor(key);
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

  void _snack(String msg, {bool error = false}) {
    _noticeTimer?.cancel();
    setState(() {
      _notice = msg;
      _noticeError = error;
    });
    _noticeTimer = Timer(const Duration(seconds: 6), () {
      if (mounted) setState(() => _notice = null);
    });
  }

  // ── UI ──────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    final size = MediaQuery.sizeOf(context);

    return Dialog(
      backgroundColor: Colors.transparent,
      elevation: 0,
      insetPadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 32),
      child: ConstrainedBox(
        constraints: BoxConstraints(maxWidth: 440, maxHeight: size.height * 0.82),
        child: Material(
          color: ClinicTheme.surfaceOf(context),
          borderRadius: BorderRadius.circular(22),
          clipBehavior: Clip.antiAlias,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              // Title strip in the chosen accent, like the page headers.
              Container(
                padding: const EdgeInsets.fromLTRB(18, 14, 8, 14),
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: ClinicTheme.heroGradientOf(context),
                  ),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.how_to_reg_rounded, color: Colors.white, size: 24),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        l.pick(uz: 'Davomatni tasdiqlash', ru: 'Подтверждение присутствия', en: 'Confirm attendance'),
                        style: const TextStyle(color: Colors.white, fontSize: 16.5, fontWeight: FontWeight.w800),
                      ),
                    ),
                    IconButton(
                      tooltip: l.pick(uz: 'Yangilash', ru: 'Обновить', en: 'Refresh'),
                      onPressed: _loading ? null : _load,
                      icon: const Icon(Icons.refresh_rounded, color: Colors.white),
                    ),
                    IconButton(
                      tooltip: l.close,
                      onPressed: () => Navigator.of(context).pop(),
                      icon: const Icon(Icons.close_rounded, color: Colors.white),
                    ),
                  ],
                ),
              ),
              if (_notice != null)
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                  color: _noticeError ? const Color(0xFFBE123C) : const Color(0xFF047857),
                  child: Text(_notice!,
                      style: const TextStyle(color: Colors.white, fontSize: 12.5, fontWeight: FontWeight.w600)),
                ),
              Flexible(
                child: ListView(
                  shrinkWrap: true,
                  padding: const EdgeInsets.fromLTRB(14, 14, 14, 16),
                  children: [
                    if (_loading)
                      const Padding(
                        padding: EdgeInsets.symmetric(vertical: 36),
                        child: Center(child: CircularProgressIndicator()),
                      )
                    else if (_error != null)
                      _message(Icons.wifi_off_rounded, _error!)
                    else if (_pending.isEmpty)
                      _message(
                          Icons.event_available_outlined,
                          l.pick(
                              uz: 'Hozir ochiq davomat yo\'q.\nO\'qituvchi davomatni boshlaganda shu yerda ko\'rinadi.',
                              ru: 'Сейчас открытой переклички нет.\nКогда преподаватель начнёт, она появится здесь.',
                              en: 'No attendance is open right now.\nWhen the teacher starts one, it appears here.'))
                    else
                      ..._pending.map(_sessionCard),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _message(IconData icon, String text) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 28),
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
            _signalRow(p),
            const SizedBox(height: 8),
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

  /// Live room signal: the median dBm of the last readings, green once it
  /// clears [minRssi], with the Bluetooth/location problem named when the
  /// scan cannot run at all.
  Widget _signalRow(PendingAttendance p) {
    final l = context.l10n;
    final stats = _signalFor(p);
    final r = _readiness;

    final String text;
    final String? value;
    final Color tone;
    final IconData icon;
    if (p.beacon == null) {
      text = l.pick(uz: 'Bu xona uchun beacon sozlanmagan', ru: 'Для аудитории маяк не настроен', en: 'No beacon for this room');
      value = null;
      tone = ClinicTheme.amberOf(context);
      icon = Icons.bluetooth_disabled;
    } else if (r != null && r != BeaconReadiness.ready) {
      text = switch (r) {
        BeaconReadiness.bluetoothOff => l.pick(uz: 'Bluetooth o\'chiq', ru: 'Bluetooth выключен', en: 'Bluetooth is off'),
        BeaconReadiness.locationOff => l.pick(uz: 'Joylashuv xizmati o\'chiq', ru: 'Геолокация выключена', en: 'Location is off'),
        BeaconReadiness.permissionDenied => l.pick(uz: 'Bluetooth / joylashuv ruxsati yo\'q', ru: 'Нет разрешения Bluetooth / геолокации', en: 'No Bluetooth / location permission'),
        _ => l.pick(uz: 'Bluetooth skaner ishlamaydi', ru: 'Сканер Bluetooth недоступен', en: 'Bluetooth scanning unavailable'),
      };
      value = null;
      tone = ClinicTheme.redOf(context);
      icon = Icons.bluetooth_disabled;
    } else if (stats == null) {
      text = l.pick(uz: 'Xona signali qidirilmoqda…', ru: 'Поиск сигнала аудитории…', en: 'Looking for the room signal…');
      value = null;
      tone = ClinicTheme.mutedOf(context);
      icon = Icons.bluetooth_searching;
    } else {
      final inRoom = stats.median >= minRssi;
      text = inRoom
          ? l.pick(uz: 'Xona signali · qabul qilinadi', ru: 'Сигнал аудитории · принимается', en: 'Room signal · accepted')
          : l.pick(uz: 'Signal kuchsiz · $minRssi dBm dan kuchli bo\'lishi kerak', ru: 'Слабый сигнал · нужно сильнее $minRssi dBm', en: 'Weak signal · needs to beat $minRssi dBm');
      value = '${stats.median} dBm';
      tone = inRoom ? ClinicTheme.greenOf(context) : ClinicTheme.redOf(context);
      icon = inRoom ? Icons.bluetooth_connected : Icons.bluetooth_searching;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: tone.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: tone.withValues(alpha: 0.25)),
      ),
      child: Row(
        children: [
          Icon(icon, size: 18, color: tone),
          const SizedBox(width: 8),
          Expanded(
            child: Text(text, style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: tone)),
          ),
          if (value != null)
            Text(
              value,
              style: TextStyle(
                fontSize: 15,
                fontWeight: FontWeight.w800,
                fontFeatures: const [FontFeature.tabularFigures()],
                color: tone,
              ),
            ),
        ],
      ),
    );
  }
}
