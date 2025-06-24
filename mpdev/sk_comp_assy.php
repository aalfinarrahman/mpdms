<?php
require_once 'auth_check.php';
checkAuth(['admin', 'manager', 'spv', 'trainer', 'leader', 'foreman']);
require_once '../backend/database.php';

$db = new Database();
$conn = $db->getConnection();

// Handle skill level updates
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_skill') {
    $npk = $_POST['npk'];
    $process = $_POST['process'];
    $skill_level = $_POST['skill_level'];
    
    try {
        // Check if record exists
        $checkStmt = $conn->prepare("SELECT id FROM skill_matrix_comp_assy WHERE npk = ? AND process = ?");
        $checkStmt->execute([$npk, $process]);
        
        if ($checkStmt->rowCount() > 0) {
            // Update existing record
            $updateStmt = $conn->prepare("UPDATE skill_matrix_comp_assy SET skill_level = ?, updated_at = NOW() WHERE npk = ? AND process = ?");
            $updateStmt->execute([$skill_level, $npk, $process]);
        } else {
            // Insert new record
            $insertStmt = $conn->prepare("INSERT INTO skill_matrix_comp_assy (npk, process, skill_level, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
            $insertStmt->execute([$npk, $process, $skill_level]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Skill level berhasil disimpan']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Build WHERE clause for filtering
$whereClause = "WHERE e.Status = 'Aktif' AND e.Section = 'Comp Assy'";
$params = [];

// Add Line filter if provided
if (isset($_GET['filter_line']) && !empty($_GET['filter_line'])) {
    $whereClause .= " AND e.Line = :line";
    $params[':line'] = $_GET['filter_line'];
}

// Get unique lines for filter options
$linesStmt = $conn->query("SELECT DISTINCT Line FROM employees WHERE Status = 'Aktif' AND Section = 'Comp Assy' ORDER BY Line");
$lines = $linesStmt->fetchAll(PDO::FETCH_COLUMN);

// Get employees data with their current processes
$compAssyQuery = "
    SELECT DISTINCT 
        e.NPK,
        e.Nama,
        e.Section,
        e.Line,
        e.Leader,
        GROUP_CONCAT(DISTINCT ed.namaPos ORDER BY ed.dateEdukasi DESC SEPARATOR ', ') as proses_saat_ini
    FROM employees e
    LEFT JOIN education ed ON e.NPK = ed.npk
    $whereClause
    GROUP BY e.NPK, e.Nama, e.Section, e.Line, e.Leader
    ORDER BY e.Line, e.NPK
";

$stmt = $conn->prepare($compAssyQuery);
$stmt->execute($params);
$compAssyData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get existing skill levels
$skillLevelsQuery = "SELECT npk, process, skill_level FROM skill_matrix_comp_assy";
$skillLevelsData = $conn->query($skillLevelsQuery)->fetchAll(PDO::FETCH_ASSOC);
$skillLevels = [];
foreach ($skillLevelsData as $skill) {
    $skillLevels[$skill['npk']][$skill['process']] = $skill['skill_level'];
}

// Define processes for Comp Assy
$compAssyProcesses = [
    'Mizusumashi Towing', 'Mizusumashi Shaft', 'Pre Check', 'Part Washing Big Part (IN)', 'Part Washing Inner Part (IN)', 'Pass Room (Prepare Piston)', 'Pass Room (Prepare Gasket)', 'Prepare Thrust Bearing', 'Prepare Oring PRV', 'Bearing Assy',
    'Bushing Assy', 'Mizusumashi Assy', 'Part Washing Big Part (OUT) & LIP Seal Assy', 'Part Washing Inner Part (OUT)', 'PRV Assy', 'Piston & Swash Measuring', 'Shoe Selecting', 'Cylinder Block Assy', 'Shoe Clearance & Muffler Bolt', 'QR Code Label Assy & Press Pin',
    'Front Side Assy', 'Front Housing Assy', 'Rear Housing Assy', 'Housing Bolt', 'Bolt Tightening', 'Concentricity Check & Torque Check', 'Empty Weight & Dummy Assy', 'Vacuum & Gas Charging', 'Helium Leak Test', 'High Pressure Check',
    'Performance Test', 'Release Dummy', 'Pre Oil & Oil Filling', 'Seal Cap Assy', 'Flange Assy', 'Air Leak Test', 'Gas Release', 'Seal Cap Assy 2', 'Final Washing', 'Name Plate Assy',
    'Thermal Sensor', 'Felt Assy', 'Foot & Boshi Check', 'Robot Final Check', 'Taking & Laser Name Plate', 'Repair Shoe Clearance', 'Repair Dipping'
];

// Calculate stats
$totalEmployees = count($compAssyData);
$totalProcesses = count($compAssyProcesses);
$totalSkills = 0;
$completedSkills = 0;
$expertSkills = 0;

foreach ($skillLevelsData as $skill) {
    $totalSkills++;
    if ($skill['skill_level'] > 0) $completedSkills++;
    if ($skill['skill_level'] == 4) $expertSkills++;
}

$completionRate = $totalSkills > 0 ? round(($completedSkills / ($totalEmployees * $totalProcesses)) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Skill Matrix Comp Assy - MP Development</title>
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
      background: #3b82f6;
      color: white;
      border-color: #3b82f6;
    }
    
    /* Main Content */
    .main-content {
      margin-top: 80px;
      padding: 30px;
      min-height: calc(100vh - 80px);
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
      margin-bottom: 20px;
    }
    
    .action-buttons {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }
    
    .btn-modern {
      padding: 10px 20px;
      border-radius: 8px;
      font-weight: 500;
      border: none;
      transition: all 0.2s ease;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      cursor: pointer;
    }
    
    .btn-modern.btn-success {
      background: #10b981;
      color: white;
    }
    
    .btn-modern.btn-success:hover {
      background: #059669;
      transform: translateY(-1px);
    }
    
    .btn-modern.btn-info {
      background: #3b82f6;
      color: white;
    }
    
    .btn-modern.btn-info:hover {
      background: #2563eb;
      transform: translateY(-1px);
    }
    
    /* Stats Cards */
    .stats-container {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      margin-bottom: 25px;
    }
    
    .stat-card {
      background: white;
      border-radius: 12px;
      padding: 20px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      border: 1px solid #e2e8f0;
      text-align: center;
      border-left: 4px solid #3b82f6;
    }
    
    .stat-value {
      font-size: 2rem;
      font-weight: 700;
      color: #1e293b;
      margin-bottom: 5px;
    }
    
    .stat-label {
      font-size: 0.9rem;
      color: #64748b;
      font-weight: 500;
    }
    
    /* Info Alert */
    .info-card {
      background: white;
      border-radius: 12px;
      padding: 25px;
      margin-bottom: 25px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      border: 1px solid #e2e8f0;
      border-left: 4px solid #3b82f6;
    }
    
    .skill-legend {
      display: flex;
      flex-wrap: wrap;
      gap: 20px;
      margin-top: 15px;
    }
    
    .skill-legend-item {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    /* Filter Card */
    .filter-card {
      background: white;
      border-radius: 12px;
      padding: 25px;
      margin-bottom: 25px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      border: 1px solid #e2e8f0;
    }
    
    .filter-title {
      color: #1e293b;
      font-weight: 600;
      margin-bottom: 20px;
      font-size: 1.1rem;
    }
    
    .form-select {
      border-radius: 8px;
      border: 1px solid #d1d5db;
      padding: 10px 12px;
    }
    
    .form-select:focus {
      border-color: #3b82f6;
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .btn-filter {
      background: #3b82f6;
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 8px;
      font-weight: 500;
      transition: all 0.2s ease;
    }
    
    .btn-filter:hover {
      background: #2563eb;
      transform: translateY(-1px);
      color: white;
    }
    
    .btn-reset {
      background: #6b7280;
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 8px;
      font-weight: 500;
      transition: all 0.2s ease;
      text-decoration: none;
    }
    
    .btn-reset:hover {
      background: #4b5563;
      transform: translateY(-1px);
      color: white;
      text-decoration: none;
    }
    
    /* Table Container */
    .table-wrapper {
      background: white;
      border-radius: 12px;
      padding: 20px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      border: 1px solid #e2e8f0;
    }
    
    .table-container {
      overflow: auto;
      max-height: 700px;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      position: relative;
      scroll-behavior: smooth;
    }
    
    /* Skill Matrix Table */
    .skill-matrix-table {
      font-size: 14px;
      border-collapse: separate;
      border-spacing: 0;
      margin: 0;
    }
    
    .skill-matrix-table th {
      min-width: 60px;
      padding: 12px 8px;
      background-color: #1e293b;
      color: white;
      font-weight: 600;
      text-align: center;
      vertical-align: middle;
      border: 1px solid #334155;
      position: sticky;
      top: 0;
      z-index: 20;
    }
    
    .skill-matrix-table td {
      text-align: center;
      padding: 8px;
      border: 1px solid #e2e8f0;
      vertical-align: middle;
      background-color: white;
    }
    
    /* Employee Info Columns (Frozen) */
    .employee-info {
      position: sticky;
      background: #f8fafc;
      z-index: 15;
      font-weight: 600;
      border-right: 2px solid #3b82f6;
      box-shadow: 2px 0 3px rgba(0,0,0,0.1);
      color: #1e293b;
    }
    
    .employee-info:nth-child(1) { left: 0; min-width: 80px; }
    .employee-info:nth-child(2) { left: 80px; min-width: 150px; }
    .employee-info:nth-child(3) { left: 230px; min-width: 100px; }
    .employee-info:nth-child(4) { left: 330px; min-width: 80px; }
    .employee-info:nth-child(5) { left: 410px; min-width: 120px; }
    .employee-info:nth-child(6) { 
      left: 530px; 
      min-width: 150px; 
      border-right: 4px solid #10b981;
      cursor: pointer;
    }
    
    .employee-info:nth-child(6):hover {
      background-color: #ecfdf5 !important;
      color: #065f46;
    }
    
    .employee-header {
      position: sticky;
      top: 0;
      background-color: #1e293b !important;
      color: white !important;
      z-index: 25;
      border-bottom: 2px solid #334155;
    }
    
    .employee-header:nth-child(1) { left: 0; }
    .employee-header:nth-child(2) { left: 80px; }
    .employee-header:nth-child(3) { left: 230px; }
    .employee-header:nth-child(4) { left: 330px; }
    .employee-header:nth-child(5) { left: 410px; }
    .employee-header:nth-child(6) { left: 530px; }
    
    /* Process Headers */
    .process-header {
      background-color: #475569 !important;
      color: white !important;
      font-size: 13px;
      font-weight: 600;
      text-align: center;
      vertical-align: middle;
      min-width: 80px;
      max-width: 120px;
      word-wrap: break-word;
      hyphens: auto;
    }
    
    .current-process-header {
      background-color: #10b981 !important;
      color: white !important;
      box-shadow: 0 0 10px rgba(16, 185, 129, 0.5);
    }
    
    /* Skill Levels */
    .skill-level {
      width: 35px;
      height: 35px;
      border-radius: 50%;
      display: inline-block;
      border: 2px solid #374151;
      position: relative;
      background: white;
      margin: 3px;
    }
    
    .level-0 { background: white; }
    .level-1 { background: conic-gradient(from 0deg, #374151 0deg 90deg, white 90deg 360deg); }
    .level-2 { background: conic-gradient(from 0deg, #374151 0deg 180deg, white 180deg 360deg); }
    .level-3 { background: conic-gradient(from 0deg, #374151 0deg 270deg, white 270deg 360deg); }
    .level-4 { background: #374151; }
    
    .skill-cell {
      position: relative;
      min-width: 80px;
    }
    
    .skill-indicator {
      width: 30px;
      height: 30px;
      border-radius: 50%;
      border: 2px solid #374151;
      margin: 0 auto 8px;
      display: block;
    }
    
    .skill-selector {
      width: 60px;
      font-size: 13px;
      padding: 4px;
      border: 1px solid #d1d5db;
      border-radius: 6px;
      background: white;
    }
    
    .skill-selector:focus {
      border-color: #3b82f6;
      box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
      outline: none;
    }
    
    /* Current Process Indicators */
    .current-process-indicator {
      position: absolute;
      top: -3px;
      left: -3px;
      right: -3px;
      bottom: -3px;
      border: 3px solid #10b981;
      border-radius: 8px;
      background-color: rgba(16, 185, 129, 0.05);
      z-index: 5;
      pointer-events: none;
    }
    
    .current-process-cell {
      position: relative;
      background-color: rgba(16, 185, 129, 0.05) !important;
    }
    
    /* Employee Rows */
    .employee-row:nth-child(even) .employee-info {
      background-color: #f1f5f9;
    }
    
    .employee-row:hover .employee-info {
      background-color: #e2e8f0;
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
      .main-content {
        padding: 15px;
      }
      
      .page-header {
        padding: 20px;
      }
      
      .page-title {
        font-size: 1.5rem;
      }
      
      .action-buttons {
        flex-direction: column;
      }
      
      .btn-modern {
        justify-content: center;
      }
      
      .skill-matrix-table {
        font-size: 12px;
      }
      
      .skill-selector {
        width: 50px;
        font-size: 11px;
      }
      
      .employee-info:nth-child(2) { left: 60px; min-width: 120px; }
      .employee-info:nth-child(3) { left: 180px; min-width: 80px; }
      .employee-info:nth-child(4) { left: 260px; min-width: 60px; }
      .employee-info:nth-child(5) { left: 320px; min-width: 100px; }
      .employee-info:nth-child(6) { left: 420px; min-width: 120px; }
      
      .top-nav {
        padding: 12px 20px;
      }
      
      .nav-brand {
        font-size: 1.2rem;
      }
      
      .stats-container {
        grid-template-columns: 1fr;
      }
    }
    
    /* Print Styles */
    @media print {
      .top-nav, .action-buttons { display: none; }
      .main-content { margin-top: 0; }
      body { background: white; }
      body::before { display: none; }
    }
    
    /* Loading Animation */
    .fade-in {
      animation: fadeIn 0.5s ease-in;
    }
    
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
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

<!-- Main Content -->
<div class="main-content">
  
  <!-- Page Header -->
  <div class="page-header fade-in">
    <div class="d-flex justify-content-between align-items-start flex-wrap">
      <div class="mb-3">
        <h1 class="page-title">📊 Skill Matrix - Comp Assy</h1>
        <p class="page-subtitle">Evaluasi kemampuan karyawan berdasarkan <?= count($compAssyProcesses) ?> proses kerja Comp Assy</p>
      </div>
      <div class="action-buttons">
        <button class="btn-modern btn-success" onclick="exportToExcel()">
          📊 Export Excel
        </button>
        <button class="btn-modern btn-info" onclick="printMatrix()">
          🖨️ Print Matrix
        </button>
      </div>
    </div>
  </div>
  
  <!-- Stats Cards -->
  <div class="stats-container fade-in">
    <div class="stat-card">
      <div class="stat-value"><?= count($compAssyProcesses) ?></div>
      <div class="stat-label">Total Proses</div>
    </div>
    <div class="stat-card">
      <div class="stat-value"><?= $totalEmployees ?></div>
      <div class="stat-label">Total Karyawan</div>
    </div>
    <div class="stat-card">
      <div class="stat-value"><?= $completionRate ?>%</div>
      <div class="stat-label">Completion Rate</div>
    </div>
    <div class="stat-card">
      <div class="stat-value"><?= $expertSkills ?></div>
      <div class="stat-label">Expert Level (4)</div>
    </div>
  </div>
  
  <!-- Info Card -->
  <div class="info-card fade-in">
    <div class="d-flex align-items-center mb-3">
      <span style="font-size: 1.5rem; margin-right: 10px;">💡</span>
      <strong style="color: #1e293b; font-size: 1.1rem;">Panduan Skill Matrix</strong>
    </div>
    <p style="color: #64748b; margin-bottom: 15px;">
      Gunakan sistem penilaian berikut untuk mengevaluasi kemampuan karyawan pada setiap proses kerja.
    </p>
    <div class="skill-legend">
      <div class="skill-legend-item">
        <span class="skill-level level-0"></span>
        <span><strong>Level 0:</strong> Tidak mampu menjalankan tugas</span>
      </div>
      <div class="skill-legend-item">
        <span class="skill-level level-1"></span>
        <span><strong>Level 1:</strong> Mampu menyelesaikan tugas</span>
      </div>
      <div class="skill-legend-item">
        <span class="skill-level level-2"></span>
        <span><strong>Level 2:</strong> Sesuai standar</span>
      </div>
      <div class="skill-legend-item">
        <span class="skill-level level-3"></span>
        <span><strong>Level 3:</strong> Sesuai standar & waktu</span>
      </div>
      <div class="skill-legend-item">
        <span class="skill-level level-4"></span>
        <span><strong>Level 4:</strong> Dapat melatih orang lain</span>
      </div>
    </div>
  </div>

  <!-- Filter Card -->
  <div class="filter-card fade-in">
    <h6 class="filter-title">🔍 Filter Data</h6>
    <form method="GET" class="row g-3">
      <div class="col-md-4">
        <label class="form-label" style="font-weight: 600; color: #374151;">Line Produksi</label>
        <select name="filter_line" class="form-select">
          <option value="">Semua Line</option>
          <?php foreach($lines as $line): ?>
          <option value="<?= htmlspecialchars($line) ?>" <?= (isset($_GET['filter_line']) && $_GET['filter_line'] == $line) ? 'selected' : '' ?>>
            <?= htmlspecialchars($line) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4 d-flex align-items-end">
        <button type="submit" class="btn-filter me-2">🔍 Filter</button>
        <a href="sk_comp_assy.php" class="btn-reset">🔄 Reset</a>
      </div>
    </form>
  </div>

  <!-- Table Wrapper -->
  <div class="table-wrapper fade-in">
    <div class="table-container">
      <table class="table table-bordered skill-matrix-table">
        <thead>
          <tr>
            <th class="employee-info employee-header">NPK</th>
            <th class="employee-info employee-header">Nama</th>
            <th class="employee-info employee-header">Section</th>
            <th class="employee-info employee-header">Line</th>
            <th class="employee-info employee-header">Leader</th>
            <th class="employee-info employee-header">Proses Saat Ini</th>
            <?php foreach ($compAssyProcesses as $index => $process): ?>
              <th class="process-header" id="process-header-<?= $index ?>"><?= htmlspecialchars($process) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($compAssyData as $employee): ?>
          <tr class="employee-row">
            <td class="employee-info"><?= htmlspecialchars($employee['NPK']) ?></td>
            <td class="employee-info"><?= htmlspecialchars($employee['Nama']) ?></td>
            <td class="employee-info"><?= htmlspecialchars($employee['Section']) ?></td>
            <td class="employee-info"><?= htmlspecialchars($employee['Line']) ?></td>
            <td class="employee-info"><?= htmlspecialchars($employee['Leader']) ?></td>
            <td class="employee-info" onclick="scrollToCurrentProcess('<?= $employee['NPK'] ?>', '<?= $employee['proses_saat_ini'] ?>')">
              <?= $employee['proses_saat_ini'] ?: 'Belum Ada' ?>
            </td>
            <?php foreach ($compAssyProcesses as $index => $process): ?>
              <?php 
              $currentLevel = isset($skillLevels[$employee['NPK']][$process]) ? $skillLevels[$employee['NPK']][$process] : 0;
              $isCurrentProcess = false;
              if ($employee['proses_saat_ini']) {
                  $currentProcesses = explode(', ', $employee['proses_saat_ini']);
                  $isCurrentProcess = in_array($process, $currentProcesses);
              }
              ?>
              <td class="skill-cell <?= $isCurrentProcess ? 'current-process-cell' : '' ?>" id="skill-cell-<?= $employee['NPK'] ?>-<?= $index ?>">
                <?php if ($isCurrentProcess): ?>
                  <div class="current-process-indicator"></div>
                <?php endif; ?>
                <div class="skill-indicator level-<?= $currentLevel ?>" id="indicator-<?= $employee['NPK'] ?>-<?= $index ?>"></div>
                <select class="skill-selector" 
                        data-npk="<?= $employee['NPK'] ?>" 
                        data-process="<?= htmlspecialchars($process) ?>"
                        data-index="<?= $index ?>"
                        onchange="updateSkillLevel(this)">
                  <option value="0" <?= $currentLevel == 0 ? 'selected' : '' ?>>0</option>
                  <option value="1" <?= $currentLevel == 1 ? 'selected' : '' ?>>1</option>
                  <option value="2" <?= $currentLevel == 2 ? 'selected' : '' ?>>2</option>
                  <option value="3" <?= $currentLevel == 3 ? 'selected' : '' ?>>3</option>
                  <option value="4" <?= $currentLevel == 4 ? 'selected' : '' ?>>4</option>
                </select>
              </td>
            <?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
          
          <?php if (empty($compAssyData)): ?>
          <tr>
            <td colspan="<?= count($compAssyProcesses) + 6 ?>" class="text-center" style="padding: 40px;">
              <div style="color: #64748b;">
                <i class="fas fa-info-circle" style="font-size: 2rem; margin-bottom: 10px;"></i>
                <p>Tidak ada data karyawan yang ditemukan untuk section Comp Assy</p>
              </div>
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
// Processes array from PHP
const compAssyProcesses = <?= json_encode($compAssyProcesses) ?>;

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

function scrollToCurrentProcess(npk, currentProcesses) {
    if (!currentProcesses || currentProcesses === 'Belum Ada') {
        alert('Karyawan ini belum memiliki proses saat ini');
        return;
    }
    
    const processArray = currentProcesses.split(', ');
    const firstProcess = processArray[0];
    const processIndex = compAssyProcesses.indexOf(firstProcess);
    
    if (processIndex !== -1) {
        const targetCell = document.getElementById(`skill-cell-${npk}-${processIndex}`);
        const targetHeader = document.getElementById(`process-header-${processIndex}`);
        
        if (targetCell) {
            if (targetHeader) {
                targetHeader.classList.add('current-process-header');
                setTimeout(() => {
                    targetHeader.classList.remove('current-process-header');
                }, 3000);
            }
            
            targetCell.scrollIntoView({
                behavior: 'smooth',
                block: 'center',
                inline: 'center'
            });
            
            targetCell.style.boxShadow = '0 0 15px rgba(16, 185, 129, 0.8)';
            setTimeout(() => {
                targetCell.style.boxShadow = '';
            }, 3000);
        }
    } else {
        alert(`Proses "${firstProcess}" tidak ditemukan dalam daftar proses Comp Assy`);
    }
}

function updateSkillLevel(selectElement) {
    const npk = selectElement.dataset.npk;
    const process = selectElement.dataset.process;
    const level = selectElement.value;
    const index = selectElement.dataset.index;
    
    // Update visual indicator
    const indicator = document.getElementById(`indicator-${npk}-${index}`);
    if (indicator) {
        indicator.className = `skill-indicator level-${level}`;
    }
    
    // Save to database via AJAX
    $.ajax({
        url: 'sk_comp_assy.php',
        method: 'POST',
        data: {
            action: 'update_skill',
            npk: npk,
            process: process,
            skill_level: level
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Show success feedback
                selectElement.style.borderColor = '#10b981';
                setTimeout(() => {
                    selectElement.style.borderColor = '#d1d5db';
                }, 1000);
                console.log('Skill level saved successfully');
            } else {
                alert('Error: ' + response.message);
                // Revert the select value on error
                location.reload();
            }
        },
        error: function() {
            alert('Error saving skill level');
            // Revert the select value on error
            location.reload();
        }
    });
}

function exportToExcel() {
    const table = document.querySelector('table');
    const ws = XLSX.utils.table_to_sheet(table);
    const wb = XLSX.utils.book_new();
    
    XLSX.utils.book_append_sheet(wb, ws, 'Comp Assy Skill Matrix');
    
    const fileName = `skill_matrix_comp_assy_${new Date().toISOString().split('T')[0]}.xlsx`;
    XLSX.writeFile(wb, fileName);
}

function printMatrix() {
    window.print();
}
</script>

</body>
</html>