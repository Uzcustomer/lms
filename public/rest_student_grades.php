<?php
// File: public/rest_student_grades.php  (API)
declare(strict_types=1);

/**
 * REST: /rest_student_grades.php
 * - Auth: X-Api-Key yoki ?api_key=
 * - Dinamik ustunlar: INFORMATION_SCHEMA -> SELECT `sg`.*
 * - Filtrlar:
 *     date_from/date_to (lesson_date), updated_from/updated_to (updated_at),
 *     student_id, student_hemis_id,
 *     subject_id (bitta yoki vergul bilan ro‘yxat),
 *     training_type_code, semester_code,
 *     level_name (bitta yoki vergul bilan ro‘yxat),
 *     semester_name (bitta yoki vergul bilan ro‘yxat),
 *     q (LIKE)
 * - Sort: ?sort=col yoki ?sort=-col
 * - Paginatsiya: page, per_page (<=500)
 * - Include: ?include=student,employee,subject (LEFT JOIN agar mavjud bo‘lsa)
 * - Natija: { success, meta{...}, data:[...] }
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

const API_KEY='1864fbf94224aef9488ee865a4cfb1cec4d784d82dddb7106c43abc4220e677f596d13ad8e7134058a779b83eb960625';

$provided = $_SERVER['HTTP_X_API_KEY'] ?? ($_GET['api_key'] ?? '');
if ($provided !== API_KEY) {
  http_response_code(401);
  echo json_encode(['success'=>false,'error'=>'Unauthorized','code'=>401]);
  exit;
}

$env = parse_ini_file(__DIR__.'/../.env', false, INI_SCANNER_RAW);
$dsn = 'mysql:host='.($env['DB_HOST']??'127.0.0.1').';port='.($env['DB_PORT']??'3306').';dbname='.($env['DB_DATABASE']??'').';charset=utf8mb4';

try {
  $pdo = new PDO($dsn, $env['DB_USERNAME']??'', $env['DB_PASSWORD']??'', [
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
  ]);
  $pdo->exec("SET time_zone = '+05:00'");
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'DB connect failed','detail'=>$e->getMessage(),'code'=>500]);
  exit;
}

/* ---------- Utils ---------- */
function intRange($v,$min,$max,$def){$v=filter_var($v,FILTER_VALIDATE_INT); if($v===false)return$def; return max($min,min($max,(int)$v));}
function ymd($s){ if(!$s)return null; $dt=DateTime::createFromFormat('Y-m-d',$s); return $dt?$dt->format('Y-m-d'):null; }
function tableExists(PDO $pdo,string $t):bool{
  $st=$pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
  $st->execute([$t]); return (bool)$st->fetchColumn();
}
function columnSet(PDO $pdo,string $t):array{
  $st=$pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION");
  $st->execute([$t]); return array_map('strval', array_column($st->fetchAll(), 'COLUMN_NAME'));
}
function hasCol(array $cols,string $c):bool{ return in_array($c,$cols,true); }
function likeParam(string $s):string{ return '%'.str_replace(['%','_'],['\\%','\\_'],$s).'%'; }
function csv_list(string $raw, bool $ints=false, int $cap=200): array {
  if ($raw==='') return [];
  $parts = array_values(array_filter(array_map('trim', explode(',', $raw)), 'strlen'));
  $parts = array_slice($parts, 0, $cap);
  if ($ints) {
    $out=[]; foreach ($parts as $p){ $v=filter_var($p, FILTER_VALIDATE_INT); if($v!==false) $out[]=(int)$v; }
    return $out;
  }
  return $parts;
}

/* ---------- Base: student_grades ---------- */
$baseTable = 'student_grades';
if (!tableExists($pdo,$baseTable)) {
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'Base table student_grades not found','code'=>500]);
  exit;
}
$sgCols = columnSet($pdo,$baseTable);

/* ---------- Includes (optional LEFT JOINs) ---------- */
$include = array_values(array_filter(array_map('trim', explode(',', (string)($_GET['include'] ?? '')))));
$include = array_unique(array_filter($include));

$joins = [];
$selectExtra = [];

/* students/employees/subjects presence */
$studentsExists = tableExists($pdo,'students');   $stuColsAvail = $studentsExists ? columnSet($pdo,'students') : [];
$employeesExists= tableExists($pdo,'employees');  $empColsAvail = $employeesExists ? columnSet($pdo,'employees') : [];
$subjectsExists = tableExists($pdo,'subjects');   $subColsAvail = $subjectsExists ? columnSet($pdo,'subjects') : [];

$joinedStudent = false; $joinedEmployee=false; $joinedSubject=false;

if (in_array('student',$include,true) && hasCol($sgCols,'student_id') && $studentsExists) {
  $joins[] = "LEFT JOIN `students` s ON s.`id` = sg.`student_id`";
  $joinedStudent = true;
  if (in_array('full_name',$stuColsAvail,true))        $selectExtra[] = "s.`full_name` AS `student_full_name`";
  if (in_array('student_id_number',$stuColsAvail,true))$selectExtra[] = "s.`student_id_number`";
  if (in_array('hemis_id',$stuColsAvail,true))         $selectExtra[] = "s.`hemis_id` AS `student_hemis_ref`";
  if (in_array('group_name',$stuColsAvail,true))       $selectExtra[] = "s.`group_name`";
  if (in_array('faculty_name',$stuColsAvail,true))     $selectExtra[] = "s.`faculty_name`";
  if (in_array('level_name',$stuColsAvail,true))       $selectExtra[] = "s.`level_name`";
  if (in_array('gender_name',$stuColsAvail,true))      $selectExtra[] = "s.`gender_name`";
}

if (in_array('employee',$include,true) && hasCol($sgCols,'employee_id') && $employeesExists) {
  $joins[] = "LEFT JOIN `employees` e ON e.`id` = sg.`employee_id`";
  $joinedEmployee = true;
  if (in_array('full_name',$empColsAvail,true)) $selectExtra[] = "e.`full_name` AS `employee_full_name`";
  if (in_array('hemis_id',$empColsAvail,true))  $selectExtra[] = "e.`hemis_id` AS `employee_hemis_ref`";
}

if (in_array('subject',$include,true) && hasCol($sgCols,'subject_id') && $subjectsExists) {
  $joins[] = "LEFT JOIN `subjects` sub ON sub.`id` = sg.`subject_id`";
  $joinedSubject = true;
  if (in_array('name',$subColsAvail,true)) $selectExtra[] = "sub.`name` AS `subject_name_ref`";
  if (in_array('code',$subColsAvail,true)) $selectExtra[] = "sub.`code` AS `subject_code_ref`";
}

/* ---------- Filters ---------- */
$page = intRange($_GET['page'] ?? 1, 1, 1000000, 1);
$per  = intRange($_GET['per_page'] ?? 100, 1, 500, 100);
$off  = ($page-1)*$per;

$dateFrom = ymd($_GET['date_from'] ?? null);
$dateTo   = ymd($_GET['date_to']   ?? null);
$updFrom  = ymd($_GET['updated_from'] ?? null);
$updTo    = ymd($_GET['updated_to']   ?? null);

/* csv-style filters */
$levelNames    = csv_list(trim((string)($_GET['level_name'] ?? '')), false);
$semesterNames = csv_list(trim((string)($_GET['semester_name'] ?? '')), false);
$subjectIds    = csv_list(trim((string)($_GET['subject_id'] ?? '')), true); // ints

/* If need level_name through students table */
if ($levelNames && !hasCol($sgCols,'level_name') && $studentsExists && in_array('level_name',$stuColsAvail,true) && hasCol($sgCols,'student_id')) {
  if (!$joinedStudent) {
    $joins[] = "LEFT JOIN `students` s ON s.`id` = sg.`student_id`";
    $joinedStudent = true;
  }
}

$where = [];
$params = [];

/* lesson_date */
if ($dateFrom && hasCol($sgCols,'lesson_date')) {
  $where[] = "sg.`lesson_date` >= ?"; $params[] = $dateFrom.' 00:00:00';
}
if ($dateTo && hasCol($sgCols,'lesson_date')) {
  $where[] = "sg.`lesson_date` <= ?"; $params[] = $dateTo.' 23:59:59';
}

/* updated_at */
if ($updFrom && hasCol($sgCols,'updated_at')) { $where[]="sg.`updated_at` >= ?"; $params[]=$updFrom.' 00:00:00'; }
if ($updTo   && hasCol($sgCols,'updated_at')) { $where[]="sg.`updated_at` <= ?"; $params[]=$updTo.' 23:59:59'; }

/* subject_id: IN list or exact */
if ($subjectIds && hasCol($sgCols,'subject_id')) {
  $ph = implode(',', array_fill(0, count($subjectIds), '?'));
  $where[] = "sg.`subject_id` IN ($ph)";
  foreach ($subjectIds as $v) $params[] = $v;
} elseif (!empty($_GET['subject_id']) && hasCol($sgCols,'subject_id')) {
  $where[]="sg.`subject_id` = ?"; $params[]=(int)$_GET['subject_id'];
}

/* other exact-id filters */
if (!empty($_GET['student_id']) && hasCol($sgCols,'student_id')) {
  $where[]="sg.`student_id` = ?"; $params[]=(int)$_GET['student_id'];
}
if (!empty($_GET['student_hemis_id']) && hasCol($sgCols,'student_hemis_id')) {
  $where[]="sg.`student_hemis_id` = ?"; $params[]=(int)$_GET['student_hemis_id'];
}
if (!empty($_GET['training_type_code']) && hasCol($sgCols,'training_type_code')) {
  $where[]="sg.`training_type_code` = ?"; $params[]=(string)$_GET['training_type_code'];
}
if (!empty($_GET['semester_code']) && hasCol($sgCols,'semester_code')) {
  $where[]="sg.`semester_code` = ?"; $params[]=(string)$_GET['semester_code'];
}

/* level_name (sg or s) */
if ($levelNames) {
  $ph = implode(',', array_fill(0, count($levelNames), '?'));
  if (hasCol($sgCols,'level_name'))       { $where[] = "sg.`level_name` IN ($ph)"; }
  elseif ($joinedStudent && in_array('level_name',$stuColsAvail,true)) { $where[] = "s.`level_name` IN ($ph)"; }
  foreach ($levelNames as $ln) $params[] = $ln;
}

/* semester_name (sg) */
if ($semesterNames && hasCol($sgCols,'semester_name')) {
  $ph = implode(',', array_fill(0, count($semesterNames), '?'));
  $where[] = "sg.`semester_name` IN ($ph)";
  foreach ($semesterNames as $sn) $params[] = $sn;
}

/* q LIKE */
if (!empty($_GET['q'])) {
  $q = (string)$_GET['q'];
  $likeCols = [];
  foreach (['subject_name','subject_code','training_type_name','semester_name','level_name'] as $c) {
    if (hasCol($sgCols,$c)) $likeCols[] = "sg.`$c` LIKE ?";
  }
  if ($joinedStudent && in_array('full_name',$stuColsAvail,true)) $likeCols[] = "s.`full_name` LIKE ?";
  if ($joinedEmployee && in_array('full_name',$empColsAvail,true)) $likeCols[] = "e.`full_name` LIKE ?";
  if ($joinedSubject) {
    if (in_array('name',$subColsAvail,true)) $likeCols[] = "sub.`name` LIKE ?";
    if (in_array('code',$subColsAvail,true)) $likeCols[] = "sub.`code` LIKE ?";
  }
  if ($likeCols) {
    $where[] = '('.implode(' OR ',$likeCols).')';
    $n = substr_count(implode(' ',$likeCols), '?');
    for ($i=0;$i<$n;$i++) $params[] = likeParam($q);
  }
}

$W = $where ? ('WHERE '.implode(' AND ',$where)) : '';

/* ---------- SELECT ---------- */
$selectBase = [];
foreach ($sgCols as $c) { $selectBase[] = "sg.`$c`"; }
$selectList = implode(',', array_merge($selectBase, $selectExtra));

/* ---------- ORDER BY ---------- */
$sort = (string)($_GET['sort'] ?? '-id');
$sortDir = (str_starts_with($sort,'-') ? 'DESC' : 'ASC');
$sortCol = ltrim($sort,'-');

$allowedOrderCols = array_merge($sgCols, [
  'student_full_name','student_id_number','employee_full_name',
  'subject_name_ref','subject_code_ref','level_name','semester_name'
]);
if (!in_array($sortCol,$allowedOrderCols,true)) $sortCol = 'id';
$ob = ($sortCol==='id' && !hasCol($sgCols,'id')) ? $sgCols[0] : $sortCol;

$from = "FROM `{$baseTable}` sg";
if ($joins) $from .= ' ' . implode(' ', $joins);

/* ---------- COUNT ---------- */
try {
  $sqlCount = "SELECT COUNT(1) $from $W";
  $stc = $pdo->prepare($sqlCount);
  $stc->execute($params);
  $total = (int)$stc->fetchColumn();
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'count failed','detail'=>$e->getMessage(),'code'=>500]);
  exit;
}

/* ---------- DATA ---------- */
try {
  $sql = "SELECT $selectList $from $W ORDER BY `$ob` $sortDir LIMIT $per OFFSET $off";
  $st = $pdo->prepare($sql);
  $st->execute($params);
  $rows = $st->fetchAll();

  echo json_encode([
    'success'=>true,
    'meta'=>[
      'page'=>$page,
      'per_page'=>$per,
      'total'=>$total,
      'last_page'=>max(1,(int)ceil($total/$per)),
      'sort'=>$sort,
      'include'=>$include,
      'filters'=>[
        'date_from'=>$dateFrom,'date_to'=>$dateTo,
        'updated_from'=>$updFrom,'updated_to'=>$updTo,
        'level_name'=>$levelNames,
        'semester_name'=>$semesterNames,
        'subject_id'=>$subjectIds,
      ],
      'generated_at'=>gmdate('c'),
    ],
    'data'=>$rows
  ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>'query failed','detail'=>$e->getMessage(),'code'=>500]);
}
