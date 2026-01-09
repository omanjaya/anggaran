<?php

namespace Database\Seeders;

use App\Models\AccountCode;
use Illuminate\Database\Seeder;

class AccountCodeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accountCodes = [
            // Level 1 - Kelompok
            ['code' => '5', 'description' => 'BELANJA DAERAH', 'level' => 1, 'parent_code' => null],

            // Level 2 - Jenis
            ['code' => '5.1', 'description' => 'BELANJA OPERASI', 'level' => 2, 'parent_code' => '5'],
            ['code' => '5.2', 'description' => 'BELANJA MODAL', 'level' => 2, 'parent_code' => '5'],

            // Level 3 - Objek
            ['code' => '5.1.01', 'description' => 'Belanja Pegawai', 'level' => 3, 'parent_code' => '5.1'],
            ['code' => '5.1.02', 'description' => 'Belanja Barang dan Jasa', 'level' => 3, 'parent_code' => '5.1'],
            ['code' => '5.2.02', 'description' => 'Belanja Modal Peralatan dan Mesin', 'level' => 3, 'parent_code' => '5.2'],

            // Level 4 - Rincian Objek (Belanja Barang dan Jasa)
            ['code' => '5.1.02.01', 'description' => 'Belanja Bahan Pakai Habis', 'level' => 4, 'parent_code' => '5.1.02'],
            ['code' => '5.1.02.01.01', 'description' => 'Belanja Alat/Bahan untuk Kegiatan Kantor', 'level' => 4, 'parent_code' => '5.1.02.01'],
            ['code' => '5.1.02.02', 'description' => 'Belanja Jasa Kantor', 'level' => 4, 'parent_code' => '5.1.02'],
            ['code' => '5.1.02.02.01', 'description' => 'Belanja Telepon', 'level' => 4, 'parent_code' => '5.1.02.02'],
            ['code' => '5.1.02.04', 'description' => 'Belanja Perjalanan Dinas', 'level' => 4, 'parent_code' => '5.1.02'],
            ['code' => '5.1.02.04.01', 'description' => 'Belanja Perjalanan Dinas Dalam Negeri', 'level' => 4, 'parent_code' => '5.1.02.04'],

            // Level 5 - Sub Rincian Objek (Paling Detail)
            ['code' => '5.1.02.01.01.0024', 'description' => 'Belanja Alat/Bahan untuk Kegiatan Kantor-Alat Tulis Kantor', 'level' => 5, 'parent_code' => '5.1.02.01.01'],
            ['code' => '5.1.02.01.01.0025', 'description' => 'Belanja Alat/Bahan untuk Kegiatan Kantor-Kertas dan Cover', 'level' => 5, 'parent_code' => '5.1.02.01.01'],
            ['code' => '5.1.02.01.01.0026', 'description' => 'Belanja Alat/Bahan untuk Kegiatan Kantor-Bahan Cetak', 'level' => 5, 'parent_code' => '5.1.02.01.01'],
            ['code' => '5.1.02.01.01.0027', 'description' => 'Belanja Alat/Bahan untuk Kegiatan Kantor-Benda Pos', 'level' => 5, 'parent_code' => '5.1.02.01.01'],
            ['code' => '5.1.02.01.01.0028', 'description' => 'Belanja Alat/Bahan untuk Kegiatan Kantor-Bahan Komputer', 'level' => 5, 'parent_code' => '5.1.02.01.01'],
            ['code' => '5.1.02.01.01.0029', 'description' => 'Belanja Alat/Bahan untuk Kegiatan Kantor-Peralatan Kebersihan', 'level' => 5, 'parent_code' => '5.1.02.01.01'],
            ['code' => '5.1.02.01.01.0030', 'description' => 'Belanja Alat/Bahan untuk Kegiatan Kantor-Bahan/Bibit Tanaman', 'level' => 5, 'parent_code' => '5.1.02.01.01'],

            // Belanja Jasa - Level 5
            ['code' => '5.1.02.02.01.0001', 'description' => 'Belanja Telepon-Telepon', 'level' => 5, 'parent_code' => '5.1.02.02.01'],
            ['code' => '5.1.02.02.01.0002', 'description' => 'Belanja Telepon-Faksimili', 'level' => 5, 'parent_code' => '5.1.02.02.01'],
            ['code' => '5.1.02.02.01.0003', 'description' => 'Belanja Telepon-Internet', 'level' => 5, 'parent_code' => '5.1.02.02.01'],

            // Belanja Perjalanan Dinas - Level 5
            ['code' => '5.1.02.04.01.0001', 'description' => 'Belanja Perjalanan Dinas Biasa', 'level' => 5, 'parent_code' => '5.1.02.04.01'],
            ['code' => '5.1.02.04.01.0002', 'description' => 'Belanja Perjalanan Dinas Tetap', 'level' => 5, 'parent_code' => '5.1.02.04.01'],
            ['code' => '5.1.02.04.01.0003', 'description' => 'Belanja Perjalanan Dinas Dalam Kota', 'level' => 5, 'parent_code' => '5.1.02.04.01'],
            ['code' => '5.1.02.04.01.0004', 'description' => 'Belanja Perjalanan Dinas Paket Meeting Dalam Kota', 'level' => 5, 'parent_code' => '5.1.02.04.01'],
            ['code' => '5.1.02.04.01.0005', 'description' => 'Belanja Perjalanan Dinas Paket Meeting Luar Kota', 'level' => 5, 'parent_code' => '5.1.02.04.01'],

            // Belanja Jasa Konsultansi - Level 4 & 5
            ['code' => '5.1.02.02.05', 'description' => 'Belanja Jasa Konsultansi', 'level' => 4, 'parent_code' => '5.1.02.02'],
            ['code' => '5.1.02.02.05.0001', 'description' => 'Belanja Jasa Konsultansi Perencanaan', 'level' => 5, 'parent_code' => '5.1.02.02.05'],
            ['code' => '5.1.02.02.05.0002', 'description' => 'Belanja Jasa Konsultansi Pengawasan', 'level' => 5, 'parent_code' => '5.1.02.02.05'],
            ['code' => '5.1.02.02.05.0003', 'description' => 'Belanja Jasa Konsultansi Kajian/Studi', 'level' => 5, 'parent_code' => '5.1.02.02.05'],

            // Belanja Sewa - Level 4 & 5
            ['code' => '5.1.02.02.06', 'description' => 'Belanja Sewa', 'level' => 4, 'parent_code' => '5.1.02.02'],
            ['code' => '5.1.02.02.06.0001', 'description' => 'Belanja Sewa Tanah', 'level' => 5, 'parent_code' => '5.1.02.02.06'],
            ['code' => '5.1.02.02.06.0002', 'description' => 'Belanja Sewa Gedung/Bangunan', 'level' => 5, 'parent_code' => '5.1.02.02.06'],
            ['code' => '5.1.02.02.06.0003', 'description' => 'Belanja Sewa Ruang Rapat', 'level' => 5, 'parent_code' => '5.1.02.02.06'],
            ['code' => '5.1.02.02.06.0004', 'description' => 'Belanja Sewa Kendaraan', 'level' => 5, 'parent_code' => '5.1.02.02.06'],
            ['code' => '5.1.02.02.06.0005', 'description' => 'Belanja Sewa Peralatan Kantor', 'level' => 5, 'parent_code' => '5.1.02.02.06'],

            // Belanja Makan Minum - Level 4 & 5
            ['code' => '5.1.02.02.07', 'description' => 'Belanja Makan dan Minum', 'level' => 4, 'parent_code' => '5.1.02.02'],
            ['code' => '5.1.02.02.07.0001', 'description' => 'Belanja Makan dan Minum Rapat', 'level' => 5, 'parent_code' => '5.1.02.02.07'],
            ['code' => '5.1.02.02.07.0002', 'description' => 'Belanja Makan dan Minum Tamu', 'level' => 5, 'parent_code' => '5.1.02.02.07'],
            ['code' => '5.1.02.02.07.0003', 'description' => 'Belanja Makan dan Minum Kegiatan', 'level' => 5, 'parent_code' => '5.1.02.02.07'],

            // Belanja Jasa Tenaga Ahli - Level 4 & 5
            ['code' => '5.1.02.02.08', 'description' => 'Belanja Jasa Tenaga Ahli', 'level' => 4, 'parent_code' => '5.1.02.02'],
            ['code' => '5.1.02.02.08.0001', 'description' => 'Belanja Jasa Narasumber/Instruktur', 'level' => 5, 'parent_code' => '5.1.02.02.08'],
            ['code' => '5.1.02.02.08.0002', 'description' => 'Belanja Jasa Tenaga Pengajar/Pelatih', 'level' => 5, 'parent_code' => '5.1.02.02.08'],
            ['code' => '5.1.02.02.08.0003', 'description' => 'Belanja Jasa Moderator', 'level' => 5, 'parent_code' => '5.1.02.02.08'],
            ['code' => '5.1.02.02.08.0004', 'description' => 'Belanja Jasa Penerjemah', 'level' => 5, 'parent_code' => '5.1.02.02.08'],

            // Belanja Modal - Level 4 & 5
            ['code' => '5.2.02.01', 'description' => 'Belanja Modal Alat Besar', 'level' => 4, 'parent_code' => '5.2.02'],
            ['code' => '5.2.02.01.01.0001', 'description' => 'Belanja Modal Alat Besar Darat', 'level' => 5, 'parent_code' => '5.2.02.01'],
            ['code' => '5.2.02.02', 'description' => 'Belanja Modal Alat Angkutan', 'level' => 4, 'parent_code' => '5.2.02'],
            ['code' => '5.2.02.02.01.0001', 'description' => 'Belanja Modal Kendaraan Dinas Bermotor Roda 4', 'level' => 5, 'parent_code' => '5.2.02.02'],
            ['code' => '5.2.02.02.01.0002', 'description' => 'Belanja Modal Kendaraan Dinas Bermotor Roda 2', 'level' => 5, 'parent_code' => '5.2.02.02'],
            ['code' => '5.2.02.03', 'description' => 'Belanja Modal Alat Kantor', 'level' => 4, 'parent_code' => '5.2.02'],
            ['code' => '5.2.02.03.01.0001', 'description' => 'Belanja Modal Komputer/PC', 'level' => 5, 'parent_code' => '5.2.02.03'],
            ['code' => '5.2.02.03.01.0002', 'description' => 'Belanja Modal Laptop/Notebook', 'level' => 5, 'parent_code' => '5.2.02.03'],
            ['code' => '5.2.02.03.01.0003', 'description' => 'Belanja Modal Printer', 'level' => 5, 'parent_code' => '5.2.02.03'],
            ['code' => '5.2.02.03.01.0004', 'description' => 'Belanja Modal Scanner', 'level' => 5, 'parent_code' => '5.2.02.03'],
            ['code' => '5.2.02.03.01.0005', 'description' => 'Belanja Modal Server', 'level' => 5, 'parent_code' => '5.2.02.03'],
            ['code' => '5.2.02.03.01.0006', 'description' => 'Belanja Modal UPS', 'level' => 5, 'parent_code' => '5.2.02.03'],
            ['code' => '5.2.02.03.01.0007', 'description' => 'Belanja Modal AC', 'level' => 5, 'parent_code' => '5.2.02.03'],
        ];

        foreach ($accountCodes as $data) {
            AccountCode::updateOrCreate(
                ['code' => $data['code']],
                $data
            );
        }

        $this->command->info('Account codes seeded successfully!');
    }
}
