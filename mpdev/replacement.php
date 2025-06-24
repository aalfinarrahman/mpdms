<?php
require_once 'auth_check.php';
checkAuth(['admin', 'manager', 'spv', 'trainer', 'leader', 'foreman']);
require_once '../backend/database.php';

$db = new Database();
$conn = $db->getConnection();

// Handle GET request untuk delete
if (isset($_GET['delete'])) {
    try {
        $id = (int)$_GET['delete'];
        $stmt = $conn->prepare("DELETE FROM recruitment WHERE id = ?");
        $stmt->execute([$id]);
        
        // Preserve filter parameters in redirect
        $redirectParams = [];
        if (isset($_GET['filter_month']) && !empty($_GET['filter_month'])) {
            $redirectParams[] = 'filter_month=' . urlencode($_GET['filter_month']);
        }
        if (isset($_GET['filter_year']) && !empty($_GET['filter_year'])) {
            $redirectParams[] = 'filter_year=' . urlencode($_GET['filter_year']);
        }
        $redirectParams[] = 'success=delete';
        
        $redirectUrl = 'replacement.php?' . implode('&', $redirectParams);
        header("Location: $redirectUrl");
        exit();
    } catch (Exception $e) {
        $error_message = "Gagal menghapus data: " . $e->getMessage();
    }
}

// Handle POST request untuk tambah/edit replacement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_auto'])) {
            // Proses otomatis untuk multiple end contracts
            if (isset($_POST['selected_contracts']) && is_array($_POST['selected_contracts'])) {
                $successCount = 0;
                foreach ($_POST['selected_contracts'] as $npk) {
                    // Cari data end contract
                    $contractStmt = $conn->prepare("SELECT * FROM end_contracts WHERE npk = ?");
                    $contractStmt->execute([$npk]);
                    $contract = $contractStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($contract) {  
                        $sql = "INSERT INTO recruitment 
                            (`npk_keluar`, `nama_keluar`, `date_out`, `section`, `line`, `leader`, `status`, `dateCreated`) 
                            VALUES (:npk_keluar, :nama_keluar, :date_out, :section, :line, :leader, :status, NOW())";
                        
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([
                            ':npk_keluar' => $contract['npk'],
                            ':nama_keluar' => $contract['name'],
                            ':date_out' => $contract['dateOut'],
                            ':section' => $contract['section'],
                            ':line' => $contract['line'],
                            ':leader' => $contract['leader'],
                            ':status' => 'On Time'
                        ]);
                        $successCount++;
                    }
                }
            }
            
            // Preserve filter parameters in redirect
            $redirectParams = [];
            if (isset($_GET['filter_month']) && !empty($_GET['filter_month'])) {
                $redirectParams[] = 'filter_month=' . urlencode($_GET['filter_month']);
            }
            if (isset($_GET['filter_year']) && !empty($_GET['filter_year'])) {
                $redirectParams[] = 'filter_year=' . urlencode($_GET['filter_year']);
            }
            $redirectParams[] = 'success=auto';
            
            $redirectUrl = 'replacement.php?' . implode('&', $redirectParams);
            header("Location: $redirectUrl");
            exit();
        }
        
        if (isset($_POST['add_manual'])) {
            $sql = "INSERT INTO recruitment 
                (`npk_keluar`, `nama_keluar`, `date_out`, `npk_pengganti`, `nama_pengganti`, `gender`, `section`, `line`, `leader`, `date_in`, `rekomendasi`, `status`, `dateCreated`) 
                VALUES (:npk_keluar, :nama_keluar, :date_out, :npk_pengganti, :nama_pengganti, :gender, :section, :line, :leader, :date_in, :rekomendasi, :status, NOW())";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':npk_keluar' => $_POST['npk_keluar'],
                ':nama_keluar' => $_POST['nama_keluar'],
                ':date_out' => $_POST['date_out'],
                ':npk_pengganti' => $_POST['npk_pengganti'],
                ':nama_pengganti' => $_POST['nama_pengganti'],
                ':gender' => $_POST['gender'],
                ':section' => $_POST['section'],
                ':line' => $_POST['line'],
                ':leader' => $_POST['leader'],
                ':date_in' => $_POST['date_in'],
                ':rekomendasi' => $_POST['rekomendasi'],
                ':status' => $_POST['status']
            ]);
            
            // Preserve filter parameters in redirect
            $redirectParams = [];
            if (isset($_GET['filter_month']) && !empty($_GET['filter_month'])) {
                $redirectParams[] = 'filter_month=' . urlencode($_GET['filter_month']);
            }
            if (isset($_GET['filter_year']) && !empty($_GET['filter_year'])) {
                $redirectParams[] = 'filter_year=' . urlencode($_GET['filter_year']);
            }
            $redirectParams[] = 'success=manual';
            
            $redirectUrl = 'replacement.php?' . implode('&', $redirectParams);
            header("Location: $redirectUrl");
            exit();
        }
        
        if (isset($_POST['edit'])) {
            $sql = "UPDATE recruitment SET 
                npk_pengganti = :npk_pengganti,
                nama_pengganti = :nama_pengganti,
                gender = :gender,
                date_in = :date_in,
                rekomendasi = :rekomendasi,
                status = :status
                WHERE id = :id";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':npk_pengganti' => $_POST['npk_pengganti'],
                ':nama_pengganti' => $_POST['nama_pengganti'],
                ':gender' => $_POST['gender'],
                ':date_in' => $_POST['date_in'],
                ':rekomendasi' => $_POST['rekomendasi'],
                ':status' => $_POST['status'],
                ':id' => $_POST['id']
            ]);
            
            // Preserve filter parameters in redirect
            $redirectParams = [];
            if (isset($_GET['filter_month']) && !empty($_GET['filter_month'])) {
                $redirectParams[] = 'filter_month=' . urlencode($_GET['filter_month']);
            }
            if (isset($_GET['filter_year']) && !empty($_GET['filter_year'])) {
                $redirectParams[] = 'filter_year=' . urlencode($_GET['filter_year']);
            }
            $redirectParams[] = 'success=edit';
            
            $redirectUrl = 'replacement.php?' . implode('&', $redirectParams);
            header("Location: $redirectUrl");
            exit();
        }
    } catch (Exception $e) {
        $error_message = "Terjadi kesalahan: " . $e->getMessage();
    }
}

// Handle date filters
$whereClause = "";
$params = [];

// Get filter values
$filter_month = isset($_GET['filter_month']) && !empty($_GET['filter_month']) ? (int)$_GET['filter_month'] : null;
$filter_year = isset($_GET['filter_year']) && !empty($_GET['filter_year']) ? (int)$_GET['filter_year'] : null;

// Build where clause based on date_out (tanggal keluar MP)
if ($filter_month) {
    $whereClause .= " AND MONTH(r.date_out) = :month";
    $params[':month'] = $filter_month;
}

if ($filter_year) {
    $whereClause .= " AND YEAR(r.date_out) = :year";
    $params[':year'] = $filter_year;
}

// Get filtered replacement data
try {
    $sql = "SELECT * FROM recruitment r WHERE 1=1" . $whereClause . " ORDER BY r.date_out DESC, r.dateCreated DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $data = [];
    $error_message = "Gagal mengambil data: " . $e->getMessage();
}

// Query untuk data end contracts yang belum ada di replacement
try {
    $endContractsStmt = $conn->query("
        SELECT ec.npk, ec.name, ec.dateOut, ec.section, ec.line, ec.leader 
        FROM end_contracts ec 
        WHERE ec.npk NOT IN (SELECT npk_keluar FROM recruitment WHERE npk_keluar IS NOT NULL)
        AND ec.dateOut >= CURDATE() - INTERVAL 30 DAY
        ORDER BY ec.dateOut DESC
    ");
    $endContracts = $endContractsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $endContracts = [];
}

// Query untuk data employees (untuk auto-fill pengganti)
try {
    $employeesStmt = $conn->query("SELECT * FROM employees WHERE Status = 'Aktif' ORDER BY NPK DESC");
    $employees = $employeesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $employees = [];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Replacement - MP Development</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <style>
    body {
      background: #f8fafc;
      font-family: 'Segoe UI', sans-serif;
      margin: 0;
      color: #334155;
    }
    
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
    
    .main-container {
      margin-top: 80px;
      padding: 30px;
      max-width: 100%;
    }
    
    .section-title {
      font-size: 1.5rem;
      font-weight: 600;
      color: #1e293b;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .filter-card, .data-card, .info-card {
      background: white;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 20px;
      margin-bottom: 20px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      transition: all 0.3s ease;
    }
    
    .filter-card:hover, .data-card:hover {
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    .filter-title, .card-title {
      font-size: 1.1rem;
      font-weight: 600;
      color: #1e293b;
      margin-bottom: 15px;
    }
    
    .form-select, .form-control {
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 8px 12px;
      background: white;
      color: #334155;
    }
    
    .form-select:focus, .form-control:focus {
      border-color: #3b82f6;
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .btn-primary {
      background: #3b82f6;
      border-color: #3b82f6;
      padding: 10px 20px;
      border-radius: 8px;
      font-weight: 500;
    }
    
    .btn-primary:hover {
      background: #2563eb;
      border-color: #2563eb;
    }
    
    .btn-success {
      background: #10b981;
      border-color: #10b981;
      padding: 10px 20px;
      border-radius: 8px;
      font-weight: 500;
    }
    
    .btn-success:hover {
      background: #059669;
      border-color: #059669;
    }
    
    .btn-warning {
      background: #f59e0b;
      border-color: #f59e0b;
      color: white;
      padding: 10px 20px;
      border-radius: 8px;
      font-weight: 500;
    }
    
    .btn-warning:hover {
      background: #d97706;
      border-color: #d97706;
      color: white;
    }
    
    .btn-secondary {
      background: #6b7280;
      border-color: #6b7280;
      padding: 10px 20px;
      border-radius: 8px;
      font-weight: 500;
    }
    
    .btn-danger {
      background: #ef4444;
      border-color: #ef4444;
    }
    
    .btn-danger:hover {
      background: #dc2626;
      border-color: #dc2626;
    }
    
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
      text-transform: uppercase;
      letter-spacing: 0.025em;
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
    
    .bg-success {
      background-color: #10b981 !important;
    }
    
    .bg-warning {
      background-color: #f59e0b !important;
      color: white !important;
    }
    
    .bg-danger {
      background-color: #ef4444 !important;
    }
    
    .modal-content {
      border: none;
      border-radius: 12px;
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }
    
    .modal-header {
      border-bottom: 1px solid #e2e8f0;
      padding: 20px 25px;
    }
    
    .modal-body {
      padding: 25px;
    }
    
    .modal-footer {
      border-top: 1px solid #e2e8f0;
      padding: 20px 25px;
    }
    
    .alert {
      border: none;
      border-radius: 8px;
      padding: 12px 16px;
    }
    
    .alert-success {
      background: #d1fae5;
      color: #065f46;
      border-left: 4px solid #10b981;
    }
    
    .alert-danger {
      background: #fee2e2;
      color: #991b1b;
      border-left: 4px solid #ef4444;
    }
    
    .alert-info {
      background: #dbeafe;
      color: #1e3a8a;
      border-left: 4px solid #3b82f6;
    }
    
    .card-header {
      background: #3b82f6 !important;
      color: white !important;
      border-radius: 12px 12px 0 0 !important;
      padding: 15px 20px;
      font-weight: 600;
    }
    
    .status-info {
      background: #dbeafe;
      border: 1px solid #93c5fd;
      border-radius: 8px;
      padding: 15px;
      margin-bottom: 20px;
    }
    
    .filter-summary {
      background: #f1f5f9;
      border-radius: 6px;
      padding: 8px 12px;
      font-size: 0.875rem;
      color: #64748b;
      margin-top: 10px;
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
    
    /* DataTables custom styling */
    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter,
    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_paginate {
      color: #374151;
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button {
      border: 1px solid #e2e8f0;
      background: white;
      color: #374151 !important;
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
      background: #3b82f6 !important;
      border-color: #3b82f6 !important;
      color: white !important;
    }
    
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
      
      .data-card {
        padding: 15px;
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
  <!-- Page Title -->
  <div class="section-title">
    🔄 Replacement Management
  </div>

  <!-- Alerts -->
  <?php if (isset($_GET['success'])): ?>
    <?php if ($_GET['success'] == 'auto'): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        ✅ Data replacement otomatis berhasil ditambahkan!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php elseif ($_GET['success'] == 'manual'): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        ✅ Data replacement manual berhasil ditambahkan!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php elseif ($_GET['success'] == 'edit'): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        ✅ Data replacement berhasil diupdate!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php elseif ($_GET['success'] == 'delete'): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        ✅ Data replacement berhasil dihapus!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <?php if (isset($error_message)): ?>
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    ❌ <?= htmlspecialchars($error_message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>

  <!-- Filter Section -->
  <div class="filter-card">
    <div class="filter-title">🔍 Filter Data (Berdasarkan Tanggal Keluar MP)</div>
    <form method="GET" class="row g-3" id="filterForm">
      <div class="col-lg-3 col-md-4">
        <label class="form-label">Bulan Keluar</label>
        <select name="filter_month" class="form-select">
          <option value="">Semua Bulan</option>
          <?php for($i = 1; $i <= 12; $i++): ?>
            <option value="<?= $i ?>" <?= ($filter_month == $i) ? 'selected' : '' ?>>
              <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
            </option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="col-lg-3 col-md-4">
        <label class="form-label">Tahun Keluar</label>
        <select name="filter_year" class="form-select">
          <option value="">Semua Tahun</option>
          <?php 
          $currentYear = date('Y');
          for($year = $currentYear - 2; $year <= $currentYear + 1; $year++): 
          ?>
            <option value="<?= $year ?>" <?= ($filter_year == $year) ? 'selected' : '' ?>>
              <?= $year ?>
            </option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="col-lg-6 col-md-12 d-flex align-items-end">
        <button type="submit" class="btn btn-primary me-2">
          🔍 Filter
        </button>
        <a href="replacement.php" class="btn btn-secondary me-3">
          🔄 Reset
        </a>
        <div class="filter-summary">
          <?php if ($filter_month || $filter_year): ?>
            📊 Menampilkan MP keluar pada <?= $filter_month ? date('F', mktime(0, 0, 0, $filter_month, 1)) : 'semua bulan' ?>
            <?= $filter_year ? $filter_year : 'semua tahun' ?> 
            (<?= count($data) ?> record)
          <?php else: ?>
            📊 Menampilkan semua data (<?= count($data) ?> record)
          <?php endif; ?>
        </div>
      </div>
    </form>
  </div>

  <!-- End Contracts Available Section -->
  <?php if (!empty($endContracts)): ?>
  <div class="data-card">
    <div class="card-header">
      📋 End Contracts Tersedia (30 Hari Terakhir)
    </div>
    <div class="card-body p-0">
      <form method="POST" id="autoAddForm">
        <!-- Include current filter parameters -->
        <?php if ($filter_month): ?>
          <input type="hidden" name="filter_month" value="<?= $filter_month ?>">
        <?php endif; ?>
        <?php if ($filter_year): ?>
          <input type="hidden" name="filter_year" value="<?= $filter_year ?>">
        <?php endif; ?>
        
        <div class="p-3">
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead>
                <tr>
                  <th><input type="checkbox" id="selectAll"></th>
                  <th>NPK</th>
                  <th>Nama</th>
                  <th>Date Out</th>
                  <th>Section</th>
                  <th>Line</th>
                  <th>Leader</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($endContracts as $contract): ?>
                <tr>
                  <td><input type="checkbox" name="selected_contracts[]" value="<?= htmlspecialchars($contract['npk']) ?>"></td>
                  <td><strong><?= htmlspecialchars($contract['npk']) ?></strong></td>
                  <td><?= htmlspecialchars($contract['name']) ?></td>
                  <td><?= date('d/m/Y', strtotime($contract['dateOut'])) ?></td>
                  <td><?= htmlspecialchars($contract['section']) ?></td>
                  <td><?= htmlspecialchars($contract['line']) ?></td>
                  <td><?= htmlspecialchars($contract['leader']) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="mt-3">
            <button type="submit" name="add_auto" class="btn btn-success">
              ✅ Tambah Terpilih ke Replacement
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <!-- Main Data Section -->
  <div class="data-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h5 class="mb-0">📊 Data Replacement</h5>
      <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#manualModal">
        ⚡ Tambah Manual (Urgent)
      </button>
    </div>

    <?php if (empty($data)): ?>
      <div class="alert alert-info">
        <strong>ℹ️ Info:</strong> 
        <?php if ($filter_month || $filter_year): ?>
          Tidak ada data replacement untuk MP yang keluar pada periode yang dipilih.
        <?php else: ?>
          Belum ada data replacement.
        <?php endif; ?>
      </div>
    <?php else: ?>
    <div class="table-responsive">
      <table id="replacementTable" class="table">
        <thead>
          <tr>
            <th>NPK Keluar</th>
            <th>Nama Keluar</th>
            <th>Date Out</th>
            <th>NPK Pengganti</th>
            <th>Nama Pengganti</th>
            <th>Section</th>
            <th>Line</th>
            <th>Rekomendasi</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($data as $row): ?>
          <tr>
            <td><strong><?= htmlspecialchars($row['npk_keluar']) ?></strong></td>
            <td><?= htmlspecialchars($row['nama_keluar']) ?></td>
            <td><?= date('d/m/Y', strtotime($row['date_out'])) ?></td>
            <td><?= htmlspecialchars($row['npk_pengganti'] ?? '-') ?></td>
            <td><?= htmlspecialchars($row['nama_pengganti'] ?? '-') ?></td>
            <td><?= htmlspecialchars($row['section']) ?></td>
            <td><?= htmlspecialchars($row['line']) ?></td>
            <td><?= htmlspecialchars($row['rekomendasi'] ?? '-') ?></td>
            <td>
              <?php if ($row['status'] == 'On Time'): ?>
                <span class="badge bg-success"><?= htmlspecialchars($row['status']) ?></span>
              <?php elseif ($row['status'] == 'Delay'): ?>
                <span class="badge bg-warning"><?= htmlspecialchars($row['status']) ?></span>
              <?php else: ?>
                <span class="badge bg-danger"><?= htmlspecialchars($row['status']) ?></span>
              <?php endif; ?>
            </td>
            <td>
              <button class="btn btn-sm btn-primary me-1" onclick="editReplacement(<?= htmlspecialchars(json_encode($row)) ?>)">
                ✏️ Edit
              </button>
              <a href="?delete=<?= $row['id'] ?><?= $filter_month ? '&filter_month=' . $filter_month : '' ?><?= $filter_year ? '&filter_year=' . $filter_year : '' ?>" 
                 class="btn btn-sm btn-danger" 
                 onclick="return confirm('Yakin ingin menghapus data replacement ini?')">
                🗑️ Delete
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Manual Add Modal -->
<div class="modal fade" id="manualModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">⚡ Tambah Replacement Manual</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <!-- Include current filter parameters -->
        <?php if ($filter_month): ?>
          <input type="hidden" name="filter_month" value="<?= $filter_month ?>">
        <?php endif; ?>
        <?php if ($filter_year): ?>
          <input type="hidden" name="filter_year" value="<?= $filter_year ?>">
        <?php endif; ?>
        
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6">
              <h6 class="text-muted mb-3">📤 Data Karyawan Keluar</h6>
              <div class="mb-3">
                <label class="form-label">End Contract</label>
                <select class="form-select" id="endContractSelect" onchange="fillEndContractData()">
                  <option value="">Pilih End Contract</option>
                  <?php 
                  try {
                    $allEndContracts = $conn->query("SELECT * FROM end_contracts ORDER BY dateOut DESC")->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($allEndContracts as $ec): 
                  ?>
                    <option value='<?= htmlspecialchars(json_encode($ec)) ?>'><?= htmlspecialchars($ec['npk']) ?> - <?= htmlspecialchars($ec['name']) ?></option>
                  <?php 
                    endforeach;
                  } catch (Exception $e) {
                    echo '<option value="">Error loading end contracts</option>';
                  }
                  ?>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">NPK Keluar</label>
                <input type="text" class="form-control" name="npk_keluar" id="npk_keluar" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Nama Keluar</label>
                <input type="text" class="form-control" name="nama_keluar" id="nama_keluar" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Date Out</label>
                <input type="date" class="form-control" name="date_out" id="date_out" required>
              </div>
            </div>
            <div class="col-md-6">
              <h6 class="text-muted mb-3">📥 Data Karyawan Pengganti</h6>
              <div class="mb-3">
                <label class="form-label">Employee Database</label>
                <select class="form-select" id="employeeSelect" onchange="fillEmployeeData()">
                  <option value="">Pilih Employee</option>
                  <?php foreach ($employees as $emp): ?>
                    <option value='<?= htmlspecialchars(json_encode($emp)) ?>'><?= htmlspecialchars($emp['NPK']) ?> - <?= htmlspecialchars($emp['Nama']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">NPK Pengganti</label>
                <input type="text" class="form-control" name="npk_pengganti" id="npk_pengganti">
              </div>
              <div class="mb-3">
                <label class="form-label">Nama Pengganti</label>
                <input type="text" class="form-control" name="nama_pengganti" id="nama_pengganti">
              </div>
              <div class="mb-3">
                <label class="form-label">Gender</label>
                <input type="text" class="form-control" name="gender" id="gender" readonly>
              </div>
              <div class="mb-3">
                <label class="form-label">Section</label>
                <input type="text" class="form-control" name="section" id="section" readonly>
              </div>
              <div class="mb-3">
                <label class="form-label">Line</label>
                <input type="text" class="form-control" name="line" id="line" readonly>
              </div>
              <div class="mb-3">
                <label class="form-label">Leader</label>
                <input type="text" class="form-control" name="leader" id="leader" readonly>
              </div>
              <div class="mb-3">
                <label class="form-label">Date In</label>
                <input type="date" class="form-control" name="date_in" id="date_in" readonly>
              </div>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Rekomendasi</label>
            <textarea class="form-control" name="rekomendasi" rows="3" placeholder="Masukkan rekomendasi untuk replacement ini..."></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Status</label>
            <select class="form-select" name="status" required>
              <option value="">Pilih Status</option>
              <option value="On Time">On Time</option>
              <option value="Delay">Delay</option>
              <option value="Over">Over</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">❌ Batal</button>
          <button type="submit" name="add_manual" class="btn btn-primary">💾 Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">✏️ Edit Replacement</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <!-- Include current filter parameters -->
        <?php if ($filter_month): ?>
          <input type="hidden" name="filter_month" value="<?= $filter_month ?>">
        <?php endif; ?>
        <?php if ($filter_year): ?>
          <input type="hidden" name="filter_year" value="<?= $filter_year ?>">
        <?php endif; ?>
        
        <div class="modal-body">
          <input type="hidden" name="id" id="edit_id">
          <div class="mb-3">
            <label class="form-label">Employee Database</label>
            <select class="form-select" id="editEmployeeSelect" onchange="fillEditEmployeeData()">
              <option value="">Pilih Employee</option>
              <?php foreach ($employees as $emp): ?>
                <option value='<?= htmlspecialchars(json_encode($emp)) ?>'><?= htmlspecialchars($emp['NPK']) ?> - <?= htmlspecialchars($emp['Nama']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">NPK Pengganti</label>
            <input type="text" class="form-control" name="npk_pengganti" id="edit_npk_pengganti">
          </div>
          <div class="mb-3">
            <label class="form-label">Nama Pengganti</label>
            <input type="text" class="form-control" name="nama_pengganti" id="edit_nama_pengganti">
          </div>
          <div class="mb-3">
            <label class="form-label">Gender</label>
            <input type="text" class="form-control" name="gender" id="edit_gender" readonly>
          </div>
          <div class="mb-3">
            <label class="form-label">Date In</label>
            <input type="date" class="form-control" name="date_in" id="edit_date_in" readonly>
          </div>
          <div class="mb-3">
            <label class="form-label">Rekomendasi</label>
            <textarea class="form-control" name="rekomendasi" id="edit_rekomendasi" rows="3"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Status</label>
            <select class="form-select" name="status" id="edit_status" required>
              <option value="">Pilih Status</option>
              <option value="On Time">On Time</option>
              <option value="Delay">Delay</option>
              <option value="Over">Over</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">❌ Batal</button>
          <button type="submit" name="edit" class="btn btn-primary">💾 Update</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
  <?php if (!empty($data)): ?>
  $('#replacementTable').DataTable({
    language: {
      url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
    },
    responsive: true,
    pageLength: 25,
    order: [[2, 'desc']]
  });
  <?php endif; ?>
  
  // Select all checkbox
  $('#selectAll').change(function() {
    $('input[name="selected_contracts[]"]').prop('checked', this.checked);
  });
  
  // Clear form when modals are hidden
  $('#manualModal').on('hidden.bs.modal', function () {
    this.querySelector('form').reset();
  });
  
  $('#editModal').on('hidden.bs.modal', function () {
    this.querySelector('form').reset();
  });
});

function fillEndContractData() {
  const select = document.getElementById('endContractSelect');
  if (!select.value) {
    document.getElementById('npk_keluar').value = '';
    document.getElementById('nama_keluar').value = '';
    document.getElementById('date_out').value = '';
    return;
  }
  
  try {
    const data = JSON.parse(select.value);
    document.getElementById('npk_keluar').value = data.npk || '';
    document.getElementById('nama_keluar').value = data.name || '';
    document.getElementById('date_out').value = data.dateOut || '';
  } catch (e) {
    console.error('Error parsing end contract data:', e);
  }
}

function fillEmployeeData() {
  const select = document.getElementById('employeeSelect');
  if (!select.value) {
    document.getElementById('npk_pengganti').value = '';
    document.getElementById('nama_pengganti').value = '';
    document.getElementById('gender').value = '';
    document.getElementById('section').value = '';
    document.getElementById('line').value = '';
    document.getElementById('leader').value = '';
    document.getElementById('date_in').value = '';
    return;
  }
  
  try {
    const data = JSON.parse(select.value);
    document.getElementById('npk_pengganti').value = data.NPK || '';
    document.getElementById('nama_pengganti').value = data.Nama || '';
    document.getElementById('gender').value = data.Gender || '';
    document.getElementById('section').value = data.Section || '';
    document.getElementById('line').value = data.Line || '';
    document.getElementById('leader').value = data.Leader || '';
    document.getElementById('date_in').value = data.DateIn || '';
  } catch (e) {
    console.error('Error parsing employee data:', e);
  }
}

function fillEditEmployeeData() {
  const select = document.getElementById('editEmployeeSelect');
  if (!select.value) {
    document.getElementById('edit_npk_pengganti').value = '';
    document.getElementById('edit_nama_pengganti').value = '';
    document.getElementById('edit_gender').value = '';
    document.getElementById('edit_date_in').value = '';
    return;
  }
  
  try {
    const data = JSON.parse(select.value);
    document.getElementById('edit_npk_pengganti').value = data.NPK || '';
    document.getElementById('edit_nama_pengganti').value = data.Nama || '';
    document.getElementById('edit_gender').value = data.Gender || '';
    document.getElementById('edit_date_in').value = data.DateIn || '';
  } catch (e) {
    console.error('Error parsing employee data:', e);
  }
}

function editReplacement(data) {
  try {
    document.getElementById('edit_id').value = data.id || '';
    document.getElementById('edit_npk_pengganti').value = data.npk_pengganti || '';
    document.getElementById('edit_nama_pengganti').value = data.nama_pengganti || '';
    document.getElementById('edit_gender').value = data.gender || '';
    document.getElementById('edit_date_in').value = data.date_in || '';
    document.getElementById('edit_rekomendasi').value = data.rekomendasi || '';
    document.getElementById('edit_status').value = data.status || '';
    
    new bootstrap.Modal(document.getElementById('editModal')).show();
  } catch (e) {
    console.error('Error filling edit form:', e);
    alert('Terjadi kesalahan saat mengisi form edit');
  }
}

// Debug filter - berdasarkan date_out
console.log('Current filters (based on date_out):', {
  month: <?= json_encode($filter_month) ?>,
  year: <?= json_encode($filter_year) ?>,
  recordCount: <?= count($data) ?>
});
</script>
</body>
</html>