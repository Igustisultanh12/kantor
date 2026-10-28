<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Presensi Kehadiran - {{ $meeting->title }}</title>
    
    <!-- Dynamic Favicon Web -->
    <link rel="icon" type="image/png" href="{{ asset('images/logo_sinden_app_icon.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Figtree', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #1e293b;
            min-height: 100vh;
            padding: 20px 14px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
        }

        .container {
            width: 100%;
            max-width: 580px;
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.35);
            overflow: hidden;
            margin-bottom: 24px;
        }

        .header-banner {
            background: linear-gradient(135deg, #1e3a8a 0%, #0f172a 100%);
            color: #ffffff;
            padding: 28px 20px 24px 20px;
            text-align: center;
            position: relative;
        }

        .header-logo {
            width: 68px;
            height: 68px;
            border-radius: 16px;
            background: #ffffff;
            padding: 6px;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.25);
            margin: 0 auto 12px auto;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .header-logo img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .kop-instansi {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #93c5fd;
            margin-bottom: 4px;
        }

        .kop-unit {
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #ffffff;
            margin-bottom: 12px;
        }

        .meeting-title {
            font-size: 18px;
            font-weight: 800;
            line-height: 1.35;
            color: #f8fafc;
            margin-bottom: 14px;
        }

        .meta-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
        }

        .badge-info {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 20px;
            padding: 6px 14px;
            font-size: 11.5px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #e2e8f0;
        }

        .form-content {
            padding: 24px 20px;
        }

        .status-closed-banner {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 20px 16px;
            border-radius: 16px;
            text-align: center;
            margin-bottom: 16px;
        }

        .status-closed-banner h3 {
            font-size: 16px;
            font-weight: 800;
            margin-bottom: 6px;
        }

        .status-closed-banner p {
            font-size: 13px;
            line-height: 1.4;
            color: #7f1d1d;
        }

        .success-banner {
            background: #f0fdf4;
            border: 1.5px solid #86efac;
            color: #14532d;
            padding: 24px 18px;
            border-radius: 20px;
            text-align: center;
            margin-bottom: 20px;
            animation: fadeIn 0.4s ease-out;
        }

        .success-icon {
            width: 54px;
            height: 54px;
            background: #22c55e;
            color: #ffffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px auto;
            box-shadow: 0 8px 16px rgba(34, 197, 94, 0.3);
        }

        .success-icon svg {
            width: 32px;
            height: 32px;
        }

        .success-banner h3 {
            font-size: 17px;
            font-weight: 800;
            color: #15803d;
            margin-bottom: 6px;
        }

        .success-banner p {
            font-size: 13.5px;
            color: #166534;
            margin-bottom: 16px;
        }

        .success-details {
            background: #ffffff;
            border-radius: 12px;
            padding: 12px 14px;
            text-align: left;
            font-size: 12.5px;
            color: #334155;
            margin-bottom: 16px;
            border: 1px solid #bbf7d0;
        }

        .success-details div {
            margin-bottom: 6px;
        }
        .success-details div:last-child {
            margin-bottom: 0;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: #334155;
            margin-bottom: 6px;
        }

        .form-label .required-dot {
            color: #ef4444;
            margin-left: 2px;
        }

        .form-control {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid #cbd5e1;
            border-radius: 12px;
            font-size: 14px;
            color: #0f172a;
            outline: none;
            transition: all 0.2s ease;
            background: #f8fafc;
        }

        .form-control:focus {
            background: #ffffff;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        .form-hint {
            font-size: 11px;
            color: #64748b;
            margin-top: 4px;
        }

        /* Canvas Paraf / Tanda Tangan */
        .signature-box {
            border: 1.5px dashed #94a3b8;
            border-radius: 12px;
            background: #f8fafc;
            position: relative;
            touch-action: none;
            overflow: hidden;
        }

        .signature-canvas {
            width: 100%;
            height: 160px;
            display: block;
            cursor: crosshair;
        }

        .btn-clear-sig {
            position: absolute;
            top: 8px;
            right: 8px;
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
        }

        .btn-clear-sig:hover {
            background: #ffffff;
            color: #ef4444;
            border-color: #fca5a5;
        }

        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff;
            border: none;
            border-radius: 14px;
            padding: 14px 20px;
            font-size: 15px;
            font-weight: 800;
            letter-spacing: 0.3px;
            cursor: pointer;
            box-shadow: 0 10px 20px -5px rgba(37, 99, 235, 0.4);
            transition: all 0.2s ease;
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 25px -5px rgba(37, 99, 235, 0.5);
        }

        .btn-submit:active {
            transform: translateY(1px);
        }

        .btn-again {
            display: inline-block;
            background: #15803d;
            color: #ffffff;
            text-decoration: none;
            padding: 10px 18px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            border: none;
        }

        .footer-note {
            text-align: center;
            font-size: 11px;
            color: #94a3b8;
            margin-top: 16px;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

    <div class="container">
        
        <!-- HEADER KEDINASAN -->
        <div class="header-banner">
            <div class="header-logo">
                <img src="{{ asset('images/logo_sinden_app_icon.png') }}" alt="Logo SINDEN" onerror="this.src='{{ asset('images/logo_tni_al.png') }}'" />
            </div>

            <div class="kop-instansi">KOMANDO DAERAH TNI AL V</div>
            <div class="kop-unit">DETASEMEN INTELIJEN</div>
            
            <h1 class="meeting-title">{{ $meeting->title }}</h1>

            <div class="meta-badges">
                <span class="badge-info">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    {{ $meeting->formatted_date }}
                </span>
                @if($meeting->time_start)
                <span class="badge-info">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    {{ $meeting->time_start }} {{ $meeting->time_end ? 's.d. ' . $meeting->time_end : '' }} WIB
                </span>
                @endif
                @if($meeting->location)
                <span class="badge-info">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><circle cx="12" cy="11" r="3"></circle></svg>
                    {{ $meeting->location }}
                </span>
                @endif
            </div>
        </div>

        <div class="form-content">

            @if(session('attendee_success'))
                <!-- NOTIFIKASI BERHASIL SUBMIT -->
                <div class="success-banner">
                    <div class="success-icon">
                        <svg fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    <h3>Presensi Berhasil Dicatat!</h3>
                    <p>Terima kasih, data kehadiran Anda telah resmi terverifikasi ke sistem naskah rapat Detasemen Intelijen Kodaeral V.</p>

                    <div class="success-details">
                        <div><strong>Nama:</strong> {{ session('attendee_success.name') }}</div>
                        <div><strong>Pangkat / NRP:</strong> {{ session('attendee_success.pangkat_nrp') }}</div>
                        <div><strong>Jabatan:</strong> {{ session('attendee_success.jabatan') }}</div>
                        <div><strong>Waktu Hadir:</strong> {{ session('attendee_success.attended_at') }} WIB</div>
                    </div>

                    <a href="{{ route('absen.public', $meeting->uuid) }}" class="btn-again">Isi Presensi untuk Peserta Lain</a>
                </div>
            @elseif($meeting->status === 'closed')
                <!-- STATUS DITUTUP -->
                <div class="status-closed-banner">
                    <svg style="width: 44px; height: 44px; margin: 0 auto 10px auto; display: block;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    <h3>Sesi Absensi Telah Ditutup</h3>
                    <p>Panitia / Petugas telah menutup formulir presensi untuk agenda rapat ini. Jika Anda peserta yang baru hadir, silakan hubungi petugas sekretariat / operator di lokasi.</p>
                </div>
            @else
                <!-- FORMULIR PENGISIAN PRESENSI (PUBLIK TANPA LOGIN) -->
                @if($errors->any())
                    <div style="background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 12px 14px; border-radius: 12px; font-size: 13px; margin-bottom: 18px;">
                        <strong>Perhatian:</strong>
                        <ul style="margin-left: 20px; margin-top: 4px;">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form id="attendanceForm" action="{{ route('absen.submit', $meeting->uuid) }}" method="POST">
                    @csrf

                    <!-- 1. NAMA LENGKAP -->
                    <div class="form-group">
                        <label class="form-label" for="inputName">
                            Nama Lengkap <span class="required-dot">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="inputName" 
                            name="name" 
                            class="form-control" 
                            placeholder="Nama Lengkap" 
                            value="{{ old('name') }}" 
                            required 
                            autofocus 
                        />
                    </div>

                    <!-- 2. PANGKAT / NRP -->
                    <div class="form-group">
                        <label class="form-label" for="inputPangkatNrp">
                            Pangkat / NRP / NIP
                        </label>
                        <input 
                            type="text" 
                            id="inputPangkatNrp" 
                            name="pangkat_nrp" 
                            class="form-control" 
                            placeholder="Pangkat / NRP / NIP (isi '-' jika sipil)" 
                            value="{{ old('pangkat_nrp') }}" 
                        />
                    </div>

                    <!-- 3. JABATAN / INSTANSI -->
                    <div class="form-group">
                        <label class="form-label" for="inputJabatan">
                            Jabatan / Kesatuan / Instansi
                        </label>
                        <input 
                            type="text" 
                            id="inputJabatan" 
                            name="jabatan" 
                            class="form-control" 
                            placeholder="Jabatan / Kesatuan / Instansi" 
                            value="{{ old('jabatan') }}" 
                        />
                    </div>

                    <!-- 4. KOLOM KUSTOM DINAMIS DARI PETUGAS -->
                    @if(!empty($meeting->custom_fields) && is_array($meeting->custom_fields))
                        @foreach($meeting->custom_fields as $field)
                            @php
                                $fName = $field['name'];
                                $fLabel = $field['label'];
                                $fType = $field['type'] ?? 'text';
                                $fRequired = !empty($field['required']);
                                $fPlaceholder = $field['placeholder'] ?? '';
                            @endphp
                            <div class="form-group">
                                <label class="form-label" for="field_{{ $fName }}">
                                    {{ $fLabel }} @if($fRequired)<span class="required-dot">*</span>@endif
                                </label>

                                @if($fType === 'textarea')
                                    <textarea 
                                        id="field_{{ $fName }}" 
                                        name="custom[{{ $fName }}]" 
                                        class="form-control" 
                                        rows="3" 
                                        placeholder="{{ $fPlaceholder }}"
                                        @if($fRequired) required @endif
                                    >{{ old("custom.{$fName}") }}</textarea>
                                @elseif($fType === 'select' && !empty($field['options']))
                                    <select 
                                        id="field_{{ $fName }}" 
                                        name="custom[{{ $fName }}]" 
                                        class="form-control" 
                                        @if($fRequired) required @endif
                                    >
                                        <option value="">-- Pilih {{ $fLabel }} --</option>
                                        @foreach($field['options'] as $opt)
                                            <option value="{{ $opt }}" {{ old("custom.{$fName}") == $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input 
                                        type="{{ in_array($fType, ['number', 'tel', 'email']) ? $fType : 'text' }}" 
                                        id="field_{{ $fName }}" 
                                        name="custom[{{ $fName }}]" 
                                        class="form-control" 
                                        placeholder="{{ $fPlaceholder }}" 
                                        value="{{ old("custom.{$fName}") }}" 
                                        @if($fRequired) required @endif 
                                    />
                                @endif
                            </div>
                        @endforeach
                    @endif

                    <!-- 5. PARAF / TANDA TANGAN DIGITAL (JIKA DIAKTIFKAN) -->
                    @if($meeting->require_signature)
                        <div class="form-group">
                            <label class="form-label">
                                Tanda Tangan / Paraf Digital di Layar HP
                            </label>
                            <div class="signature-box">
                                <canvas id="signatureCanvas" class="signature-canvas"></canvas>
                                <button type="button" id="clearSigBtn" class="btn-clear-sig">Ulangi / Hapus</button>
                            </div>
                            <input type="hidden" name="signature" id="signatureInput" />
                            <div class="form-hint">Bubuhkan tanda tangan atau paraf Anda menggunakan jari langsung pada kotak di atas.</div>
                        </div>
                    @endif

                    <button type="submit" class="btn-submit" id="submitBtn">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                        <span>Kirim Presensi Kehadiran</span>
                    </button>
                </form>
            @endif

        </div>

    </div>

    <div class="footer-note">
        Sistem Informasi Presensi Kedinasan &bull; Detasemen Intelijen Kodaeral V
    </div>

    @if($meeting->require_signature && $meeting->status === 'open' && !session('attendee_success'))
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const canvas = document.getElementById('signatureCanvas');
            const clearBtn = document.getElementById('clearSigBtn');
            const signatureInput = document.getElementById('signatureInput');
            const form = document.getElementById('attendanceForm');
            if (!canvas) return;

            const ctx = canvas.getContext('2d');
            let isDrawing = false;
            let hasDrawn = false;

            function resizeCanvas() {
                const rect = canvas.getBoundingClientRect();
                const dpr = window.devicePixelRatio || 1;
                canvas.width = rect.width * dpr;
                canvas.height = rect.height * dpr;
                ctx.scale(dpr, dpr);
                ctx.strokeStyle = '#0f172a';
                ctx.lineWidth = 2.5;
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';
            }

            resizeCanvas();
            window.addEventListener('resize', resizeCanvas);

            function getPos(e) {
                const rect = canvas.getBoundingClientRect();
                const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                return {
                    x: clientX - rect.left,
                    y: clientY - rect.top
                };
            }

            function startDraw(e) {
                isDrawing = true;
                const pos = getPos(e);
                ctx.beginPath();
                ctx.moveTo(pos.x, pos.y);
                e.preventDefault();
            }

            function draw(e) {
                if (!isDrawing) return;
                const pos = getPos(e);
                ctx.lineTo(pos.x, pos.y);
                ctx.stroke();
                hasDrawn = true;
                e.preventDefault();
            }

            function stopDraw() {
                if (isDrawing) {
                    isDrawing = false;
                    ctx.closePath();
                }
            }

            canvas.addEventListener('mousedown', startDraw);
            canvas.addEventListener('mousemove', draw);
            window.addEventListener('mouseup', stopDraw);

            canvas.addEventListener('touchstart', startDraw, { passive: false });
            canvas.addEventListener('touchmove', draw, { passive: false });
            window.addEventListener('touchend', stopDraw);

            clearBtn.addEventListener('click', function () {
                const rect = canvas.getBoundingClientRect();
                ctx.clearRect(0, 0, rect.width, rect.height);
                hasDrawn = false;
                signatureInput.value = '';
            });

            form.addEventListener('submit', function () {
                if (hasDrawn) {
                    signatureInput.value = canvas.toDataURL('image/png');
                }
            });
        });
    </script>
    @endif

</body>
</html>
