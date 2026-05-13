<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
$env=parse_ini_file(__DIR__.'/../.env', false, INI_SCANNER_RAW);
$dsn='mysql:host='.($env['DB_HOST']??'127.0.0.1').';port='.($env['DB_PORT']??'3306').';dbname='.($env['DB_DATABASE']??'').';charset=utf8mb4';
try{
  $pdo=new PDO($dsn,$env['DB_USERNAME']??'',$env['DB_PASSWORD']??'',[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
  ]);
  $cols=$pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'student_grades' ORDER BY ORDINAL_POSITION");
  $cols->execute(); $names=array_map(fn($r)=>$r['COLUMN_NAME'],$cols->fetchAll());
  echo json_encode(['ok'=>true,'columns'=>$names], JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){ http_response_code(500); echo json_encode(['ok'=>false,'err'=>$e->getMessage()]); }
