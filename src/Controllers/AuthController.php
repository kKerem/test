<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use PDO;

class AuthController
{
    public function register(): Response
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $name = trim($input['name'] ?? '');
        $email = strtolower(trim($input['email'] ?? ''));
        $password = $input['password'] ?? '';

        // Kullanıcı adı veya e-posta kabul et: sadece boş olmaması ve temel şifre uzunluğu kontrolü
        if ($name === '' || $email === '' || strlen($password) < 4) {
            return Response::json(['error' => 'Kullanıcı adı/e-posta ve en az 4 karakterli bir şifre girin'], 422);
        }

        $pdo = Database::connection();

        // email unique
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        if ($stmt->fetch()) {
            return Response::json(['error' => 'Email already taken'], 422);
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, created_at) VALUES (:name, :email, :password, :role, NOW())');
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'password' => $hash,
            'role' => 'user',
        ]);

        $userId = (int)$pdo->lastInsertId();

        $_SESSION['user_id'] = $userId;
        $_SESSION['role'] = 'user';

        return Response::json([
            'id' => $userId,
            'name' => $name,
            'email' => $email,
            'role' => 'user',
        ], 201);
    }

    public function login(): Response
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $identifier = strtolower(trim($input['email'] ?? ''));
        $password = $input['password'] ?? '';

        if ($identifier === '' || $password === '') {
            return Response::json(['error' => 'Kullanıcı adı/e-posta ve şifre gerekli'], 422);
        }

        $pdo = Database::connection();
        // E-posta veya kullanıcı adı (name) ile giriş desteği
        $stmt = $pdo->prepare('SELECT id, name, email, password_hash, role FROM users WHERE LOWER(email) = :id OR LOWER(name) = :id LIMIT 1');
        $stmt->execute(['id' => $identifier]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return Response::json(['error' => 'Kullanıcı bulunamadı'], 401);
        }

        if (!password_verify($password, $user['password_hash'])) {
            return Response::json(['error' => 'Şifre hatalı'], 401);
        }

        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['role'] = $user['role'];

        return Response::json([
            'id' => (int)$user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
        ]);
    }

    public function logout(): Response
    {
        session_destroy();
        return Response::json(['message' => 'Logged out']);
    }
    
    public function check(): Response
    {
        if (empty($_SESSION['user_id'])) {
            return Response::json(['authenticated' => false], 401);
        }
        
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, name, email, role FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            session_destroy();
            return Response::json(['authenticated' => false], 401);
        }
        
        return Response::json([
            'authenticated' => true,
            'user' => [
                'id' => (int)$user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
            ],
        ]);
    }
}


