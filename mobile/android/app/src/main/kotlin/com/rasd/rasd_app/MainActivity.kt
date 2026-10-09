package com.rasd.rasd_app

import android.content.Intent
import android.net.Uri
import androidx.core.content.FileProvider
import io.flutter.embedding.android.FlutterFragmentActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel
import java.io.File

// قناة مشاركة خفيفة (بدون اعتماديات خارجية): واتساب/تلغرام/فتح روابط.
// ملاحظة: لقطات الشاشة مسموحة داخل التطبيق (لا FLAG_SECURE).
class MainActivity : FlutterFragmentActivity() {
    private val channel = "rasd.share"

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        MethodChannel(
            flutterEngine.dartExecutor.binaryMessenger, channel
        ).setMethodCallHandler { call, result ->
            try {
                when (call.method) {
                    "shareText" -> {
                        val text = call.argument<String>("text") ?: ""
                        val pkg = call.argument<String>("package")
                        val intent = Intent(Intent.ACTION_SEND).apply {
                            type = "text/plain"
                            putExtra(Intent.EXTRA_TEXT, text)
                            pkg?.let { setPackage(it) }
                        }
                        startActivity(
                            if (pkg == null) Intent.createChooser(intent, null)
                            else intent
                        )
                        result.success(true)
                    }
                    "shareFiles" -> {
                        @Suppress("UNCHECKED_CAST")
                        val paths =
                            (call.argument<List<String>>("paths") ?: emptyList())
                        val text = call.argument<String>("text")
                        val pkg = call.argument<String>("package")
                        val uris = paths.map { p ->
                            FileProvider.getUriForFile(
                                this,
                                "$packageName.fileprovider",
                                File(p)
                            )
                        }
                        val intent = Intent(
                            if (uris.size > 1) Intent.ACTION_SEND_MULTIPLE
                            else Intent.ACTION_SEND
                        ).apply {
                            type = "*/*"
                            if (uris.size > 1) {
                                putParcelableArrayListExtra(
                                    Intent.EXTRA_STREAM,
                                    ArrayList(uris)
                                )
                            } else if (uris.size == 1) {
                                putExtra(Intent.EXTRA_STREAM, uris[0])
                            }
                            text?.let {
                                putExtra(Intent.EXTRA_TEXT, it)
                            }
                            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                            pkg?.let { setPackage(it) }
                        }
                        // منح صلاحية القراءة لكل تطبيق مستهدف
                        val targets = if (pkg != null) {
                            listOf(pkg)
                        } else {
                            packageManager
                                .queryIntentActivities(intent, 0)
                                .map { it.activityInfo.packageName }
                        }
                        for (t in targets) {
                            for (u in uris as List<Uri>) {
                                grantUriPermission(
                                    t, u,
                                    Intent.FLAG_GRANT_READ_URI_PERMISSION
                                )
                            }
                        }
                        startActivity(
                            if (pkg == null) Intent.createChooser(intent, null)
                            else intent
                        )
                        result.success(true)
                    }
                    "openUrl" -> {
                        val url = call.argument<String>("url") ?: ""
                        startActivity(
                            Intent(Intent.ACTION_VIEW, Uri.parse(url))
                        )
                        result.success(true)
                    }
                    "isInstalled" -> {
                        val pkg = call.argument<String>("package") ?: ""
                        val ok = try {
                            packageManager.getPackageInfo(pkg, 0)
                            true
                        } catch (_: Exception) {
                            false
                        }
                        result.success(ok)
                    }
                    else -> result.notImplemented()
                }
            } catch (e: Exception) {
                result.error("SHARE_FAILED", e.message, null)
            }
        }
    }
}
