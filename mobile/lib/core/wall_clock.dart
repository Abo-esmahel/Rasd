import 'package:flutter/material.dart';

class Wall {
  static String combine(DateTime date, TimeOfDay time) {
    final d =
        '${date.year}-${_p(date.month)}-${_p(date.day)}';
    return '${d}T${_p(time.hour)}:${_p(time.minute)}';
  }

  static String _p(int n) => n.toString().padLeft(2, '0');

  static ({String date, String time}) parts(String? iso) {
    if (iso == null || iso.length < 16) return (date: '', time: '');
    return (
      date: iso.substring(0, 10),
      time: iso.substring(11, 16),
    );
  }

  static String time12(String hhmm) {
    final p = hhmm.split(':');
    if (p.length < 2) return hhmm;
    final h = int.tryParse(p[0]) ?? 0;
    final m = p[1];
    final suffix = h < 12 ? 'ص' : 'م';
    final h12 = h % 12 == 0 ? 12 : h % 12;
    return '$h12:$m $suffix';
  }

  static bool isFuture(DateTime date, TimeOfDay time) {
    final now = DateTime.now();
    final v = DateTime(
        date.year, date.month, date.day, time.hour, time.minute);
    return v.isAfter(now);
  }

  static int spanMinutes(TimeOfDay start, TimeOfDay end) {
    final s = start.hour * 60 + start.minute;
    var e = end.hour * 60 + end.minute;
    if (e < s) e += 24 * 60;
    return e - s;
  }

  static const maxSpanMinutes = 12 * 60;
}
