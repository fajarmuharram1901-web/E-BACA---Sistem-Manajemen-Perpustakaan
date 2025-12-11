<?php
/**
 * config.php - Konfigurasi Database dan Konstanta Aplikasi
 * Error Handling dan Debugging Configuration
 */

// Error Reporting untuk Development (matikan di production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

// Pastikan folder logs ada
if (!file_exists(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

// Konstanta Database
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'perpustakaan_db');
define('DB_CHARSET', 'utf8mb4');

// Konstanta Aplikasi
define('APP_NAME', 'E-BACA Perpustakaan');
define('APP_VERSION', '1.0.0');
define('DENDA_PER_HARI', 5000); // Denda keterlambatan per hari
define('MAX_DURASI_PINJAM', 30); // Maksimal hari peminjaman

// Timezone
date_default_timezone_set('Asia/Jakarta');

/**
 * Class Database Connection dengan Error Handling
 */
class Database {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $this->conn = new PDO($dsn, DB_USER, DB_PASS, $options);
            $this->createTables();
            
        } catch(PDOException $e) {
            $this->logError("Database Connection Error: " . $e->getMessage());
            die(json_encode([
                'success' => false,
                'error' => 'Koneksi database gagal. Silakan coba lagi.'
            ]));
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    /**
     * Create Tables jika belum ada
     */
    private function createTables() {
        try {
            // Tabel Buku
            $this->conn->exec("CREATE TABLE IF NOT EXISTS buku (
                id VARCHAR(50) PRIMARY KEY,
                judul VARCHAR(255) NOT NULL,
                pengarang VARCHAR(255) NOT NULL,
                kategori VARCHAR(100) NOT NULL,
                isbn VARCHAR(50),
                tahun VARCHAR(10),
                status ENUM('Tersedia', 'Dipinjam') DEFAULT 'Tersedia',
                kondisi TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_status (status),
                INDEX idx_judul (judul)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
            // Tabel Anggota
            $this->conn->exec("CREATE TABLE IF NOT EXISTS anggota (
                id VARCHAR(50) PRIMARY KEY,
                nama VARCHAR(255) NOT NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                telp VARCHAR(20) NOT NULL,
                alamat TEXT,
                jenis ENUM('Mahasiswa', 'Dosen', 'Umum') NOT NULL,
                tanggal_daftar DATE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_email (email),
                INDEX idx_nama (nama)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
            // Tabel Peminjaman
            $this->conn->exec("CREATE TABLE IF NOT EXISTS peminjaman (
                id VARCHAR(50) PRIMARY KEY,
                anggota_id VARCHAR(50) NOT NULL,
                buku_id VARCHAR(50) NOT NULL,
                tgl_pinjam DATE NOT NULL,
                tgl_jatuh_tempo DATE NOT NULL,
                durasi INT NOT NULL,
                status ENUM('Aktif', 'Selesai') DEFAULT 'Aktif',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (anggota_id) REFERENCES anggota(id) ON DELETE CASCADE,
                FOREIGN KEY (buku_id) REFERENCES buku(id) ON DELETE CASCADE,
                INDEX idx_status (status),
                INDEX idx_anggota (anggota_id),
                INDEX idx_buku (buku_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
            // Tabel Pengembalian
            $this->conn->exec("CREATE TABLE IF NOT EXISTS pengembalian (
                id VARCHAR(50) PRIMARY KEY,
                peminjaman_id VARCHAR(50) NOT NULL,
                tgl_kembali DATE NOT NULL,
                keterlambatan INT DEFAULT 0,
                denda DECIMAL(10,2) DEFAULT 0,
                kondisi ENUM('Baik', 'Rusak') NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (peminjaman_id) REFERENCES peminjaman(id) ON DELETE CASCADE,
                INDEX idx_peminjaman (peminjaman_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
            // Tabel Log Aktivitas (untuk debugging)
            $this->conn->exec("CREATE TABLE IF NOT EXISTS log_aktivitas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_action VARCHAR(100) NOT NULL,
                description TEXT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_action (user_action),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
        } catch(PDOException $e) {
            $this->logError("Create Tables Error: " . $e->getMessage());
        }
    }
    
    /**
     * Log Error ke File
     */
    public function logError($message) {
        $logFile = __DIR__ . '/logs/error.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message" . PHP_EOL;
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
    
    /**
     * Log Aktivitas User
     */
    public function logActivity($action, $description = '') {
        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO log_aktivitas (user_action, description, ip_address, user_agent) 
                VALUES (?, ?, ?, ?)"
            );
            
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            
            $stmt->execute([$action, $description, $ip, $userAgent]);
        } catch(PDOException $e) {
            $this->logError("Log Activity Error: " . $e->getMessage());
        }
    }
}

/**
 * Helper Functions untuk Response JSON
 */
function sendResponse($success, $data = null, $message = '') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function sendError($message, $code = 400) {
    http_response_code($code);
    sendResponse(false, null, $message);
}

/**
 * Sanitize dan Validasi Input
 */
function sanitizeString($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return strlen($phone) >= 10 && strlen($phone) <= 15;
}

function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Generate ID Unik
 */
function generateId($prefix = '') {
    return $prefix . time() . rand(100, 999);
}

/**
 * Capitalize Words (Title Case)
 */
function capitalizeWords($string) {
    return mb_convert_case($string, MB_CASE_TITLE, 'UTF-8');
}

/**
 * Format Rupiah
 */
function formatRupiah($number) {
    return 'Rp ' . number_format($number, 0, ',', '.');
}

/**
 * Hitung Selisih Hari
 */
function hitungSelisihHari($tanggal1, $tanggal2) {
    $date1 = new DateTime($tanggal1);
    $date2 = new DateTime($tanggal2);
    $diff = $date1->diff($date2);
    return $diff->days;
}
?>