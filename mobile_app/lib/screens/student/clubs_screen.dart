import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/theme.dart';
import '../../config/aurora_themes.dart';
import '../../providers/settings_provider.dart';
import '../../services/api_service.dart';
import '../../services/student_service.dart';

class ClubsScreen extends StatefulWidget {
  const ClubsScreen({super.key});

  @override
  State<ClubsScreen> createState() => _ClubsScreenState();
}

class _ClubsScreenState extends State<ClubsScreen> with SingleTickerProviderStateMixin {
  late TabController _tabCtrl;
  final _service = StudentService(ApiService());

  List<dynamic> _sections = [];
  List<dynamic> _myClubs = [];
  bool _loading = true;
  String? _error;
  String? _joiningClub;
  String? _cancellingClub;

  @override
  void initState() {
    super.initState();
    _tabCtrl = TabController(length: 2, vsync: this);
    _loadData();
  }

  @override
  void dispose() {
    _tabCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadData() async {
    setState(() { _loading = true; _error = null; });
    try {
      final results = await Future.wait([
        _service.getClubs(),
        _service.getMyClubs(),
      ]);
      if (!mounted) return;
      setState(() {
        _sections = results[0]['data'] as List? ?? [];
        _myClubs = results[1]['data'] as List? ?? [];
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = 'Ma\'lumotlarni yuklashda xatolik';
        _loading = false;
      });
    }
  }

  Future<void> _joinClub(Map<String, dynamic> club, String kafedraName) async {
    final name = club['name'] as String;
    setState(() => _joiningClub = name);
    try {
      final res = await _service.joinClub(
        clubName: name,
        clubPlace: club['place'] as String?,
        clubDay: club['day'] as String?,
        clubTime: club['time'] as String?,
        kafedraName: kafedraName,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(res['message'] ?? "Ariza yuborildi!"),
          backgroundColor: const Color(0xFF16A34A),
        ),
      );
      await _loadData();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message), backgroundColor: const Color(0xFFDC2626)),
      );
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Xatolik yuz berdi'), backgroundColor: Color(0xFFDC2626)),
      );
    } finally {
      if (mounted) setState(() => _joiningClub = null);
    }
  }

  Future<void> _cancelClub(String clubName) async {
    setState(() => _cancellingClub = clubName);
    try {
      final res = await _service.cancelClub(clubName);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(res['message'] ?? "Ariza bekor qilindi!"),
          backgroundColor: const Color(0xFFF59E0B),
        ),
      );
      await _loadData();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message), backgroundColor: const Color(0xFFDC2626)),
      );
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Xatolik yuz berdi'), backgroundColor: Color(0xFFDC2626)),
      );
    } finally {
      if (mounted) setState(() => _cancellingClub = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final aurora = context.watch<SettingsProvider>().auroraTheme;
    final statusBarH = MediaQuery.of(context).padding.top;
    final cardColor = isDark ? AppTheme.darkCard : Colors.white;
    final textColor = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;
    final subColor = isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;

    return Scaffold(
      backgroundColor: auroraBase(aurora, isDark),
      body: Column(
        children: [
          Container(
            padding: EdgeInsets.only(top: statusBarH),
            decoration: const BoxDecoration(
              color: Color(0xFF0A1A3A),
              borderRadius: BorderRadius.only(
                bottomLeft: Radius.circular(18),
                bottomRight: Radius.circular(18),
              ),
            ),
            child: Column(
              children: [
                SizedBox(
                  height: 56,
                  child: Row(
                    children: [
                      const SizedBox(width: 4),
                      IconButton(
                        icon: const Icon(Icons.arrow_back, color: Colors.white, size: 22),
                        onPressed: () => Navigator.pop(context),
                      ),
                      const Expanded(
                        child: Text(
                          "To'garaklar",
                          style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: Colors.white),
                          textAlign: TextAlign.center,
                        ),
                      ),
                      const SizedBox(width: 48),
                    ],
                  ),
                ),
                TabBar(
                  controller: _tabCtrl,
                  indicatorColor: Colors.white,
                  indicatorWeight: 3,
                  labelColor: Colors.white,
                  unselectedLabelColor: Colors.white60,
                  labelStyle: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700),
                  unselectedLabelStyle: const TextStyle(fontSize: 13, fontWeight: FontWeight.w500),
                  tabs: [
                    const Tab(text: "Barcha to'garaklar"),
                    Tab(text: "Arizalarim (${_myClubs.length})"),
                  ],
                ),
              ],
            ),
          ),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _error != null
                    ? Center(
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(_error!, style: TextStyle(color: subColor)),
                            const SizedBox(height: 12),
                            TextButton(onPressed: _loadData, child: const Text('Qayta yuklash')),
                          ],
                        ),
                      )
                    : TabBarView(
                        controller: _tabCtrl,
                        children: [
                          _buildAllClubs(cardColor, textColor, subColor, isDark),
                          _buildMyClubs(cardColor, textColor, subColor, isDark),
                        ],
                      ),
          ),
        ],
      ),
    );
  }

  Widget _buildAllClubs(Color cardColor, Color textColor, Color subColor, bool isDark) {
    return RefreshIndicator(
      onRefresh: _loadData,
      child: ListView.builder(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
        itemCount: _sections.length,
        itemBuilder: (context, sIdx) {
          final section = _sections[sIdx] as Map<String, dynamic>;
          final title = section['title'] as String? ?? '';
          final clubs = (section['clubs'] as List? ?? []).cast<Map<String, dynamic>>();

          final rows = <Widget>[];
          for (int i = 0; i < clubs.length; i += 2) {
            final left = clubs[i];
            final right = (i + 1 < clubs.length) ? clubs[i + 1] : null;
            rows.add(Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: IntrinsicHeight(
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Expanded(
                      child: _buildClubCard(left, i + 1, title, cardColor, textColor, subColor, isDark),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: right == null
                          ? const SizedBox.shrink()
                          : _buildClubCard(right, i + 2, title, cardColor, textColor, subColor, isDark),
                    ),
                  ],
                ),
              ),
            ));
          }

          return Padding(
            padding: const EdgeInsets.only(bottom: 18),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                  margin: const EdgeInsets.only(bottom: 10),
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                      colors: isDark
                          ? const [Color(0xFF1E3A5F), Color(0xFF274468)]
                          : const [Color(0xFFC2DEF9), Color(0xFFD9EAFB)],
                    ),
                    borderRadius: BorderRadius.circular(14),
                    boxShadow: [
                      BoxShadow(
                        color: const Color(0xFF4F46E5).withAlpha(isDark ? 30 : 18),
                        blurRadius: 10,
                        offset: const Offset(0, 4),
                      ),
                    ],
                  ),
                  child: Text(
                    title,
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                      color: isDark ? Colors.white : const Color(0xFF1E3A5F),
                      height: 1.4,
                    ),
                    textAlign: TextAlign.center,
                  ),
                ),
                ...rows,
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _buildClubCard(
    Map<String, dynamic> club,
    int number,
    String kafedraTitle,
    Color cardColor,
    Color textColor,
    Color subColor,
    bool isDark,
  ) {
    final applied = club['applied'] == true;
    final isJoining = _joiningClub == club['name'];
    final isCancelling = _cancellingClub == club['name'];

    return AnimatedContainer(
      duration: const Duration(milliseconds: 220),
      curve: Curves.easeOut,
      padding: const EdgeInsets.fromLTRB(12, 12, 12, 12),
      decoration: BoxDecoration(
        color: cardColor,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: applied
              ? const Color(0xFF16A34A).withAlpha(70)
              : isDark
                  ? Colors.white12
                  : const Color(0xFFE2E8F0),
          width: applied ? 1.4 : 1,
        ),
        boxShadow: [
          BoxShadow(
            color: isDark
                ? Colors.black.withAlpha(60)
                : const Color(0xFF0F1B3D).withAlpha(applied ? 18 : 10),
            blurRadius: applied ? 14 : 8,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            '$number. ${club['name']}',
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w700,
              color: textColor,
              height: 1.3,
            ),
          ),
          const SizedBox(height: 8),
          _infoRow(Icons.location_on_outlined, club['place'] ?? '', subColor),
          const SizedBox(height: 4),
          _infoRow(Icons.calendar_today_outlined, club['day'] ?? '', subColor),
          const SizedBox(height: 4),
          _infoRow(Icons.access_time_outlined, club['time'] ?? '', subColor),
          const Spacer(),
          const SizedBox(height: 10),
          if (applied)
            GestureDetector(
              onTap: isCancelling ? null : () => _cancelClub(club['name'] as String),
              child: Container(
                width: double.infinity,
                padding: const EdgeInsets.symmetric(vertical: 7),
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: const Color(0xFFDC2626).withAlpha(15),
                  border: Border.all(color: const Color(0xFFDC2626).withAlpha(60)),
                  borderRadius: BorderRadius.circular(9),
                ),
                child: isCancelling
                    ? const SizedBox(
                        width: 14,
                        height: 14,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          valueColor: AlwaysStoppedAnimation(Color(0xFFDC2626)),
                        ),
                      )
                    : const Text(
                        'Bekor qilish',
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                          color: Color(0xFFDC2626),
                        ),
                      ),
              ),
            )
          else
            Material(
              color: Colors.transparent,
              child: InkWell(
                borderRadius: BorderRadius.circular(9),
                onTap: isJoining ? null : () => _joinClub(club, kafedraTitle),
                child: Ink(
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                      colors: [Color(0xFF6366F1), Color(0xFF4F46E5)],
                    ),
                    borderRadius: BorderRadius.circular(9),
                    boxShadow: [
                      BoxShadow(
                        color: const Color(0xFF4F46E5).withAlpha(70),
                        blurRadius: 10,
                        offset: const Offset(0, 4),
                      ),
                    ],
                  ),
                  child: Container(
                    width: double.infinity,
                    padding: const EdgeInsets.symmetric(vertical: 9),
                    alignment: Alignment.center,
                    child: isJoining
                        ? const SizedBox(
                            width: 14,
                            height: 14,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              valueColor: AlwaysStoppedAnimation(Colors.white),
                            ),
                          )
                        : const Text(
                            "A'zo bo'lish",
                            style: TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w700,
                              color: Colors.white,
                              letterSpacing: 0.2,
                            ),
                          ),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildMyClubs(Color cardColor, Color textColor, Color subColor, bool isDark) {
    if (_myClubs.isEmpty) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.groups_outlined, size: 64, color: subColor.withAlpha(80)),
            const SizedBox(height: 12),
            Text(
              "Hali ariza yuborilmagan",
              style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: subColor),
            ),
            const SizedBox(height: 4),
            Text(
              "To'garakka a'zo bo'lish uchun ariza yuboring",
              style: TextStyle(fontSize: 12, color: subColor.withAlpha(160)),
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _loadData,
      child: ListView.builder(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
        itemCount: _myClubs.length,
        itemBuilder: (context, index) {
          final club = _myClubs[index] as Map<String, dynamic>;
          final status = club['status'] as String? ?? 'pending';

          Color statusColor;
          String statusLabel;
          switch (status) {
            case 'approved':
              statusColor = const Color(0xFF16A34A);
              statusLabel = 'Tasdiqlangan';
              break;
            case 'rejected':
              statusColor = const Color(0xFFDC2626);
              statusLabel = 'Rad etilgan';
              break;
            default:
              statusColor = const Color(0xFFF59E0B);
              statusLabel = 'Kutilmoqda';
          }

          return Container(
            margin: const EdgeInsets.only(bottom: 12),
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: cardColor,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: isDark ? Colors.white10 : const Color(0xFFE2E8F0)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        club['club_name'] ?? '',
                        style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w700,
                          color: textColor,
                        ),
                      ),
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                        color: statusColor.withAlpha(20),
                        border: Border.all(color: statusColor.withAlpha(80)),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text(
                        statusLabel,
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                          color: statusColor,
                        ),
                      ),
                    ),
                  ],
                ),
                if (club['kafedra_name'] != null) ...[
                  const SizedBox(height: 8),
                  Text(
                    club['kafedra_name'],
                    style: TextStyle(fontSize: 11, color: subColor, fontWeight: FontWeight.w500),
                  ),
                ],
                const SizedBox(height: 8),
                if (club['club_place'] != null)
                  _infoRow(Icons.location_on_outlined, club['club_place'], subColor),
                if (club['club_day'] != null) ...[
                  const SizedBox(height: 3),
                  _infoRow(Icons.calendar_today_outlined, club['club_day'], subColor),
                ],
                if (club['club_time'] != null) ...[
                  const SizedBox(height: 3),
                  _infoRow(Icons.access_time_outlined, club['club_time'], subColor),
                ],
                if (status == 'rejected' && club['reject_reason'] != null) ...[
                  const SizedBox(height: 8),
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: const Color(0xFFDC2626).withAlpha(15),
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: const Color(0xFFDC2626).withAlpha(40)),
                    ),
                    child: Text(
                      'Sabab: ${club['reject_reason']}',
                      style: const TextStyle(fontSize: 11, color: Color(0xFFDC2626)),
                    ),
                  ),
                ],
                const SizedBox(height: 10),
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        club['created_at'] ?? '',
                        style: TextStyle(fontSize: 10, color: subColor.withAlpha(120)),
                      ),
                    ),
                    GestureDetector(
                      onTap: _cancellingClub == club['club_name']
                          ? null
                          : () => _cancelClub(club['club_name'] as String),
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                        decoration: BoxDecoration(
                          color: const Color(0xFFDC2626).withAlpha(15),
                          border: Border.all(color: const Color(0xFFDC2626).withAlpha(60)),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: _cancellingClub == club['club_name']
                            ? const SizedBox(
                                width: 14,
                                height: 14,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  valueColor: AlwaysStoppedAnimation(Color(0xFFDC2626)),
                                ),
                              )
                            : const Text(
                                'Bekor qilish',
                                style: TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w700,
                                  color: Color(0xFFDC2626),
                                ),
                              ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _infoRow(IconData icon, String text, Color color) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 13, color: color.withAlpha(160)),
        const SizedBox(width: 5),
        Expanded(
          child: Text(
            text,
            style: TextStyle(fontSize: 11, color: color, height: 1.3),
          ),
        ),
      ],
    );
  }
}
