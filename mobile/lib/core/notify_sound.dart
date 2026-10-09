import 'package:flutter_local_notifications/flutter_local_notifications.dart';

class NotifySound {
  static final _plugin = FlutterLocalNotificationsPlugin();
  static bool _ready = false;

  static Future<void> init() async {
    if (_ready) return;
    const android = AndroidInitializationSettings('@mipmap/ic_launcher');
    await _plugin.initialize(
      const InitializationSettings(android: android),
    );
    await _plugin
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>()
        ?.requestNotificationsPermission();
    const channel = AndroidNotificationChannel(
      'rasd_alerts',
      'تنبيهات رصد',
      importance: Importance.high,
      playSound: true,
      enableVibration: true,
    );
    await _plugin
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(channel);
    _ready = true;
  }

  static Future<void> headsUp(
      String id, String title, String body) async {
    if (!_ready) await init();
    await _plugin.show(
      id.hashCode & 0x7fffffff,
      title.isEmpty ? 'رصد' : title,
      body,
      const NotificationDetails(
        android: AndroidNotificationDetails(
          'rasd_alerts',
          'تنبيهات رصد',
          importance: Importance.high,
          priority: Priority.high,
          playSound: true,
          enableVibration: true,
        ),
      ),
    );
  }

  static Future<void> cancelId(String id) async {
    try {
      await _plugin.cancel(id.hashCode & 0x7fffffff);
    } catch (_) {}
  }

  static Future<void> cancelAll() async {
    try {
      await _plugin.cancelAll();
    } catch (_) {}
  }
}
