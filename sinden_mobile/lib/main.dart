import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'core/theme.dart';
import 'providers/auth_provider.dart';
import 'providers/letter_provider.dart';
import 'providers/skhpp_provider.dart';
import 'providers/cash_provider.dart';
import 'providers/location_provider.dart';
import 'providers/notification_provider.dart';
import 'services/notification_service.dart';
import 'screens/auth/login_screen.dart';
import 'screens/main_navigation_screen.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Initialize Sound Notification Service Channel
  try {
    await NotificationService().initialize();
  } catch (_) {}

  runApp(const SindenApp());
}

class SindenApp extends StatelessWidget {
  const SindenApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => AuthProvider()..checkAuthStatus()),
        ChangeNotifierProvider(create: (_) => LetterProvider()),
        ChangeNotifierProvider(create: (_) => SkhppProvider()),
        ChangeNotifierProvider(create: (_) => CashProvider()),
        ChangeNotifierProvider(create: (_) => LocationProvider()),
        ChangeNotifierProvider(create: (_) => NotificationProvider()),
      ],
      child: Consumer<AuthProvider>(
        builder: (context, auth, _) {
          return MaterialApp(
            title: 'SI SINDEN',
            debugShowCheckedModeBanner: false,
            theme: AppTheme.lightTheme,
            home: auth.isInitialLoading
                ? const Scaffold(
                    body: Center(
                      child: CircularProgressIndicator(color: AppTheme.primaryNavy),
                    ),
                  )
                : auth.isAuthenticated
                    ? const MainNavigationScreen()
                    : const LoginScreen(),
          );
        },
      ),
    );
  }
}