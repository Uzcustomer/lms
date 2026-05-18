import 'dart:io';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import '../../providers/auth_provider.dart';

class FaceLoginScreen extends StatefulWidget {
  final String login;
  const FaceLoginScreen({super.key, required this.login});

  @override
  State<FaceLoginScreen> createState() => _FaceLoginScreenState();
}

class _FaceLoginScreenState extends State<FaceLoginScreen> {
  static const _accent = Color(0xFF1E3A8A);
  static const _ink = Color(0xFF0F1B3D);

  File? _capturedFile;
  bool _busy = false;
  String? _error;
  double? _similarity;

  Future<void> _captureFace() async {
    setState(() {
      _error = null;
      _similarity = null;
    });

    final picker = ImagePicker();
    try {
      final picked = await picker.pickImage(
        source: ImageSource.camera,
        preferredCameraDevice: CameraDevice.front,
        imageQuality: 85,
        maxWidth: 1280,
        maxHeight: 1280,
      );
      if (picked == null) return;
      setState(() {
        _capturedFile = File(picked.path);
      });
    } catch (e) {
      setState(() => _error = 'Kameraga kirish bekor qilindi yoki xatolik yuz berdi');
    }
  }

  Future<void> _verify() async {
    if (_capturedFile == null) return;
    setState(() {
      _busy = true;
      _error = null;
      _similarity = null;
    });

    final auth = context.read<AuthProvider>();
    final success = await auth.studentFaceLogin(widget.login, _capturedFile!);

    if (!mounted) return;

    if (success) {
      Navigator.of(context).pop(true);
    } else {
      setState(() {
        _busy = false;
        _error = auth.errorMessage ?? 'Yuz mos kelmadi';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final safeTop = MediaQuery.of(context).padding.top;

    return Scaffold(
      backgroundColor: const Color(0xFFF7F8FB),
      body: SingleChildScrollView(
        child: Column(
          children: [
            Container(
              padding: EdgeInsets.fromLTRB(8, safeTop + 12, 24, 24),
              decoration: const BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [_accent, Color(0xFF2950C8)],
                ),
                borderRadius: BorderRadius.only(
                  bottomLeft: Radius.circular(24),
                  bottomRight: Radius.circular(24),
                ),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      IconButton(
                        icon: const Icon(Icons.arrow_back, color: Colors.white),
                        onPressed: () => Navigator.pop(context),
                      ),
                      const Expanded(
                        child: Text(
                          'Face ID',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 16,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                      const SizedBox(width: 48),
                    ],
                  ),
                  const SizedBox(height: 16),
                  const Center(
                    child: Icon(Icons.face_retouching_natural,
                        color: Colors.white, size: 48),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Yuzingizni kameraga ko\'rsating',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 14,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    widget.login,
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: Colors.white.withOpacity(0.75),
                      fontSize: 12,
                      letterSpacing: 1,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  AspectRatio(
                    aspectRatio: 1,
                    child: GestureDetector(
                      onTap: _busy ? null : _captureFace,
                      child: Container(
                        decoration: BoxDecoration(
                          color: Colors.white,
                          border: Border.all(
                            color: _capturedFile != null
                                ? _accent
                                : _ink.withOpacity(0.12),
                            width: 2,
                          ),
                          borderRadius: BorderRadius.circular(24),
                          boxShadow: [
                            BoxShadow(
                              color: _accent.withOpacity(0.08),
                              blurRadius: 24,
                              offset: const Offset(0, 8),
                            ),
                          ],
                        ),
                        child: ClipRRect(
                          borderRadius: BorderRadius.circular(22),
                          child: _capturedFile != null
                              ? Image.file(_capturedFile!, fit: BoxFit.cover)
                              : Center(
                                  child: Column(
                                    mainAxisAlignment: MainAxisAlignment.center,
                                    children: [
                                      Container(
                                        width: 80,
                                        height: 80,
                                        decoration: BoxDecoration(
                                          color: _accent.withOpacity(0.08),
                                          shape: BoxShape.circle,
                                        ),
                                        child: Icon(Icons.camera_alt_rounded,
                                            color: _accent, size: 36),
                                      ),
                                      const SizedBox(height: 12),
                                      Text(
                                        'Kamerani ochish',
                                        style: TextStyle(
                                          color: _ink,
                                          fontSize: 14,
                                          fontWeight: FontWeight.w700,
                                        ),
                                      ),
                                      const SizedBox(height: 4),
                                      Text(
                                        'Yuzingiz markazda bo\'lsin',
                                        style: TextStyle(
                                          color: _ink.withOpacity(0.55),
                                          fontSize: 12,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  if (_capturedFile != null)
                    TextButton.icon(
                      onPressed: _busy ? null : _captureFace,
                      icon: const Icon(Icons.refresh, size: 18),
                      label: const Text('Qayta olish'),
                      style: TextButton.styleFrom(foregroundColor: _accent),
                    ),
                  if (_error != null) ...[
                    const SizedBox(height: 8),
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: const Color(0xFFFEE2E2),
                        border: Border.all(color: const Color(0xFFFECACA)),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Row(
                        children: [
                          const Icon(Icons.error_outline,
                              color: Color(0xFFB91C1C), size: 16),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              _error!,
                              style: const TextStyle(
                                color: Color(0xFFB91C1C),
                                fontSize: 12,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                  if (_similarity != null) ...[
                    const SizedBox(height: 8),
                    Text(
                      'Mos kelish: ${_similarity!.toStringAsFixed(1)}%',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: _ink.withOpacity(0.7),
                        fontSize: 12,
                      ),
                    ),
                  ],
                  const SizedBox(height: 20),
                  InkWell(
                    onTap: (_capturedFile == null || _busy) ? null : _verify,
                    borderRadius: BorderRadius.circular(14),
                    child: Container(
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      decoration: BoxDecoration(
                        color: _capturedFile == null || _busy
                            ? _ink.withOpacity(0.15)
                            : _accent,
                        borderRadius: BorderRadius.circular(14),
                        boxShadow: _capturedFile == null || _busy
                            ? null
                            : [
                                BoxShadow(
                                  color: _accent.withOpacity(0.3),
                                  blurRadius: 20,
                                  offset: const Offset(0, 8),
                                ),
                              ],
                      ),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          if (_busy) ...[
                            const SizedBox(
                              width: 16,
                              height: 16,
                              child: CircularProgressIndicator(
                                strokeWidth: 2.4,
                                valueColor:
                                    AlwaysStoppedAnimation(Colors.white),
                              ),
                            ),
                            const SizedBox(width: 8),
                          ],
                          Text(
                            _busy ? 'Tekshirilmoqda...' : 'Tasdiqlash',
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 14,
                              fontWeight: FontWeight.w700,
                              letterSpacing: 0.3,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 24),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
