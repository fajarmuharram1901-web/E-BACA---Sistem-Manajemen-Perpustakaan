<?php
/**
 * anggota.php - API Endpoint untuk Manajemen Anggota
 * Server-Side Validation dengan PHP filter_var()
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$db = Database::getInstance();
$conn = $db->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch($method) {
        case 'GET':
            handleGet($conn, $db);
            break;
        case 'POST':
            handlePost($conn, $db);
            break;
        case 'DELETE':
            handleDelete($conn, $db);
            break;
        default:
            sendError('Method tidak diizinkan', 405);
    }
} catch(Exception $e) {
    $db->logError("Anggota API Error: " . $e->getMessage());
    sendError('Terjadi kesalahan server: ' . $e->getMessage(), 500);
}

/**
 * GET - Ambil data anggota
 */
function handleGet($conn, $db) {
    try {
        $id = isset($_GET['id']) ? sanitizeString($_GET['id']) : null;
        $search = isset($_GET['search']) ? sanitizeString($_GET['search']) : null;
        
        if ($id) {
            $stmt = $conn->prepare("SELECT * FROM anggota WHERE id = ?");
            $stmt->execute([$id]);
            $anggota = $stmt->fetch();
            
            if ($anggota) {
                sendResponse(true, $anggota, 'Anggota ditemukan');
            } else {
                sendError('Anggota tidak ditemukan', 404);
            }
        } else {
            $sql = "SELECT * FROM anggota WHERE 1=1";
            $params = [];
            
            if ($search) {
                $sql .= " AND (nama LIKE ? OR email LIKE ?)";
                $searchParam = "%$search%";
                $params[] = $searchParam;
                $params[] = $searchParam;
            }
            
            $sql .= " ORDER BY created_at DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $anggota = $stmt->fetchAll();
            
            $db->logActivity('GET_ALL_ANGGOTA', "Mengambil " . count($anggota) . " anggota");
            sendResponse(true, $anggota, 'Data anggota berhasil diambil');
        }
        
    } catch(PDOException $e) {
        $db->logError("Get Anggota Error: " . $e->getMessage());
        sendError('Gagal mengambil data anggota', 500);
    }
}

/**
 * POST - Registrasi anggota baru dengan validasi komprehensif
 */
function handlePost($conn, $db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            sendError('Data tidak valid', 400);
        }
        
        // Validasi Required Fields
        $requiredFields = ['nama', 'email', 'telp', 'jenis'];
        foreach ($requiredFields as $field) {
            if (empty($input[$field])) {
                sendError("Field '$field' wajib diisi", 400);
            }
        }
        
        // Sanitize Input
        $id = generateId('AG');
        $nama = sanitizeString($input['nama']);
        $email = strtolower(sanitizeString($input['email']));
        $telp = sanitizeString($input['telp']);
        $alamat = isset($input['alamat']) ? sanitizeString($input['alamat']) : '-';
        $jenis = sanitizeString($input['jenis']);
        $tanggalDaftar = date('Y-m-d');
        
        // ===== VALIDASI SERVER-SIDE LENGKAP =====
        
        // 1. Validasi Nama (minimal 3 karakter, maksimal 255, hanya huruf dan spasi)
        if (strlen($nama) < 3) {
            sendError('Nama minimal 3 karakter', 400);
        }
        
        if (strlen($nama) > 255) {
            sendError('Nama maksimal 255 karakter', 400);
        }
        
        // Validasi karakter nama (hanya huruf, spasi, titik, koma)
        if (!preg_match("/^[a-zA-Z\s.,'-]+$/u", $nama)) {
            sendError('Nama hanya boleh mengandung huruf, spasi, dan tanda baca', 400);
        }
        
        // 2. Validasi Email dengan filter_var()
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendError('Format email tidak valid', 400);
        }
        
        // Validasi panjang email
        if (strlen($email) > 255) {
            sendError('Email terlalu panjang (maksimal 255 karakter)', 400);
        }
        
        // Validasi domain email (optional, bisa dikustomisasi)
        $emailParts = explode('@', $email);
        if (count($emailParts) !== 2) {
            sendError('Format email tidak valid', 400);
        }
        
        // Check email sudah terdaftar
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM anggota WHERE email = ?");
        $stmt->execute([$email]);
        $emailExists = $stmt->fetch();
        
        if ($emailExists['count'] > 0) {
            sendError('Email sudah terdaftar', 409);
        }
        
        // 3. Validasi Telepon dengan berbagai metode
        
        // Remove semua karakter non-digit
        $telpClean = preg_replace('/[^0-9]/', '', $telp);
        
        // Validasi panjang nomor telepon Indonesia (10-15 digit)
        if (strlen($telpClean) < 10 || strlen($telpClean) > 15) {
            sendError('Nomor telepon harus 10-15 digit', 400);
        }
        
        // Validasi awalan nomor Indonesia (08, +62, 62)
        if (!preg_match('/^(08|628|62|8)/', $telpClean)) {
            sendError('Format nomor telepon Indonesia tidak valid (harus diawali 08)', 400);
        }
        
        // Simpan nomor yang sudah dibersihkan
        $telp = $telpClean;
        
        // 4. Validasi Jenis Anggota (whitelist validation)
        $validJenis = ['Mahasiswa', 'Dosen', 'Umum'];
        if (!in_array($jenis, $validJenis)) {
            sendError('Jenis anggota tidak valid (pilih: Mahasiswa, Dosen, atau Umum)', 400);
        }
        
        // 5. Validasi Alamat (jika diisi)
        if ($alamat !== '-') {
            if (strlen($alamat) > 500) {
                sendError('Alamat maksimal 500 karakter', 400);
            }
            
            // Filter XSS di alamat
            $alamat = strip_tags($alamat);
        }
        
        // Capitalize nama untuk konsistensi
        $nama = capitalizeWords($nama);
        
        // ===== INSERT KE DATABASE =====
        $stmt = $conn->prepare(
            "INSERT INTO anggota (id, nama, email, telp, alamat, jenis, tanggal_daftar) 
            VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        
        $stmt->execute([$id, $nama, $email, $telp, $alamat, $jenis, $tanggalDaftar]);
        
        // Log aktivitas
        $db->logActivity('ADD_ANGGOTA', "Mendaftar anggota: $nama ($email)");
        
        // Backup ke file
        backupAnggotaToFile([
            'id' => $id,
            'nama' => $nama,
            'email' => $email,
            'action' => 'REGISTER'
        ]);
        
        sendResponse(true, ['id' => $id], 'Anggota berhasil terdaftar');
        
    } catch(PDOException $e) {
        $db->logError("Add Anggota Error: " . $e->getMessage());
        
        if ($e->getCode() == 23000) {
            sendError('Email sudah terdaftar', 409);
        }
        
        sendError('Gagal mendaftarkan anggota', 500);
    }
}

/**
 * DELETE - Hapus anggota
 */
function handleDelete($conn, $db) {
    try {
        $id = isset($_GET['id']) ? sanitizeString($_GET['id']) : null;
        
        if (!$id) {
            sendError('ID anggota tidak ditemukan', 400);
        }
        
        // Check if anggota memiliki peminjaman aktif
        $stmt = $conn->prepare(
            "SELECT COUNT(*) as count FROM peminjaman 
            WHERE anggota_id = ? AND status = 'Aktif'"
        );
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            sendError('Anggota memiliki peminjaman aktif, tidak dapat dihapus', 400);
        }
        
        // Get anggota data for logging
        $stmt = $conn->prepare("SELECT nama, email FROM anggota WHERE id = ?");
        $stmt->execute([$id]);
        $anggota = $stmt->fetch();
        
        if (!$anggota) {
            sendError('Anggota tidak ditemukan', 404);
        }
        
        // Delete anggota
        $stmt = $conn->prepare("DELETE FROM anggota WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            $db->logActivity('DELETE_ANGGOTA', "Menghapus anggota: {$anggota['nama']} ({$anggota['email']})");
            sendResponse(true, null, 'Anggota berhasil dihapus');
        } else {
            sendError('Anggota tidak ditemukan', 404);
        }
        
    } catch(PDOException $e) {
        $db->logError("Delete Anggota Error: " . $e->getMessage());
        sendError('Gagal menghapus anggota', 500);
    }
}

/**
 * Backup data anggota ke file untuk audit trail
 */
function backupAnggotaToFile($data) {
    $backupDir = __DIR__ . '/backups';
    
    if (!file_exists($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    $filename = $backupDir . '/anggota_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "$timestamp | " . json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    
    // Write dengan file locking untuk mencegah race condition
    $fp = fopen($filename, 'a');
    if (flock($fp, LOCK_EX)) {
        fwrite($fp, $logEntry);
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}
?>