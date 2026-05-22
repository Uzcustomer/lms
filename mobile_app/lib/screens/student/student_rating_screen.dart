import 'package:flutter/material.dart';
import '../../services/api_service.dart';
import '../../services/student_service.dart';
import '../../widgets/clinic_header.dart';

class StudentRatingScreen extends StatefulWidget {
  const StudentRatingScreen({super.key});

  @override
  State<StudentRatingScreen> createState() => _StudentRatingScreenState();
}

class _StudentRatingScreenState extends State<StudentRatingScreen> {
  final _filters = const ['group', 'specialty', 'department'];
  final _filterLabels = const {
    'group': 'Guruh',
    'specialty': 'Yo\'nalish',
    'department': 'Fakultet',
  };

  String _activeFilter = 'group';
  bool _loading = true;
  int _myRank = 0;
  double _myAvg = 0;
  int _totalStudents = 0;
  List<dynamic> _students = [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final api = ApiService();
      final service = StudentService(api);
      final res = await service.getRating(filter: _activeFilter);
      if (mounted && res['success'] == true) {
        final data = res['data'] as Map<String, dynamic>;
        setState(() {
          _myRank = data['my_rank'] as int? ?? 0;
          _myAvg = (data['my_jn_average'] as num?)?.toDouble() ?? 0;
          _totalStudents = data['total_students'] as int? ?? 0;
          _students = data['students'] as List<dynamic>? ?? [];
          _loading = false;
        });
      } else {
        if (mounted) setState(() => _loading = false);
      }
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: ClinicTheme.bgOf(context),
      body: Column(
        children: [
          ClinicHeader(
            overline: 'FOYDALI',
            title: 'Talabalar reytingi',
            onBack: () => Navigator.pop(context),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 12, 14, 0),
            child: _buildMyRankCard(),
          ),
          const SizedBox(height: 12),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 14),
            child: Row(
              children: _filters.map((f) {
                final active = _activeFilter == f;
                return Expanded(
                  child: GestureDetector(
                    onTap: () {
                      if (_activeFilter != f) {
                        _activeFilter = f;
                        _load();
                      }
                    },
                    child: AnimatedContainer(
                      duration: const Duration(milliseconds: 200),
                      margin: EdgeInsets.only(
                          right: f != _filters.last ? 8 : 0),
                      padding: const EdgeInsets.symmetric(vertical: 9),
                      decoration: BoxDecoration(
                        color: active
                            ? ClinicTheme.teal
                            : (Theme.of(context).brightness == Brightness.dark
                                ? Colors.white.withOpacity(0.06)
                                : const Color(0xFFF1F5F9)),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Text(
                        _filterLabels[f]!,
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          fontSize: 12.5,
                          fontWeight: active ? FontWeight.w800 : FontWeight.w600,
                          color: active ? Colors.white : ClinicTheme.mutedOf(context),
                        ),
                      ),
                    ),
                  ),
                );
              }).toList(),
            ),
          ),
          const SizedBox(height: 12),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _students.isEmpty
                    ? Center(
                        child: Text('Ma\'lumot topilmadi',
                            style: TextStyle(color: ClinicTheme.mutedOf(context))))
                    : RefreshIndicator(
                        onRefresh: _load,
                        child: ListView.builder(
                          padding: const EdgeInsets.fromLTRB(14, 0, 14, 24),
                          itemCount: _students.length,
                          itemBuilder: (_, i) => _buildStudentTile(_students[i]),
                        ),
                      ),
          ),
        ],
      ),
    );
  }

  Widget _buildMyRankCard() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF0F766E), Color(0xFF1E3A8A)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF0F766E).withOpacity(0.35),
            blurRadius: 14,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            width: 54,
            height: 54,
            decoration: BoxDecoration(
              color: Colors.white.withOpacity(0.18),
              borderRadius: BorderRadius.circular(15),
            ),
            alignment: Alignment.center,
            child: _loading
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                : _myRank <= 3 && _myRank > 0
                    ? const Icon(Icons.emoji_events_rounded, color: Colors.white, size: 28)
                    : Text(
                        _myRank > 0 ? '$_myRank' : '—',
                        style: const TextStyle(
                            fontSize: 22, fontWeight: FontWeight.w900, color: Colors.white),
                      ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('SIZNING O\'RNINGIZ',
                    style: TextStyle(
                        fontSize: 10,
                        letterSpacing: 0.5,
                        color: Colors.white.withOpacity(0.8),
                        fontWeight: FontWeight.w700)),
                const SizedBox(height: 3),
                Text(
                  _loading
                      ? '...'
                      : '$_totalStudents talaba ichida $_myRank-o\'rin',
                  style: const TextStyle(
                      fontSize: 14, color: Colors.white, fontWeight: FontWeight.w700),
                ),
              ],
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text('O\'RTACHA',
                  style: TextStyle(
                      fontSize: 9,
                      letterSpacing: 0.5,
                      color: Colors.white.withOpacity(0.7),
                      fontWeight: FontWeight.w700)),
              Text(
                _loading ? '...' : _myAvg.toStringAsFixed(1),
                style: const TextStyle(
                    fontSize: 24, fontWeight: FontWeight.w900, color: Colors.white),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildStudentTile(dynamic s) {
    final ink = ClinicTheme.inkOf(context);
    final muted = ClinicTheme.mutedOf(context);
    final rank = s['rank'] as int? ?? 0;
    final name = s['full_name']?.toString() ?? '';
    final group = s['group_name']?.toString() ?? '';
    final avg = (s['jn_average'] as num?)?.toDouble() ?? 0;
    final isMe = s['is_me'] == true;

    Color? medalColor;
    if (rank == 1) {
      medalColor = const Color(0xFFD4A017);
    } else if (rank == 2) {
      medalColor = const Color(0xFF94A3B8);
    } else if (rank == 3) {
      medalColor = const Color(0xFFB45309);
    }

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: ClinicTheme.surfaceOf(context),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: isMe ? ClinicTheme.teal : ClinicTheme.dividerOf(context),
          width: isMe ? 1.5 : 1,
        ),
        boxShadow: ClinicTheme.cardShadow,
      ),
      child: Row(
        children: [
          SizedBox(
            width: 34,
            child: medalColor != null
                ? Icon(Icons.emoji_events_rounded, color: medalColor, size: 24)
                : Text(
                    '$rank',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w900,
                        color: isMe ? ClinicTheme.teal : muted),
                  ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  name,
                  style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                      color: isMe ? ClinicTheme.teal : ink),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                if (_activeFilter != 'group')
                  Text(group, style: TextStyle(fontSize: 11, color: muted)),
              ],
            ),
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
            decoration: BoxDecoration(
              color: _avgColor(avg),
              borderRadius: BorderRadius.circular(8),
            ),
            child: Text(
              avg.toStringAsFixed(1),
              style: const TextStyle(
                  fontSize: 13, fontWeight: FontWeight.w900, color: Colors.white),
            ),
          ),
          if (isMe) ...[
            const SizedBox(width: 6),
            const Icon(Icons.person_rounded, size: 16, color: ClinicTheme.teal),
          ],
        ],
      ),
    );
  }

  Color _avgColor(double avg) {
    if (avg >= 86) return const Color(0xFF15803D);
    if (avg >= 71) return const Color(0xFF1D4ED8);
    if (avg >= 56) return const Color(0xFFB45309);
    return const Color(0xFFBE123C);
  }
}
