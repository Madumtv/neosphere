<?php
/** Helpers chargement de blocs de contenu versionnés */
require_once __DIR__.'/db.php';

if(!function_exists('content_get')){
    function content_get(string $slug, string $fallback=''): string {
        global $pdo; if(!$pdo) return $fallback;
        try {
            $st = $pdo->prepare('SELECT content FROM content_blocks WHERE slug=? LIMIT 1');
            $st->execute([$slug]);
            $row = $st->fetch();
            if($row && $row['content']!==null && $row['content']!=='') return $row['content'];
        } catch(Throwable $e){ /* log silencieux */ }
        return $fallback;
    }
}

// Version sécurisée : autorise un sous-ensemble de balises + conversion emojis custom
if(!function_exists('content_get_safe')){
    /**
     * Récupère un bloc et applique un filtrage basique (whitelist de balises) + nettoyage des attributs dangereux.
     * ATTENTION: pour un site public sensible préférer une librairie spécialisée (HTML Purifier).
     * @param array $allowedTags Liste de balises autorisées (sans chevrons)
     */
    function content_get_safe(string $slug, string $fallback='', array $allowedTags=['br','strong','em','b','i','p','ul','ol','li','span','a']): string {
        $raw = content_get($slug, $fallback);
        if($raw==='') return '';
        // Autoriser seulement les balises choisies
        $allowString = '<'.implode('><',$allowedTags).'>';
        $clean = strip_tags($raw, $allowString);
        // Supprimer attributs dangereux (on* events, javascript:)
        // Retire onXXX="..."
        $clean = preg_replace('/\s+on[a-zA-Z]+\s*=\s*("[^"]*"|\'[^\']*\')/i','',$clean);
        // Retire javascript: dans href/src/style
        $clean = preg_replace('/(href|src|style)\s*=\s*("|\')(javascript:|data:text\/html)/i','$1="#"',$clean);
        // Limiter target à _blank ou _self uniquement
        $clean = preg_replace_callback('/<a\b([^>]*)>/i', function($m){
            $attrs = $m[1];
            // Forcer rel pour sécurité si target _blank
            if(preg_match('/target\s*=\s*("|\')?_blank/',$attrs)){
                if(!preg_match('/rel\s*=/i',$attrs)) $attrs .= ' rel="noopener noreferrer"';
            }
            return '<a'.$attrs.'>';
        }, $clean);
        return $clean;
    }
}

if(!function_exists('content_render')){
    /**
     * Récupère un bloc, applique safe + remplace codes emoji simples (:phone:, :email:, etc.)
     */
    function content_render(string $slug, string $fallback=''): string {
        $txt = content_get_safe($slug, $fallback);
        if($txt==='') return '';
        static $emojiMap = [
            ':phone:'=>'📞', ':email:'=>'✉️', ':mail:'=>'✉️', ':map:'=>'📍', ':location:'=>'📍', ':time:'=>'🕒', ':clock:'=>'🕒',
            ':heart:'=>'❤️', ':ok:'=>'✅', ':check:'=>'✅', ':warning:'=>'⚠️', ':info:'=>'ℹ️'
        ];
        $txt = str_replace(array_keys($emojiMap), array_values($emojiMap), $txt);
        return $txt;
    }
}

// Rend un bloc avec conversion automatique des retours à la ligne en <br> si aucune balise de saut déjà présente
if(!function_exists('content_render_br')){
    function content_render_br(string $slug, string $fallback=''): string {
        $html = content_render($slug, $fallback);
        // Si déjà des <br> ou des paragraphes, ne rien faire de plus
        if(stripos($html,'<br')===false && stripos($html,'<p')===false){
            $html = nl2br($html,false); // false => <br> au lieu de <br />
        }
        return $html;
    }
}

// Version spécifique horaires : ajoute une icône devant les lignes 2+ si absente
if(!function_exists('content_render_hours')){
    function content_render_hours(string $slug, string $fallback='', string $icon='⏰'): string {
        $html = content_render_br($slug, $fallback);
        // Normaliser: séparer sur <br>
        $parts = preg_split('/<br\s*\/?>(?![^<]*>)/i', $html);
        if(!$parts || count($parts)<=1) return $html; // une seule ligne
        foreach($parts as $i=>&$p){
            $trim = trim($p);
            if($i>0){
                // Ajouter l'icône seulement si elle n'est pas déjà présente au début
                if($trim!=='' && !preg_match('/^'.preg_quote($icon,'/').'/u', $trim)){
                    $p = $icon.' '.ltrim($p);
                }
            }
        }
        return implode('<br>', $parts);
    }
}
