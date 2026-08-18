import 'package:flutter_test/flutter_test.dart';
import 'package:sinden_mobile/models/user_model.dart';

void main() {
  group('UserModel Tests', () {
    test('Should correctly parse UserModel from JSON', () {
      final json = {
        'id': 1,
        'name': 'Budi Santoso',
        'email': 'budi@tnial.mil.id',
        'pangkat': 'Kapten Laut (E)',
        'korps': 'Pelaut',
        'nrp': '12345/P',
        'role': 'admin',
      };

      final user = UserModel.fromJson(json);

      expect(user.id, equals(1));
      expect(user.name, equals('Budi Santoso'));
      expect(user.pangkat, equals('Kapten Laut (E)'));
      expect(user.korps, equals('Pelaut'));
      expect(user.nrp, equals('12345/P'));
      expect(user.role, equals('admin'));
    });

    test('Should compute fullIdentity string correctly', () {
      final user = UserModel(
        id: 1,
        name: 'Budi Santoso',
        pangkat: 'Kapten Laut (E)',
        korps: 'Pelaut',
        nrp: '12345/P',
      );

      expect(user.fullIdentity, equals('Kapten Laut (E) Pelaut Budi Santoso NRP 12345/P'));
    });
  });
}