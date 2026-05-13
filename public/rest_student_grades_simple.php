<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
const API_KEY='1864fbf94224aef9488ee865a4cfb1cec4d784d82dddb7106c43abc4220e677f596d13ad8e7134058a779b83eb960625';
if(!(($_SERVER['HTTP_X_API_KEY']??($_GET['api_key']??''))===API_KEY)){http_response_code(401);echo json_encode(['success'=>false,'error'=>'Unauthorized','code'=>401]);exit;}

$env=parse_ini_file(__DIR__.'/../.env', false, INI_SCANNER_RAW);
$dsn='mysql:host='.($env['DB_HOST']??'127.0.0.1').';port='.($env['DB_PORT']??'3306').';dbname='.($env['DB_DATABASE']??'').';charset=utf8mb4';
try{$pdo=new PDO($dsn,$env['DB_USERNAME']??'',$env['DB_PASSWORD']??'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$pdo->exec("SET time_zone = '+05:00'");}
catch(Throwable $e){http_response_code(500);echo json_encode(['success'=>false,'error'=>'DB connect failed','detail'=>$e->getMessage(),'code'=>500]);exit;}

/* paging */
$per=max(1,min(500,(int)($_GET['per_page']??100)));
$page=max(1,(int)($_GET['page']??1));
$off=($page-1)*$per;

/* filters: real column is lesson_date (API’da grade_date deb ko‘rsatamiz) */
$where=[];$p=[];
if(isset($_GET['date_from']) && $_GET['date_from']!==''){$where[]='lesson_date >= ?';$p[]=$_GET['date_from'];}
if(isset($_GET['date_to'])   && $_GET['date_to']!=='')  {$where[]='lesson_date <= ?';$p[]=$_GET['date_to'];}
$w=$where?('WHERE '.implode(' AND ',$where)):'';

/* sort: id | -id | updated_at | -updated_at | grade | -grade | grade_date | -grade_date  */
$sort=$_GET['sort']??'-id';
$dir=str_starts_with($sort,'-')?'DESC':'ASC';
$col=ltrim($sort,'-');
$map=['id'=>'id','updated_at'=>'updated_at','grade'=>'grade','grade_date'=>'lesson_date'];
$ob=$map[$col]??'id';

try{
  $total=(int)$pdo->query('SELECT COUNT(1) FROM student_grades')->fetchColumn();
  $sql="
    SELECT
      id,
      student_id,
      subject_id,
      subject_name,
      subject_code,
      grade,
      DATE_FORMAT(lesson_date,'%Y-%m-%d') AS grade_date,
      DATE_FORMAT(updated_at,'%Y-%m-%dT%H:%i:%s+05:00') AS updated_at,
      DATE_FORMAT(created_at,'%Y-%m-%dT%H:%i:%s+05:00') AS created_at
    FROM student_grades
    $w
    ORDER BY $ob $dir
    LIMIT $per OFFSET $off
  ";
  $st=$pdo->prepare($sql); $st->execute($p); $rows=$st->fetchAll();
} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'query failed','detail'=>$e->getMessage(),'code'=>500]);
  exit;
}

echo json_encode(['success'=>true,'meta'=>['page'=>$page,'per_page'=>$per,'total'=>$total,'last_page'=>max(1,(int)ceil($total/$per)),'sort'=>$sort],'data'=>$rows],JSON_UNESCAPED_UNICODE);
