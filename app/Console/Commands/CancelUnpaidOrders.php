<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

class CancelUnpaidOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:cancel-unpaid {--hours=24 : Batalkan order baru yang belum dibayar melebihi batas jam ini}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Batalkan otomatis pesanan baru yang belum dibayar melewati batas waktu';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hours = (int) $this->option('hours');

        $canceled = Order::where('status', 'baru')
            ->where('payment_status', '!=', 'paid')
            ->where('created_at', '<', now()->subHours($hours))
            ->update(['status' => 'dibatalkan']);

        $this->info("Berhasil membatalkan {$canceled} pesanan yang belum dibayar melebihi {$hours} jam.");

        return Command::SUCCESS;
    }
}
