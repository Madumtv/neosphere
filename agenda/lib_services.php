<?php
/**
 * Détection dynamique de la table des soins / services.
 * Cherche dans une liste de candidats et retourne le nom trouvé ou null.
 */
function agenda_detect_services_table(PDO $pdo): ?string {
    $candidates = ['services','prestations','soins','soin','types_soins','catalog_soins'];
    foreach ($candidates as $t) {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE '".addslashes($t)."'");
            if ($stmt && $stmt->fetch()) return $t;
        } catch (Throwable $e) { }
    }
    return null;
}

/**
 * Map logique -> colonne réelle pour la table des services.
 * Retourne un tableau: [ 'id' => 'id', 'name' => 'nom_col', 'duration' => 'duree_col', 'max_per_day' => 'max_col', 'active' => 'active_col']
 */
function agenda_map_service_columns(PDO $pdo, string $table): array {
    $desc = [];
    try { $desc = $pdo->query("DESCRIBE `{$table}`")->fetchAll(PDO::FETCH_ASSOC); } catch(Throwable $e) { }
    $cols = array_column($desc,'Field');
    $map = [];
    $map['id'] = in_array('id',$cols)?'id':(in_array($table.'_id',$cols)?$table.'_id':$cols[0]??'id');
    $map['name'] = agenda_find_col($cols,['name','nom','titre','label'],'name');
    $map['duration'] = agenda_find_col($cols,['duration_minutes','duration','duree','length_min','duree_minutes'],'duration_minutes');
    $map['max_per_day'] = agenda_find_col($cols,['max_per_day','max_par_jour','max_day','quota_jour'],'max_per_day');
    $map['active'] = agenda_find_col($cols,['active','actif','is_active','enabled','status'],'active');
    $map['_has_max_per_day'] = in_array($map['max_per_day'],$cols);
    $map['_has_active'] = in_array($map['active'],$cols);
    $map['_has_duration'] = in_array($map['duration'],$cols);
    return $map;
}

function agenda_find_col(array $cols, array $candidates, string $default): string {
    foreach ($candidates as $c) if (in_array($c,$cols)) return $c;
    // partial match fallback
    foreach ($cols as $c) {
        foreach ($candidates as $cand) if (stripos($c,$cand)!==false) return $c;
    }
    return $default; // peut ne pas exister mais évite notices
}

/** Utilitaire pour récupérer tous les services */
function agenda_parse_duration_to_minutes($raw): int {
    if ($raw === null || $raw === '') return 30;
    $raw = trim(strtolower($raw));
    // Remplacer séparateurs
    $raw = str_replace(['mn','min','minutes',' '], ['m','m','m',''], $raw);
    // Formes 1h30, 1h15, 2h, etc.
    if (preg_match('/^(\d+)h(\d{1,2})$/', $raw, $m)) {
        return (int)$m[1]*60 + (int)$m[2];
    }
    if (preg_match('/^(\d+)h$/', $raw, $m)) return (int)$m[1]*60;
    if (preg_match('/^(\d+)m$/', $raw, $m)) return (int)$m[1];
    if (preg_match('/^(\d{1,3})$/', $raw, $m)) {
        // Supposons minutes si <= 180, sinon transformer heuristiquement (ex 120 -> 120)
        return (int)$m[1];
    }
    // Tentative extraction nombres
    if (preg_match_all('/(\d+)/',$raw,$mm) && isset($mm[1][0])) {
        $nums = array_map('intval',$mm[1]);
        if (count($nums)===2) return $nums[0]*60 + $nums[1];
        return $nums[0];
    }
    return 30;
}

function agenda_fetch_services(PDO $pdo): array {
    $tbl = agenda_detect_services_table($pdo);
    if (!$tbl) return [];
    $m = agenda_map_service_columns($pdo,$tbl);
    // Assurer table meta si manques
    $needsMeta = (!$m['_has_max_per_day'] || !$m['_has_active']);
    if ($needsMeta) {
        try { $pdo->exec("CREATE TABLE IF NOT EXISTS service_meta (service_table VARCHAR(64) NOT NULL, service_id INT NOT NULL, max_per_day INT NOT NULL DEFAULT 8, active TINYINT(1) NOT NULL DEFAULT 1, PRIMARY KEY(service_table, service_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch(Throwable $e) {}
    }
    // Déterminer existence éventuelle de grid_id pour filtrage
    $hasGrid = false;
    try {
        $descAll = $pdo->query("DESCRIBE `{$tbl}`")->fetchAll(PDO::FETCH_ASSOC);
        $allCols = array_column($descAll,'Field');
        if (in_array('grid_id',$allCols)) $hasGrid = true;
    } catch(Throwable $e) {}
    // Charger base (ajoute grid_id si présent)
    $selectCols = [$m['id'],$m['name'],$m['duration']];
    if ($m['_has_max_per_day']) $selectCols[] = $m['max_per_day'];
    if ($m['_has_active']) $selectCols[] = $m['active'];
    if ($hasGrid) $selectCols[] = 'grid_id';
    $colsSql = implode(', ', array_map(fn($c)=>"`$c`", $selectCols));
    try {
        $rows = $pdo->query("SELECT {$colsSql} FROM `{$tbl}` ORDER BY `{$m['name']}` ASC")->fetchAll();
    } catch(Throwable $e) { return []; }
    // Charger meta si besoins
    $metaMap = [];
    if ($needsMeta && $rows) {
        $ids = array_map(fn($r)=> (int)($r[$m['id']]??0), $rows);
        $ids = array_filter($ids, fn($v)=>$v>0);
        if ($ids) {
            $in = implode(',', array_map('intval',$ids));
            try {
                $q = $pdo->query("SELECT service_id,max_per_day,active FROM service_meta WHERE service_table='".addslashes($tbl)."' AND service_id IN ($in)");
                foreach ($q as $mr) { $metaMap[(int)$mr['service_id']] = $mr; }
            } catch(Throwable $e) {}
        }
    }
    $out=[]; foreach ($rows as $r) {
        $id = $r[$m['id']]??null;
        $rawDur = $r[$m['duration']] ?? 30;
        $parsedDur = is_numeric($rawDur) ? (int)$rawDur : agenda_parse_duration_to_minutes($rawDur);
        $maxPer = $m['_has_max_per_day'] ? (int)($r[$m['max_per_day']] ?? 8) : ($metaMap[$id]['max_per_day'] ?? 8);
        $active = $m['_has_active'] ? (int)($r[$m['active']] ?? 1) : (int)($metaMap[$id]['active'] ?? 1);
        $rowOut = [
            'id'=>$id,
            'name'=>$r[$m['name']]??'?',
            'duration_minutes'=>$parsedDur,
            'max_per_day'=>$maxPer,
            'active'=>$active,
            '_raw'=>$r,
            '_map'=>$m,
            '_table'=>$tbl,
            '_has_intrinsic'=>$m['_has_max_per_day'] && $m['_has_active'],
        ];
        if (isset($r['grid_id'])) $rowOut['grid_id'] = (int)$r['grid_id'];
        $out[] = $rowOut;
    }
    return $out;
}
