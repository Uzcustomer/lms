import 'package:flutter/material.dart';
import '../../config/theme.dart';
import '../../l10n/app_localizations.dart';
import '../../utils/page_transitions.dart';
import '../../widgets/scale_tap.dart';
import '../../widgets/settings_sheet.dart';
import '../../widgets/notification_bell.dart';
import 'student_services_screen.dart';
import 'student_exam_schedule_screen.dart';
import 'attendance_stats_screen.dart';
import 'gpa_calculator_screen.dart';
import 'student_rating_screen.dart';
import 'chat_contacts_screen.dart';
import 'library_webview_screen.dart';
import 'ai_chat_screen.dart';
import 'student_home_screen.dart';

// ── Clinic-calm palette ──────────────────────────────
const _calmInk = Color(0xFF0F172A);
const _calmMuted = Color(0xFF64748B);
const _calmLine = Color(0xFFE2E8F0);
const _heroTeal = Color(0xFF0F766E);
const _heroNavy = Color(0xFF1E3A8A);
const _calmBg = Color(0xFFF4F7FB);
const _calmPanel = Color(0xFFF8FBFF);

class StudentUsefulScreen extends StatelessWidget {
  const StudentUsefulScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final ink = isDark ? Colors.white : _calmInk;
    final muted = isDark ? AppTheme.darkTextSecondary : _calmMuted;
    final surface = isDark ? AppTheme.darkCard : Colors.white;
    final divider = isDark ? Colors.white.withOpacity(0.08) : _calmLine;
    final statusBarH = MediaQuery.of(context).padding.top;

    final services = [
      _ServiceCard(
        icon: Icons.auto_awesome,
        title: l.pick(uz: 'AI Yordamchi', ru: 'AI помощник', en: 'AI Assistant'),
        subtitle: l.pick(
          uz: 'Gemini AI bilan savol-javob',
          ru: 'Вопросы и ответы с Gemini AI',
          en: 'Q&A with Gemini AI',
        ),
        color: const Color(0xFF7B1FA2),
        screen: const AiChatScreen(),
      ),
      _ServiceCard(
        icon: Icons.calculate_outlined,
        title: l.pick(uz: 'GPA Kalkulyator', ru: 'GPA калькулятор', en: 'GPA Calculator'),
        subtitle: l.pick(
          uz: 'GPA hisoblash va prognoz',
          ru: 'Расчет и прогноз GPA',
          en: 'Calculate and forecast GPA',
        ),
        color: const Color(0xFF0F766E),
        screen: const GpaCalculatorScreen(),
      ),
      _ServiceCard(
        icon: Icons.bar_chart_rounded,
        title: l.pick(
          uz: 'Davomat statistikasi',
          ru: 'Статистика посещаемости',
          en: 'Attendance statistics',
        ),
        subtitle: l.pick(
          uz: 'Davomat va baholar jadvali',
          ru: 'Посещаемость и оценки',
          en: 'Attendance and grades table',
        ),
        color: const Color(0xFF047857),
        screen: const AttendanceStatsScreen(),
      ),
      _ServiceCard(
        icon: Icons.emoji_events_outlined,
        title: l.pick(
          uz: 'Talabalar reytingi',
          ru: 'Рейтинг студентов',
          en: 'Student ranking',
        ),
        subtitle: l.pick(
          uz: 'Guruh va yo\'nalish reytingi',
          ru: 'Рейтинг группы и направления',
          en: 'Group and major ranking',
        ),
        color: const Color(0xFF6D28D9),
        screen: const StudentRatingScreen(),
      ),
      _ServiceCard(
        icon: Icons.chat_bubble_outline_rounded,
        title: l.pick(uz: 'Guruh chati', ru: 'Групповой чат', en: 'Group chat'),
        subtitle: l.pick(
          uz: 'Guruh a\'zolari bilan yozishing',
          ru: 'Переписка с участниками группы',
          en: 'Chat with your group members',
        ),
        color: const Color(0xFF0369A1),
        screen: const ChatContactsScreen(),
      ),
      _ServiceCard(
        icon: Icons.grid_view_rounded,
        title: l.pick(uz: 'Imtihon sanalari', ru: 'Даты экзаменов', en: 'Exam dates'),
        subtitle: l.pick(
          uz: 'OSKI va Test kunlari',
          ru: 'Дни OSCE и тестов',
          en: 'OSCE and test dates',
        ),
        color: const Color(0xFF4338CA),
        screen: const ExamScheduleScreen(),
      ),
      _ServiceCard(
        icon: Icons.menu_book_rounded,
        title: l.pick(uz: 'Kutubxona', ru: 'Библиотека', en: 'Library'),
        subtitle: l.pick(
          uz: 'Elektron darsliklar',
          ru: 'Электронные учебники',
          en: 'Digital textbooks',
        ),
        color: const Color(0xFFC2410C),
        screen: const LibraryWebViewScreen(),
      ),
    ];

    return Scaffold(
      backgroundColor: isDark ? AppTheme.darkBackground : _calmBg,
      body: Stack(
        children: [
          if (!isDark) ...[
            Positioned(
              top: -80,
              right: -30,
              child: Container(
                width: 220,
                height: 220,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  gradient: RadialGradient(
                    colors: [
                      const Color(0xFF99F6E4).withOpacity(0.24),
                      Colors.transparent,
                    ],
                  ),
                ),
              ),
            ),
            Positioned(
              top: 110,
              left: -50,
              child: Container(
                width: 180,
                height: 180,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  gradient: RadialGradient(
                    colors: [
                      const Color(0xFFBFDBFE).withOpacity(0.22),
                      Colors.transparent,
                    ],
                  ),
                ),
              ),
            ),
          ],
          SingleChildScrollView(
            padding: EdgeInsets.zero,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  padding: EdgeInsets.fromLTRB(14, statusBarH + 10, 14, 12),
                  decoration: BoxDecoration(
                    color: isDark ? surface : Colors.white.withOpacity(0.86),
                    border: Border(bottom: BorderSide(color: divider, width: 1)),
                    boxShadow: isDark
                        ? null
                        : [
                            BoxShadow(
                              color: const Color(0xFF0F172A).withOpacity(0.04),
                              blurRadius: 14,
                              offset: const Offset(0, 6),
                            ),
                          ],
                  ),
                  child: Row(
                    children: [
                      _HeaderIconButton(
                        isDark: isDark,
                        child: IconButton(
                          padding: EdgeInsets.zero,
                          icon: Icon(Icons.arrow_back_rounded, color: ink, size: 20),
                          onPressed: () => StudentHomeScreen.switchToHome(context),
                        ),
                      ),
                      const SizedBox(width: 11),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              l.useful.toUpperCase(),
                              style: TextStyle(
                                fontSize: 10,
                                fontWeight: FontWeight.w700,
                                letterSpacing: 0.7,
                                color: muted,
                              ),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              l.useful,
                              style: TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.w800,
                                color: ink,
                              ),
                            ),
                          ],
                        ),
                      ),
                      _HeaderIconButton(
                        isDark: isDark,
                        child: NotificationBell(iconColor: ink, iconSize: 18),
                      ),
                      const SizedBox(width: 8),
                      _HeaderIconButton(
                        isDark: isDark,
                        child: IconButton(
                          padding: EdgeInsets.zero,
                          icon: Icon(Icons.settings_outlined, color: ink, size: 18),
                          onPressed: () => showSettingsSheet(context),
                        ),
                      ),
                    ],
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(14, 14, 14, 100),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      ScaleTap(
                        onTap: () => Navigator.push(
                          context,
                          SlideFadePageRoute(builder: (_) => const StudentServicesScreen()),
                        ),
                        child: const _CalmHero(),
                      ),
                      const SizedBox(height: 14),
                      Text(
                        l.pick(
                          uz: 'Tezkor xizmatlar',
                          ru: 'Быстрые сервисы',
                          en: 'Quick services',
                        ),
                        style: TextStyle(
                          color: ink,
                          fontSize: 17,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        l.pick(
                          uz: 'Talabalarga kerak bo‘ladigan asosiy bo‘limlar bir joyda.',
                          ru: 'Основные разделы для студентов собраны в одном месте.',
                          en: 'The main student sections are collected in one place.',
                        ),
                        style: TextStyle(
                          color: muted,
                          fontSize: 12.5,
                          height: 1.45,
                        ),
                      ),
                      const SizedBox(height: 12),
                      GridView.builder(
                        shrinkWrap: true,
                        padding: EdgeInsets.zero,
                        physics: const NeverScrollableScrollPhysics(),
                        itemCount: services.length,
                        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                          crossAxisCount: 2,
                          crossAxisSpacing: 12,
                          mainAxisSpacing: 12,
                          mainAxisExtent: 168,
                        ),
                        itemBuilder: (_, i) => _ServiceTile(
                          item: services[i],
                          ink: ink,
                          muted: muted,
                          surface: surface,
                          divider: divider,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _HeaderIconButton extends StatelessWidget {
  final Widget child;
  final bool isDark;
  const _HeaderIconButton({required this.child, required this.isDark});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 38,
      height: 38,
      decoration: BoxDecoration(
        color: isDark ? Colors.white.withOpacity(0.06) : const Color(0xFFF1F5F9),
        borderRadius: BorderRadius.circular(11),
      ),
      child: child,
    );
  }
}

// ---------- Shimmering hero card ----------
class _ShinyHero extends StatefulWidget {
  const _ShinyHero();

  @override
  State<_ShinyHero> createState() => _ShinyHeroState();
}

class _ShinyHeroState extends State<_ShinyHero> with SingleTickerProviderStateMixin {
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
    final l = AppLocalizations.of(context);
    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: _heroTeal.withOpacity(0.35),
            blurRadius: 16,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(20),
        child: Stack(
          children: [
            // Gradient content
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(18),
              decoration: const BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: [_heroTeal, _heroNavy],
                ),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
                    decoration: BoxDecoration(
                      color: Colors.white.withOpacity(0.18),
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: Text(
                      l.services.toUpperCase(),
                      style: const TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.w800,
                        letterSpacing: 1.2,
                        color: Colors.white,
                      ),
                    ),
                  ),
                  const SizedBox(height: 10),
                  Text(
                    l.pick(
                      uz: 'Elektron xizmatlar',
                      ru: 'Электронные услуги',
                      en: 'Digital services',
                    ),
                    style: const TextStyle(
                      fontSize: 22,
                      fontWeight: FontWeight.w900,
                      letterSpacing: -0.5,
                      color: Colors.white,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Sababli ariza · Ma\'lumotnoma · Xizmatlar',
                    style: TextStyle(fontSize: 12.5, color: Colors.white.withOpacity(0.9)),
                  ),
                  const SizedBox(height: 14),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 9),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(11),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(l.signIn,
                            style: const TextStyle(
                                fontSize: 12.5,
                                fontWeight: FontWeight.w800,
                                color: _heroTeal)),
                        const SizedBox(width: 6),
                        const Icon(Icons.chevron_right_rounded, size: 16, color: _heroTeal),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            // Sweeping shine
            Positioned.fill(
              child: IgnorePointer(
                child: AnimatedBuilder(
                  animation: _controller,
                  builder: (_, __) {
                    return LayoutBuilder(
                      builder: (_, c) {
                        final dx = (-0.35 + 1.7 * _controller.value) * c.maxWidth;
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
                                        Colors.white.withOpacity(0.32),
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
      ),
    );
  }
}

class _CalmHero extends StatelessWidget {
  const _CalmHero();

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: _calmPanel,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: const Color(0xFFD8E4F0)),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0F172A).withOpacity(0.06),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Stack(
        children: [
          Positioned(
            right: -8,
            top: -12,
            child: Container(
              width: 90,
              height: 90,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: const Color(0xFFCCFBF1).withOpacity(0.75),
              ),
            ),
          ),
          Positioned(
            right: 38,
            bottom: -26,
            child: Container(
              width: 74,
              height: 74,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: const Color(0xFFDBEAFE).withOpacity(0.8),
              ),
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                decoration: BoxDecoration(
                  color: const Color(0xFFE6FFFA),
                  borderRadius: BorderRadius.circular(999),
                ),
                child: Text(
                  l.services.toUpperCase(),
                  style: const TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.w800,
                    letterSpacing: 1,
                    color: _heroTeal,
                  ),
                ),
              ),
              const SizedBox(height: 12),
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 54,
                    height: 54,
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(18),
                      gradient: const LinearGradient(
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                        colors: [_heroTeal, _heroNavy],
                      ),
                    ),
                    child: const Icon(Icons.widgets_rounded, color: Colors.white, size: 26),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          l.pick(
                            uz: 'Elektron xizmatlar',
                            ru: 'Электронные услуги',
                            en: 'Digital services',
                          ),
                          style: const TextStyle(
                            fontSize: 22,
                            fontWeight: FontWeight.w900,
                            letterSpacing: -0.4,
                            color: _calmInk,
                          ),
                        ),
                        const SizedBox(height: 5),
                        Text(
                          l.pick(
                            uz: 'Arizalar, ma’lumotnomalar va foydali xizmatlar bir sahifada.',
                            ru: 'Заявки, справки и полезные сервисы собраны на одной странице.',
                            en: 'Applications, certificates, and helpful services in one place.',
                          ),
                          style: const TextStyle(
                            fontSize: 12.5,
                            height: 1.45,
                            color: _calmMuted,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 11),
                decoration: BoxDecoration(
                  color: Colors.white.withOpacity(0.82),
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: const Color(0xFFD9E7F4)),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.auto_awesome_rounded, size: 18, color: _heroTeal),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        l.pick(
                          uz: 'Ariza yuborish, ko‘rish va boshqarish uchun bosing',
                          ru: 'Нажмите, чтобы отправлять, просматривать и управлять заявками',
                          en: 'Tap to submit, view, and manage your applications',
                        ),
                        style: const TextStyle(
                          fontSize: 12.5,
                          fontWeight: FontWeight.w700,
                          color: _heroTeal,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    const Icon(Icons.chevron_right_rounded, size: 18, color: _heroTeal),
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

// ---------- Service tile ----------
class _ServiceTile extends StatelessWidget {
  final _ServiceCard item;
  final Color ink;
  final Color muted;
  final Color surface;
  final Color divider;
  const _ServiceTile({
    required this.item,
    required this.ink,
    required this.muted,
    required this.surface,
    required this.divider,
  });

  @override
  Widget build(BuildContext context) {
    return ScaleTap(
      onTap: () => Navigator.push(
        context,
        SlideFadePageRoute(builder: (_) => item.screen),
      ),
      child: Container(
        decoration: BoxDecoration(
          color: surface,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: divider, width: 1),
          boxShadow: [
            BoxShadow(
              color: const Color(0xFF0F172A).withOpacity(0.08),
              blurRadius: 18,
              offset: const Offset(0, 8),
            ),
          ],
        ),
        child: Material(
          color: Colors.transparent,
          child: InkWell(
            onTap: () => Navigator.push(
              context,
              SlideFadePageRoute(builder: (_) => item.screen),
            ),
            borderRadius: BorderRadius.circular(20),
            child: Padding(
              padding: const EdgeInsets.fromLTRB(14, 14, 14, 12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Container(
                    width: 52,
                    height: 52,
                    decoration: BoxDecoration(
                      color: item.color.withOpacity(0.12),
                      borderRadius: BorderRadius.circular(16),
                    ),
                    child: Icon(item.icon, size: 24, color: item.color),
                  ),
                  const SizedBox(height: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          item.title,
                          style: TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w800,
                            height: 1.25,
                            letterSpacing: -0.2,
                            color: ink,
                          ),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                        ),
                        const SizedBox(height: 4),
                        Text(
                          item.subtitle,
                          style: TextStyle(fontSize: 11.5, height: 1.38, color: muted),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ],
                    ),
                  ),
                  Align(
                    alignment: Alignment.bottomRight,
                    child: Container(
                      width: 34,
                      height: 34,
                      decoration: BoxDecoration(
                        color: item.color.withOpacity(0.10),
                        shape: BoxShape.circle,
                      ),
                      child: Icon(
                        Icons.arrow_forward_rounded,
                        size: 18,
                        color: item.color,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _ServiceCard {
  final IconData icon;
  final String title;
  final String subtitle;
  final Color color;
  final Widget screen;

  const _ServiceCard({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.color,
    required this.screen,
  });
}
