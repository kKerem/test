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
        // Önce klasik .env beklentileri
        $host = self::env('DB_HOST');
        $port = self::env('DB_PORT');
        $db = self::env('DB_DATABASE');
        $user = self::env('DB_USERNAME');
        $pass = self::env('DB_PASSWORD');

        // Railway/MySQL eklentisi isimleri
        $host ??= self::env('MYSQL_HOST') ?? self::env('MYSQLHOST') ?? self::env('RAILWAY_PRIVATE_DOMAIN') ?? self::env('RAILWAY_TCP_PROXY_DOMAIN');
        $port ??= self::env('MYSQL_PORT') ?? self::env('MYSQLPORT') ?? self::env('RAILWAY_TCP_PROXY_PORT');
        $db ??= self::env('MYSQL_DATABASE') ?? self::env('MYSQLDATABASE');
        $user ??= self::env('MYSQL_USER') ?? self::env('MYSQLUSER');
        $pass ??= self::env('MYSQL_PASSWORD') ?? self::env('MYSQLPASSWORD') ?? self::env('MYSQL_ROOT_PASSWORD');

        // MYSQL_URL veya MYSQL_PUBLIC_URL varsa parse et
        $url = self::env('MYSQL_URL') ?? self::env('MYSQL_PUBLIC_URL');
        if ($url) {
            $parsed = parse_url($url);
            if (is_array($parsed)) {
                $host ??= $parsed['host'] ?? null;
                $port ??= isset($parsed['port']) ? (string)$parsed['port'] : null;
                $user ??= $parsed['user'] ?? null;
                $pass ??= $parsed['pass'] ?? null;
                if (($parsed['path'] ?? null) !== null) {
                    $db ??= ltrim($parsed['path'], '/');
                }
            }
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
                ]);
                self::ensureSchema(self::$pdo);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Database connection failed']);
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


