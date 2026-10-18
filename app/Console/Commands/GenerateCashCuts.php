<?php

namespace App\Console\Commands;

use App\Services\CashCutService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('pos:cortes')]
#[Description('Genera los cortes de caja por hora pendientes y cierra los días anteriores')]
class GenerateCashCuts extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(CashCutService $cashCuts): int
    {
        $count = $cashCuts->catchUp();

        $this->info("Cortes generados o actualizados: {$count}");

        return self::SUCCESS;
    }
}
