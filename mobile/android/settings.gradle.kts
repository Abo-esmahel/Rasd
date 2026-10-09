pluginManagement {
    val flutterSdkPath =
        run {
            val properties = java.util.Properties()
            file("local.properties").inputStream().use { properties.load(it) }
            val flutterSdkPath = properties.getProperty("flutter.sdk")
            require(flutterSdkPath != null) { "flutter.sdk not set in local.properties" }
            flutterSdkPath
        }

    includeBuild("$flutterSdkPath/packages/flutter_tools/gradle")

    repositories {
        google()
        mavenCentral()
        gradlePluginPortal()
    }
}

plugins {
    id("dev.flutter.flutter-plugin-loader") version "1.0.0"
    id("com.android.application") version "8.9.1" apply false
    id("org.jetbrains.kotlin.android") version "2.1.0" apply false
}

include(":app")

// بيئة التطوير تملك platforms (36 و37.2) فقط ولا تصل لمستودع SDK —
// نرفع compileSdk أي إضافة تحت 36 إلى android-36 بدل الفشل.
gradle.projectsLoaded {
    rootProject.subprojects {
        afterEvaluate {
            val ext = extensions.findByName("android")
            if (ext is com.android.build.gradle.BaseExtension) {
                val current = (ext.compileSdkVersion ?: "")
                    .removePrefix("android-").toIntOrNull() ?: 0
                if (current in 1..35) {
                    ext.compileSdkVersion("android-36")
                }
            }
        }
    }
}
