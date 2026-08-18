import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:sinden_mobile/widgets/status_pill.dart';

void main() {
  testWidgets('StatusPill widget displays text and styling correctly', (WidgetTester tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: StatusPill.success('Tervalidasi TTE'),
        ),
      ),
    );

    expect(find.text('TERVALIDASI TTE'), findsOneWidget);
  });
}