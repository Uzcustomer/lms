class YnGradeCalculator {
  static int? computeFromSubject(Map<String, dynamic> subject) {
    if (_isExplicitFalse(subject['yn_can_calculate'])) {
      return null;
    }

    final gradesRaw = subject['grades'];
    final grades = gradesRaw is Map
        ? Map<String, dynamic>.from(gradesRaw)
        : <String, dynamic>{};
    final weights = _weightsForSubject(subject, grades);

    return computeFromGrades(
      grades,
      davPercent: _toDouble(subject['dav_percent']) ?? 0,
      weightJn: weights.jn,
      weightMt: weights.mt,
      weightOn: weights.on,
      weightOski: weights.oski,
      weightTest: weights.test,
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

  static _YnWeights _weightsForSubject(
    Map<String, dynamic> subject,
    Map<String, dynamic> grades,
  ) {
    final closingForm = _normalizeClosingForm(
      subject['closing_form'] ??
          subject['yopilish_shakli'] ??
          subject['assessment_type'],
    );

    if (closingForm == 'test') {
      return const _YnWeights(jn: 50, mt: 20, on: 0, oski: 0, test: 30);
    }
    if (closingForm == 'oski' || closingForm == 'oske') {
      return const _YnWeights(jn: 50, mt: 20, on: 0, oski: 30, test: 0);
    }
    if (closingForm == 'oski_test' || closingForm == 'oske_test') {
      return const _YnWeights(jn: 50, mt: 20, on: 0, oski: 15, test: 15);
    }
    if (closingForm == 'sinov') {
      return const _YnWeights(jn: 80, mt: 20, on: 0, oski: 0, test: 0);
    }

    final hasOski = _toDouble(grades['oski']) != null;
    final hasTest = _toDouble(grades['test']) != null;
    if (hasTest && !hasOski) {
      return const _YnWeights(jn: 50, mt: 20, on: 0, oski: 0, test: 30);
    }
    if (hasOski && !hasTest) {
      return const _YnWeights(jn: 50, mt: 20, on: 0, oski: 30, test: 0);
    }

    return const _YnWeights(jn: 50, mt: 20, on: 0, oski: 15, test: 15);
  }

  static String _normalizeClosingForm(dynamic raw) {
    return raw?.toString().trim().toLowerCase().replaceAll('-', '_') ?? '';
  }

  static bool _isExplicitFalse(dynamic raw) {
    if (raw is bool) return !raw;
    if (raw is num) return raw == 0;
    if (raw is String) {
      final normalized = raw.trim().toLowerCase();
      return normalized == 'false' || normalized == '0' || normalized == 'no';
    }
    return false;
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

class _YnWeights {
  final int jn;
  final int mt;
  final int on;
  final int oski;
  final int test;

  const _YnWeights({
    required this.jn,
    required this.mt,
    required this.on,
    required this.oski,
    required this.test,
  });
}
