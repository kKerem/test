<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            // MAMP / lokal geliştirme için varsayılanlar:
            // host=localhost, db=prensmedya, user=root, pass=""
            $host = $_ENV['DB_HOST'] ?? 'localhost';
            $port = $_ENV['DB_PORT'] ?? '3306';
            $db = $_ENV['DB_DATABASE'] ?? 'prensmedya';
            $user = $_ENV['DB_USERNAME'] ?? 'root';
            $pass = $_ENV['DB_PASSWORD'] ?? 'root';

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


