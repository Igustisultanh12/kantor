<?php



namespace App\Http\Controllers;



use App\Models\AuditLog;

use Inertia\Inertia;



class AuditLogController extends Controller

{

    public function index()

    {

        return Inertia::render('AuditLogs/Index', [

            'logs' => AuditLog::with('user')

                ->latest()

                ->paginate(20)

        ]);

    }

}