import 'package:flutter/material.dart';

class StatusPill extends StatelessWidget {
  final String label;
  final Color backgroundColor;
  final Color textColor;

  const StatusPill({
    super.key,
    required this.label,
    required this.backgroundColor,
    required this.textColor,
  });

  factory StatusPill.success(String label) => StatusPill(
        label: label,
        backgroundColor: const Color(0xFFDCFCE7),
        textColor: const Color(0xFF15803D),
      );

  factory StatusPill.warning(String label) => StatusPill(
        label: label,
        backgroundColor: const Color(0xFFFEF3C7),
        textColor: const Color(0xFFB45309),
      );

  factory StatusPill.danger(String label) => StatusPill(
        label: label,
        backgroundColor: const Color(0xFFFEE2E2),
        textColor: const Color(0xFFB91C1C),
      );

  factory StatusPill.info(String label) => StatusPill(
        label: label,
        backgroundColor: const Color(0xFFDBEAFE),
        textColor: const Color(0xFF1D4ED8),
      );

  factory StatusPill.neutral(String label) => StatusPill(
        label: label,
        backgroundColor: const Color(0xFFF1F5F9),
        textColor: const Color(0xFF475569),
      );

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: backgroundColor,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        label.toUpperCase(),
        style: TextStyle(
          fontSize: 10,
          fontWeight: FontWeight.w800,
          color: textColor,
          letterSpacing: 0.3,
        ),
      ),
    );
  }
}