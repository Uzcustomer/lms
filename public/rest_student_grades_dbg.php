<?php
declare(strict_types=1);
header("Content-Type: application/json; charset=UTF-8");
const API_KEY='1864fbf94224aef9488ee865a4cfb1cec4d784d82dddb7106c43abc4220e677f596d13ad8e7134058a779b83eb960625';
$k=$_SERVER['HTTP_X_API_KEY']??($_GET['api_key']??'');
if(!hash_equals(API_KEY,(string)$k)){ http_response_code(401); echo json_encode(['ok'=>false,'err'=>'unauthorized']); exit; }

$APP_ROOT=realpath(__DIR__.'/..'); $DOTENV=$APP_ROOT.'/.env';
$env=[]; foreach(file($DOTENV, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $line){
  if(str_starts_with(trim($line),'#')) continue;
  $p=strpos($line,'='); if($p===false) continue;
  $kk=trim(substr($line,0,$p)); $vv=trim(substr($line,$p+1));
  if(strlen($vv)>=2 && (($vv[0]=="'"&&$vv[-1]=="'")||($vv[0]=='"'&&$vv[-1]=='"'))) $vv=substr($vv,1,-1);
  $env[$kk]=$vv;
}
$dsn="mysql:host=".($env['DB_HOST']??'127.0.0.1').";port=".($env['DB_PORT']??'3306').";dbname=".($env['DB_DATABASE']??'').";charset=utf8mb4";

try {
  $pdo=new PDO($dsn,$env['DB_USERNAME']??'', $env['DB_PASSWORD']??'', [
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
  ]);
  $pdo->exec("SET time_zone = '+05:00'");
  // 1) Jadvallar bormi?
  $tables=[
    'student_grades'=>$pdo->query("SHOW TABLES LIKE 'student_grades'")->fetchColumn()?true:false,
    'students'      =>$pdo->query("SHOW TABLES LIKE 'students'")->fetchColumn()?true:false,
    'subjects'      =>$pdo->query("SHOW TABLES LIKE 'subjects'")->fetchColumn()?true:false,
    'lesson_pairs'  =>$pdo->query("SHOW TABLES LIKE 'lesson_pairs'")->fetchColumn()?true:false,
  ];
  // 2) Minimal SELECT
  $rows=$pdo->query("SELECT id,student_id,subject_id,lesson_pair_id,grade,grade_type,grade_date,updated_at,created_at FROM student_grades ORDER BY id DESC LIMIT 3")->fetchAll();
  echo json_encode(['ok'=>true,'tables'=>$tables,'sample'=>$rows], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'err'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
