# E-BACA Perpustakaan - Sistem Manajemen Perpustakaan

Aplikasi web untuk manajemen data perpustakaan dengan fitur input anggota, manajemen buku, dan peminjaman.

## 🚀 Fitur Utama

- ✅ Manajemen Anggota (CRUD)
- ✅ Manajemen Buku (CRUD)
- ✅ Sistem Peminjaman & Pengembalian
- ✅ Dashboard dengan Statistik
- ✅ Validasi Form (Client & Server-side)
- ✅ Error Logging & Activity Tracking
- ✅ File Handling & Backup
- ✅ CORS Support untuk API

## 📋 Persyaratan

- PHP 7.4+
- MySQL 5.7+
- Apache Web Server
- Laragon (untuk development)

## 🔧 Instalasi

### 1. Setup Database

```bash
mysql -u root perpustakaan_db < database.sql
```

Atau manual:
```sql
CREATE DATABASE perpustakaan_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. Configure .htaccess (PENTING!)

Jika Anda menggunakan **Apache Server** (production), rename file:

```bash
mv htaccess.example .htaccess
```

**Catatan:** 
- Untuk **Laragon (development)**, `.htaccess` opsional
- Untuk **Production Server**, `.htaccess` WAJIB ada untuk CORS headers

### 3. Buat Folder Logs

```bash
mkdir logs
chmod 755 logs
```

### 4. Akses Aplikasi

Buka browser dan akses:
```
http://localhost/revisilagi/frontend_integrated.html
```

## 📁 Struktur Folder

```
revisilagi/
├── config.php                    # Database & Helper Functions
├── anggota.php                   # API Anggota (CRUD)
├── buku.php                      # API Buku (CRUD)
├── peminjaman.php                # API Peminjaman & Pengembalian
├── frontend_integrated.html       # UI Aplikasi
├── app_js.js                     # Frontend Logic
├── style.css                     # Styling
├── htaccess.example              # Server Config (rename ke .htaccess untuk production)
├── logs/                         # Error & Activity Logs
└── README.md                     # File ini
```

## 🔌 API Endpoints

### Anggota
- `GET /anggota.php` - Ambil semua anggota
- `GET /anggota.php?id=AG001` - Ambil anggota by ID
- `POST /anggota.php` - Tambah anggota baru
- `DELETE /anggota.php?id=AG001` - Hapus anggota

### Buku
- `GET /buku.php` - Ambil semua buku
- `GET /buku.php?id=BK001` - Ambil buku by ID
- `POST /buku.php` - Tambah buku baru
- `PUT /buku.php` - Update buku
- `DELETE /buku.php?id=BK001` - Hapus buku

### Peminjaman
- `GET /peminjaman.php?action=list` - Daftar peminjaman aktif
- `GET /peminjaman.php?action=stats` - Statistik dashboard
- `POST /peminjaman.php?action=pinjam` - Proses peminjaman
- `POST /peminjaman.php?action=kembali` - Proses pengembalian

## 📊 Komponen yang Diimplementasikan

### 1. Form dan Input Data HTML ✅
- Input text, select, radio button, checkbox
- Validasi form di frontend

### 2. Validasi Form PHP ✅
- `filter_var()` untuk validasi email
- Fungsi validasi custom (validateEmail, validatePhone, validateDate)
- Validasi panjang string dengan `strlen()`

### 3. Manipulasi String ✅
- `strlen()`, `explode()`, `trim()`, `htmlspecialchars()`
- `substr()`, `mb_convert_case()` untuk transformasi string

### 4. File Handling ✅
- `fopen()`, `fwrite()`, `fclose()` - Operasi file
- `file_put_contents()` - Write file dengan locking
- `mkdir()` - Create directory

### 5. Debugging & Error Handling ✅
- `error_reporting(E_ALL)` - Report semua error
- `ini_set()` - Konfigurasi error logging
- `logError()` & `logActivity()` - Custom logging functions
- Try-catch exception handling

## 🗄️ Database Schema

### Table: anggota
```sql
- id (VARCHAR, PRIMARY KEY)
- nama (VARCHAR)
- email (VARCHAR, UNIQUE)
- telp (VARCHAR)
- alamat (TEXT)
- jenis (ENUM: Reguler, Premium, Student)
- status (ENUM: Aktif, Nonaktif, Suspended)
- created_at, updated_at (TIMESTAMP)
```

### Table: buku
```sql
- id (VARCHAR, PRIMARY KEY)
- judul (VARCHAR)
- pengarang (VARCHAR)
- penerbit (VARCHAR)
- tahun_terbit (INT)
- isbn (VARCHAR, UNIQUE)
- kategori (VARCHAR)
- stok (INT)
- stok_tersedia (INT)
- lokasi (VARCHAR)
- status (ENUM: Tersedia, Rusak, Hilang)
- created_at, updated_at (TIMESTAMP)
```

### Table: peminjaman
```sql
- id (VARCHAR, PRIMARY KEY)
- anggota_id (VARCHAR, FOREIGN KEY)
- buku_id (VARCHAR, FOREIGN KEY)
- tanggal_pinjam (DATE)
- tanggal_kembali_rencana (DATE)
- tanggal_kembali_aktual (DATE)
- durasi (INT)
- status (ENUM: Aktif, Selesai, Hilang, Rusak)
- denda (INT)
- created_at, updated_at (TIMESTAMP)
```

## 🐛 Debugging

Cek error log:
```bash
tail -f logs/error.log
```

Atau lihat di MySQL:
```sql
SELECT * FROM activity_log ORDER BY created_at DESC LIMIT 10;
```

## 👥 Default Data Sample

### Anggota Sample:
- ID: AG001, Nama: Budi Santoso
- ID: AG002, Nama: Siti Nurhaliza
- ID: AG003, Nama: Ahmad Rahman

### Buku Sample:
- ID: BK001, Judul: Laskar Pelangi
- ID: BK002, Judul: Negeri 5 Menara
- ID: BK003, Judul: Data Science Fundamentals

## 📝 Catatan Penting

1. **CORS Headers** - Jika deploy ke production, pastikan `.htaccess` ada
2. **File Logs** - Folder `logs/` harus writable oleh web server
3. **Database Config** - Edit `config.php` jika menggunakan database yang berbeda
4. **Security** - Untuk production, gunakan environment variables untuk DB credentials

## 👨‍💻 Author

Dibuat oleh: Fajar, Erix, Sony

## 📄 License

MIT License - Bebas digunakan untuk keperluan apapun

---

**Last Updated:** 12 December 2025
