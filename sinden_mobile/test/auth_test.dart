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

      expect(user.id, 1);
      expect(user.name, 'Budi Santoso');
      expect(user.pangkat, 'Kapten Laut (E)');
      expect(user.nrp, '12345/P');
      expect(user.role, 'admin');
      expect(user.fullIdentity, 'Kapten Laut (E) Pelaut Budi Santoso NRP 12345/P');
    });
  });
}