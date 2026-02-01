<?php 
date_default_timezone_set('Asia/Jerusalem');

// מניעת פלט מיותר שעלול לשבור את ה-JSON
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json; charset=utf-8');

$conn = new mysqli("localhost", "root", "", "it_vault");

if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "DB Connection Failed"]));
}

$conn->set_charset("utf8mb4");

// --- פונקציית עזר ל-Audit Log ---
function log_event($conn, $username, $action, $details = "") {
    $username = $conn->real_escape_string($username ?: 'System/Guest');
    $action = $conn->real_escape_string($action);
    $details = $conn->real_escape_string($details);
    $ip = $_SERVER['REMOTE_ADDR'];
    
    $conn->query("INSERT INTO audit_logs (username, action, details, ip_address) 
                  VALUES ('$username', '$action', '$details', '$ip')");
}

// --- פונקציות עזר ל-2FA ---
function verify_totp($secret, $code) {
    $secret = strtoupper(str_replace([' ', '-'], '', $secret));
    $code = str_replace(' ', '', $code);
    $checkTime = floor(time() / 30);
    for ($i = -1; $i <= 1; $i++) {
        $t = $checkTime + $i;
        $decodedSecret = base32_decode($secret);
        $sha1 = hash_hmac('sha1', pack('N*', 0, $t), $decodedSecret, true);
        $offset = ord($sha1[19]) & 0xf;
        $hash = (((ord($sha1[$offset+0]) & 0x7f) << 24) | ((ord($sha1[$offset+1]) & 0xff) << 16) | ((ord($sha1[$offset+2]) & 0xff) << 8) | (ord($sha1[$offset+3]) & 0xff)) % 1000000;
        if (str_pad($hash, 6, '0', STR_PAD_LEFT) == $code) return true;
    }
    return false;
}

function base32_decode($base32) {
    $base32chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";
    $base32charsFlipped = array_flip(str_split($base32chars));
    $output = ""; $buffer = 0; $bitsLeft = 0;
    $base32 = rtrim(strtoupper($base32), '=');
    for ($j = 0; $j < strlen($base32); $j++) {
        $ch = $base32[$j];
        if (!isset($base32charsFlipped[$ch])) continue;
        $buffer = ($buffer << 5) | $base32charsFlipped[$ch];
        $bitsLeft += 5;
        if ($bitsLeft >= 8) {
            $bitsLeft -= 8;
            $output .= chr(($buffer >> $bitsLeft) & 0xFF);
        }
    }
    return $output;
}

$action = $_GET['action'] ?? '';

if ($action == 'login') {
    $input = json_decode(file_get_contents('php://input'), true);
    $user = $conn->real_escape_string($input['username'] ?? '');
    $pass = $input['password'] ?? '';
    $otp  = $input['otp'] ?? '';
    $sql = "SELECT * FROM users WHERE username='$user'";
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_assoc()) {
        if (password_verify($pass, $row['password'])) {
            if (!empty($row['tfa_code'])) {
                if (empty($otp)) { echo json_encode(["status" => "need_otp"]); exit; }
                if (verify_totp($row['tfa_code'], $otp)) {
                    log_event($conn, $user, "Login Success", "2FA Verified");
                    echo json_encode(["status" => "success"]);
                } else {
                    log_event($conn, $user, "Login Failed", "Invalid 2FA Code");
                    echo json_encode(["status" => "error", "message" => "קוד אימות לא תקין"]);
                }
            } else {
                log_event($conn, $user, "Login Success", "No 2FA");
                echo json_encode(["status" => "success"]);
            }
        } else {
            log_event($conn, $user, "Login Failed", "Wrong Password");
            echo json_encode(["status" => "error", "message" => "סיסמה שגויה"]);
        }
    } else {
        log_event($conn, $user, "Login Failed", "User Not Found");
        echo json_encode(["status" => "error", "message" => "משתמש לא נמצא"]);
    }
    exit;
}

if ($action == 'save') {
    $json = file_get_contents('php://input');
    $target = $_GET['target'] ?? 'General Update';
    $user = $_GET['user'] ?? 'System'; // המשתמש שביצע את הפעולה
    
    if ($json && json_decode($json)) {
        $clean_json = $conn->real_escape_string($json);
        $conn->query("DELETE FROM customers"); 
        if ($conn->query("INSERT INTO customers (data) VALUES ('$clean_json')")) {
            log_event($conn, $user, "Modification", $target);
            echo json_encode(["status" => "success"]);
        }
    }
    exit;
}

if ($action == 'load') {
    $result = $conn->query("SELECT data FROM customers LIMIT 1");
    echo ($result && $row = $result->fetch_assoc()) ? $row['data'] : json_encode(new stdClass());
    exit;
}

if ($action == 'register') {
    $input = json_decode(file_get_contents('php://input'), true);
    $user = $conn->real_escape_string($input['username'] ?? '');
    $pass = password_hash($input['password'] ?? '', PASSWORD_DEFAULT);
    $tfa = $conn->real_escape_string($input['tfa'] ?? '');
    $check = $conn->query("SELECT id FROM users WHERE username='$user'");
    if ($check && $check->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "User exists"]);
    } else {
        if ($conn->query("INSERT INTO users (username, password, tfa_code) VALUES ('$user', '$pass', '$tfa')")) {
            log_event($conn, $user, "Account Created");
            echo json_encode(["status" => "success"]);
        }
    }
    exit;
}

if ($action == 'reset_account') {
    if (!file_exists('reset_lock.txt')) { echo json_encode(["status" => "error", "message" => "Need reset_lock.txt"]); exit; }
    $input = json_decode(file_get_contents('php://input'), true);
    $user = $conn->real_escape_string($input['username'] ?? '');
    $new_pass = password_hash($input['password'] ?? '', PASSWORD_DEFAULT);
    if ($conn->query("UPDATE users SET password='$new_pass', tfa_code='' WHERE username='$user'")) {
        log_event($conn, $user, "Emergency Reset");
        @unlink('reset_lock.txt');
        echo json_encode(["status" => "success"]);
    }
    exit;
}
?>