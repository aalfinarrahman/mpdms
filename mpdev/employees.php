<?php
require_once '../backend/database.php';

$db = new Database();
$conn = $db->getConnection();

// Handle date filters
$whereClause = "";
$params = [];

if (isset($_GET['filter_month']) && !empty($_GET['filter_month'])) {
    $whereClause .= " AND MONTH(DateIn) = :month";
    $params[':month'] = $_GET['filter_month'];
}

if (isset($_GET['filter_year']) && !empty($_GET['filter_year'])) {
    $whereClause .= " AND YEAR(DateIn) = :year";
    $params[':year'] = $_GET['filter_year'];
}

$sql = "SELECT * FROM employees WHERE 1=1" . $whereClause . " ORDER BY NPK ASC";
$employeeStmt = $conn->prepare($sql);
$employeeStmt->execute($params);
$employees = $employeeStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if employee already exists
    $checkStmt = $conn->prepare("SELECT NPK FROM employees WHERE NPK = ?");
    $checkStmt->execute([$_POST['npk']]);
    $existingEmployee = $checkStmt->fetch();
    
    if ($existingEmployee || isset($_POST['edit'])) {
        // Update existing employee
        $sql = "UPDATE employees SET 
            `Nama` = :name, `Gender` = :gender, `Section` = :section, `Line` = :line, `Leader` = :leader, 
            `DateIn` = :dateIn, `Status` = :status, `Function` = :functionRole, `Tipe` = :tipe, `Durasi` = :durasi
            WHERE `NPK` = :npk";
    } else {
        // Insert new employee
        $sql = "INSERT INTO employees 
            (`NPK`, `Nama`, `Gender`, `Section`, `Line`, `Leader`, `DateIn`, `Status`, `Function`, `Tipe`, `Durasi`) 
            VALUES (:npk, :name, :gender, :section, :line, :leader, :dateIn, :status, :functionRole, :tipe, :durasi)";
    }
    
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':npk' => $_POST['npk'],
            ':name' => $_POST['name'],
            ':gender' => $_POST['gender'],
            ':section' => $_POST['section'],
            ':line' => $_POST['line'],
            ':leader' => $_POST['leader'],
            ':dateIn' => $_POST['dateIn'],
            ':status' => $_POST['status'],
            ':functionRole' => $_POST['functionrole'],
            ':tipe' => $_POST['tipe'],
            ':durasi' => ($_POST['tipe'] === 'Kontrak') ? $_POST['durasi'] : null
        ]);
        
        $message = $existingEmployee ? "Data karyawan berhasil diupdate!" : "Karyawan baru berhasil ditambahkan!";
        echo "<script>alert('$message'); window.location.href='employees.php';</script>";
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            echo "<script>alert('Error: NPK sudah ada di database!'); window.history.back();</script>";
        } else {
            echo "<script>alert('Error: " . $e->getMessage() . "'); window.history.back();</script>";
        }
    }
}

if (isset($_GET['delete'])) {
    $stmt = $conn->prepare("DELETE FROM employees WHERE `NPK` = :npk");
    $stmt->execute([':npk' => $_GET['delete']]);
    header("Location: employees.php?deleted=1");
    exit();
}

$sql = "SELECT * FROM employees WHERE 1=1" . $whereClause . " ORDER BY NPK DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Database MP - MP Development</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css" rel="stylesheet">
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
    
    .filter-card {
      background: white;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 20px;
      margin-bottom: 20px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .filter-title {
      font-size: 1.1rem;
      font-weight: 600;
      color: #1e293b;
      margin-bottom: 15px;
    }
    
    .data-card {
      background: white;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 25px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      transition: all 0.3s ease;
    }
    
    .data-card:hover {
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
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
    
    .btn-secondary {
      background: #6b7280;
      border-color: #6b7280;
      padding: 10px 20px;
      border-radius: 8px;
      font-weight: 500;
    }
    
    .btn-warning {
      background: #f59e0b;
      border-color: #f59e0b;
      color: white;
    }
    
    .btn-warning:hover {
      background: #d97706;
      border-color: #d97706;
      color: white;
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
    
    .bg-info {
      background-color: #06b6d4 !important;
      color: white !important;
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
        padding: 20px;
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
    👥 Database MP
  </div>

  <!-- Alerts -->
  <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      ✅ Data berhasil disimpan!
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php elseif (isset($_GET['deleted'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      🗑️ Data berhasil dihapus!
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <!-- Filter Section -->
  <div class="filter-card">
    <div class="filter-title">🔍 Filter Data</div>
    <form method="GET" class="row g-3">
      <div class="col-lg-3 col-md-4">
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
      <div class="col-lg-3 col-md-4">
        <label class="form-label">Tahun</label>
        <select name="filter_year" class="form-select">
          <option value="">Semua Tahun</option>
          <?php 
          $currentYear = date('Y');
          for($year = $currentYear - 5; $year <= $currentYear + 1; $year++): 
          ?>
            <option value="<?= $year ?>" <?= (isset($_GET['filter_year']) && $_GET['filter_year'] == $year) ? 'selected' : '' ?>>
              <?= $year ?>
            </option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="col-lg-3 col-md-4 d-flex align-items-end">
        <button type="submit" class="btn btn-primary me-2">🔍 Filter</button>
        <a href="employees.php" class="btn btn-secondary">🔄 Reset</a>
      </div>
    </form>
  </div>

  <!-- Data Section -->
  <div class="data-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h5 class="mb-0">📋 Data Karyawan</h5>
      <button class="btn btn-primary" id="btnTambah">
        ➕ Tambah Karyawan
      </button>
    </div>

    <div class="table-responsive">
      <table id="employeeTable" class="table table-hover">
        <thead>
          <tr>
            <th>NPK</th>
            <th>Nama</th>
            <th>Gender</th>
            <th>Section</th>
            <th>Line</th>
            <th>Leader</th>
            <th>Date In</th>
            <th>Status</th>
            <th>Tipe</th>
            <th>Function</th>
            <th>Durasi</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($data as $row): ?>
          <tr>
            <td><strong><?= $row['NPK'] ?></strong></td>
            <td><?= $row['Nama'] ?></td>
            <td>
              <span class="badge <?= $row['Gender'] == 'Pria' ? 'bg-primary' : 'bg-info' ?>">
                <?= $row['Gender'] ?>
              </span>
            </td>
            <td><?= $row['Section'] ?></td>
            <td><?= $row['Line'] ?></td>
            <td><?= $row['Leader'] ?></td>
            <td><?= date('d/m/Y', strtotime($row['DateIn'])) ?></td>
            <td>
              <span class="badge <?= $row['Status'] == 'Aktif' ? 'bg-success' : 'bg-secondary' ?>">
                <?= $row['Status'] ?>
              </span>
            </td>
            <td>
              <span class="badge bg-info">
                <?= $row['Tipe'] ?>
              </span>
            </td>
            <td><?= $row['Function'] ?></td>
            <td><?= $row['Durasi'] ? $row['Durasi'] . ' bulan' : '-' ?></td>
            <td>
              <button class="btn btn-sm btn-warning me-1" data-bs-toggle="modal" data-bs-target="#formModal" data-emp='<?= json_encode($row) ?>'>
                ✏️ Edit
              </button>
              <a href="?delete=<?= $row['NPK'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin ingin menghapus data <?= $row['Nama'] ?>?')">
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

<!-- Modal Form -->
<div class="modal fade" id="formModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header">
          <h5 class="modal-title">📝 Form Data MP</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">NPK</label>
              <input type="number" name="npk" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Nama</label>
              <input type="text" name="name" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Gender</label>
              <select name="gender" class="form-select" required>
                <option value="">Pilih Gender</option>
                <option value="Pria">Pria</option>
                <option value="Wanita">Wanita</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Section</label>
              <select name="section" class="form-select" required>
                <option value="">Pilih Section</option>
                <option value="Comp Assy">Comp Assy</option>
                <option value="Comp WClutch">Comp WClutch</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Line</label>
              <select name="line" class="form-select" required>
                <option value="">Pilih Line</option>
                <option value="1A">1A</option>
                <option value="1B">1B</option>
                <option value="2A">2A</option>
                <option value="2B">2B</option>
                <option value="3">3</option>
                <option value="4A">4A</option>
                <option value="4B">4B</option>
                <option value="Supporting">Supporting</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Leader</label>
              <select name="leader" class="form-select" required>
                <option value="">Pilih Leader</option>
                <option value="Agus S">Agus S</option>
                <option value="Isya A">Isya A</option>
                <option value="Bogar B">Bogar B</option>
                <option value="Ramdan S">Ramdan S</option>
                <option value="Sudarno">Sudarno</option>
                <option value="Romadiyanto">Romadiyanto</option>
                <option value="Doni M">Doni M</option>
                <option value="Momon A">Momon A</option>
                <option value="Ujang D">Ujang D</option>
                <option value="Daris P">Daris P</option>
                <option value="Ahmad H">Ahmad H</option>
                <option value="Maman K">Maman K</option>
                <option value="Wahyudi">Wahyudi</option>
                <option value="Asep M">Asep M</option>
                <option value="Bagus A">Bagus A</option>
                <option value="Eko B">Eko B</option>
                <option value="Winanda">Winanda</option>
                <option value="Kirwanto">Kirwanto</option>
                <option value="Aldi PH">Aldi PH</option>
                <option value="Wendra S">Wendra S</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Date In</label>
              <input type="date" name="dateIn" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Status</label>
              <select name="status" class="form-select" required>
                <option value="">Pilih Status</option>
                <option value="Aktif">Aktif</option>
                <option value="Nonaktif">Nonaktif</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Function</label>
              <select name="functionrole" class="form-select" required>
                <option value="">Pilih Function</option>
                <option value="Operator">Operator</option>
                <option value="Mizusumashi">Mizusumashi</option>
                <option value="Inspection">Inspection</option>
                <option value="Repair">Repair</option>
                <option value="Leader">Leader</option>
                <option value="Foreman">Foreman</option>
                <option value="Supervisor">Supervisor</option>
                <option value="Einstaller">Einstaller</option>
                <option value="MP Development">MP Development</option>
                <option value="Supporting">Supporting</option>
                <option value="Buffer MP">Buffer MP</option>
                <option value="Release Oli">Release Oli</option>
                <option value="Prepare Bracket">Prepare Bracket</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Tipe</label>
              <select name="tipe" class="form-select" required onchange="toggleDurasi(this.value)">
                <option value="">Pilih Tipe</option>
                <option value="Tetap">Tetap</option>
                <option value="Kontrak">Kontrak</option>
              </select>
            </div>
            <div class="col-md-4" id="durasiField" style="display:none">
              <label class="form-label">Durasi (bulan)</label>
              <select name="durasi" class="form-select">
                <option value="">Pilih Durasi</option>
                <option value="12">12</option>
                <option value="24">24</option>
                <option value="36">36</option>
                <option value="48">48</option>
                <option value="60">60</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">❌ Batal</button>
          <button type="submit" class="btn btn-primary">💾 Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
  $('#employeeTable').DataTable({
    responsive: true,
    language: {
      url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
    },
    pageLength: 25,
    order: [[0, 'desc']]
  });
});

document.addEventListener('DOMContentLoaded', function () {
  const formModal = new bootstrap.Modal(document.getElementById('formModal'));
  const form = document.querySelector('#formModal form');

  // Tambah button handler
  document.querySelector('#btnTambah')?.addEventListener('click', () => {
    form.reset();
    form.npk.readOnly = false;
    document.getElementById('durasiField').style.display = 'none';
    const editInput = form.querySelector('[name=edit]');
    if (editInput) editInput.remove();
    document.querySelector('.modal-title').textContent = '➕ Tambah Data MP';
    formModal.show();
  });

  // Edit button handlers
  document.querySelectorAll('button[data-emp]').forEach(button => {
    button.addEventListener('click', () => {
      const emp = JSON.parse(button.getAttribute('data-emp'));
      
      form.npk.value = emp.NPK;
      form.npk.readOnly = true;
      form.name.value = emp.Nama || '';
      form.gender.value = emp.Gender || '';
      form.section.value = emp.Section || '';
      form.line.value = emp.Line || '';
      form.leader.value = emp.Leader || '';
      form.dateIn.value = emp.DateIn || '';
      form.status.value = emp.Status || '';
      form.functionrole.value = emp.Function || '';
      form.tipe.value = emp.Tipe || '';
      form.durasi.value = emp.Durasi || '';

      document.getElementById('durasiField').style.display = (emp.Tipe === 'Kontrak') ? 'block' : 'none';

      if (!form.querySelector('[name=edit]')) {
        const editInput = document.createElement('input');
        editInput.type = 'hidden';
        editInput.name = 'edit';
        editInput.value = '1';
        form.appendChild(editInput);
      }
      
      document.querySelector('.modal-title').textContent = '✏️ Edit Data MP';
      formModal.show();
    });
  });
});

function toggleDurasi(tipe) {
  document.getElementById('durasiField').style.display = (tipe === 'Kontrak') ? 'block' : 'none';
  if (tipe !== 'Kontrak') {
    document.querySelector('[name="durasi"]').value = '';
  }
}
</script>
</body>
</html>