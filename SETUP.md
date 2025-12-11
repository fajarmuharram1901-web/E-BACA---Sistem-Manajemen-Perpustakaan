# 📖 Panduan Setup E-BACA Perpustakaan

## 🎯 Langkah-Langkah Setup

### A. Setup Database

#### Opsi 1: Menggunakan Command Line
```powershell
# Windows PowerShell
& "C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe" -u root -e "CREATE DATABASE perpustakaan_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

#### Opsi 2: Menggunakan phpMyAdmin
1. Buka `http://localhost/phpmyadmin`
2. Klik "New" di sidebar
3. Nama database: `perpustakaan_db`
4. Collation: `utf8mb4_unicode_ci`
5. Klik Create

### B. Setup Tabel Database

File SQL sudah ada di project, jalankan:

```bash
mysql -u root perpustakaan_db < database.sql
```

Atau import manual di phpMyAdmin dengan file SQL yang disediakan.

### C. Setup .htaccess (PRODUCTION ONLY)

Jika menggunakan Apache Server di **production**, rename file:

```bash
# Windows PowerShell
Rename-Item -Path "htaccess.example" -NewName ".htaccess"

# Atau Linux/Mac
mv htaccess.example .htaccess
```

**UNTUK LARAGON:** Tidak perlu rename, `.htaccess` optional.

### D. Setup Folder Logs

Buat folder untuk logs:

```bash
# Windows PowerShell
mkdir logs

# Linux/Mac
mkdir -p logs
chmod 755 logs
```

### E. Verifikasi Setup

#### Test Database Connection:
```bash
mysql -u root perpustakaan_db -e "SHOW TABLES;"
```

**Output yang benar:**
```
activity_log
anggota
buku
error_log
peminjaman
```

#### Test API Endpoints:
```bash
# Test Buku API
curl http://localhost/revisilagi/buku.php

# Test Anggota API
curl http://localhost/revisilagi/anggota.php

# Test Stats
curl "http://localhost/revisilagi/peminjaman.php?action=stats"
```

### F. Akses Aplikasi

Buka browser:
```
http://localhost/revisilagi/frontend_integrated.html
```

---

## 🔌 API Testing dengan Postman/cURL

### Create Buku (POST)
```bash
curl -X POST http://localhost/revisilagi/buku.php \
  -H "Content-Type: application/json" \
  -d '{
    "judul": "Buku Baru",
    "pengarang": "Penulis Baru",
    "kategori": "Fiksi",
    "stok": 5
  }'
```

### Create Anggota (POST)
```bash
curl -X POST http://localhost/revisilagi/anggota.php \
  -H "Content-Type: application/json" \
  -d '{
    "nama": "Nama Anggota",
    "email": "email@example.com",
    "telp": "081234567890",
    "alamat": "Alamat",
    "jenis": "Reguler"
  }'
```

### Get All Buku (GET)
```bash
curl http://localhost/revisilagi/buku.php
```

### Delete Buku (DELETE)
```bash
curl -X DELETE "http://localhost/revisilagi/buku.php?id=BK001"
```

---

## 🐛 Troubleshooting

### Problem: "Database Connection Error"
**Solution:**
1. Pastikan MySQL running: `Get-Process mysqld`
2. Check credentials di `config.php`
3. Cek logs: `Get-Content logs/error.log`

### Problem: "404 Not Found"
**Solution:**
1. Pastikan file PHP ada di folder
2. Cek file path di `app_js.js` (API_URL)
3. Restart Apache

### Problem: "CORS Error"
**Solution:**
1. Rename `.htaccess` jika di production
2. Atau debug CORS headers: `curl -i http://localhost/revisilagi/buku.php`

### Problem: "File Permission Denied"
**Solution:**
```bash
# Windows: Run as Administrator
# Linux/Mac:
chmod 755 logs/
chmod 644 logs/*
```

---

## 📊 Database Backup

### Backup Database:
```bash
mysqldump -u root perpustakaan_db > backup.sql
```

### Restore Database:
```bash
mysql -u root perpustakaan_db < backup.sql
```

---

## 🔐 Security Notes (Production)

1. **Use Environment Variables** untuk DB credentials:
   ```php
   $host = getenv('DB_HOST');
   $user = getenv('DB_USER');
   $pass = getenv('DB_PASS');
   ```

2. **Enable .htaccess** untuk CORS headers

3. **Set Proper Permissions:**
   ```bash
   chmod 644 *.php
   chmod 755 logs/
   ```

4. **Use HTTPS** di production

5. **Validate & Sanitize** semua input (sudah ada di code)

---

## 📱 Mobile Responsive

Aplikasi sudah responsive untuk mobile. CSS sudah include:
```css
@media (max-width: 768px) {
  /* Mobile styles */
}
```

---

## 🎓 Learning Points

Aplikasi ini mengajarkan:

1. **PHP Basics** - Database connection, CRUD operations
2. **Form Validation** - Client-side & Server-side
3. **File Handling** - Logging, file operations
4. **Error Handling** - Exception handling, debugging
5. **API Development** - RESTful API dengan JSON
6. **Frontend JS** - DOM manipulation, async/await, fetch API
7. **SQL** - Database design, queries, relationships
8. **Security** - Input validation, sanitization, CORS

---

**Happy Learning! 🚀**
