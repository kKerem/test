<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $pdo = null;

    /**
     * Ortam değişkenini $_ENV veya getenv üzerinden okur.
     */
    private static function env(string $key, ?string $default = null): ?string
    {
        $value = $_ENV[$key] ?? getenv($key);
        return $value !== false && $value !== null ? $value : $default;
    }

    /**
     * Railway/MySQL ortam değişkenlerini normalize eder.
     */
    private static function resolveConfig(): array
    {
        $host = null;
        $port = null;
        $db = null;
        $user = null;
        $pass = null;

        // Railway değişkenleri varsa öncelik onları alacak
        // Önce MYSQL_URL'i kontrol et (Railway otomatik olarak bunu sağlar)
        $url = self::env('MYSQL_URL');
        if ($url && !empty($url)) {
            // Template değişkenleri içeriyorsa skip et (Railway henüz resolve etmemiş)
            if (strpos($url, '${{') === false) {
                $parsed = parse_url($url);
                if (is_array($parsed) && isset($parsed['host'])) {
                    $host = $parsed['host'];
                    $port = isset($parsed['port']) ? (string)$parsed['port'] : '3306';
                    $user = $parsed['user'] ?? null;
                    $pass = $parsed['pass'] ?? null;
                    if (isset($parsed['path'])) {
                        $db = ltrim($parsed['path'], '/');
                    }
                }
            }
        }

        // Eğer URL'den alamadıysak, tek tek değişkenleri kontrol et
        if (!$host) {
            $host = self::env('MYSQL_HOST') 
                ?? self::env('MYSQLHOST') 
                ?? self::env('RAILWAY_PRIVATE_DOMAIN');
        }
        
        if (!$port) {
            $port = self::env('MYSQL_PORT') 
                ?? self::env('MYSQLPORT') 
                ?? '3306';
        }
        
        if (!$db) {
            $db = self::env('MYSQL_DATABASE') 
                ?? self::env('MYSQLDATABASE');
        }
        
        if (!$user) {
            $user = self::env('MYSQL_USER') 
                ?? self::env('MYSQLUSER');
        }
        
        if (!$pass) {
            $pass = self::env('MYSQL_PASSWORD') 
                ?? self::env('MYSQLPASSWORD') 
                ?? self::env('MYSQL_ROOT_PASSWORD');
        }

        // Railway değişkenleri yoksa klasik .env beklentilerine bak
        if (!$host) {
            $host = self::env('DB_HOST');
        }
        if (!$port) {
            $port = self::env('DB_PORT');
        }
        if (!$db) {
            $db = self::env('DB_DATABASE');
        }
        if (!$user) {
            $user = self::env('DB_USERNAME');
        }
        if (!$pass) {
            $pass = self::env('DB_PASSWORD');
        }

        // Varsayılanlar (lokal geliştirme)
        return [
            'host' => $host ?? 'localhost',
            'port' => $port ?? '3306',
            'db' => $db ?? 'prensmedya',
            'user' => $user ?? 'root',
            'pass' => $pass ?? 'root',
        ];
    }

    /**
     * Debug için config'i döndürür (sadece development için)
     */
    public static function getConfig(): array
    {
        return self::resolveConfig();
    }

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            $config = self::resolveConfig();

            $host = $config['host'];
            $port = $config['port'];
            $db = $config['db'];
            $user = $config['user'];
            $pass = $config['pass'];

            $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";

            try {
                self::$pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT => 5,
                ]);
                self::ensureSchema(self::$pdo);
            } catch (PDOException $e) {
                // Hata ayıklama için daha detaylı mesaj (production'da kaldırılabilir)
                $errorMsg = 'Database connection failed';
                if (getenv('APP_DEBUG') === 'true' || getenv('APP_ENV') === 'local') {
                    $errorMsg .= ': ' . $e->getMessage() . ' (Host: ' . $host . ', Port: ' . $port . ', DB: ' . $db . ')';
                }
                http_response_code(500);
                echo json_encode(['error' => $errorMsg]);
                exit;
            }
        }

        return self::$pdo;
    }

    /**
     * Kullanıcı tabloyu henüz oluşturmadıysa schema.sql içeriğini çalıştır.
     */
    private static function ensureSchema(PDO $pdo): void
    {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
            if ($stmt->fetchColumn()) {
                return;
            }
        } catch (PDOException) {
            // devam et ve schema yüklemeyi dene
        }

        $schemaFile = __DIR__ . '/../../database/schema.sql';
        if (!is_file($schemaFile)) {
            return;
        }

        $sql = file_get_contents($schemaFile);
        if ($sql === false) {
            return;
        }

        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $statement) {
            if ($statement === '' || str_starts_with($statement, '--')) {
                continue;
            }
            try {
                $pdo->exec($statement);
            } catch (PDOException) {
                // sessiz geç; tekrar tekrar schema çalıştırmaya gerek yok
            }
        }
    }
}

