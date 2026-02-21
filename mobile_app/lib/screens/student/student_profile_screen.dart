import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/theme.dart';
import '../../config/api_config.dart';
import '../../providers/auth_provider.dart';
import '../../providers/student_provider.dart';
import '../../l10n/app_localizations.dart';
import '../../widgets/loading_widget.dart';

class StudentProfileScreen extends StatefulWidget {
  const StudentProfileScreen({super.key});

  @override
  State<StudentProfileScreen> createState() => _StudentProfileScreenState();
}

class _StudentProfileScreenState extends State<StudentProfileScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<StudentProvider>().loadProfile();
    });
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bgColor = isDark ? AppTheme.darkBackground : AppTheme.backgroundColor;

    return Scaffold(
      backgroundColor: bgColor,
      appBar: AppBar(
        title: Text(l.profile),
        centerTitle: true,
        leading: Navigator.canPop(context)
            ? IconButton(
                icon: const Icon(Icons.arrow_back),
                onPressed: () => Navigator.pop(context),
              )
            : const Padding(
                padding: EdgeInsets.all(12),
                child: Icon(Icons.account_balance, size: 28),
              ),
        actions: [
          IconButton(
            icon: const Icon(Icons.notifications_outlined),
            onPressed: () {},
          ),
          IconButton(
            icon: const Icon(Icons.logout),
            onPressed: () => _showLogoutDialog(context),
          ),
        ],
      ),
      body: Consumer<StudentProvider>(
        builder: (context, provider, _) {
          if (provider.isLoading && provider.profile == null) {
            return const LoadingWidget();
          }

          final profile = provider.profile;
          if (profile == null) {
            return Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(provider.error ?? l.profileNotFound),
                  const SizedBox(height: 16),
                  ElevatedButton(
                    onPressed: () => provider.loadProfile(),
                    child: Text(l.reload),
                  ),
                ],
              ),
            );
          }

          return RefreshIndicator(
            onRefresh: () => provider.loadProfile(),
            child: SingleChildScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
              child: Column(
                children: [
                  _buildProfileCard(context, profile),
                  const SizedBox(height: 16),
                  _buildPersonalInfo(context, profile),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _buildProfileCard(BuildContext context, Map<String, dynamic> profile) {
    final l = AppLocalizations.of(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final fullName = profile['full_name']?.toString() ?? '';
    final studentId = profile['student_id_number']?.toString() ?? '';
    final faculty = profile['department_name']?.toString() ?? '';
    final major = profile['specialty_name']?.toString() ?? '';
    final rawImage = profile['image']?.toString();
    final photoUrl = _buildImageUrl(rawImage);
    final cardColor = isDark ? AppTheme.darkCard : Colors.white;
    final textColor = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;
    final subTextColor = isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;

    return Card(
      elevation: 0,
      color: cardColor,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 24, horizontal: 16),
        child: Column(
          children: [
            Container(
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(color: AppTheme.primaryColor.withAlpha(50), width: 3),
              ),
              child: CircleAvatar(
                radius: 52,
                backgroundColor: AppTheme.primaryColor.withAlpha(30),
                backgroundImage:
                    photoUrl != null && photoUrl.isNotEmpty ? NetworkImage(photoUrl) : null,
                child: photoUrl == null || photoUrl.isEmpty
                    ? Text(
                        _getInitials(fullName),
                        style: const TextStyle(
                          fontSize: 32,
                          fontWeight: FontWeight.bold,
                          color: AppTheme.primaryColor,
                        ),
                      )
                    : null,
              ),
            ),
            const SizedBox(height: 16),
            Text(
              fullName,
              style: Theme.of(context).textTheme.titleLarge?.copyWith(
                    fontWeight: FontWeight.bold,
                    color: textColor,
                  ),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 6),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
              decoration: BoxDecoration(
                color: AppTheme.primaryColor.withAlpha(isDark ? 40 : 20),
                borderRadius: BorderRadius.circular(20),
              ),
              child: Text(
                studentId,
                style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: AppTheme.primaryColor,
                ),
              ),
            ),
            const SizedBox(height: 16),
            _buildInfoRow(Icons.account_balance, l.faculty, faculty, subTextColor, textColor),
            const SizedBox(height: 8),
            _buildInfoRow(Icons.school, l.direction, major, subTextColor, textColor),
          ],
        ),
      ),
    );
  }

  Widget _buildInfoRow(IconData icon, String label, String value, Color subColor, Color textColor) {
    if (value.isEmpty) return const SizedBox.shrink();
    return Row(
      children: [
        Icon(icon, size: 18, color: subColor),
        const SizedBox(width: 8),
        Text(
          '$label: ',
          style: TextStyle(fontSize: 13, color: subColor),
        ),
        Expanded(
          child: Text(
            value,
            style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: textColor),
            overflow: TextOverflow.ellipsis,
          ),
        ),
      ],
    );
  }

  Widget _buildPersonalInfo(BuildContext context, Map<String, dynamic> profile) {
    final l = AppLocalizations.of(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final gender = profile['gender'];
    final province = profile['province_name']?.toString();
    final district = profile['district_name']?.toString();
    final educationType = profile['education_type_name']?.toString();
    final educationForm = profile['education_form_name']?.toString();
    final groupName = profile['group_name']?.toString();
    final phone = profile['phone']?.toString();
    final telegramUsername = profile['telegram_username']?.toString();
    final telegramVerified = profile['telegram_verified'] == true;
    final cardColor = isDark ? AppTheme.darkCard : Colors.white;
    final subTextColor = isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;
    final textColor = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;

    final items = <MapEntry<String, String>>[];
    if (phone != null && phone.isNotEmpty) {
      items.add(MapEntry(l.get('phone'), phone));
    }
    if (telegramUsername != null && telegramUsername.isNotEmpty) {
      items.add(MapEntry('Telegram', '$telegramUsername${telegramVerified ? ' \u2705' : ''}'));
    }
    if (groupName != null && groupName.isNotEmpty) {
      items.add(MapEntry(l.group, groupName));
    }
    if (gender != null) {
      items.add(MapEntry(l.gender, gender == 11 ? l.male : l.female));
    }
    if (educationType != null && educationType.isNotEmpty) {
      items.add(MapEntry(l.educationType, educationType));
    }
    if (educationForm != null && educationForm.isNotEmpty) {
      items.add(MapEntry(l.educationForm, educationForm));
    }
    if (province != null && province.isNotEmpty) {
      items.add(MapEntry(l.province, province));
    }
    if (district != null && district.isNotEmpty) {
      items.add(MapEntry(l.district, district));
    }

    if (items.isEmpty) return const SizedBox.shrink();

    return Card(
      elevation: 0,
      color: cardColor,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            color: isDark ? AppTheme.darkSurface : AppTheme.primaryColor,
            child: Text(
              l.personalInfo,
              style: const TextStyle(
                fontWeight: FontWeight.bold,
                fontSize: 14,
                color: Colors.white,
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
            ...items.map((item) => Padding(
                  padding: const EdgeInsets.symmetric(vertical: 6),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      SizedBox(
                        width: 120,
                        child: Text(
                          item.key,
                          style: TextStyle(color: subTextColor, fontSize: 13),
                        ),
                      ),
                      Expanded(
                        child: Text(
                          item.value,
                          style: const TextStyle(
                            fontWeight: FontWeight.w500,
                            fontSize: 13,
                            color: AppTheme.successColor,
                          ),
                        ),
                      ),
                    ],
                  ),
                )),
              ],
            ),
          ),
        ],
      ),
    );
  }

  String? _buildImageUrl(String? imagePath) {
    if (imagePath == null || imagePath.isEmpty) return null;
    if (imagePath.startsWith('http://') || imagePath.startsWith('https://')) {
      final imageHost = Uri.parse(imagePath).host;
      final apiHost = Uri.parse(ApiConfig.baseUrl).host;
      if (imageHost != apiHost) {
        final encoded = Uri.encodeComponent(imagePath);
        return '${ApiConfig.baseUrl}${ApiConfig.imageProxy}?url=$encoded';
      }
      return imagePath;
    }
    final baseHost = Uri.parse(ApiConfig.baseUrl).origin;
    final path = imagePath.startsWith('/') ? imagePath : '/$imagePath';
    return '$baseHost$path';
  }

  String _getInitials(String name) {
    final parts = name.split(' ');
    if (parts.length >= 2) {
      return '${parts[0][0]}${parts[1][0]}';
    }
    return name.isNotEmpty ? name[0] : '?';
  }

  void _showLogoutDialog(BuildContext context) {
    final l = AppLocalizations.of(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: isDark ? AppTheme.darkCard : Colors.white,
        title: Text(l.logout),
        content: Text(l.logoutConfirm),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(),
            child: Text(l.cancel),
          ),
          ElevatedButton(
            onPressed: () {
              Navigator.of(ctx).pop();
              context.read<AuthProvider>().logout();
            },
            style: ElevatedButton.styleFrom(backgroundColor: AppTheme.errorColor),
            child: Text(l.logout),
          ),
        ],
      ),
    );
  }
}
