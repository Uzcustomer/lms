import 'package:flutter_test/flutter_test.dart';
import 'package:lms_mobile/services/sababli_retake_plan.dart';

// 2026-11: 1, 8, 15 — yakshanba. Qoidalar LMS dagi SababliRetakePlan::validate bilan bir xil.

Map<String, dynamic> item(String subject, String type, String original, String? makeup, {bool submitted = false}) => {
      'subject_name': subject,
      'assessment_type': type,
      'original_date': original,
      'makeup_date': makeup,
      'already_submitted': submitted,
    };

void main() {
  // Server tuzgan avtomatik jadval: 3 kun dars qoldirilgan, retake 09.11 dan 11.11 gacha
  final plan = SababliRetakePlan(
    items: [
      item('Anatomiya', 'jn', '2026-11-02', '2026-11-09'),
      item('Anatomiya', 'jn', '2026-11-03', '2026-11-10'),
      item('Anatomiya', 'oski', '2026-11-04', '2026-11-10'),
      item('Anatomiya', 'mt', '2026-11-04', '2026-11-10'),
      item('Fiziologiya', 'jn', '2026-11-02', '2026-11-09'),
      item('Fiziologiya', 'mt', '2026-11-03', null, submitted: true),
    ],
    retakeStart: '2026-11-09',
    retakeUntil: '2026-11-11',
  );

  test('the automatic plan leaves out submitted items', () {
    expect(plan.autoPlan.keys, [0, 1, 2, 3, 4]);
    expect(plan.planError(plan.withoutMt(plan.autoPlan)), isNull);
  });

  test('the MT deadline follows the last JN retake of the subject', () {
    // OSKI oxirgi retake kunidan oldin qolmasin — avval u, keyin JN ko'chiriladi
    expect(plan.moveTo(1, '2026-11-11', null), isNull);
    final moved = plan.moveTo(1, '2026-11-11', plan.moveTo(2, '2026-11-11', null))!;
    expect(plan.plan(moved)[3], '2026-11-11');
    expect(plan.plan(moved).containsKey(5), isFalse);
  });

  test('rejects Sundays, days outside the window, two retakes of a subject and OSKI before the last JN', () {
    final base = plan.withoutMt(plan.autoPlan);
    expect(plan.planError({...base, 0: '2026-11-08'}), contains('mumkin emas'));
    expect(plan.planError({...base, 0: '2026-11-12'}), contains('mumkin emas'));
    expect(plan.planError({...base, 0: '2026-11-10'}), contains('bittadan ortiq retake'));
    expect(plan.planError({...base, 2: '2026-11-09'}), contains('OSKI oxirgi retake kunidan oldin'));
  });

  test('offers only free days that keep the rules, with the day load', () {
    final days = plan.candidateDays(4, null);
    expect(days.map((d) => d.date), ['2026-11-10', '2026-11-11']);
    expect(days.map((d) => d.load), [2, 1]);
    expect(plan.candidateDays(0, null), isEmpty); // 10 va 11 — Anatomiyaning boshqa kunlari / OSKI dan keyin
  });

  test('caps a day at three subjects and keeps the test a day after the retakes', () {
    final p = SababliRetakePlan(
      items: [
        item('A', 'jn', '2026-11-02', '2026-11-09'),
        item('B', 'jn', '2026-11-02', '2026-11-09'),
        item('C', 'jn', '2026-11-02', '2026-11-09'),
        item('D', 'jn', '2026-11-02', '2026-11-10'),
        item('A', 'test', '2026-11-04', '2026-11-11'),
      ],
      retakeStart: '2026-11-09',
      retakeUntil: '2026-11-12',
    );
    expect(p.planError({...p.autoPlan, 3: '2026-11-09'}), contains('3 tadan ortiq fan'));
    expect(p.planError(p.autoPlan), isNull);
    expect(p.planError({...p.autoPlan, 4: '2026-11-10'}), contains('bir kun oraliq'));
  });

  test('counts days without Sundays and the filing deadline from the next working day', () {
    expect(SababliRetakePlan.countNonSundays(DateTime(2026, 11, 2), DateTime(2026, 11, 9)), 7);
    // 07.11 (shanba) tugagan — muddat 09.11 (dushanba) dan
    expect(SababliRetakePlan.daysSinceEnd(DateTime(2026, 11, 7), DateTime(2026, 11, 8)), isNull);
    expect(SababliRetakePlan.daysSinceEnd(DateTime(2026, 11, 7), DateTime(2026, 11, 20)), 11);
  });
}
