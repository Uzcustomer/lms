import 'dart:async';
import 'dart:math' as math;
import 'package:flutter/material.dart';
import '../../config/api_config.dart';
import '../../config/theme.dart';
import '../../services/api_service.dart';
import '../../services/student_service.dart';

class ChatGroupScreen extends StatefulWidget {
  const ChatGroupScreen({super.key});

  @override
  State<ChatGroupScreen> createState() => _ChatGroupScreenState();
}

class _ChatGroupScreenState extends State<ChatGroupScreen> {
  final _controller = TextEditingController();
  final _scrollController = ScrollController();
  final _focusNode = FocusNode();
  List<dynamic> _messages = [];
  bool _loading = true;
  bool _sending = false;
  Timer? _pollTimer;

  @override
  void initState() {
    super.initState();
    _loadMessages();
    _pollTimer = Timer.periodic(const Duration(seconds: 5), (_) {
      if (mounted) _loadMessages(silent: true);
    });
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    _controller.dispose();
    _scrollController.dispose();
    _focusNode.dispose();
    super.dispose();
  }

  Future<void> _loadMessages({bool silent = false}) async {
    try {
      final api = ApiService();
      final service = StudentService(api);
      final res = await service.getGroupMessages();
      if (mounted && res['success'] == true) {
        final newList = res['data'] as List<dynamic>? ?? [];
        final hadMessages = _messages.length;
        setState(() {
          _messages = newList;
          _loading = false;
        });
        if (hadMessages < newList.length) {
          _scrollToBottom();
        }
      } else {
        if (mounted && !silent) setState(() => _loading = false);
      }
    } catch (_) {
      if (mounted && !silent) setState(() => _loading = false);
    }
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scrollController.hasClients) {
        _scrollController.animateTo(
          _scrollController.position.maxScrollExtent,
          duration: const Duration(milliseconds: 200),
          curve: Curves.easeOut,
        );
      }
    });
  }

  Future<void> _send() async {
    final text = _controller.text.trim();
    if (text.isEmpty || _sending) return;

    setState(() => _sending = true);
    _controller.clear();

    try {
      final api = ApiService();
      final service = StudentService(api);
      final res = await service.sendGroupMessage(text);
      if (mounted && res['success'] == true) {
        final msg = res['data'];
        setState(() {
          _messages.add(msg);
          _sending = false;
        });
        _scrollToBottom();
      } else {
        if (mounted) {
          setState(() => _sending = false);
          _controller.text = text;
        }
      }
    } catch (_) {
      if (mounted) {
        setState(() => _sending = false);
        _controller.text = text;
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final sub = isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;

    return Stack(
      children: [
        Positioned.fill(
          child: CustomPaint(
            painter: _MedicalPatternPainter(isDark: isDark),
          ),
        ),
        Column(
          children: [
            Expanded(
              child: _loading
                  ? const Center(child: CircularProgressIndicator())
                  : _messages.isEmpty
                      ? Center(
                          child: Container(
                            padding: const EdgeInsets.symmetric(
                                horizontal: 32, vertical: 24),
                            margin: const EdgeInsets.symmetric(horizontal: 40),
                            decoration: BoxDecoration(
                              color: (isDark ? Colors.black : Colors.white)
                                  .withOpacity(0.85),
                              borderRadius: BorderRadius.circular(20),
                              border: Border.all(
                                  color: isDark
                                      ? Colors.white10
                                      : Colors.grey.shade200),
                            ),
                            child: Column(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Container(
                                  width: 60,
                                  height: 60,
                                  decoration: BoxDecoration(
                                    color: const Color(0xFF4A6CF7)
                                        .withOpacity(0.1),
                                    shape: BoxShape.circle,
                                  ),
                                  child: Icon(Icons.group_outlined,
                                      size: 28, color: sub),
                                ),
                                const SizedBox(height: 14),
                                Text('Guruh chati',
                                    style: TextStyle(
                                        color: isDark
                                            ? AppTheme.darkTextPrimary
                                            : AppTheme.textPrimary,
                                        fontSize: 15,
                                        fontWeight: FontWeight.w600)),
                                const SizedBox(height: 4),
                                Text(
                                    'Guruhingiz a\'zolari bilan suhbatlashing!',
                                    textAlign: TextAlign.center,
                                    style:
                                        TextStyle(color: sub, fontSize: 12)),
                              ],
                            ),
                          ),
                        )
                      : ListView.builder(
                          controller: _scrollController,
                          padding: const EdgeInsets.symmetric(
                              horizontal: 12, vertical: 8),
                          itemCount: _messages.length,
                          itemBuilder: (_, i) {
                            final msg = _messages[i];
                            final isMe = msg['is_me'] == true;
                            final showDate = i == 0 ||
                                _dateDiffers(_messages[i - 1], msg);
                            final showName = !isMe &&
                                (i == 0 ||
                                    _messages[i - 1]['is_me'] == true ||
                                    _senderDiffers(_messages[i - 1], msg));
                            return Column(
                              children: [
                                if (showDate) _buildDateChip(msg, isDark),
                                _buildBubble(msg, isMe, isDark, showName),
                              ],
                            );
                          },
                        ),
            ),
            _buildInput(isDark),
          ],
        ),
      ],
    );
  }

  bool _dateDiffers(dynamic prev, dynamic curr) {
    try {
      final a = DateTime.parse(prev['created_at'].toString()).toLocal();
      final b = DateTime.parse(curr['created_at'].toString()).toLocal();
      return a.year != b.year || a.month != b.month || a.day != b.day;
    } catch (_) {
      return false;
    }
  }

  bool _senderDiffers(dynamic prev, dynamic curr) {
    return prev['sender_name']?.toString() !=
        curr['sender_name']?.toString();
  }

  Widget _buildDateChip(dynamic msg, bool isDark) {
    String label = '';
    try {
      final dt = DateTime.parse(msg['created_at'].toString()).toLocal();
      final now = DateTime.now();
      if (dt.year == now.year && dt.month == now.month && dt.day == now.day) {
        label = 'Bugun';
      } else if (dt.year == now.year &&
          dt.month == now.month &&
          dt.day == now.day - 1) {
        label = 'Kecha';
      } else {
        label =
            '${dt.day}.${dt.month.toString().padLeft(2, '0')}.${dt.year}';
      }
    } catch (_) {
      return const SizedBox.shrink();
    }

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Center(
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 5),
          decoration: BoxDecoration(
            color: (isDark ? Colors.black87 : Colors.white).withOpacity(0.85),
            borderRadius: BorderRadius.circular(14),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(0.06),
                blurRadius: 4,
              ),
            ],
          ),
          child: Text(label,
              style: TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w500,
                  color: isDark
                      ? AppTheme.darkTextSecondary
                      : AppTheme.textSecondary)),
        ),
      ),
    );
  }

  Widget _buildBubble(
      dynamic msg, bool isMe, bool isDark, bool showName) {
    final text = msg['message']?.toString() ?? '';
    final senderName = msg['sender_name']?.toString() ?? '';
    final senderImage = msg['sender_image']?.toString();
    String time = '';
    try {
      final dt = DateTime.parse(msg['created_at'].toString()).toLocal();
      time =
          '${dt.hour.toString().padLeft(2, '0')}:${dt.minute.toString().padLeft(2, '0')}';
    } catch (_) {}

    final textColor = isMe
        ? Colors.white
        : isDark
            ? AppTheme.darkTextPrimary
            : AppTheme.textPrimary;
    final timeColor = isMe
        ? Colors.white70
        : isDark
            ? AppTheme.darkTextSecondary
            : AppTheme.textSecondary;

    final nameColors = [
      const Color(0xFFE53935),
      const Color(0xFF8E24AA),
      const Color(0xFF43A047),
      const Color(0xFFFF6D00),
      const Color(0xFF1E88E5),
      const Color(0xFF00897B),
      const Color(0xFFC62828),
      const Color(0xFF6A1B9A),
    ];
    final nameColor =
        nameColors[senderName.hashCode.abs() % nameColors.length];

    final initials = senderName
        .split(' ')
        .take(2)
        .map((w) => w.isNotEmpty ? w[0].toUpperCase() : '')
        .join();

    return Padding(
      padding: EdgeInsets.only(top: showName && !isMe ? 8 : 2, bottom: 2),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        mainAxisAlignment:
            isMe ? MainAxisAlignment.end : MainAxisAlignment.start,
        children: [
          if (!isMe) ...[
            if (showName)
              Container(
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  border: Border.all(
                      color: nameColor.withOpacity(0.3), width: 1.5),
                ),
                child: CircleAvatar(
                  radius: 16,
                  backgroundColor: nameColor.withOpacity(0.1),
                  backgroundImage:
                      senderImage != null && senderImage.isNotEmpty
                          ? NetworkImage(
                              '${ApiConfig.baseUrl}/image-proxy?url=$senderImage')
                          : null,
                  child: senderImage == null || senderImage.isEmpty
                      ? Text(initials,
                          style: TextStyle(
                              fontSize: 10,
                              fontWeight: FontWeight.w700,
                              color: nameColor))
                      : null,
                ),
              )
            else
              const SizedBox(width: 34),
            const SizedBox(width: 6),
          ],
          Flexible(
            child: Container(
              constraints: BoxConstraints(
                  maxWidth: MediaQuery.of(context).size.width * 0.72),
              padding: const EdgeInsets.fromLTRB(12, 6, 12, 6),
              decoration: BoxDecoration(
                gradient: isMe
                    ? const LinearGradient(
                        colors: [Color(0xFF4A6CF7), Color(0xFF5B7BF8)],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      )
                    : null,
                color: isMe
                    ? null
                    : isDark
                        ? AppTheme.darkCard
                        : Colors.white,
                borderRadius: BorderRadius.only(
                  topLeft: const Radius.circular(18),
                  topRight: const Radius.circular(18),
                  bottomLeft: Radius.circular(isMe ? 18 : 4),
                  bottomRight: Radius.circular(isMe ? 4 : 18),
                ),
                border: isMe
                    ? null
                    : Border.all(
                        color: isDark ? Colors.white10 : Colors.grey.shade200),
                boxShadow: [
                  BoxShadow(
                    color: isMe
                        ? const Color(0xFF4A6CF7).withOpacity(0.2)
                        : Colors.black.withOpacity(0.05),
                    blurRadius: 8,
                    offset: const Offset(0, 2),
                  ),
                ],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (showName && !isMe)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 3),
                      child: Text(senderName,
                          style: TextStyle(
                              fontSize: 11.5,
                              fontWeight: FontWeight.w700,
                              color: nameColor)),
                    ),
                  Text(text,
                      style: TextStyle(fontSize: 14.5, color: textColor)),
                  const SizedBox(height: 2),
                  Align(
                    alignment: Alignment.bottomRight,
                    child: Text(time,
                        style:
                            TextStyle(fontSize: 10.5, color: timeColor)),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildInput(bool isDark) {
    final inputBg = isDark ? AppTheme.darkCard : Colors.white;

    return Container(
      padding: EdgeInsets.only(
        left: 10,
        right: 10,
        top: 10,
        bottom: MediaQuery.of(context).padding.bottom + 10,
      ),
      decoration: BoxDecoration(
        color: inputBg,
        border: Border(
          top: BorderSide(
            color: isDark ? Colors.white10 : Colors.grey.shade200,
          ),
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.05),
            blurRadius: 10,
            offset: const Offset(0, -3),
          ),
        ],
      ),
      child: Row(
        children: [
          Expanded(
            child: Container(
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(24),
                border: Border.all(
                  color: isDark ? Colors.white12 : Colors.grey.shade300,
                ),
              ),
              child: TextField(
                controller: _controller,
                focusNode: _focusNode,
                textCapitalization: TextCapitalization.sentences,
                maxLines: 4,
                minLines: 1,
                decoration: InputDecoration(
                  hintText: 'Guruhga yozing...',
                  hintStyle: TextStyle(
                      color: isDark
                          ? AppTheme.darkTextSecondary
                          : AppTheme.textSecondary,
                      fontSize: 14),
                  filled: true,
                  fillColor: isDark
                      ? AppTheme.darkBackground
                      : const Color(0xFFF5F7FA),
                  contentPadding:
                      const EdgeInsets.symmetric(horizontal: 18, vertical: 10),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(24),
                    borderSide: BorderSide.none,
                  ),
                ),
                onSubmitted: (_) => _send(),
              ),
            ),
          ),
          const SizedBox(width: 8),
          Container(
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [Color(0xFF4A6CF7), Color(0xFF6C63FF)],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              shape: BoxShape.circle,
              boxShadow: [
                BoxShadow(
                  color: const Color(0xFF4A6CF7).withOpacity(0.3),
                  blurRadius: 8,
                  offset: const Offset(0, 2),
                ),
              ],
            ),
            child: Material(
              color: Colors.transparent,
              shape: const CircleBorder(),
              child: InkWell(
                customBorder: const CircleBorder(),
                onTap: _sending ? null : _send,
                child: Padding(
                  padding: const EdgeInsets.all(11),
                  child: _sending
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                              strokeWidth: 2, color: Colors.white))
                      : const Icon(Icons.send_rounded,
                          color: Colors.white, size: 20),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _MedicalPatternPainter extends CustomPainter {
  final bool isDark;

  _MedicalPatternPainter({required this.isDark});

  @override
  void paint(Canvas canvas, Size size) {
    final bgColor = isDark ? const Color(0xFF121212) : const Color(0xFFEDF2F7);
    canvas.drawRect(
        Rect.fromLTWH(0, 0, size.width, size.height), Paint()..color = bgColor);

    final paint = Paint()
      ..color = (isDark ? Colors.white : const Color(0xFF4A6CF7))
          .withOpacity(isDark ? 0.03 : 0.04)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.2;

    final fillPaint = Paint()
      ..color = (isDark ? Colors.white : const Color(0xFF4A6CF7))
          .withOpacity(isDark ? 0.015 : 0.02)
      ..style = PaintingStyle.fill;

    const spacing = 90.0;
    const iconSize = 18.0;

    for (double y = 20; y < size.height; y += spacing) {
      for (double x = 20; x < size.width; x += spacing) {
        final col = ((x - 20) / spacing).floor();
        final row = ((y - 20) / spacing).floor();
        final offsetX = (row % 2 == 0) ? 0.0 : spacing / 2;
        final px = x + offsetX;
        if (px > size.width) continue;

        final type = (col + row * 3) % 6;
        switch (type) {
          case 0:
            _drawCross(canvas, px, y, iconSize, paint);
            break;
          case 1:
            _drawHeart(canvas, px, y, iconSize * 0.8, paint, fillPaint);
            break;
          case 2:
            _drawPill(canvas, px, y, iconSize, paint);
            break;
          case 3:
            _drawStethoscope(canvas, px, y, iconSize, paint);
            break;
          case 4:
            _drawDna(canvas, px, y, iconSize, paint);
            break;
          case 5:
            _drawPulse(canvas, px, y, iconSize * 1.2, paint);
            break;
        }
      }
    }
  }

  void _drawCross(Canvas canvas, double cx, double cy, double s, Paint p) {
    final half = s / 2;
    final thick = s / 5;
    final path = Path()
      ..moveTo(cx - thick, cy - half)
      ..lineTo(cx + thick, cy - half)
      ..lineTo(cx + thick, cy - thick)
      ..lineTo(cx + half, cy - thick)
      ..lineTo(cx + half, cy + thick)
      ..lineTo(cx + thick, cy + thick)
      ..lineTo(cx + thick, cy + half)
      ..lineTo(cx - thick, cy + half)
      ..lineTo(cx - thick, cy + thick)
      ..lineTo(cx - half, cy + thick)
      ..lineTo(cx - half, cy - thick)
      ..lineTo(cx - thick, cy - thick)
      ..close();
    canvas.drawPath(path, p);
  }

  void _drawHeart(
      Canvas canvas, double cx, double cy, double s, Paint p, Paint fp) {
    final path = Path();
    path.moveTo(cx, cy + s * 0.4);
    path.cubicTo(
        cx - s, cy - s * 0.2, cx - s * 0.5, cy - s * 0.7, cx, cy - s * 0.3);
    path.cubicTo(
        cx + s * 0.5, cy - s * 0.7, cx + s, cy - s * 0.2, cx, cy + s * 0.4);
    path.close();
    canvas.drawPath(path, fp);
    canvas.drawPath(path, p);
  }

  void _drawPill(Canvas canvas, double cx, double cy, double s, Paint p) {
    final r = s * 0.22;
    final halfLen = s * 0.4;
    canvas.save();
    canvas.translate(cx, cy);
    canvas.rotate(math.pi / 4);
    final rrect = RRect.fromRectAndRadius(
        Rect.fromLTRB(-r, -halfLen, r, halfLen), Radius.circular(r));
    canvas.drawRRect(rrect, p);
    canvas.drawLine(Offset(-r, 0), Offset(r, 0), p);
    canvas.restore();
  }

  void _drawStethoscope(
      Canvas canvas, double cx, double cy, double s, Paint p) {
    final path = Path();
    path.moveTo(cx - s * 0.25, cy - s * 0.4);
    path.lineTo(cx - s * 0.25, cy - s * 0.1);
    path.quadraticBezierTo(
        cx - s * 0.25, cy + s * 0.15, cx, cy + s * 0.15);
    path.quadraticBezierTo(
        cx + s * 0.25, cy + s * 0.15, cx + s * 0.25, cy - s * 0.1);
    path.lineTo(cx + s * 0.25, cy - s * 0.4);
    canvas.drawPath(path, p);

    path.reset();
    path.moveTo(cx, cy + s * 0.15);
    path.lineTo(cx, cy + s * 0.35);
    canvas.drawPath(path, p);
    canvas.drawCircle(Offset(cx, cy + s * 0.45), s * 0.08, p);
  }

  void _drawDna(Canvas canvas, double cx, double cy, double s, Paint p) {
    final top = cy - s * 0.45;
    final bot = cy + s * 0.45;
    for (double t = top; t <= bot; t += 1.5) {
      final frac = (t - top) / (bot - top);
      final wave = math.sin(frac * math.pi * 2.5) * s * 0.25;
      canvas.drawCircle(Offset(cx + wave, t), 0.5, p);
      canvas.drawCircle(Offset(cx - wave, t), 0.5, p);
    }
    for (int i = 0; i < 4; i++) {
      final frac = (i + 0.5) / 4;
      final y = top + frac * (bot - top);
      final wave = math.sin(frac * math.pi * 2.5) * s * 0.25;
      canvas.drawLine(Offset(cx - wave, y), Offset(cx + wave, y), p);
    }
  }

  void _drawPulse(Canvas canvas, double cx, double cy, double s, Paint p) {
    final path = Path();
    final left = cx - s / 2;
    path.moveTo(left, cy);
    path.lineTo(left + s * 0.2, cy);
    path.lineTo(left + s * 0.3, cy - s * 0.35);
    path.lineTo(left + s * 0.45, cy + s * 0.25);
    path.lineTo(left + s * 0.55, cy - s * 0.15);
    path.lineTo(left + s * 0.65, cy);
    path.lineTo(left + s, cy);
    canvas.drawPath(path, p);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
