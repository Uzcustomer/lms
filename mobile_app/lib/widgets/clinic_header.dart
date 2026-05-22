import 'package:flutter/material.dart';
import '../config/theme.dart';

/// Shared "clinic-calm" palette used across the redesigned student screens.
class ClinicTheme {
  static const ink = Color(0xFF0F172A);
  static const muted = Color(0xFF64748B);
  static const faint = Color(0xFF94A3B8);
  static const teal = Color(0xFF0D9488);
  static const blue = Color(0xFF1E3A8A);
  static const green = Color(0xFF047857);
  static const line = Color(0xFFE2E8F0);
  static const bg = Color(0xFFFFFFFF);

  static Color inkOf(BuildContext c) =>
      Theme.of(c).brightness == Brightness.dark ? Colors.white : ink;
  static Color mutedOf(BuildContext c) => Theme.of(c).brightness == Brightness.dark
      ? AppTheme.darkTextSecondary
      : muted;
  static Color surfaceOf(BuildContext c) =>
      Theme.of(c).brightness == Brightness.dark ? AppTheme.darkCard : Colors.white;
  static Color dividerOf(BuildContext c) => Theme.of(c).brightness == Brightness.dark
      ? Colors.white.withOpacity(0.08)
      : line;
  static Color bgOf(BuildContext c) => Theme.of(c).brightness == Brightness.dark
      ? AppTheme.darkBackground
      : Colors.white;

  static List<BoxShadow> cardShadow = [
    BoxShadow(
      color: const Color(0xFF0F172A).withOpacity(0.14),
      blurRadius: 5,
      offset: const Offset(0, 2),
    ),
  ];
}

/// Soft-square 38×38 icon button used in clinical headers.
class ClinicIconButton extends StatelessWidget {
  final IconData icon;
  final VoidCallback onTap;
  const ClinicIconButton({super.key, required this.icon, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Container(
      width: 38,
      height: 38,
      decoration: BoxDecoration(
        color: isDark ? Colors.white.withOpacity(0.06) : const Color(0xFFF1F5F9),
        borderRadius: BorderRadius.circular(11),
      ),
      child: IconButton(
        padding: EdgeInsets.zero,
        icon: Icon(icon, color: ClinicTheme.inkOf(context), size: 18),
        onPressed: onTap,
      ),
    );
  }
}

/// White clinical header with a hairline bottom border, an optional back
/// button, a two-line title and trailing action widgets.
class ClinicHeader extends StatelessWidget {
  final String? overline;
  final String title;
  final VoidCallback? onBack;
  final List<Widget> actions;

  const ClinicHeader({
    super.key,
    this.overline,
    required this.title,
    this.onBack,
    this.actions = const [],
  });

  @override
  Widget build(BuildContext context) {
    final statusBarH = MediaQuery.of(context).padding.top;
    final ink = ClinicTheme.inkOf(context);
    final muted = ClinicTheme.mutedOf(context);

    return Container(
      padding: EdgeInsets.fromLTRB(14, statusBarH + 10, 14, 12),
      decoration: BoxDecoration(
        color: ClinicTheme.surfaceOf(context),
        border: Border(
          bottom: BorderSide(color: ClinicTheme.dividerOf(context), width: 1),
        ),
      ),
      child: Row(
        children: [
          if (onBack != null) ...[
            ClinicIconButton(icon: Icons.arrow_back_rounded, onTap: onBack!),
            const SizedBox(width: 11),
          ],
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (overline != null) ...[
                  Text(
                    overline!,
                    style: TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.w600,
                      letterSpacing: 0.5,
                      color: muted,
                    ),
                  ),
                  const SizedBox(height: 2),
                ],
                Text(
                  title,
                  style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: ink),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
          for (final a in actions) ...[const SizedBox(width: 8), a],
        ],
      ),
    );
  }
}

/// Wraps [child] with a looping diagonal "shine" sweep, clipped to a
/// rounded rectangle of [radius]. Use for hero / feature cards.
class ShinySweep extends StatefulWidget {
  final Widget child;
  final double radius;
  const ShinySweep({super.key, required this.child, this.radius = 18});

  @override
  State<ShinySweep> createState() => _ShinySweepState();
}

class _ShinySweepState extends State<ShinySweep>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 2800),
    )..repeat();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(widget.radius),
      child: Stack(
        children: [
          widget.child,
          Positioned.fill(
            child: IgnorePointer(
              child: AnimatedBuilder(
                animation: _controller,
                builder: (_, __) {
                  return LayoutBuilder(
                    builder: (_, c) {
                      final dx =
                          (-0.35 + 1.7 * _controller.value) * c.maxWidth;
                      return Stack(
                        children: [
                          Positioned(
                            left: dx,
                            top: -90,
                            child: Transform.rotate(
                              angle: 0.42,
                              child: Container(
                                width: 58,
                                height: 340,
                                decoration: BoxDecoration(
                                  gradient: LinearGradient(
                                    begin: Alignment.centerLeft,
                                    end: Alignment.centerRight,
                                    colors: [
                                      Colors.white.withOpacity(0),
                                      Colors.white.withOpacity(0.30),
                                      Colors.white.withOpacity(0),
                                    ],
                                  ),
                                ),
                              ),
                            ),
                          ),
                        ],
                      );
                    },
                  );
                },
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Just the looping diagonal shine band — drop into a Stack as a
/// `Positioned.fill` layer (the Stack itself should be clipped).
class ShineOverlay extends StatefulWidget {
  final double opacity;
  const ShineOverlay({super.key, this.opacity = 0.22});

  @override
  State<ShineOverlay> createState() => _ShineOverlayState();
}

class _ShineOverlayState extends State<ShineOverlay>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 3000),
    )..repeat();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: AnimatedBuilder(
        animation: _controller,
        builder: (_, __) => LayoutBuilder(
          builder: (_, c) {
            final dx = (-0.4 + 1.8 * _controller.value) * c.maxWidth;
            return Stack(
              children: [
                Positioned(
                  left: dx,
                  top: -220,
                  child: Transform.rotate(
                    angle: 0.42,
                    child: Container(
                      width: 72,
                      height: c.maxHeight + 440,
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          begin: Alignment.centerLeft,
                          end: Alignment.centerRight,
                          colors: [
                            Colors.white.withOpacity(0),
                            Colors.white.withOpacity(widget.opacity),
                            Colors.white.withOpacity(0),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
              ],
            );
          },
        ),
      ),
    );
  }
}
