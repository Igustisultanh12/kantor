class DashboardStatsModel {
  final int totalLogs;
  final int totalArchives;
  final int activePersonnel;
  final int pendingSkhpp;
  final double totalCashBalance;

  DashboardStatsModel({
    required this.totalLogs,
    required this.totalArchives,
    required this.activePersonnel,
    required this.pendingSkhpp,
    required this.totalCashBalance,
  });

  factory DashboardStatsModel.fromJson(Map<String, dynamic> json) {
    return DashboardStatsModel(
      totalLogs: json['total_logs'] ?? 0,
      totalArchives: json['total_archives'] ?? 0,
      activePersonnel: json['active_personnel'] ?? 0,
      pendingSkhpp: json['pending_skhpp'] ?? 0,
      totalCashBalance: double.tryParse(json['total_cash_balance']?.toString() ?? '0') ?? 0.0,
    );
  }
}