<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();
if (! $app->environment('testing') || ! str_ends_with((string) config('database.connections.mysql.database'), '_test')) {
    throw new RuntimeException('Concurrency workers only run against the dedicated test database.');
}

Auth::login(User::findOrFail((int) $argv[2]));
file_put_contents($argv[3], (string) DB::selectOne('SELECT CONNECTION_ID() AS id')->id);
$restock = ($argv[5] ?? 'sale') === 'restock';
$parameters = [
    'submission_id' => $argv[4] ?? (string) Str::uuid(),
    'product_id' => (int) $argv[1],
    'quantity' => 4,
    'type' => 'restock',
    'reason' => 'Concurrent delivery',
];
if (($argv[5] ?? '') === 'cart') {
    $items = [['product_id' => (int) $argv[1], 'quantity' => 4], ['product_id' => (int) $argv[6], 'quantity' => 1]];
    $parameters = ['submission_id' => $argv[4], 'items' => ($argv[7] ?? '0') === '1' ? array_reverse($items) : $items, 'payment_method' => 'card'];
}
$request = Request::create($restock ? '/products/'.(int) $argv[1].'/stock' : '/sales', 'POST', $parameters);
$kernel = $app->make(HttpKernel::class);
$response = $kernel->handle($request);
$errors = session('errors');
echo json_encode(['status' => $response->getStatusCode(), 'result' => $errors ? 'rejected' : 'sold', 'errors' => is_object($errors) ? $errors->all() : $errors]);
$kernel->terminate($request, $response);
