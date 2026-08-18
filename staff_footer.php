            <!-- Footer Content -->
            <div class="mt-10 pt-4 border-t border-slate-200 dark:border-slate-700/50 text-center text-xs font-medium text-slate-400 dark:text-slate-500">
                &copy; <?php echo date('Y'); ?>, made with <i class="fa-solid fa-mug-hot text-amber-600 dark:text-amber-500 mx-1"></i> by <a href="https://api.whatsapp.com/send/?phone=6285742818069&text&type=phone_number&app_absent=0" target="_blank" class="text-brand-600 dark:text-brand-400 font-semibold hover:underline transition-colors">Javag Team</a>
            </div>
        </div> <!-- End of .flex-1 p-4 sm:p-8 -->
    </main> <!-- End of .lg:ml-72 -->

    <!-- Mobile Bottom Safe Area (optional spacing for devices with bottom bars) -->
    <div class="h-6 lg:hidden pb-[env(safe-area-inset-bottom)]"></div>

    <script>
        // Modal global functions (if needed by staff pages)
        window.openModal = function(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }
        };

        window.closeModal = function(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('hidden');
                document.body.style.overflow = '';
            }
        };
        
        // Close modal on Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modals = document.querySelectorAll('[id^="modal-"]:not(.hidden)');
                modals.forEach(modal => {
                    closeModal(modal.id);
                });
            }
        });

        // --- Fungsi Global Logout (SweetAlert2) ---
        function confirmLogout(event, url) {
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
                    cancelButtonText: 'Batal'
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
    </script>
    
    <!-- SweetAlert2 (Already included in header) -->
</body>
</html>
