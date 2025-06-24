<?php
require_once 'auth_check.php';
checkAuth(['admin', 'manager', 'spv', 'trainer', 'leader', 'foreman']);
require_once '../backend/database.php';

$db = new Database();
$conn = $db->getConnection();

// Define processes for different sections
$compAssyProcesses = [
  'Mizusumashi Towing', 'Mizusumashi Shaft', 'Pre Check', 'Part Washing Big Part (IN)', 'Part Washing Inner Part (IN)', 'Pass Room (Prepare Piston)', 'Pass Room (Prepare Gasket)', 'Prepare Thrust Bearing', 'Prepare Oring PRV', 'Bearing Assy',
  'Bushing Assy', 'Mizusumashi Assy', 'Part Washing Big Part (OUT) & LIP Seal Assy', 'Part Washing Inner Part (OUT)', 'PRV Assy', 'Piston & Swash Measuring', 'Shoe Selecting', 'Cylinder Block Assy', 'Shoe Clearance & Muffler Bolt', 'QR Code Label Assy & Press Pin',
  'Front Side Assy', 'Front Housing Assy', 'Rear Housing Assy', 'Housing Bolt', 'Bolt Tightening', 'Concentricity Check & Torque Check', 'Empty Weight & Dummy Assy', 'Vacuum & Gas Charging', 'Helium Leak Test', 'High Pressure Check',
  'Performance Test', 'Release Dummy', 'Pre Oil & Oil Filling', 'Seal Cap Assy', 'Flange Assy', 'Air Leak Test', 'Gas Release', 'Seal Cap Assy 2', 'Final Washing', 'Name Plate Assy',
  'Thermal Sensor', 'Felt Assy', 'Foot & Boshi Check', 'Robot Final Check', 'Taking & Laser Name Plate', 'Repair Shoe Clearance', 'Repair Dipping'
];

$compWClutchProcesses = [
    'Pre Stator', 'Stator Assy', 'Rotor Assy', 'Washer Selection', 'Bracket Assy', 'Final Check WClutch', 'Packaging', 'Mizusumashi WClutch', 'Repair Man WClutch', 'Prepare Bracket'
];

// Handle AJAX request for getting processes by section
if (isset($_GET['action']) && $_GET['action'] === 'get_processes') {
    $section = $_GET['section'] ?? '';
    $processes = [];
    
    if ($section === 'Comp Assy') {
        $processes = $compAssyProcesses;
    } elseif ($section === 'Comp WClutch') {
        $processes = $compWClutchProcesses;
    }
    
    header('Content-Type: application/json');
    echo json_encode($processes);
    exit;
}

// Handle delete request
if (isset($_GET['delete'])) {
    try {
        $id = (int)$_GET['delete'];
        
        // Get file path before deleting record
        $fileStmt = $conn->prepare("SELECT raport FROM education WHERE id = ?");
        $fileStmt->execute([$id]);
        $filePath = $fileStmt->fetchColumn();
        
        // Delete record
        $deleteStmt = $conn->prepare("DELETE FROM education WHERE id = ?");
        $deleteStmt->execute([$id]);
        
        // Delete file if exists
        if ($filePath && file_exists($filePath)) {
            unlink($filePath);
        }
        
        header("Location: education.php?success=3");
        exit();
    } catch (Exception $e) {
        header("Location: education.php?error=delete_failed");
        exit();
    }
}

// Function to handle file upload
function handleFileUpload($file) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    
    // Validate file type
    $allowedTypes = ['application/pdf'];
    $fileType = mime_content_type($file['tmp_name']);
    if (!in_array($fileType, $allowedTypes)) {
        throw new Exception("Only PDF files are allowed");
    }
    
    // Validate file size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception("File size too large. Maximum 5MB allowed");
    }
    
    $uploadDir = 'uploads/raport/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = time() . '_' . uniqid() . '.' . $fileExtension;
    $filePath = $uploadDir . $fileName;
    
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        throw new Exception("Failed to upload file");
    }
    
    return $filePath;
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add'])) {
            // Handle file upload
            $raportPath = handleFileUpload($_FILES['raport'] ?? null);
            
            // Calculate datePlanning (6 months after dateEdukasi)
            $dateEdukasi = new DateTime($_POST['dateEdukasi']);
            $datePlanning = clone $dateEdukasi;
            $datePlanning->add(new DateInterval('P6M'));
            
            $sql = "INSERT INTO education 
                (npk, name, section, line, leader, namaPos, category, dateEdukasi, datePlanning, raport, status) 
                VALUES (:npk, :name, :section, :line, :leader, :namaPos, :category, :dateEdukasi, :datePlanning, :raport, :status)";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':npk' => $_POST['npk'],
                ':name' => $_POST['name'],
                ':section' => $_POST['section'],
                ':line' => $_POST['line'],
                ':leader' => $_POST['leader'],
                ':namaPos' => $_POST['namaPos'],
                ':category' => $_POST['category'],
                ':dateEdukasi' => $_POST['dateEdukasi'],
                ':datePlanning' => $datePlanning->format('Y-m-d'),
                ':raport' => $raportPath,
                ':status' => $_POST['status']
            ]);
            
            header("Location: education.php?success=1");
            exit();
            
        } elseif (isset($_POST['edit'])) {
            $id = (int)$_POST['edit_id'];
            
            // Get current file path
            $currentFileStmt = $conn->prepare("SELECT raport FROM education WHERE id = ?");
            $currentFileStmt->execute([$id]);
            $currentFilePath = $currentFileStmt->fetchColumn();
            
            // Handle new file upload
            $raportPath = $currentFilePath; // Keep current file by default
            if (isset($_FILES['raport']) && $_FILES['raport']['error'] === UPLOAD_ERR_OK) {
                $raportPath = handleFileUpload($_FILES['raport']);
                
                // Delete old file if new file uploaded successfully
                if ($currentFilePath && file_exists($currentFilePath)) {
                    unlink($currentFilePath);
                }
            }
            
            // Calculate new datePlanning if dateEdukasi changed
            $dateEdukasi = new DateTime($_POST['dateEdukasi']);
            $datePlanning = clone $dateEdukasi;
            $datePlanning->add(new DateInterval('P6M'));
            
            $sql = "UPDATE education SET 
                namaPos = :namaPos, 
                category = :category, 
                dateEdukasi = :dateEdukasi, 
                datePlanning = :datePlanning, 
                raport = :raport, 
                status = :status 
                WHERE id = :id";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':namaPos' => $_POST['namaPos'],
                ':category' => $_POST['category'],
                ':dateEdukasi' => $_POST['dateEdukasi'],
                ':datePlanning' => $datePlanning->format('Y-m-d'),
                ':raport' => $raportPath,
                ':status' => $_POST['status'],
                ':id' => $id
            ]);
            
            header("Location: education.php?success=2");
            exit();
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Handle date filters
$whereClause = "";
$params = [];

if (isset($_GET['filter_month']) && !empty($_GET['filter_month'])) {
    $whereClause .= " AND MONTH(dateEdukasi) = :month";
    $params[':month'] = $_GET['filter_month'];
}

if (isset($_GET['filter_year']) && !empty($_GET['filter_year'])) {
    $whereClause .= " AND YEAR(dateEdukasi) = :year";
    $params[':year'] = $_GET['filter_year'];
}

// Get employees data for auto-fill
try {
    $employeesStmt = $conn->query("SELECT * FROM employees ORDER BY NPK ASC");
    $employees = $employeesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $employees = [];
}

// Get education data
try {
    $sql = "SELECT * FROM education WHERE 1=1" . $whereClause . " ORDER BY id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $data = [];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Edukasi - MP Development</title>
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
    
    .filter-card, .data-card {
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
    
    .btn-info {
      background: #06b6d4;
      border-color: #06b6d4;
    }
    
    .btn-info:hover {
      background: #0891b2;
      border-color: #0891b2;
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
    
    .bg-success {
      background-color: #10b981 !important;
    }
    
    .bg-warning {
      background-color: #f59e0b !important;
      color: white !important;
    }
    
    .bg-secondary {
      background-color: #6b7280 !important;
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
    📚 Data Edukasi
  </div>
  
  <!-- Alerts -->
  <?php if (isset($_GET['success'])): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    <?php 
    $success_msg = '';
    switch($_GET['success']) {
      case '1': $success_msg = '✅ Data edukasi berhasil ditambahkan!'; break;
      case '2': $success_msg = '✅ Data edukasi berhasil diupdate!'; break;
      case '3': $success_msg = '✅ Data edukasi berhasil dihapus!'; break;
      default: $success_msg = '✅ Operasi berhasil!';
    }
    echo $success_msg;
    ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>
  
  <?php if (isset($error_message)): ?>
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    ❌ Error: <?= htmlspecialchars($error_message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>
  
  <?php if (isset($_GET['error'])): ?>
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php 
    $error_msg = '';
    switch($_GET['error']) {
      case 'delete_failed': $error_msg = '❌ Gagal menghapus data edukasi!'; break;
      default: $error_msg = '❌ Terjadi kesalahan!';
    }
    echo $error_msg;
    ?>
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
          for($year = $currentYear - 2; $year <= $currentYear + 2; $year++): 
          ?>
            <option value="<?= $year ?>" <?= (isset($_GET['filter_year']) && $_GET['filter_year'] == $year) ? 'selected' : '' ?>>
              <?= $year ?>
            </option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="col-lg-6 col-md-12 d-flex align-items-end">
        <button type="submit" class="btn btn-primary me-2">🔍 Filter</button>
        <a href="education.php" class="btn btn-secondary">🔄 Reset</a>
      </div>
    </form>
  </div>
  
  <!-- Main Data Section -->
  <div class="data-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h5 class="mb-0">📋 Data Edukasi</h5>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#formModal">
        ➕ Tambah Edukasi
      </button>
    </div>
    
    <div class="table-responsive">
      <table id="educationTable" class="table">
        <thead class="table-dark">
          <tr>
            <th>NPK</th>
            <th>Name</th>
            <th>Section</th>
            <th>Line</th>
            <th>Leader</th>
            <th>Nama Pos</th>
            <th>Category</th>
            <th>Date Education</th>
            <th>Date Planning</th>
            <th>Raport</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($data as $row): ?>
          <tr>
            <td><strong><?= htmlspecialchars($row['npk']) ?></strong></td>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <td><?= htmlspecialchars($row['section']) ?></td>
            <td><?= htmlspecialchars($row['line']) ?></td>
            <td><?= htmlspecialchars($row['leader']) ?></td>
            <td><?= htmlspecialchars($row['namaPos']) ?></td>
            <td>
              <span class="badge bg-primary">
                <?= htmlspecialchars($row['category']) ?>
              </span>
            </td>
            <td><?= date('d/m/Y', strtotime($row['dateEdukasi'])) ?></td>
            <td><?= date('d/m/Y', strtotime($row['datePlanning'])) ?></td>
            <td>
              <?php if ($row['raport']): ?>
                <a href="<?= htmlspecialchars($row['raport']) ?>" target="_blank" class="btn btn-sm btn-info">
                  📄 View PDF
                </a>
              <?php else: ?>
                <span class="text-muted">No file</span>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge bg-<?= $row['status'] === 'Selesai' ? 'success' : ($row['status'] === 'Berlangsung' ? 'warning' : 'secondary') ?>">
                <?= htmlspecialchars($row['status']) ?>
              </span>
            </td>
            <td>
              <button class="btn btn-sm btn-warning me-1" onclick="editEducation(this)" 
                      data-id="<?= $row['id'] ?>"
                      data-npk="<?= htmlspecialchars($row['npk']) ?>"
                      data-name="<?= htmlspecialchars($row['name']) ?>"
                      data-section="<?= htmlspecialchars($row['section']) ?>"
                      data-line="<?= htmlspecialchars($row['line']) ?>"
                      data-leader="<?= htmlspecialchars($row['leader']) ?>"
                      data-namapos="<?= htmlspecialchars($row['namaPos']) ?>"
                      data-category="<?= htmlspecialchars($row['category']) ?>"
                      data-dateedukasi="<?= htmlspecialchars($row['dateEdukasi']) ?>"
                      data-status="<?= htmlspecialchars($row['status']) ?>"
                      data-raport="<?= htmlspecialchars($row['raport']) ?>">
                ✏️ Edit
              </button>
              <button class="btn btn-sm btn-danger" onclick="deleteEducation(<?= $row['id'] ?>)">
                🗑️ Delete
              </button>
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
      <div class="modal-header">
        <h5 class="modal-title">📝 Tambah Data Edukasi</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" enctype="multipart/form-data">
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6">
              <h6 class="text-muted mb-3">👤 Data Karyawan</h6>
              <div class="mb-3">
                <label class="form-label">Pilih dari Database MP</label>
                <select class="form-select" id="selectEmployee">
                  <option value="">-- Pilih Karyawan --</option>
                  <?php foreach ($employees as $emp): ?>
                  <option value="<?= $emp['NPK'] ?>" 
                    data-name="<?= htmlspecialchars($emp['Nama']) ?>"
                    data-section="<?= htmlspecialchars($emp['Section']) ?>"
                    data-line="<?= htmlspecialchars($emp['Line']) ?>"
                    data-leader="<?= htmlspecialchars($emp['Leader']) ?>">
                    <?= $emp['NPK'] ?> - <?= htmlspecialchars($emp['Nama']) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              
              <div class="row">
                <div class="col-md-6">
                  <label class="form-label">NPK</label>
                  <input type="text" name="npk" id="npkInput" class="form-control" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Name</label>
                  <input type="text" name="name" id="nameInput" class="form-control" required>
                </div>
              </div>
              
              <div class="row">
                <div class="col-md-4">
                  <label class="form-label">Section</label>
                  <input type="text" name="section" id="sectionInput" class="form-control" required>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Line</label>
                  <input type="text" name="line" id="lineInput" class="form-control" required>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Leader</label>
                  <input type="text" name="leader" id="leaderInput" class="form-control" required>
                </div>
              </div>
            </div>
            
            <div class="col-md-6">
              <h6 class="text-muted mb-3">📚 Data Edukasi</h6>
              <div class="mb-3">
                <label class="form-label">Nama Pos</label>
                <select name="namaPos" id="namaPosSelect" class="form-select" required>
                  <option value="">Pilih Nama Pos</option>
                </select>
                <small class="text-muted">Nama pos akan otomatis muncul berdasarkan section yang dipilih</small>
              </div>
              
              <div class="mb-3">
                <label class="form-label">Category</label>
                <select name="category" class="form-select" required>
                  <option value="">Pilih Category</option>
                  <option value="New MP">New MP</option>
                  <option value="Refresh MP">Refresh MP</option>
                  <option value="Skill Up MP">Skill Up MP</option>
                </select>
              </div>
              
              <div class="mb-3">
                <label class="form-label">Date Education</label>
                <input type="date" name="dateEdukasi" class="form-control" required>
                <small class="text-muted">Date Planning akan otomatis 6 bulan setelah tanggal ini</small>
              </div>
              
              <div class="mb-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select" required>
                  <option value="">Pilih Status</option>
                  <option value="Planned">Planned</option>
                  <option value="Berlangsung">Berlangsung</option>
                  <option value="Selesai">Selesai</option>
                </select>
              </div>
              
              <div class="mb-3">
                <label class="form-label">Raport (PDF)</label>
                <input type="file" name="raport" class="form-control" accept=".pdf">
                <small class="text-muted">Upload file PDF untuk raport (Max 5MB)</small>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">❌ Batal</button>
          <button type="submit" name="add" class="btn btn-primary">💾 Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">✏️ Edit Data Edukasi</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="edit_id" id="edit_id">
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6">
              <h6 class="text-muted mb-3">👤 Data Karyawan</h6>
              <div class="mb-3">
                <label class="form-label">NPK</label>
                <input type="text" name="npk" id="edit_npk" class="form-control" readonly>
              </div>
              
              <div class="mb-3">
                <label class="form-label">Name</label>
                <input type="text" name="name" id="edit_name" class="form-control" readonly>
              </div>
              
              <div class="mb-3">
                <label class="form-label">Section</label>
                <input type="text" name="section" id="edit_section" class="form-control" readonly>
              </div>
              
              <div class="mb-3">
                <label class="form-label">Line</label>
                <input type="text" name="line" id="edit_line" class="form-control" readonly>
              </div>
              
              <div class="mb-3">
                <label class="form-label">Leader</label>
                <input type="text" name="leader" id="edit_leader" class="form-control" readonly>
              </div>
            </div>
            
            <div class="col-md-6">
              <h6 class="text-muted mb-3">📚 Data Edukasi</h6>
              <div class="mb-3">
                <label class="form-label">Nama Pos</label>
                <select name="namaPos" id="edit_namaPos" class="form-select" required>
                  <option value="">Pilih Nama Pos</option>
                </select>
              </div>
              
              <div class="mb-3">
                <label class="form-label">Category</label>
                <select name="category" id="edit_category" class="form-select" required>
                  <option value="">Pilih Category</option>
                  <option value="New MP">New MP</option>
                  <option value="Refresh MP">Refresh MP</option>
                  <option value="Skill Up MP">Skill Up MP</option>
                </select>
              </div>
              
              <div class="mb-3">
                <label class="form-label">Date Education</label>
                <input type="date" name="dateEdukasi" id="edit_dateEdukasi" class="form-control" required>
              </div>
              
              <div class="mb-3">
                <label class="form-label">Status</label>
                <select name="status" id="edit_status" class="form-select" required>
                  <option value="">Pilih Status</option>
                  <option value="Planned">Planned</option>
                  <option value="Berlangsung">Berlangsung</option>
                  <option value="Selesai">Selesai</option>
                </select>
              </div>
              
              <div class="mb-3">
                <label class="form-label">Raport (PDF)</label>
                <input type="file" name="raport" class="form-control" accept=".pdf">
                <small class="text-muted">Kosongkan jika tidak ingin mengubah file (Max 5MB)</small>
              </div>
            </div>
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
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
  $('#educationTable').DataTable({
    responsive: true,
    language: {
      url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
    },
    pageLength: 25,
    order: [[7, 'desc']]
  });
});

// Initialize Select2 when modal is shown
$('#formModal').on('shown.bs.modal', function () {
  $('#selectEmployee').select2({
    theme: 'bootstrap-5',
    placeholder: '-- Pilih Karyawan --',
    allowClear: true,
    width: '100%',
    dropdownParent: $('#formModal'),
    language: {
      noResults: function() {
        return "Tidak ada data yang ditemukan";
      },
      searching: function() {
        return "Mencari...";
      }
    }
  });
  
  // Add event listener for Select2 change
  $('#selectEmployee').on('select2:select', function (e) {
    fillEmployeeData();
  });
  
  $('#selectEmployee').on('select2:clear', function (e) {
    clearEmployeeData();
  });
  
  $('#namaPosSelect').select2({
    theme: 'bootstrap-5',
    placeholder: 'Pilih Nama Pos',
    allowClear: true,
    width: '100%',
    dropdownParent: $('#formModal'),
    language: {
      noResults: function() {
        return "Tidak ada data yang ditemukan";
      },
      searching: function() {
        return "Mencari...";
      }
    }
  });
});

// Initialize Select2 for edit modal
$('#editModal').on('shown.bs.modal', function () {
  $('#edit_namaPos').select2({
    theme: 'bootstrap-5',
    placeholder: 'Pilih Nama Pos',
    allowClear: true,
    width: '100%',
    dropdownParent: $('#editModal'),
    language: {
      noResults: function() {
        return "Tidak ada data yang ditemukan";
      },
      searching: function() {
        return "Mencari...";
      }
    }
  });
});

// Destroy Select2 when modal is hidden
$('#formModal').on('hidden.bs.modal', function () {
  if ($('#selectEmployee').hasClass('select2-hidden-accessible')) {
    $('#selectEmployee').select2('destroy');
  }
  if ($('#namaPosSelect').hasClass('select2-hidden-accessible')) {
    $('#namaPosSelect').select2('destroy');
  }
  // Reset form
  this.querySelector('form').reset();
  document.getElementById('namaPosSelect').innerHTML = '<option value="">Pilih Nama Pos</option>';
});

$('#editModal').on('hidden.bs.modal', function () {
  if ($('#edit_namaPos').hasClass('select2-hidden-accessible')) {
    $('#edit_namaPos').select2('destroy');
  }
});

// Function to fill employee data when selected from dropdown
function fillEmployeeData() {
  console.log('fillEmployeeData called'); // Debug log
  const selectElement = document.getElementById('selectEmployee');
  const selectedOption = selectElement.options[selectElement.selectedIndex];
  
  console.log('Selected option:', selectedOption); // Debug log
  
  if (selectedOption.value) {
    const npk = selectedOption.value;
    const name = selectedOption.getAttribute('data-name') || '';
    const section = selectedOption.getAttribute('data-section') || '';
    const line = selectedOption.getAttribute('data-line') || '';
    const leader = selectedOption.getAttribute('data-leader') || '';
    
    console.log('Data to fill:', { npk, name, section, line, leader }); // Debug log
    
    // Fill the form fields
    document.getElementById('npkInput').value = npk;
    document.getElementById('nameInput').value = name;
    document.getElementById('sectionInput').value = section;
    document.getElementById('lineInput').value = line;
    document.getElementById('leaderInput').value = leader;
    
    // Load processes based on section
    if (section) {
      console.log('Loading processes for section:', section); // Debug log
      loadProcessesBySection(section);
    }
  }
}

// Function to clear employee data
function clearEmployeeData() {
  console.log('clearEmployeeData called'); // Debug log
  document.getElementById('npkInput').value = '';
  document.getElementById('nameInput').value = '';
  document.getElementById('sectionInput').value = '';
  document.getElementById('lineInput').value = '';
  document.getElementById('leaderInput').value = '';
  loadProcessesBySection(''); // Clear processes
}

// Function to load processes by section
function loadProcessesBySection(section) {
  console.log('loadProcessesBySection called with section:', section); // Debug log
  
  if (!section) {
    const namaPosSelect = document.getElementById('namaPosSelect');
    if ($('#namaPosSelect').hasClass('select2-hidden-accessible')) {
      $('#namaPosSelect').select2('destroy');
    }
    namaPosSelect.innerHTML = '<option value="">Pilih Nama Pos</option>';
    $('#namaPosSelect').select2({
      theme: 'bootstrap-5',
      placeholder: 'Pilih Nama Pos',
      allowClear: true,
      width: '100%',
      dropdownParent: $('#formModal'),
      language: {
        noResults: function() {
          return "Tidak ada data yang ditemukan";
        },
        searching: function() {
          return "Mencari...";
        }
      }
    });
    return;
  }
  
  const url = `education.php?action=get_processes&section=${encodeURIComponent(section)}`;
  console.log('Fetching from URL:', url); // Debug log
  
  fetch(url)
    .then(response => {
      console.log('Response status:', response.status); // Debug log
      if (!response.ok) {
        throw new Error('Network response was not ok');
      }
      return response.json();
    })
    .then(processes => {
      console.log('Processes received:', processes); // Debug log
      const namaPosSelect = document.getElementById('namaPosSelect');
      
      if ($('#namaPosSelect').hasClass('select2-hidden-accessible')) {
        $('#namaPosSelect').select2('destroy');
      }
      
      namaPosSelect.innerHTML = '<option value="">Pilih Nama Pos</option>';
      
      processes.forEach(process => {
        const option = document.createElement('option');
        option.value = process;
        option.textContent = process;
        namaPosSelect.appendChild(option);
      });
      
      $('#namaPosSelect').select2({
        theme: 'bootstrap-5',
        placeholder: 'Pilih Nama Pos',
        allowClear: true,
        width: '100%',
        dropdownParent: $('#formModal'),
        language: {
          noResults: function() {
            return "Tidak ada data yang ditemukan";
          },
          searching: function() {
            return "Mencari...";
          }
        }
      });
    })
    .catch(error => {
      console.error('Error loading processes:', error);
      const namaPosSelect = document.getElementById('namaPosSelect');
      if ($('#namaPosSelect').hasClass('select2-hidden-accessible')) {
        $('#namaPosSelect').select2('destroy');
      }
      namaPosSelect.innerHTML = '<option value="">Error loading processes</option>';
      $('#namaPosSelect').select2({
        theme: 'bootstrap-5',
        placeholder: 'Pilih Nama Pos',
        allowClear: true,
        width: '100%',
        dropdownParent: $('#formModal'),
        language: {
          noResults: function() {
            return "Tidak ada data yang ditemukan";
          },
          searching: function() {
            return "Mencari...";
          }
        }
      });
    });
}

// Function to load processes for edit modal
function loadProcessesForEdit(section, currentPos) {
  if (!section) {
    const select = document.getElementById('edit_namaPos');
    if ($('#edit_namaPos').hasClass('select2-hidden-accessible')) {
      $('#edit_namaPos').select2('destroy');
    }
    select.innerHTML = '<option value="">Pilih Nama Pos</option>';
    $('#edit_namaPos').select2({
      theme: 'bootstrap-5',
      placeholder: 'Pilih Nama Pos',
      allowClear: true,
      width: '100%',
      dropdownParent: $('#editModal'),
      language: {
        noResults: function() {
          return "Tidak ada data yang ditemukan";
        },
        searching: function() {
          return "Mencari...";
        }
      }
    });
    return;
  }
  
  fetch(`education.php?action=get_processes&section=${encodeURIComponent(section)}`)
    .then(response => {
      if (!response.ok) {
        throw new Error('Network response was not ok');
      }
      return response.json();
    })
    .then(processes => {
      const select = document.getElementById('edit_namaPos');
      
      if ($('#edit_namaPos').hasClass('select2-hidden-accessible')) {
        $('#edit_namaPos').select2('destroy');
      }
      
      select.innerHTML = '<option value="">Pilih Nama Pos</option>';
      
      processes.forEach(process => {
        const option = document.createElement('option');
        option.value = process;
        option.textContent = process;
        if (process === currentPos) {
          option.selected = true;
        }
        select.appendChild(option);
      });
      
      $('#edit_namaPos').select2({
        theme: 'bootstrap-5',
        placeholder: 'Pilih Nama Pos',
        allowClear: true,
        width: '100%',
        dropdownParent: $('#editModal'),
        language: {
          noResults: function() {
            return "Tidak ada data yang ditemukan";
          },
          searching: function() {
            return "Mencari...";
          }
        }
      });
    })
    .catch(error => {
      console.error('Error loading processes:', error);
      const select = document.getElementById('edit_namaPos');
      if ($('#edit_namaPos').hasClass('select2-hidden-accessible')) {
        $('#edit_namaPos').select2('destroy');
      }
      select.innerHTML = '<option value="">Error loading processes</option>';
      $('#edit_namaPos').select2({
        theme: 'bootstrap-5',
        placeholder: 'Pilih Nama Pos',
        allowClear: true,
        width: '100%',
        dropdownParent: $('#editModal'),
        language: {
          noResults: function() {
            return "Tidak ada data yang ditemukan";
          },
          searching: function() {
            return "Mencari...";
          }
        }
      });
    });
}

// Function to edit education data
function editEducation(button) {
  // Get data from button attributes
  const id = button.getAttribute('data-id');
  const npk = button.getAttribute('data-npk');
  const name = button.getAttribute('data-name');
  const section = button.getAttribute('data-section');
  const line = button.getAttribute('data-line');
  const leader = button.getAttribute('data-leader');
  const namaPos = button.getAttribute('data-namapos');
  const category = button.getAttribute('data-category');
  const dateEdukasi = button.getAttribute('data-dateedukasi');
  const status = button.getAttribute('data-status');
  
  // Fill the edit form
  document.getElementById('edit_id').value = id;
  document.getElementById('edit_npk').value = npk;
  document.getElementById('edit_name').value = name;
  document.getElementById('edit_section').value = section;
  document.getElementById('edit_line').value = line;
  document.getElementById('edit_leader').value = leader;
  document.getElementById('edit_category').value = category;
  document.getElementById('edit_dateEdukasi').value = dateEdukasi;
  document.getElementById('edit_status').value = status;
  
  // Load processes for the section and set current position
  loadProcessesForEdit(section, namaPos);
  
  // Show the modal
  const editModal = new bootstrap.Modal(document.getElementById('editModal'));
  editModal.show();
}

function deleteEducation(id) {
  if (confirm('Yakin ingin menghapus data edukasi ini?\n\nData yang dihapus tidak dapat dikembalikan.')) {
    window.location.href = `education.php?delete=${id}`;
  }
}

// Add event listener for section input change
document.addEventListener('DOMContentLoaded', function() {
  // Add event listener for section input in add form (fallback untuk manual input)
  const sectionInput = document.getElementById('sectionInput');
  if (sectionInput) {
    sectionInput.addEventListener('input', function() {
      const section = this.value.trim();
      loadProcessesBySection(section);
    });
  }
});

// File validation
document.addEventListener('DOMContentLoaded', function() {
  const fileInputs = document.querySelectorAll('input[type="file"]');
  
  fileInputs.forEach(function(input) {
    input.addEventListener('change', function() {
      const file = this.files[0];
      if (file) {
        // Check file size (5MB limit)
        if (file.size > 5 * 1024 * 1024) {
          alert('Ukuran file terlalu besar. Maksimal 5MB.');
          this.value = '';
          return;
        }
        
        // Check file type
        if (file.type !== 'application/pdf') {
          alert('Hanya file PDF yang diperbolehkan.');
          this.value = '';
          return;
        }
      }
    });
  });
});
</script>
</body>
</html>