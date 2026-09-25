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
        colorIndex: 0,
        onTap: () => Navigator.push(
          context,
          SlideFadePageRoute(builder: (_) => const AbsenceExcuseListScreen()),
        ),
      ),
      _ServiceItem(
        icon: Icons.groups_2_outlined,
        title: l.clubs,
        subtitle: l.clubsDesc,
        colorIndex: 1,
        onTap: () => Navigator.push(
          context,
          SlideFadePageRoute(builder: (_) => const ClubsScreen()),
        ),
      ),
      _ServiceItem(
        icon: Icons.gavel_rounded,
        title: l.appeal,
        subtitle: l.appealDesc,
        colorIndex: 2,
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
        colorIndex: 3,
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
        colorIndex: 4,
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
      backgroundColor: ClinicTheme.bgOf(context),
      body: Stack(
        children: [
          Column(
            children: [
              ClinicHeader(
                overline: l.useful.toUpperCase(),
                title: l.services,
                onBack: () => Navigator.pop(context),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(14, 10, 14, 2),
                child: _ServicesSearchField(
                  onChanged: (value) => setState(() => _query = value),
                ),
              ),
              Expanded(
                child: filteredServices.isEmpty
                    ? _EmptySearchState(query: _query)
                    : GridView.builder(
                        padding: const EdgeInsets.fromLTRB(14, 12, 14, 28),
                        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                          crossAxisCount: 2,
                          crossAxisSpacing: 12,
                          mainAxisSpacing: 12,
                          mainAxisExtent: 146,
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
        color: ClinicTheme.elevatedOf(context),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: ClinicTheme.dividerOf(context)),
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
                color: ClinicTheme.tintOf(context, const Color(0xFF3B82F6)),
                borderRadius: BorderRadius.circular(20),
              ),
              child: Icon(Icons.search_off_rounded, color: ClinicTheme.blueOf(context)),
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
  /// Position in the accent scheme's palette.
  final int colorIndex;
  final VoidCallback onTap;

  const _ServiceItem({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.colorIndex,
    required this.onTap,
  });
}

class _ServiceCard extends StatelessWidget {
  final _ServiceItem item;

  const _ServiceCard({required this.item});

  @override
  Widget build(BuildContext context) {
    // Same tile as the "Foydali" grid: solid colour block, title, subtitle.
    final color = ClinicTheme.tileOf(context, item.colorIndex);

    return ScaleTap(
      onTap: item.onTap,
      child: Container(
        decoration: BoxDecoration(
          color: ClinicTheme.surfaceOf(context),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: ClinicTheme.dividerOf(context), width: 1),
          boxShadow: ClinicTheme.cardShadowOf(context),
        ),
        child: Material(
          color: Colors.transparent,
          child: InkWell(
            onTap: item.onTap,
            borderRadius: BorderRadius.circular(16),
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Container(
                    width: 54,
                    height: 54,
                    decoration: BoxDecoration(
                      color: color,
                      borderRadius: BorderRadius.circular(14),
                      boxShadow: [
                        BoxShadow(
                          color: color.withValues(alpha: 0.35),
                          blurRadius: 8,
                          offset: const Offset(0, 3),
                        ),
                      ],
                    ),
                    child: Icon(item.icon, size: 27, color: Colors.white),
                  ),
                  const SizedBox(height: 10),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        item.title,
                        style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w800,
                          height: 1.25,
                          letterSpacing: -0.2,
                          color: ClinicTheme.inkOf(context),
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                      const SizedBox(height: 3),
                      Text(
                        item.subtitle,
                        style: TextStyle(
                            fontSize: 11, height: 1.35, color: ClinicTheme.mutedOf(context)),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
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
