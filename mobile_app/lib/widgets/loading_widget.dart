import 'package:flutter/material.dart';
import 'package:shimmer/shimmer.dart';
import '../config/theme.dart';

class LoadingWidget extends StatelessWidget {
  const LoadingWidget({super.key});

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final baseColor = isDark ? Colors.grey[700]! : Colors.grey[300]!;
    final highlightColor = isDark ? Colors.grey[600]! : Colors.grey[100]!;
    final shimmerItemColor = isDark ? AppTheme.darkCard : Colors.white;

    return Padding(
      padding: const EdgeInsets.all(16),
      child: Shimmer.fromColors(
        baseColor: baseColor,
        highlightColor: highlightColor,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 200,
              height: 24,
              decoration: BoxDecoration(
                color: shimmerItemColor,
                borderRadius: BorderRadius.circular(8),
              ),
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(child: _shimmerCard(shimmerItemColor)),
                const SizedBox(width: 12),
                Expanded(child: _shimmerCard(shimmerItemColor)),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(child: _shimmerCard(shimmerItemColor)),
                const SizedBox(width: 12),
                Expanded(child: _shimmerCard(shimmerItemColor)),
              ],
            ),
            const SizedBox(height: 24),
            _shimmerListItem(shimmerItemColor),
            const SizedBox(height: 8),
            _shimmerListItem(shimmerItemColor),
            const SizedBox(height: 8),
            _shimmerListItem(shimmerItemColor),
          ],
        ),
      ),
    );
  }

  Widget _shimmerCard(Color color) {
    return Container(
      height: 100,
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(16),
      ),
    );
  }

  Widget _shimmerListItem(Color color) {
    return Container(
      height: 72,
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(12),
      ),
    );
  }
}
