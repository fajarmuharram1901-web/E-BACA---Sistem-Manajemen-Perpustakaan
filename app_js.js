/**
 * app.js - Frontend Application Logic
 * Terintegrasi dengan PHP Backend API
 */

// API Base URL - sesuaikan dengan lokasi backend Anda
const API_URL = 'http://localhost/revisilagi';

// Data cache
let buku = [];
let anggota = [];
let peminjaman = [];
let pengembalian = [];

// ===== UTILITY FUNCTIONS =====

function showAlert(elementId, message, type) {
    const alertDiv = document.getElementById(elementId);
    alertDiv.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
    setTimeout(() => {
        alertDiv.innerHTML = '';
    }, 5000);
}

function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('show');
}

function showSection(sectionId) {
    document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.menu-item').forEach(m => m.classList.remove('active'));
    
    document.getElementById(sectionId).classList.add('active');
    event.target.closest('.menu-item').classList.add('active');
    
    if (window.innerWidth <= 768) {
        document.getElementById('sidebar').classList.remove('show');
    }
    
    // Load data sesuai section
    if (sectionId === 'dashboard') loadDashboard();
    if (sectionId === 'buku') loadBuku();
    if (sectionId === 'anggota') loadAnggota();
    if (sectionId === 'peminjaman') {
        loadPeminjaman();
        loadPeminjamanDropdowns();
    }
    if (sectionId === 'pengembalian') {
        loadPengembalian();
        loadPengembalianDropdowns();
    }
}

// ===== API CALL FUNCTIONS =====

async function apiCall(endpoint, method = 'GET', data = null) {
    try {
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            }
        };
        
        if (data && method !== 'GET') {
            options.body = JSON.stringify(data);
        }
        
        const response = await fetch(`${API_URL}/${endpoint}`, options);
        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.message || 'Terjadi kesalahan');
        }
        
        return result;
        
    } catch (error) {
        console.error('API Error:', error);
        throw error;
    }
}

// ===== DASHBOARD =====

async function loadDashboard() {
    try {
        const result = await apiCall('peminjaman.php?action=stats');
        
        if (result.success) {
            const stats = result.data;
            document.getElementById('totalBuku').textContent = stats.totalBuku;
            document.getElementById('totalAnggota').textContent = stats.totalAnggota;
            document.getElementById('bukuDipinjam').textContent = stats.bukuDipinjam;
            document.getElementById('totalDenda').textContent = 
                'Rp ' + parseInt(stats.totalDenda).toLocaleString('id-ID');
        }
    } catch (error) {
        console.error('Error loading dashboard:', error);
    }
}

// ===== MANAJEMEN BUKU =====

async function loadBuku() {
    try {
        const result = await apiCall('buku.php');
        
        if (result.success) {
            buku = result.data;
            renderBuku(buku);
        }
    } catch (error) {
        showAlert('bukuAlert', 'Gagal memuat data buku', 'error');
    }
}

function renderBuku(data) {
    const tbody = document.getElementById('bukuList');
    tbody.innerHTML = '';
    
    if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center">Tidak ada data</td></tr>';
        return;
    }
    
    data.forEach((b, index) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${index + 1}</td>
            <td>${b.judul}</td>
            <td>${b.pengarang}</td>
            <td>${b.kategori}</td>
            <td>${b.isbn}</td>
            <td><span style="color: ${b.status === 'Tersedia' ? 'green' : 'red'}">${b.status}</span></td>
            <td>
                <div class="action-btns">
                    <button class="btn btn-small btn-danger" onclick="deleteBuku('${b.id}')">Hapus</button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

document.getElementById('formBuku').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const data = {
        judul: document.getElementById('judulBuku').value.trim(),
        pengarang: document.getElementById('pengarangBuku').value.trim(),
        penerbit: document.getElementById('penerbitBuku').value.trim(),
        tahun_terbit: document.getElementById('tahunBuku').value ? parseInt(document.getElementById('tahunBuku').value) : null,
        isbn: document.getElementById('isbnBuku').value.trim(),
        kategori: document.getElementById('kategoriBuku').value,
        stok: document.getElementById('stokBuku').value ? parseInt(document.getElementById('stokBuku').value) : 0,
        lokasi: document.getElementById('lokasiBuku').value.trim()
    };
    
    try {
        const result = await apiCall('buku.php', 'POST', data);
        
        if (result.success) {
            showAlert('bukuAlert', result.message, 'success');
            this.reset();
            loadBuku();
            loadDashboard();
        }
    } catch (error) {
        showAlert('bukuAlert', error.message, 'error');
    }
});

async function deleteBuku(id) {
    if (!confirm('Yakin ingin menghapus buku ini?')) return;
    
    try {
        const result = await apiCall(`buku.php?id=${id}`, 'DELETE');
        
        if (result.success) {
            showAlert('bukuAlert', result.message, 'success');
            loadBuku();
            loadDashboard();
        }
    } catch (error) {
        alert(error.message);
    }
}

// Search Buku
document.getElementById('searchBuku').addEventListener('input', function(e) {
    const search = e.target.value.toLowerCase();
    const filtered = buku.filter(b => 
        b.judul.toLowerCase().includes(search) || 
        b.pengarang.toLowerCase().includes(search)
    );
    renderBuku(filtered);
});

// ===== MANAJEMEN ANGGOTA =====

async function loadAnggota() {
    try {
        const result = await apiCall('anggota.php');
        
        if (result.success) {
            anggota = result.data;
            renderAnggota(anggota);
        }
    } catch (error) {
        showAlert('anggotaAlert', 'Gagal memuat data anggota', 'error');
    }
}

function renderAnggota(data) {
    const tbody = document.getElementById('anggotaList');
    tbody.innerHTML = '';
    
    if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center">Tidak ada data</td></tr>';
        return;
    }
    
    data.forEach((a) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${a.id}</td>
            <td>${a.nama}</td>
            <td>${a.email}</td>
            <td>${a.telp}</td>
            <td>${a.jenis}</td>
            <td>
                <div class="action-btns">
                    <button class="btn btn-small btn-danger" onclick="deleteAnggota('${a.id}')">Hapus</button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

document.getElementById('formAnggota').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const data = {
        nama: document.getElementById('namaAnggota').value.trim(),
        email: document.getElementById('emailAnggota').value.trim(),
        telp: document.getElementById('telpAnggota').value.trim(),
        alamat: document.getElementById('alamatAnggota').value.trim(),
        jenis: document.getElementById('jenisAnggota').value
    };
    
    try {
        const result = await apiCall('anggota.php', 'POST', data);
        
        if (result.success) {
            showAlert('anggotaAlert', result.message, 'success');
            this.reset();
            loadAnggota();
            loadDashboard();
        }
    } catch (error) {
        showAlert('anggotaAlert', error.message, 'error');
    }
});

async function deleteAnggota(id) {
    if (!confirm('Yakin ingin menghapus anggota ini?')) return;
    
    try {
        const result = await apiCall(`anggota.php?id=${id}`, 'DELETE');
        
        if (result.success) {
            showAlert('anggotaAlert', result.message, 'success');
            loadAnggota();
            loadDashboard();
        }
    } catch (error) {
        alert(error.message);
    }
}

// Search Anggota
document.getElementById('searchAnggota').addEventListener('input', function(e) {
    const search = e.target.value.toLowerCase();
    const filtered = anggota.filter(a => 
        a.nama.toLowerCase().includes(search) || 
        a.email.toLowerCase().includes(search)
    );
    renderAnggota(filtered);
});

// ===== PEMINJAMAN =====

async function loadPeminjaman() {
    try {
        const result = await apiCall('peminjaman.php?action=list');
        
        if (result.success) {
            peminjaman = result.data;
            renderPeminjaman(peminjaman);
        }
    } catch (error) {
        console.error('Error loading peminjaman:', error);
    }
}

function renderPeminjaman(data) {
    const tbody = document.getElementById('peminjamanList');
    tbody.innerHTML = '';
    
    if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center">Tidak ada peminjaman aktif</td></tr>';
        return;
    }
    
    data.forEach(p => {
        const statusColor = p.terlambat ? 'red' : 'green';
        const statusText = p.status_display || p.status;
        
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${p.id}</td>
            <td>${p.anggota_nama}</td>
            <td>${p.buku_judul}</td>
            <td>${p.tgl_pinjam}</td>
            <td>${p.tgl_jatuh_tempo}</td>
            <td><span style="color: ${statusColor}">${statusText}</span></td>
        `;
        tbody.appendChild(tr);
    });
}

async function loadPeminjamanDropdowns() {
    try {
        // Load anggota dan buku untuk dropdown
        if (anggota.length === 0) await loadAnggota();
        if (buku.length === 0) await loadBuku();
        
        const selectAnggota = document.getElementById('anggotaPinjam');
        const selectBuku = document.getElementById('bukuPinjam');
        
        selectAnggota.innerHTML = '<option value="">-- Pilih Anggota --</option>';
        anggota.forEach(a => {
            selectAnggota.innerHTML += `<option value="${a.id}">${a.nama} (${a.id})</option>`;
        });
        
        selectBuku.innerHTML = '<option value="">-- Pilih Buku --</option>';
        buku.filter(b => b.status === 'Tersedia').forEach(b => {
            selectBuku.innerHTML += `<option value="${b.id}">${b.judul} - ${b.pengarang}</option>`;
        });
    } catch (error) {
        console.error('Error loading dropdowns:', error);
    }
}

document.getElementById('formPeminjaman').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const data = {
        anggotaId: document.getElementById('anggotaPinjam').value,
        bukuId: document.getElementById('bukuPinjam').value,
        tglPinjam: document.getElementById('tglPinjam').value,
        durasi: parseInt(document.getElementById('durasiPinjam').value)
    };
    
    try {
        const result = await apiCall('peminjaman.php?action=pinjam', 'POST', data);
        
        if (result.success) {
            showAlert('peminjamanAlert', result.message, 'success');
            this.reset();
            loadPeminjaman();
            loadBuku();
            loadDashboard();
            loadPeminjamanDropdowns();
        }
    } catch (error) {
        showAlert('peminjamanAlert', error.message, 'error');
    }
});

// ===== PENGEMBALIAN =====

async function loadPengembalian() {
    try {
        const result = await apiCall('peminjaman.php?action=riwayat');
        
        if (result.success) {
            pengembalian = result.data;
            renderPengembalian(pengembalian);
        }
    } catch (error) {
        console.error('Error loading pengembalian:', error);
    }
}

function renderPengembalian(data) {
    const tbody = document.getElementById('pengembalianList');
    tbody.innerHTML = '';
    
    if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center">Belum ada pengembalian</td></tr>';
        return;
    }
    
    data.forEach(p => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${p.id}</td>
            <td>${p.anggota_nama}</td>
            <td>${p.buku_judul}</td>
            <td>${p.tgl_kembali}</td>
            <td>${p.keterlambatan} hari</td>
            <td>Rp ${parseInt(p.denda).toLocaleString('id-ID')}</td>
        `;
        tbody.appendChild(tr);
    });
}

async function loadPengembalianDropdowns() {
    try {
        if (peminjaman.length === 0) await loadPeminjaman();
        
        const select = document.getElementById('peminjamanKembali');
        select.innerHTML = '<option value="">-- Pilih Peminjaman --</option>';
        
        peminjaman.forEach(p => {
            select.innerHTML += `<option value="${p.id}">${p.anggota_nama} - ${p.buku_judul}</option>`;
        });
    } catch (error) {
        console.error('Error loading dropdown:', error);
    }
}

document.getElementById('formPengembalian').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const data = {
        peminjamanId: document.getElementById('peminjamanKembali').value,
        tglKembali: document.getElementById('tglKembali').value,
        kondisi: document.querySelector('input[name="kondisiKembali"]:checked').value
    };
    
    try {
        const result = await apiCall('peminjaman.php?action=kembali', 'POST', data);
        
        if (result.success) {
            showAlert('pengembalianAlert', result.message, 'success');
            this.reset();
            loadPengembalian();
            loadPeminjaman();
            loadBuku();
            loadDashboard();
            loadPengembalianDropdowns();
        }
    } catch (error) {
        showAlert('pengembalianAlert', error.message, 'error');
    }
});

// ===== INITIALIZE APPLICATION =====

window.addEventListener('load', function() {
    // Set default dates
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('tglPinjam').value = today;
    document.getElementById('tglKembali').value = today;
    
    // Load dashboard
    loadDashboard();
});
