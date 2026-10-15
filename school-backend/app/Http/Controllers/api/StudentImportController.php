<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StudentImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class StudentImportController extends Controller
{
    protected StudentImportService $importService;

    public function __construct(StudentImportService $importService)
    {
        $this->importService = $importService;
    }

    /**
     * B5: Unduh template Excel standar siswa
     */
    public function downloadTemplate()
    {
        $content = $this->importService->generateTemplate();

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="Template_Import_Siswa_MAN11.xlsx"',
        ]);
    }

    /**
     * B2 & B3: Pratinjau & Eksekusi Import Siswa
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // Max 10MB
        ]);

        $isPreview = $request->boolean('preview', false);
        $file = $request->file('file');

        $result = $this->importService->processFile($file, ! $isPreview);

        // Jika mode simpan dan ada baris yang gagal, simpan sementara ke cache agar bisa diunduh
        if (! $isPreview && ! empty($result['failed_rows'])) {
            $reportKey = 'student_import_failed_' . auth()->id();
            Cache::put($reportKey, $result['failed_rows'], now()->addHours(2));
            $result['has_failed_report'] = true;
        }

        return response()->json($result);
    }

    /**
     * B4: Unduh Laporan Baris yang Gagal Diimpor
     */
    public function downloadFailedReport(Request $request)
    {
        $reportKey = 'student_import_failed_' . auth()->id();
        $failedRows = Cache::get($reportKey);

        // Bila dikirim via POST body
        if (empty($failedRows) && $request->has('failed_rows')) {
            $failedRows = $request->input('failed_rows');
        }

        if (empty($failedRows)) {
            return response()->json(['message' => 'Tidak ada data laporan baris gagal yang tersedia.'], 404);
        }

        $content = $this->importService->generateFailedReport($failedRows);

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="Laporan_Gagal_Import_Siswa.xlsx"',
        ]);
    }
}
