<?php
require_once 'auth_check.php';
require_once '../backend/database.php';

$db = new Database();
$conn = $db->getConnection();

// Handle date filters
$whereClause = "";
$params = [];

if (isset($_GET['filter_month']) && !empty($_GET['filter_month'])) {
    $whereClause .= " AND MONTH(ec.dateOut) = :month";
    $params[':month'] = $_GET['filter_month'];
}

if (isset($_GET['filter_year']) && !empty($_GET['filter_year'])) {
    $whereClause .= " AND YEAR(ec.dateOut) = :year";
    $params[':year'] = $_GET['filter_year'];
}

// Add mapping filters
if (isset($_GET['filter_section']) && !empty($_GET['filter_section'])) {
    $whereClause .= " AND ec.section = :section";
    $params[':section'] = $_GET['filter_section'];
}

if (isset($_GET['filter_line']) && !empty($_GET['filter_line'])) {
    $whereClause .= " AND ec.line = :line";
    $params[':line'] = $_GET['filter_line'];
}

// Get unique sections and lines for filter options
$sectionsStmt = $conn->query("SELECT DISTINCT section FROM end_contracts ORDER BY section");
$sections = $sectionsStmt->fetchAll(PDO::FETCH_COLUMN);

$linesStmt = $conn->query("SELECT DISTINCT line FROM end_contracts ORDER BY line");
$lines = $linesStmt->fetchAll(PDO::FETCH_COLUMN);

// Ambil semua karyawan kontrak aktif yang belum ada di end_contracts
$employeeStmt = $conn->query("
    SELECT e.* FROM employees e 
    WHERE e.Tipe = 'Kontrak' 
    AND e.Status = 'Aktif' 
    AND e.NPK NOT IN (SELECT npk FROM end_contracts)
    ORDER BY e.Section, e.Line, e.NPK
");
$availableEmployees = $employeeStmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil semua karyawan untuk tambah manual (urgent cases)
$allEmployeesStmt = $conn->query("SELECT * FROM employees WHERE Status = 'Aktif' ORDER BY NPK ASC");
$allEmployees = $allEmployeesStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_from_list'])) {
        // Proses multiple selection dari daftar otomatis
        if (!empty($_POST['selected_employees'])) {
            foreach ($_POST['selected_employees'] as $npk) {
                $emp = array_filter($availableEmployees, function($e) use ($npk) {
                    return $e['NPK'] == $npk;
                });
                $emp = reset($emp);
                
                if ($emp) {
                    // Hitung dateOut berdasarkan durasi
                    $durasi = (int)$emp['Durasi'];
                    $dateIn = new DateTime($emp['DateIn']);
                    $dateOut = clone $dateIn;
                    $dateOut->add(new DateInterval('P' . $durasi . 'M'));
                    
                    $sql = "INSERT INTO end_contracts 
                        (`npk`, `name`, `gender`, `section`, `line`, `leader`, `dateIn`, `dateOut`, `durasi`, `reason`) 
                        VALUES (:npk, :name, :gender, :section, :line, :leader, :dateIn, :dateOut, :durasi, :reason)";

                    $stmt = $conn->prepare($sql);
                    $stmt->execute([
                        ':npk' => $emp['NPK'],
                        ':name' => $emp['Nama'],
                        ':gender' => $emp['Gender'],
                        ':section' => $emp['Section'],
                        ':line' => $emp['Line'],
                        ':leader' => $emp['Leader'],
                        ':dateIn' => $emp['DateIn'],
                        ':dateOut' => $dateOut->format('Y-m-d'),
                        ':durasi' => $emp['Durasi'],
                        ':reason' => $_POST['reason']
                    ]);
                }
            }
        }
        header("Location: end_contracts.php?success=1");
        exit();
    }
    
    // Existing manual add code...
    if (isset($_POST['add'])) {
        $sql = "INSERT INTO end_contracts 
            (`npk`, `name`, `gender`, `section`, `line`, `leader`, `dateIn`, `dateOut`, `durasi`, `reason`) 
            VALUES (:npk, :name, :gender, :section, :line, :leader, :dateIn, :dateOut, :durasi, :reason)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':npk' => $_POST['npk'],
            ':name' => $_POST['name'],
            ':gender' => $_POST['gender'],
            ':section' => $_POST['section'],
            ':line' => $_POST['line'],
            ':leader' => $_POST['leader'],
            ':dateIn' => $_POST['dateIn'],
            ':dateOut' => $_POST['dateOut'],
            ':durasi' => !empty($_POST['durasi']) ? (int)$_POST['durasi'] : 0,
            ':reason' => $_POST['reason']
        ]);

        header("Location: end_contracts.php?success=1");
        exit();
    }
    
    // Edit functionality...
    if (isset($_POST['edit'])) {
        $sql = "UPDATE end_contracts SET 
            `npk` = :npk, `name` = :name, `gender` = :gender, `section` = :section, 
            `line` = :line, `leader` = :leader, `dateIn` = :dateIn, `dateOut` = :dateOut, 
            `durasi` = :durasi, `reason` = :reason 
            WHERE id = :id";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':id' => $_POST['id'],
            ':npk' => $_POST['npk'],
            ':name' => $_POST['name'],
            ':gender' => $_POST['gender'],
            ':section' => $_POST['section'],
            ':line' => $_POST['line'],
            ':leader' => $_POST['leader'],
            ':dateIn' => $_POST['dateIn'],
            ':dateOut' => $_POST['dateOut'],
            ':durasi' => $_POST['durasi'],
            ':reason' => $_POST['reason']
        ]);

        header("Location: end_contracts.php?updated=1");
        exit();
    }
}

if (isset($_GET['delete'])) {
    $stmt = $conn->prepare("DELETE FROM end_contracts WHERE id = :id");
    $stmt->execute([':id' => $_GET['delete']]);
    header("Location: end_contracts.php?deleted=1");
    exit();
}

// Get filtered data
$sql = "SELECT * FROM end_contracts ec WHERE 1=1" . $whereClause . " ORDER BY ec.dateOut DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>End Contract - MP Development</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
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
    
    .table-dark th {
      background: #1e293b !important;
      color: white !important;
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
    
    .action-buttons {
      display: flex;
      gap: 10px;
      align-items: center;
      margin-bottom: 20px;
    }
    
    .status-badge {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 8px 16px;
      border-radius: 8px;
      font-size: 0.875rem;
      font-weight: 500;
    }
    
    .status-success {
      background: #d1fae5;
      color: #065f46;
      border: 1px solid #a7f3d0;
    }
    
    .status-warning {
      background: #fef3c7;
      color: #92400e;
      border: 1px solid #fde68a;
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
      
      .action-buttons {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
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
    📅 End Contract Management
  </div>

  <!-- Alerts -->
  <?php if (isset($_GET['success'])): ?>
    <?php if ($_GET['success'] == '1'): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        ✅ Data end contract berhasil ditambahkan!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>
  <?php endif; ?>
  
  <?php if (isset($_GET['updated'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      ✅ Data berhasil diupdate!
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>
  
  <?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      ✅ Data berhasil dihapus!
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <!-- Info Section -->
  <div class="info-card">
    <h6 class="mb-3">📋 Informasi Sistem</h6>
    <div class="row g-3">
      <div class="col-lg-6">
        <div class="status-badge status-success">
          <span>📋</span>
          <strong>Proses Otomatis:</strong> Pilih dari daftar karyawan kontrak yang akan berakhir
        </div>
      </div>
      <div class="col-lg-6">
        <div class="status-badge status-warning">
          <span>⚡</span>
          <strong>Tambah Manual:</strong> Untuk kasus urgent/khusus (resign, mutasi, dll)
        </div>
      </div>
    </div>
  </div>

  <!-- Filter Section -->
  <div class="filter-card">
    <div class="filter-title">🔍 Filter Data</div>
    <form method="GET" class="row g-3">
      <div class="col-lg-2 col-md-3">
        <label class="form-label">Bulan</label>
        <select name="filter_month" class="form-select">
          <option value="">Semua Bulan</option>
          <?php for($i = 1; $i <= 12; $i++): ?>
            <option value="<?= $i ?>" <?= (isset($_GET['filter_month']) && $_GET['filter_month'] == $i) ? 'selected' : '' ?>>
              <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
            </option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="col-lg-2 col-md-3">
        <label class="form-label">Tahun</label>
        <select name="filter_year" class="form-select">
          <option value="">Semua Tahun</option>
          <?php 
          $currentYear = date('Y');
          for($year = $currentYear - 2; $year <= $currentYear + 2; $year++): 
          ?>
            <option value="<?= $year ?>" <?= (isset($_GET['filter_year']) && $_GET['filter_year'] == $year) ? 'selected' : '' ?>>
              <?= $year ?>
            </option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="col-lg-2 col-md-3">
        <label class="form-label">Section</label>
        <select name="filter_section" class="form-select">
          <option value="">Semua Section</option>
          <?php foreach($sections as $section): ?>
            <option value="<?= $section ?>" <?= (isset($_GET['filter_section']) && $_GET['filter_section'] == $section) ? 'selected' : '' ?>>
              <?= $section ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-lg-2 col-md-3">
        <label class="form-label">Line</label>
        <select name="filter_line" class="form-select">
          <option value="">Semua Line</option>
          <?php foreach($lines as $line): ?>
            <option value="<?= $line ?>" <?= (isset($_GET['filter_line']) && $_GET['filter_line'] == $line) ? 'selected' : '' ?>>
              <?= $line ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-lg-4 col-md-12 d-flex align-items-end">
        <button type="submit" class="btn btn-primary me-2">🔍 Filter</button>
        <a href="end_contracts.php" class="btn btn-secondary">🔄 Reset</a>
      </div>
    </form>
  </div>

  <!-- Action Buttons -->
  <div class="data-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h5 class="mb-0">📋 Data End Contract</h5>
      <div class="action-buttons">
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#autoAddModal">
          📋 Proses Otomatis
        </button>
        <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#manualModal" onclick="resetForm()">
          ⚡ Tambah Manual
        </button>
      </div>
    </div>

    <!-- Tabel Karyawan Kontrak yang Tersedia -->
    <?php if (!empty($availableEmployees)): ?>
    <div class="alert alert-info mb-4">
      <h6 class="mb-3">👥 Karyawan Kontrak Aktif (<?= count($availableEmployees) ?> orang)</h6>
      <div class="table-responsive">
        <table class="table table-sm">
          <thead>
            <tr>
              <th>NPK</th>
              <th>Nama</th>
              <th>Section</th>
              <th>Line</th>
              <th>Leader</th>
              <th>Date In</th>
              <th>Durasi</th>
              <th>Perkiraan End</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (array_slice($availableEmployees, 0, 5) as $emp): 
              $durasi = (int)$emp['Durasi'];
              $dateIn = new DateTime($emp['DateIn']);
              $estimatedEnd = clone $dateIn;
              $estimatedEnd->add(new DateInterval('P' . $durasi . 'M'));
            ?>
            <tr>
              <td><strong><?= $emp['NPK'] ?></strong></td>
              <td><?= $emp['Nama'] ?></td>
              <td><?= $emp['Section'] ?></td>
              <td><?= $emp['Line'] ?></td>
              <td><?= $emp['Leader'] ?></td>
              <td><?= date('d/m/Y', strtotime($emp['DateIn'])) ?></td>
              <td><?= $emp['Durasi'] ?> bulan</td>
              <td><?= $estimatedEnd->format('d/m/Y') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (count($availableEmployees) > 5): ?>
            <tr>
              <td colspan="8" class="text-center text-muted">
                <small>... dan <?= count($availableEmployees) - 5 ?> karyawan lainnya</small>
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- Main Data Table -->
    <div class="table-responsive">
      <table id="endContractTable" class="table">
        <thead class="table-dark">
          <tr>
            <th>NPK</th>
            <th>Nama</th>
            <th>Gender</th>
            <th>Section</th>
            <th>Line</th>
            <th>Leader</th>
            <th>Date In</th>
            <th>Date Out</th>
            <th>Durasi</th>
            <th>Reason</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($data as $row): ?>
          <tr>
            <td><strong><?= htmlspecialchars($row['npk']) ?></strong></td>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <td>
              <span class="badge <?= $row['gender'] == 'Pria' ? 'bg-primary' : 'bg-info' ?>">
                <?= htmlspecialchars($row['gender']) ?>
              </span>
            </td>
            <td><?= htmlspecialchars($row['section']) ?></td>
            <td><?= htmlspecialchars($row['line']) ?></td>
            <td><?= htmlspecialchars($row['leader']) ?></td>
            <td><?= date('d/m/Y', strtotime($row['dateIn'])) ?></td>
            <td><?= date('d/m/Y', strtotime($row['dateOut'])) ?></td>
            <td><?= htmlspecialchars($row['durasi']) ?> bulan</td>
            <td>
              <span class="badge bg-secondary">
                <?= htmlspecialchars($row['reason']) ?>
              </span>
            </td>
            <td>
              <button class="btn btn-sm btn-warning me-1" 
                      onclick="editData(<?= htmlspecialchars(json_encode($row)) ?>)" 
                      data-bs-toggle="modal" data-bs-target="#manualModal">
                ✏️ Edit
              </button>
              <a href="?delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger" 
                 onclick="return confirm('Yakin ingin menghapus data <?= htmlspecialchars($row['name']) ?>?')">
                🗑️ Delete
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal Proses Otomatis -->
<div class="modal fade" id="autoAddModal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header">
          <h5 class="modal-title">📋 Proses End Contract Otomatis</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <?php if (!empty($availableEmployees)): ?>
          <div class="row">
            <div class="col-md-8">
              <label class="form-label">Pilih Karyawan yang akan End Contract:</label>
              <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                <table class="table table-sm table-striped">
                  <thead class="sticky-top bg-light">
                    <tr>
                      <th><input type="checkbox" id="selectAll" onchange="toggleAll()"></th>
                      <th>NPK</th>
                      <th>Nama</th>
                      <th>Section</th>
                      <th>Line</th>
                      <th>Perkiraan End</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($availableEmployees as $emp): 
                      $durasi = (int)$emp['Durasi'];
                      $dateIn = new DateTime($emp['DateIn']);
                      $estimatedEnd = clone $dateIn;
                      $estimatedEnd->add(new DateInterval('P' . $durasi . 'M'));
                    ?>
                    <tr>
                      <td><input type="checkbox" name="selected_employees[]" value="<?= $emp['NPK'] ?>" class="employee-checkbox"></td>
                      <td><?= $emp['NPK'] ?></td>
                      <td><?= $emp['Nama'] ?></td>
                      <td><?= $emp['Section'] ?></td>
                      <td><?= $emp['Line'] ?></td>
                      <td><?= $estimatedEnd->format('d/m/Y') ?></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Reason untuk semua yang dipilih:</label>
              <select name="reason" class="form-select" required>
                <option value="">Pilih Alasan</option>
                <option>Kontrak Habis</option>
                <option>Resign</option>
                <option>Mutasi</option>
                <option>Pensiun</option>
              </select>
            </div>
          </div>
          <?php else: ?>
          <div class="alert alert-info">
            Tidak ada karyawan kontrak yang tersedia untuk diproses.
          </div>
          <?php endif; ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">❌ Batal</button>
          <?php if (!empty($availableEmployees)): ?>
          <button type="submit" name="add_from_list" class="btn btn-success">✅ Proses yang Dipilih</button>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Manual (Urgent) -->
<div class="modal fade" id="manualModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" id="endContractForm">
        <input type="hidden" name="id" id="editId">
        <div class="modal-header">
          <h5 class="modal-title" id="modalTitle">⚡ Tambah Manual (Urgent)</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">NPK</label>
              <select name="npk" id="npkSelect" class="form-select" onchange="autofillEmployeeData()" required>
                <option value="">Pilih NPK</option>
                <?php foreach ($allEmployees as $e): ?>
                <option value="<?= $e['NPK'] ?>"><?= $e['NPK'] ?> - <?= $e['Nama'] ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Nama</label>
              <input name="name" class="form-control" readonly>
            </div>
            <div class="col-md-4">
              <label class="form-label">Gender</label>
              <input name="gender" class="form-control" readonly>
            </div>
            <div class="col-md-3">
              <label class="form-label">Section</label>
              <input name="section" class="form-control" readonly>
            </div>
            <div class="col-md-3">
              <label class="form-label">Line</label>
              <input name="line" class="form-control" readonly>
            </div>
            <div class="col-md-3">
              <label class="form-label">Leader</label>
              <input name="leader" class="form-control" readonly>
            </div>
            <div class="col-md-3">
              <label class="form-label">Durasi</label>
              <input name="durasi" class="form-control" readonly>
            </div>
            <div class="col-md-4">
              <label class="form-label">Date In</label>
              <input name="dateIn" class="form-control" readonly>
            </div>
            <div class="col-md-4">
              <label class="form-label">Date Out</label>
              <input name="dateOut" class="form-control" type="date" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Reason</label>
              <select name="reason" class="form-select" required>
                <option value="">Pilih Alasan</option>
                <option>Kontrak Habis</option>
                <option>Resign</option>
                <option>Mutasi</option>
                <option>Pensiun</option>
                <option>PHK</option>
                <option>Lainnya</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">❌ Batal</button>
          <button type="submit" name="add" id="submitBtn" class="btn btn-warning">💾 Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
const allEmployees = <?= json_encode($allEmployees) ?>;

function toggleAll() {
  const selectAll = document.getElementById('selectAll');
  const checkboxes = document.querySelectorAll('.employee-checkbox');
  checkboxes.forEach(cb => cb.checked = selectAll.checked);
}

function autofillEmployeeData() {
  const npk = document.querySelector('[name=npk]').value;
  const emp = allEmployees.find(e => e.NPK == npk);
  if (!emp) return;
  
  document.querySelector('[name=name]').value = emp.Nama || '';
  document.querySelector('[name=gender]').value = emp.Gender || '';
  document.querySelector('[name=section]').value = emp.Section || '';
  document.querySelector('[name=line]').value = emp.Line || '';
  document.querySelector('[name=leader]').value = emp.Leader || '';
  document.querySelector('[name=dateIn]').value = emp.DateIn || '';
  document.querySelector('[name=durasi]').value = emp.Durasi || '';
}

function resetForm() {
  document.getElementById('endContractForm').reset();
  document.getElementById('editId').value = '';
  document.getElementById('modalTitle').textContent = '⚡ Tambah Manual (Urgent)';
  document.getElementById('submitBtn').name = 'add';
  document.getElementById('submitBtn').textContent = '💾 Simpan';
}

function editData(data) {
  document.getElementById('editId').value = data.id;
  document.querySelector('[name=npk]').value = data.npk;
  document.querySelector('[name=name]').value = data.name;
  document.querySelector('[name=gender]').value = data.gender;
  document.querySelector('[name=section]').value = data.section;
  document.querySelector('[name=line]').value = data.line;
  document.querySelector('[name=leader]').value = data.leader;
  document.querySelector('[name=dateIn]').value = data.dateIn;
  document.querySelector('[name=dateOut]').value = data.dateOut;
  document.querySelector('[name=durasi]').value = data.durasi;
  document.querySelector('[name=reason]').value = data.reason;
  
  document.getElementById('modalTitle').textContent = '✏️ Edit End Contract';
  document.getElementById('submitBtn').name = 'edit';
  document.getElementById('submitBtn').textContent = '💾 Update';
}

// Initialize DataTable and Select2
$(document).ready(function() {
  $('#endContractTable').DataTable({
    responsive: true,
    language: {
      url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
    },
    pageLength: 25,
    order: [[7, 'desc']]
  });
});

// Initialize Select2 when modal is shown
$('#manualModal').on('shown.bs.modal', function () {
  $('#npkSelect').select2({
    theme: 'bootstrap-5',
    dropdownParent: $('#manualModal'),
    placeholder: 'Cari NPK atau Nama...',
    allowClear: true,
    width: '100%'
  });
});

// Destroy Select2 when modal is hidden to prevent conflicts
$('#manualModal').on('hidden.bs.modal', function () {
  if ($('#npkSelect').hasClass('select2-hidden-accessible')) {
    $('#npkSelect').select2('destroy');
  }
});
</script>
</body>
</html>