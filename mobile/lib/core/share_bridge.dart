import 'package:flutter/services.dart';

class ShareBridge {
  static const _ch = MethodChannel('rasd.share');

  static const whatsapp = 'com.whatsapp';
  static const telegram = 'org.telegram.messenger';

  static Future<bool> isInstalled(String package) async {
    try {
      return await _ch.invokeMethod<bool>(
            'isInstalled',
            {'package': package},
          ) ??
          false;
    } catch (_) {
      return false;
    }
  }

  static Future<bool> shareText(String text,
      {String? package}) async {
    try {
      await _ch.invokeMethod('shareText', {
        'text': text,
        if (package != null) 'package': package,
      });
      return true;
    } catch (_) {
      return false;
    }
  }

  static Future<bool> shareFiles(
    List<String> paths,
    String? text, {
    String? package,
  }) async {
    try {
      await _ch.invokeMethod('shareFiles', {
        'paths': paths,
        if (text != null) 'text': text,
        if (package != null) 'package': package,
      });
      return true;
    } catch (_) {
      return false;
    }
  }

  static Future<bool> openUrl(String url) async {
    try {
      await _ch.invokeMethod('openUrl', {'url': url});
      return true;
    } catch (_) {
      return false;
    }
  }
}
