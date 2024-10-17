<?php

header('Content-Type: application/json; charset=utf-8');

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
        $message = $e->getMessage();
        echo json_encode(['error' => "Database connection failed: $message"]);
        exit();
    }
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $pdo = getDbConnection();
        if (!array_key_exists('id', $_GET)) {
            $stmt = $pdo->query("SELECT * FROM users");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($users);
            exit();
        }

        $id = (int)$_GET['id'];
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) echo json_encode($user);
        else {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
        }
        break;

    case 'POST':
        // Проверяем, есть ли свойство json в теле запроса
        $isJsonRequest = isset($_POST['json']) && $_POST['json'] === 'true';

        if (!$isJsonRequest)  $inputData = $_POST; // Если json не true, используем $_POST
        else {
            $inputData = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid JSON.']);
                exit();
            }
        }

        $name = $inputData['name'] ?? null;
        $passwordHash = md5($inputData['password'] ?? '');
        $gender = $inputData['gender'] ?? null;

        if (!$name || !$gender) {
            http_response_code(400);
            echo json_encode(['error' => 'Name and gender are required']);
            exit();
        }

        $pdo = getDbConnection();
        $stmt = $pdo->prepare("INSERT INTO users (name, password_hash, gender) VALUES (:name, :password_hash, :gender)");
        $stmt->execute([
            ':name' => $name,
            ':password_hash' => $passwordHash,
            ':gender' => $gender
        ]);

        echo json_encode(['message' => 'User created successfully']);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}
