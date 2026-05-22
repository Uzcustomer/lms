import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:webview_flutter/webview_flutter.dart';
import '../../widgets/clinic_header.dart';

class LibraryWebViewScreen extends StatefulWidget {
  const LibraryWebViewScreen({super.key});

  @override
  State<LibraryWebViewScreen> createState() => _LibraryWebViewScreenState();
}

class _LibraryWebViewScreenState extends State<LibraryWebViewScreen> {
  static const _url = 'https://unilibrary.uz';

  WebViewController? _controller;
  bool _isLoading = true;
  double _progress = 0;

  bool get _isMobile =>
      !kIsWeb &&
      (defaultTargetPlatform == TargetPlatform.android ||
          defaultTargetPlatform == TargetPlatform.iOS);

  @override
  void initState() {
    super.initState();
    if (_isMobile) {
      _controller = WebViewController()
        ..setJavaScriptMode(JavaScriptMode.unrestricted)
        ..setNavigationDelegate(NavigationDelegate(
          onPageStarted: (_) {
            if (mounted) setState(() => _isLoading = true);
          },
          onProgress: (progress) {
            if (mounted) setState(() => _progress = progress / 100);
          },
          onPageFinished: (_) {
            if (mounted) setState(() => _isLoading = false);
          },
        ))
        ..loadRequest(Uri.parse(_url));
    } else {
      _openInBrowser();
    }
  }

  void _openInBrowser() {
    launchUrl(Uri.parse(_url), mode: LaunchMode.externalApplication);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) Navigator.pop(context);
    });
  }

  @override
  Widget build(BuildContext context) {
    if (!_isMobile) {
      return Scaffold(
        backgroundColor: ClinicTheme.bgOf(context),
        body: Column(
          children: [
            ClinicHeader(
              overline: 'FOYDALI',
              title: 'Kutubxona',
              onBack: () => Navigator.pop(context),
            ),
            const Expanded(child: Center(child: CircularProgressIndicator())),
          ],
        ),
      );
    }

    return Scaffold(
      backgroundColor: ClinicTheme.bgOf(context),
      body: Column(
        children: [
          ClinicHeader(
            overline: 'FOYDALI',
            title: 'Kutubxona',
            onBack: () => Navigator.pop(context),
            actions: [
              ClinicIconButton(
                icon: Icons.open_in_browser_rounded,
                onTap: () => launchUrl(Uri.parse(_url),
                    mode: LaunchMode.externalApplication),
              ),
              ClinicIconButton(
                icon: Icons.refresh_rounded,
                onTap: () => _controller?.reload(),
              ),
            ],
          ),
          if (_isLoading)
            LinearProgressIndicator(
              value: _progress,
              backgroundColor: ClinicTheme.line,
              valueColor: const AlwaysStoppedAnimation<Color>(ClinicTheme.teal),
              minHeight: 3,
            ),
          Expanded(child: WebViewWidget(controller: _controller!)),
        ],
      ),
    );
  }
}

