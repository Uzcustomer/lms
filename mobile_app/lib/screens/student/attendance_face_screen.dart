import 'dart:async';
import 'dart:io';
import 'package:camera/camera.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:permission_handler/permission_handler.dart';
import '../../services/api_service.dart';
import '../../services/attendance_service.dart';
import '../../widgets/clinic_header.dart';
import '../../l10n/app_localizations.dart';

/// In-app face step of attendance: the student's approved LMS photo on
/// top (what the server will compare against), a live front-camera frame
/// below. One tap takes the picture and hands it to [submit], which sends
/// it with the beacon data; the server's verdict is shown right here.
///
/// Pops with the server response map on success, null when the student
/// leaves without confirming.
class AttendanceFaceScreen extends StatefulWidget {
  final String subjectName;
  final Future<Map<String, dynamic>> Function(File photo) submit;

  const AttendanceFaceScreen({
    super.key,
    required this.subjectName,
    required this.submit,
  });

  @override
  State<AttendanceFaceScreen> createState() => _AttendanceFaceScreenState();
}

enum _Stage { preview, comparing, matched, failed }

class _AttendanceFaceScreenState extends State<AttendanceFaceScreen>
    with WidgetsBindingObserver {
  final _service = AttendanceService();

  FaceReference? _reference;
  bool _referenceLoading = true;
  String? _referenceError;

  CameraController? _camera;
  bool _cameraDenied = false;
  String? _cameraError;

  _Stage _stage = _Stage.preview;
  File? _captured;
  String? _verdict;
  double? _similarity;
  Map<String, dynamic>? _result;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _loadReference();
    _openCamera();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _camera?.dispose();
    super.dispose();
  }

  // The camera must be released when the app goes to the background and
  // reopened on return, or the preview comes back black.
  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    final cam = _camera;
    if (cam == null || !cam.value.isInitialized) return;
    if (state == AppLifecycleState.inactive ||
        state == AppLifecycleState.paused) {
      cam.dispose();
      _camera = null;
    } else if (state == AppLifecycleState.resumed && _stage == _Stage.preview) {
      _openCamera();
    }
  }

  Future<void> _loadReference() async {
    try {
      final ref = await _service.faceReference();
      if (!mounted) return;
      setState(() {
        _reference = ref;
        _referenceLoading = false;
      });
    } on ApiException catch (e) {
      if (mounted)
        setState(() {
          _referenceError = e.message;
          _referenceLoading = false;
        });
    } catch (_) {
      if (mounted) {
        setState(() {
          _referenceError = AppLocalizations.current.networkError;
          _referenceLoading = false;
        });
      }
    }
  }

  Future<void> _openCamera() async {
    final status = await Permission.camera.request();
    if (!mounted) return;
    if (!status.isGranted) {
      setState(() => _cameraDenied = true);
      return;
    }
    try {
      final cameras = await availableCameras();
      if (cameras.isEmpty) throw CameraException('none', 'no cameras');
      final front = cameras.firstWhere(
        (c) => c.lensDirection == CameraLensDirection.front,
        orElse: () => cameras.first,
      );
      final cam = CameraController(
        front,
        ResolutionPreset.medium,
        enableAudio: false,
        imageFormatGroup: ImageFormatGroup.jpeg,
      );
      await cam.initialize();
      if (!mounted) {
        cam.dispose();
        return;
      }
      setState(() {
        _camera = cam;
        _cameraDenied = false;
        _cameraError = null;
      });
    } catch (e) {
      if (mounted) setState(() => _cameraError = e.toString());
    }
  }

  // ── Capture + compare ───────────────────────────────

  Future<void> _captureAndCompare() async {
    final cam = _camera;
    if (cam == null || !cam.value.isInitialized || cam.value.isTakingPicture)
      return;

    setState(() {
      _stage = _Stage.comparing;
      _verdict = null;
      _similarity = null;
    });

    File? file;
    try {
      final shot = await cam.takePicture();
      file = File(shot.path);
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _stage = _Stage.failed;
        _verdict = context.l10n.pick(
          uz: 'Suratga olib bo\'lmadi. Qayta urinib ko\'ring.',
          ru: 'Не удалось сделать снимок. Попробуйте снова.',
          en: 'Could not take the picture. Try again.',
        );
      });
      return;
    }
    if (!mounted) return;
    setState(() => _captured = file);

    try {
      final res = await widget.submit(file);
      if (!mounted) return;
      setState(() {
        _stage = _Stage.matched;
        _result = res;
        _similarity = (res['face_similarity'] as num?)?.toDouble();
        _verdict = res['message']?.toString();
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _stage = _Stage.failed;
        _verdict = e.message;
        _similarity = (e.body?['face_similarity'] as num?)?.toDouble();
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _stage = _Stage.failed;
        _verdict = AppLocalizations.current.retryError;
      });
    }
  }

  void _retry() {
    setState(() {
      _stage = _Stage.preview;
      _captured = null;
      _verdict = null;
      _similarity = null;
    });
    if (_camera == null) _openCamera();
  }

  // ── UI ──────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    final l = context.l10n;
    return PopScope(
      canPop: _stage != _Stage.comparing,
      child: Scaffold(
        backgroundColor: ClinicTheme.bgOf(context),
        body: Column(
          children: [
            ClinicHeader(
              title: l.pick(
                uz: 'Yuzni tasdiqlash',
                ru: 'Подтверждение лица',
                en: 'Face check',
              ),
              overline: widget.subjectName,
              onBack: _stage == _Stage.comparing
                  ? null
                  : () => Navigator.of(context).pop(_result),
            ),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.fromLTRB(14, 14, 14, 30),
                children: [
                  _referenceCard(),
                  const SizedBox(height: 12),
                  _cameraCard(),
                  const SizedBox(height: 14),
                  _actions(),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  /// Top: the approved LMS photo and whether one was found.
  Widget _referenceCard() {
    final l = context.l10n;
    final ref = _reference;
    final found = ref?.found == true && ref?.photoUrl != null;

    final String title;
    final String sub;
    final Color tone;
    final IconData icon;
    if (_referenceLoading) {
      title = l.pick(
        uz: 'Rasm qidirilmoqda…',
        ru: 'Поиск фото…',
        en: 'Looking up photo…',
      );
      sub = l.pick(
        uz: 'LMS dagi tasdiqlangan rasmingiz',
        ru: 'Ваше утверждённое фото в LMS',
        en: 'Your approved LMS photo',
      );
      tone = ClinicTheme.mutedOf(context);
      icon = Icons.hourglass_top_rounded;
    } else if (_referenceError != null) {
      title = l.pick(
        uz: 'Rasmni olib bo\'lmadi',
        ru: 'Не удалось получить фото',
        en: 'Could not load photo',
      );
      sub = _referenceError!;
      tone = ClinicTheme.amberOf(context);
      icon = Icons.wifi_off_rounded;
    } else if (found) {
      title = l.pick(
        uz: 'Talaba rasmi topildi',
        ru: 'Фото студента найдено',
        en: 'Student photo found',
      );
      sub = l.pick(
        uz: 'Yuzingiz shu rasm bilan solishtiriladi · chegara ${ref!.threshold.toStringAsFixed(0)}%',
        ru: 'Лицо сравнивается с этим фото · порог ${ref.threshold.toStringAsFixed(0)}%',
        en: 'Your face is matched to this photo · threshold ${ref.threshold.toStringAsFixed(0)}%',
      );
      tone = ClinicTheme.greenOf(context);
      icon = Icons.verified_rounded;
    } else {
      title = l.pick(
        uz: 'Tasdiqlangan rasm topilmadi',
        ru: 'Утверждённое фото не найдено',
        en: 'No approved photo',
      );
      sub = l.pick(
        uz: 'LMS da tasdiqlangan suratingiz yo\'q — yuz tekshirilmaydi, davomat belgi bilan o\'tadi.',
        ru: 'В LMS нет утверждённого фото — лицо не проверяется, отметка пройдёт с пометкой.',
        en: 'No approved photo in LMS — face is not checked, attendance passes with a note.',
      );
      tone = ClinicTheme.amberOf(context);
      icon = Icons.no_photography_outlined;
    }

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: _cardDeco(),
      child: Row(
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(12),
            child: SizedBox(
              width: 64,
              height: 80,
              child: found
                  ? CachedNetworkImage(
                      imageUrl: ref!.photoUrl!,
                      fit: BoxFit.cover,
                      placeholder: (_, _) => _photoPlaceholder(),
                      errorWidget: (_, _, _) => _photoPlaceholder(broken: true),
                    )
                  : _photoPlaceholder(),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Icon(icon, size: 18, color: tone),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text(
                        title,
                        style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w800,
                          color: tone,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                Text(
                  sub,
                  style: TextStyle(
                    fontSize: 12,
                    height: 1.35,
                    color: ClinicTheme.mutedOf(context),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _photoPlaceholder({bool broken = false}) => Container(
    color: ClinicTheme.dividerOf(context),
    alignment: Alignment.center,
    child: Icon(
      broken ? Icons.broken_image_outlined : Icons.person_outline,
      color: ClinicTheme.faintOf(context),
      size: 28,
    ),
  );

  /// Bottom: live front camera, or the frozen shot while comparing.
  Widget _cameraCard() {
    final l = context.l10n;
    Widget body;
    if (_captured != null && _stage != _Stage.preview) {
      body = Image.file(_captured!, fit: BoxFit.cover);
    } else if (_cameraDenied) {
      body = _cameraMessage(
        Icons.no_photography_outlined,
        l.pick(
          uz: 'Kamera ruxsati berilmagan.',
          ru: 'Нет разрешения на камеру.',
          en: 'Camera permission not granted.',
        ),
        action: l.permissionGrant,
        onAction: openAppSettings,
      );
    } else if (_cameraError != null) {
      body = _cameraMessage(
        Icons.videocam_off_outlined,
        l.pick(
          uz: 'Kamerani ochib bo\'lmadi.',
          ru: 'Не удалось открыть камеру.',
          en: 'Could not open the camera.',
        ),
        action: l.pick(uz: 'Qayta urinish', ru: 'Повторить', en: 'Retry'),
        onAction: _openCamera,
      );
    } else if (_camera == null || !_camera!.value.isInitialized) {
      body = const Center(child: CircularProgressIndicator());
    } else {
      // Fill the frame the way a mirror would: crop, do not letterbox.
      final cam = _camera!;
      body = ClipRect(
        child: OverflowBox(
          alignment: Alignment.center,
          child: FittedBox(
            fit: BoxFit.cover,
            child: SizedBox(
              width: cam.value.previewSize?.height ?? 480,
              height: cam.value.previewSize?.width ?? 640,
              child: CameraPreview(cam),
            ),
          ),
        ),
      );
    }

    final verdictTone = switch (_stage) {
      _Stage.matched => ClinicTheme.greenOf(context),
      _Stage.failed => ClinicTheme.redOf(context),
      _ => ClinicTheme.primaryOf(context),
    };

    return Container(
      decoration: _cardDeco(),
      clipBehavior: Clip.antiAlias,
      child: Column(
        children: [
          // A 230 px wide 3:4 box rather than the full card width: the
          // face only needs to fill the oval, and the page stays short.
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 14, 14, 0),
            child: Center(
              child: ClipRRect(
                borderRadius: BorderRadius.circular(14),
                child: SizedBox(
                  width: 230,
                  child: AspectRatio(
                    aspectRatio: 3 / 4,
                    child: Stack(
                      fit: StackFit.expand,
                      children: [
                        ColoredBox(color: Colors.black, child: body),
                        // Oval guide so the student centres their face.
                        if (_stage == _Stage.preview && _camera != null)
                          IgnorePointer(
                            child: CustomPaint(
                              painter: _FaceGuidePainter(verdictTone),
                            ),
                          ),
                        if (_stage == _Stage.comparing)
                          Container(
                            color: Colors.black45,
                            alignment: Alignment.center,
                            child: Column(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                const CircularProgressIndicator(
                                  color: Colors.white,
                                ),
                                const SizedBox(height: 12),
                                Text(
                                  l.pick(
                                    uz: 'Solishtirilmoqda…',
                                    ru: 'Сравнение…',
                                    en: 'Comparing…',
                                  ),
                                  style: const TextStyle(
                                    color: Colors.white,
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                              ],
                            ),
                          ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 10, 14, 12),
            child: Row(
              children: [
                Icon(
                  switch (_stage) {
                    _Stage.matched => Icons.check_circle_rounded,
                    _Stage.failed => Icons.error_rounded,
                    _Stage.comparing => Icons.compare_rounded,
                    _Stage.preview => Icons.face_retouching_natural,
                  },
                  size: 20,
                  color: verdictTone,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    _verdict ??
                        l.pick(
                          uz: 'Yuzingizni ramka ichiga joylashtiring, yorug\' joyda turing.',
                          ru: 'Поместите лицо в рамку, стойте на свету.',
                          en: 'Put your face inside the frame, in good light.',
                        ),
                    style: TextStyle(
                      fontSize: 12.5,
                      height: 1.35,
                      color: ClinicTheme.inkOf(context),
                    ),
                  ),
                ),
                if (_similarity != null) ...[
                  const SizedBox(width: 8),
                  Text(
                    '${_similarity!.toStringAsFixed(0)}%',
                    style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w800,
                      color: verdictTone,
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _cameraMessage(
    IconData icon,
    String text, {
    String? action,
    VoidCallback? onAction,
  }) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 44, color: Colors.white70),
          const SizedBox(height: 10),
          Text(
            text,
            textAlign: TextAlign.center,
            style: const TextStyle(color: Colors.white),
          ),
          if (action != null) ...[
            const SizedBox(height: 10),
            OutlinedButton(
              onPressed: onAction,
              style: OutlinedButton.styleFrom(
                foregroundColor: Colors.white,
                side: const BorderSide(color: Colors.white70),
              ),
              child: Text(action),
            ),
          ],
        ],
      ),
    );
  }

  Widget _actions() {
    final l = context.l10n;
    final cameraReady = _camera != null && _camera!.value.isInitialized;

    final (label, icon, onTap, color) = switch (_stage) {
      _Stage.preview => (
        l.pick(
          uz: 'Suratga olish va solishtirish',
          ru: 'Снять и сравнить',
          en: 'Take photo and compare',
        ),
        Icons.camera_alt_rounded,
        cameraReady && !_referenceLoading ? _captureAndCompare : null,
        ClinicTheme.primaryOf(context),
      ),
      _Stage.comparing => (
        l.checking,
        Icons.hourglass_top_rounded,
        null,
        ClinicTheme.primaryOf(context),
      ),
      _Stage.matched => (
        l.pick(uz: 'Yopish', ru: 'Закрыть', en: 'Close'),
        Icons.check_rounded,
        () => Navigator.of(context).pop(_result),
        ClinicTheme.greenOf(context),
      ),
      _Stage.failed => (
        l.pick(uz: 'Qayta urinish', ru: 'Попробовать снова', en: 'Try again'),
        Icons.refresh_rounded,
        _retry,
        ClinicTheme.primaryOf(context),
      ),
    };

    return SizedBox(
      width: double.infinity,
      height: 50,
      child: ElevatedButton.icon(
        onPressed: onTap,
        style: ElevatedButton.styleFrom(
          backgroundColor: color,
          foregroundColor: Colors.white,
          disabledBackgroundColor: ClinicTheme.dividerOf(context),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
        ),
        icon: Icon(icon),
        label: Text(
          label,
          style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15),
        ),
      ),
    );
  }

  BoxDecoration _cardDeco() => BoxDecoration(
    color: ClinicTheme.surfaceOf(context),
    borderRadius: BorderRadius.circular(16),
    border: Border.all(color: ClinicTheme.dividerOf(context)),
    boxShadow: ClinicTheme.cardShadowOf(context),
  );
}

/// Dashed oval in the middle of the preview, darkened outside.
class _FaceGuidePainter extends CustomPainter {
  final Color color;
  const _FaceGuidePainter(this.color);

  @override
  void paint(Canvas canvas, Size size) {
    final oval = Rect.fromCenter(
      center: Offset(size.width / 2, size.height * 0.46),
      width: size.width * 0.62,
      height: size.height * 0.58,
    );
    final outside = Path()
      ..addRect(Offset.zero & size)
      ..addOval(oval)
      ..fillType = PathFillType.evenOdd;
    canvas.drawPath(
      outside,
      Paint()..color = Colors.black.withValues(alpha: 0.35),
    );
    canvas.drawOval(
      oval,
      Paint()
        ..color = color
        ..style = PaintingStyle.stroke
        ..strokeWidth = 3,
    );
  }

  @override
  bool shouldRepaint(_FaceGuidePainter old) => old.color != color;
}
