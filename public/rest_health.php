<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
const API_KEY = '1864fbf94224aef9488ee865a4cfb1cec4d784d82dddb7106c43abc4220e677f596d13ad8e7134058a779b83eb960625';
$k = $_SERVER['HTTP_X_API_KEY'] ?? ($_GET['api_key'] ?? '');
if (!hash_equals(API_KEY, (string)$k)) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'err'=>'unauthorized']);
    exit;
}
echo json_encode(['ok'=>true,'time'=>date('c')]);
