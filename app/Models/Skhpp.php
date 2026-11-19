<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Skhpp extends Model
{
    use HasFactory;

    protected $table = 'skhpps';

    protected $fillable = [
        'kategori_personel',
        'is_pernikahan',
        'nomor_skhpp',
        'nomor_urut',
        'bulan_romawi',
        'tahun',
        'tanggal_skhpp',
        'surat_pengantar',
        'nama',
        'pangkat_korps_nrp',
        'nik',
        'jenis_identitas',
        'letter_sub_category_id',
        'nomor_manual',
        'nomor_tulis_tangan',
        'jabatan_pekerjaan',
        'tempat_lahir',
        'tanggal_lahir',
        'jenis_kelamin',
        'agama',
        'alamat',
        'has_pengikut',
        'satuan',
        'peruntukan',
        'kegiatan',
        'foto_1',
        'foto_2',
        'label_foto_1',
        'label_foto_2',
        'status',
        'ttd_mode',
        'signed_file',
        'signed_uploaded_by',
        'signed_uploaded_at',
        'verification_code',
        'submitted_by',
        'operator_name',
        'operator_nrp_pangkat',
        'submitted_at',
        'approved_by',
        'approved_at',
        'catatan_revisi',
    ];

    protected $casts = [
        'is_pernikahan' => 'boolean',
        'has_pengikut' => 'boolean',
        'nomor_tulis_tangan' => 'boolean',
        'tanggal_lahir' => 'date',
        'tanggal_skhpp' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'signed_uploaded_at' => 'datetime',
    ];

    // Path berkas bertanda tangan tidak dikirim ke browser; cukup penanda ada/tidaknya.
    protected $hidden = ['signed_file'];
    protected $appends = ['has_signed_file'];

    public function getHasSignedFileAttribute(): bool
    {
        return !empty($this->attributes['signed_file'] ?? null);
    }

    /** Awalan poin 3; operator cukup mengisi kegiatannya (mis. "Seleksi Diktukpa LVI/56 TA. 2026"). */
    public const AWALAN_PERUNTUKAN = 'dalam rangka melengkapi administrasi ';

    /** Judul lampiran pengikut (sama dengan judulLampiran() di resources/js/Utils/skhppIdentitas.js). */
    public function judulLampiran(): string
    {
        $satuan = trim((string) $this->satuan);
        $kegiatan = trim((string) $this->kegiatan);
        // Data lama tanpa kolom kegiatan: ambil dari peruntukan bila berawalan baku.
        if ($kegiatan === '' && stripos((string) $this->peruntukan, self::AWALAN_PERUNTUKAN) === 0) {
            $kegiatan = rtrim(trim(substr($this->peruntukan, strlen(self::AWALAN_PERUNTUKAN))), '.');
        }
        if ($satuan === '' || $kegiatan === '') {
            return 'DAFTAR NAMA-NAMA ANGGOTA PENGIKUT';
        }
        return mb_strtoupper("DAFTAR NAMA PERSONEL {$satuan} YANG MELAKSANAKAN SC {$kegiatan}");
    }

    /** Jenis identitas; data lama tanpa jenis_identitas ditebak dari kolom terisi (sama dengan Utils/skhppIdentitas.js). */
    public function jenisIdentitasNaskah(): string
    {
        return $this->jenis_identitas ?: ($this->pangkat_korps_nrp ? 'NRP' : 'NIK');
    }

    /** Label kolom identitas pada tabel lampiran pengikut. */
    public function labelIdentitasLampiran(): string
    {
        return ['NRP' => 'Pangkat/Korp/NRP', 'NIP' => 'Pangkat/Gol/NIP', 'NIM' => 'NIM'][$this->jenisIdentitasNaskah()] ?? 'NIK';
    }

    public function jumlahPengikut(): int
    {
        return $this->has_pengikut ? count($this->members) : 0;
    }

    /**
     * Baris data personel naskah SKHPP (label => isi), urutan mengikuti naskah acuan.
     * Dipakai bersama oleh PDF (pdf/skhpp.blade.php) dan DOCX (App\Services\SkhppDocx).
     */
    public function barisDataNaskah(string $tanggalLahirIndo): array
    {
        $jenisId = $this->jenisIdentitasNaskah();
        $berpangkat = in_array($jenisId, ['NRP', 'NIP']);

        $baris = ['Nama' => $this->nama];
        if ($berpangkat) {
            $baris[$jenisId === 'NIP' ? 'Pangkat/Gol/NIP' : 'Pangkat/Korp/NRP'] = $this->pangkat_korps_nrp;
            $baris['Jabatan'] = $this->jabatan_pekerjaan;
        } else {
            $baris[$jenisId === 'NIM' ? 'NIM' : 'NIK'] = $this->nik ?: '-';
        }
        $baris['Tempat/tgl lahir'] = $this->tempat_lahir . ', ' . $tanggalLahirIndo;
        $baris['Jenis kelamin'] = $this->jenis_kelamin;
        $baris['Agama/kepercayaan'] = $this->agama;
        if (!$berpangkat) {
            $baris['Pekerjaan'] = $this->jabatan_pekerjaan;
        }
        $baris['Alamat rumah/tlp'] = $this->alamat;
        if ($this->jumlahPengikut() > 0) {
            $baris['Pengikut'] = $this->jumlahPengikut() . ' orang (daftar nama terlampir)';
        }

        return $baris;
    }

    public function members()
    {
        return $this->hasMany(SkhppMember::class, 'skhpp_id')->orderBy('no_urut', 'asc');
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
