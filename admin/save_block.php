<?php
session_start();
require_once __DIR__.'/../inc/auth.php';
header('Content-Type: application/json; charset=utf-8');
if(!auth_is_admin()){
  http_response_code(403);
  echo json_encode(['success'=>false,'error'=>'forbidden']);
  exit;
}
require_once __DIR__.'/inc/db.php';
if(!$pdo){ echo json_encode(['success'=>false,'error'=>'db']); exit; }
$slug = trim($_POST['slug'] ?? '');
$content = $_POST['content'] ?? '';
$csrf = $_POST['csrf'] ?? '';
if(!auth_csrf_check($csrf)){ http_response_code(400); echo json_encode(['success'=>false,'error'=>'csrf']); exit; }
if($slug===''){ echo json_encode(['success'=>false,'error'=>'slug']); exit; }
try{
  $stmt=$pdo->prepare('SELECT id FROM content_blocks WHERE slug=? LIMIT 1');
  $stmt->execute([$slug]);
  $id=$stmt->fetchColumn();
  if($id){
    $u=$pdo->prepare('UPDATE content_blocks SET content=?, updated_at=NOW() WHERE id=? LIMIT 1');
    $u->execute([$content,$id]);
  }else{
    $i=$pdo->prepare('INSERT INTO content_blocks (slug,title,content,updated_by) VALUES (?,?,?,NULL)');
    $i->execute([$slug,$slug,$content]);
  }
  echo json_encode(['success'=>true]);
}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
