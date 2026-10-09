class V {
  static String lang = 'ar';
  static String msg(String ar, String en) => lang == 'en' ? en : ar;

  static const descMin = 10;
  static const descMax = 5000;
  static const floorMin = 0;
  static const floorMax = 200;
  static const cameraMin = 1;
  static const cameraMax = 100000;

  static const imageMax = 40 * 1024 * 1024;
  static const videoMax = 500 * 1024 * 1024;
  static const audioMax = 500 * 1024 * 1024;
  static const maxFiles = 10;

  static const _imgExt = {
    'jpg',
    'jpeg',
    'png',
    'webp',
    'heic',
    'heif',
    'tiff',
    'tif',
    'bmp',
    'avif',
    'gif',
    'svg',
  };
  static const _vidExt = {
    'mp4',
    'webm',
    'mov',
    'avi',
    '3gp',
    '3gpp',
    'mkv',
    'm4v',
    'mpg',
    'mpeg',
    'wmv',
    'flv',
    'ogv',
    'ts',
    'mts',
    'm2ts',
    'vob',
    'asf',
    'm2v',
    '3g2',
    'f4v',
    'm4p',
  };
  static const _audExt = {
    'mp3',
    'wav',
    'ogg',
    'oga',
    'm4a',
    'aac',
    'wma',
    'flac',
    'opus',
    'aiff',
    'aif',
    'amr',
    '3ga',
    'awb',
    'mid',
    'midi',
    'au',
    'ra',
    'weba',
    'ac3',
    'dts',
    'alac',
  };

  static String? required(String? v, String field) {
    if (v == null || v.trim().isEmpty) {
      return msg('$field مطلوب', '$field is required');
    }
    return null;
  }

  static String? username(String? v) {
    final value = (v ?? '').trim();
    if (value.isEmpty) return msg('اسم المستخدم مطلوب', 'Username is required');
    if (value.length > 50) {
      return msg('اسم المستخدم طويل جداً', 'Username is too long');
    }
    return null;
  }

  static String? profileName(String? v) {
    final value = (v ?? '').trim();
    if (value.isEmpty) return msg('الاسم مطلوب', 'Name is required');
    if (value.length > 100) return msg('الاسم طويل جداً', 'Name is too long');
    return null;
  }

  static String? serverAddress(String? input) {
    var value = (input ?? '').trim();
    if (value.isEmpty) {
      return msg('عنوان الخادم مطلوب', 'Server address is required');
    }
    if (!value.contains('://')) value = 'http://$value';
    final uri = Uri.tryParse(value);
    if (uri == null ||
        !{'http', 'https'}.contains(uri.scheme.toLowerCase()) ||
        uri.host.isEmpty ||
        uri.userInfo.isNotEmpty ||
        uri.hasQuery ||
        uri.hasFragment) {
      return msg(
        'أدخل عنواناً صحيحاً مثل https://example.com',
        'Enter a valid address, such as https://example.com',
      );
    }
    return null;
  }

  static String? syrianPhone(String? input) {
    final raw = (input ?? '').trim();
    if (raw.isEmpty) return null;
    final clean = raw.replaceAll(RegExp(r'[\s\-().]'), '');
    var digits = clean.replaceAll(RegExp(r'\D'), '');
    if (clean.startsWith('+')) digits = '+$digits';
    if (digits.startsWith('00963')) {
      digits = '+963${digits.substring(5)}';
    } else if (digits.startsWith('963')) {
      digits = '+$digits';
    } else if (digits.startsWith('09')) {
      digits = '+963${digits.substring(1)}';
    } else if (digits.startsWith('9')) {
      digits = '+963$digits';
    }
    if (!RegExp(r'^\+9639\d{8}$').hasMatch(digits)) {
      return msg(
        'أدخل رقماً سورياً صحيحاً مثل 09xxxxxxxx',
        'Enter a valid Syrian number, such as 09xxxxxxxx',
      );
    }
    return null;
  }

  static String? password(String? v) {
    if (v == null || v.isEmpty) {
      return msg('كلمة المرور مطلوبة', 'Password is required');
    }
    return null;
  }

  static String? floor(String? v) {
    final n = int.tryParse((v ?? '').trim());
    if (n == null) {
      return msg('الطابق مطلوب (رقم صحيح)', 'Floor is required (integer)');
    }
    if (n < floorMin || n > floorMax) {
      return msg(
        'الطابق يجب أن يكون بين $floorMin و $floorMax',
        'Floor must be between $floorMin and $floorMax',
      );
    }
    return null;
  }

  static String? camera(String? v) {
    final n = int.tryParse((v ?? '').trim());
    if (n == null) {
      return msg('الكاميرا مطلوبة (رقم صحيح)', 'Camera is required (integer)');
    }
    if (n < cameraMin || n > cameraMax) {
      return msg(
        'رقم الكاميرا يجب أن يكون بين $cameraMin و $cameraMax',
        'Camera must be between $cameraMin and $cameraMax',
      );
    }
    return null;
  }

  static String? description(String? v) {
    final t = (v ?? '').trim();
    if (t.isEmpty) return msg('الوصف مطلوب', 'Description is required');
    if (t.length < descMin) {
      return msg(
        'الوصف يجب أن يحتوي على $descMin أحرف على الأقل',
        'Description needs at least $descMin characters',
      );
    }
    if (t.length > descMax) {
      return msg(
        'الوصف يجب ألا يتجاوز $descMax حرف',
        'Description must not exceed $descMax characters',
      );
    }
    return null;
  }

  static String? rejectionReason(String? v) {
    final t = (v ?? '').trim();
    if (t.length < 5) {
      return msg(
        'سبب الرفض مطلوب (5 أحرف على الأقل)',
        'Reason required (min 5 characters)',
      );
    }
    if (t.length > 1000) {
      return msg('سبب الرفض طويل جداً', 'Reason is too long');
    }
    return null;
  }

  static String? reportTitle(String? v) {
    final t = (v ?? '').trim();
    if (t.isNotEmpty && (t.length < 3 || t.length > 255)) {
      return msg(
        'العنوان بين 3 و 255 حرفاً (أو اتركه فارغاً)',
        'Title 3–255 chars (or leave empty)',
      );
    }
    return null;
  }

  static String kindOf(String path) {
    final ext = path.split('.').last.toLowerCase();
    if (_imgExt.contains(ext)) return 'image';
    if (_vidExt.contains(ext)) return 'video';
    if (_audExt.contains(ext)) return 'audio';
    return 'other';
  }

  static String? fileProblem(String path, int bytes) {
    final kind = kindOf(path);
    if (kind == 'other') {
      return msg('نوع الملف غير مدعوم', 'Unsupported file type');
    }
    final limit = kind == 'image'
        ? imageMax
        : kind == 'video'
        ? videoMax
        : audioMax;
    if (bytes > limit) {
      final mb = (limit / 1024 / 1024).round();
      return msg(
        'الملف أكبر من الحد ($mb م.ب)',
        'File exceeds the limit ($mb MB)',
      );
    }
    return null;
  }
}
