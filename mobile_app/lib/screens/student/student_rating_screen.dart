import 'package:flutter/material.dart';
import '../../config/theme.dart';
import '../../services/api_service.dart';
import '../../services/student_service.dart';

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
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bg = isDark ? AppTheme.darkBackground : const Color(0xFFF5F7FB);
    final card = isDark ? AppTheme.darkCard : Colors.white;
    final txt = isDark ? AppTheme.darkTextPrimary : AppTheme.textPrimary;
    final sub = isDark ? AppTheme.darkTextSecondary : AppTheme.textSecondary;

    return Scaffold(
      backgroundColor: bg,
      appBar: AppBar(title: const Text('Talabalar reytingi')),
      body: Column(
        children: [
          // My rank card
          _buildMyRankCard(isDark),

          // Filter chips
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
            child: Row(
              children: _filters
                  .map((f) => Expanded(
                        child: GestureDetector(
                          onTap: () {
                            if (_activeFilter != f) {
                              _activeFilter = f;
                              _load();
                            }
                          },
                          child: AnimatedContainer(
                            duration: const Duration(milliseconds: 200),
                            margin: const EdgeInsets.symmetric(horizontal: 3),
                            padding: const EdgeInsets.symmetric(vertical: 10),
                            decoration: BoxDecoration(
                              color: _activeFilter == f
                                  ? const Color(0xFF4A6CF7)
                                  : isDark
                                      ? Colors.white.withOpacity(0.06)
                                      : Colors.white,
                              borderRadius: BorderRadius.circular(10),
                              border: _activeFilter != f
                                  ? Border.all(
                                      color: isDark
                                          ? Colors.white12
                                          : Colors.grey.shade200)
                                  : null,
                            ),
                            child: Text(
                              _filterLabels[f]!,
                              textAlign: TextAlign.center,
                              style: TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.w600,
                                color: _activeFilter == f
                                    ? Colors.white
                                    : sub,
                              ),
                            ),
                          ),
                        ),
                      ))
                  .toList(),
            ),
          ),

          // Student list
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _students.isEmpty
                    ? Center(
                        child: Text('Ma\'lumot topilmadi',
                            style: TextStyle(color: sub)))
                    : RefreshIndicator(
                        onRefresh: _load,
                        child: ListView.builder(
                          padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
                          itemCount: _students.length,
                          itemBuilder: (_, i) => _buildStudentTile(
                              _students[i], card, txt, sub, isDark),
                        ),
                      ),
          ),
        ],
      ),
    );
  }

  Widget _buildMyRankCard(bool isDark) {
    final Color color;
    if (_myRank <= 3 && _myRank > 0) {
      color = const Color(0xFFFF9800);
    } else {
      color = const Color(0xFF4A6CF7);
    }

    return Container(
      margin: const EdgeInsets.fromLTRB(16, 12, 16, 4),
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [color, color.withOpacity(0.7)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(18),
        boxShadow: [
          BoxShadow(
            color: color.withOpacity(0.3),
            blurRadius: 16,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            width: 56,
            height: 56,
            decoration: BoxDecoration(
              color: Colors.white.withOpacity(0.2),
              borderRadius: BorderRadius.circular(16),
            ),
            alignment: Alignment.center,
            child: _loading
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(
                        strokeWidth: 2, color: Colors.white))
                : Text(
                    _myRank > 0 ? '#$_myRank' : '—',
                    style: const TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.bold,
                        color: Colors.white),
                  ),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Sizning o\'rningiz',
                    style: TextStyle(
                        fontSize: 13,
                        color: Colors.white70,
                        fontWeight: FontWeight.w500)),
                const SizedBox(height: 2),
                Text(
                  _loading
                      ? '...'
                      : '$_totalStudents talaba ichida $_myRank-o\'rin',
                  style: const TextStyle(
                      fontSize: 15,
                      color: Colors.white,
                      fontWeight: FontWeight.w600),
                ),
              ],
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              const Text('O\'rtacha',
                  style: TextStyle(fontSize: 11, color: Colors.white60)),
              Text(
                _loading ? '...' : _myAvg.toStringAsFixed(1),
                style: const TextStyle(
                    fontSize: 24,
                    fontWeight: FontWeight.bold,
                    color: Colors.white),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildStudentTile(dynamic s, Color card, Color txt, Color sub,
      bool isDark) {
    final rank = s['rank'] as int? ?? 0;
    final name = s['full_name']?.toString() ?? '';
    final group = s['group_name']?.toString() ?? '';
    final avg = (s['jn_average'] as num?)?.toDouble() ?? 0;
    final isMe = s['is_me'] == true;

    final Color? medalColor;
    IconData? medalIcon;
    if (rank == 1) {
      medalColor = const Color(0xFFFFD700);
      medalIcon = Icons.emoji_events_rounded;
    } else if (rank == 2) {
      medalColor = const Color(0xFFC0C0C0);
      medalIcon = Icons.emoji_events_rounded;
    } else if (rank == 3) {
      medalColor = const Color(0xFFCD7F32);
      medalIcon = Icons.emoji_events_rounded;
    } else {
      medalColor = null;
      medalIcon = null;
    }

    return Container(
      margin: const EdgeInsets.only(bottom: 6),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: isMe
            ? const Color(0xFF4A6CF7).withOpacity(isDark ? 0.15 : 0.06)
            : card,
        borderRadius: BorderRadius.circular(12),
        border: isMe
            ? Border.all(
                color: const Color(0xFF4A6CF7).withOpacity(0.3), width: 1.5)
            : null,
        boxShadow: isMe
            ? null
            : [
                BoxShadow(
                  color: Colors.black.withOpacity(0.02),
                  blurRadius: 6,
                  offset: const Offset(0, 1),
                ),
              ],
      ),
      child: Row(
        children: [
          // Rank
          SizedBox(
            width: 36,
            child: medalColor != null
                ? Icon(medalIcon, color: medalColor, size: 24)
                : Text(
                    '$rank',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w700,
                        color: isMe
                            ? const Color(0xFF4A6CF7)
                            : sub),
                  ),
          ),
          const SizedBox(width: 10),
          // Name
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  name,
                  style: TextStyle(
                      fontSize: 13,
                      fontWeight:
                          isMe ? FontWeight.w700 : FontWeight.w500,
                      color: isMe
                          ? const Color(0xFF4A6CF7)
                          : txt),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                if (_activeFilter != 'group')
                  Text(group,
                      style: TextStyle(fontSize: 11, color: sub)),
              ],
            ),
          ),
          // Average
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            decoration: BoxDecoration(
              color: _avgColor(avg).withOpacity(isDark ? 0.15 : 0.08),
              borderRadius: BorderRadius.circular(8),
            ),
            child: Text(
              avg.toStringAsFixed(1),
              style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.bold,
                  color: _avgColor(avg)),
            ),
          ),
          if (isMe) ...[
            const SizedBox(width: 6),
            const Icon(Icons.person_rounded,
                size: 16, color: Color(0xFF4A6CF7)),
          ],
        ],
      ),
    );
  }

  Color _avgColor(double avg) {
    if (avg >= 86) return const Color(0xFF4CAF50);
    if (avg >= 71) return const Color(0xFF29B6F6);
    if (avg >= 56) return const Color(0xFFFF9800);
    return const Color(0xFFE53935);
  }
}
