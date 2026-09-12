import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:lms_mobile/utils/yn_grade_calculator.dart';
import 'package:lms_mobile/widgets/stat_card.dart';

void main() {
  group('YnGradeCalculator', () {
    test('returns null when JN and MT are both zero', () {
      expect(
        YnGradeCalculator.compute(
          jn: 0, mt: 0, on: 0, oski: 0, test: 0, davPercent: 0,
        ),
        isNull,
      );
    });

    test('returns -3 when absence is 25% or more', () {
      expect(
        YnGradeCalculator.compute(
          jn: 90, mt: 90, on: 0, oski: 90, test: 90, davPercent: 25,
        ),
        -3,
      );
    });

    test('returns -1 when final exam is missing but base is passing', () {
      expect(
        YnGradeCalculator.compute(
          jn: 80, mt: 80, on: 0, oski: 0, test: 0, davPercent: 0,
        ),
        -1,
      );
    });

    test('returns 0 when any weighted component is below 60', () {
      expect(
        YnGradeCalculator.compute(
          jn: 55, mt: 80, on: 0, oski: 80, test: 80, davPercent: 0,
        ),
        0,
      );
    });

    test('sums weighted parts for a fully passing subject', () {
      // 80*0.5 + 80*0.2 + 80*0.15 + 80*0.15 = 40 + 16 + 12 + 12 = 80
      expect(
        YnGradeCalculator.compute(
          jn: 80, mt: 80, on: 0, oski: 80, test: 80, davPercent: 0,
        ),
        80,
      );
    });

    test('uses 80/20 weights for sinov subjects', () {
      final yn = YnGradeCalculator.computeFromSubject({
        'closing_form': 'sinov',
        'grades': {'jn': 90, 'mt': 70},
        'dav_percent': 5,
      });
      // 90*0.8 + 70*0.2 = 72 + 14 = 86
      expect(yn, 86);
    });

    test('respects yn_can_calculate = false', () {
      final yn = YnGradeCalculator.computeFromSubject({
        'yn_can_calculate': false,
        'grades': {'jn': 90, 'mt': 90},
      });
      expect(yn, isNull);
    });
  });

  testWidgets('StatCard renders title and value', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: StatCard(
            title: 'GPA',
            value: '4.2',
            icon: Icons.school,
            color: Colors.blue,
          ),
        ),
      ),
    );

    expect(find.text('GPA'), findsOneWidget);
    expect(find.text('4.2'), findsOneWidget);
    expect(find.byIcon(Icons.school), findsOneWidget);
  });
}
