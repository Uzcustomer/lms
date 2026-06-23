import 'package:flutter/material.dart';
import '../../l10n/app_localizations.dart';
import '../../utils/page_transitions.dart';
import '../../widgets/scale_tap.dart';
import '../../widgets/clinic_header.dart';
import 'absence_excuse_list_screen.dart';
import 'appeals_list_screen.dart';
import 'clubs_screen.dart';
import 'english_group_application_screen.dart';
import 'retake_applications_screen.dart';

class StudentServicesScreen extends StatefulWidget {
  const StudentServicesScreen({super.key});

  @override
  State<StudentServicesScreen> createState() => _StudentServicesScreenState();
}

class _StudentServicesScreenState extends State<StudentServicesScreen> {
  String _query = '';

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);

    final services = [
      _ServiceItem(
        icon: Icons.description_outlined,
        title: l.absenceExcuse,
        subtitle: l.absenceExcuseDesc,
        color: const Color(0xFF3B82F6),
        bgColor: const Color(0xFFEAF2FF),
        onTap: () => Navigator.push(
          context,
          SlideFadePageRoute(builder: (_) => const AbsenceExcuseListScreen()),
        ),
      ),
      _ServiceItem(
        icon: Icons.groups_2_outlined,
        title: l.clubs,
        subtitle: l.clubsDesc,
        color: const Color(0xFFA855F7),
        bgColor: const Color(0xFFF3E8FF),
        onTap: () => Navigator.push(
          context,
          SlideFadePageRoute(builder: (_) => const ClubsScreen()),
        ),
      ),
      _ServiceItem(
        icon: Icons.gavel_rounded,
        title: l.appeal,
        subtitle: l.appealDesc,
        color: const Color(0xFFF97316),
        bgColor: const Color(0xFFFFF1E8),
        onTap: () => Navigator.push(
          context,
          SlideFadePageRoute(builder: (_) => const AppealsListScreen()),
        ),
      ),
      _ServiceItem(
        icon: Icons.translate_rounded,
        title: l.pick(
          uz: 'Ingliz tili guruhi',
          ru: 'Группа английского языка',
          en: 'English group',
        ),
        subtitle: l.pick(
          uz: 'O\'tish uchun ariza yuborish',
          ru: 'Подать заявление на перевод',
          en: 'Apply to transfer',
        ),
        color: const Color(0xFF10B981),
        bgColor: const Color(0xFFE8FBF4),
        onTap: () => Navigator.push(
          context,
          SlideFadePageRoute(
            builder: (_) => const EnglishGroupApplicationScreen(),
          ),
        ),
      ),
      _ServiceItem(
        icon: Icons.school_outlined,
        title: l.pick(
          uz: 'Qayta o\'qish',
          ru: 'Пересдача',
          en: 'Retake',
        ),
        subtitle: l.pick(
          uz: 'Qarzdor fanlar uchun ariza yuborish',
          ru: 'Подать заявку по предметам с задолженностью',
          en: 'Apply for subjects with academic debt',
        ),
        color: const Color(0xFF22C55E),
        bgColor: const Color(0xFFEAFBF1),
        onTap: () => Navigator.push(
          context,
          SlideFadePageRoute(builder: (_) => const RetakeApplicationsScreen()),
        ),
      ),
    ];
    final query = _query.trim().toLowerCase();
    final filteredServices = query.isEmpty
        ? services
        : services.where((service) {
            final haystack = '${service.title} ${service.subtitle}'.toLowerCase();
            return haystack.contains(query);
          }).toList();

    return Scaffold(
      backgroundColor: const Color(0xFFF6F8FC),
      body: Stack(
        children: [
          Positioned(
            top: -92,
            right: -56,
            child: Container(
              width: 260,
              height: 260,
              decoration: const BoxDecoration(
                shape: BoxShape.circle,
                color: Color(0xFFEFF3FF),
              ),
            ),
          ),
          Positioned(
            bottom: -110,
            left: -88,
            child: Container(
              width: 230,
              height: 230,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: const Color(0xFFEAFBF1).withAlpha(180),
              ),
            ),
          ),
          Column(
            children: [
              ClinicHeader(
                overline: l.useful.toUpperCase(),
                title: l.services,
                onBack: () => Navigator.pop(context),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(18, 10, 18, 2),
                child: _ServicesSearchField(
                  onChanged: (value) => setState(() => _query = value),
                ),
              ),
              Expanded(
                child: filteredServices.isEmpty
                    ? _EmptySearchState(query: _query)
                    : GridView.builder(
                        padding: const EdgeInsets.fromLTRB(18, 12, 18, 28),
                        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                          crossAxisCount: 2,
                          crossAxisSpacing: 14,
                          mainAxisSpacing: 14,
                          childAspectRatio: 1.06,
                        ),
                        itemCount: filteredServices.length,
                        itemBuilder: (context, index) =>
                            _ServiceCard(item: filteredServices[index]),
                      ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _ServicesSearchField extends StatelessWidget {
  final ValueChanged<String> onChanged;

  const _ServicesSearchField({required this.onChanged});

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Container(
      height: 52,
      decoration: BoxDecoration(
        color: const Color(0xFFF0F3F9),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFE5EAF4)),
      ),
      child: TextField(
        onChanged: onChanged,
        textInputAction: TextInputAction.search,
        style: TextStyle(
          color: ClinicTheme.inkOf(context),
          fontSize: 14,
          fontWeight: FontWeight.w700,
        ),
        decoration: InputDecoration(
          border: InputBorder.none,
          prefixIcon: Icon(
            Icons.search_rounded,
            color: ClinicTheme.mutedOf(context),
            size: 23,
          ),
          hintText: l.pick(
            uz: 'Xizmatni qidiring...',
            ru: 'Найти услугу...',
            en: 'Search services...',
          ),
          hintStyle: TextStyle(
            color: ClinicTheme.mutedOf(context),
            fontSize: 13.5,
            fontWeight: FontWeight.w600,
          ),
          contentPadding: const EdgeInsets.symmetric(vertical: 15),
        ),
      ),
    );
  }
}

class _EmptySearchState extends StatelessWidget {
  final String query;

  const _EmptySearchState({required this.query});

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(28),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 58,
              height: 58,
              decoration: BoxDecoration(
                color: const Color(0xFFEAF2FF),
                borderRadius: BorderRadius.circular(20),
              ),
              child: const Icon(Icons.search_off_rounded, color: Color(0xFF3B82F6)),
            ),
            const SizedBox(height: 12),
            Text(
              l.pick(
                uz: '"$query" bo\'yicha xizmat topilmadi',
                ru: 'Услуга по запросу "$query" не найдена',
                en: 'No service found for "$query"',
              ),
              textAlign: TextAlign.center,
              style: TextStyle(
                color: ClinicTheme.inkOf(context),
                fontSize: 14,
                fontWeight: FontWeight.w900,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ServiceItem {
  final IconData icon;
  final String title;
  final String subtitle;
  final Color color;
  final Color bgColor;
  final VoidCallback onTap;

  const _ServiceItem({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.color,
    required this.bgColor,
    required this.onTap,
  });
}

class _ServiceCard extends StatelessWidget {
  final _ServiceItem item;

  const _ServiceCard({required this.item});

  @override
  Widget build(BuildContext context) {
    final textColor = ClinicTheme.inkOf(context);
    final mutedColor = ClinicTheme.mutedOf(context);

    return ScaleTap(
      onTap: item.onTap,
      child: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(24),
          border: Border.all(color: const Color(0xFFE8EDF6)),
          boxShadow: [
            BoxShadow(
              color: const Color(0xFF0F172A).withAlpha(16),
              blurRadius: 22,
              offset: const Offset(0, 10),
            ),
          ],
        ),
        child: Material(
          color: Colors.transparent,
          child: InkWell(
            onTap: item.onTap,
            borderRadius: BorderRadius.circular(24),
            child: Stack(
              children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(15, 15, 14, 48),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Container(
                        width: 52,
                        height: 52,
                        decoration: BoxDecoration(
                          color: item.bgColor,
                          borderRadius: BorderRadius.circular(17),
                        ),
                        child: Icon(item.icon, size: 28, color: item.color),
                      ),
                      const SizedBox(height: 14),
                      Text(
                        item.title,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          fontSize: 13.5,
                          height: 1.18,
                          fontWeight: FontWeight.w900,
                          color: textColor,
                        ),
                      ),
                      const SizedBox(height: 7),
                      Text(
                        item.subtitle,
                        maxLines: 3,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          fontSize: 11,
                          height: 1.35,
                          color: mutedColor,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                ),
                Positioned(
                  right: 12,
                  bottom: 12,
                  child: Container(
                    width: 32,
                    height: 32,
                    decoration: BoxDecoration(
                      color: item.bgColor,
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
    );
  }
}
