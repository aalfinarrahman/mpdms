# 🏭 MP Development System

Sistem manajemen manpower yang modern dan profesional untuk mengelola data karyawan, kontrak, edukasi, skill matrix, dan overtime dengan teknologi PHP dan MySQL.

## 📋 Table of Contents
- [Features](#-features)
- [Tech Stack](#-tech-stack)
- [Installation](#-installation)
- [Usage](#-usage)
- [File Structure](#-file-structure)
- [Database Schema](#-database-schema)
- [Modules](#-modules)
- [Contributing](#-contributing)

## ✨ Features

### 🎯 Core Modules
- **🏠 Dashboard** - Overview dan statistik real-time dengan charts
- **📋 Database MP** - CRUD lengkap untuk data karyawan
- **📅 End Contract** - Manajemen kontrak berakhir
- **🔁 Replacement** - Tracking proses replacement karyawan
- **🎓 Edukasi** - Manajemen program pelatihan dan sertifikasi
- **📆 Jadwal Edukasi** - Penjadwalan program edukasi
- **🗺️ Mapping MP** - Pemetaan manpower per section dan line
- **📊 SK_CompAssy** - Skill Matrix untuk Comp Assy (47 proses)
- **📊 SK_WClutch** - Skill Matrix untuk WClutch (11 proses)
- **⏰ Overtime** - Manajemen data overtime karyawan
- **🏢 Workspace** - Central hub untuk semua modul
- **⚙️ Settings** - Konfigurasi sistem

### 🎨 UI/UX Features
- ✅ Modern & Professional Design dengan Bootstrap 5
- ✅ Fully Responsive (Desktop, Tablet, Mobile)
- ✅ Sidebar Navigation dengan Active States
- ✅ Interactive Charts dengan Chart.js
- ✅ DataTables untuk tabel interaktif
- ✅ Modal Forms untuk input data
- ✅ Real-time Search & Filter
- ✅ Status Indicators dan Badges
- ✅ Notification System
- ✅ Export functionality (Excel, CSV)

### ⚡ Functionality
- ✅ Authentication & Authorization System
- ✅ Role-based Access Control (Admin, Manager, SPV, Trainer, Leader, Foreman)
- ✅ CRUD Operations untuk semua modul
- ✅ Auto-generate NPK
- ✅ Data Validation (Client & Server side)
- ✅ Export to Excel/CSV
- ✅ Real-time Charts dan Statistics
- ✅ Advanced Filtering dan Search
- ✅ Skill Matrix Management
- ✅ Education Tracking
- ✅ Contract Management
- ✅ Backup & Restore System

## 🛠 Tech Stack

### Backend
- **PHP 8.0+** - Server-side scripting
- **MySQL/MariaDB** - Database management
- **PDO** - Database abstraction layer
- **PHPMailer** - Email functionality

### Frontend
- **HTML5 & CSS3** - Structure and styling
- **Bootstrap 5.3.3** - UI framework
- **JavaScript (ES6+)** - Client-side functionality
- **jQuery 3.7.0** - DOM manipulation
- **DataTables 1.13.6** - Advanced table features
- **Chart.js** - Data visualization
- **Select2** - Enhanced select boxes

### Development Tools
- **Composer** - PHP dependency management
- **Git** - Version control
- **VS Code** - Recommended IDE

## 🚀 Installation

### Prerequisites
- **XAMPP/WAMP/MAMP** atau server lokal dengan:
  - PHP 8.0 atau lebih tinggi
  - MySQL 5.7 atau lebih tinggi
  - Apache Web Server
- **Composer** (untuk dependency management)
- **Git** (optional)

### Setup Steps

1. **Clone atau Download Project**
   ```bash
   git clone <repository-url>
   cd MP_dev

  2.Install Dependencies
  composer install

  3. Database Setup

- Buat database baru di MySQL: mp_development
- Import schema dari sql/schema.sql

CREATE DATABASE mp_development;
USE mp_development;
SOURCE sql/schema.sql;

4. Environment Configuration

- Copy .env.example ke .env (jika ada)
- Edit backend/database.php sesuai konfigurasi database Anda

private $host = "localhost";
private $db_name = "mp_development";
private $username = "your_username";
private $password = "your_password";

5. Web Server Setup

- Pindahkan folder project ke htdocs (XAMPP) atau www (WAMP)
- Atau setup virtual host
- Akses melalui: http://localhost/MP_dev/mpdev/

6. Test Connection
php test_connection.php

7.File Structure
   MP_dev/
│
├── 📄 README.md              # Dokumentasi project
├── 📄 .env                   # Environment variables
├── 📄 composer.json          # PHP dependencies
├── 📄 test_connection.php    # Database connection test
│
├── 📁 assets/                # Static assets
│   ├── 📄 favicon.ico
│   ├── 📁 icons/            # App icons
│   └── 📁 images/           # Images
│
├── 📁 backend/               # Backend logic
│   └── 📄 database.php      # Database connection class
│
├── 📁 backups/               # Database backups
│   ├── 📄 backup_*.json     # JSON backups
│   └── 📄 backup_*.csv      # CSV backups
│
├── 📁 sql/                   # Database schema
│   └── 📄 schema.sql        # Database structure
│
├── 📁 vendor/                # Composer dependencies
│   ├── 📁 phpmailer/        # Email library
│   └── 📄 autoload.php      # Composer autoloader
│
└── 📁 mpdev/                 # Main application
    ├── 📄 auth_check.php     # Authentication middleware
    ├── 📄 login.php          # Login page
    ├── 📄 logout.php         # Logout handler
    ├── 📄 unauthorized.php   # Access denied page
    │
    ├── 📁 css/               # Stylesheets
    │   ├── 📄 sidebar.css    # Sidebar styling
    │   ├── 📄 dashboard.css  # Dashboard specific
    │   ├── 📄 employees.css  # Employee module
    │   ├── 📄 login-style.css # Login page
    │   └── 📄 style.css      # Global styles
    │
    ├── 📁 js/                # JavaScript files
    │   ├── 📄 script.js      # Main functionality
    │   ├── 📄 employees.js   # Employee module
    │   ├── 📄 auth.js        # Authentication
    │   └── 📄 browser-info.js # Browser detection
    │
    ├── 📄 dashboard.php      # Main dashboard
    ├── 📄 workspace.php      # Workspace hub
    ├── 📄 employees.php      # Employee management
    ├── 📄 education.php      # Education management
    ├── 📄 education_schedule.php # Education scheduling
    ├── 📄 end_contracts.php  # Contract management
    ├── 📄 replacement.php    # Replacement tracking
    ├── 📄 mapping.php        # Manpower mapping
    ├── 📄 sk_comp_assy.php   # Skill Matrix Comp Assy
    ├── 📄 sk_wclutch.php     # Skill Matrix WClutch
    ├── 📄 overtime.php       # Overtime management
    └── 📄 settings.php       # System settings

  ##  Database Schema
### Main Tables
- employees - Data karyawan utama
- education - Data program edukasi
- end_contracts - Data kontrak berakhir
- replacement - Data replacement karyawan
- skill_matrix_comp_assy - Skill matrix Comp Assy
- skill_matrix_wclutch - Skill matrix WClutch
- overtime - Data overtime karyawan
- users - User authentication
### Key Features
- Foreign key relationships
- Indexed columns untuk performance
- Timestamp tracking (created_at, updated_at)
- Soft delete support
## 📱 Modules
### 🏠 Dashboard
- Real-time statistics dan KPI
- Interactive charts (Chart.js)
- Summary cards untuk setiap modul
- Filter berdasarkan periode
### 📋 Database MP
- CRUD lengkap untuk data karyawan
- Auto-generate NPK
- Advanced search dan filter
- Export ke Excel/CSV
- Bulk operations
### 🎓 Edukasi
- Manajemen program pelatihan
- Tracking sertifikasi
- Searchable "Nama Pos" dengan Select2
- Status tracking (Planned, Completed, etc.)
### 📊 Skill Matrix
- Comp Assy : 47 proses kerja
- WClutch : 11 proses kerja
- Visual skill indicators (0-4 levels)
- Sticky columns untuk easy navigation
- Real-time skill level updates
### 🗺️ Mapping MP
- Pemetaan karyawan per section dan line
- Interactive charts untuk visualisasi
- Filter berdasarkan section, line, status
- Summary cards untuk quick overview
### ⏰ Overtime
- Tracking jam overtime karyawan
- Kalkulasi otomatis
- Reporting dan analytics
- Export functionality
## 🔐 Authentication & Authorization
### User Roles
- Admin - Full access ke semua modul
- Manager - Management level access
- SPV - Supervisor level access
- Trainer - Education module focus
- Leader - Team leader access
- Foreman - Operational access
### Security Features
- Session-based authentication
- Role-based access control
- SQL injection protection (PDO prepared statements)
- XSS protection
- CSRF protection
## 💡 Usage
### Login
1. Akses http://localhost/MP_dev/mpdev/login.php
2. Masukkan credentials
3. Sistem akan redirect ke dashboard sesuai role
### Navigation
- Gunakan sidebar untuk navigasi antar modul
- Workspace sebagai central hub
- Breadcrumb navigation
### Data Management
- Setiap modul memiliki CRUD functionality
- Real-time search dan filter
- Export data ke Excel/CSV
- Bulk operations untuk efficiency
## 🔧 Development
### Adding New Module
1. Buat file PHP baru di mpdev/
2. Include auth_check.php untuk authentication
3. Tambahkan navigation link di sidebar
4. Buat CSS specific jika diperlukan
5. Update database schema jika perlu

   Database Operations
   // Contoh penggunaan database class
require_once '../backend/database.php';
$db = new Database();
$conn = $db->getConnection();

$stmt = $conn->prepare("SELECT * FROM employees WHERE status = ?");
$stmt->execute(['Aktif']);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

Adding Charts
// Contoh implementasi Chart.js
const ctx = document.getElementById('myChart').getContext('2d');
const chart = new Chart(ctx, {
    type: 'bar',
    data: chartData,
    options: chartOptions
});


##  Future Enhancements
- API REST untuk mobile app
- Real-time notifications dengan WebSocket
- Advanced reporting dengan PDF export
- Multi-language support
- Dark/Light theme toggle
- Advanced analytics dashboard
- Mobile app (React Native/Flutter)
- Integration dengan HR systems
- Automated backup scheduling
- Performance monitoring
## 🐛 Troubleshooting
### Common Issues
1. Database Connection Error
   
   - Check database credentials di backend/database.php
   - Pastikan MySQL service running
   - Test dengan test_connection.php
2. Permission Denied
   
   - Check file permissions (755 untuk folders, 644 untuk files)
   - Pastikan web server memiliki akses ke folder
3. Composer Dependencies
composer install --no-dev
composer dump-autoload
4. 1. JavaScript Errors
   
   - Check browser console (F12)
   - Pastikan semua library ter-load
   - Check network tab untuk failed requests
## 📞 Support
Untuk support dan pertanyaan:

1. Check dokumentasi ini
2. Check browser console untuk error
3. Verify database connection
4. Check server error logs
## 📄 License
This project is proprietary software for internal company use.

Happy Coding! 🎉

Sistem MP Development - Solusi modern untuk manajemen manpower yang efisien dan profesional.
