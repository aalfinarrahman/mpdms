<?php
require_once 'auth_check.php';
checkAuth(['admin', 'manager', 'spv']); // Only allow these roles to access workspace
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Workspace - MP Development</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="css/sidebar.css" rel="stylesheet">
  <style>
    body {
        background: url('assets/images/factory-background.jpg') no-repeat center center fixed;
        background-size: cover;
        font-family: 'Segoe UI', sans-serif;
        margin: 0;
        min-height: 100vh;
        color: #334155;
    }
    
    .workspace-container {
      padding: 40px 20px;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }
    
    .workspace-header {
      text-align: center;
      margin-bottom: 50px;
      color: #1e293b;
    }
    
    .workspace-header h1 {
      font-size: 2.5rem;
      font-weight: 600;
      margin-bottom: 15px;
      color: #0f172a;
    }
    
    .workspace-header p {
      font-size: 1.1rem;
      color: #64748b;
      margin-bottom: 0;
      font-weight: 400;
    }
    
    .workspace-grid {
      max-width: 1200px;
      margin: 0 auto;
    }
    
    .workspace-card {
      background: white;
      border-radius: 12px;
      padding: 30px 25px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
      transition: all 0.3s ease;
      cursor: pointer;
      border: 1px solid #e2e8f0;
      height: 160px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      text-align: center;
      text-decoration: none;
      color: inherit;
    }
    
    .workspace-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
      border-color: #cbd5e1;
      color: inherit;
      text-decoration: none;
    }
    
    .workspace-card .icon {
      font-size: 2.5rem;
      margin-bottom: 15px;
      display: block;
    }
    
    .workspace-card .title {
      font-size: 1.1rem;
      font-weight: 600;
      color: #1e293b;
      margin-bottom: 8px;
    }
    
    .workspace-card .description {
      font-size: 0.9rem;
      color: #64748b;
      margin: 0;
      font-weight: 400;
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
    
    .workspace-main {
      margin-top: 80px;
    }
    
    /* Warna yang lebih santai untuk setiap card */
    .card-dashboard .icon { color: #3b82f6; }
    .card-employees .icon { color: #ec4899; }
    .card-contracts .icon { color: #06b6d4; }
    .card-replacement .icon { color: #10b981; }
    .card-education .icon { color: #f59e0b; }
    .card-schedule .icon { color: #8b5cf6; }
    .card-mapping .icon { color: #ef4444; }
    .card-skill1 .icon { color: #14b8a6; }
    .card-skill2 .icon { color: #f97316; }
    .card-overtime .icon { color: #6366f1; }
    
    /* Hover effects untuk icon */
    .workspace-card:hover .icon {
      transform: scale(1.1);
    }
    
    @media (max-width: 768px) {
      .workspace-header h1 {
        font-size: 2rem;
      }
      
      .workspace-header p {
        font-size: 1rem;
      }
      
      .workspace-card {
        height: 140px;
        padding: 20px 15px;
      }
      
      .workspace-card .icon {
        font-size: 2rem;
        margin-bottom: 10px;
      }
      
      .workspace-card .title {
        font-size: 1rem;
      }
      
      .workspace-card .description {
        font-size: 0.8rem;
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
  </style>
</head>
<body>

<!-- Top Navigation -->
<div class="top-nav">
  <div class="d-flex justify-content-between align-items-center">
    <a href="workspace.php" class="nav-brand">🛠️ Man Power Development Management System</a>
    <div class="nav-actions">
      <a href="settings.php" class="nav-btn">⚙️ Settings</a>
      <a href="logout.php" class="nav-btn">🚪 Logout</a>
    </div>
  </div>
</div>

<!-- Main Workspace -->
<div class="workspace-main">
  <div class="workspace-container">
    
    <!-- Header -->
    <div class="workspace-header">
      <h1>🏠 Workspace</h1>
      <p>Pilih modul yang ingin Anda akses untuk mengelola MP Development</p>
    </div>
    
    <!-- Workspace Grid -->
    <div class="workspace-grid">
      <div class="row g-4">
        
        <div class="col-lg-3 col-md-4 col-sm-6">
          <a href="dashboard.php" class="workspace-card card-dashboard">
            <span class="icon">📊</span>
            <div class="title">Dashboard</div>
            <p class="description">Statistik & Analytics MP</p>
          </a>
        </div>
        
        <div class="col-lg-3 col-md-4 col-sm-6">
          <a href="employees.php" class="workspace-card card-employees">
            <span class="icon">👥</span>
            <div class="title">Database MP</div>
            <p class="description">Kelola Data Karyawan</p>
          </a>
        </div>
        
        <div class="col-lg-3 col-md-4 col-sm-6">
          <a href="end_contracts.php" class="workspace-card card-contracts">
            <span class="icon">📅</span>
            <div class="title">End Contract</div>
            <p class="description">Kontrak Berakhir</p>
          </a>
        </div>
        
        <div class="col-lg-3 col-md-4 col-sm-6">
          <a href="replacement.php" class="workspace-card card-replacement">
            <span class="icon">🔄</span>
            <div class="title">Replacement</div>
            <p class="description">Penggantian MP</p>
          </a>
        </div>
        
        <div class="col-lg-3 col-md-4 col-sm-6">
          <a href="education.php" class="workspace-card card-education">
            <span class="icon">📚</span>
            <div class="title">Edukasi</div>
            <p class="description">Program Edukasi MP</p>
          </a>
        </div>
        
        <div class="col-lg-3 col-md-4 col-sm-6">
          <a href="education_schedule.php" class="workspace-card card-schedule">
            <span class="icon">🗓️</span>
            <div class="title">Jadwal Edukasi</div>
            <p class="description">Penjadwalan Edukasi</p>
          </a>
        </div>
        
        <div class="col-lg-3 col-md-4 col-sm-6">
          <a href="mapping.php" class="workspace-card card-mapping">
            <span class="icon">🗺️</span>
            <div class="title">Mapping MP</div>
            <p class="description">Pemetaan Posisi MP</p>
          </a>
        </div>
        
        <div class="col-lg-3 col-md-4 col-sm-6">
          <a href="sk_comp_assy.php" class="workspace-card card-skill1">
            <span class="icon">⚙️</span>
            <div class="title">SK Comp Assy</div>
            <p class="description">Skill Matrix Comp Assy</p>
          </a>
        </div>
        
        <div class="col-lg-3 col-md-4 col-sm-6">
          <a href="sk_wclutch.php" class="workspace-card card-skill2">
            <span class="icon">🔧</span>
            <div class="title">SK WClutch</div>
            <p class="description">Skill Matrix WClutch</p>
          </a>
        </div>
        
        <div class="col-lg-3 col-md-4 col-sm-6">
          <a href="overtime.php" class="workspace-card card-overtime">
            <span class="icon">⏰</span>
            <div class="title">Overtime</div>
            <p class="description">Manajemen Lembur</p>
          </a>
        </div>
        
      </div>
    </div>
    
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Smooth animations
document.addEventListener('DOMContentLoaded', function() {
  const cards = document.querySelectorAll('.workspace-card');
  
  // Animate cards on load
  cards.forEach((card, index) => {
    card.style.opacity = '0';
    card.style.transform = 'translateY(20px)';
    
    setTimeout(() => {
      card.style.transition = 'all 0.5s ease';
      card.style.opacity = '1';
      card.style.transform = 'translateY(0)';
    }, index * 100);
  });
  
  // Hover effects
  cards.forEach(card => {
    card.addEventListener('mouseenter', function() {
      this.style.transform = 'translateY(-3px)';
    });
    
    card.addEventListener('mouseleave', function() {
      this.style.transform = 'translateY(0)';
    });
  });
});
</script>

</body>
</html>