<?php
/**
 * buku.php - API Endpoint untuk Manajemen Buku
 * CRUD Operations untuk Data Buku
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
        case 'PUT':
            handlePut($conn, $db);
            break;
        case 'DELETE':
            handleDelete($conn, $db);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan']);
    }
} catch(Exception $e) {
    $db->logError("Buku API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan server']);
}

/**
 * GET - Ambil data buku
 */
function handleGet($conn, $db) {
    try {
        $id = isset($_GET['id']) ? sanitizeString($_GET['id']) : null;
        $search = isset($_GET['search']) ? sanitizeString($_GET['search']) : null;
        
        if ($id) {
            // Get buku by ID
            $stmt = $conn->prepare("SELECT * FROM buku WHERE id = ?");
            $stmt->execute([$id]);
            $buku = $stmt->fetch();
            
            if ($buku) {
                http_response_code(200);
                echo json_encode(['success' => true, 'data' => $buku, 'message' => 'Buku ditemukan']);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Buku tidak ditemukan']);
            }
        } else {
            // Get all buku dengan search
            $sql = "SELECT * FROM buku WHERE 1=1";
            $params = [];
            
            if ($search) {
                $sql .= " AND (judul LIKE ? OR pengarang LIKE ? OR isbn LIKE ?)";
                $searchParam = "%$search%";
                $params[] = $searchParam;
                $params[] = $searchParam;
                $params[] = $searchParam;
            }
            
            $sql .= " ORDER BY created_at DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $buku = $stmt->fetchAll();
            
            $db->logActivity('GET_ALL_BUKU', "Mengambil " . count($buku) . " buku");
            http_response_code(200);
            echo json_encode(['success' => true, 'data' => $buku, 'message' => 'Data buku berhasil diambil']);
        }
        
    } catch(PDOException $e) {
        $db->logError("Get Buku Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal mengambil data buku']);
    }
}

/**
 * POST - Tambah buku baru
 */
function handlePost($conn, $db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Data tidak valid']);
            return;
        }
        
        // Validasi Required Fields
        $requiredFields = ['judul', 'pengarang'];
        foreach ($requiredFields as $field) {
            if (empty($input[$field])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "Field '$field' wajib diisi"]);
                return;
            }
        }
        
        // Sanitize Input
        $id = generateId('BK');
        $judul = sanitizeString($input['judul']);
        $pengarang = sanitizeString($input['pengarang']);
        $penerbit = isset($input['penerbit']) ? sanitizeString($input['penerbit']) : null;
        $tahun_terbit = isset($input['tahun_terbit']) ? (int)$input['tahun_terbit'] : null;
        $isbn = isset($input['isbn']) ? sanitizeString($input['isbn']) : null;
        $kategori = isset($input['kategori']) ? sanitizeString($input['kategori']) : 'Umum';
        $stok = isset($input['stok']) ? (int)$input['stok'] : 0;
        $lokasi = isset($input['lokasi']) ? sanitizeString($input['lokasi']) : 'Rak Umum';
        
        // Validasi
        if (strlen($judul) < 3) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Judul minimal 3 karakter']);
            return;
        }
        
        if ($stok < 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Stok tidak boleh negatif']);
            return;
        }
        
        // Check ISBN unique
        if ($isbn) {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM buku WHERE isbn = ?");
            $stmt->execute([$isbn]);
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'ISBN sudah terdaftar']);
                return;
            }
        }
        
        // Insert buku
        $stmt = $conn->prepare(
            "INSERT INTO buku (id, judul, pengarang, penerbit, tahun_terbit, isbn, kategori, stok, stok_tersedia, lokasi) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        
        $stmt->execute([
            $id, $judul, $pengarang, $penerbit, $tahun_terbit, $isbn, $kategori, $stok, $stok, $lokasi
        ]);
        
        // Log aktivitas
        $db->logActivity('ADD_BUKU', "Menambah buku: $judul ($pengarang)");
        
        http_response_code(201);
        echo json_encode(['success' => true, 'data' => ['id' => $id], 'message' => 'Buku berhasil ditambahkan']);
        
    } catch(PDOException $e) {
        $db->logError("Add Buku Error: " . $e->getMessage());
        
        if ($e->getCode() == 23000) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Data buku sudah ada']);
            return;
        }
        
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menambah buku']);
    }
}

/**
 * PUT - Update buku
 */
function handlePut($conn, $db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID buku tidak ditemukan']);
            return;
        }
        
        $id = sanitizeString($input['id']);
        
        // Build dynamic UPDATE query
        $updates = [];
        $values = [];
        
        $allowedFields = ['judul', 'pengarang', 'penerbit', 'tahun_terbit', 'isbn', 'kategori', 'stok', 'stok_tersedia', 'lokasi', 'status'];
        
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $updates[] = "$field = ?";
                $values[] = $field === 'stok' || $field === 'stok_tersedia' || $field === 'tahun_terbit' 
                    ? (int)$input[$field] 
                    : sanitizeString($input[$field]);
            }
        }
        
        if (empty($updates)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Tidak ada field yang diupdate']);
            return;
        }
        
        $values[] = $id;
        
        $sql = "UPDATE buku SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute($values);
        
        if ($stmt->rowCount() > 0) {
            $db->logActivity('UPDATE_BUKU', "Update buku: $id");
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Buku berhasil diupdate']);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Buku tidak ditemukan']);
        }
        
    } catch(PDOException $e) {
        $db->logError("Update Buku Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal mengupdate buku']);
    }
}

/**
 * DELETE - Hapus buku
 */
function handleDelete($conn, $db) {
    try {
        $id = isset($_GET['id']) ? sanitizeString($_GET['id']) : null;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID buku tidak ditemukan']);
            return;
        }
        
        // Check if buku memiliki peminjaman aktif
        $stmt = $conn->prepare(
            "SELECT COUNT(*) as count FROM peminjaman 
            WHERE buku_id = ? AND status = 'Aktif'"
        );
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Buku sedang dipinjam, tidak dapat dihapus']);
            return;
        }
        
        // Get buku data for logging
        $stmt = $conn->prepare("SELECT judul, pengarang FROM buku WHERE id = ?");
        $stmt->execute([$id]);
        $buku = $stmt->fetch();
        
        if (!$buku) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Buku tidak ditemukan']);
            return;
        }
        
        // Delete buku
        $stmt = $conn->prepare("DELETE FROM buku WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            $db->logActivity('DELETE_BUKU', "Menghapus buku: {$buku['judul']} ({$buku['pengarang']})");
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Buku berhasil dihapus']);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Buku tidak ditemukan']);
        }
        
    } catch(PDOException $e) {
        $db->logError("Delete Buku Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus buku']);
    }
}

?>
