class UserModel {
  final int id;
  final String name;
  final String? email;
  final String? pangkat;
  final String? korps;
  final String? nrp;
  final String? role;
  final String? jabatan;
  final String? phone;
  final String? avatar;
  final double? latitude;
  final double? longitude;
  final String? lastActive;

  UserModel({
    required this.id,
    required this.name,
    this.email,
    this.pangkat,
    this.korps,
    this.nrp,
    this.role,
    this.jabatan,
    this.phone,
    this.avatar,
    this.latitude,
    this.longitude,
    this.lastActive,
  });

  factory UserModel.fromJson(Map<String, dynamic> json) {
    return UserModel(
      id: json['id'] ?? 0,
      name: json['name'] ?? '',
      email: json['email'],
      pangkat: json['pangkat'],
      korps: json['korps'],
      nrp: json['nrp'] ?? json['username'],
      role: json['role'] ?? 'user',
      jabatan: json['jabatan'],
      phone: json['phone'] ?? json['no_wa'],
      avatar: json['avatar'],
      latitude: json['latitude'] != null ? double.tryParse(json['latitude'].toString()) : null,
      longitude: json['longitude'] != null ? double.tryParse(json['longitude'].toString()) : null,
      lastActive: json['last_seen_at'] ?? json['updated_at'],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'email': email,
      'pangkat': pangkat,
      'korps': korps,
      'nrp': nrp,
      'role': role,
      'jabatan': jabatan,
      'phone': phone,
      'avatar': avatar,
      'latitude': latitude,
      'longitude': longitude,
    };
  }

  String get fullIdentity => [
    if (pangkat != null && pangkat!.isNotEmpty) pangkat,
    if (korps != null && korps!.isNotEmpty) korps,
    name,
    if (nrp != null && nrp!.isNotEmpty) 'NRP $nrp',
  ].join(' ');
}