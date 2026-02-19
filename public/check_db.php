<?php
/**
 * Vendor Autoload orqali Database ulanishini tekshirish
 *
 * Bu script vendor/autoload.php ni yuklab, bazaga to'g'ridan-to'g'ri ulanishni tekshiradi.
 * Docker container ichida yoki tashqarisida ishlaydi.
 *
 * Foydalanish:
 *   CLI:  php public/check_db.php
 *   Web:  https://your-domain/check_db.php (API key kerak)
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

// --- Xavfsizlik: Web orqali kirilganda API key tekshirish ---
if (php_sapi_name() !== 'cli') {
    $apiKey = '1864fbf94224aef9488ee865a4cfb1cec4d784d82dddb7106c43abc4220e677f596d13ad8e7134058a779b83eb960625';
    $k = $_SERVER['HTTP_X_API_KEY'] ?? ($_GET['api_key'] ?? '');
    if (!hash_equals($apiKey, (string)$k)) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'unauthorized']);
        exit;
    }
}

$result = [
    'ok' => false,
    'checks' => [],
    'time' => date('c'),
];

// --- 1. Vendor autoload tekshirish ---
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    $result['checks']['vendor_autoload'] = [
        'status' => 'FAIL',
        'message' => 'vendor/autoload.php topilmadi. "composer install" bajaring.',
    ];
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit(1);
}

require $autoloadPath;
$result['checks']['vendor_autoload'] = [
    'status' => 'OK',
    'message' => 'vendor/autoload.php muvaffaqiyatli yuklandi',
];

// --- 2. Laravel Application bootstrap ---
$laravelBooted = false;
try {
    $app = require_once __DIR__ . '/../bootstrap/app.php';
    $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    $laravelBooted = true;

    $result['checks']['laravel_bootstrap'] = [
        'status' => 'OK',
        'message' => 'Laravel application muvaffaqiyatli yuklandi',
        'environment' => app()->environment(),
    ];
} catch (\Throwable $e) {
    $result['checks']['laravel_bootstrap'] = [
        'status' => 'FAIL',
        'message' => 'Laravel yuklanmadi: ' . $e->getMessage(),
    ];
}

// --- 3. .env fayl tekshirish ---
$envPath = __DIR__ . '/../.env';
$envExists = file_exists($envPath);
$result['checks']['env_file'] = [
    'status' => $envExists ? 'OK' : 'WARN',
    'message' => $envExists
        ? '.env fayl mavjud'
        : '.env fayl topilmadi (Docker environment variables ishlatilishi mumkin)',
];

// --- 4. Database ulanishini tekshirish ---
if ($laravelBooted) {
    // Laravel DB Facade orqali tekshirish
    try {
        $connection = config('database.default');
        $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();

        $result['checks']['database_connection'] = [
            'status' => 'OK',
            'message' => 'Bazaga muvaffaqiyatli ulandi',
            'driver' => $connection,
            'host' => config("database.connections.{$connection}.host", 'N/A'),
            'database' => config("database.connections.{$connection}.database"),
            'server_version' => $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION),
        ];

        // Jadvallar sonini tekshirish
        $tables = \Illuminate\Support\Facades\DB::select('SHOW TABLES');
        $tableCount = count($tables);

        $result['checks']['database_tables'] = [
            'status' => $tableCount > 0 ? 'OK' : 'WARN',
            'message' => $tableCount > 0
                ? "{$tableCount} ta jadval topildi"
                : 'Jadvallar topilmadi. Migration ishlatilmagan bo\'lishi mumkin.',
            'table_count' => $tableCount,
        ];

        // Migration holatini tekshirish
        try {
            $migrations = \Illuminate\Support\Facades\DB::table('migrations')->count();
            $result['checks']['migrations'] = [
                'status' => 'OK',
                'message' => "{$migrations} ta migration bajarilgan",
                'count' => $migrations,
            ];
        } catch (\Throwable $e) {
            $result['checks']['migrations'] = [
                'status' => 'WARN',
                'message' => 'Migrations jadvali topilmadi: ' . $e->getMessage(),
            ];
        }

        $result['ok'] = true;
    } catch (\Throwable $e) {
        $result['checks']['database_connection'] = [
            'status' => 'FAIL',
            'message' => 'Bazaga ulanib bo\'lmadi: ' . $e->getMessage(),
            'driver' => config('database.default'),
            'host' => config('database.connections.' . config('database.default') . '.host', 'N/A'),
        ];
    }
} else {
    // Fallback: PDO orqali to'g'ridan-to'g'ri tekshirish
    $dbHost = getenv('DB_HOST') ?: '127.0.0.1';
    $dbPort = getenv('DB_PORT') ?: '3306';
    $dbName = getenv('DB_DATABASE') ?: 'lmsttatf';
    $dbUser = getenv('DB_USERNAME') ?: 'root';
    $dbPass = getenv('DB_PASSWORD') ?: '';
    $dbConn = getenv('DB_CONNECTION') ?: 'mysql';

    try {
        $dsn = "{$dbConn}:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
        $pdo = new \PDO($dsn, $dbUser, $dbPass, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_TIMEOUT => 5,
        ]);

        $result['checks']['database_connection'] = [
            'status' => 'OK',
            'message' => 'PDO orqali bazaga muvaffaqiyatli ulandi (Laravel bootstrap ishlamadi)',
            'driver' => $dbConn,
            'host' => $dbHost,
            'database' => $dbName,
            'server_version' => $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION),
        ];

        $stmt = $pdo->query('SHOW TABLES');
        $tableCount = $stmt->rowCount();

        $result['checks']['database_tables'] = [
            'status' => $tableCount > 0 ? 'OK' : 'WARN',
            'message' => "{$tableCount} ta jadval topildi",
            'table_count' => $tableCount,
        ];

        $result['ok'] = true;
    } catch (\Throwable $e) {
        $result['checks']['database_connection'] = [
            'status' => 'FAIL',
            'message' => 'PDO orqali bazaga ulanib bo\'lmadi: ' . $e->getMessage(),
            'driver' => $dbConn,
            'host' => $dbHost,
        ];
    }
}

// --- Natijani chiqarish ---
$httpCode = $result['ok'] ? 200 : 503;
if (php_sapi_name() !== 'cli') {
    http_response_code($httpCode);
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

if (php_sapi_name() === 'cli') {
    echo "\n";
    exit($result['ok'] ? 0 : 1);
}
