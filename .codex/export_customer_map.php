<?php

use App\Models\Customer;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$path = storage_path('app/access-migrations/statements/access-customer-map.csv');
$stream = fopen($path, 'wb');
fwrite($stream, "\xEF\xBB\xBF");
fputcsv($stream, ['printed_customer_name', 'customer_code']);
Customer::query()
    ->leftJoin('opening_receivable_balances as opening', 'opening.customer_id', '=', 'customers.id')
    ->select('customers.*')
    ->whereNotNull('legacy_code')
    ->orderByRaw('opening.id IS NULL')
    ->orderByRaw("CAST(NULLIF(legacy_code, '') AS integer) NULLS LAST")
    ->orderBy('id')
    ->each(fn (Customer $customer) => fputcsv($stream, [
        $customer->legacy_name ?: $customer->name,
        $customer->customer_code,
    ]));
fclose($stream);

echo $path.PHP_EOL;
