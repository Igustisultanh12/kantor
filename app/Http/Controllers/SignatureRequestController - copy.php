<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\SignatureRequest;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use setasign\Fpdi\Fpdi;

class SignatureRequestController extends Controller
{
    public function index() {
        $user = auth()->user();
        $query = SignatureRequest::with('user');

        if ($user->role !== 'admin' && $user->role !== 'komandan') {
            $query->where('user_id', $user->id);
        }

        $requests = $query->latest()->paginate(15)->withQueryString();
        return Inertia::render('Signature/Index', ['requests' => $requests]);
    }

    public function store(Request $request) {
        $request->validate([
            'subject' => 'required|string|max:255',
            'letter_number' => 'nullable|string|max:100',
            'file' => 'required|mimes:pdf|max:10240', 
            'x' => 'nullable|numeric',
            'y' => 'nullable|numeric',
            'width' => 'nullable|numeric',
            
        ]);

        try {
            if (!Storage::disk('public')->exists('signature_reqs')) {
                Storage::disk('public')->makeDirectory('signature_reqs');
            }

            $path = $request->file('file')->store('signature_reqs', 'public');
            
            $signatureRequest = SignatureRequest::create([
                'user_id' => auth()->id(),
                'subject' => $request->subject,
                'letter_number' => $request->letter_number,
                'file_path' => $path,
                'status' => 'pending',
                'x' => $request->x ?? 0.5,
                'y' => $request->y ?? 0.5,
                'width' => $request->width ?? 0.2,
            ]);

            $komandan = User::where('role', 'komandan')->first();
            if ($komandan && $komandan->phone) {
                $pesan = " *SI SINDEN: PEMBERITAHUAN*\n\n" .
                         "Mohon izin Komandan, terdapat pengajuan berkas baru:\n\n" .
                         " *Perihal:* {$request->subject}\n" .
                         " *Pengaju:* " . auth()->user()->name . "\n\n" .
                         "Mohon izin untuk memeriksa berkas di Laman : https://sisinden.my.id/signature-requests";
                         
                WhatsappService::sendMessage($komandan->phone, $pesan);
            }

            return back()->with('message', 'Permintaan Berhasil Dikirim.');
        } catch (\Exception $e) {
            Log::error("Sinden Store Error: " . $e->getMessage());
            return back()->with('error', 'Gagal mengirim berkas.');
        }
    }

    public function update(Request $request, SignatureRequest $signatureRequest) {
        $user = auth()->user();

        // 1. LOGIKA REVISI (STAF UNGGAH ULANG)
        if ($user->id === $signatureRequest->user_id && $signatureRequest->status === 'rejected') {
            $request->validate([
                'file' => 'required|mimes:pdf|max:10240',
                'x' => 'nullable|numeric', 'y' => 'nullable|numeric', 'width' => 'nullable|numeric',
            ]);
            
            try {
                if (Storage::disk('public')->exists($signatureRequest->file_path)) {
                    Storage::disk('public')->delete($signatureRequest->file_path);
                }
                $newPath = $request->file('file')->store('signature_reqs', 'public');
                $signatureRequest->update([
                    'file_path' => $newPath, 
                    'status' => 'pending', 
                    'note' => null,
                    'x' => $request->x ?? $signatureRequest->x,
                    'y' => $request->y ?? $signatureRequest->y,
                    'width' => $request->width ?? $signatureRequest->width,
                ]);

                $komandan = User::where('role', 'komandan')->first();
                if ($komandan && $komandan->phone) {
                    $pesanRev = " *SI SINDEN: BERKAS TELAH DIREVISI*\n\n" .
                                "Mohon izin Komandan, berkas yang sebelumnya ditolak telah diperbaiki oleh staf:\n\n" .
                                " *Perihal:* {$signatureRequest->subject}\n" .
                                " *Oleh:* {$user->name}\n\n" .
                                "Mohon izin untuk memeriksa berkas di : https://sisinden.my.id/signature-request";
                    WhatsappService::sendMessage($komandan->phone, $pesanRev);
                }

                return back()->with('message', 'Berkas revisi berhasil diunggah.');
            } catch (\Exception $e) { return back()->with('error', 'Gagal revisi.'); }
        }

        // 2. LOGIKA PENGESAHAN (KOMANDAN/ADMIN)
        if ($user->role !== 'admin' && $user->role !== 'komandan') {
            return back()->with('error', 'Otoritas Ditolak.');
        }

        $request->validate([
            'status' => 'required|in:approved,rejected',
            'note' => 'nullable|string|max:500',
            'x' => 'nullable|numeric',
            'y' => 'nullable|numeric',
            'width' => 'nullable|numeric',
        ]);

        try {
            DB::beginTransaction();

            if ($request->status === 'approved') {
                $originalPath = storage_path('app/public/' . $signatureRequest->file_path);
                $signatureKey = Setting::where('key', 'commander_signature')->first();
                $signatureImg = storage_path('app/public/' . ($signatureKey->value ?? 'signatures/komandan_ttd.png'));

                if (!file_exists($signatureImg)) throw new \Exception('File TTD tidak ditemukan.');

                $pdf = new Fpdi();
                $pageCount = $pdf->setSourceFile($originalPath);

                $xRatio = $request->x ?? $signatureRequest->x;
                $yRatio = $request->y ?? $signatureRequest->y;
                $wRatio = $request->width ?? $signatureRequest->width;

                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    $templateId = $pdf->importPage($pageNo);
                    $size = $pdf->getTemplateSize($templateId);
                    $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $pdf->useTemplate($templateId);

                    if ($pageNo === $pageCount) {
                        $pdfW = $size['width'];
                        $pdfH = $size['height'];
                        $posX = $xRatio * $pdfW;
                        $posY = $yRatio * $pdfH;
                        $ttdW = $wRatio * $pdfW;
                        $pdf->Image($signatureImg, $posX, $posY, $ttdW, 0, 'PNG');
                    }
                }

                $newFileName = 'signed_' . time() . '_' . basename($signatureRequest->file_path);
                $newPath = 'signature_reqs/' . $newFileName;
                $pdf->Output(storage_path('app/public/' . $newPath), 'F');

                if (Storage::disk('public')->exists($signatureRequest->file_path)) {
                    Storage::disk('public')->delete($signatureRequest->file_path);
                }
                
                $signatureRequest->file_path = $newPath;
                $signatureRequest->x = $xRatio;
                $signatureRequest->y = $yRatio;
                $signatureRequest->width = $wRatio;
            }

            $signatureRequest->status = $request->status;
            $signatureRequest->note = $request->note;
            $signatureRequest->save();

            $targetUser = $signatureRequest->user;
            if ($targetUser && $targetUser->phone) {
                if ($request->status === 'approved') {
                    $statusMsg = " *TELAH DISAHKAN*";
                    $ket = "Silakan unduh berkas Anda.";
                } else {
                    $statusMsg = " *DITOLAK / PERLU REVISI*";
                    $ket = "Alasan: _" . ($request->note ?? '-') . "_";
                }
                
                $pesanWA = " *SI SINDEN: STATUS BERKAS*\n\n" .
                           "Berkas: *{$signatureRequest->subject}*\n" .
                           "Status: {$statusMsg}\n\n" .
                           " {$ket}";
                WhatsappService::sendMessage($targetUser->phone, $pesanWA);
            }

            AuditLog::create([
                'user_id' => $user->id, 'admin_name' => $user->name,
                'action' => 'TTD_DECISION', 'target_personnel' => $signatureRequest->subject,
                'description' => strtoupper($request->status) . " berkas pengajuan.",
                'ip_address' => $request->ip(),
            ]);

            DB::commit();
            return back()->with('message', 'Proses Berhasil.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Sinden Error: " . $e->getMessage());
            return back()->with('error', 'Gagal memproses pengesahan.');
        }
    }

    public function destroy(SignatureRequest $signatureRequest) {
        if ($signatureRequest->file_path && Storage::disk('public')->exists($signatureRequest->file_path)) {
            Storage::disk('public')->delete($signatureRequest->file_path);
        }
        $signatureRequest->delete();
        return back()->with('message', 'Data Dihapus.');
    }
    
    public function clearAll() {
        if (auth()->user()->role !== 'admin') {
            return back()->with('error', 'Otoritas Ditolak.');
        }

        try {
            $requests = SignatureRequest::all();
            foreach ($requests as $req) {
                if ($req->file_path && Storage::disk('public')->exists($req->file_path)) {
                    Storage::disk('public')->delete($req->file_path);
                }
                $req->delete();
            }
            return back()->with('message', 'Seluruh data riwayat telah dibersihkan.');
        } catch (\Exception $e) {
            Log::error("Clear All Error: " . $e->getMessage());
            return back()->with('error', 'Gagal membersihkan data.');
        }
    }
}