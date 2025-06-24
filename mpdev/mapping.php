<?php
require_once 'auth_check.php';
require_once '../backend/database.php';

// Database connection
try {
    $db = new Database();
    $conn = $db->getConnection();
    
    if (!$conn) {
        throw new Exception("Koneksi database gagal");
    }
} catch (Exception $e) {
    error_log("Database Error: " . $e->getMessage());
    die("Terjadi kesalahan sistem. Silakan coba lagi nanti.");
}

// Handle filters
$allowedSections = ['Comp Assy', 'Comp WClutch'];
$allowedStatuses = ['Aktif', 'Tidak Aktif'];

$whereClause = "WHERE 1=1";
$params = [];

if (isset($_GET['filter_section']) && !empty($_GET['filter_section'])) {
    if (in_array($_GET['filter_section'], $allowedSections)) {
        $whereClause .= " AND Section = :section";
        $params[':section'] = $_GET['filter_section'];
    }
}

if (isset($_GET['filter_line']) && !empty($_GET['filter_line'])) {
    if (preg_match('/^[A-Za-z0-9\s-]+$/', $_GET['filter_line'])) {
        $whereClause .= " AND Line = :line";
        $params[':line'] = $_GET['filter_line'];
    }
}

if (isset($_GET['filter_status']) && !empty($_GET['filter_status'])) {
    if (in_array($_GET['filter_status'], $allowedStatuses)) {
        $whereClause .= " AND Status = :status";
        $params[':status'] = $_GET['filter_status'];
    }
} else {
    // Default: only show active employees
    $whereClause .= " AND Status = 'Aktif'";
}

// Get unique sections and lines for filter options
$sectionsStmt = $conn->query("SELECT DISTINCT Section FROM employees WHERE Status = 'Aktif' ORDER BY Section");
$sections = $sectionsStmt->fetchAll(PDO::FETCH_COLUMN);

$linesStmt = $conn->query("SELECT DISTINCT Line FROM employees WHERE Status = 'Aktif' ORDER BY Line");
$lines = $linesStmt->fetchAll(PDO::FETCH_COLUMN);

// Get filtered data
$sql = "SELECT * FROM employees " . $whereClause . " ORDER BY Section, Line, NPK";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Summary data - separate queries for better performance
$summaryQueries = [
    'comp_assy_kartap' => "SELECT COUNT(*) FROM employees WHERE Status = 'Aktif' AND Section = 'Comp Assy' AND Tipe = 'Tetap'",
    'comp_assy_kontrak' => "SELECT COUNT(*) FROM employees WHERE Status = 'Aktif' AND Section = 'Comp Assy' AND Tipe = 'Kontrak'",
    'comp_wclutch_kartap' => "SELECT COUNT(*) FROM employees WHERE Status = 'Aktif' AND Section = 'Comp WClutch' AND Tipe = 'Tetap'",
    'comp_wclutch_kontrak' => "SELECT COUNT(*) FROM employees WHERE Status = 'Aktif' AND Section = 'Comp WClutch' AND Tipe = 'Kontrak'"
];

$summaryData = [];
foreach ($summaryQueries as $key => $query) {
    $stmt = $conn->query($query);
    $summaryData[$key] = $stmt->fetchColumn();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Mapping MP - MP Development</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
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
      flex-wrap: wrap;
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
      background: #ef4444;
      color: white;
      border-color: #ef4444;
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
    
    .section-title {
      font-size: 1.5rem;
      font-weight: 600;
      color: #1e293b;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .filter-card, .data-card, .chart-card {
      background: white;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 25px;
      margin-bottom: 25px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      transition: all 0.3s ease;
    }
    
    .filter-card:hover, .data-card:hover, .chart-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    .filter-title, .card-title {
      font-size: 1.1rem;
      font-weight: 600;
      color: #1e293b;
      margin-bottom: 20px;
    }
    
    .form-select, .form-control {
      border: 1px solid #d1d5db;
      border-radius: 8px;
      padding: 10px 12px;
      background: white;
      color: #334155;
    }
    
    .form-select:focus, .form-control:focus {
      border-color: #ef4444;
      box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
    }
    
    .btn-primary {
      background: #ef4444;
      border-color: #ef4444;
      padding: 10px 20px;
      border-radius: 8px;
      font-weight: 500;
      transition: all 0.2s ease;
    }
    
    .btn-primary:hover {
      background: #dc2626;
      border-color: #dc2626;
      transform: translateY(-1px);
    }
    
    .btn-secondary {
      background: #6b7280;
      border-color: #6b7280;
      padding: 10px 20px;
      border-radius: 8px;
      font-weight: 500;
      transition: all 0.2s ease;
    }
    
    .btn-secondary:hover {
      background: #4b5563;
      border-color: #4b5563;
      transform: translateY(-1px);
    }
    
    /* Summary Cards */
    .summary-card {
      background: white;
      border-radius: 12px;
      padding: 25px;
      margin-bottom: 20px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      border: 1px solid #e2e8f0;
      transition: all 0.3s ease;
      text-align: center;
    }
    
    .summary-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }
    
    .summary-card.bg-primary {
      background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%) !important;
      color: white;
      border-color: #3b82f6;
    }
    
    .summary-card.bg-success {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
      color: white;
      border-color: #10b981;
    }
    
    .summary-card.bg-info {
      background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%) !important;
      color: white;
      border-color: #06b6d4;
    }
    
    .summary-card.bg-warning {
      background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
      color: white;
      border-color: #f59e0b;
    }
    
    .summary-card h5 {
      font-size: 0.9rem;
      font-weight: 600;
      margin-bottom: 15px;
      opacity: 0.9;
    }
    
    .summary-card h2 {
      font-size: 2.5rem;
      font-weight: 700;
      margin: 0;
    }
    
    /* Table Styling */
    .table {
      background: white;
      border-radius: 8px;
      overflow: hidden;
      border: 1px solid #e2e8f0;
    }
    
    .table thead th {
      background: #1e293b;
      border-bottom: 1px solid #334155;
      color: white;
      font-weight: 600;
      font-size: 0.875rem;
      text-transform: uppercase;
      letter-spacing: 0.025em;
      padding: 15px 12px;
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
    
    .bg-danger {
      background-color: #ef4444 !important;
    }
    
    /* Chart Container */
    .chart-container {
      position: relative;
      height: 350px;
      width: 100%;
      padding: 20px 0;
    }
    
    /* DataTables custom styling */
    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter,
    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_paginate {
      color: #374151;
    }
    
    .dataTables_wrapper .dataTables_filter input {
      border: 1px solid #d1d5db;
      border-radius: 8px;
      padding: 8px 12px;
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button {
      border: 1px solid #e2e8f0;
      background: white;
      color: #374151 !important;
      border-radius: 6px;
      margin: 0 2px;
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
      background: #ef4444 !important;
      border-color: #ef4444 !important;
      color: white !important;
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
      background: #f3f4f6 !important;
      border-color: #d1d5db !important;
      color: #374151 !important;
    }
    
    #loadingIndicator {
      display: none;
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
      .data-card,
      .filter-card,
      .chart-card {
        padding: 20px;
      }
      
      .nav-actions {
        justify-content: center;
      }
      
      .chart-container {
        height: 300px;
        padding: 10px 0;
      }
      
      .summary-card h2 {
        font-size: 2rem;
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
    <h1 class="page-title">🗺️ Mapping Manpower</h1>
    <p class="page-subtitle">Visualisasi dan pemetaan distribusi manpower berdasarkan section dan line produksi</p>
  </div>
  
  <!-- Filter Section -->
  <div class="filter-card fade-in">
    <div class="filter-title">🔍 Filter Data</div>
    <form method="GET" class="row g-3">
      <div class="col-lg-3 col-md-4">
        <label class="form-label" style="font-weight: 600; color: #374151;">Section</label>
        <select name="filter_section" class="form-select">
          <option value="">Semua Section</option>
          <?php foreach($sections as $section): ?>
            <option value="<?= htmlspecialchars($section) ?>" <?= (isset($_GET['filter_section']) && $_GET['filter_section'] == $section) ? 'selected' : '' ?>>
              <?= htmlspecialchars($section) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-lg-3 col-md-4">
        <label class="form-label" style="font-weight: 600; color: #374151;">Line</label>
        <select name="filter_line" class="form-select">
          <option value="">Semua Line</option>
          <?php foreach($lines as $line): ?>
            <option value="<?= htmlspecialchars($line) ?>" <?= (isset($_GET['filter_line']) && $_GET['filter_line'] == $line) ? 'selected' : '' ?>>
              <?= htmlspecialchars($line) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-lg-3 col-md-4">
        <label class="form-label" style="font-weight: 600; color: #374151;">Status</label>
        <select name="filter_status" class="form-select">
          <option value="">Hanya Aktif</option>
          <option value="Aktif" <?= (isset($_GET['filter_status']) && $_GET['filter_status'] == 'Aktif') ? 'selected' : '' ?>>Aktif</option>
          <option value="Tidak Aktif" <?= (isset($_GET['filter_status']) && $_GET['filter_status'] == 'Tidak Aktif') ? 'selected' : '' ?>>Tidak Aktif</option>
        </select>
      </div>
      <div class="col-lg-3 col-md-12 d-flex align-items-end gap-2">
        <button type="submit" class="btn btn-primary">🔍 Filter</button>
        <a href="mapping.php" class="btn btn-secondary">🔄 Reset</a>
      </div>
    </form>
  </div>

  <!-- Summary Cards -->
  <div class="row mb-4 fade-in">
    <div class="col-lg-3 col-md-6 mb-3">
      <div class="summary-card bg-primary">
        <h5>MP Kartap (Comp Assy)</h5>
        <h2><?= $summaryData['comp_assy_kartap'] ?></h2>
      </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
      <div class="summary-card bg-success">
        <h5>MP Kontrak (Comp Assy)</h5>
        <h2><?= $summaryData['comp_assy_kontrak'] ?></h2>
      </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
      <div class="summary-card bg-info">
        <h5>MP Kartap (Comp WClutch)</h5>
        <h2><?= $summaryData['comp_wclutch_kartap'] ?></h2>
      </div>
    </div>
    <div class="col-lg-3 col-md-6 mb-3">
      <div class="summary-card bg-warning">
        <h5>MP Kontrak (Comp WClutch)</h5>
        <h2><?= $summaryData['comp_wclutch_kontrak'] ?></h2>
      </div>
    </div>
  </div>

  <!-- Chart Section -->
  <div class="row mb-4 fade-in">
    <div class="col-lg-6 col-md-12 mb-3">
      <div class="chart-card">
        <h5 class="card-title">📊 Mapping MP Comp Assy</h5>
        <div class="chart-container">
          <canvas id="compAssyChart"></canvas>
        </div>
      </div>
    </div>
    <div class="col-lg-6 col-md-12 mb-3">
      <div class="chart-card">
        <h5 class="card-title">📊 Mapping MP Comp WClutch</h5>
        <div class="chart-container">
          <canvas id="compWClutchChart"></canvas>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Main Data Section -->
  <div class="data-card fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h5 class="mb-0">📋 Data Mapping Manpower</h5>
      <a href="employees.php" class="btn btn-primary">
        ➕ Kelola Data MP
      </a>
    </div>
    
    <!-- Loading Indicator -->
    <div id="loadingIndicator" class="text-center py-4">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2">Memuat data...</p>
    </div>
    
    <div class="table-responsive">
      <table id="mappingTable" class="table">
        <thead>
          <tr>
            <th>NPK</th>
            <th>Nama</th>
            <th>Section</th>
            <th>Line</th>
            <th>Leader</th>
            <th>Function</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($data as $row): ?>
            <tr>
              <td><strong><?= htmlspecialchars($row['NPK']) ?></strong></td>
              <td><?= htmlspecialchars($row['Nama']) ?></td>
              <td><?= htmlspecialchars($row['Section']) ?></td>
              <td><?= htmlspecialchars($row['Line']) ?></td>
              <td><?= htmlspecialchars($row['Leader']) ?></td>
              <td><?= htmlspecialchars($row['Function']) ?></td>
              <td>
                <span class="badge <?= $row['Status'] == 'Aktif' ? 'bg-success' : 'bg-danger' ?>">
                  <?= htmlspecialchars($row['Status']) ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
$(document).ready(function() {
  // Smooth animations on load
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
  
  // Show loading
  $('#loadingIndicator').show();
  
  // Initialize DataTable
  $('#mappingTable').DataTable({
    responsive: true,
    language: {
      url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
    },
    pageLength: 25,
    dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
    order: [[2, 'asc'], [3, 'asc']], // Sort by Section, then Line
    initComplete: function() {
      // Hide loading when table is ready
      $('#loadingIndicator').hide();
    }
  });
  
  // Initialize charts
  initializeCharts();
});

function initializeCharts() {
  // Chart data preparation
  <?php
  // Get data for charts - active employees only
  $compAssyData = array_filter($data, function($emp) { 
      return $emp['Section'] == 'Comp Assy' && $emp['Status'] == 'Aktif'; 
  });
  $compWClutchData = array_filter($data, function($emp) { 
      return $emp['Section'] == 'Comp WClutch' && $emp['Status'] == 'Aktif'; 
  });

  $compAssyLineCounts = array_count_values(array_column($compAssyData, 'Line'));
  $compWClutchLineCounts = array_count_values(array_column($compWClutchData, 'Line'));
  ?>

  // Comp Assy Chart (Bar Chart)
  const compAssyData = <?= json_encode($compAssyLineCounts) ?>;
  const compAssyLabels = Object.keys(compAssyData);
  const compAssyValues = Object.values(compAssyData);

  if (compAssyLabels.length > 0) {
    const ctx1 = document.getElementById('compAssyChart').getContext('2d');
    new Chart(ctx1, {
      type: 'bar',
      data: {
        labels: compAssyLabels,
        datasets: [{
          label: 'Jumlah MP',
          data: compAssyValues,
          backgroundColor: [
            '#3b82f6', '#2563eb', '#1d4ed8', '#1e40af', '#1e3a8a'
          ],
          borderColor: '#2563eb',
          borderWidth: 2,
          borderRadius: 8,
          borderSkipped: false,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            backgroundColor: 'rgba(59, 130, 246, 0.9)',
            titleColor: '#ffffff',
            bodyColor: '#ffffff',
            borderColor: '#2563eb',
            borderWidth: 1,
            cornerRadius: 8,
            callbacks: {
              label: function(context) {
                return 'Jumlah: ' + context.parsed.y + ' orang';
              }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              stepSize: 1,
              color: '#6b7280',
              font: {
                size: 12
              }
            },
            grid: {
              color: '#e5e7eb',
              drawBorder: false
            },
            title: {
              display: true,
              text: 'Jumlah MP',
              color: '#374151',
              font: {
                size: 14,
                weight: 'bold'
              }
            }
          },
          x: {
            ticks: {
              color: '#6b7280',
              font: {
                size: 12
              }
            },
            grid: {
              display: false
            },
            title: {
              display: true,
              text: 'Line Produksi',
              color: '#374151',
              font: {
                size: 14,
                weight: 'bold'
              }
            }
          }
        },
        animation: {
          duration: 2000,
          easing: 'easeOutBounce'
        },
        interaction: {
          intersect: false,
          mode: 'index'
        }
      }
    });
  } else {
    // Show no data message for Comp Assy
    document.getElementById('compAssyChart').parentElement.innerHTML = 
      '<div class="text-center py-5"><p class="text-muted">Tidak ada data untuk Comp Assy</p></div>';
  }

  // Comp WClutch Chart (Bar Chart)
  const compWClutchData = <?= json_encode($compWClutchLineCounts) ?>;
  const compWClutchLabels = Object.keys(compWClutchData);
  const compWClutchValues = Object.values(compWClutchData);

  if (compWClutchLabels.length > 0) {
    const ctx2 = document.getElementById('compWClutchChart').getContext('2d');
    new Chart(ctx2, {
      type: 'bar',
      data: {
        labels: compWClutchLabels,
        datasets: [{
          label: 'Jumlah MP',
          data: compWClutchValues,
          backgroundColor: [
            '#06b6d4', '#0891b2', '#0e7490', '#155e75', '#164e63'
          ],
          borderColor: '#0891b2',
          borderWidth: 2,
          borderRadius: 8,
          borderSkipped: false,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            backgroundColor: 'rgba(6, 182, 212, 0.9)',
            titleColor: '#ffffff',
            bodyColor: '#ffffff',
            borderColor: '#0891b2',
            borderWidth: 1,
            cornerRadius: 8,
            callbacks: {
              label: function(context) {
                return 'Jumlah: ' + context.parsed.y + ' orang';
              }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              stepSize: 1,
              color: '#6b7280',
              font: {
                size: 12
              }
            },
            grid: {
              color: '#e5e7eb',
              drawBorder: false
            },
            title: {
              display: true,
              text: 'Jumlah MP',
              color: '#374151',
              font: {
                size: 14,
                weight: 'bold'
              }
            }
          },
          x: {
            ticks: {
              color: '#6b7280',
              font: {
                size: 12
              }
            },
            grid: {
              display: false
            },
            title: {
              display: true,
              text: 'Line Produksi',
              color: '#374151',
              font: {
                size: 14,
                weight: 'bold'
              }
            }
          }
        },
        animation: {
          duration: 2000,
          easing: 'easeOutBounce'
        },
        interaction: {
          intersect: false,
          mode: 'index'
        }
      }
    });
  } else {
    // Show no data message for Comp WClutch
    document.getElementById('compWClutchChart').parentElement.innerHTML = 
      '<div class="text-center py-5"><p class="text-muted">Tidak ada data untuk Comp WClutch</p></div>';
  }
}
</script>
</body>
</html>