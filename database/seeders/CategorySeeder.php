<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        // 1. Definisikan struktur Kategori Utama (Parent) dan Sub-Kategori (Children)
        $data = [
            [
                'name' => 'Infrastruktur',
                'description' => 'Laporan terkait kerusakan fisik fasilitas utama kota.',
                'children' => [
                    ['name' => 'Jalan Berlubang / Rusak', 'description' => 'Kerusakan aspal, lubang jalan, atau amblas.'],
                    ['name' => 'Jembatan Rusak', 'description' => 'Kerusakan konstruksi jembatan atau fasilitas penyeberangan.'],
                    ['name' => 'Pipa Air Bocor / Rusak', 'description' => 'Kebocoran saluran air bersih utama atau pipa PDAM.'],
                ]
            ],
            [
                'name' => 'Lingkungan & Sanitasi',
                'description' => 'Masalah kebersihan, polusi, dan kesehatan lingkungan publik.',
                'children' => [
                    ['name' => 'Penumpukan Sampah Liar', 'description' => 'Pembuangan sampah sembarangan atau TPS yang meluap.'],
                    ['name' => 'Saluran Air / Drainase Tersumbat', 'description' => 'Got atau gorong-gorong mampet yang berpotensi banjir.'],
                    ['name' => 'Polusi Udara / Asap Industri', 'description' => 'Pencemaran udara akibat pembakaran atau pabrik.'],
                ]
            ],
            [
                'name' => 'Fasilitas Umum',
                'description' => 'Kerusakan sarana penunjang aktivitas warga.',
                'children' => [
                    ['name' => 'Lampu Jalan Mati', 'description' => 'Penerangan Jalan Umum (PJU) yang padam atau rusak.'],
                    ['name' => 'Kerusakan Taman / Alun-alun', 'description' => 'Fasilitas bermain rusak, bangku taman rusak, atau tanaman layu.'],
                    ['name' => 'Kerusakan Halte / Terminal', 'description' => 'Fasilitas ruang tunggu kendaraan umum yang tidak layak.'],
                ]
            ],
            [
                'name' => 'Keamanan & Ketertiban',
                'description' => 'Laporan gangguan ketentraman warga dan lingkungan.',
                'children' => [
                    ['name' => 'Penerangan Jalan Kurang (Rawan Begal)', 'description' => 'Lokasi gelap yang rawan tindakan kriminal.'],
                    ['name' => 'Gangguan Ketertiban Umum', 'description' => 'Balap liar, pungli, pengamen jalanan yang meresahkan.'],
                ]
            ],
            [
                'name' => 'Pengaduan Umum (Lainnya)',
                'description' => 'Gunakan jika masalah tidak masuk dalam kategori mana pun di atas.',
                'children' => [
                    ['name' => 'Masalah Lain-lain', 'description' => 'Akan direview manual oleh admin untuk penentuan dinas terkait.'],
                ]
            ],
        ];

        // 2. Proses Looping untuk menyimpan ke Database
        foreach ($data as $parentData) {
            // Simpan Kategori Utama (Parent)
            $parent = Category::create([
                'parent_id'   => null,
                'name'        => $parentData['name'],
                'description' => $parentData['description'],
                'is_active'   => true,
            ]);

            // Simpan semua Sub-Kategori (Children) yang terikat ke Parent ini
            foreach ($parentData['children'] as $childData) {
                Category::create([
                    'parent_id'   => $parent->id, // Mengikat ke ID Parent yang baru dibuat
                    'name'        => $childData['name'],
                    'description' => $childData['description'],
                    'is_active'   => true,
                ]);
            }
        }
    }
}