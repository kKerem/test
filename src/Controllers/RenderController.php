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

        $fullHtml = '<!doctype html><html><head><meta charset="utf-8"><title>Önizleme</title>' .
            '<style>' . htmlspecialchars($css) . '</style></head><body><div class="container">' .
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


