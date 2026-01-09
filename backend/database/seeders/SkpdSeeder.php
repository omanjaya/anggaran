<?php

namespace Database\Seeders;

use App\Models\Skpd;
use Illuminate\Database\Seeder;

class SkpdSeeder extends Seeder
{
    public function run(): void
    {
        $skpdData = [
            [
                'code' => '1.01.01',
                'name' => 'Dinas Pendidikan, Pemuda dan Olahraga Provinsi Bali',
                'short_name' => 'Disdikpora',
                'address' => 'Jl. Raya Puputan No. 1, Renon, Denpasar',
                'phone' => '(0361) 226115',
                'email' => 'disdikpora@baliprov.go.id',
                'is_active' => true,
            ],
            [
                'code' => '1.02.01',
                'name' => 'Dinas Kesehatan Provinsi Bali',
                'short_name' => 'Dinkes',
                'address' => 'Jl. Melati No. 20, Denpasar',
                'phone' => '(0361) 222145',
                'email' => 'dinkes@baliprov.go.id',
                'is_active' => true,
            ],
            [
                'code' => '2.01.01',
                'name' => 'Dinas Pekerjaan Umum dan Penataan Ruang Provinsi Bali',
                'short_name' => 'DPUPR',
                'address' => 'Jl. Cok Agung Tresna No. 1, Denpasar',
                'phone' => '(0361) 224138',
                'email' => 'dpupr@baliprov.go.id',
                'is_active' => true,
            ],
            [
                'code' => '2.03.01',
                'name' => 'Dinas Perumahan Rakyat dan Kawasan Permukiman Provinsi Bali',
                'short_name' => 'Disperkim',
                'address' => 'Jl. D.I. Panjaitan No. 1, Denpasar',
                'phone' => '(0361) 225144',
                'email' => 'disperkim@baliprov.go.id',
                'is_active' => true,
            ],
            [
                'code' => '2.07.01',
                'name' => 'Dinas Perhubungan Provinsi Bali',
                'short_name' => 'Dishub',
                'address' => 'Jl. Cok Agung Tresna No. 1, Denpasar',
                'phone' => '(0361) 224561',
                'email' => 'dishub@baliprov.go.id',
                'is_active' => true,
            ],
            [
                'code' => '2.11.01',
                'name' => 'Dinas Lingkungan Hidup dan Kehutanan Provinsi Bali',
                'short_name' => 'DLHK',
                'address' => 'Jl. D.I. Panjaitan No. 1, Denpasar',
                'phone' => '(0361) 235512',
                'email' => 'dlhk@baliprov.go.id',
                'is_active' => true,
            ],
            [
                'code' => '3.25.01',
                'name' => 'Dinas Kebudayaan Provinsi Bali',
                'short_name' => 'Disbud',
                'address' => 'Jl. Hayam Wuruk No. 17, Denpasar',
                'phone' => '(0361) 222674',
                'email' => 'disbud@baliprov.go.id',
                'is_active' => true,
            ],
            [
                'code' => '3.26.01',
                'name' => 'Dinas Pariwisata Provinsi Bali',
                'short_name' => 'Dispar',
                'address' => 'Jl. S. Parman, Niti Mandala, Denpasar',
                'phone' => '(0361) 222387',
                'email' => 'dispar@baliprov.go.id',
                'is_active' => true,
            ],
            [
                'code' => '4.01.01',
                'name' => 'Sekretariat Daerah Provinsi Bali',
                'short_name' => 'Setda',
                'address' => 'Jl. Basuki Rahmat No. 1, Denpasar',
                'phone' => '(0361) 225000',
                'email' => 'setda@baliprov.go.id',
                'is_active' => true,
            ],
            [
                'code' => '5.02.01',
                'name' => 'Badan Perencanaan Pembangunan Daerah Provinsi Bali',
                'short_name' => 'Bappeda',
                'address' => 'Jl. Melati No. 18, Denpasar',
                'phone' => '(0361) 222509',
                'email' => 'bappeda@baliprov.go.id',
                'is_active' => true,
            ],
            [
                'code' => '5.03.01',
                'name' => 'Badan Pengelola Keuangan dan Aset Daerah Provinsi Bali',
                'short_name' => 'BPKAD',
                'address' => 'Jl. Basuki Rahmat No. 1, Denpasar',
                'phone' => '(0361) 225100',
                'email' => 'bpkad@baliprov.go.id',
                'is_active' => true,
            ],
        ];

        foreach ($skpdData as $data) {
            Skpd::updateOrCreate(
                ['code' => $data['code']],
                $data
            );
        }

        $this->command->info('SKPD seeder completed. ' . count($skpdData) . ' records created.');
    }
}
