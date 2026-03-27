<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RozetkaService;
use App\Services\HelpCrunchService;

class SyncRozetkaMessages extends Command
{
    // Назва команди, яку будемо викликати
    protected $signature = 'rozetka:sync';
    protected $description = 'Синхронізація нових повідомлень з Rozetka в HelpCrunch';

    public function handle(RozetkaService $rozetkaService, HelpCrunchService $helpCrunchService)
    {
        $this->info('Початок синхронізації...');

        // Викликаємо ваш метод
        $rozetkaService->syncNewMessages($helpCrunchService);

        $this->info('Синхронізацію завершено.');
    }
}
