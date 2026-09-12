import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:provider/provider.dart';
import 'package:google_fonts/google_fonts.dart';
import 'config/theme.dart';
import 'services/api_service.dart';
import 'services/auth_service.dart';
import 'services/student_service.dart';
import 'services/student_data_cache.dart';
import 'widgets/notification_bell.dart';
import 'widgets/biometric_gate.dart';
import 'services/teacher_service.dart';
import 'providers/auth_provider.dart';
import 'providers/student_provider.dart';
import 'providers/teacher_provider.dart';
import 'providers/settings_provider.dart';
import 'l10n/app_localizations.dart';
import 'screens/common/splash_screen.dart';
import 'screens/auth/login_screen.dart';
import 'screens/auth/complete_profile_screen.dart';
import 'screens/student/student_home_screen.dart';
import 'screens/teacher/teacher_home_screen.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  GoogleFonts.config.allowRuntimeFetching = true;
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

    StudentDataCache().attachService(studentService);

    return MultiProvider(
      providers: [
        ChangeNotifierProvider(
          create: (_) => SettingsProvider(),
        ),
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
      child: Consumer<SettingsProvider>(
        builder: (context, settings, _) {
          return MaterialApp(
            title: 'TDTU LMS',
            debugShowCheckedModeBanner: false,
            theme: AppTheme.lightTheme,
            darkTheme: AppTheme.darkTheme,
            themeMode: settings.themeMode,
            locale: settings.locale,
            supportedLocales: AppLocalizations.supportedLocales,
            localizationsDelegates: const [
              AppLocalizations.delegate,
              GlobalMaterialLocalizations.delegate,
              GlobalWidgetsLocalizations.delegate,
              GlobalCupertinoLocalizations.delegate,
            ],
            home: _SessionEffects(
              child: Consumer<AuthProvider>(
                builder: (context, auth, _) {
                  switch (auth.state) {
                    case AuthState.initial:
                      return const SplashScreen();
                    case AuthState.authenticated:
                      return BiometricGate(
                        child: auth.isTeacher
                            ? const TeacherHomeScreen()
                            : const StudentHomeScreen(),
                      );
                    case AuthState.profileIncomplete:
                      return const CompleteProfileScreen();
                    case AuthState.loading:
                    case AuthState.unauthenticated:
                    case AuthState.error:
                    case AuthState.requires2fa:
                      return const LoginScreen();
                  }
                },
              ),
            ),
          );
        },
      ),
    );
  }
}

/// Starts/stops the student-session side effects (session user sync, data
/// cache warm-up, notification polling) exactly once per session change,
/// instead of on every rebuild of the auth consumer.
class _SessionEffects extends StatefulWidget {
  final Widget child;
  const _SessionEffects({required this.child});

  @override
  State<_SessionEffects> createState() => _SessionEffectsState();
}

class _SessionEffectsState extends State<_SessionEffects> {
  late final AuthProvider _auth;
  String? _sessionKey;

  @override
  void initState() {
    super.initState();
    _auth = context.read<AuthProvider>();
    _auth.addListener(_sync);
    WidgetsBinding.instance.addPostFrameCallback((_) => _sync());
  }

  @override
  void dispose() {
    _auth.removeListener(_sync);
    super.dispose();
  }

  void _sync() {
    if (!mounted) return;
    final isStudent =
        _auth.state == AuthState.authenticated && _auth.isStudent;
    final key = isStudent ? 'student:${_auth.user?['id']}' : 'none';
    if (key == _sessionKey) return;
    _sessionKey = key;

    final student = context.read<StudentProvider>();
    if (isStudent) {
      student.syncSessionUser(_auth.user);
      StudentDataCache().ensureFresh();
      NotificationBadge.startPolling();
    } else {
      student.syncSessionUser(null);
      NotificationBadge.stopPolling();
      NotificationBadge.unread.value = 0;
    }
  }

  @override
  Widget build(BuildContext context) => widget.child;
}
