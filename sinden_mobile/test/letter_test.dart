import 'package:flutter_test/flutter_test.dart';
import 'package:sinden_mobile/models/letter_log_model.dart';

void main() {
  group('LetterLogModel Tests', () {
    test('Should correctly parse LetterLogModel from JSON', () {
      final json = {
        'id': 10,
        'number': 42,
        'full_number': 'B/42/VIII/2026/Denintel',
        'subject': 'Permohonan Pengamanan Wilayah Maritim',
        'recipient': 'Pangkoarmada II',
        'date': '2026-08-18',
        'category_id': 1,
        'is_archived': 1,
      };

      final log = LetterLogModel.fromJson(json);

      expect(log.id, 10);
      expect(log.fullNumber, 'B/42/VIII/2026/Denintel');
      expect(log.isArchived, true);
    });
  });
}