class SkhppModel {
  final int id;
  final String? noSkhpp;
  final String nama;
  final String? pangkatKorpsNrp;
  final String? jabatan;
  final String? kesatuan;
  final String? peruntukan;
  final String kategori; // militer, sipil, nikah
  final String status;   // draft, pending, approved, rejected
  final String? barcodeString;
  final String? signedAt;
  final String? tanggalSurat;
  final String? createdBy;

  SkhppModel({
    required this.id,
    this.noSkhpp,
    required this.nama,
    this.pangkatKorpsNrp,
    this.jabatan,
    this.kesatuan,
    this.peruntukan,
    required this.kategori,
    required this.status,
    this.barcodeString,
    this.signedAt,
    this.tanggalSurat,
    this.createdBy,
  });

  factory SkhppModel.fromJson(Map<String, dynamic> json) {
    return SkhppModel(
      id: json['id'] ?? 0,
      noSkhpp: json['no_skhpp'],
      nama: json['nama'] ?? '',
      pangkatKorpsNrp: json['pangkat_korps_nrp'],
      jabatan: json['jabatan'],
      kesatuan: json['kesatuan'],
      peruntukan: json['peruntukan'],
      kategori: json['kategori'] ?? 'militer',
      status: json['status'] ?? 'pending',
      barcodeString: json['barcode_string'],
      signedAt: json['signed_at'],
      tanggalSurat: json['tanggal_surat'] ?? json['created_at'],
      createdBy: json['creator'] != null ? json['creator']['name'] : null,
    );
  }
}