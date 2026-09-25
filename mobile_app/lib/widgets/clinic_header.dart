import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../config/accent_themes.dart';
import '../config/theme.dart';
import '../providers/settings_provider.dart';

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

  static bool isDark(BuildContext c) => Theme.of(c).brightness == Brightness.dark;

  /// Pick a colour by brightness — the one helper every other getter uses.
  static Color tone(BuildContext c, Color light, Color dark) => isDark(c) ? dark : light;

  // Surfaces and text
  static Color inkOf(BuildContext c) => tone(c, ink, AppTheme.darkTextPrimary);
  static Color mutedOf(BuildContext c) => tone(c, muted, AppTheme.darkTextSecondary);
  static Color faintOf(BuildContext c) => tone(c, faint, AppTheme.darkTextFaint);
  static Color surfaceOf(BuildContext c) => tone(c, Colors.white, AppTheme.darkCard);
  /// Soft fill for chips, icon buttons, secondary rows.
  static Color elevatedOf(BuildContext c) => tone(c, const Color(0xFFF1F5F9), AppTheme.darkElevated);
  static Color dividerOf(BuildContext c) => tone(c, line, AppTheme.darkDivider);
  static Color bgOf(BuildContext c) => tone(c, Colors.white, AppTheme.darkBackground);

  // Accents — the brand colours, lifted for dark so they read on navy.
  static Color tealOf(BuildContext c) => tone(c, teal, const Color(0xFF2DD4BF));
  static Color blueOf(BuildContext c) => tone(c, blue, AppTheme.darkAccent);
  static Color greenOf(BuildContext c) => tone(c, green, AppTheme.darkSuccess);
  /// Same accents for *fills* that carry white text: a step deeper than the
  /// foreground shade in dark, so the white stays legible on them.
  static Color tealFillOf(BuildContext c) => tone(c, teal, const Color(0xFF14B8A6));
  static Color greenFillOf(BuildContext c) => tone(c, green, const Color(0xFF10B981));
  static Color redOf(BuildContext c) => tone(c, const Color(0xFFBE123C), AppTheme.darkError);
  static Color amberOf(BuildContext c) => tone(c, const Color(0xFFB45309), AppTheme.darkWarning);
  static Color violetOf(BuildContext c) => tone(c, const Color(0xFF6D28D9), const Color(0xFFA78BFA));

  /// Pale tint behind a status label: washed in light, translucent in dark.
  static Color tintOf(BuildContext c, Color base) =>
      base.withValues(alpha: isDark(c) ? 0.18 : 0.10);

  // ── The scheme picked in Settings ────────────────────────────────
  /// Read through the provider so the caller *depends* on it and is rebuilt
  /// when the student picks another scheme. A plain static would leave
  /// const widgets and off-screen tabs showing the old colour until
  /// something else happened to rebuild them.
  static AccentTheme accent(BuildContext c) {
    try {
      return Provider.of<SettingsProvider>(c).accent;
    } on ProviderNotFoundException {
      return AccentThemes.current;
    }
  }

  /// Hero cards (gradient panels with the shine sweep) and their glow.
  static List<Color> heroGradientOf(BuildContext c) => accent(c).gradient;
  static Color heroGlowOf(BuildContext c) => accent(c).start;

  /// Interactive colour: active tab, links, selected chips.
  static Color primaryOf(BuildContext c) => accent(c).primaryOf(isDark(c));

  /// Colour for tile [i] of a grid, from the scheme's palette.
  static Color tileOf(BuildContext c, int i) => accent(c).tile(i);

  /// A border that visibly separates a card from the page, unlike the
  /// hairline [dividerOf].
  static Color strongBorderOf(BuildContext c) =>
      tone(c, const Color(0xFFCBD5E1), AppTheme.darkBorderColor);

  static List<BoxShadow> cardShadow = [
    BoxShadow(
      color: const Color(0xFF0F172A).withValues(alpha: 0.14),
      blurRadius: 5,
      offset: const Offset(0, 2),
    ),
  ];

  /// Shadows read as smudges on a dark ground — none there.
  static List<BoxShadow> cardShadowOf(BuildContext c) => isDark(c) ? const [] : cardShadow;
}

/// Soft-square 38×38 icon button used in clinical headers.
///
/// [onHeader] styles it for the tinted [ClinicHeader] - a translucent
/// white well with a white glyph - instead of the page surface.
class ClinicIconButton extends StatelessWidget {
  final IconData icon;
  final VoidCallback onTap;
  final bool onHeader;
  const ClinicIconButton({
    super.key,
    required this.icon,
    required this.onTap,
    this.onHeader = false,
  });

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Container(
      width: 38,
      height: 38,
      decoration: BoxDecoration(
        color: onHeader
            ? Colors.white.withValues(alpha: 0.18)
            : (isDark ? AppTheme.darkElevated : const Color(0xFFF1F5F9)),
        borderRadius: BorderRadius.circular(11),
      ),
      child: IconButton(
        padding: EdgeInsets.zero,
        icon: Icon(icon, color: onHeader ? Colors.white : ClinicTheme.inkOf(context), size: 18),
        onPressed: onTap,
      ),
    );
  }
}

/// Page header painted in the colour scheme the student picked, with an
/// optional back button, a two-line title and trailing action widgets.
/// Everything on it is white, so pass [onHeader] to any icon button and
/// bell placed in [actions].
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
    const ink = Colors.white;
    final muted = Colors.white.withValues(alpha: 0.75);

    return Container(
      padding: EdgeInsets.fromLTRB(14, statusBarH + 10, 14, 12),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: ClinicTheme.heroGradientOf(context),
        ),
      ),
      child: Row(
        children: [
          if (onBack != null) ...[
            ClinicIconButton(
              icon: Icons.arrow_back_rounded,
              onTap: onBack!,
              onHeader: true,
            ),
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
                            top: -200,
                            child: Transform.rotate(
                              angle: 0.42,
                              child: Container(
                                width: 58,
                                height: c.maxHeight + 400,
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

/// An avatar wrapped with a looping "shine" — expanding rings around it.
class AvatarHalo extends StatefulWidget {
  final Widget child;
  final double size;
  const AvatarHalo({super.key, required this.child, required this.size});

  @override
  State<AvatarHalo> createState() => _AvatarHaloState();
}

class _AvatarHaloState extends State<AvatarHalo>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 2400),
    )..repeat();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final box = widget.size + 36;
    return SizedBox(
      width: box,
      height: box,
      child: AnimatedBuilder(
        animation: _controller,
        builder: (_, __) => Stack(
          alignment: Alignment.center,
          children: [
            CustomPaint(
              size: Size(box, box),
              painter: _HaloPainter(_controller.value, widget.size / 2),
            ),
            widget.child,
          ],
        ),
      ),
    );
  }
}

class _HaloPainter extends CustomPainter {
  final double progress;
  final double avatarRadius;
  const _HaloPainter(this.progress, this.avatarRadius);

  @override
  void paint(Canvas canvas, Size size) {
    final center = Offset(size.width / 2, size.height / 2);
    const count = 3;
    for (int i = 0; i < count; i++) {
      final t = (progress + i / count) % 1.0;
      final opacity = (1 - t) * 0.55;
      if (opacity <= 0) continue;
      canvas.drawCircle(
        center,
        avatarRadius + 3 + t * 15,
        Paint()
          ..color = Colors.white.withOpacity(opacity)
          ..style = PaintingStyle.stroke
          ..strokeWidth = 2,
      );
    }
  }

  @override
  bool shouldRepaint(_HaloPainter old) => old.progress != progress;
}
