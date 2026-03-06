<?php

namespace Pterodactyl\Console\Commands\Billing;

use Illuminate\Console\Command;
use Pterodactyl\Services\Billing\ExchangeRateService;

class FetchExchangeRatesCommand extends Command
{
    protected $signature = 'p:billing:fetch-exchange-rates';

    protected $description = 'Fetch USD/EUR/BRL exchange rates from ExchangeRate-API and store for frontend display.';

    public function handle(ExchangeRateService $service): int
    {
        $rates = $service->fetchAndStore();

        if ($rates) {
            $this->info('Exchange rates updated:');
            $this->table(
                ['Currency', 'Rate (vs USD)'],
                [
                    ['USD', '1.00'],
                    ['EUR', number_format($rates['EUR'], 4)],
                    ['BRL', number_format($rates['BRL'], 4)],
                ]
            );

            return 0;
        }

        $this->error('Failed to fetch exchange rates. Check logs.');

        return 1;
    }
}
