class LetterLogModel {
  final int id;
  final int number;
  final String fullNumber;
  final String subject;
  final String recipient;
  final String date;
  final int categoryId;
  final String? categoryName;
  final bool isArchived;
  final String? fileUrl;
  final String? createdBy;

  LetterLogModel({
    required this.id,
    required this.number,
    required this.fullNumber,
    required this.subject,
    required this.recipient,
    required this.date,
    required this.categoryId,
    this.categoryName,
    required this.isArchived,
    this.fileUrl,
    this.createdBy,
  });

  factory LetterLogModel.fromJson(Map<String, dynamic> json) {
    return LetterLogModel(
      id: json['id'] ?? 0,
      number: json['number'] ?? 0,
      fullNumber: json['full_number'] ?? '-',
      subject: json['subject'] ?? '',
      recipient: json['recipient'] ?? '-',
      date: json['date'] ?? '',
      categoryId: json['category_id'] ?? 0,
      categoryName: json['category'] != null ? json['category']['name'] : null,
      isArchived: json['is_archived'] == 1 || json['is_archived'] == true,
      fileUrl: json['file_path'] ?? json['file_url'],
      createdBy: json['user'] != null ? json['user']['name'] : null,
    );
  }
}