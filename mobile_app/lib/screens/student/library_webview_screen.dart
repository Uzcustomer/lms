import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:webview_flutter/webview_flutter.dart';

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
        appBar: AppBar(title: const Text('Kutubxona')),
        body: const Center(child: CircularProgressIndicator()),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text('Kutubxona'),
        actions: [
          IconButton(
            icon: const Icon(Icons.open_in_browser_rounded),
            onPressed: () => launchUrl(Uri.parse(_url),
                mode: LaunchMode.externalApplication),
          ),
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            onPressed: () => _controller?.reload(),
          ),
        ],
        bottom: _isLoading
            ? PreferredSize(
                preferredSize: const Size.fromHeight(3),
                child: LinearProgressIndicator(
                  value: _progress,
                  backgroundColor: Colors.transparent,
                  minHeight: 3,
                ),
              )
            : null,
      ),
      body: WebViewWidget(controller: _controller!),
    );
  }
}
