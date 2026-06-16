class YnGradeCalculator {
  static int? computeFromSubject(Map<String, dynamic> subject) {
    final gradesRaw = subject['grades'];
    final grades = gradesRaw is Map
        ? Map<String, dynamic>.from(gradesRaw)
        : <String, dynamic>{};

    return computeFromGrades(
      grades,
      davPercent: _toDouble(subject['dav_percent']) ?? 0,
    );
  }

  static int? computeFromGrades(
    Map<String, dynamic> grades, {
    required double davPercent,
    int weightJn = 50,
    int weightMt = 20,
    int weightOn = 0,
    int weightOski = 15,
    int weightTest = 15,
  }) {
    return compute(
      jn: _toRoundedInt(grades['jn']) ?? 0,
      mt: _toRoundedInt(grades['mt']) ?? 0,
      on: _toRoundedInt(grades['on']) ?? 0,
      oski: _toRoundedInt(grades['oski']) ?? 0,
      test: _toRoundedInt(grades['test']) ?? 0,
      davPercent: davPercent,
      weightJn: weightJn,
      weightMt: weightMt,
      weightOn: weightOn,
      weightOski: weightOski,
      weightTest: weightTest,
    );
  }

  static int? compute({
    required int jn,
    required int mt,
    required int on,
    required int oski,
    required int test,
    required double davPercent,
    int weightJn = 50,
    int weightMt = 20,
    int weightOn = 0,
    int weightOski = 15,
    int weightTest = 15,
  }) {
    if (jn == 0 && mt == 0) return null;
    if (davPercent >= 25) return -3;

    final baseSum = _weightedPart(jn, weightJn) +
        _weightedPart(mt, weightMt) +
        _weightedPart(on, weightOn);
    final examSum =
        _weightedPart(oski, weightOski) + _weightedPart(test, weightTest);
    final maxBaseWeight = weightJn + weightMt + weightOn;
    final baseRatio = maxBaseWeight > 0 ? baseSum / maxBaseWeight : 0.0;

    final missingFinalExam =
        (weightOski > 0 && oski == 0) || (weightTest > 0 && test == 0);
    if (missingFinalExam && baseRatio >= 0.6) {
      return -1;
    }

    final hasFailedComponent =
        (weightJn > 0 && jn < 60) ||
        (weightMt > 0 && mt < 60) ||
        (weightOn > 0 && on < 60) ||
        (weightOski > 0 && oski < 60) ||
        (weightTest > 0 && test < 60);
    if (hasFailedComponent) {
      return 0;
    }

    return baseSum.round() + examSum.round();
  }

  static double _weightedPart(int value, int weight) {
    if (weight <= 0 || value < 60) return 0;
    return value * weight / 100;
  }

  static int? _toRoundedInt(dynamic raw) {
    final parsed = _toDouble(raw);
    return parsed?.round();
  }

  static double? _toDouble(dynamic raw) {
    if (raw is num) return raw.toDouble();
    if (raw is String) {
      final normalized = raw.trim().replaceAll(',', '.');
      if (normalized.isEmpty) return null;
      return double.tryParse(normalized);
    }
    return null;
  }
}
