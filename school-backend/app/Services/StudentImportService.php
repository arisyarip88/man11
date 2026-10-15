<?php

namespace App\Services;

use App\Models\ClassModel;
use App\Models\ClassName;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class StudentImportService
{
    /**
     * Generate template Excel standar sekolah untuk import siswa.
     */
    public function generateTemplate(): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Import Siswa');

        $headers = [
            'A1' => 'NIS',
            'B1' => 'NISN',
            'C1' => 'Nama Lengkap',
            'D1' => 'Jenis Kelamin (L/P)',
            'E1' => 'Tempat Lahir',
            'F1' => 'Tanggal Lahir (YYYY-MM-DD)',
            'G1' => 'Email',
            'H1' => 'No HP / WA',
            'I1' => 'Alamat',
            'J1' => 'Kelas (Nama Rombel)',
        ];

        foreach ($headers as $cell => $text) {
            $sheet->setCellValue($cell, $text);
        }

        // Header Styling
        $headerRange = 'A1:J1';
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('15803D'); // Hijau MAN 11
        $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Contoh Data berdasarkan kelas yang tersedia di sistem
        $availableClasses = ClassModel::with('className')->get()->map(fn ($c) => $c->className?->name)->filter()->values()->all();
        $sampleClass = $availableClasses[0] ?? 'XI-MIPA-5';

        $examples = [
            [
                '20261101', '0089123451', 'Ahmad Fauzi Pratama', 'L',
                'Jakarta', '2008-05-12', 'ahmad.fauzi@student.man11.sch.id', '081234567890',
                'Jl. Madrasah No. 11, Cilandak, Jakarta Selatan', $sampleClass
            ],
            [
                '20261102', '0089123452', 'Nurul Aini Lestari', 'P',
                'Depok', '2008-08-20', 'nurul.aini@student.man11.sch.id', '081234567891',
                'Jl. Margonda Raya No. 44, Beji, Depok', $sampleClass
            ],
        ];

        $rowIdx = 2;
        foreach ($examples as $row) {
            $colIdx = 'A';
            foreach ($row as $val) {
                // Set explicit string untuk NIS/NISN agar tidak berubah jadi scientific notation
                $sheet->setCellValueExplicit($colIdx . $rowIdx, $val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $colIdx++;
            }
            $rowIdx++;
        }

        // Auto width
        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Tambahkan lembar referensi Daftar Rombel jika ada rombel terdaftar
        if (! empty($availableClasses)) {
            $classSheet = $spreadsheet->createSheet();
            $classSheet->setTitle('Daftar Rombel');
            $classSheet->setCellValue('A1', 'No');
            $classSheet->setCellValue('B1', 'Nama Rombel / Kelas Terdaftar');

            $classSheet->getStyle('A1:B1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $classSheet->getStyle('A1:B1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('15803D');
            $classSheet->getStyle('A1:B1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $cRow = 2;
            foreach ($availableClasses as $idx => $cName) {
                $classSheet->setCellValue('A' . $cRow, $idx + 1);
                $classSheet->setCellValue('B' . $cRow, $cName);
                $cRow++;
            }
            $classSheet->getColumnDimension('A')->setAutoSize(true);
            $classSheet->getColumnDimension('B')->setAutoSize(true);

            // Kembali fokus ke lembar utama
            $spreadsheet->setActiveSheetIndex(0);
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        return ob_get_clean();
    }

    /**
     * Membaca dan memvalidasi file Excel/CSV.
     * Bila $execute = true, simpan data ke database secara idempoten.
     */
    public function processFile(UploadedFile $file, bool $execute = false): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rawRows = $sheet->toArray(null, true, true, true);

        if (count($rawRows) < 2) {
            return [
                'success' => false,
                'message' => 'File kosong atau tidak memiliki baris data.',
                'summary' => [
                    'total' => 0,
                    'valid' => 0,
                    'errors' => 0,
                    'duplicates' => 0,
                    'created' => 0,
                    'updated' => 0,
                ],
                'rows' => [],
                'failed_rows' => [],
            ];
        }

        // Ambil header dari baris pertama tanpa mengacak nomor baris Excel (preserving keys)
        reset($rawRows);
        $firstKey = key($rawRows);
        $headerRow = $rawRows[$firstKey];
        unset($rawRows[$firstKey]);

        $colMap = $this->mapHeaders($headerRow);

        $processedRows = [];
        $failedRows = [];
        $seenNis = [];

        $totalCount = 0;
        $validCount = 0;
        $errorCount = 0;
        $duplicateCount = 0;
        $createdCount = 0;
        $updatedCount = 0;

        // Cache kelas rombel untuk resolusi cepat
        $classRombels = ClassModel::with('className')->get();

        foreach ($rawRows as $rowNum => $row) {
            // Lewati baris kosong total
            if (empty(array_filter($row))) {
                continue;
            }

            $totalCount++;
            $errors = [];

            $nis = trim((string) ($row[$colMap['nis'] ?? 'A'] ?? ''));
            $nisn = trim((string) ($row[$colMap['nisn'] ?? 'B'] ?? ''));
            $nama = trim((string) ($row[$colMap['nama'] ?? 'C'] ?? ''));
            $genderRaw = trim((string) ($row[$colMap['gender'] ?? 'D'] ?? ''));
            $birthPlace = trim((string) ($row[$colMap['birth_place'] ?? 'E'] ?? ''));
            $birthDateRaw = trim((string) ($row[$colMap['birth_date'] ?? 'F'] ?? ''));
            $email = trim((string) ($row[$colMap['email'] ?? 'G'] ?? ''));
            $phone = trim((string) ($row[$colMap['phone'] ?? 'H'] ?? ''));
            $address = trim((string) ($row[$colMap['address'] ?? 'I'] ?? ''));
            $kelasRaw = trim((string) ($row[$colMap['kelas'] ?? 'J'] ?? ''));

            // 1. Validasi Wajib
            if (empty($nis)) {
                $errors[] = 'NIS wajib diisi';
            } elseif (isset($seenNis[$nis])) {
                $errors[] = "NIS duplikat di dalam file (sudah ada di baris {$seenNis[$nis]})";
                $duplicateCount++;
            } else {
                $seenNis[$nis] = $rowNum;
            }

            if (empty($nama)) {
                $errors[] = 'Nama lengkap wajib diisi';
            }

            // 2. Normalisasi Gender
            $gender = null;
            $gUpper = strtoupper($genderRaw);
            if (in_array($gUpper, ['L', 'LAKI-LAKI', 'LAKI', 'M', 'MALE'])) {
                $gender = 'male';
            } elseif (in_array($gUpper, ['P', 'PEREMPUAN', 'WANITA', 'F', 'FEMALE'])) {
                $gender = 'female';
            } else {
                $errors[] = 'Jenis kelamin harus L atau P';
            }

            // 3. Normalisasi Tanggal Lahir
            $birthDate = null;
            if (! empty($birthDateRaw)) {
                $birthDate = $this->parseBirthDate($birthDateRaw);
                if (! $birthDate) {
                    $errors[] = 'Format tanggal lahir tidak valid (gunakan YYYY-MM-DD)';
                }
            }

            // 4. Resolusi Kelas Rombel
            $matchedClassId = null;
            if (! empty($kelasRaw)) {
                $matchedClass = $classRombels->first(function ($c) use ($kelasRaw) {
                    $cName = strtolower(trim($c->className?->name ?? ''));
                    $search = strtolower($kelasRaw);
                    return $cName === $search || str_replace(['-', ' '], '', $cName) === str_replace(['-', ' '], '', $search);
                });

                if ($matchedClass) {
                    $matchedClassId = $matchedClass->id;
                } else {
                    $errors[] = "Kelas/Rombel '{$kelasRaw}' tidak ditemukan di sistem";
                }
            }

            // 5. Cek apakah di database sudah ada (Upsert idempoten)
            $existingStudent = ! empty($nis) ? Student::where('nis', $nis)->first() : null;
            $isUpdate = (bool) $existingStudent;

            $status = empty($errors) ? 'valid' : 'error';

            $rowData = [
                'row_number' => $rowNum,
                'nis' => $nis,
                'nisn' => $nisn,
                'nama' => $nama,
                'gender' => $gender ?? $genderRaw,
                'birth_place' => $birthPlace,
                'birth_date' => $birthDate,
                'email' => $email,
                'phone' => $phone,
                'address' => $address,
                'kelas' => $kelasRaw,
                'class_model_id' => $matchedClassId,
                'is_update' => $isUpdate,
                'status' => $status,
                'errors' => $errors,
            ];

            if ($status === 'valid') {
                $validCount++;

                // Jika mode eksekusi simpan, jalankan DB transaksi
                if ($execute) {
                    try {
                        DB::transaction(function () use ($rowData, $matchedClassId, &$createdCount, &$updatedCount) {
                            $student = Student::updateOrCreate(
                                ['nis' => $rowData['nis']],
                                [
                                    'nisn' => $rowData['nisn'] ?: null,
                                    'name' => $rowData['nama'],
                                    'gender' => $rowData['gender'],
                                    'birth_place' => $rowData['birth_place'] ?: null,
                                    'birth_date' => $rowData['birth_date'] ?: null,
                                    'email' => $rowData['email'] ?: "{$rowData['nis']}@student.man11.sch.id",
                                    'phone' => $rowData['phone'] ?: null,
                                    'address' => $rowData['address'] ?: null,
                                    'status' => 'active',
                                ]
                            );

                            if ($rowData['is_update']) {
                                $updatedCount++;
                            } else {
                                $createdCount++;
                            }

                            // Buat Akun User jika belum ada
                            $user = User::where('username', $rowData['nis'])->first();
                            $studentEmail = $student->email;
                            
                            // Kata sandi default aman: SiswaMAN11@ + 4 digit terakhir NIS
                            $defaultPassword = 'SiswaMAN11@' . substr($rowData['nis'], -4);

                            if (! $user) {
                                User::create([
                                    'name' => $rowData['nama'],
                                    'username' => $rowData['nis'],
                                    'email' => $studentEmail,
                                    'password' => Hash::make($defaultPassword),
                                    'role_id' => 3, // Siswa
                                ]);
                            } else {
                                $updates = [];
                                if (! empty($rowData['nama']) && $user->name !== $rowData['nama']) {
                                    $updates['name'] = $rowData['nama'];
                                }
                                if (! empty($studentEmail) && $user->email !== $studentEmail) {
                                    $updates['email'] = $studentEmail;
                                }
                                if (! empty($updates)) {
                                    $user->update($updates);
                                }
                            }

                            // Hubungkan ke Rombel Kelas bila ada
                            if ($matchedClassId) {
                                $student->classes()->syncWithoutDetaching([$matchedClassId]);
                            }
                        });
                    } catch (\Exception $e) {
                        $rowData['status'] = 'error';
                        $rowData['errors'][] = 'Gagal menyimpan: ' . $e->getMessage();
                        $validCount--;
                        $errorCount++;
                        $failedRows[] = $rowData;
                    }
                }
            } else {
                $errorCount++;
                $failedRows[] = $rowData;
            }

            $processedRows[] = $rowData;
        }

        return [
            'success' => true,
            'summary' => [
                'total' => $totalCount,
                'valid' => $validCount,
                'errors' => $errorCount,
                'duplicates' => $duplicateCount,
                'created' => $createdCount,
                'updated' => $updatedCount,
            ],
            'rows' => $processedRows,
            'failed_rows' => $failedRows,
        ];
    }

    /**
     * Generate file Excel laporan baris yang gagal diimpor
     */
    public function generateFailedReport(array $failedRows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan Gagal Impor');

        $headers = [
            'A1' => 'Baris',
            'B1' => 'NIS',
            'C1' => 'NISN',
            'D1' => 'Nama Lengkap',
            'E1' => 'Jenis Kelamin',
            'F1' => 'Kelas',
            'G1' => 'Penyebab Gagal / Kesalahan',
        ];

        foreach ($headers as $cell => $text) {
            $sheet->setCellValue($cell, $text);
        }

        $headerRange = 'A1:G1';
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DC2626'); // Merah error
        $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $rowIdx = 2;
        foreach ($failedRows as $item) {
            $sheet->setCellValue('A' . $rowIdx, $item['row_number'] ?? $rowIdx);
            $sheet->setCellValueExplicit('B' . $rowIdx, (string) ($item['nis'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('C' . $rowIdx, (string) ($item['nisn'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue('D' . $rowIdx, $item['nama'] ?? '');
            $sheet->setCellValue('E' . $rowIdx, $item['gender'] ?? '');
            $sheet->setCellValue('F' . $rowIdx, $item['kelas'] ?? '');
            $sheet->setCellValue('G' . $rowIdx, implode('; ', $item['errors'] ?? ['Error tidak diketahui']));
            $rowIdx++;
        }

        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        return ob_get_clean();
    }

    /**
     * Map nama header ke kolom spreadsheet
     */
    private function mapHeaders(array $headerRow): array
    {
        $colMap = [
            'nis' => 'A',
            'nisn' => 'B',
            'nama' => 'C',
            'gender' => 'D',
            'birth_place' => 'E',
            'birth_date' => 'F',
            'email' => 'G',
            'phone' => 'H',
            'address' => 'I',
            'kelas' => 'J',
        ];

        foreach ($headerRow as $colLetter => $rawHeader) {
            $clean = strtolower(trim((string) $rawHeader));
            if (empty($clean)) {
                continue;
            }

            if (str_contains($clean, 'kelas') || str_contains($clean, 'rombel')) {
                $colMap['kelas'] = $colLetter;
            } elseif (str_contains($clean, 'nisn')) {
                $colMap['nisn'] = $colLetter;
            } elseif (str_contains($clean, 'nis') && ! str_contains($clean, 'jenis')) {
                $colMap['nis'] = $colLetter;
            } elseif (str_contains($clean, 'kelamin') || str_contains($clean, 'gender') || str_contains($clean, 'jk')) {
                $colMap['gender'] = $colLetter;
            } elseif (str_contains($clean, 'nama') || str_contains($clean, 'name') || str_contains($clean, 'siswa')) {
                $colMap['nama'] = $colLetter;
            } elseif (str_contains($clean, 'tempat')) {
                $colMap['birth_place'] = $colLetter;
            } elseif (str_contains($clean, 'tanggal') || str_contains($clean, 'tgl') || str_contains($clean, 'lahir')) {
                $colMap['birth_date'] = $colLetter;
            } elseif (str_contains($clean, 'email') || str_contains($clean, 'surel')) {
                $colMap['email'] = $colLetter;
            } elseif (str_contains($clean, 'hp') || str_contains($clean, 'wa') || str_contains($clean, 'telepon') || str_contains($clean, 'telp') || str_contains($clean, 'phone')) {
                $colMap['phone'] = $colLetter;
            } elseif (str_contains($clean, 'alamat') || str_contains($clean, 'address')) {
                $colMap['address'] = $colLetter;
            }
        }

        return $colMap;
    }

    /**
     * Parse tanggal dari berbagai format (string YYYY-MM-DD, DD/MM/YYYY, Excel timestamp)
     */
    private function parseBirthDate($raw): ?string
    {
        if (is_numeric($raw)) {
            // Excel serial date number
            try {
                $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $raw);
                return $dt->format('Y-m-d');
            } catch (\Exception) {
                // fall through
            }
        }

        $raw = trim((string) $raw);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $raw;
        }

        $parsed = strtotime($raw);
        if ($parsed !== false && $parsed > 0) {
            return date('Y-m-d', $parsed);
        }

        return null;
    }
}
