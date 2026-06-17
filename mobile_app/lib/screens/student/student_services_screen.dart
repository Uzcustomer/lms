import 'package:flutter/material.dart';
import '../../l10n/app_localizations.dart';
import '../../utils/page_transitions.dart';
import '../../widgets/scale_tap.dart';
import '../../widgets/clinic_header.dart';
import 'absence_excuse_list_screen.dart';
import 'appeals_list_screen.dart';
import 'clubs_screen.dart';
import 'retake_applications_screen.dart';

class StudentServicesScreen extends StatelessWidget {
  const StudentServicesScreen({super.key});

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
        icon: Icons.school_outlined,
        title: 'Qayta o\'qish',
        subtitle: 'Qarzdor fanlar uchun ariza yuborish',
        color: const Color(0xFF22C55E),
        bgColor: const Color(0xFFEAFBF1),
        onTap: () => Navigator.push(
          context,
          SlideFadePageRoute(builder: (_) => const RetakeApplicationsScreen()),
        ),
      ),
    ];

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
                overline: 'FOYDALI',
                title: l.services,
                onBack: () => Navigator.pop(context),
              ),
              Expanded(
                child: GridView.builder(
                  padding: const EdgeInsets.fromLTRB(18, 14, 18, 28),
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 2,
                    crossAxisSpacing: 14,
                    mainAxisSpacing: 14,
                    childAspectRatio: 0.92,
                  ),
                  itemCount: services.length,
                  itemBuilder: (context, index) => _ServiceCard(item: services[index]),
                ),
              ),
            ],
          ),
        ],
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
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 16, 13, 13),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 58,
                    height: 58,
                    decoration: BoxDecoration(
                      color: item.bgColor,
                      borderRadius: BorderRadius.circular(18),
                    ),
                    child: Icon(item.icon, size: 30, color: item.color),
                  ),
                  const SizedBox(height: 18),
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
                  const Spacer(),
                  Align(
                    alignment: Alignment.bottomRight,
                    child: Container(
                      width: 34,
                      height: 34,
                      decoration: BoxDecoration(
                        color: item.bgColor,
                        shape: BoxShape.circle,
                      ),
                      child: Icon(
                        Icons.arrow_forward_rounded,
                        size: 19,
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
