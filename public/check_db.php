<?php
/**
 * Vendor Autoload orqali Database tekshirish va ma'lumotlarni ko'rish
 *
 * Bu script vendor/autoload.php ni yuklab, bazaga ulanadi va
 * jadvallar, ma'lumotlarni to'g'ridan-to'g'ri tekshirish imkonini beradi.
 * Docker container ichida va tashqarisida ishlaydi.
 *
 * Foydalanish (CLI):
 *   php public/check_db.php                          — ulanish + jadvallar ro'yxati
 *   php public/check_db.php students                 — students jadvalidagi ma'lumotlar (oxirgi 10 ta)
 *   php public/check_db.php students 25              — students jadvalidan 25 ta yozuv
 *   php public/check_db.php students --count         — students jadvalidagi yozuvlar soni
 *   php public/check_db.php students --where="group_name=A-101"  — filtr bilan
 *   php public/check_db.php students --search="Ali"  — full_name yoki boshqa ustunlardan qidirish
 *
 * Foydalanish (Web):
 *   GET /check_db.php?api_key=...                    — ulanish tekshirish
 *   GET /check_db.php?api_key=...&table=students     — jadval ma'lumotlari
 *   GET /check_db.php?api_key=...&table=students&limit=25
 *   GET /check_db.php?api_key=...&table=students&count=1
 *   GET /check_db.php?api_key=...&table=students&where=group_name:A-101
 *   GET /check_db.php?api_key=...&table=students&search=Ali
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

// --- Xavfsizlik: Web orqali kirilganda API key tekshirish ---
$isCli = php_sapi_name() === 'cli';
if (!$isCli) {
    $apiKey = '1864fbf94224aef9488ee865a4cfb1cec4d784d82dddb7106c43abc4220e677f596d13ad8e7134058a779b83eb960625';
    $k = $_SERVER['HTTP_X_API_KEY'] ?? ($_GET['api_key'] ?? '');
    if (!hash_equals($apiKey, (string)$k)) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'unauthorized']);
        exit;
    }
}

// --- CLI argumentlarni parse qilish ---
$cliTable = null;
$cliLimit = 10;
$cliCount = false;
$cliWhere = [];
$cliSearch = null;

if ($isCli) {
    $args = array_slice($argv, 1);
    foreach ($args as $arg) {
        if ($arg === '--count') {
            $cliCount = true;
        } elseif (str_starts_with($arg, '--where=')) {
            $pair = substr($arg, 8);
            if (str_contains($pair, '=')) {
                [$col, $val] = explode('=', $pair, 2);
                $cliWhere[trim($col)] = trim($val);
            }
        } elseif (str_starts_with($arg, '--search=')) {
            $cliSearch = substr($arg, 9);
        } elseif (is_numeric($arg)) {
            $cliLimit = min((int)$arg, 500);
        } elseif (!str_starts_with($arg, '-')) {
            $cliTable = $arg;
        }
    }
}

// Web parametrlar
if (!$isCli) {
    $cliTable = $_GET['table'] ?? null;
    $cliLimit = min((int)($_GET['limit'] ?? 10), 500);
    $cliCount = isset($_GET['count']);
    $cliSearch = $_GET['search'] ?? null;
    if (!empty($_GET['where'])) {
        foreach (explode(',', $_GET['where']) as $pair) {
            if (str_contains($pair, ':')) {
                [$col, $val] = explode(':', $pair, 2);
                $cliWhere[trim($col)] = trim($val);
            }
        }
    }
}

$result = [
    'ok' => false,
    'time' => date('c'),
];

// --- 1. Vendor autoload tekshirish ---
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    $result['error'] = 'vendor/autoload.php topilmadi. "composer install" bajaring.';
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit(1);
}

require $autoloadPath;

// --- 2. Laravel Application bootstrap ---
$laravelBooted = false;
try {
    $app = require_once __DIR__ . '/../bootstrap/app.php';
    $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    $laravelBooted = true;
} catch (\Throwable $e) {
    $result['error'] = 'Laravel yuklanmadi: ' . $e->getMessage();
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit(1);
}

// --- 3. Database ulanishini tekshirish ---
try {
    $connection = config('database.default');
    $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();
    $dbName = config("database.connections.{$connection}.database");
    $result['ok'] = true;
    $result['connection'] = [
        'driver' => $connection,
        'host' => config("database.connections.{$connection}.host", 'N/A'),
        'database' => $dbName,
        'server' => $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION),
    ];
} catch (\Throwable $e) {
    $result['error'] = 'Bazaga ulanib bo\'lmadi: ' . $e->getMessage();
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit(1);
}

// --- 4. Jadvallar ro'yxati (agar table ko'rsatilmagan bo'lsa) ---
if (!$cliTable) {
    $tables = \Illuminate\Support\Facades\DB::select('SHOW TABLES');
    $dbKey = "Tables_in_{$dbName}";
    $tableList = [];
    foreach ($tables as $t) {
        $name = $t->$dbKey ?? (array_values((array)$t)[0] ?? '');
        $count = \Illuminate\Support\Facades\DB::table($name)->count();
        $tableList[] = [
            'table' => $name,
            'rows' => $count,
        ];
    }
    $result['tables'] = $tableList;
    $result['total_tables'] = count($tableList);

    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit(0);
}

// --- 5. Jadval mavjudligini tekshirish ---
$safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $cliTable);
try {
    $exists = \Illuminate\Support\Facades\DB::select(
        "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?",
        [$dbName, $safeTable]
    );
    if (empty($exists)) {
        $result['error'] = "'{$safeTable}' jadvali topilmadi";
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit(1);
    }
} catch (\Throwable $e) {
    $result['error'] = 'Jadval tekshirishda xato: ' . $e->getMessage();
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit(1);
}

// --- 6. Jadval ustunlarini olish ---
$columns = \Illuminate\Support\Facades\DB::select(
    "SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION",
    [$dbName, $safeTable]
);
$colNames = array_map(fn($c) => $c->COLUMN_NAME, $columns);
$colTypes = [];
foreach ($columns as $c) {
    $colTypes[$c->COLUMN_NAME] = $c->DATA_TYPE;
}

$result['table'] = $safeTable;
$result['columns'] = $colNames;

// --- 7. Faqat COUNT so'ralgan bo'lsa ---
if ($cliCount) {
    $query = \Illuminate\Support\Facades\DB::table($safeTable);

    foreach ($cliWhere as $col => $val) {
        $safeCol = preg_replace('/[^a-zA-Z0-9_]/', '', $col);
        if (in_array($safeCol, $colNames, true)) {
            $query->where($safeCol, $val);
        }
    }

    if ($cliSearch) {
        $searchCols = array_filter($colNames, fn($c) => in_array($colTypes[$c] ?? '', ['varchar', 'text', 'char', 'longtext', 'mediumtext', 'tinytext']));
        if ($searchCols) {
            $query->where(function ($q) use ($searchCols, $cliSearch) {
                foreach ($searchCols as $col) {
                    $q->orWhere($col, 'LIKE', "%{$cliSearch}%");
                }
            });
        }
    }

    $result['count'] = $query->count();
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit(0);
}

// --- 8. Ma'lumotlarni olish ---
$query = \Illuminate\Support\Facades\DB::table($safeTable);

// WHERE filtrlari
foreach ($cliWhere as $col => $val) {
    $safeCol = preg_replace('/[^a-zA-Z0-9_]/', '', $col);
    if (in_array($safeCol, $colNames, true)) {
        $query->where($safeCol, $val);
    }
}

// SEARCH - matnli ustunlardan qidirish
if ($cliSearch) {
    $searchCols = array_filter($colNames, fn($c) => in_array($colTypes[$c] ?? '', ['varchar', 'text', 'char', 'longtext', 'mediumtext', 'tinytext']));
    if ($searchCols) {
        $query->where(function ($q) use ($searchCols, $cliSearch) {
            foreach ($searchCols as $col) {
                $q->orWhere($col, 'LIKE', "%{$cliSearch}%");
            }
        });
    }
}

$total = (clone $query)->count();

$data = $query
    ->orderByDesc(in_array('id', $colNames, true) ? 'id' : $colNames[0])
    ->limit($cliLimit)
    ->get();

$result['total'] = $total;
$result['showing'] = count($data);
$result['limit'] = $cliLimit;
$result['data'] = $data;

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

if ($isCli) {
    echo "\n";
}
