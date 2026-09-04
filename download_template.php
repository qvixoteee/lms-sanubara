<?php
// Mengatur header agar file langsung terunduh sebagai file CSV/Excel
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=template_import_bank_soal.csv');

$output = fopen('php://output', 'w');

// Menyesuaikan persis dengan kolom tabel bank_soal: id_ujian, id_video, pertanyaan, pilihan_benar, pilihan_lain_1, pilihan_lain_2, pilihan_lain_3
fputcsv($output, ['id_ujian', 'id_video', 'pertanyaan', 'pilihan_benar', 'pilihan_lain_1', 'pilihan_lain_2', 'pilihan_lain_3']);

// Contoh baris data (id_ujian dapat diisi kode ujian target, id_video bisa dikosongkan/diisi)
fputcsv($output, [
    'EXAM_CONTOH', 
    '', 
    'Apa singkatan dari K3LL dalam standar perusahaan?', 
    'Keselamatan, Kesehatan Kerja dan Lindungan Lingkungan', 
    'Keamanan, Kebersihan Lingkungan Kerja', 
    'Kesehatan Karyawan dan Lingkungan Luar', 
    'Keselamatan Kerja dan Lingkungan'
]);

fclose($output);
exit;
?>