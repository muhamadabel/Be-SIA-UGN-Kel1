<?php

namespace Database\Seeders;

use App\Models\TuitionFee;
use App\Models\TuitionPayment;
use App\Models\TuitionRate;
use App\Models\VirtualAccount;
use App\Models\User_si;
use App\Models\AcademicPeriod;
use Illuminate\Database\Seeder;

class TuitionFeeSeeder extends Seeder
{
    /**
     * Seed data demo:
     * 1. Virtual Accounts untuk semua mahasiswa
     * 2. Tagihan UKT pada semester aktif
     * 3. Beberapa pembayaran dengan status berbeda
     */
    public function run(): void
    {
        $activePeriod = AcademicPeriod::where('is_active', true)->first();

        if (!$activePeriod) {
            $this->command->warn('Tidak ada periode akademik aktif. Jalankan AcademicPeriodSeeder terlebih dahulu.');
            return;
        }

        $students = User_si::where('role', 'mahasiswa')
            ->where('is_active', true)
            ->with(['profile', 'program'])
            ->get();

        if ($students->isEmpty()) {
            $this->command->warn('Tidak ada mahasiswa aktif. Jalankan UserSeeder_si terlebih dahulu.');
            return;
        }

        $bankPrefix = '8801';
        $bankCode = 'BNI';
        $bankName = 'Bank Negara Indonesia';

        $createdVA = 0;
        $createdFees = 0;
        $createdPayments = 0;

        foreach ($students as $index => $student) {
            // === 1. Generate Virtual Account ===
            $nim = $student->profile?->registration_number;
            if ($nim) {
                $vaNumber = $bankPrefix . $nim;
                VirtualAccount::firstOrCreate(
                    ['id_user_si' => $student->id_user_si],
                    [
                        'va_number' => $vaNumber,
                        'bank_code' => $bankCode,
                        'bank_name' => $bankName,
                        'is_active' => true,
                    ]
                );
                $createdVA++;
            }

            // === 2. Generate Tagihan UKT ===
            // Gunakan tarif UKT yang sudah di-assign ke mahasiswa
            $rate = $student->id_tuition_rate
                ? TuitionRate::find($student->id_tuition_rate)
                : TuitionRate::where('id_program', $student->id_program)
                    ->where('is_active', true)
                    ->first();

            $amount = $rate?->amount ?? 5000000;
            $discount = ($index % 5 === 0) ? 500000 : 0; // Setiap 5 mahasiswa dapat diskon
            $finalAmount = $amount - $discount;

            $fee = TuitionFee::firstOrCreate(
                [
                    'id_user_si' => $student->id_user_si,
                    'id_academic_period' => $activePeriod->id_academic_period,
                ],
                [
                    'id_tuition_rate' => $rate?->id_tuition_rate,
                    'amount' => $amount,
                    'discount' => $discount,
                    'final_amount' => max(0, $finalAmount),
                    'status' => 'unpaid',
                    'due_date' => $activePeriod->end_date?->subMonth(),
                    'notes' => $discount > 0 ? 'Mendapat potongan beasiswa' : null,
                ]
            );
            $createdFees++;

            // === 3. Buat beberapa pembayaran demo ===
            // Mahasiswa ke-1, ke-2: sudah bayar & verified (lunas)
            // Mahasiswa ke-3: sudah bayar, menunggu verifikasi (pending)
            // Sisanya: belum bayar

            if ($index === 0 || $index === 1) {
                // Lunas
                $payment = TuitionPayment::firstOrCreate(
                    ['id_tuition_fee' => $fee->id_tuition_fee],
                    [
                        'id_user_si' => $student->id_user_si,
                        'amount_paid' => $fee->final_amount,
                        'payment_method' => 'virtual_account',
                        'payment_proof' => null,
                        'transaction_reference' => 'TRX' . str_pad($index + 1, 6, '0', STR_PAD_LEFT),
                        'verification_status' => 'verified',
                        'verified_by' => User_si::where('role', 'admin')->first()?->id_user_si,
                        'verified_at' => now()->subDays(rand(1, 14)),
                        'admin_notes' => 'Pembayaran sesuai nominal.',
                    ]
                );
                $fee->update(['status' => 'paid']);
                $createdPayments++;

            } elseif ($index === 2) {
                // Pending verification
                TuitionPayment::firstOrCreate(
                    ['id_tuition_fee' => $fee->id_tuition_fee],
                    [
                        'id_user_si' => $student->id_user_si,
                        'amount_paid' => $fee->final_amount,
                        'payment_method' => 'bank_transfer',
                        'payment_proof' => 'payment-proofs/demo_proof_pending.jpg',
                        'transaction_reference' => 'TRX' . str_pad($index + 1, 6, '0', STR_PAD_LEFT),
                        'verification_status' => 'pending',
                    ]
                );
                $createdPayments++;
            }
            // Sisanya tetap unpaid (tanpa pembayaran)
        }

        $this->command->info("Tuition seeder completed:");
        $this->command->info("  - Virtual Accounts: {$createdVA}");
        $this->command->info("  - Tagihan UKT: {$createdFees}");
        $this->command->info("  - Pembayaran demo: {$createdPayments}");
    }
}
