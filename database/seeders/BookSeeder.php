<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BookSeeder extends Seeder
{
    /**
     * Seed data buku perpustakaan.
     */
    public function run(): void
    {
        // Ambil ID kategori berdasarkan slug
        $categories = DB::table('book_categories')->pluck('id_book_category', 'slug')->toArray();

        $books = [
            // =============================================
            // INFORMATIKA
            // =============================================
            [
                'title'            => 'Algoritma dan Pemrograman dengan Python',
                'author'           => 'Rinaldi Munir',
                'publisher'        => 'Informatika Bandung',
                'year'             => 2023,
                'isbn'             => '978-602-8758-90-1',
                'id_book_category' => $categories['informatika'],
                'total_stock'      => 5,
                'available_stock'  => 5,
            ],
            [
                'title'            => 'Rekayasa Perangkat Lunak: Pendekatan Praktis',
                'author'           => 'Roger S. Pressman',
                'publisher'        => 'Andi Publisher',
                'year'             => 2022,
                'isbn'             => '978-979-29-7150-2',
                'id_book_category' => $categories['informatika'],
                'total_stock'      => 3,
                'available_stock'  => 3,
            ],
            [
                'title'            => 'Basis Data: Konsep dan Implementasi',
                'author'           => 'Fathansyah',
                'publisher'        => 'Informatika Bandung',
                'year'             => 2021,
                'isbn'             => '978-602-8758-45-1',
                'id_book_category' => $categories['informatika'],
                'total_stock'      => 4,
                'available_stock'  => 4,
            ],
            [
                'title'            => 'Jaringan Komputer: Teori dan Praktik',
                'author'           => 'Andrew S. Tanenbaum',
                'publisher'        => 'Pearson',
                'year'             => 2021,
                'isbn'             => '978-013-359414-0',
                'id_book_category' => $categories['informatika'],
                'total_stock'      => 3,
                'available_stock'  => 3,
            ],
            [
                'title'            => 'Pemrograman Web dengan Laravel',
                'author'           => 'M. Arief Luthfi',
                'publisher'        => 'Lokomedia',
                'year'             => 2024,
                'isbn'             => '978-602-0120-88-5',
                'id_book_category' => $categories['informatika'],
                'total_stock'      => 6,
                'available_stock'  => 6,
            ],
            [
                'title'            => 'Kecerdasan Buatan: Teknik dan Aplikasinya',
                'author'           => 'Sri Kusumadewi',
                'publisher'        => 'Graha Ilmu',
                'year'             => 2023,
                'isbn'             => '978-979-756-337-8',
                'id_book_category' => $categories['informatika'],
                'total_stock'      => 4,
                'available_stock'  => 4,
            ],
            [
                'title'            => 'Struktur Data dan Algoritma dalam Java',
                'author'           => 'Robert Lafore',
                'publisher'        => 'Andi Publisher',
                'year'             => 2022,
                'isbn'             => '978-979-29-5840-4',
                'id_book_category' => $categories['informatika'],
                'total_stock'      => 3,
                'available_stock'  => 3,
            ],
            [
                'title'            => 'Keamanan Sistem Informasi',
                'author'           => 'Budi Rahardjo',
                'publisher'        => 'ITB Press',
                'year'             => 2023,
                'isbn'             => '978-623-297-010-3',
                'id_book_category' => $categories['informatika'],
                'total_stock'      => 2,
                'available_stock'  => 2,
            ],

            // =============================================
            // MATEMATIKA
            // =============================================
            [
                'title'            => 'Kalkulus dan Geometri Analitis Jilid 1',
                'author'           => 'Edwin J. Purcell',
                'publisher'        => 'Erlangga',
                'year'             => 2020,
                'isbn'             => '978-979-075-456-8',
                'id_book_category' => $categories['matematika'],
                'total_stock'      => 5,
                'available_stock'  => 5,
            ],
            [
                'title'            => 'Aljabar Linear Elementer',
                'author'           => 'Howard Anton',
                'publisher'        => 'Erlangga',
                'year'             => 2021,
                'isbn'             => '978-979-075-630-2',
                'id_book_category' => $categories['matematika'],
                'total_stock'      => 4,
                'available_stock'  => 4,
            ],
            [
                'title'            => 'Matematika Diskrit dan Aplikasinya',
                'author'           => 'Kenneth H. Rosen',
                'publisher'        => 'McGraw-Hill',
                'year'             => 2022,
                'isbn'             => '978-125-967-651-2',
                'id_book_category' => $categories['matematika'],
                'total_stock'      => 3,
                'available_stock'  => 3,
            ],
            [
                'title'            => 'Analisis Real',
                'author'           => 'Bartle dan Sherbert',
                'publisher'        => 'Wiley',
                'year'             => 2020,
                'isbn'             => '978-047-105-366-9',
                'id_book_category' => $categories['matematika'],
                'total_stock'      => 2,
                'available_stock'  => 2,
            ],
            [
                'title'            => 'Persamaan Diferensial Biasa',
                'author'           => 'Shepley L. Ross',
                'publisher'        => 'Erlangga',
                'year'             => 2021,
                'isbn'             => '978-979-075-112-3',
                'id_book_category' => $categories['matematika'],
                'total_stock'      => 3,
                'available_stock'  => 3,
            ],

            // =============================================
            // MANAJEMEN
            // =============================================
            [
                'title'            => 'Manajemen Strategis: Konsep dan Kasus',
                'author'           => 'Fred R. David',
                'publisher'        => 'Salemba Empat',
                'year'             => 2023,
                'isbn'             => '978-979-061-510-4',
                'id_book_category' => $categories['manajemen'],
                'total_stock'      => 4,
                'available_stock'  => 4,
            ],
            [
                'title'            => 'Manajemen Keuangan: Teori dan Aplikasi',
                'author'           => 'Brigham dan Houston',
                'publisher'        => 'Salemba Empat',
                'year'             => 2022,
                'isbn'             => '978-979-061-480-0',
                'id_book_category' => $categories['manajemen'],
                'total_stock'      => 3,
                'available_stock'  => 3,
            ],
            [
                'title'            => 'Perilaku Organisasi',
                'author'           => 'Stephen P. Robbins',
                'publisher'        => 'Salemba Empat',
                'year'             => 2023,
                'isbn'             => '978-979-061-590-6',
                'id_book_category' => $categories['manajemen'],
                'total_stock'      => 5,
                'available_stock'  => 5,
            ],
            [
                'title'            => 'Manajemen Sumber Daya Manusia',
                'author'           => 'Gary Dessler',
                'publisher'        => 'Salemba Empat',
                'year'             => 2024,
                'isbn'             => '978-979-061-620-0',
                'id_book_category' => $categories['manajemen'],
                'total_stock'      => 3,
                'available_stock'  => 3,
            ],
            [
                'title'            => 'Pengantar Manajemen',
                'author'           => 'T. Hani Handoko',
                'publisher'        => 'BPFE Yogyakarta',
                'year'             => 2021,
                'isbn'             => '978-979-503-040-1',
                'id_book_category' => $categories['manajemen'],
                'total_stock'      => 6,
                'available_stock'  => 6,
            ],
            [
                'title'            => 'Manajemen Pemasaran',
                'author'           => 'Philip Kotler',
                'publisher'        => 'Erlangga',
                'year'             => 2023,
                'isbn'             => '978-979-075-880-1',
                'id_book_category' => $categories['manajemen'],
                'total_stock'      => 4,
                'available_stock'  => 4,
            ],

            // =============================================
            // STATISTIKA
            // =============================================
            [
                'title'            => 'Pengantar Statistika',
                'author'           => 'Ronald E. Walpole',
                'publisher'        => 'Gramedia Pustaka Utama',
                'year'             => 2022,
                'isbn'             => '978-602-03-5120-7',
                'id_book_category' => $categories['statistika'],
                'total_stock'      => 4,
                'available_stock'  => 4,
            ],
            [
                'title'            => 'Analisis Regresi: Teori, Kasus, dan Solusi',
                'author'           => 'Agus Irianto',
                'publisher'        => 'Kencana',
                'year'             => 2021,
                'isbn'             => '978-602-422-140-5',
                'id_book_category' => $categories['statistika'],
                'total_stock'      => 3,
                'available_stock'  => 3,
            ],
            [
                'title'            => 'Statistika untuk Penelitian',
                'author'           => 'Sugiyono',
                'publisher'        => 'Alfabeta',
                'year'             => 2023,
                'isbn'             => '978-602-289-270-3',
                'id_book_category' => $categories['statistika'],
                'total_stock'      => 5,
                'available_stock'  => 5,
            ],
            [
                'title'            => 'Probabilitas dan Statistika untuk Teknik',
                'author'           => 'Jay L. Devore',
                'publisher'        => 'Erlangga',
                'year'             => 2022,
                'isbn'             => '978-979-075-710-1',
                'id_book_category' => $categories['statistika'],
                'total_stock'      => 3,
                'available_stock'  => 3,
            ],
            [
                'title'            => 'Metode Statistika Multivariat',
                'author'           => 'Richard A. Johnson',
                'publisher'        => 'Pearson',
                'year'             => 2021,
                'isbn'             => '978-013-214-075-2',
                'id_book_category' => $categories['statistika'],
                'total_stock'      => 2,
                'available_stock'  => 2,
            ],

            // =============================================
            // UMUM
            // =============================================
            [
                'title'            => 'Bahasa Indonesia untuk Perguruan Tinggi',
                'author'           => 'Arifin dan Tasai',
                'publisher'        => 'Akademia Pressindo',
                'year'             => 2022,
                'isbn'             => '978-979-820-050-5',
                'id_book_category' => $categories['umum'],
                'total_stock'      => 6,
                'available_stock'  => 6,
            ],
            [
                'title'            => 'Pendidikan Pancasila untuk Perguruan Tinggi',
                'author'           => 'Kaelan',
                'publisher'        => 'Paradigma',
                'year'             => 2021,
                'isbn'             => '978-979-8470-87-6',
                'id_book_category' => $categories['umum'],
                'total_stock'      => 5,
                'available_stock'  => 5,
            ],
            [
                'title'            => 'Bahasa Inggris Akademik',
                'author'           => 'John Swales',
                'publisher'        => 'Cambridge University Press',
                'year'             => 2023,
                'isbn'             => '978-110-771-450-8',
                'id_book_category' => $categories['umum'],
                'total_stock'      => 4,
                'available_stock'  => 4,
            ],
            [
                'title'            => 'Metodologi Penelitian',
                'author'           => 'Sugiyono',
                'publisher'        => 'Alfabeta',
                'year'             => 2024,
                'isbn'             => '978-602-289-380-9',
                'id_book_category' => $categories['umum'],
                'total_stock'      => 7,
                'available_stock'  => 7,
            ],
            [
                'title'            => 'Etika Profesi dan Kewirausahaan',
                'author'           => 'Suhrawardi K. Lubis',
                'publisher'        => 'Sinar Grafika',
                'year'             => 2022,
                'isbn'             => '978-979-007-840-2',
                'id_book_category' => $categories['umum'],
                'total_stock'      => 3,
                'available_stock'  => 3,
            ],
        ];

        foreach ($books as $book) {
            DB::table('books')->updateOrInsert(
                ['isbn' => $book['isbn']],
                array_merge($book, [
                    'status'     => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
