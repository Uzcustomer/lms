import 'dart:typed_data';

import '../config/api_config.dart';
import 'api_service.dart';

class EnglishGroupApplicationService {
  final ApiService _api;

  EnglishGroupApplicationService(this._api);

  Future<Map<String, dynamic>> getOverview() async {
    return await _api.get(ApiConfig.studentEnglishGroupApplications);
  }

  Future<Map<String, dynamic>> submit({
    required String englishLevel,
    String? phoneNumber,
    Uint8List? certificateBytes,
    String? certificateFileName,
  }) async {
    return await _api.multipartPost(
      ApiConfig.studentEnglishGroupApplications,
      {
        'english_level': englishLevel,
        if (phoneNumber != null && phoneNumber.trim().isNotEmpty)
          'phone_number': phoneNumber.trim(),
      },
      fileBytes: certificateBytes,
      fileName: certificateFileName,
      fileField: 'certificate_pdf',
    );
  }

  String certificateUrl(int id) =>
      '${ApiConfig.baseUrl}${ApiConfig.studentEnglishGroupApplications}/$id/certificate';
}
