import 'package:flutter/material.dart';
import '../../config/theme.dart';

class TeacherServicesScreen extends StatelessWidget {
  const TeacherServicesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.backgroundColor,
      appBar: AppBar(
        leading: const Padding(
          padding: EdgeInsets.all(12),
          child: Icon(Icons.account_balance, size: 28),
        ),
        title: const Text('Xizmatlar'),
      ),
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: const [
            Icon(Icons.miscellaneous_services_outlined,
                size: 64, color: AppTheme.textSecondary),
            SizedBox(height: 16),
            Text(
              'Xizmatlar',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w600,
                color: AppTheme.textPrimary,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
