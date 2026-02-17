class AppConstants {
  static const String appName = 'TDTU LMS';
  static const String appVersion = '1.0.0';
  static const int tokenExpiryDays = 30;

  // Training type codes
  static const int trainingTypeExcluded = 11;
  static const int trainingTypeMT = 99;
  static const int trainingTypeON = 100;
  static const int trainingTypeOSKI = 101;
  static const int trainingTypeTest = 102;

  // Training type names
  static const Map<int, String> trainingTypeNames = {
    99: 'MT (Mustaqil ta\'lim)',
    100: 'ON (Oraliq nazorat)',
    101: 'OSKI',
    102: 'Test',
  };

  // Grade status
  static const String statusPending = 'pending';
  static const String statusRecorded = 'recorded';
  static const String statusClosed = 'closed';
  static const String statusRetake = 'retake';
}
