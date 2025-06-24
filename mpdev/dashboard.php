<?php
require_once 'auth_check.php';
checkAuth(['admin', 'manager', 'spv']); // Only allow these roles to access dashboard
require_once '../backend/database.php';

$db = new Database();
$conn = $db->getConnection();

// Get selected year from filter (default to current year)
$selectedYear = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Basic stats
// Perbaikan query untuk card statistics (sekitar baris 11-14)
$mpAktif = $conn->query("SELECT COUNT(*) FROM employees WHERE status = 'Aktif'")->fetchColumn();
$mpEndContract = $conn->query("SELECT COUNT(*) FROM end_contracts WHERE MONTH(dateOut) = MONTH(CURDATE()) AND YEAR(dateOut) = YEAR(CURDATE())")->fetchColumn();
// Perbaikan: Menggunakan date_in untuk menampilkan MP yang replacement di bulan berjalan
$mpRecruit = $conn->query("SELECT COUNT(*) FROM recruitment WHERE MONTH(date_in) = MONTH(CURDATE()) AND YEAR(date_in) = YEAR(CURDATE()) AND date_in IS NOT NULL")->fetchColumn();
$mpEdukasi = $conn->query("SELECT COUNT(*) FROM education WHERE MONTH(dateEdukasi) = MONTH(CURDATE()) AND YEAR(dateEdukasi) = YEAR(CURDATE())")->fetchColumn();

// Perbaikan query MP Aktif by Line (sekitar baris 16-22)
$mpAktifCompAssy = $conn->prepare("SELECT line, COUNT(*) as total FROM employees WHERE status = 'Aktif' AND section = 'Comp Assy' GROUP BY line");
$mpAktifCompAssy->execute();
$mpAktifCompAssyData = $mpAktifCompAssy->fetchAll(PDO::FETCH_ASSOC);

$mpAktifCompWClutch = $conn->prepare("SELECT line, COUNT(*) as total FROM employees WHERE status = 'Aktif' AND section = 'Comp WClutch' GROUP BY line");
$mpAktifCompWClutch->execute();
$mpAktifCompWClutchData = $mpAktifCompWClutch->fetchAll(PDO::FETCH_ASSOC);

// MP End Contract by month (April to March) for selected year with gender breakdown
// Perbaikan untuk query End Contract Comp Assy dengan breakdown gender
$endContractCompAssy = $conn->prepare("
    SELECT 
        CASE 
            WHEN MONTH(dateOut) >= 4 THEN CONCAT(YEAR(dateOut), '-', LPAD(MONTH(dateOut), 2, '0'))
            ELSE CONCAT(YEAR(dateOut), '-', LPAD(MONTH(dateOut), 2, '0'))
        END as month_year,
        gender,
        COUNT(*) as total,
        MIN(CASE 
            WHEN MONTH(dateOut) >= 4 THEN YEAR(dateOut) * 100 + MONTH(dateOut)
            ELSE (YEAR(dateOut) + 1) * 100 + MONTH(dateOut)
        END) as sort_order
    FROM end_contracts 
    WHERE section = 'Comp Assy' 
    AND ((MONTH(dateOut) >= 4 AND YEAR(dateOut) = :year) 
         OR (MONTH(dateOut) <= 3 AND YEAR(dateOut) = :year_plus_1))
    GROUP BY month_year, gender
    ORDER BY sort_order
");
$endContractCompAssy->execute([
    ':year' => $selectedYear,
    ':year_plus_1' => $selectedYear + 1
]);
$endContractCompAssyData = $endContractCompAssy->fetchAll(PDO::FETCH_ASSOC);

$endContractCompWClutch = $conn->prepare("
    SELECT 
        CASE 
            WHEN MONTH(dateOut) >= 4 THEN CONCAT(YEAR(dateOut), '-', LPAD(MONTH(dateOut), 2, '0'))
            ELSE CONCAT(YEAR(dateOut), '-', LPAD(MONTH(dateOut), 2, '0'))
        END as month_year,
        gender,
        COUNT(*) as total,
        MIN(CASE 
            WHEN MONTH(dateOut) >= 4 THEN YEAR(dateOut) * 100 + MONTH(dateOut)
            ELSE (YEAR(dateOut) + 1) * 100 + MONTH(dateOut)
        END) as sort_order
    FROM end_contracts 
    WHERE section = 'Comp WClutch' 
    AND ((YEAR(dateOut) = :year AND MONTH(dateOut) >= 4) OR (YEAR(dateOut) = :year_plus_1 AND MONTH(dateOut) <= 3))
    GROUP BY month_year, gender
    ORDER BY sort_order
");
$endContractCompWClutch->execute([':year' => $selectedYear, ':year_plus_1' => $selectedYear + 1]);
$endContractCompWClutchData = $endContractCompWClutch->fetchAll(PDO::FETCH_ASSOC);

// Tambahkan parameter category filter
// Tambahkan setelah $selectedYear
$selectedCategory = isset($_GET['category']) ? $_GET['category'] : '';

// Tambahkan parameter month filter untuk daily charts
$selectedMonth = isset($_GET['month']) ? $_GET['month'] : date('n'); // Default ke bulan sekarang
$selectedMonthYear = isset($_GET['month_year']) ? $_GET['month_year'] : date('Y'); // Default ke tahun sekarang

// Education data from education_schedule
// Fix for Comp Assy yearly education data (around line 61-69)
$educationCompAssyYearly = $conn->prepare("
    SELECT 
        COUNT(DISTINCT e.id) as planned,
        SUM(CASE WHEN es.status = 'Hadir' THEN 1 ELSE 0 END) as hadir,
        SUM(CASE WHEN es.status = 'Tidak Hadir' THEN 1 ELSE 0 END) as tidak_hadir
    FROM education e
    LEFT JOIN education_schedule es ON e.id = es.education_id
    WHERE e.section = 'Comp Assy' 
    AND (
        (MONTH(e.dateEdukasi) >= 4 AND YEAR(e.dateEdukasi) = :year) OR
        (MONTH(e.dateEdukasi) <= 3 AND YEAR(e.dateEdukasi) = :year + 1)
    )
    " . ($selectedCategory ? "AND e.category = :category" : "") . "
");

// Fix for Comp WClutch yearly education data (around line 72-80)
$educationCompWClutchYearly = $conn->prepare("
    SELECT 
        COUNT(DISTINCT e.id) as planned,
        SUM(CASE WHEN es.status = 'Hadir' THEN 1 ELSE 0 END) as hadir,
        SUM(CASE WHEN es.status = 'Tidak Hadir' THEN 1 ELSE 0 END) as tidak_hadir
    FROM education e
    LEFT JOIN education_schedule es ON e.id = es.education_id
    WHERE e.section = 'Comp WClutch' 
    AND (
        (MONTH(e.dateEdukasi) >= 4 AND YEAR(e.dateEdukasi) = :year) OR
        (MONTH(e.dateEdukasi) <= 3 AND YEAR(e.dateEdukasi) = :year + 1)
    )
    " . ($selectedCategory ? "AND e.category = :category" : "") . "
");

// Execute queries with category parameter
$params = [':year' => $selectedYear];
if ($selectedCategory) {
    $params[':category'] = $selectedCategory;
}

$educationCompAssyYearly->execute($params);
$educationCompAssyYearlyData = $educationCompAssyYearly->fetch(PDO::FETCH_ASSOC);

$educationCompWClutchYearly->execute($params);
$educationCompWClutchYearlyData = $educationCompWClutchYearly->fetch(PDO::FETCH_ASSOC);

// Education data by month - tambahkan juga filter category
$educationCompAssyMonthly = $conn->prepare("
    SELECT 
        CASE 
            WHEN MONTH(e.dateEdukasi) >= 4 THEN MONTH(e.dateEdukasi) - 3
            ELSE MONTH(e.dateEdukasi) + 9
        END as fiscal_month,
        MONTH(e.dateEdukasi) as actual_month,
        COUNT(DISTINCT e.id) as planned,
        SUM(CASE WHEN es.status = 'Hadir' THEN 1 ELSE 0 END) as hadir,
        SUM(CASE WHEN es.status = 'Tidak Hadir' THEN 1 ELSE 0 END) as tidak_hadir
    FROM education e
    LEFT JOIN education_schedule es ON e.id = es.education_id
    WHERE e.section = 'Comp Assy' 
    AND (
        (MONTH(e.dateEdukasi) >= 4 AND YEAR(e.dateEdukasi) = :year) OR
        (MONTH(e.dateEdukasi) <= 3 AND YEAR(e.dateEdukasi) = :year + 1)
    )
    " . ($selectedCategory ? "AND e.category = :category" : "") . "
    GROUP BY MONTH(e.dateEdukasi), 
             CASE 
                 WHEN MONTH(e.dateEdukasi) >= 4 THEN MONTH(e.dateEdukasi) - 3
                 ELSE MONTH(e.dateEdukasi) + 9
             END
    ORDER BY fiscal_month
");
$educationCompAssyMonthly->execute($params);
$educationCompAssyMonthlyData = $educationCompAssyMonthly->fetchAll(PDO::FETCH_ASSOC);

$educationCompWClutchMonthly = $conn->prepare("
    SELECT 
        CASE 
            WHEN MONTH(e.dateEdukasi) >= 4 THEN MONTH(e.dateEdukasi) - 3
            ELSE MONTH(e.dateEdukasi) + 9
        END as fiscal_month,
        MONTH(e.dateEdukasi) as actual_month,
        COUNT(DISTINCT e.id) as planned,
        SUM(CASE WHEN es.status = 'Hadir' THEN 1 ELSE 0 END) as hadir,
        SUM(CASE WHEN es.status = 'Tidak Hadir' THEN 1 ELSE 0 END) as tidak_hadir
    FROM education e
    LEFT JOIN education_schedule es ON e.id = es.education_id
    WHERE e.section = 'Comp WClutch' 
    AND (
        (MONTH(e.dateEdukasi) >= 4 AND YEAR(e.dateEdukasi) = :year) OR
        (MONTH(e.dateEdukasi) <= 3 AND YEAR(e.dateEdukasi) = :year + 1)
    )
    " . ($selectedCategory ? "AND e.category = :category" : "") . "
    GROUP BY MONTH(e.dateEdukasi), 
             CASE 
                 WHEN MONTH(e.dateEdukasi) >= 4 THEN MONTH(e.dateEdukasi) - 3
                 ELSE MONTH(e.dateEdukasi) + 9
             END
    ORDER BY fiscal_month
");
$educationCompWClutchMonthly->execute($params);
$educationCompWClutchMonthlyData = $educationCompWClutchMonthly->fetchAll(PDO::FETCH_ASSOC);

// Education data by day - untuk daily monitoring
$educationCompAssyDaily = $conn->prepare("
    SELECT 
        DAY(e.dateEdukasi) as day,
        DATE(e.dateEdukasi) as education_date,
        COUNT(DISTINCT e.id) as planned,
        SUM(CASE WHEN es.status = 'Hadir' THEN 1 ELSE 0 END) as hadir,
        SUM(CASE WHEN es.status = 'Tidak Hadir' THEN 1 ELSE 0 END) as tidak_hadir
    FROM education e
    LEFT JOIN education_schedule es ON e.id = es.education_id
    WHERE e.section = 'Comp Assy' 
    AND MONTH(e.dateEdukasi) = :month
    AND YEAR(e.dateEdukasi) = :month_year
    " . ($selectedCategory ? "AND e.category = :category" : "") . "
    GROUP BY DAY(e.dateEdukasi), DATE(e.dateEdukasi)
    ORDER BY DAY(e.dateEdukasi)
");

$educationCompWClutchDaily = $conn->prepare("
    SELECT 
        DAY(e.dateEdukasi) as day,
        DATE(e.dateEdukasi) as education_date,
        COUNT(DISTINCT e.id) as planned,
        SUM(CASE WHEN es.status = 'Hadir' THEN 1 ELSE 0 END) as hadir,
        SUM(CASE WHEN es.status = 'Tidak Hadir' THEN 1 ELSE 0 END) as tidak_hadir
    FROM education e
    LEFT JOIN education_schedule es ON e.id = es.education_id
    WHERE e.section = 'Comp WClutch' 
    AND MONTH(e.dateEdukasi) = :month
    AND YEAR(e.dateEdukasi) = :month_year
    " . ($selectedCategory ? "AND e.category = :category" : "") . "
    GROUP BY DAY(e.dateEdukasi), DATE(e.dateEdukasi)
    ORDER BY DAY(e.dateEdukasi)
");

// Execute daily queries
$dailyParams = [':month' => $selectedMonth, ':month_year' => $selectedMonthYear];
if ($selectedCategory) {
    $dailyParams[':category'] = $selectedCategory;
}

$educationCompAssyDaily->execute($dailyParams);
$educationCompAssyDailyData = $educationCompAssyDaily->fetchAll(PDO::FETCH_ASSOC);

$educationCompWClutchDaily->execute($dailyParams);
$educationCompWClutchDailyData = $educationCompWClutchDaily->fetchAll(PDO::FETCH_ASSOC);

// Get available years for filter - update to include education dates
$yearsQuery = $conn->query("
    SELECT DISTINCT 
        CASE 
            WHEN MONTH(dateOut) >= 4 THEN YEAR(dateOut)
            ELSE YEAR(dateOut) - 1
        END as fiscal_year 
    FROM end_contracts 
    UNION 
    SELECT DISTINCT 
        CASE 
            WHEN MONTH(dateEdukasi) >= 4 THEN YEAR(dateEdukasi)
            ELSE YEAR(dateEdukasi) - 1
        END as fiscal_year 
    FROM education 
    ORDER BY fiscal_year DESC
");
$availableYears = $yearsQuery->fetchAll(PDO::FETCH_COLUMN);

// Get available months for daily filter
$monthsQuery = $conn->query("
    SELECT DISTINCT MONTH(dateEdukasi) as month, YEAR(dateEdukasi) as year
    FROM education 
    ORDER BY year DESC, month DESC
");
$availableMonths = $monthsQuery->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Dashboard MP Development</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
  <style>
    body {
      background: #f8fafc;
      font-family: 'Segoe UI', sans-serif;
      margin: 0;
      color: #334155;
    }
    
    .card-box {
      border-radius: 12px;
      padding: 25px;
      background: white;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      border: 1px solid #e2e8f0;
      transition: all 0.3s ease;
    }
    
    .card-box:hover {
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    .dashboard-title {
      font-size: 14px;
      color: #64748b;
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 8px;
    }
    
    .dashboard-value {
      font-size: 2.5rem;
      font-weight: 700;
      color: #1e293b;
      margin-bottom: 5px;
    }
    
    .dashboard-subtitle {
      font-size: 13px;
      color: #64748b;
      font-weight: 400;
    }
    
    .chart-container {
      position: relative;
      height: 350px;
      margin: 20px 0;
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
    }
    
    .filter-title {
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
    
    /* Card accent colors */
    .card-primary { border-left: 4px solid #3b82f6; }
    .card-success { border-left: 4px solid #10b981; }
    .card-warning { border-left: 4px solid #f59e0b; }
    .card-purple { border-left: 4px solid #8b5cf6; }
    
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
    
    @media (max-width: 768px) {
      .main-container {
        padding: 20px 15px;
      }
      
      .dashboard-value {
        font-size: 2rem;
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
    📊 Dashboard MP Development
  </div>
  
  <!-- Summary Cards -->
  <div class="row g-4 mb-5">
    <div class="col-lg-3 col-md-6">
      <div class="card-box card-primary">
        <div class="dashboard-title">Total Man Power Aktif</div>
        <div class="dashboard-value"><?= number_format($mpAktif) ?></div>
        <div class="dashboard-subtitle">Karyawan Aktif</div>
      </div>
    </div>
    <div class="col-lg-3 col-md-6">
      <div class="card-box card-success">
        <div class="dashboard-title">MP End Contract</div>
        <div class="dashboard-value"><?= number_format($mpEndContract) ?></div>
        <div class="dashboard-subtitle">Bulan Ini</div>
      </div>
    </div>
    <div class="col-lg-3 col-md-6">
      <div class="card-box card-warning">
        <div class="dashboard-title">MP Recruitment</div>
        <div class="dashboard-value"><?= number_format($mpRecruit) ?></div>
        <div class="dashboard-subtitle">Bulan Ini</div>
      </div>
    </div>
    <div class="col-lg-3 col-md-6">
      <div class="card-box card-purple">
        <div class="dashboard-title">MP Edukasi</div>
        <div class="dashboard-value"><?= number_format($mpEdukasi) ?></div>
        <div class="dashboard-subtitle">Bulan Ini</div>
      </div>
    </div>
  </div>

  <!-- End Contract Charts dengan Filter Tahun -->
  <div class="filter-card">
    <div class="d-flex justify-content-between align-items-center">
      <div class="filter-title">📅 MP End Contract Charts</div>
      <div class="d-flex align-items-center gap-2">
        <label for="yearFilter" class="form-label mb-0">Filter Tahun:</label>
        <select id="yearFilter" class="form-select" style="width: auto;" onchange="filterByYear()">
          <?php foreach ($availableYears as $year): ?>
            <option value="<?= $year ?>" <?= $year == $selectedYear ? 'selected' : '' ?>><?= $year ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>
  
  <div class="row g-4 mb-5">
    <div class="col-lg-6">
      <div class="card-box">
        <h5 class="mb-3">MP End Contract Comp Assy (Apr <?= $selectedYear ?> - Mar <?= $selectedYear + 1 ?>)</h5>
        <div class="chart-container">
          <canvas id="endContractCompAssyChart"></canvas>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card-box">
        <h5 class="mb-3">MP End Contract Comp WClutch (Apr <?= $selectedYear ?> - Mar <?= $selectedYear + 1 ?>)</h5>
        <div class="chart-container">
          <canvas id="endContractCompWClutchChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Education Monthly Charts dengan Filter Category -->
  <div class="filter-card">
    <div class="d-flex justify-content-between align-items-center">
      <div class="filter-title">🎓 Education Charts - Monthly</div>
      <div class="d-flex align-items-center gap-2">
        <label for="categoryFilter" class="form-label mb-0">Filter Category:</label>
        <select id="categoryFilter" class="form-select" style="width: auto;" onchange="filterByCategory()">
          <option value="">Semua Category</option>
          <option value="New MP" <?= $selectedCategory == 'New MP' ? 'selected' : '' ?>>New MP</option>
          <option value="Refresh MP" <?= $selectedCategory == 'Refresh MP' ? 'selected' : '' ?>>Refresh MP</option>
          <option value="Skill Up MP" <?= $selectedCategory == 'Skill Up MP' ? 'selected' : '' ?>>Skill Up MP</option>
        </select>
      </div>
    </div>
  </div>
  
  <div class="row g-4 mb-5">
    <div class="col-lg-6">
      <div class="card-box">
        <h5 class="mb-3">Edukasi Comp Assy <?= $selectedYear ?> - Bulanan<?= $selectedCategory ? ' (' . $selectedCategory . ')' : '' ?></h5>
        <div class="chart-container">
          <canvas id="educationCompAssyMonthlyChart"></canvas>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card-box">
        <h5 class="mb-3">Edukasi Comp WClutch <?= $selectedYear ?> - Bulanan<?= $selectedCategory ? ' (' . $selectedCategory . ')' : '' ?></h5>
        <div class="chart-container">
          <canvas id="educationCompWClutchMonthlyChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Education Daily Charts dengan Filter Bulan -->
  <div class="filter-card">
    <div class="d-flex justify-content-between align-items-center">
      <div class="filter-title">🎓 Education Charts - Daily</div>
      <div class="d-flex align-items-center gap-3">
        <div class="d-flex align-items-center gap-2">
          <label for="monthFilter" class="form-label mb-0">Filter Bulan:</label>
          <select id="monthFilter" class="form-select" style="width: auto;" onchange="filterByMonth()">
            <?php 
            $monthNames = [
              1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
              5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 
              9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
            ];
            foreach ($availableMonths as $monthData): 
            ?>
              <option value="<?= $monthData['month'] ?>-<?= $monthData['year'] ?>" 
                      <?= ($monthData['month'] == $selectedMonth && $monthData['year'] == $selectedMonthYear) ? 'selected' : '' ?>>
                <?= $monthNames[$monthData['month']] ?> <?= $monthData['year'] ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="d-flex align-items-center gap-2">
          <label for="dailyCategoryFilter" class="form-label mb-0">Category:</label>
          <select id="dailyCategoryFilter" class="form-select" style="width: auto;" onchange="filterByMonth()">
            <option value="">Semua Category</option>
            <option value="New MP" <?= $selectedCategory == 'New MP' ? 'selected' : '' ?>>New MP</option>
            <option value="Refresh MP" <?= $selectedCategory == 'Refresh MP' ? 'selected' : '' ?>>Refresh MP</option>
            <option value="Skill Up MP" <?= $selectedCategory == 'Skill Up MP' ? 'selected' : '' ?>>Skill Up MP</option>
          </select>
        </div>
      </div>
    </div>
  </div>
  
  <div class="row g-4 mb-5">
    <div class="col-lg-6">
      <div class="card-box">
        <h5 class="mb-3">Edukasi Comp Assy - <?= $monthNames[$selectedMonth] ?> <?= $selectedMonthYear ?> (Harian)<?= $selectedCategory ? ' (' . $selectedCategory . ')' : '' ?></h5>
        <div class="chart-container">
          <canvas id="educationCompAssyDailyChart"></canvas>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card-box">
        <h5 class="mb-3">Edukasi Comp WClutch - <?= $monthNames[$selectedMonth] ?> <?= $selectedMonthYear ?> (Harian)<?= $selectedCategory ? ' (' . $selectedCategory . ')' : '' ?></h5>
        <div class="chart-container">
          <canvas id="educationCompWClutchDailyChart"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
// Debug logs
echo "<script>console.log('End Contract Comp Assy:', " . json_encode($endContractCompAssyData) . ");</script>";
echo "<script>console.log('End Contract Comp WClutch:', " . json_encode($endContractCompWClutchData) . ");</script>";
echo "<script>console.log('Education Comp Assy Monthly:', " . json_encode($educationCompAssyMonthlyData) . ");</script>";
echo "<script>console.log('Education Comp WClutch Monthly:', " . json_encode($educationCompWClutchMonthlyData) . ");</script>";
echo "<script>console.log('Education Comp Assy Daily:', " . json_encode($educationCompAssyDailyData) . ");</script>";
echo "<script>console.log('Education Comp WClutch Daily:', " . json_encode($educationCompWClutchDailyData) . ");</script>";
?>

<script>
// Function to filter by year
function filterByYear() {
    const yearFilter = document.getElementById('yearFilter');
    const categoryFilter = document.getElementById('categoryFilter');
    const selectedYear = yearFilter.value;
    const selectedCategory = categoryFilter ? categoryFilter.value : '';
    
    const url = new URL(window.location.href);
    url.searchParams.set('year', selectedYear);
    if (selectedCategory) {
        url.searchParams.set('category', selectedCategory);
    } else {
        url.searchParams.delete('category');
    }
    window.location.href = url.toString();
}

// Function to filter by category
function filterByCategory() {
    const categoryFilter = document.getElementById('categoryFilter');
    const yearFilter = document.getElementById('yearFilter');
    const selectedCategory = categoryFilter.value;
    const selectedYear = yearFilter ? yearFilter.value : <?= $selectedYear ?>;
    
    const url = new URL(window.location.href);
    url.searchParams.set('year', selectedYear);
    if (selectedCategory) {
        url.searchParams.set('category', selectedCategory);
    } else {
        url.searchParams.delete('category');
    }
    window.location.href = url.toString();
}

// Function to filter by month for daily charts
function filterByMonth() {
    const monthFilter = document.getElementById('monthFilter');
    const dailyCategoryFilter = document.getElementById('dailyCategoryFilter');
    const yearFilter = document.getElementById('yearFilter');
    
    const selectedMonthYear = monthFilter.value.split('-');
    const selectedMonth = selectedMonthYear[0];
    const selectedYear = selectedMonthYear[1];
    const selectedCategory = dailyCategoryFilter.value;
    const fiscalYear = yearFilter ? yearFilter.value : <?= $selectedYear ?>;
    
    const url = new URL(window.location.href);
    url.searchParams.set('year', fiscalYear);
    url.searchParams.set('month', selectedMonth);
    url.searchParams.set('month_year', selectedYear);
    if (selectedCategory) {
        url.searchParams.set('category', selectedCategory);
    } else {
        url.searchParams.delete('category');
    }
    window.location.href = url.toString();
}

// Set selected values on page load
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const selectedCategory = urlParams.get('category');
    const selectedYear = urlParams.get('year');
    const selectedMonth = urlParams.get('month');
    const selectedMonthYear = urlParams.get('month_year');
    
    if (selectedCategory && document.getElementById('categoryFilter')) {
        document.getElementById('categoryFilter').value = selectedCategory;
    }
    if (selectedCategory && document.getElementById('dailyCategoryFilter')) {
        document.getElementById('dailyCategoryFilter').value = selectedCategory;
    }
    if (selectedYear && document.getElementById('yearFilter')) {
        document.getElementById('yearFilter').value = selectedYear;
    }
    if (selectedMonth && selectedMonthYear && document.getElementById('monthFilter')) {
        document.getElementById('monthFilter').value = selectedMonth + '-' + selectedMonthYear;
    }
});

// Definisikan selectedYear dari PHP ke JavaScript
const selectedYear = <?= $selectedYear ?>;
const selectedMonth = <?= $selectedMonth ?>;
const selectedMonthYear = <?= $selectedMonthYear ?>;

// Definisikan context untuk End Contract charts
const endContractCompAssyCtx = document.getElementById('endContractCompAssyChart').getContext('2d');
const endContractCompWClutchCtx = document.getElementById('endContractCompWClutchChart').getContext('2d');

// Process data untuk End Contract Comp Assy
const endContractCompAssyData = <?= json_encode($endContractCompAssyData) ?>;
const fiscalMonthNames = ['Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar'];

// Process data untuk fiscal year (Apr-Mar) - Comp Assy
const processedCompAssyData = {};
fiscalMonthNames.forEach(month => {
    processedCompAssyData[month] = { Pria: 0, Wanita: 0 };
});

endContractCompAssyData.forEach(item => {
    const monthIndex = parseInt(item.month_year.split('-')[1]) - 1;
    let fiscalMonthIndex;
    
    if (monthIndex >= 3) { // Apr-Dec (months 3-11 in 0-based index)
        fiscalMonthIndex = monthIndex - 3;
    } else { // Jan-Mar (months 0-2 in 0-based index)
        fiscalMonthIndex = monthIndex + 9;
    }
    
    const monthName = fiscalMonthNames[fiscalMonthIndex];
    processedCompAssyData[monthName][item.gender] = item.total;
});

const compAssyLabels = fiscalMonthNames;
const compAssyPriaData = fiscalMonthNames.map(month => processedCompAssyData[month].Pria);
const compAssyWanitaData = fiscalMonthNames.map(month => processedCompAssyData[month].Wanita);

// End Contract Comp Assy Chart - dengan breakdown gender (STACKED)
new Chart(endContractCompAssyCtx, {
    type: 'bar',
    data: {
        labels: compAssyLabels,
        datasets: [
            {
                label: 'Pria',
                data: compAssyPriaData,
                backgroundColor: 'rgba(59, 130, 246, 0.8)',
                borderColor: 'rgba(59, 130, 246, 1)',
                borderWidth: 1,
                stack: 'endContract'
            },
            {
                label: 'Wanita',
                data: compAssyWanitaData,
                backgroundColor: 'rgba(236, 72, 153, 0.8)',
                borderColor: 'rgba(236, 72, 153, 1)',
                borderWidth: 1,
                stack: 'endContract'
            },
            {
                label: 'Total',
                data: compAssyLabels.map((_, index) => compAssyPriaData[index] + compAssyWanitaData[index]),
                type: 'line',
                borderColor: 'rgba(245, 158, 11, 1)',
                backgroundColor: 'rgba(245, 158, 11, 0.2)',
                borderWidth: 3,
                pointRadius: 6,
                pointHoverRadius: 8,
                fill: false,
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                stacked: true,
                position: 'left'
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                beginAtZero: true,
                grid: {
                    drawOnChartArea: false
                }
            },
            x: {
                stacked: true
            }
        },
        plugins: {
            legend: {
                display: true,
                position: 'top'
            },
            datalabels: {
                display: true,
                color: 'black',
                font: {
                    weight: 'bold',
                    size: 10
                },
                formatter: function(value, context) {
                    if (context.dataset.type === 'line') {
                        return value > 0 ? value : '';
                    }
                    return value > 0 ? value : '';
                }
            }
        }
    }
});

// Process data untuk End Contract Comp WClutch
const endContractCompWClutchData = <?= json_encode($endContractCompWClutchData) ?>;

// Process data untuk fiscal year (Apr-Mar) - Comp WClutch
const processedCompWClutchData = {};
fiscalMonthNames.forEach(month => {
    processedCompWClutchData[month] = { Pria: 0, Wanita: 0 };
});

endContractCompWClutchData.forEach(item => {
    const monthIndex = parseInt(item.month_year.split('-')[1]) - 1;
    let fiscalMonthIndex;
    
    if (monthIndex >= 3) { // Apr-Dec (months 3-11 in 0-based index)
        fiscalMonthIndex = monthIndex - 3;
    } else { // Jan-Mar (months 0-2 in 0-based index)
        fiscalMonthIndex = monthIndex + 9;
    }
    
    const monthName = fiscalMonthNames[fiscalMonthIndex];
    processedCompWClutchData[monthName][item.gender] = item.total;
});

const compWClutchLabels = fiscalMonthNames;
const compWClutchPriaData = fiscalMonthNames.map(month => processedCompWClutchData[month].Pria);
const compWClutchWanitaData = fiscalMonthNames.map(month => processedCompWClutchData[month].Wanita);

// End Contract Comp WClutch Chart - dengan breakdown gender (STACKED)
new Chart(endContractCompWClutchCtx, {
    type: 'bar',
    data: {
        labels: compWClutchLabels,
        datasets: [
            {
                label: 'Pria',
                data: compWClutchPriaData,
                backgroundColor: 'rgba(139, 92, 246, 0.8)',
                borderColor: 'rgba(139, 92, 246, 1)',
                borderWidth: 1,
                stack: 'endContract'
            },
            {
                label: 'Wanita',
                data: compWClutchWanitaData,
                backgroundColor: 'rgba(16, 185, 129, 0.8)',
                borderColor: 'rgba(16, 185, 129, 1)',
                borderWidth: 1,
                stack: 'endContract'
            },
            {
                label: 'Total',
                data: compWClutchLabels.map((_, index) => compWClutchPriaData[index] + compWClutchWanitaData[index]),
                type: 'line',
                borderColor: 'rgba(245, 158, 11, 1)',
                backgroundColor: 'rgba(245, 158, 11, 0.2)',
                borderWidth: 3,
                pointRadius: 6,
                pointHoverRadius: 8,
                fill: false,
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                stacked: true,
                position: 'left'
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                beginAtZero: true,
                grid: {
                    drawOnChartArea: false
                }
            },
            x: {
                stacked: true
            }
        },
        plugins: {
            legend: {
                display: true,
                position: 'top'
            },
            datalabels: {
                display: true,
                color: 'black',
                font: {
                    weight: 'bold',
                    size: 10
                },
                formatter: function(value, context) {
                    if (context.dataset.type === 'line') {
                        return value > 0 ? value : '';
                    }
                    return value > 0 ? value : '';
                }
            }
        }
    }
});

// Education Comp Assy Monthly Chart
const educationCompAssyMonthlyCtx = document.getElementById('educationCompAssyMonthlyChart').getContext('2d');
const monthNames = ['Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar'];
const compAssyMonthlyData = <?= json_encode($educationCompAssyMonthlyData) ?>;

// Create arrays for all 12 months (Apr-Mar)
const compAssyMonthlyLabels = monthNames;
const compAssyMonthlyPlanned = new Array(12).fill(0);
const compAssyMonthlyHadir = new Array(12).fill(0);
const compAssyMonthlyTidakHadir = new Array(12).fill(0);

// Fill data based on fiscal_month
compAssyMonthlyData.forEach(item => {
    const index = item.fiscal_month - 1; // fiscal_month is 1-12, array is 0-11
    if (index >= 0 && index < 12) {
        compAssyMonthlyPlanned[index] = item.planned;
        compAssyMonthlyHadir[index] = item.hadir;
        compAssyMonthlyTidakHadir[index] = item.tidak_hadir;
    }
});

new Chart(educationCompAssyMonthlyCtx, {
    type: 'bar',
    data: {
        labels: compAssyMonthlyLabels,
        datasets: [
            {
                label: 'Hadir',
                data: compAssyMonthlyHadir,
                backgroundColor: 'rgba(16, 185, 129, 0.8)',
                stack: 'attendance'
            },
            {
                label: 'Tidak Hadir',
                data: compAssyMonthlyTidakHadir,
                backgroundColor: 'rgba(239, 68, 68, 0.8)',
                stack: 'attendance'
            },
            {
                label: 'Planned',
                data: compAssyMonthlyPlanned,
                type: 'line',
                borderColor: 'rgba(59, 130, 246, 1)',
                backgroundColor: 'rgba(59, 130, 246, 0.2)',
                borderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7,
                fill: false
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            datalabels: {
                display: true,
                color: 'black',
                font: {
                    weight: 'bold',
                    size: 10
                },
                formatter: function(value, context) {
                    return value > 0 ? value : '';
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                stacked: true
            },
            x: {
                stacked: true
            }
        }
    }
});

// Education Comp WClutch Monthly Chart
const educationCompWClutchMonthlyCtx = document.getElementById('educationCompWClutchMonthlyChart').getContext('2d');
const compWClutchMonthlyData = <?= json_encode($educationCompWClutchMonthlyData) ?>;

// Create arrays for all 12 months (Apr-Mar)
const compWClutchMonthlyLabels = monthNames;
const compWClutchMonthlyPlanned = new Array(12).fill(0);
const compWClutchMonthlyHadir = new Array(12).fill(0);
const compWClutchMonthlyTidakHadir = new Array(12).fill(0);

// Fill data based on fiscal_month
compWClutchMonthlyData.forEach(item => {
    const index = item.fiscal_month - 1; // fiscal_month is 1-12, array is 0-11
    if (index >= 0 && index < 12) {
        compWClutchMonthlyPlanned[index] = item.planned;
        compWClutchMonthlyHadir[index] = item.hadir;
        compWClutchMonthlyTidakHadir[index] = item.tidak_hadir;
    }
});

new Chart(educationCompWClutchMonthlyCtx, {
    type: 'bar',
    data: {
        labels: compWClutchMonthlyLabels,
        datasets: [
            {
                label: 'Hadir',
                data: compWClutchMonthlyHadir,
                backgroundColor: 'rgba(16, 185, 129, 0.8)',
                stack: 'attendance'
            },
            {
                label: 'Tidak Hadir',
                data: compWClutchMonthlyTidakHadir,
                backgroundColor: 'rgba(239, 68, 68, 0.8)',
                stack: 'attendance'
            },
            {
                label: 'Planned',
                data: compWClutchMonthlyPlanned,
                type: 'line',
                borderColor: 'rgba(139, 92, 246, 1)',
                backgroundColor: 'rgba(139, 92, 246, 0.2)',
                borderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7,
                fill: false
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            datalabels: {
                display: true,
                color: 'black',
                font: {
                    weight: 'bold',
                    size: 10
                },
                formatter: function(value, context) {
                    return value > 0 ? value : '';
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                stacked: true
            },
            x: {
                stacked: true
            }
        }
    }
});

// Education Daily Charts
const educationCompAssyDailyCtx = document.getElementById('educationCompAssyDailyChart').getContext('2d');
const educationCompWClutchDailyCtx = document.getElementById('educationCompWClutchDailyChart').getContext('2d');

// Process daily data for Comp Assy
const compAssyDailyData = <?= json_encode($educationCompAssyDailyData) ?>;

// Get number of days in the selected month
const daysInMonth = new Date(selectedMonthYear, selectedMonth, 0).getDate();
const dayLabels = Array.from({length: daysInMonth}, (_, i) => i + 1);

// Initialize arrays for all days in month
const compAssyDailyPlanned = new Array(daysInMonth).fill(0);
const compAssyDailyHadir = new Array(daysInMonth).fill(0);
const compAssyDailyTidakHadir = new Array(daysInMonth).fill(0);

// Fill data for days that have education data
compAssyDailyData.forEach(item => {
    const dayIndex = item.day - 1; // day is 1-based, array is 0-based
    if (dayIndex >= 0 && dayIndex < daysInMonth) {
        compAssyDailyPlanned[dayIndex] = item.planned;
        compAssyDailyHadir[dayIndex] = item.hadir;
        compAssyDailyTidakHadir[dayIndex] = item.tidak_hadir;
    }
});

// Comp Assy Daily Chart
new Chart(educationCompAssyDailyCtx, {
    type: 'bar',
    data: {
        labels: dayLabels,
        datasets: [
            {
                label: 'Hadir',
                data: compAssyDailyHadir,
                backgroundColor: 'rgba(16, 185, 129, 0.8)',
                stack: 'attendance'
            },
            {
                label: 'Tidak Hadir',
                data: compAssyDailyTidakHadir,
                backgroundColor: 'rgba(239, 68, 68, 0.8)',
                stack: 'attendance'
            },
            {
                label: 'Planned',
                data: compAssyDailyPlanned,
                type: 'line',
                borderColor: 'rgba(59, 130, 246, 1)',
                backgroundColor: 'rgba(59, 130, 246, 0.2)',
                borderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                fill: false
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            datalabels: {
                display: true,
                color: 'black',
                font: {
                    weight: 'bold',
                    size: 8
                },
                formatter: function(value, context) {
                    return value > 0 ? value : '';
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                stacked: true
            },
            x: {
                stacked: true,
                title: {
                    display: true,
                    text: 'Tanggal'
                }
            }
        }
    }
});

// Process daily data for Comp WClutch
const compWClutchDailyData = <?= json_encode($educationCompWClutchDailyData) ?>;

// Initialize arrays for all days in month
const compWClutchDailyPlanned = new Array(daysInMonth).fill(0);
const compWClutchDailyHadir = new Array(daysInMonth).fill(0);
const compWClutchDailyTidakHadir = new Array(daysInMonth).fill(0);

// Fill data for days that have education data
compWClutchDailyData.forEach(item => {
    const dayIndex = item.day - 1; // day is 1-based, array is 0-based
    if (dayIndex >= 0 && dayIndex < daysInMonth) {
        compWClutchDailyPlanned[dayIndex] = item.planned;
        compWClutchDailyHadir[dayIndex] = item.hadir;
        compWClutchDailyTidakHadir[dayIndex] = item.tidak_hadir;
    }
});

// Comp WClutch Daily Chart
new Chart(educationCompWClutchDailyCtx, {
    type: 'bar',
    data: {
        labels: dayLabels,
        datasets: [
            {
                label: 'Hadir',
                data: compWClutchDailyHadir,
                backgroundColor: 'rgba(16, 185, 129, 0.8)',
                stack: 'attendance'
            },
            {
                label: 'Tidak Hadir',
                data: compWClutchDailyTidakHadir,
                backgroundColor: 'rgba(239, 68, 68, 0.8)',
                stack: 'attendance'
            },
            {
                label: 'Planned',
                data: compWClutchDailyPlanned,
                type: 'line',
                borderColor: 'rgba(139, 92, 246, 1)',
                backgroundColor: 'rgba(139, 92, 246, 0.2)',
                borderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                fill: false
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            datalabels: {
                display: true,
                color: 'black',
                font: {
                    weight: 'bold',
                    size: 8
                },
                formatter: function(value, context) {
                    return value > 0 ? value : '';
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                stacked: true
            },
            x: {
                stacked: true,
                title: {
                    display: true,
                    text: 'Tanggal'
                }
            }
        }
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>