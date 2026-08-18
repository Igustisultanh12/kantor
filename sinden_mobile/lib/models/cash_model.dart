class CashTransactionModel {
  final int id;
  final String date;
  final String description;
  final double debit;
  final double credit;
  final double balance;
  final List<String> receiptUrls;
  final String? recordedBy;

  CashTransactionModel({
    required this.id,
    required this.date,
    required this.description,
    required this.debit,
    required this.credit,
    required this.balance,
    required this.receiptUrls,
    this.recordedBy,
  });

  factory CashTransactionModel.fromJson(Map<String, dynamic> json) {
    List<String> receipts = [];
    if (json['receipt_urls'] is List) {
      receipts = (json['receipt_urls'] as List).map((e) => e.toString()).toList();
    }

    return CashTransactionModel(
      id: json['id'] ?? 0,
      date: json['date'] ?? '',
      description: json['description'] ?? '',
      debit: double.tryParse(json['debit'].toString()) ?? 0.0,
      credit: double.tryParse(json['credit'].toString()) ?? 0.0,
      balance: double.tryParse(json['balance'].toString()) ?? 0.0,
      receiptUrls: receipts,
      recordedBy: json['user'] != null ? json['user']['name'] : null,
    );
  }
}