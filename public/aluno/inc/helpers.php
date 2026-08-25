<?php
/**
 * Helpers de view da área do aluno.
 * Sem dependência de banco — usados tanto no modo demo quanto em produção.
 */
declare(strict_types=1);

/** Escape seguro para HTML. */
function e(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Segundos -> "12 min" ou "1h 05" */
function fmt_duration(int $seconds): string
{
    if ($seconds <= 0) {
        return '--';
    }
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    if ($h > 0) {
        return $h . 'h ' . str_pad((string) $m, 2, '0', STR_PAD_LEFT);
    }
    return $m . ' min';
}

/** Percentual inteiro 0..100 a partir de assistido/duração. */
function progress_pct(int $watched, int $duration): int
{
    if ($duration <= 0) {
        return 0;
    }
    return (int) max(0, min(100, round($watched / $duration * 100)));
}

/**
 * Detecta o provedor de vídeo pela URL (player agnóstico).
 * Retorna: youtube | vimeo | bunny | file | other
 */
function detect_provider(string $url, string $hint = 'other'): string
{
    $u = strtolower($url);
    if (str_contains($u, 'youtube.com') || str_contains($u, 'youtu.be')) {
        return 'youtube';
    }
    if (str_contains($u, 'vimeo.com')) {
        return 'vimeo';
    }
    if (str_contains($u, 'mediadelivery.net') || str_contains($u, 'b-cdn.net')) {
        return 'bunny';
    }
    if (preg_match('/\.(mp4|webm|ogg)(\?|$)/', $u)) {
        return 'file';
    }
    return in_array($hint, ['youtube', 'vimeo', 'bunny', 'file'], true) ? $hint : 'other';
}

/** Extrai o ID de vídeos do YouTube em formatos comuns. */
function youtube_id(string $url): ?string
{
    if (preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|v/))([\w-]{11})~', $url, $m)) {
        return $m[1];
    }
    return null;
}

/**
 * Monta o HTML do player a partir da aula.
 * URLs simuladas (provider "other") caem num placeholder com selo, em vez
 * de tentar incorporar um vídeo inexistente — nunca mistura mock com real.
 */
function video_embed(array $lesson): string
{
    $url = (string) ($lesson['video_url'] ?? '');
    $provider = detect_provider($url, (string) ($lesson['video_provider'] ?? 'other'));

    if ($provider === 'youtube' && ($id = youtube_id($url))) {
        $src = 'https://www.youtube-nocookie.com/embed/' . $id . '?rel=0&modestbranding=1';
        return '<iframe src="' . e($src) . '" allow="accelerated-destination; encrypted-media; picture-in-picture" allowfullscreen loading="lazy" title="' . e($lesson['title'] ?? 'Aula') . '"></iframe>';
    }
    if ($provider === 'vimeo' && preg_match('~vimeo\.com/(\d+)~', $url, $m)) {
        $src = 'https://player.vimeo.com/video/' . $m[1];
        return '<iframe src="' . e($src) . '" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen loading="lazy" title="' . e($lesson['title'] ?? 'Aula') . '"></iframe>';
    }
    if ($provider === 'bunny') {
        return '<iframe src="' . e($url) . '" allow="accelerated-2d-canvas; fullscreen" allowfullscreen loading="lazy" title="' . e($lesson['title'] ?? 'Aula') . '"></iframe>';
    }
    if ($provider === 'file') {
        return '<video controls preload="metadata" src="' . e($url) . '"></video>';
    }

    // other -> prévia simulada
    return '<div class="player__mock">'
        . '<i class="ti ti-player-play-filled" aria-hidden="true"></i>'
        . '<div><b>Prévia simulada</b><div style="font-size:12.5px;color:var(--text-mute);margin-top:4px;">Troque pela URL real do vídeo no painel admin.</div></div>'
        . '</div>';
}

/** Iniciais para o avatar. */
function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $a = $parts[0][0] ?? '?';
    $b = count($parts) > 1 ? $parts[count($parts) - 1][0] : '';
    return strtoupper($a . $b);
}
