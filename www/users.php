<?php

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

function getDbConnection() {
    $host = 'db'; // Имя сервиса, определенное в docker-compose
    $db = 'users_db';
    $user = 'user';
    $pass = 'password';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        error_log($e->getMessage(), 3, '/var/log/app_errors.log');
        echo json_encode(['error' => 'Database connection failed'], JSON_UNESCAPED_UNICODE);
        exit();
    }
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $pdo = getDbConnection();
        if (!array_key_exists('id', $_GET)) {
            $stmt = $pdo->query("SELECT * FROM users");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($users, JSON_UNESCAPED_UNICODE);
            exit();
        }

        $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid ID'], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) echo json_encode($user, JSON_UNESCAPED_UNICODE);
        else {
            http_response_code(404);
            echo json_encode(['error' => 'User not found'], JSON_UNESCAPED_UNICODE);
        }
        break;

    case 'POST':
        // Проверяем, есть ли свойство json
        $isJsonRequest = isset($_GET['json']) && $_GET['json'] === 'true';

        if (!$isJsonRequest)  $inputData = $_POST; // Если json не true, используем $_POST
        else {
            $inputData = json_decode(file_get_contents('php://input'), true);

            if (empty($inputData)) {
                http_response_code(400);
                echo json_encode(['error' => 'No input data provided.'], JSON_UNESCAPED_UNICODE);
                exit();
            }

            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Invalid JSON.',
                    'message' => json_last_error_msg()
                ], JSON_UNESCAPED_UNICODE);
                exit();
            }
        }

        if (!isset($inputData['name'], $inputData['password'], $inputData['gender'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Name, password and gender are required'], JSON_UNESCAPED_UNICODE);
            exit();
        }

        if (strlen($inputData['password']) < 8) {
            http_response_code(400);
            echo json_encode(['error' => 'Password must be at least 8 characters long'], JSON_UNESCAPED_UNICODE);
            exit();
        }
        
        $name = $inputData['name'];
        $passwordHash = password_hash($inputData['password'], PASSWORD_BCRYPT);
        $gender = $inputData['gender'];

        if (strlen($name) < 3 || !preg_match("/^[a-zA-Z ]+$/", $name)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid name. It must be at least 3 characters and contain only letters and spaces.'], JSON_UNESCAPED_UNICODE);
            exit();
        }

        if (!in_array($gender, ['male', 'female'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid gender'], JSON_UNESCAPED_UNICODE);
            exit();
        }

        $pdo = getDbConnection();
        
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO users (name, password_hash, gender) VALUES (:name, :password_hash, :gender)");
            $stmt->execute([
                ':name' => $name,
                ':password_hash' => $passwordHash,
                ':gender' => $gender
            ]);

            if ($stmt->rowCount() > 0) {
                http_response_code(201);
                echo json_encode(['message' => 'User created successfully'], JSON_UNESCAPED_UNICODE);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to create user'], JSON_UNESCAPED_UNICODE);
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollback();
            http_response_code(500);
            echo json_encode(['error' => 'Database transaction failed'], JSON_UNESCAPED_UNICODE);
            exit();
        }
        
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
        break;
}
