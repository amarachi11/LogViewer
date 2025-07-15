<?php

require __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


function loadEnv($path = __DIR__ . '/../.env') {
    if (!file_exists($path)) return;

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;

        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}
loadEnv();


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'getLogFolders':
            getLogFolders();
            break;

        case 'signupUser':
            signupUser();
            break;

        case 'getLogFiles':
            getLogFilesInFolder();
            break;

        case 'readLogFile':
            readSelectedLogFile();
            break;
        case 'getUsers':
            getUsers();
            break;
        case 'getPendingUsers':
            getPendingUsers();
            break;
        case 'getUser':
            getUser();
            break;
        case 'updateUser':
            updateUser();
            break;
        case 'updateUserStatus':
            updateUserStatus();
            break;


        default:
            echo json_encode(['error' => 'Invalid action']);
    }
}

function dbConnect()
{
    try {
        $dsn = "mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_NAME'];
        return new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'DB connection failed: ' . $e->getMessage()]);
        exit;
    }
}

function sendAdminNotification($userEmail)
{
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $_ENV['MAIL_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['MAIL_USER'];
        $mail->Password   = $_ENV['MAIL_PASS'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $_ENV['MAIL_PORT'];

        $mail->setFrom($_ENV['MAIL_FROM'], $_ENV['MAIL_FROM_NAME']);
        $mail->addAddress('okezie.austin@dufil.com');
        $mail->addAddress('okezie.austin@tolaram.com');

        $mail->isHTML(true);
        $mail->Subject = 'New User Signup Request';
        $mail->Body = "
            <h2>New Sign-Up Request</h2>
            <p>Email: <strong>{$userEmail}</strong></p>
            <p><a href='http://localhost/admin/users.php'>Go to Admin Panel to approve or reject</a></p>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

function signupUser()
{
    
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $password = trim($_POST['password'] ?? '');
    $confirm = trim($_POST['confirm'] ?? '');

    if (!$email) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email']);
        return;
    }

    if (strlen($password) < 6) {
        echo json_encode(['status' => 'error', 'message' => 'Password must be at least 6 characters']);
        return;
    }

    if ($password !== $confirm) {
        echo json_encode(['status' => 'error', 'message' => 'Passwords do not match']);
        return;
    }

    try {
        $pdo = dbConnect();

        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'Email already registered']);
            return;
        }

        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        // Insert user
        $stmt = $pdo->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
        $stmt->execute([$email, $hashedPassword]);

        if ($stmt->rowCount() === 1) {
            // Only send mail if insert succeeded
            if (sendAdminNotification($email)) {
                echo json_encode(['status' => 'success', 'message' => 'Registration successful. Await admin approval.']);
            } else {
                echo json_encode(['status' => 'warning', 'message' => 'User saved, but email failed to send']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'User registration failed.']);
        }

    } catch (Exception $e) {
        error_log("Signup error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Signup failed. Try again later.']);
    }
}



function getLogFolders() {
    $baseDir = "C:\\wamp64\\www";
    $foldersWithLogs = [];

    if (!is_dir($baseDir)) {
        echo json_encode($foldersWithLogs);
        return;
    }

    $dirIterator = new DirectoryIterator($baseDir);
    foreach ($dirIterator as $fileInfo) {
        if ($fileInfo->isDot() || !$fileInfo->isDir()) continue;

        $folderPath = $fileInfo->getPathname();
        if (folderContainsLogFile($folderPath)) {
            $foldersWithLogs[] = $fileInfo->getFilename();
        }
    }

    echo json_encode($foldersWithLogs);
}

function folderContainsLogFile($folder) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'log') {
            return true;
        }
    }

    return false;
}

function getLogFilesInFolder() {
    $folder = $_POST['folder'] ?? '';
    $folderPath = "C:\\wamp64\\www\\$folder";

    $logFiles = [];

    if (!is_dir($folderPath)) {
        echo json_encode($logFiles);
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($folderPath, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $fileInfo) {
        if ($fileInfo->isFile() && strtolower($fileInfo->getExtension()) === 'log') {
            $relativePath = str_replace($folderPath . DIRECTORY_SEPARATOR, '', $fileInfo->getPathname());
            $logFiles[] = $relativePath;
        }
    }

    echo json_encode($logFiles);
}

function readSelectedLogFile() {
    $folder = $_POST['folder'] ?? '';
    $file = $_POST['file'] ?? '';

    $basePath = "C:\\wamp64\\www\\$folder";
    $relativePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file);
    $fullPath = $basePath . DIRECTORY_SEPARATOR . $relativePath;

    if (!file_exists($fullPath)) {
        echo "File not found.";
        return;
    }

    if (!is_readable($fullPath)) {
        echo "File is not readable.";
        return;
    }

    echo file_get_contents($fullPath);
}


function getUsers() {
    $pdo = dbConnect();
    $stmt = $pdo->query("SELECT email, role, status FROM users WHERE status NOT IN ('pending', 'declined') ORDER BY email");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function getPendingUsers() {
    $pdo = dbConnect();
    $stmt = $pdo->prepare("SELECT email FROM users WHERE status = 'pending'");
    $stmt->execute();
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function getUser() {
    $email = $_POST['email'] ?? '';
    $pdo = dbConnect();
    $stmt = $pdo->prepare("SELECT email, role, status FROM users WHERE email = ?");
    $stmt->execute([$email]);
    echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
}

function updateUser() {
    $email = $_POST['email'];
    $role = $_POST['role'];
    $status = $_POST['status'];
    $pdo = dbConnect();
    $stmt = $pdo->prepare("UPDATE users SET role = ?, status = ? WHERE email = ?");
    $stmt->execute([$role, $status, $email]);
    echo json_encode(['status' => 'ok']);
}

function updateUserStatus() {
    $email = $_POST['email'];
    $status = $_POST['status'];
    $pdo = dbConnect();
    $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE email = ?");
    $stmt->execute([$status, $email]);
    echo json_encode(['status' => 'ok']);
}
