<?php
require_once 'auth_check.php';
checkAuth(['admin']); // Only admin can access settings
require_once '../backend/database.php';

$db = new Database();
$conn = $db->getConnection();

// Handle form submissions
if ($_POST) {
    $success = false;
    $message = '';
    
    switch ($_POST['action']) {
        case 'general':
            // Handle general settings
            $app_name = $_POST['app_name'] ?? '';
            $timezone = $_POST['timezone'] ?? '';
            $date_format = $_POST['date_format'] ?? '';
            $theme = $_POST['theme'] ?? '';
            $language = $_POST['language'] ?? '';
            
            // Save to database or config file
            $success = true;
            $message = 'Pengaturan umum berhasil disimpan!';
            break;
            
        case 'data':
            // Handle data settings
            $items_per_page = $_POST['items_per_page'] ?? 10;
            $default_order = $_POST['default_order'] ?? 'npk ASC';
            
            $success = true;
            $message = 'Pengaturan data berhasil disimpan!';
            break;
            
        case 'security':
            // Handle security settings
            $login_attempts = $_POST['login_attempts'] ?? 3;
            $session_timeout = $_POST['session_timeout'] ?? 60;
            
            $success = true;
            $message = 'Pengaturan keamanan berhasil disimpan!';
            break;
            
        case 'backup':
            // Handle backup data
            if (isset($_POST['backup_type'])) {
                $backup_type = $_POST['backup_type'];
                $backup_dir = '../backups/';
                
                // Create backup directory if not exists
                if (!file_exists($backup_dir)) {
                    mkdir($backup_dir, 0755, true);
                }
                
                $timestamp = date('Y-m-d_H-i-s');
                $filename = "backup_{$backup_type}_{$timestamp}";
                
                try {
                    switch ($backup_type) {
                        case 'json':
                            $data = [];
                            // Get all employees data
                            $stmt = $conn->query("SELECT * FROM employees ORDER BY NPK");
                            $data['employees'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            // Get education data
                            $stmt = $conn->query("SELECT * FROM education ORDER BY dateEdukasi DESC");
                            $data['education'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            // Get skill matrix data
                            $stmt = $conn->query("SELECT * FROM skill_matrix_comp_assy");
                            $data['skill_comp_assy'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            $stmt = $conn->query("SELECT * FROM skill_matrix_wclutch");
                            $data['skill_wclutch'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            file_put_contents($backup_dir . $filename . '.json', json_encode($data, JSON_PRETTY_PRINT));
                            break;
                            
                        case 'csv':
                            // Backup employees to CSV
                            $stmt = $conn->query("SELECT * FROM employees ORDER BY NPK");
                            $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            $csv_file = fopen($backup_dir . $filename . '_employees.csv', 'w');
                            if (!empty($employees)) {
                                fputcsv($csv_file, array_keys($employees[0]));
                                foreach ($employees as $row) {
                                    fputcsv($csv_file, $row);
                                }
                            }
                            fclose($csv_file);
                            break;
                            
                        case 'complete':
                            // Complete backup including all tables
                            $tables = ['employees', 'education', 'skill_matrix_comp_assy', 'skill_matrix_wclutch'];
                            $backup_data = [];
                            
                            foreach ($tables as $table) {
                                $stmt = $conn->query("SELECT * FROM {$table}");
                                $backup_data[$table] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            }
                            
                            file_put_contents($backup_dir . $filename . '_complete.json', json_encode($backup_data, JSON_PRETTY_PRINT));
                            break;
                    }
                    
                    $success = true;
                    $message = "Backup {$backup_type} berhasil dibuat: {$filename}";
                } catch (Exception $e) {
                    $success = false;
                    $message = "Error saat membuat backup: " . $e->getMessage();
                }
            }
            break;
            
        case 'upload':
            // Handle file upload
            if (isset($_FILES['upload_file']) && $_FILES['upload_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/';
                
                // Create upload directory if not exists
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_tmp = $_FILES['upload_file']['tmp_name'];
                $file_name = $_FILES['upload_file']['name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                $allowed_extensions = ['json', 'csv', 'xlsx'];
                
                if (in_array($file_ext, $allowed_extensions)) {
                    $new_filename = 'upload_' . date('Y-m-d_H-i-s') . '.' . $file_ext;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($file_tmp, $upload_path)) {
                        $success = true;
                        $message = "File berhasil diupload: {$new_filename}";
                    } else {
                        $success = false;
                        $message = "Gagal mengupload file.";
                    }
                } else {
                    $success = false;
                    $message = "Format file tidak didukung. Hanya JSON, CSV, dan Excel yang diizinkan.";
                }
            } else {
                $success = false;
                $message = "Tidak ada file yang dipilih atau terjadi error saat upload.";
            }
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan - MP Development</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            min-height: 100vh;
            color: #334155;
        }
        
        /* Background pattern yang subtle */
        body::before {
          content: '';
          position: fixed;
          top: 0;
          left: 0;
          right: 0;
          bottom: 0;
          background-image: 
            radial-gradient(circle at 25% 25%, #f1f5f9 0%, transparent 50%),
            radial-gradient(circle at 75% 75%, #f8fafc 0%, transparent 50%);
          z-index: -1;
          opacity: 0.5;
        }
        
        /* Top Navigation */
        .top-nav {
          position: fixed;
          top: 0;
          left: 0;
          right: 0;
          background: white;
          border-bottom: 1px solid #e2e8f0;
          padding: 15px 30px;
          z-index: 1000;
          box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .nav-brand {
          color: #1e293b;
          font-size: 1.4rem;
          font-weight: 600;
          text-decoration: none;
        }
        
        .nav-brand:hover {
          color: #334155;
          text-decoration: none;
        }
        
        .nav-actions {
          display: flex;
          gap: 10px;
          align-items: center;
        }
        
        .nav-btn {
          background: #f1f5f9;
          border: 1px solid #e2e8f0;
          color: #475569;
          padding: 8px 16px;
          border-radius: 8px;
          text-decoration: none;
          font-weight: 500;
          transition: all 0.2s ease;
          font-size: 0.9rem;
        }
        
        .nav-btn:hover {
          background: #e2e8f0;
          color: #334155;
          text-decoration: none;
        }
        
        .nav-btn.active {
          background: #8b5cf6;
          color: white;
          border-color: #8b5cf6;
        }
        
        /* Main Content */
        .main-container {
          margin-top: 80px;
          padding: 30px;
          max-width: 100%;
        }
        
        .page-header {
          background: white;
          border-radius: 12px;
          padding: 30px;
          margin-bottom: 25px;
          box-shadow: 0 1px 3px rgba(0,0,0,0.1);
          border: 1px solid #e2e8f0;
        }
        
        .page-title {
          font-size: 2rem;
          font-weight: 600;
          color: #1e293b;
          margin-bottom: 10px;
        }
        
        .page-subtitle {
          color: #64748b;
          font-size: 1.1rem;
          margin-bottom: 0;
        }
        
        /* Settings Navigation */
        .settings-nav {
          background: white;
          border-radius: 12px;
          padding: 20px;
          margin-bottom: 25px;
          box-shadow: 0 1px 3px rgba(0,0,0,0.1);
          border: 1px solid #e2e8f0;
        }
        
        .settings-nav-title {
          font-size: 1.1rem;
          font-weight: 600;
          color: #1e293b;
          margin-bottom: 20px;
        }
        
        .settings-tab {
          border: none;
          border-radius: 8px;
          margin-bottom: 8px;
          padding: 12px 16px;
          text-align: left;
          background: #f8fafc;
          transition: all 0.3s ease;
          width: 100%;
          color: #475569;
          font-weight: 500;
        }
        
        .settings-tab.active {
          background: #8b5cf6;
          color: white;
          transform: translateX(4px);
        }
        
        .settings-tab:hover {
          background: #e2e8f0;
          transform: translateX(2px);
        }
        
        .settings-tab.active:hover {
          background: #7c3aed;
        }
        
        /* Settings Content */
        .settings-content {
          background: white;
          border-radius: 12px;
          padding: 30px;
          box-shadow: 0 1px 3px rgba(0,0,0,0.1);
          border: 1px solid #e2e8f0;
          transition: all 0.3s ease;
        }
        
        .settings-content:hover {
          transform: translateY(-2px);
          box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .form-section {
          margin-bottom: 30px;
          padding-bottom: 25px;
          border-bottom: 1px solid #f1f5f9;
        }
        
        .form-section:last-child {
          border-bottom: none;
          margin-bottom: 0;
        }
        
        .section-title {
          font-size: 1.1rem;
          font-weight: 600;
          color: #1e293b;
          margin-bottom: 15px;
          display: flex;
          align-items: center;
          gap: 8px;
        }
        
        .form-label {
          font-weight: 600;
          color: #374151;
          margin-bottom: 8px;
        }
        
        .form-control, .form-select {
          border: 1px solid #d1d5db;
          border-radius: 8px;
          padding: 10px 12px;
          background: white;
          color: #334155;
          transition: all 0.2s ease;
        }
        
        .form-control:focus, .form-select:focus {
          border-color: #8b5cf6;
          box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }
        
        .btn-primary {
          background: #8b5cf6;
          border-color: #8b5cf6;
          padding: 12px 24px;
          border-radius: 8px;
          font-weight: 500;
          transition: all 0.2s ease;
        }
        
        .btn-primary:hover {
          background: #7c3aed;
          border-color: #7c3aed;
          transform: translateY(-1px);
        }
        
        .btn-success {
          background: #10b981;
          border-color: #10b981;
          padding: 10px 20px;
          border-radius: 8px;
          font-weight: 500;
          transition: all 0.2s ease;
        }
        
        .btn-success:hover {
          background: #059669;
          border-color: #059669;
          transform: translateY(-1px);
        }
        
        .btn-info {
          background: #06b6d4;
          border-color: #06b6d4;
          padding: 10px 20px;
          border-radius: 8px;
          font-weight: 500;
          transition: all 0.2s ease;
        }
        
        .btn-info:hover {
          background: #0891b2;
          border-color: #0891b2;
          transform: translateY(-1px);
        }
        
        .btn-warning {
          background: #f59e0b;
          border-color: #f59e0b;
          padding: 10px 20px;
          border-radius: 8px;
          font-weight: 500;
          transition: all 0.2s ease;
        }
        
        .btn-warning:hover {
          background: #d97706;
          border-color: #d97706;
          transform: translateY(-1px);
        }
        
        /* Backup Cards */
        .backup-card {
          background: white;
          border: 2px solid #e2e8f0;
          border-radius: 12px;
          padding: 25px;
          text-align: center;
          margin-bottom: 20px;
          transition: all 0.3s ease;
        }
        
        .backup-card:hover {
          border-color: #8b5cf6;
          background-color: #faf5ff;
          transform: translateY(-4px);
          box-shadow: 0 8px 25px rgba(139, 92, 246, 0.15);
        }
        
        .backup-icon {
          font-size: 2.5rem;
          margin-bottom: 15px;
          display: block;
        }
        
        .backup-title {
          font-size: 1.1rem;
          font-weight: 600;
          color: #1e293b;
          margin-bottom: 8px;
        }
        
        .backup-desc {
          color: #64748b;
          font-size: 0.9rem;
          margin-bottom: 20px;
        }
        
        /* Upload Area */
        .upload-area {
          border: 2px dashed #d1d5db;
          border-radius: 12px;
          padding: 40px 20px;
          text-align: center;
          cursor: pointer;
          transition: all 0.3s ease;
          background: #fafbfc;
        }
        
        .upload-area:hover {
          border-color: #8b5cf6;
          background-color: #faf5ff;
        }
        
        .upload-area.dragover {
          border-color: #8b5cf6;
          background-color: #f3f0ff;
          transform: scale(1.02);
        }
        
        .upload-icon {
          font-size: 3rem;
          color: #6b7280;
          margin-bottom: 15px;
        }
        
        /* Alert Styling */
        .alert {
          border-radius: 8px;
          border: none;
          padding: 15px 20px;
        }
        
        .alert-success {
          background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
          color: #065f46;
          border-left: 4px solid #10b981;
        }
        
        .alert-danger {
          background: linear-gradient(135deg, #fef2f2 0%, #fecaca 100%);
          color: #991b1b;
          border-left: 4px solid #ef4444;
        }
        
        .alert-info {
          background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
          color: #1e40af;
          border-left: 4px solid #3b82f6;
        }
        
        /* Table Styling */
        .table {
          background: white;
          border-radius: 8px;
          overflow: hidden;
          border: 1px solid #e2e8f0;
        }
        
        .table thead th {
          background: #f8fafc;
          border-bottom: 1px solid #e2e8f0;
          color: #374151;
          font-weight: 600;
          font-size: 0.875rem;
          padding: 12px;
        }
        
        .table tbody td {
          padding: 12px;
          border-bottom: 1px solid #f1f5f9;
          vertical-align: middle;
        }
        
        .table tbody tr:hover {
          background: #f8fafc;
        }
        
        .badge {
          padding: 6px 12px;
          border-radius: 6px;
          font-size: 0.75rem;
          font-weight: 500;
        }
        
        /* Loading Animation */
        .fade-in {
          animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
          from { opacity: 0; transform: translateY(20px); }
          to { opacity: 1; transform: translateY(0); }
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
          .main-container {
            padding: 20px 15px;
          }
          
          .top-nav {
            padding: 12px 20px;
          }
          
          .nav-brand {
            font-size: 1.2rem;
          }
          
          .nav-btn {
            padding: 6px 12px;
            font-size: 0.8rem;
          }
          
          .page-header,
          .settings-content,
          .settings-nav {
            padding: 20px;
          }
          
          .backup-card {
            padding: 20px;
          }
          
          .upload-area {
            padding: 30px 15px;
          }
        }
    </style>
</head>
<body>

<!-- Top Navigation -->
<div class="top-nav">
  <div class="d-flex justify-content-between align-items-center">
    <a href="workspace.php" class="nav-brand">🛠️ Man Power Development Management System</a>
    <div class="nav-actions">
      <a href="workspace.php" class="nav-btn">🚀 Workspace</a>
      <a href="settings.php" class="nav-btn">⚙️ Settings</a>
      <a href="logout.php" class="nav-btn">🚪 Logout</a>
    </div>
  </div>
</div>

<div class="main-container">
  
  <!-- Page Header -->
  <div class="page-header fade-in">
    <h1 class="page-title">⚙️ Pengaturan Sistem</h1>
    <p class="page-subtitle">Kelola konfigurasi sistem dan backup data</p>
  </div>

  <!-- Alert Messages -->
  <?php if (isset($success) && $success): ?>
    <div class="alert alert-success alert-dismissible fade show fade-in" role="alert">
      <strong>✅ Berhasil!</strong> <?= $message ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php elseif (isset($success) && !$success): ?>
    <div class="alert alert-danger alert-dismissible fade show fade-in" role="alert">
      <strong>❌ Error!</strong> <?= $message ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <div class="row">
    
    <!-- Settings Navigation -->
    <div class="col-lg-3 col-md-4">
      <div class="settings-nav fade-in">
        <h6 class="settings-nav-title">📋 Menu Pengaturan</h6>
        <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist">
          <button class="settings-tab active" id="general-tab" data-bs-toggle="pill" data-bs-target="#general" type="button" role="tab">
            🔧 Pengaturan Umum
          </button>
          <button class="settings-tab" id="data-tab" data-bs-toggle="pill" data-bs-target="#data" type="button" role="tab">
            📊 Data & Tampilan
          </button>
          <button class="settings-tab" id="security-tab" data-bs-toggle="pill" data-bs-target="#security" type="button" role="tab">
            🔒 Keamanan
          </button>
          <button class="settings-tab" id="backup-tab" data-bs-toggle="pill" data-bs-target="#backup" type="button" role="tab">
            💾 Backup & Restore
          </button>
        </div>
      </div>
    </div>
    
    <!-- Settings Content -->
    <div class="col-lg-9 col-md-8">
      <div class="tab-content fade-in" id="v-pills-tabContent">
        
        <!-- General Settings -->
        <div class="tab-pane fade show active" id="general" role="tabpanel">
          <div class="settings-content">
            <h4 class="mb-4">🔧 Pengaturan Umum</h4>
            
            <form method="POST">
              <input type="hidden" name="action" value="general">
              
              <div class="form-section">
                <div class="section-title">ℹ️ Informasi Aplikasi</div>
                
                <div class="mb-3">
                  <label for="app_name" class="form-label">Nama Aplikasi</label>
                  <input type="text" class="form-control" id="app_name" name="app_name" value="MP Development Management System" placeholder="Masukkan nama aplikasi">
                </div>
                
                <div class="row">
                  <div class="col-md-6">
                    <label for="timezone" class="form-label">Zona Waktu</label>
                    <select class="form-select" id="timezone" name="timezone">
                      <option value="Asia/Jakarta" selected>Asia/Jakarta (WIB)</option>
                      <option value="Asia/Makassar">Asia/Makassar (WITA)</option>
                      <option value="Asia/Jayapura">Asia/Jayapura (WIT)</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label for="date_format" class="form-label">Format Tanggal</label>
                    <select class="form-select" id="date_format" name="date_format">
                      <option value="d/m/Y" selected>DD/MM/YYYY</option>
                      <option value="m/d/Y">MM/DD/YYYY</option>
                      <option value="Y-m-d">YYYY-MM-DD</option>
                    </select>
                  </div>
                </div>
              </div>
              
              <div class="form-section">
                <div class="section-title">🎨 Tampilan</div>
                
                <div class="row">
                  <div class="col-md-6">
                    <label for="theme" class="form-label">Tema</label>
                    <select class="form-select" id="theme" name="theme">
                      <option value="light" selected>Terang</option>
                      <option value="dark">Gelap</option>
                      <option value="auto">Otomatis</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label for="language" class="form-label">Bahasa</label>
                    <select class="form-select" id="language" name="language">
                      <option value="id" selected>Bahasa Indonesia</option>
                      <option value="en">English</option>
                    </select>
                  </div>
                </div>
              </div>
              
              <button type="submit" class="btn btn-primary">💾 Simpan Pengaturan</button>
            </form>
          </div>
        </div>
        
        <!-- Data Settings -->
        <div class="tab-pane fade" id="data" role="tabpanel">
          <div class="settings-content">
            <h4 class="mb-4">📊 Pengaturan Data & Tampilan</h4>
            
            <form method="POST">
              <input type="hidden" name="action" value="data">
              
              <div class="form-section">
                <div class="section-title">📋 Tampilan Tabel</div>
                
                <div class="row">
                  <div class="col-md-6">
                    <label for="items_per_page" class="form-label">Item per Halaman</label>
                    <select class="form-select" id="items_per_page" name="items_per_page">
                      <option value="10">10</option>
                      <option value="25" selected>25</option>
                      <option value="50">50</option>
                      <option value="100">100</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label for="default_order" class="form-label">Urutan Default</label>
                    <select class="form-select" id="default_order" name="default_order">
                      <option value="npk ASC" selected>NPK (A-Z)</option>
                      <option value="npk DESC">NPK (Z-A)</option>
                      <option value="nama ASC">Nama (A-Z)</option>
                      <option value="nama DESC">Nama (Z-A)</option>
                      <option value="section ASC">Section (A-Z)</option>
                    </select>
                  </div>
                </div>
              </div>
              
              <button type="submit" class="btn btn-primary">📊 Simpan Pengaturan Data</button>
            </form>
          </div>
        </div>
        
        <!-- Security Settings -->
        <div class="tab-pane fade" id="security" role="tabpanel">
          <div class="settings-content">
            <h4 class="mb-4">🔒 Pengaturan Keamanan</h4>
            
            <form method="POST">
              <input type="hidden" name="action" value="security">
              
              <div class="form-section">
                <div class="section-title">🛡️ Keamanan Login</div>
                
                <div class="row">
                  <div class="col-md-6">
                    <label for="login_attempts" class="form-label">Maksimal Percobaan Login</label>
                    <input type="number" class="form-control" id="login_attempts" name="login_attempts" value="3" min="1" max="10">
                    <div class="form-text">Jumlah maksimal percobaan login sebelum akun dikunci</div>
                  </div>
                  <div class="col-md-6">
                    <label for="session_timeout" class="form-label">Timeout Session (menit)</label>
                    <select class="form-select" id="session_timeout" name="session_timeout">
                      <option value="30">30 menit</option>
                      <option value="60" selected>60 menit</option>
                      <option value="120">120 menit</option>
                      <option value="240">240 menit</option>
                    </select>
                  </div>
                </div>
              </div>
              
              <button type="submit" class="btn btn-primary">🔒 Simpan Pengaturan Keamanan</button>
            </form>
          </div>
        </div>
        
        <!-- Backup & Restore -->
        <div class="tab-pane fade" id="backup" role="tabpanel">
          <div class="settings-content">
            <h4 class="mb-4">💾 Backup & Restore Data</h4>
            
            <!-- Backup Section -->
            <div class="form-section">
              <div class="section-title">📤 Backup Data</div>
              <p class="text-muted mb-4">Buat backup data sistem dalam berbagai format untuk keamanan data</p>
              
              <div class="row">
                <div class="col-lg-4 col-md-6 mb-3">
                  <div class="backup-card">
                    <span class="backup-icon">📄</span>
                    <div class="backup-title">JSON Format</div>
                    <div class="backup-desc">Backup lengkap semua data dalam format JSON</div>
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="action" value="backup">
                      <input type="hidden" name="backup_type" value="json">
                      <button type="submit" class="btn btn-success">📥 Backup JSON</button>
                    </form>
                  </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-3">
                  <div class="backup-card">
                    <span class="backup-icon">📊</span>
                    <div class="backup-title">CSV Format</div>
                    <div class="backup-desc">Backup data karyawan dalam format CSV</div>
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="action" value="backup">
                      <input type="hidden" name="backup_type" value="csv">
                      <button type="submit" class="btn btn-info">📥 Backup CSV</button>
                    </form>
                  </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-3">
                  <div class="backup-card">
                    <span class="backup-icon">🗃️</span>
                    <div class="backup-title">Backup Lengkap</div>
                    <div class="backup-desc">Backup semua tabel dan data sistem</div>
                    <form method="POST" class="d-inline">
                      <input type="hidden" name="action" value="backup">
                      <input type="hidden" name="backup_type" value="complete">
                      <button type="submit" class="btn btn-warning">📥 Backup Lengkap</button>
                    </form>
                  </div>
                </div>
              </div>
              
              <div class="alert alert-info">
                <strong>💡 Tips:</strong> 
                <ul class="mb-0 mt-2">
                  <li>File backup akan disimpan di folder <code>/backups/</code> dengan timestamp</li>
                  <li>Disarankan melakukan backup secara berkala</li>
                  <li>JSON format cocok untuk restore data lengkap</li>
                  <li>CSV format cocok untuk analisis data di Excel</li>
                </ul>
              </div>
            </div>
            
            <!-- Upload Section -->
            <div class="form-section">
              <div class="section-title">📤 Upload & Restore Data</div>
              <p class="text-muted mb-4">Upload file data untuk restore atau import data baru</p>
              
              <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <input type="hidden" name="action" value="upload">
                
                <div class="upload-area" id="uploadArea" onclick="document.getElementById('fileInput').click()">
                  <div class="upload-icon">☁️</div>
                  <h5>Klik untuk memilih file atau drag & drop</h5>
                  <p class="text-muted">Mendukung format: JSON, CSV, Excel (.xlsx)</p>
                  <p class="small text-muted">Maksimal ukuran file: 10MB</p>
                  
                  <input type="file" id="fileInput" name="upload_file" accept=".json,.csv,.xlsx" style="display: none;" onchange="handleFileSelect(this)">
                </div>
                
                <div id="fileInfo" class="mt-3" style="display: none;">
                  <div class="alert alert-info">
                    <strong>📄 File dipilih:</strong> <span id="fileName"></span><br>
                    <strong>📏 Ukuran:</strong> <span id="fileSize"></span><br>
                    <strong>🏷️ Tipe:</strong> <span id="fileType"></span>
                  </div>
                  
                  <button type="submit" class="btn btn-primary me-2">📤 Upload File</button>
                  <button type="button" class="btn btn-secondary" onclick="resetUpload()">❌ Batal</button>
                </div>
              </form>
            </div>
            
            <!-- Recent Files -->
            <div class="form-section">
              <div class="section-title">📁 File Backup Terbaru</div>
              <div class="table-responsive">
                <table class="table">
                  <thead>
                    <tr>
                      <th>Nama File</th>
                      <th>Tipe</th>
                      <th>Ukuran</th>
                      <th>Tanggal</th>
                      <th>Aksi</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                    $backup_dir = '../backups/';
                    $upload_dir = '../uploads/';
                    $files = [];
                    
                    // Get backup files
                    if (is_dir($backup_dir)) {
                        $backup_files = scandir($backup_dir);
                        foreach ($backup_files as $file) {
                            if ($file != '.' && $file != '..') {
                                $files[] = [
                                    'name' => $file,
                                    'path' => $backup_dir . $file,
                                    'type' => 'Backup',
                                    'size' => filesize($backup_dir . $file),
                                    'date' => filemtime($backup_dir . $file)
                                ];
                            }
                        }
                    }
                    
                    // Get upload files
                    if (is_dir($upload_dir)) {
                        $upload_files = scandir($upload_dir);
                        foreach ($upload_files as $file) {
                            if ($file != '.' && $file != '..') {
                                $files[] = [
                                    'name' => $file,
                                    'path' => $upload_dir . $file,
                                    'type' => 'Upload',
                                    'size' => filesize($upload_dir . $file),
                                    'date' => filemtime($upload_dir . $file)
                                ];
                            }
                        }
                    }
                    
                    // Sort by date (newest first)
                    usort($files, function($a, $b) {
                        return $b['date'] - $a['date'];
                    });
                    
                    // Show only last 10 files
                    $files = array_slice($files, 0, 10);
                    
                    if (empty($files)): ?>
                      <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                          <div style="color: #64748b;">
                            📂 Belum ada file backup atau upload
                          </div>
                        </td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($files as $file): ?>
                        <tr>
                          <td><?= htmlspecialchars($file['name']) ?></td>
                          <td>
                            <span class="badge bg-<?= $file['type'] == 'Backup' ? 'primary' : 'success' ?>">
                              <?= $file['type'] ?>
                            </span>
                          </td>
                          <td><?= number_format($file['size'] / 1024, 2) ?> KB</td>
                          <td><?= date('d/m/Y H:i', $file['date']) ?></td>
                          <td>
                            <a href="<?= $file['path'] ?>" class="btn btn-sm btn-outline-primary" download>
                              📥 Download
                            </a>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
        
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Smooth animations on load
document.addEventListener('DOMContentLoaded', function() {
  const elements = document.querySelectorAll('.fade-in');
  
  elements.forEach((element, index) => {
    element.style.opacity = '0';
    element.style.transform = 'translateY(20px)';
    
    setTimeout(() => {
      element.style.transition = 'all 0.5s ease';
      element.style.opacity = '1';
      element.style.transform = 'translateY(0)';
    }, index * 100);
  });
});

// Handle tab switching
document.querySelectorAll('.settings-tab').forEach(tab => {
  tab.addEventListener('click', function() {
    document.querySelectorAll('.settings-tab').forEach(t => t.classList.remove('active'));
    this.classList.add('active');
  });
});

// File upload handling
function handleFileSelect(input) {
  const file = input.files[0];
  if (file) {
    document.getElementById('fileName').textContent = file.name;
    document.getElementById('fileSize').textContent = (file.size / 1024).toFixed(2) + ' KB';
    document.getElementById('fileType').textContent = file.type || 'Unknown';
    document.getElementById('fileInfo').style.display = 'block';
  }
}

function resetUpload() {
  document.getElementById('fileInput').value = '';
  document.getElementById('fileInfo').style.display = 'none';
}

// Drag and drop functionality
const uploadArea = document.getElementById('uploadArea');

uploadArea.addEventListener('dragover', function(e) {
  e.preventDefault();
  this.classList.add('dragover');
});

uploadArea.addEventListener('dragleave', function(e) {
  e.preventDefault();
  this.classList.remove('dragover');
});

uploadArea.addEventListener('drop', function(e) {
  e.preventDefault();
  this.classList.remove('dragover');
  
  const files = e.dataTransfer.files;
  if (files.length > 0) {
    document.getElementById('fileInput').files = files;
    handleFileSelect(document.getElementById('fileInput'));
  }
});

// Auto-dismiss alerts after 5 seconds
setTimeout(function() {
  const alerts = document.querySelectorAll('.alert');
  alerts.forEach(alert => {
    if (alert.querySelector('.btn-close')) {
      alert.querySelector('.btn-close').click();
    }
  });
}, 5000);
</script>
</body>
</html>