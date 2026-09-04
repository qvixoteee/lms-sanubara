<?php
session_start();
if (isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}
include_once 'config/Database.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $database = new Database();
    $db = $database->getConnection();

    try {
        // Menggunakan tabel 'users' dan kolom 'username'
        $stmt = $db->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Memeriksa password (mendukung teks biasa atau hash SHA256/password_verify)
        if ($user && ($password === $user['password'] || password_verify($password, $user['password']) || hash('sha256', $password) === $user['password'])) {
            // Simpan data ke session sesuai skema tabel users
            $_SESSION['user'] = [
                'id' => $user['id_user'],
                'name' => $user['nama_lengkap'],
                'username' => $user['username'],
                'departemen' => $user['departemen']
            ];
            header("Location: index.php");
            exit;
        } else {
            $error = "Username atau kata sandi salah!";
        }
    } catch (Exception $e) {
        $error = "Terjadi kesalahan pada database: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sanubara Learning Center</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans antialiased h-screen flex items-center justify-center p-0 lg:p-6">

    <!-- Kontainer Utama: 2 Sisi di Desktop (Grid 2 Kolom), 1 Kolom di HP -->
    <div class="w-full h-full lg:h-[85vh] lg:max-w-6xl lg:rounded-3xl lg:shadow-2xl overflow-hidden bg-white grid grid-cols-1 lg:grid-cols-2">
        
        <!-- ================= SISI KIRI: FORM LOGIN ================= -->
        <div class="flex flex-col justify-center items-center p-8 sm:p-12 lg:p-16 h-full bg-white">
            <div class="w-full max-w-sm space-y-4">
                
                <!-- Logo yang diatur rapi dan ditengahkan -->
                <div class="w-full flex justify-center">
                    <img src="https://i.ibb.co.com/vCGQWjc7/Sanubara-grup-20260725-151551-0000.png" alt="Sanubara Group" class="w-80% h-auto object-contain">
                </div>

                <!-- Judul -->
                <div class="text-center space-y-1">
                    <h2 class="text-2xl font-black text-gray-900">Login LMS</h2>
                    <p class="text-xs text-gray-500">Sanubara Learning Center</p>
                </div>

                <!-- Pesan Error (jika ada) -->
                <?php if (!empty($error)): ?>
                    <div class="w-full bg-red-50 text-red-600 text-xs p-3 rounded-2xl font-medium border border-red-100 text-center">
                        <?= htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <!-- Form Login -->
                <form action="" method="POST" class="space-y-4 text-left">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Username</label>
                        <input type="text" name="username" required class="w-full px-4 py-3 rounded-2xl border border-gray-400 text-xs focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 bg-gray-50/50 transition">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Kata Sandi</label>
                        <div class="relative">
                            <input type="password" name="password" id="password" required class="w-full px-4 py-3 pr-10 rounded-2xl border border-gray-400 text-xs focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 bg-gray-50/50 transition">
                            <button type="button" onclick="togglePassword()" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 focus:outline-none">
                                <svg id="eyeOpen" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="w-full bg-amber-500 hover:bg-amber-600 text-white font-bold py-3 rounded-2xl text-xs shadow-md transition duration-200 mt-2 cursor-pointer">
                        Masuk
                    </button>
                </form>

            </div>
        </div>

        <!-- ================= SISI KANAN: ILUSTRASI / BACKGROUND KORPORAT ================= -->
        <div class="hidden lg:flex relative bg-amber-500 justify-center items-center overflow-hidden p-8">
            
            <!-- Pola latar belakang dekoratif halus -->
            <div class="absolute  inset-0 opacity-50 bg-[radial-gradient(#fff_1px,transparent_1px)] [background-size:16px_16px]"></div>

                <!-- Gambar Ilustrasi / Pembelajaran -->
                <img src="https://images.unsplash.com/photo-1579389083078-4e7018379f7e?q=80&w=1170&auto=format&fit=crop&ixlib=rb-4.1.0&ixid=
                M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D" alt="Learning Center" class="absolute bottom-0 object-cover w-full h-full opacity-90 mix-blend-overlay">

            </div>

        </div>

    </div>

</body>
<script>
function togglePassword() {
    const input = document.getElementById('password');
    if (input.type === 'password') {
        input.type = 'text';
    } else {
        input.type = 'password';
    }
}
</script>
</html>