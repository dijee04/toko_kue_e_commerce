<?php

namespace Database\Seeders;

use App\Models\Kategori;
use App\Models\Produk;
use Illuminate\Database\Seeder;

/**
 * Mengisi katalog asli Dear Seana dari "Katalog Dear Seana.pdf".
 * Hanya item yang sudah punya harga di katalog yang dimasukkan.
 * Idempoten: dijalankan ulang akan memperbarui produk dengan nama yang sama.
 */
class DearSeanaCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $kategoriNames = [
            'Kue Kering',
            'Dessert',
            'Kue Ulang Tahun',
            'Savoury',
            'Hampers',
            'Kue Basah',
        ];

        $kategori = [];
        foreach ($kategoriNames as $nama) {
            $kategori[$nama] = Kategori::firstOrCreate(['nama_kategori' => $nama]);
        }

        $img = fn (string $path) => 'assets/img_produk/' . $path;

        $produk = [
            // ================= KUE KERING / COOKIES â€” Rp 125.000 =================
            ['Nastar', 'Kue Kering', 125000, $img('Cookies/Nastar_Cookies.png'), 'Nastar klasik dengan selai nanas homemade, renyah dan lumer di mulut.'],
            ['Nutella Tart', 'Kue Kering', 125000, $img('Cookies/Nutella_Cookies.png'), 'Tart renyah dengan isian Nutella yang melimpah.'],
            ['Palm Cheese', 'Kue Kering', 125000, $img('Cookies/Palm_Cheese.png'), 'Kue palm keju gurih dengan tekstur renyah berlapis.'],
            ['Putri Salju', 'Kue Kering', 125000, $img('Cookies/Assorted_Cookies.png'), 'Kue putri salju lembut bertabur gula halus.'],
            ['Florentine', 'Kue Kering', 125000, $img('Cookies/Florentine_Cookies.png'), 'Florentine almond renyah dengan lapisan caramel manis.'],
            ['Red Velvet Cookies', 'Kue Kering', 125000, $img('Cookies/Coklat_cookies.png'), 'Cookies red velvet dengan rasa cokelat lembut.'],

            // ================= CAKES / DESSERT =================
            ['Mini Basque Burnt Cheese Cake Original (9,5 cm)', 'Dessert', 100000, $img('Kue/Mini_BBCheesecake.png'), 'Mini basque burnt cheesecake diameter 9,5 cm, rasa original.'],
            ['Mini Basque Burnt Cheese Cake Nutella (9,5 cm)', 'Dessert', 115000, $img('Kue/Cheesecake_coklat.png'), 'Mini basque burnt cheesecake dengan topping Nutella.'],
            ['Mini Basque Burnt Cheese Cake Biscoff (9,5 cm)', 'Dessert', 115000, $img('Kue/Cheesecake_Lotus.png'), 'Mini basque burnt cheesecake dengan topping Lotus Biscoff.'],
            ['Fudgy Brownies Original 20x10 cm', 'Dessert', 120000, $img('Kue/Brownis_1.png'), 'Fudgy brownies cokelat original, ukuran 20x10 cm.'],
            ['Fudgy Brownies Original 25x25 cm', 'Dessert', 240000, $img('Kue/Brownis_2.png'), 'Fudgy brownies cokelat original, ukuran 25x25 cm.'],
            ['Fudgy Brownies Nutella 20x10 cm', 'Dessert', 150000, $img('Kue/Brownis_3.png'), 'Fudgy brownies dengan isian Nutella, ukuran 20x10 cm.'],
            ['Fudgy Brownies Nutella 25x25 cm', 'Dessert', 300000, $img('Kue/Brownis_3.png'), 'Fudgy brownies dengan isian Nutella, ukuran 25x25 cm.'],
            ['Fudgy Brownies Biscoff 20x10 cm', 'Dessert', 150000, $img('Kue/Brownis_4.png'), 'Fudgy brownies dengan topping Lotus Biscoff, ukuran 20x10 cm.'],
            ['Fudgy Brownies Biscoff 25x25 cm', 'Dessert', 300000, $img('Kue/Brownis_4.png'), 'Fudgy brownies dengan topping Lotus Biscoff, ukuran 25x25 cm.'],
            ['Fudgy Brownies Cheese 20x10 cm', 'Dessert', 150000, $img('Kue/Brownis_1.png'), 'Fudgy brownies dengan lapisan cheese creamy, ukuran 20x10 cm.'],
            ['Fudgy Brownies Cheese 25x25 cm', 'Dessert', 300000, $img('Kue/Brownis_2.png'), 'Fudgy brownies dengan lapisan cheese creamy, ukuran 25x25 cm.'],

            // ================= WHOLE CAKES =================
            ['Custom Whole Cake (16-20 cm)', 'Kue Ulang Tahun', 350000, $img('Whole Cakes/Coklat_Cake.png'), 'Custom whole cake diameter 16 atau 20 cm, desain sesuai permintaan. Harga mulai Rp 350.000.'],

            // ================= HAMPERS =================
            ['Hampers Eid Mubarak 3 Jar', 'Hampers', 475000, $img('Hempers/Hempers_1.png'), 'Hampers Eid Mubarak berisi 3 jar cookies pilihan.'],
            ['Hampers Eid Mubarak 2 Jar', 'Hampers', 300000, $img('Hempers/Hempers_2.png'), 'Hampers Eid Mubarak berisi 2 jar cookies pilihan.'],
            ['Hampers Package Mini Cheesecake', 'Hampers', 400000, $img('Hempers/Hempers_3l.png'), 'Hampers package mini cheesecake. Harga mulai Rp 400.000.'],

            // ================= SAVOURY TREATS =================
            ['Dimsum Original', 'Savoury', 25000, $img('Additional/Screenshot 2026-04-14 145805.png'), 'Dimsum ayam udang original, dikukus fresh.'],
            ['Dimsum Mentai', 'Savoury', 30000, $img('Additional/Screenshot 2026-04-14 145826.png'), 'Dimsum ayam udang dengan saus mentai creamy.'],
            ['Dimsum Mentai Mozza', 'Savoury', 35000, $img('Additional/Screenshot 2026-04-14 145826.png'), 'Dimsum mentai dengan topping keju mozzarella.'],
            ['Gyoza', 'Savoury', 25000, $img('Catering/Lumpia.png'), 'Gyoza gurih dengan isian ayam, disajikan bersama saus.'],
            ['Spaghetti Brulee', 'Savoury', 45000, $img('Additional/Screenshot 2026-04-14 145536.png'), 'Spaghetti saus bolognese dengan topping saus keju brulee.'],
            ['Mac & Cheese', 'Savoury', 30000, $img('Additional/MacNCheese.png'), 'Macaroni dengan saus keju creamy, dipanggang hingga berkerak.'],

        ];

        foreach ($produk as [$nama, $namaKategori, $harga, $gambar, $deskripsi]) {
            Produk::updateOrCreate(
                ['nama_produk' => $nama],
                [
                    'kategori_id' => $kategori[$namaKategori]->id,
                    'harga' => $harga,
                    'gambar' => $gambar,
                    'deskripsi' => $deskripsi,
                    'is_active' => true,
                    'has_options' => false,
                ]
            );
        }

        // Produk demo lama disembunyikan (riwayat order uji tetap utuh).
        Produk::whereIn('nama_produk', [
            'Dark Chocolate Bliss',
            'Cupcake Vanilla Drizzle',
            'Celebration Tart',
            'Fudgy Melted Brownie',
            'pukis',
        ])->update(['is_active' => false]);
    }
}
