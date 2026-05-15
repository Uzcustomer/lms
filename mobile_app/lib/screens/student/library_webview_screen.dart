import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:webview_flutter/webview_flutter.dart';
import '../../config/aurora_themes.dart';
import '../../providers/settings_provider.dart';

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
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final aurora = context.watch<SettingsProvider>().auroraTheme;
    final statusBarH = MediaQuery.of(context).padding.top;

    if (!_isMobile) {
      return Scaffold(
        backgroundColor: auroraBase(aurora, isDark),
        body: Column(
          children: [
            _buildHeader(statusBarH),
            const Expanded(child: Center(child: CircularProgressIndicator())),
          ],
        ),
      );
    }

    return Scaffold(
      backgroundColor: auroraBase(aurora, isDark),
      body: Column(
        children: [
          Container(
            padding: EdgeInsets.only(top: statusBarH, left: 4, right: 4),
            decoration: BoxDecoration(
              color: isDark ? AppTheme.darkHeaderColor : const Color(0xFF1E3A8A),
              borderRadius: BorderRadius.only(
                bottomLeft: Radius.circular(18),
                bottomRight: Radius.circular(18),
              ),
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                SizedBox(height: statusBarH > 0 ? 0 : 8),
                Row(
                  children: [
                    IconButton(
                      icon: const Icon(Icons.arrow_back, color: Colors.white, size: 22),
                      onPressed: () => Navigator.pop(context),
                    ),
                    const Expanded(
                      child: Text(
                        'Kutubxona',
                        style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: Colors.white),
                        textAlign: TextAlign.center,
                      ),
                    ),
                    IconButton(
                      icon: const Icon(Icons.open_in_browser_rounded, color: Colors.white, size: 22),
                      onPressed: () => launchUrl(Uri.parse(_url),
                          mode: LaunchMode.externalApplication),
                    ),
                    IconButton(
                      icon: const Icon(Icons.refresh_rounded, color: Colors.white, size: 22),
                      onPressed: () => _controller?.reload(),
                    ),
                  ],
                ),
                if (_isLoading)
                  LinearProgressIndicator(
                    value: _progress,
                    backgroundColor: Colors.transparent,
                    minHeight: 3,
                  ),
              ],
            ),
          ),
          Expanded(child: WebViewWidget(controller: _controller!)),
        ],
      ),
    );
  }

  Widget _buildHeader(double statusBarH) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Container(
      padding: EdgeInsets.only(top: statusBarH, left: 4, right: 4),
      height: statusBarH + 64,
      decoration: BoxDecoration(
        color: isDark ? AppTheme.darkHeaderColor : const Color(0xFF1E3A8A),
        borderRadius: BorderRadius.only(
          bottomLeft: Radius.circular(18),
          bottomRight: Radius.circular(18),
        ),
      ),
      child: Row(
        children: [
          IconButton(
            icon: const Icon(Icons.arrow_back, color: Colors.white, size: 22),
            onPressed: () => Navigator.pop(context),
          ),
          const Expanded(
            child: Text(
              'Kutubxona',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: Colors.white),
              textAlign: TextAlign.center,
            ),
          ),
          const SizedBox(width: 48),
        ],
      ),
    );
  }
}
