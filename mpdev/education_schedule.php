<?php
require_once 'auth_check.php';
checkAuth(['admin', 'manager', 'spv', 'trainer', 'leader', 'foreman']);
require_once '../backend/database.php';

$db = new Database();
$conn = $db->getConnection();

// Handle date filters
$whereClause = "";
$params = [];

if (isset($_GET['filter_date']) && !empty($_GET['filter_date'])) {
    $whereClause .= " AND DATE(e.dateEdukasi) = :filter_date";
    $params[':filter_date'] = $_GET['filter_date'];
}

if (isset($_GET['filter_status']) && !empty($_GET['filter_status'])) {
    $whereClause .= " AND es.status = :filter_status";
    $params[':filter_status'] = $_GET['filter_status'];
}

// Handle POST request for updating status only
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['update_status'])) {
            // Check if schedule already exists
            $checkStmt = $conn->prepare("SELECT id FROM education_schedule WHERE education_id = ?");
            $checkStmt->execute([$_POST['education_id']]);
            
            if ($checkStmt->rowCount() > 0) {
                // Update existing schedule
                $sql = "UPDATE education_schedule SET time = :time, status = :status, note = :note WHERE education_id = :education_id";
            } else {
                // Insert new schedule
                $sql = "INSERT INTO education_schedule (education_id, npk, name, section, line, leader, category, namaPos, date, time, status, note) 
                        SELECT id, npk, name, section, line, leader, category, namaPos, dateEdukasi, :time, :status, :note 
                        FROM education WHERE id = :education_id";
            }
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':education_id' => $_POST['education_id'],
                ':time' => $_POST['time'],
                ':status' => $_POST['status'],
                ':note' => $_POST['note']
            ]);
            
            header("Location: education_schedule.php?success=1");
            exit();
        }
    } catch (Exception $e) {
        $error_message = "Terjadi kesalahan: " . $e->getMessage();
    }
}

// Get filtered education data with schedule info
try {
    $sql = "SELECT e.*, 
                   es.time, es.status, es.note, es.id as schedule_id
            FROM education e 
            LEFT JOIN education_schedule es ON e.id = es.education_id 
            WHERE 1=1" . $whereClause . "
            ORDER BY e.dateEdukasi ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $educationData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $educationData = [];
    $error_message = "Gagal mengambil data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Jadwal Edukasi - MP Development</title>
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
    
    .bg-primary {
      background-color: #3b82f6 !important;
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
      color: #1e40af;
      border-left: 4px solid #3b82f6;
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
    📆 Jadwal Edukasi
  </div>
  
  <!-- Alerts -->
  <?php if (isset($_GET['success'])): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    ✅ Jadwal edukasi berhasil diupdate!
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>
  
  <?php if (isset($error_message)): ?>
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    ❌ <?= htmlspecialchars($error_message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>
  
  <!-- Filter Section -->
  <div class="filter-card">
    <div class="filter-title">🔍 Filter Jadwal</div>
    <form method="GET" class="row g-3">
      <div class="col-lg-3 col-md-4">
        <label class="form-label">Tanggal</label>
        <input type="date" name="filter_date" class="form-control" 
               value="<?= isset($_GET['filter_date']) ? htmlspecialchars($_GET['filter_date']) : '' ?>">
      </div>
      <div class="col-lg-3 col-md-4">
        <label class="form-label">Status</label>
        <select name="filter_status" class="form-select">
          <option value="">Semua Status</option>
          <option value="Hadir" <?= (isset($_GET['filter_status']) && $_GET['filter_status'] == 'Hadir') ? 'selected' : '' ?>>Hadir</option>
          <option value="Tidak Hadir" <?= (isset($_GET['filter_status']) && $_GET['filter_status'] == 'Tidak Hadir') ? 'selected' : '' ?>>Tidak Hadir</option>
          <option value="Belum ditentukan" <?= (isset($_GET['filter_status']) && $_GET['filter_status'] == 'Belum ditentukan') ? 'selected' : '' ?>>Belum Ditentukan</option>
        </select>
      </div>
      <div class="col-lg-6 col-md-12 d-flex align-items-end gap-2">
        <button type="submit" class="btn btn-primary">🔍 Filter</button>
        <a href="education_schedule.php" class="btn btn-secondary">🔄 Reset</a>
        <button type="button" class="btn btn-info" onclick="setToday()">📅 Hari Ini</button>
      </div>
    </form>
  </div>
  
  <!-- Info Alert -->
  <div class="alert alert-info">
    <strong>ℹ️ Info:</strong> Semua data edukasi otomatis ditampilkan sebagai jadwal. Klik "Set Jadwal" untuk mengatur waktu dan status kehadiran.
  </div>
  
  <!-- Main Data Section -->
  <div class="data-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h5 class="mb-0">📋 Jadwal Edukasi</h5>
      <a href="education.php" class="btn btn-primary">
        ➕ Tambah Data Edukasi
      </a>
    </div>
    
    <div class="table-responsive">
      <table id="scheduleTable" class="table">
        <thead class="table-dark">
          <tr>
            <th>NPK</th>
            <th>Name</th>
            <th>Section</th>
            <th>Line</th>
            <th>Leader</th>
            <th>Category</th>
            <th>Nama Pos</th>
            <th>Tanggal Edukasi</th>
            <th>Waktu Jadwal</th>
            <th>Status Kehadiran</th>
            <th>Note</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($educationData as $row): ?>
          <tr>
            <td><strong><?= htmlspecialchars($row['npk']) ?></strong></td>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <td><?= htmlspecialchars($row['section']) ?></td>
            <td><?= htmlspecialchars($row['line']) ?></td>
            <td><?= htmlspecialchars($row['leader']) ?></td>
            <td>
              <span class="badge bg-primary">
                <?= htmlspecialchars($row['category']) ?>
              </span>
            </td>
            <td><?= htmlspecialchars($row['namaPos']) ?></td>
            <td><?= date('d/m/Y', strtotime($row['dateEdukasi'])) ?></td>
            <td>
              <?php if ($row['time']): ?>
                <span class="badge bg-primary"><?= htmlspecialchars($row['time']) ?></span>
              <?php else: ?>
                <span class="badge bg-secondary">Belum Dijadwalkan</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($row['status']): ?>
                <span class="badge bg-<?= $row['status'] === 'Hadir' ? 'success' : ($row['status'] === 'Tidak Hadir' ? 'danger' : 'secondary') ?>">
                  <?= htmlspecialchars($row['status']) ?>
                </span>
              <?php else: ?>
                <span class="badge bg-secondary">Belum Absen</span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($row['note'] ?: '-') ?></td>
            <td>
              <button class="btn btn-sm btn-warning" onclick="setSchedule(<?= $row['id'] ?>, '<?= htmlspecialchars($row['npk']) ?>', '<?= htmlspecialchars($row['name']) ?>', '<?= htmlspecialchars($row['time'] ?: '') ?>', '<?= htmlspecialchars($row['status'] ?: '') ?>', '<?= htmlspecialchars($row['note'] ?: '') ?>')">
                ⚙️ Set Jadwal
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal Set Jadwal -->
<div class="modal fade" id="scheduleModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">⚙️ Set Jadwal & Status Kehadiran</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <div class="modal-body">
          <input type="hidden" name="education_id" id="education_id">
          
          <div class="mb-3">
            <label class="form-label">👤 NPK - Nama</label>
            <input type="text" id="employee_info" class="form-control" readonly>
          </div>
          
          <div class="mb-3">
            <label class="form-label">⏰ Waktu Jadwal</label>
            <select name="time" id="schedule_time" class="form-select" required>
              <option value="">Pilih Waktu Jadwal</option>
              <option value="08:00-11:45">08:00-11:45 (Shift 1)</option>
              <option value="13:00-16:30">13:00-16:30 (Shift 2)</option>
              <option value="16:40-20:00">16:40-20:00 (Shift 3)</option>
              <option value="Custom">Custom Time</option>
            </select>
            <small class="text-muted">Pilih waktu jadwal edukasi sesuai shift</small>
          </div>
          
          <div class="mb-3">
            <label class="form-label">✅ Status Kehadiran</label>
            <select name="status" id="attendance_status" class="form-select">
              <option value="Belum ditentukan">Belum Ditentukan</option>
              <option value="Hadir">Hadir</option>
              <option value="Tidak Hadir">Tidak Hadir</option>
            </select>
            <small class="text-muted">Update status kehadiran setelah pelaksanaan edukasi</small>
          </div>
          
          <div class="mb-3">
            <label class="form-label">📝 Note</label>
            <textarea name="note" id="schedule_note" class="form-control" rows="3" placeholder="Tambahkan catatan jadwal atau keterangan tambahan..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">❌ Batal</button>
          <button type="submit" name="update_status" class="btn btn-primary">💾 Simpan Jadwal</button>
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
  $('#scheduleTable').DataTable({
    responsive: true,
    language: {
      url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
    },
    order: [[7, 'asc']], // Sort by education date
    pageLength: 25,
    dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
  });
});

function setSchedule(educationId, npk, name, time, status, note) {
  document.getElementById('education_id').value = educationId;
  document.getElementById('employee_info').value = npk + ' - ' + name;
  document.getElementById('schedule_time').value = time;
  document.getElementById('attendance_status').value = status;
  document.getElementById('schedule_note').value = note;
  
  const scheduleModal = new bootstrap.Modal(document.getElementById('scheduleModal'));
  scheduleModal.show();
}

function setToday() {
  const today = new Date().toISOString().split('T')[0];
  document.querySelector('input[name="filter_date"]').value = today;
  document.querySelector('form').submit();
}

// File validation and form enhancements
document.addEventListener('DOMContentLoaded', function() {
  // Smooth animation for cards
  const cards = document.querySelectorAll('.filter-card, .data-card');
  cards.forEach((card, index) => {
    card.style.opacity = '0';
    card.style.transform = 'translateY(20px)';
    
    setTimeout(() => {
      card.style.transition = 'all 0.5s ease';
      card.style.opacity = '1';
      card.style.transform = 'translateY(0)';
    }, index * 100);
  });
  
  // Enhanced hover effects
  cards.forEach(card => {
    card.addEventListener('mouseenter', function() {
      this.style.transform = 'translateY(-2px)';
    });
    
    card.addEventListener('mouseleave', function() {
      this.style.transform = 'translateY(0)';
    });
  });
  
  // Button hover effects
  const buttons = document.querySelectorAll('.btn');
  buttons.forEach(button => {
    button.addEventListener('mouseenter', function() {
      this.style.transform = 'translateY(-1px)';
    });
    
    button.addEventListener('mouseleave', function() {
      this.style.transform = 'translateY(0)';
    });
  });
});

// Alert auto-hide
setTimeout(function() {
  const alerts = document.querySelectorAll('.alert');
  alerts.forEach(alert => {
    if (alert.classList.contains('alert-success')) {
      alert.style.transition = 'opacity 0.5s ease';
      alert.style.opacity = '0';
      setTimeout(() => {
        if (alert.parentNode) {
          alert.parentNode.removeChild(alert);
        }
      }, 500);
    }
  });
}, 5000);
</script>
</body>
</html>