import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'config/theme.dart';
import 'services/api_service.dart';
import 'services/auth_service.dart';
import 'services/student_service.dart';
import 'services/teacher_service.dart';
import 'providers/auth_provider.dart';
import 'providers/student_provider.dart';
import 'providers/teacher_provider.dart';
import 'screens/common/splash_screen.dart';
import 'screens/auth/login_screen.dart';
import 'screens/student/student_home_screen.dart';
import 'screens/teacher/teacher_home_screen.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  SystemChrome.setPreferredOrientations([
    DeviceOrientation.portraitUp,
    DeviceOrientation.portraitDown,
  ]);
  SystemChrome.setSystemUIOverlayStyle(
    const SystemUiOverlayStyle(
      statusBarColor: Colors.transparent,
      statusBarIconBrightness: Brightness.dark,
    ),
  );
  runApp(const LmsApp());
}

class LmsApp extends StatelessWidget {
  const LmsApp({super.key});

  @override
  Widget build(BuildContext context) {
    final apiService = ApiService();
    final authService = AuthService(apiService);
    final studentService = StudentService(apiService);
    final teacherService = TeacherService(apiService);

    return MultiProvider(
      providers: [
        ChangeNotifierProvider(
          create: (_) => AuthProvider(authService, apiService),
        ),
        ChangeNotifierProvider(
          create: (_) => StudentProvider(studentService),
        ),
        ChangeNotifierProvider(
          create: (_) => TeacherProvider(teacherService),
        ),
      ],
      child: MaterialApp(
        title: 'TDTU LMS',
        debugShowCheckedModeBanner: false,
        theme: AppTheme.lightTheme,
        home: Consumer<AuthProvider>(
          builder: (context, auth, _) {
            switch (auth.state) {
              case AuthState.initial:
                return const SplashScreen();
              case AuthState.loading:
                return const SplashScreen();
              case AuthState.authenticated:
                if (auth.isTeacher) {
                  return const TeacherHomeScreen();
                }
                return const StudentHomeScreen();
              case AuthState.unauthenticated:
              case AuthState.error:
              case AuthState.requires2fa:
                return const LoginScreen();
            }
          },
        ),
      ),
    );
  }
}
