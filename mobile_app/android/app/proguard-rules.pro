## Flutter
-keep class io.flutter.** { *; }
-keep class io.flutter.plugins.** { *; }

## flutter_secure_storage
-keep class com.it_nomads.fluttersecurestorage.** { *; }

## Keep Kotlin metadata
-keepattributes *Annotation*
-keep class kotlin.** { *; }
-keep class kotlinx.** { *; }

## OkHttp / networking
-dontwarn okhttp3.**
-dontwarn okio.**
-dontwarn javax.annotation.**

## Google Play Core (deferred components)
-dontwarn com.google.android.play.core.**

## General
-keepattributes Signature
-keepattributes Exceptions
