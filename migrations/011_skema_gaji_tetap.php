<?php
return [
    'name' => 'Skema payroll dan profil penghasilan tetap per karyawan',
    'up' => function ($conn, MigrationLog $log) {
        if (!tabelAda($conn, 'employee_salary_profile')) {
            $sql = "CREATE TABLE `employee_salary_profile` (
                `id` int NOT NULL AUTO_INCREMENT,
                `id_karyawan` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
                `scheme_code` varchar(30) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'STANDARD_ATTENDANCE',
                `effective_from` date NOT NULL,
                `effective_to` date DEFAULT NULL,
                `gaji_pokok` decimal(15,2) NOT NULL DEFAULT '0.00',
                `transport_tetap` decimal(15,2) NOT NULL DEFAULT '0.00',
                `uang_makan_tetap` decimal(15,2) NOT NULL DEFAULT '0.00',
                `approved_by` int DEFAULT NULL,
                `approved_at` datetime DEFAULT NULL,
                `catatan` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
                `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_salary_profile_period` (`id_karyawan`,`effective_from`),
                KEY `idx_salary_profile_active` (`id_karyawan`,`effective_from`,`effective_to`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
            if (!$conn->query($sql)) {
                $log->error('Gagal membuat employee_salary_profile: ' . $conn->error);
                return;
            }
            $log->ok('Tabel <code>employee_salary_profile</code> berhasil dibuat.');
        } else {
            $log->skip('Tabel <code>employee_salary_profile</code> sudah ada.');
        }

        $columns = [
            'payroll_scheme' => "varchar(30) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'STANDARD_ATTENDANCE' AFTER `tanggal_cetak`",
            'salary_profile_id' => "int DEFAULT NULL AFTER `payroll_scheme`",
            'transport_tetap' => "decimal(15,2) NOT NULL DEFAULT '0.00' AFTER `akomodasi`",
            'uang_makan_tetap' => "decimal(15,2) NOT NULL DEFAULT '0.00' AFTER `transport_tetap`",
            'penghasilan_tetap' => "decimal(15,2) NOT NULL DEFAULT '0.00' AFTER `uang_makan_tetap`",
            'tampilkan_potongan' => "tinyint(1) NOT NULL DEFAULT '1' AFTER `gaji_bersih`",
        ];
        foreach ($columns as $name => $definition) {
            if (kolomAda($conn, 'slip_gaji', $name)) {
                $log->skip("Kolom <code>slip_gaji.$name</code> sudah ada.");
                continue;
            }
            if (!$conn->query("ALTER TABLE `slip_gaji` ADD COLUMN `$name` $definition")) {
                $log->error("Gagal menambah slip_gaji.$name: " . $conn->error);
                return;
            }
            $log->ok("Kolom <code>slip_gaji.$name</code> berhasil ditambahkan.");
        }
    },
];
