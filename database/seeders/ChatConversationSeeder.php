<?php

namespace Database\Seeders;

use App\Models\Classes;
use App\Models\ChatConversation;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ChatConversationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // 1. Ambil SEMUA kelas yang ada di database.
        $classes = Classes::all();

        if ($classes->isEmpty()) {
            $this->command->warn('No classes found. Skipping ChatConversationSeeder.');
            return;
        }

        // 2. Ulangi untuk setiap kelas dan buat percakapan untuknya.
        foreach ($classes as $class) {
            ChatConversation::firstOrCreate(
                // Cari berdasarkan id_class untuk mencegah duplikasi
                ['id_class' => $class->id_class], 
                [
                    // Jika belum ada, buat dengan initiator_id (misal, admin pertama)
                    'id_initiator' => 1,
                    'type' => 'group', // Pastikan tipenya 'group'
                ]
            );
        }

        $this->command->info('Chat conversations for all classes seeded successfully!');
    }
}

