<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use PDO;

class RenderController
{
    public function show(string $siteSlug, string $pageSlug): Response
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('
            SELECT p.title, p.content_json
            FROM pages p
            JOIN sites s ON s.id = p.site_id
            WHERE s.slug = :site_slug
              AND p.slug = :page_slug
              AND p.published = 1
            LIMIT 1
        ');
        $stmt->execute([
            'site_slug' => $siteSlug,
            'page_slug' => $pageSlug,
        ]);
        $page = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$page) {
            http_response_code(404);
            return new Response('Not Found', 404, ['Content-Type' => 'text/plain']);
        }

        $content = $page['content_json'] ? json_decode($page['content_json'], true) : [];
        
        // GrapesJS formatında kaydedilmişse HTML ve CSS'i kullan
        if (isset($content['html']) && isset($content['css'])) {
            $html = $content['html'];
            $css = $content['css'];
        } elseif (isset($content['components'])) {
            // JSON formatında - basit render (ileride geliştirilebilir)
            $html = $this->renderFromComponents($content['components']);
            $css = $content['styles'] ?? '';
        } else {
            // Eski format - blocks array
            $html = $this->renderBlocks($content['blocks'] ?? []);
            $css = '';
        }

        $fullHtml = '<!doctype html><html><head><meta charset="utf-8"><title>' .
            htmlspecialchars($page['title'], ENT_QUOTES, 'UTF-8') .
            '</title><meta name="viewport" content="width=device-width, initial-scale=1">' .
            '<style>body{font-family:system-ui, sans-serif;margin:0;padding:0;}</style>' .
            ($css ? '<style>' . htmlspecialchars($css, ENT_QUOTES, 'UTF-8') . '</style>' : '') .
            '</head><body><div class="container">' . $html . '</div></body></html>';

        return new Response($fullHtml, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public function preview(string $token): Response
    {
        $pdo = Database::connection();

        // Tablo yoksa oluştur
        $this->ensurePreviewTokensTableExists($pdo);

        // Token'ı kontrol et
        $stmt = $pdo->prepare('
            SELECT ppt.page_id, ppt.user_id, p.content_json
            FROM page_preview_tokens ppt
            JOIN pages p ON p.id = ppt.page_id
            WHERE ppt.token = :token
              AND ppt.expires_at > NOW()
            LIMIT 1
        ');
        $stmt->execute(['token' => $token]);
        $preview = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$preview) {
            http_response_code(404);
            return new Response('Preview not found or expired', 404, ['Content-Type' => 'text/plain']);
        }

        // Session kontrolü - sadece token sahibi görebilir
        if (empty($_SESSION['user_id']) || (int)$_SESSION['user_id'] !== (int)$preview['user_id']) {
            http_response_code(403);
            return new Response('Forbidden', 403, ['Content-Type' => 'text/plain']);
        }

        $content = $preview['content_json'] ? json_decode($preview['content_json'], true) : [];
        
        // GrapesJS formatında kaydedilmişse HTML ve CSS'i kullan
        if (isset($content['html']) && isset($content['css'])) {
            $html = $content['html'];
            $css = $content['css'];
        } elseif (isset($content['components'])) {
            // JSON formatında - basit render (ileride geliştirilebilir)
            $html = $this->renderFromComponents($content['components']);
            $css = $content['styles'] ?? '';
        } else {
            // Eski format - blocks array
            $html = $this->renderBlocks($content['blocks'] ?? []);
            $css = '';
        }

        // Efekt animasyon CSS'lerini ekle
        $effectStyles = '
        <style>
        @keyframes gradientFlow {
          0% { background-position: 0% 50%; }
          50% { background-position: 100% 50%; }
          100% { background-position: 0% 50%; }
        }
        @keyframes shimmer {
          0% { background-position: -1000px 0; }
          100% { background-position: 1000px 0; }
        }
        @keyframes radialPulse {
          0%, 100% { background-size: 100% 100%; opacity: 1; }
          50% { background-size: 200% 200%; opacity: 0.8; }
        }
        @keyframes meshGradient {
          0% { background-position: 0% 0%; }
          25% { background-position: 100% 0%; }
          50% { background-position: 100% 100%; }
          75% { background-position: 0% 100%; }
          100% { background-position: 0% 0%; }
        }
        @keyframes stripes {
          0% { background-position: 0 0; }
          100% { background-position: 50px 50px; }
        }
        @keyframes aurora {
          0%, 100% { background-position: 0% 50%; }
          50% { background-position: 100% 50%; }
        }
        @keyframes particles {
          0% { transform: translateY(100%) translateX(0) rotate(0deg); opacity: 0; }
          10% { opacity: 1; }
          50% { transform: translateY(50%) translateX(50px) rotate(180deg); opacity: 1; }
          90% { opacity: 1; }
          100% { transform: translateY(-10%) translateX(100px) rotate(360deg); opacity: 0; }
        }
        @keyframes ripple {
          0% { transform: translate(-50%, -50%) scale(0); opacity: 1; }
          50% { opacity: 0.8; }
          100% { transform: translate(-50%, -50%) scale(6); opacity: 0; }
        }
        @keyframes float {
          0%, 100% { transform: translateY(0px); }
          50% { transform: translateY(-20px); }
        }
        @keyframes rotate {
          0% { transform: rotate(0deg); }
          100% { transform: rotate(360deg); }
        }
        @keyframes slide {
          0% { background-position: 0% 0%; }
          100% { background-position: 100% 100%; }
        }
        @keyframes glow {
          0%, 100% { box-shadow: 0 0 20px rgba(102, 126, 234, 0.5); }
          50% { box-shadow: 0 0 40px rgba(102, 126, 234, 0.8); }
        }
        @keyframes breathe {
          0%, 100% { transform: scale(1); }
          50% { transform: scale(1.05); }
        }
        @keyframes cosmic {
          0% { background-position: 0% 0%; }
          50% { background-position: 100% 100%; }
          100% { background-position: 0% 0%; }
        }
        @keyframes sunset {
          0% { background-position: 0% 0%; }
          50% { background-position: 100% 50%; }
          100% { background-position: 0% 0%; }
        }
        .bg-animation-gradient-flow {
          background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab, #ee7752) !important;
          background-size: 400% 400% !important;
          animation: gradientFlow 8s ease infinite !important;
        }
        .bg-animation-wave {
          background: linear-gradient(45deg, #1f2937 0%, #111827 100%) !important;
          position: relative !important;
          overflow: hidden !important;
        }
        .bg-animation-wave::before {
          content: \'\' !important;
          position: absolute !important;
          top: 0 !important;
          left: -100% !important;
          width: 100% !important;
          height: 100% !important;
          background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent) !important;
          animation: shimmer 3s infinite !important;
        }
        .bg-animation-shimmer {
          background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%) !important;
          background-size: 200% 100% !important;
          animation: shimmer 2s infinite !important;
        }
        .bg-animation-radial-pulse {
          background: radial-gradient(circle, #1f2937 0%, #111827 100%) !important;
          background-size: 100% 100% !important;
          animation: radialPulse 4s ease-in-out infinite !important;
        }
        .bg-animation-mesh {
          background: radial-gradient(at 0% 0%, #667eea 0px, transparent 50%), radial-gradient(at 100% 0%, #764ba2 0px, transparent 50%), radial-gradient(at 100% 100%, #f093fb 0px, transparent 50%), radial-gradient(at 0% 100%, #4facfe 0px, transparent 50%) !important;
          background-size: 200% 200% !important;
          animation: meshGradient 10s ease infinite !important;
        }
        .bg-animation-stripes {
          background: repeating-linear-gradient(45deg, #667eea, #667eea 10px, #764ba2 10px, #764ba2 20px) !important;
          background-size: 50px 50px !important;
          animation: stripes 1s linear infinite !important;
        }
        .bg-animation-aurora {
          background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab, #ee7752) !important;
          background-size: 400% 400% !important;
          animation: aurora 10s ease infinite !important;
          position: relative !important;
          overflow: hidden !important;
        }
        .bg-animation-aurora::before {
          content: \'\' !important;
          position: absolute !important;
          top: -50% !important;
          left: -50% !important;
          width: 200% !important;
          height: 200% !important;
          background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%) !important;
          animation: rotate 20s linear infinite !important;
        }
        .bg-animation-particles {
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
          position: relative !important;
          overflow: hidden !important;
        }
        .bg-animation-particles::before,
        .bg-animation-particles::after {
          content: \'\' !important;
          position: absolute !important;
          width: 6px !important;
          height: 6px !important;
          background: rgba(255,255,255,0.8) !important;
          border-radius: 50% !important;
          animation: particles 6s infinite !important;
        }
        .bg-animation-particles::before {
          left: 20% !important;
          animation-delay: 0s !important;
        }
        .bg-animation-particles::after {
          left: 60% !important;
          animation-delay: 2s !important;
        }
        .bg-animation-ripple {
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
          position: relative !important;
          overflow: hidden !important;
        }
        .bg-animation-ripple::before {
          content: \'\' !important;
          position: absolute !important;
          top: 50% !important;
          left: 50% !important;
          width: 200px !important;
          height: 200px !important;
          margin: -100px 0 0 -100px !important;
          border-radius: 50% !important;
          background: radial-gradient(circle, rgba(255,255,255,0.4) 0%, rgba(255,255,255,0) 70%) !important;
          animation: ripple 2s infinite !important;
        }
        .bg-animation-ripple::after {
          content: \'\' !important;
          position: absolute !important;
          top: 50% !important;
          left: 50% !important;
          width: 200px !important;
          height: 200px !important;
          margin: -100px 0 0 -100px !important;
          border-radius: 50% !important;
          background: radial-gradient(circle, rgba(255,255,255,0.3) 0%, rgba(255,255,255,0) 70%) !important;
          animation: ripple 2s infinite 1s !important;
        }
        .bg-animation-float {
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
          animation: float 6s ease-in-out infinite !important;
        }
        .bg-animation-rotate {
          background: conic-gradient(from 0deg, #667eea, #764ba2, #f093fb, #4facfe, #667eea, #667eea) !important;
          background-size: 200% 200% !important;
          animation: rotate 5s linear infinite !important;
          position: relative !important;
        }
        .bg-animation-rotate::before {
          content: \'\' !important;
          position: absolute !important;
          top: 0 !important;
          left: 0 !important;
          right: 0 !important;
          bottom: 0 !important;
          background: radial-gradient(circle at center, transparent 40%, rgba(0,0,0,0.1) 100%) !important;
          animation: rotate 10s linear infinite reverse !important;
        }
        .bg-animation-slide {
          background: linear-gradient(90deg, #667eea 0%, #764ba2 50%, #f093fb 100%) !important;
          background-size: 200% 100% !important;
          animation: slide 5s ease infinite !important;
        }
        .bg-animation-glow {
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
          animation: glow 1.5s ease-in-out infinite !important;
          box-shadow: 0 0 30px rgba(102, 126, 234, 0.6), inset 0 0 30px rgba(118, 75, 162, 0.4) !important;
        }
        .bg-animation-breathe {
          background: radial-gradient(circle at center, #667eea 0%, #764ba2 100%) !important;
          animation: breathe 3s ease-in-out infinite !important;
        }
        .bg-animation-neon {
          background: linear-gradient(135deg, #0f0c29, #302b63, #24243e) !important;
          position: relative !important;
          box-shadow: 0 0 20px rgba(102, 126, 234, 0.5), inset 0 0 20px rgba(102, 126, 234, 0.2) !important;
          animation: glow 2s ease-in-out infinite !important;
        }
        .bg-animation-fire {
          background: linear-gradient(180deg, #ff6b6b 0%, #ee5a6f 50%, #c92a2a 100%) !important;
          position: relative !important;
          overflow: hidden !important;
        }
        .bg-animation-fire::before {
          content: \'\' !important;
          position: absolute !important;
          bottom: 0 !important;
          left: 0 !important;
          right: 0 !important;
          height: 50% !important;
          background: linear-gradient(to top, rgba(255,255,255,0.3), transparent) !important;
          animation: float 2s ease-in-out infinite !important;
        }
        .bg-animation-ocean {
          background: linear-gradient(180deg, #1e3c72 0%, #2a5298 50%, #7e8ba3 100%) !important;
          position: relative !important;
          overflow: hidden !important;
        }
        .bg-animation-ocean::before {
          content: \'\' !important;
          position: absolute !important;
          bottom: 0 !important;
          left: 0 !important;
          right: 0 !important;
          height: 30% !important;
          background: linear-gradient(to top, rgba(255,255,255,0.2), transparent) !important;
          animation: slide 4s ease-in-out infinite !important;
        }
        .bg-animation-space {
          background: radial-gradient(circle at 20% 50%, #1a1a2e 0%, #16213e 50%, #0f3460 100%) !important;
          position: relative !important;
          overflow: hidden !important;
        }
        .bg-animation-space::before {
          content: \'\' !important;
          position: absolute !important;
          width: 2px !important;
          height: 2px !important;
          background: white !important;
          border-radius: 50% !important;
          box-shadow: 100px 200px white, 300px 100px white, 500px 300px white, 200px 400px white !important;
          animation: particles 10s infinite !important;
        }
        .bg-animation-cosmic {
          background: linear-gradient(135deg, #667eea 0%, #764ba2 25%, #f093fb 50%, #4facfe 75%, #667eea 100%) !important;
          background-size: 400% 400% !important;
          animation: cosmic 8s ease infinite !important;
          position: relative !important;
          overflow: hidden !important;
        }
        .bg-animation-cosmic::before {
          content: \'\' !important;
          position: absolute !important;
          top: -50% !important;
          left: -50% !important;
          width: 200% !important;
          height: 200% !important;
          background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 50%) !important;
          animation: rotate 15s linear infinite !important;
        }
        .bg-animation-sunset {
          background: linear-gradient(180deg, #ff6b6b 0%, #ffa500 25%, #ffd700 50%, #ff6347 75%, #ff1493 100%) !important;
          background-size: 200% 200% !important;
          animation: sunset 10s ease infinite !important;
          position: relative !important;
          overflow: hidden !important;
        }
        .bg-animation-sunset::before {
          content: \'\' !important;
          position: absolute !important;
          top: 0 !important;
          left: 0 !important;
          right: 0 !important;
          height: 40% !important;
          background: radial-gradient(ellipse at center, rgba(255,255,255,0.3) 0%, transparent 70%) !important;
          animation: float 4s ease-in-out infinite !important;
        }
        </style>
        ';
        
        $fullHtml = '<!doctype html><html><head><meta charset="utf-8"><title>Önizleme</title>' .
            $effectStyles .
            '<style>' . htmlspecialchars($css, ENT_QUOTES, 'UTF-8') . '</style></head><body><div class="container">' .
            $html . '</div></body></html>';

        return new Response($fullHtml, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    private function renderBlocks(array $blocks): string
    {
        $out = '';
        foreach ($blocks as $block) {
            $type = $block['type'] ?? 'text';
            $props = $block['props'] ?? [];

            switch ($type) {
                case 'hero':
                    $title = htmlspecialchars($props['title'] ?? '', ENT_QUOTES, 'UTF-8');
                    $subtitle = htmlspecialchars($props['subtitle'] ?? '', ENT_QUOTES, 'UTF-8');
                    $bg = $props['bg'] ?? '#f5f5f5';
                    $padding = $props['padding'] ?? '60px';
                    $out .= "<section class=\"hero\" style=\"background:{$bg};padding:{$padding}\"><h1>{$title}</h1><p>{$subtitle}</p></section>";
                    break;
                case 'text':
                    $text = nl2br(htmlspecialchars($props['text'] ?? '', ENT_QUOTES, 'UTF-8'));
                    $out .= "<p>{$text}</p>";
                    break;
                case 'image':
                    $src = htmlspecialchars($props['src'] ?? '', ENT_QUOTES, 'UTF-8');
                    $alt = htmlspecialchars($props['alt'] ?? '', ENT_QUOTES, 'UTF-8');
                    $out .= "<img src=\"{$src}\" alt=\"{$alt}\" style=\"max-width:100%;height:auto;\"/>";
                    break;
                case 'button':
                    $label = htmlspecialchars($props['label'] ?? 'Button', ENT_QUOTES, 'UTF-8');
                    $url = htmlspecialchars($props['url'] ?? '#', ENT_QUOTES, 'UTF-8');
                    $out .= "<a href=\"{$url}\" class=\"btn\">{$label}</a>";
                    break;
                case 'columns':
                    $children = $block['children'] ?? [];
                    $out .= '<div class="columns">';
                    foreach ($children as $child) {
                        $out .= '<div class="col">' . $this->renderBlocks([$child]) . '</div>';
                    }
                    $out .= '</div>';
                    break;
                case 'spacer':
                    $height = (int)($props['height'] ?? 20);
                    $out .= "<div style=\"height:{$height}px\"></div>";
                    break;
                default:
                    // bilinmeyen blokları yok say
                    break;
            }
        }

        return $out;
    }

    private function renderFromComponents(array $components): string
    {
        // Basit bir render - GrapesJS components JSON'unu HTML'e çevir
        // İleride daha gelişmiş bir parser eklenebilir
        $out = '';
        foreach ($components as $comp) {
            $type = $comp['type'] ?? 'div';
            $content = $comp['content'] ?? '';
            $components = $comp['components'] ?? [];
            $style = $comp['style'] ?? [];
            
            $styleStr = '';
            foreach ($style as $prop => $value) {
                $styleStr .= $prop . ':' . $value . ';';
            }
            
            $out .= '<' . $type . ($styleStr ? ' style="' . htmlspecialchars($styleStr, ENT_QUOTES, 'UTF-8') . '"' : '') . '>';
            if (!empty($components)) {
                $out .= $this->renderFromComponents($components);
            } else {
                $out .= $content;
            }
            $out .= '</' . $type . '>';
        }
        return $out;
    }

    private function ensurePreviewTokensTableExists(PDO $pdo): void
    {
        try {
            // Tablo var mı kontrol et
            $stmt = $pdo->query("SHOW TABLES LIKE 'page_preview_tokens'");
            if ($stmt->rowCount() === 0) {
                // Tablo yoksa oluştur
                $pdo->exec('
                    CREATE TABLE IF NOT EXISTS page_preview_tokens (
                        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        page_id BIGINT UNSIGNED NOT NULL,
                        token VARCHAR(64) NOT NULL UNIQUE,
                        user_id BIGINT UNSIGNED NOT NULL,
                        expires_at TIMESTAMP NOT NULL,
                        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                        CONSTRAINT fk_preview_page FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE,
                        CONSTRAINT fk_preview_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                        INDEX idx_token (token),
                        INDEX idx_expires (expires_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ');
            }
        } catch (\PDOException $e) {
            // Tablo zaten varsa veya başka bir hata varsa sessizce geç
            error_log('Preview tokens table check: ' . $e->getMessage());
        }
    }
}


