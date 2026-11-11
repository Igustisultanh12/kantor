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
        /* Gaya disamakan dengan halaman Login SISINDEN: aurora gelap + kartu kaca */
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Figtree', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        :root { --ease: cubic-bezier(.22, 1, .36, 1); }
        html { background: #050b1a; -webkit-tap-highlight-color: transparent; }
        body {
            background: #050b1a; color: #e2e8f0; min-height: 100vh; padding: 24px 14px;
            display: flex; flex-direction: column; align-items: center; justify-content: flex-start;
            position: relative; overflow-x: hidden;
        }
        ::selection { background: rgba(96, 165, 250, .35); color: #fff; }

        /* ---------- Latar aurora ---------- */
        .bg { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; }
        .aurora { position: absolute; border-radius: 50%; filter: blur(90px); opacity: .55; animation: aurora 22s ease-in-out infinite alternate; will-change: transform; }
        .aurora.a1 { width: 90vmax; height: 90vmax; left: -35vmax; top: -45vmax; background: radial-gradient(circle, #1d4ed8 0%, transparent 65%); }
        .aurora.a2 { width: 70vmax; height: 70vmax; right: -30vmax; top: 15vh; background: radial-gradient(circle, #4f46e5 0%, transparent 65%); animation-delay: -7s; animation-duration: 26s; }
        .aurora.a3 { width: 70vmax; height: 70vmax; left: 10vw; bottom: -45vmax; background: radial-gradient(circle, #0d9488 0%, transparent 65%); opacity: .4; animation-delay: -13s; animation-duration: 30s; }
        @keyframes aurora {
            0% { transform: translate3d(0, 0, 0) scale(1) rotate(0deg); }
            50% { transform: translate3d(6vw, 4vh, 0) scale(1.12) rotate(20deg); }
            100% { transform: translate3d(-4vw, -3vh, 0) scale(.96) rotate(-10deg); }
        }

        /* ---------- Kartu kaca ---------- */
        .container {
            position: relative; z-index: 1; width: 100%; max-width: 580px; margin-bottom: 20px;
            border-radius: 28px; overflow: hidden; color: #fff;
            background: linear-gradient(160deg, rgba(30, 41, 70, .72), rgba(10, 17, 36, .8));
            -webkit-backdrop-filter: blur(28px) saturate(1.6); backdrop-filter: blur(28px) saturate(1.6);
            border: 1px solid rgba(255, 255, 255, .12);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, .12), 0 30px 80px -24px rgba(0, 0, 0, .75), 0 8px 24px -12px rgba(2, 6, 23, .6);
            animation: cardIn .8s var(--ease) both;
        }
        .container::before {
            content: ''; position: absolute; left: 12%; right: 12%; top: 0; height: 1px; z-index: 2;
            background: linear-gradient(90deg, transparent, rgba(147, 197, 253, .7), transparent);
        }
        @keyframes cardIn { from { opacity: 0; transform: translateY(40px) scale(.95); filter: blur(10px); } to { opacity: 1; transform: none; filter: none; } }

        .header-banner {
            padding: 30px 22px 24px; text-align: center; position: relative;
            border-bottom: 1px solid rgba(255, 255, 255, .08);
            background: linear-gradient(180deg, rgba(59, 130, 246, .12), transparent);
        }
        .header-logo {
            width: 76px; height: 76px; margin: 0 auto 14px; position: relative;
            display: flex; align-items: center; justify-content: center;
        }
        .header-logo::before {
            content: ''; position: absolute; inset: -40%; border-radius: 50%;
            background: radial-gradient(circle, rgba(59, 130, 246, .4), transparent 65%); filter: blur(14px);
            animation: halo 6s ease-in-out infinite;
        }
        @keyframes halo { 0%, 100% { opacity: .55; transform: scale(.95); } 50% { opacity: .95; transform: scale(1.06); } }
        .header-logo img { position: relative; max-width: 100%; max-height: 100%; object-fit: contain; filter: drop-shadow(0 10px 18px rgba(0, 0, 0, .5)); }

        .kop-instansi { font-size: 11px; font-weight: 700; letter-spacing: 1.6px; text-transform: uppercase; color: #93c5fd; margin-bottom: 4px; }
        .kop-unit { font-size: 13px; font-weight: 800; letter-spacing: 1.2px; text-transform: uppercase; color: #fff; margin-bottom: 14px; }
        .meeting-title { font-size: 21px; font-weight: 800; line-height: 1.3; letter-spacing: -.01em; color: #fff; margin-bottom: 16px; text-wrap: balance; }

        .meta-badges { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; }
        .badge-info {
            background: rgba(255, 255, 255, .06); border: 1px solid rgba(255, 255, 255, .12); border-radius: 999px;
            padding: 6px 13px; font-size: 11.5px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; color: #cbd5e1;
        }
        .badge-info svg { color: #7dd3fc; }

        .form-content { padding: 24px 22px 26px; }

        /* ---------- Status ---------- */
        .status-closed-banner {
            background: rgba(244, 63, 94, .1); border: 1px solid rgba(251, 113, 133, .3); color: #fda4af;
            padding: 22px 16px; border-radius: 20px; text-align: center; margin-bottom: 8px;
        }
        .status-closed-banner h3 { font-size: 16px; font-weight: 800; margin-bottom: 6px; color: #fecdd3; }
        .status-closed-banner p { font-size: 13px; line-height: 1.5; color: #fda4af; }

        .success-banner {
            background: rgba(16, 185, 129, .1); border: 1px solid rgba(52, 211, 153, .3); color: #a7f3d0;
            padding: 24px 18px; border-radius: 22px; text-align: center; margin-bottom: 8px; animation: fadeIn .5s var(--ease);
        }
        .success-icon {
            width: 56px; height: 56px; background: #10b981; color: #fff; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; margin: 0 auto 12px;
            box-shadow: 0 0 0 6px rgba(16, 185, 129, .15), 0 10px 24px rgba(16, 185, 129, .35);
        }
        .success-icon svg { width: 30px; height: 30px; }
        .success-banner h3 { font-size: 17px; font-weight: 800; color: #6ee7b7; margin-bottom: 6px; }
        .success-banner p { font-size: 13.5px; line-height: 1.5; color: #a7f3d0; margin-bottom: 16px; }
        .success-details {
            background: rgba(255, 255, 255, .05); border: 1px solid rgba(255, 255, 255, .1); border-radius: 14px;
            padding: 12px 14px; text-align: left; font-size: 12.5px; color: #cbd5e1; margin-bottom: 16px;
        }
        .success-details strong { color: #fff; }
        .success-details div { margin-bottom: 6px; }
        .success-details div:last-child { margin-bottom: 0; }

        .error-box {
            background: rgba(244, 63, 94, .1); border: 1px solid rgba(251, 113, 133, .35); color: #fda4af;
            padding: 12px 14px; border-radius: 14px; font-size: 13px; margin-bottom: 18px;
        }
        .error-box strong { color: #fecdd3; }
        .error-box ul { margin-left: 20px; margin-top: 4px; }

        /* ---------- Form ---------- */
        .form-group { margin-bottom: 16px; animation: fadeIn .6s var(--ease) both; }
        .form-group:nth-child(2) { animation-delay: .05s; } .form-group:nth-child(3) { animation-delay: .1s; }
        .form-group:nth-child(4) { animation-delay: .15s; } .form-group:nth-child(5) { animation-delay: .2s; }
        .form-label { display: block; font-size: 12.5px; font-weight: 700; color: #cbd5e1; margin-bottom: 7px; letter-spacing: .01em; }
        .form-label .required-dot { color: #fb7185; margin-left: 2px; }
        .form-control {
            width: 100%; padding: 13px 15px; border-radius: 16px; font-size: 15px; color: #fff; outline: none;
            background: rgba(255, 255, 255, .06); border: 1px solid rgba(255, 255, 255, .12);
            transition: border-color .25s var(--ease), background-color .25s, box-shadow .3s var(--ease);
        }
        .form-control::placeholder { color: #64748b; }
        .form-control:hover { background: rgba(255, 255, 255, .08); }
        .form-control:focus { border-color: rgba(96, 165, 250, .85); background: rgba(255, 255, 255, .09); box-shadow: 0 0 0 4px rgba(59, 130, 246, .22); }
        .form-control:-webkit-autofill { -webkit-text-fill-color: #fff; -webkit-box-shadow: 0 0 0 1000px #17213a inset; caret-color: #fff; }
        select.form-control { appearance: none; -webkit-appearance: none; padding-right: 40px; cursor: pointer;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='none' stroke='%2394a3b8' stroke-width='2' viewBox='0 0 24 24'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 14px center; }
        select.form-control option { background: #0f172a; color: #fff; }
        textarea.form-control { resize: vertical; min-height: 90px; }
        .form-hint { font-size: 11.5px; color: #94a3b8; margin-top: 6px; line-height: 1.45; }

        /* Kotak paraf tetap terang: goresan gelap & dicetak di PDF berlatar putih */
        .signature-box {
            border: 1px solid rgba(255, 255, 255, .2); border-radius: 16px; background: #f8fafc;
            position: relative; touch-action: none; overflow: hidden;
            box-shadow: inset 0 2px 8px rgba(15, 23, 42, .12);
        }
        .signature-canvas { width: 100%; height: 170px; display: block; cursor: crosshair; }
        .btn-clear-sig {
            position: absolute; top: 8px; right: 8px; background: rgba(255, 255, 255, .95); border: 1px solid #cbd5e1;
            border-radius: 10px; padding: 5px 11px; font-size: 11.5px; font-weight: 600; color: #475569; cursor: pointer;
        }
        .btn-clear-sig:hover { color: #e11d48; border-color: #fda4af; }

        .btn-submit {
            width: 100%; height: 52px; margin-top: 8px; padding: 0 20px; border: none; border-radius: 16px; cursor: pointer;
            background: #2563eb; color: #fff; font-size: 15px; font-weight: 700; letter-spacing: .2px;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            box-shadow: 0 10px 24px -12px rgba(0, 0, 0, .7), inset 0 1px 0 rgba(255, 255, 255, .25);
            transition: background-color .2s, transform .2s var(--ease);
        }
        .btn-submit:hover { background: #3b82f6; }
        .btn-submit:active { transform: scale(.98); }
        .btn-submit:disabled { opacity: .6; cursor: wait; }

        .btn-again {
            display: inline-block; background: #059669; color: #fff; text-decoration: none; padding: 11px 18px;
            border-radius: 14px; font-size: 13px; font-weight: 700; cursor: pointer; border: none;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, .2), 0 8px 18px -10px rgba(5, 150, 105, .8);
        }
        .btn-again:hover { background: #10b981; }

        :where(a, button, input, select, textarea):focus-visible { outline: 3px solid #60a5fa; outline-offset: 2px; }

        .footer-note { position: relative; z-index: 1; text-align: center; font-size: 11.5px; color: #94a3b8; margin-top: 6px; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }

        @media (prefers-reduced-motion: reduce) {
            .aurora, .header-logo::before, .container, .form-group, .success-banner { animation: none !important; }
        }
    </style>
</head>
<body>

    <div class="bg" aria-hidden="true"><div class="aurora a1"></div><div class="aurora a2"></div><div class="aurora a3"></div></div>

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
                    <div class="error-box">
                        <strong>Perhatian:</strong>
                        <ul>
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
                document.getElementById('submitBtn').disabled = true;
                if (hasDrawn) {
                    signatureInput.value = canvas.toDataURL('image/png');
                }
            });
        });
    </script>
    @endif

</body>
</html>
