<?php
@ini_set('display_errors','1');
@ini_set('display_startup_errors','1');
error_reporting(E_ALL);
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/lib_services.php';
if(!$pdo){ http_response_code(500); echo json_encode(['error'=>'DB HS']); exit; }
$user_id = $_SESSION['user_id'] ?? null;
if(!$user_id){ http_response_code(401); echo json_encode(['error'=>'Non connecté']); exit; }
$input = $_POST + $_GET; // permet GET rapide pour debug
$slot_id = isset($input['slot_id'])?intval($input['slot_id']):0;
$service_id = isset($input['service_id'])?intval($input['service_id']):0;
try {
  if(!$slot_id && !$service_id) throw new Exception('slot_id ou service_id requis');
  if($slot_id){
    $pdo->beginTransaction();
  $svcTable = agenda_detect_services_table($pdo) ?: 'services';
  $map = agenda_map_service_columns($pdo,$svcTable);
  $durCol = $map['duration'];
  $idCol = $map['id'];
  $slot = $pdo->prepare("SELECT ss.*, s.`{$durCol}` AS duration_minutes, s.`{$idCol}` AS svc_pk FROM service_slots ss JOIN `{$svcTable}` s ON ss.service_id=s.`{$idCol}` WHERE ss.id=? FOR UPDATE");
    $slot->execute([$slot_id]);
    $slot = $slot->fetch();
    if(!$slot) throw new Exception('Créneau introuvable');
    if($slot['status']!=='open') throw new Exception('Créneau fermé');
    if(($slot['capacity'] - $slot['booked_count']) <= 0) throw new Exception('Plus de place');
    // Vérifier quota journalier max_per_day
    $maxPerDay = null; $maxCol=$map['max_per_day'];
    try {
      if($maxCol){
        $st=$pdo->prepare("SELECT `$maxCol` FROM `$svcTable` WHERE `{$map['id']}`=? LIMIT 1");
        $st->execute([$slot['service_id']]); $mpd=$st->fetchColumn(); if($mpd!==false){ $maxPerDay=(int)$mpd; if($maxPerDay<=0) $maxPerDay=null; }
      } else {
        $st=$pdo->prepare("SELECT meta_value FROM service_meta WHERE service_id=? AND meta_key='max_per_day' LIMIT 1");
        $st->execute([$slot['service_id']]); $mv=$st->fetchColumn(); if($mv!==false){ $maxPerDay=(int)$mv?:null; }
      }
    } catch(Throwable $e){}
    if($maxPerDay){
      $chk=$pdo->prepare("SELECT SUM(booked_count) FROM service_slots WHERE service_id=? AND slot_date=?");
      $chk->execute([$slot['service_id'],$slot['slot_date']]);
      $dayBooked=(int)$chk->fetchColumn();
      if($dayBooked >= $maxPerDay) throw new Exception('Quota journalier atteint');
    }
    // créer rendez-vous
    $startDT = $slot['slot_date'].' '.$slot['start_time'];
    $endDT = $slot['slot_date'].' '.$slot['end_time'];
    $ins = $pdo->prepare('INSERT INTO appointments(user_id, service_id, slot_id, start_datetime, end_datetime, status) VALUES (?,?,?,?,?,?)');
    $ins->execute([$user_id,$slot['service_id'],$slot_id,$startDT,$endDT,'pending']);
  $pdo->prepare('UPDATE service_slots SET booked_count=booked_count+1 WHERE id=?')->execute([$slot_id]);
    // fermer si plein
    $pdo->prepare('UPDATE service_slots SET status="closed" WHERE id=? AND booked_count>=capacity')->execute([$slot_id]);
    $pdo->commit();
    echo json_encode(['ok'=>1,'appointment_id'=>$pdo->lastInsertId()]);
  } else {
    // réservation ad hoc sans slot prédéfini: calculer end_datetime via durée du service
  $svcTable = agenda_detect_services_table($pdo) ?: 'services';
  $map = agenda_map_service_columns($pdo,$svcTable);
  $idCol = $map['id'];
  $durCol = $map['duration'];
  $svc = $pdo->prepare("SELECT * FROM `{$svcTable}` WHERE `{$idCol}`=?");
    $svc->execute([$service_id]);
    $svc=$svc->fetch();
    if(!$svc) throw new Exception('Service introuvable');
    $start = $input['start'] ?? null; // format YYYY-MM-DD HH:MM
    if(!$start) throw new Exception('start requis');
    $startDT = DateTime::createFromFormat('Y-m-d H:i', $start);
    if(!$startDT) throw new Exception('Format start invalide');
  $durationVal = $svc[$durCol] ?? 30;
  $endDT = (clone $startDT)->modify('+'.intval($durationVal).' minutes');
    $ins = $pdo->prepare('INSERT INTO appointments(user_id, service_id, slot_id, start_datetime, end_datetime, status) VALUES (?,?,?,?,?,?)');
    $ins->execute([$user_id,$service_id,null,$startDT->format('Y-m-d H:i:s'),$endDT->format('Y-m-d H:i:s'),'pending']);
    echo json_encode(['ok'=>1,'appointment_id'=>$pdo->lastInsertId()]);
  }
} catch(Exception $e){ if($pdo->inTransaction()) $pdo->rollBack(); http_response_code(400); echo json_encode(['error'=>$e->getMessage()]); }
