// assets/js/main.js

document.addEventListener("DOMContentLoaded", function() {
    // --- Inisialisasi Sidebar Mobile ---
    const sidebar = document.getElementById('sidebar');
    const openSidebarBtn = document.getElementById('openSidebar');
    const closeSidebarBtn = document.getElementById('closeSidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    function openSidebar() {
        if(sidebar) sidebar.classList.remove('hidden');
        if(sidebarOverlay) sidebarOverlay.classList.remove('hidden');
    }

    function closeSidebar() {
        if(sidebar) sidebar.classList.add('hidden');
        if(sidebarOverlay) sidebarOverlay.classList.add('hidden');
    }

    if(openSidebarBtn) openSidebarBtn.addEventListener('click', openSidebar);
    if(closeSidebarBtn) closeSidebarBtn.addEventListener('click', closeSidebar);
    if(sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);

    // --- Theme (Dark Mode) ---
    const html = document.documentElement;
    const darkModeToggle = document.getElementById('darkModeToggle');
    
    // Check local storage
    if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        html.classList.add('dark');
    } else {
        html.classList.remove('dark');
    }

    if(darkModeToggle) {
        darkModeToggle.addEventListener('click', () => {
            html.classList.toggle('dark');
            if (html.classList.contains('dark')) {
                localStorage.setItem('theme', 'dark');
            } else {
                localStorage.setItem('theme', 'light');
            }
        });
    }

    // --- Logika untuk QR Code ---
    const qrForm = document.getElementById('qrForm');
    if(qrForm) {
        let qrcode = null; 
        qrForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const select = document.getElementById('karyawan_select');
            const selectedOption = select.options[select.selectedIndex];
            const karyawanId = selectedOption.value;
            if (!karyawanId) { alert('Silakan pilih karyawan terlebih dahulu.'); return; }
            
            const namaKaryawan = selectedOption.getAttribute('data-nama');
            const url = window.location.origin + window.location.pathname.replace(/\/[^\/]+$/, '') + `/absen.php?id=${karyawanId}`;
            
            document.getElementById('qr-result-box').style.display = 'block';
            const qrCanvas = document.getElementById('qrcode');
            qrCanvas.innerHTML = '';
            qrcode = new QRCode(qrCanvas, { text: url, width: 200, height: 200 });
            document.getElementById('qr-nama').textContent = namaKaryawan;
            document.getElementById('qr-id').textContent = `ID: ${karyawanId}`;
        });
    }

    // --- Event Listener untuk Tombol Edit Cabang ---
    document.querySelectorAll('.btn-edit-cabang').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.dataset.id;
            const nama = this.dataset.nama;
            const alamat = this.dataset.alamat;
            if(typeof openEditCabangModal === 'function') {
                openEditCabangModal(id, nama, alamat);
            }
        });
    });
});

// --- Fungsi Global untuk Modal ---
window.openModal = function(modalId) { 
    const modal = document.getElementById(modalId);
    if(modal) {
        modal.classList.remove("hidden");
        document.body.style.overflow = 'hidden';
    } 
}
window.closeModal = function(modalId) { 
    const modal = document.getElementById(modalId);
    if(modal) {
        modal.classList.add("hidden"); 
        document.body.style.overflow = '';
    }
}

// --- Fungsi untuk Modal Edit Karyawan ---
window.openEditKaryawanModal = function(id, idKaryawan, nama, jenisKelamin, idJabatan, idCabang) {
    document.getElementById('edit-id-karyawan-pk').value = id;
    document.getElementById('edit-id-karyawan').value = idKaryawan;
    document.getElementById('edit-nama-karyawan').value = nama;
    document.getElementById('edit-jenis-kelamin').value = jenisKelamin;
    document.getElementById('edit-id-jabatan').value = idJabatan;
    document.getElementById('edit-id-cabang').value = idCabang;
    openModal('modal-edit');
}

// Menutup modal jika klik di luar area modal content atau pencet tombol Escape
window.onclick = function(event) {
    let modals = document.querySelectorAll('[id^="modal-"]');
    modals.forEach(modal => {
        if (event.target == modal) {
            closeModal(modal.id);
        }
    });
}
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modals = document.querySelectorAll('[id^="modal-"]:not(.hidden)');
        modals.forEach(modal => {
            closeModal(modal.id);
        });
    }
});

// --- Fungsi Global untuk QR Code ---
window.printQRCode = function() { window.print(); }
window.downloadPNG = function() {
    const canvas = document.querySelector('#qrcode canvas');
    if (!canvas) { alert('Silakan generate QR code terlebih dahulu.'); return; }
    const link = document.createElement('a');
    const karyawanId = document.getElementById('qr-id').textContent.replace('ID: ', '');
    link.download = `qrcode-${karyawanId}.png`;
    link.href = canvas.toDataURL('image/png');
    link.click();
}

// --- Fungsi Global Hapus Data Master (AJAX + SweetAlert2) ---
window.handleDeleteAction = function(url, title, text) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: title,
            text: text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e11d48',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Oke, Hapus',
            cancelButtonText: 'Batal',
            heightAuto: false
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Memproses...',
                    text: 'Mohon tunggu sebentar',
                    allowOutsideClick: false,
                    heightAuto: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                fetch(url + '&is_ajax=1')
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil!',
                                text: data.message,
                                timer: 2000,
                                showConfirmButton: false,
                                heightAuto: false
                            }).then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal!',
                                text: data.message,
                                heightAuto: false
                            });
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Terjadi Kesalahan!',
                            text: 'Gagal terhubung ke server.',
                            heightAuto: false
                        });
                    });
            }
        });
    } else {
        if (confirm(text)) {
            window.location.href = url;
        }
    }
}

// --- Fungsi Global Logout (SweetAlert2) ---
window.confirmLogout = function(event, url) {
    event.preventDefault();
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Konfirmasi Logout',
            text: 'Apakah Anda yakin ingin keluar dari sistem?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#e11d48',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Ya, Logout',
            cancelButtonText: 'Batal',
            heightAuto: false
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = url;
            }
        });
    } else {
        if (confirm('Apakah Anda yakin ingin logout?')) {
            window.location.href = url;
        }
    }
}

// --- Fungsi Global Submit Form AJAX (SweetAlert2) ---
window.handleFormAjaxGlobal = function(formId, processText, confirmText, actionParamName) {
    const form = document.getElementById(formId);
    if(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Konfirmasi',
                    text: confirmText,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#10b981',
                    cancelButtonColor: '#94a3b8',
                    confirmButtonText: 'Ya, Simpan!',
                    cancelButtonText: 'Batal',
                    heightAuto: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Memproses...',
                            text: processText,
                            allowOutsideClick: false,
                            heightAuto: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        
                        const formData = new FormData(this);
                        formData.append('is_ajax', '1');
                        if (actionParamName) {
                            formData.append(actionParamName, '1');
                        }
                        
                        fetch(this.action, {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Berhasil!',
                                    text: data.message,
                                    timer: 2000,
                                    showConfirmButton: false,
                                    heightAuto: false
                                }).then(() => {
                                    window.location.reload();
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Gagal!',
                                    text: data.message,
                                    heightAuto: false
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            Swal.fire({
                                icon: 'error',
                                title: 'Terjadi Kesalahan!',
                                text: 'Gagal terhubung ke server.',
                                heightAuto: false
                            });
                        });
                    }
                });
            } else {
                if (confirm(confirmText)) {
                    this.submit();
                }
            }
        });
    }
}

// --- Fungsi Global Redirect dengan SweetAlert2 ---
window.confirmRedirect = function(event, url, title, text, confirmBtnText = 'Ya, Lanjutkan', iconType = 'warning') {
    event.preventDefault();
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: title,
            text: text,
            icon: iconType,
            showCancelButton: true,
            confirmButtonColor: '#e11d48',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: confirmBtnText,
            cancelButtonText: 'Batal',
            heightAuto: false
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = url;
            }
        });
    } else {
        if (confirm(text)) {
            window.location.href = url;
        }
    }
}
