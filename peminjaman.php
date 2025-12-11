<?php
/**
 * peminjaman.php - API Peminjaman & Pengembalian
 * Logika Bisnis Aplikasi yang Kompleks
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT');
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
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    
    switch($action) {
        case 'pinjam':
            handlePinjam($conn, $db);
            break;
        case 'kembali':
            handleKembali($conn, $db);
            break;
        case 'list':
            handleList($conn, $db);
            break;
        case 'riwayat':
            handleRiwayat($conn, $db);
            break;
        case 'stats':
            handleStats($conn, $db);
            break;
        default:
            sendError('Action tidak valid', 400);
    }
} catch(Exception $e) {
    $db->logError("Peminjaman API Error: " . $e->getMessage());
    sendError('Terjadi kesalahan server: ' . $e->getMessage(), 500);
}

/**
 * PROSES PEMINJAMAN - Logika Bisnis Kompleks
 */
function handlePinjam($conn, $db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            sendError('Data tidak valid', 400);
        }
        
        // Validasi Required Fields
        $requiredFields = ['anggotaId', 'bukuId', 'tglPinjam', 'durasi'];
        foreach ($requiredFields as $field) {
            if (!isset($input[$field]) || $input[$field] === '') {
                sendError("Field '$field' wajib diisi", 400);
            }
        }
        
        // Sanitize Input
        $anggotaId = sanitizeString($input['anggotaId']);
        $bukuId = sanitizeString($input['bukuId']);
        $tglPinjam = sanitizeString($input['tglPinjam']);
        $durasi = (int)$input['durasi'];
        
        // ===== VALIDASI BISNIS LOGIC =====
        
        // 1. Validasi Tanggal
        if (!validateDate($tglPinjam)) {
            sendError('Format tanggal tidak valid', 400);
        }
        
        // Tanggal pinjam tidak boleh di masa lalu
        $today = date('Y-m-d');
        if ($tglPinjam < $today) {
            sendError('Tanggal pinjam tidak boleh di masa lalu', 400);
        }
        
        // 2. Validasi Durasi
        if ($durasi < 1) {
            sendError('Durasi peminjaman minimal 1 hari', 400);
        }
        
        if ($durasi > MAX_DURASI_PINJAM) {
            sendError('Durasi peminjaman maksimal ' . MAX_DURASI_PINJAM . ' hari', 400);
        }
        
        // 3. Check Anggota Exists
        $stmt = $conn->prepare("SELECT * FROM anggota WHERE id = ?");
        $stmt->execute([$anggotaId]);
        $anggota = $stmt->fetch();
        
        if (!$anggota) {
            sendError('Anggota tidak ditemukan', 404);
        }
        
        // 4. Check Buku Exists dan Status
        $stmt = $conn->prepare("SELECT * FROM buku WHERE id = ?");
        $stmt->execute([$bukuId]);
        $buku = $stmt->fetch();
        
        if (!$buku) {
            sendError('Buku tidak ditemukan', 404);
        }
        
        if ($buku['status'] !== 'Tersedia') {
            sendError('Buku sedang dipinjam', 400);
        }
        
        // 5. Check Limit Peminjaman per Anggota (Business Rule)
        $maxPinjam = ($anggota['jenis'] === 'Mahasiswa') ? 3 : 
                     (($anggota['jenis'] === 'Dosen') ? 5 : 2);
        
        $stmt = $conn->prepare(
            "SELECT COUNT(*) as count FROM peminjaman 
            WHERE anggota_id = ? AND status = 'Aktif'"
        );
        $stmt->execute([$anggotaId]);
        $aktivePinjam = $stmt->fetch();
        
        if ($aktivePinjam['count'] >= $maxPinjam) {
            sendError("Anggota {$anggota['jenis']} maksimal meminjam $maxPinjam buku secara bersamaan", 400);
        }
        
        // 6. Check Tunggakan Denda (Business Rule)
        $stmt = $conn->prepare(
            "SELECT SUM(pg.denda) as total_denda 
            FROM pengembalian pg
            JOIN peminjaman pm ON pg.peminjaman_id = pm.id
            WHERE pm.anggota_id = ? AND pg.denda > 0
            AND pg.id NOT IN (
                SELECT pengembalian_id FROM pembayaran_denda WHERE status = 'Lunas'
            )"
        );
        $stmt->execute([$anggotaId]);
        $tunggakan = $stmt->fetch();
        
        if ($tunggakan && $tunggakan['total_denda'] > 0) {
            sendError('Anggota memiliki tunggakan denda Rp ' . 
                      number_format($tunggakan['total_denda'], 0, ',', '.') . 
                      '. Harap lunasi terlebih dahulu.', 400);
        }
        
        // ===== PROSES PEMINJAMAN =====
        
        // Hitung tanggal jatuh tempo
        $tglJatuhTempo = date('Y-m-d', strtotime($tglPinjam . " + $durasi days"));
        
        // Generate ID
        $peminjamanId = generateId('PM');
        
        // Begin Transaction (untuk memastikan data consistency)
        $conn->beginTransaction();
        
        try {
            // Insert Peminjaman
            $stmt = $conn->prepare(
                "INSERT INTO peminjaman 
                (id, anggota_id, buku_id, tgl_pinjam, tgl_jatuh_tempo, durasi, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'Aktif')"
            );
            
            $stmt->execute([
                $peminjamanId, 
                $anggotaId, 
                $bukuId, 
                $tglPinjam, 
                $tglJatuhTempo, 
                $durasi
            ]);
            
            // Update Status Buku
            $stmt = $conn->prepare("UPDATE buku SET status = 'Dipinjam' WHERE id = ?");
            $stmt->execute([$bukuId]);
            
            // Commit Transaction
            $conn->commit();
            
            // Log Aktivitas
            $db->logActivity('PEMINJAMAN', 
                "Anggota: {$anggota['nama']} meminjam buku: {$buku['judul']}");
            
            // Backup ke File
            backupPeminjamanToFile([
                'id' => $peminjamanId,
                'anggota' => $anggota['nama'],
                'buku' => $buku['judul'],
                'tgl_pinjam' => $tglPinjam,
                'tgl_jatuh_tempo' => $tglJatuhTempo,
                'action' => 'PINJAM'
            ]);
            
            // Response dengan data lengkap
            sendResponse(true, [
                'id' => $peminjamanId,
                'anggotaNama' => $anggota['nama'],
                'bukuJudul' => $buku['judul'],
                'tglJatuhTempo' => $tglJatuhTempo
            ], 'Peminjaman berhasil diproses');
            
        } catch(PDOException $e) {
            $conn->rollBack();
            throw $e;
        }
        
    } catch(PDOException $e) {
        $db->logError("Proses Peminjaman Error: " . $e->getMessage());
        sendError('Gagal memproses peminjaman', 500);
    }
}

/**
 * PROSES PENGEMBALIAN - Dengan Perhitungan Denda
 */
function handleKembali($conn, $db) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            sendError('Data tidak valid', 400);
        }
        
        // Validasi Required Fields
        $requiredFields = ['peminjamanId', 'tglKembali', 'kondisi'];
        foreach ($requiredFields as $field) {
            if (!isset($input[$field]) || $input[$field] === '') {
                sendError("Field '$field' wajib diisi", 400);
            }
        }
        
        // Sanitize Input
        $peminjamanId = sanitizeString($input['peminjamanId']);
        $tglKembali = sanitizeString($input['tglKembali']);
        $kondisi = sanitizeString($input['kondisi']);
        
        // Validasi
        if (!validateDate($tglKembali)) {
            sendError('Format tanggal tidak valid', 400);
        }
        
        if (!in_array($kondisi, ['Baik', 'Rusak'])) {
            sendError('Kondisi buku tidak valid', 400);
        }
        
        // Get data peminjaman
        $stmt = $conn->prepare(
            "SELECT pm.*, a.nama as anggota_nama, b.judul as buku_judul, b.id as buku_id
            FROM peminjaman pm
            JOIN anggota a ON pm.anggota_id = a.id
            JOIN buku b ON pm.buku_id = b.id
            WHERE pm.id = ?"
        );
        $stmt->execute([$peminjamanId]);
        $peminjaman = $stmt->fetch();
        
        if (!$peminjaman) {
            sendError('Data peminjaman tidak ditemukan', 404);
        }
        
        if ($peminjaman['status'] !== 'Aktif') {
            sendError('Peminjaman sudah selesai', 400);
        }
        
        // ===== LOGIKA PERHITUNGAN DENDA =====
        
        $tglJatuhTempo = new DateTime($peminjaman['tgl_jatuh_tempo']);
        $tglKembaliDate = new DateTime($tglKembali);
        
        // Hitung keterlambatan
        $keterlambatan = 0;
        $denda = 0;
        
        if ($tglKembaliDate > $tglJatuhTempo) {
            $interval = $tglJatuhTempo->diff($tglKembaliDate);
            $keterlambatan = $interval->days;
            $denda = $keterlambatan * DENDA_PER_HARI;
            
            // Denda tambahan jika buku rusak
            if ($kondisi === 'Rusak') {
                $dendaRusak = 50000; // Denda buku rusak
                $denda += $dendaRusak;
            }
        } else if ($kondisi === 'Rusak') {
            // Jika tidak terlambat tapi buku rusak
            $denda = 50000;
        }
        
        // Generate ID Pengembalian
        $pengembalianId = generateId('PG');
        
        // Begin Transaction
        $conn->beginTransaction();
        
        try {
            // Insert Pengembalian
            $stmt = $conn->prepare(
                "INSERT INTO pengembalian 
                (id, peminjaman_id, tgl_kembali, keterlambatan, denda, kondisi) 
                VALUES (?, ?, ?, ?, ?, ?)"
            );
            
            $stmt->execute([
                $pengembalianId,
                $peminjamanId,
                $tglKembali,
                $keterlambatan,
                $denda,
                $kondisi
            ]);
            
            // Update Status Peminjaman
            $stmt = $conn->prepare("UPDATE peminjaman SET status = 'Selesai' WHERE id = ?");
            $stmt->execute([$peminjamanId]);
            
            // Update Status Buku
            $stmt = $conn->prepare("UPDATE buku SET status = 'Tersedia' WHERE id = ?");
            $stmt->execute([$peminjaman['buku_id']]);
            
            // Commit Transaction
            $conn->commit();
            
            // Log Aktivitas
            $logMessage = "Pengembalian: {$peminjaman['anggota_nama']} - {$peminjaman['buku_judul']}";
            if ($keterlambatan > 0) {
                $logMessage .= " (Terlambat $keterlambatan hari, Denda: Rp " . 
                              number_format($denda, 0, ',', '.') . ")";
            }
            $db->logActivity('PENGEMBALIAN', $logMessage);
            
            // Backup ke File
            backupPengembalianToFile([
                'id' => $pengembalianId,
                'peminjaman_id' => $peminjamanId,
                'anggota' => $peminjaman['anggota_nama'],
                'buku' => $peminjaman['buku_judul'],
                'tgl_kembali' => $tglKembali,
                'keterlambatan' => $keterlambatan,
                'denda' => $denda,
                'kondisi' => $kondisi,
                'action' => 'KEMBALI'
            ]);
            
            // Response
            $message = 'Pengembalian berhasil diproses';
            if ($denda > 0) {
                $message .= '. Denda: Rp ' . number_format($denda, 0, ',', '.');
            }
            
            sendResponse(true, [
                'id' => $pengembalianId,
                'keterlambatan' => $keterlambatan,
                'denda' => $denda,
                'dendaFormatted' => formatRupiah($denda)
            ], $message);
            
        } catch(PDOException $e) {
            $conn->rollBack();
            throw $e;
        }
        
    } catch(PDOException $e) {
        $db->logError("Proses Pengembalian Error: " . $e->getMessage());
        sendError('Gagal memproses pengembalian', 500);
    }
}

/**
 * GET - Daftar Peminjaman Aktif
 */
function handleList($conn, $db) {
    try {
        $stmt = $conn->prepare(
            "SELECT pm.*, a.nama as anggota_nama, b.judul as buku_judul
            FROM peminjaman pm
            JOIN anggota a ON pm.anggota_id = a.id
            JOIN buku b ON pm.buku_id = b.id
            WHERE pm.status = 'Aktif'
            ORDER BY pm.tgl_jatuh_tempo ASC"
        );
        
        $stmt->execute();
        $peminjaman = $stmt->fetchAll();
        
        // Tambahkan info keterlambatan
        $today = new DateTime();
        foreach ($peminjaman as &$p) {
            $jatuhTempo = new DateTime($p['tgl_jatuh_tempo']);
            if ($today > $jatuhTempo) {
                $diff = $jatuhTempo->diff($today);
                $p['terlambat'] = true;
                $p['hari_terlambat'] = $diff->days;
                $p['status_display'] = 'Terlambat';
            } else {
                $p['terlambat'] = false;
                $p['hari_terlambat'] = 0;
                $p['status_display'] = 'Aktif';
            }
        }
        
        sendResponse(true, $peminjaman, 'Data peminjaman berhasil diambil');
        
    } catch(PDOException $e) {
        $db->logError("Get Peminjaman List Error: " . $e->getMessage());
        sendError('Gagal mengambil data peminjaman', 500);
    }
}

/**
 * GET - Riwayat Pengembalian
 */
function handleRiwayat($conn, $db) {
    try {
        $stmt = $conn->prepare(
            "SELECT pg.*, pm.anggota_id, 
                    a.nama as anggota_nama, 
                    b.judul as buku_judul
            FROM pengembalian pg
            JOIN peminjaman pm ON pg.peminjaman_id = pm.id
            JOIN anggota a ON pm.anggota_id = a.id
            JOIN buku b ON pm.buku_id = b.id
            ORDER BY pg.created_at DESC
            LIMIT 100"
        );
        
        $stmt->execute();
        $pengembalian = $stmt->fetchAll();
        
        sendResponse(true, $pengembalian, 'Riwayat pengembalian berhasil diambil');
        
    } catch(PDOException $e) {
        $db->logError("Get Riwayat Error: " . $e->getMessage());
        sendError('Gagal mengambil riwayat', 500);
    }
}

/**
 * GET - Statistik Dashboard
 */
function handleStats($conn, $db) {
    try {
        $stats = [];
        
        // Total Buku
        $stmt = $conn->query("SELECT COUNT(*) as total FROM buku");
        $stats['totalBuku'] = $stmt->fetch()['total'];
        
        // Total Anggota
        $stmt = $conn->query("SELECT COUNT(*) as total FROM anggota");
        $stats['totalAnggota'] = $stmt->fetch()['total'];
        
        // Buku Dipinjam
        $stmt = $conn->query("SELECT COUNT(*) as total FROM peminjaman WHERE status = 'Aktif'");
        $stats['bukuDipinjam'] = $stmt->fetch()['total'];
        
        // Total Denda
        $stmt = $conn->query("SELECT SUM(denda) as total FROM peminjaman WHERE denda > 0");
        $result = $stmt->fetch();
        $stats['totalDenda'] = $result['total'] ?? 0;
        
        // Peminjaman Terlambat
        $stmt = $conn->prepare(
            "SELECT COUNT(*) as total FROM peminjaman 
            WHERE status = 'Aktif' AND tanggal_kembali_rencana < CURDATE()"
        );
        $stmt->execute();
        $stats['peminjamanTerlambat'] = $stmt->fetch()['total'];
        
        sendResponse(true, $stats, 'Statistik berhasil diambil');
        
    } catch(PDOException $e) {
        $db->logError("Get Stats Error: " . $e->getMessage());
        sendError('Gagal mengambil statistik', 500);
    }
}

/**
 * File Handling - Backup Peminjaman
 */
function backupPeminjamanToFile($data) {
    $dir = __DIR__ . '/backups/peminjaman';
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
    
    $file = $dir . '/' . date('Y-m') . '.log';
    $entry = date('Y-m-d H:i:s') . ' | ' . json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    
    file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);
}

/**
 * File Handling - Backup Pengembalian
 */
function backupPengembalianToFile($data) {
    $dir = __DIR__ . '/backups/pengembalian';
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
    
    $file = $dir . '/' . date('Y-m') . '.log';
    $entry = date('Y-m-d H:i:s') . ' | ' . json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    
    file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);
}
?>