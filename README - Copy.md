# Gram Panchayat Complaint Management System (GPCMS)

GPCMS is a modern, citizen-friendly, and professional government portal designed for academic final-year project presentation. It enables rural citizens to lodge local grievances (water, lighting, sanitation, roads) and allows Gram Panchayat administrators to assign and monitor resolution works via field maintenance officers.

---

## 🛠️ Technology Stack (Strictly Framework-Free)
*   **Frontend**: HTML5, CSS3 (Vanilla CSS), JavaScript (Vanilla JS, CDNs: FontAwesome 6, Chart.js)
*   **Backend**: PHP (Object-Oriented PDO connection)
*   **Database**: MySQL

---

## 👥 System Roles & Core Workflows

### 1. Citizen Portal
*   **Registration & Login**: Secure signup with salted password checks.
*   **Submit Grievance**: Upload complaint title, detailed description, location/ward, and optional "Before Work" reference photo.
*   **Grievance Tracking**: View personal grievance history log and trace ticket status on an interactive visual progress timeline.
*   **Work Quality Rating**: Provide 1-to-5 star feedback and review comments once work is completed.

### 2. Gram Sevak (GP Admin) Panel
*   **Analytics Overview**: Track system metrics and check grievance trends on a dynamic line chart (Received vs Resolved).
*   **Task Delegation**: Assign incoming citizen complaints to registered field officers and add custom instructions.
*   **Work Verification**: Review "Before" vs "After" completed repair photos uploaded by field staff and choose to resolve or reopen tasks.

### 3. Field Maintenance Officer Module
*   **Job Sheets**: View personal daily assigned maintenance works.
*   **Progress Update**: Update task status to "In Progress" (sends an automated email/alert notification to the citizen).
*   **Completion Submission**: Upload "After Work" proof photos and resolution remarks to submit work for admin audit.

### 4. Super Admin System Auditor
*   **User Directory**: Edit user profiles, register administrative accounts (GP Admins or field staff), and audit permissions.
*   **Homepage Manager**: Publish public announcements, edit government scheme links, and modify emergency contact numbers dynamically.

---

## 📂 Project Directory Structure

```
gp-complaint-system/
├── assets/
│   ├── css/
│   │   ├── style.css             # Main styling, landing page, and grid variables
│   │   └── dashboard.css         # Sidebar navigation panels, widgets, and modal styling
│   └── js/
│       ├── main.js               # Toggle operations, dynamic clock, and tables search filters
│       └── chart-config.js       # Complaints line-chart controller
├── includes/
│   ├── db.php                    # PDO connection & Auto-DB setup script
│   ├── auth.php                  # Authentication rules, CSRF tokens, and security filters
│   ├── header.php                # Shared global header (clock, user metadata, notifications)
│   ├── sidebar.php               # Sidebar navigation menu with village landscape backdrop SVG
│   └── footer.php                # Shared dashboard footer with vector Panchayat building SVG
├── index.php                     # Public landing page with Ticket Timeline tracker
├── login.php                     # Secure login screen
├── register.php                  # Citizen registration form
├── logout.php                    # Terminate user session
├── citizen_dashboard.php         # Citizen workspace (Submit grievance, feedback, tables log)
├── admin_dashboard.php           # GP Admin Workspace (Assign tasks, verify completions)
├── admin_complaints.php          # Overall complaints listing with category/status filters
├── admin_categories.php          # Category CRUD manager
├── admin_reports.php             # Printable PDF system reports generator
├── officer_dashboard.php         # Field officer workspace (Task progress updates, photo uploads)
├── superadmin_dashboard.php      # Super Admin Workspace (Feedback stream, user audits)
├── superadmin_users.php          # System account creator
├── superadmin_settings.php       # Homepage content controller
├── test_db.php                   # Database connection diagnostic tool
└── schema.sql                    # MySQL tables creation script
```

---

## 🚀 Installation & Local Deployment Guide

1.  **Start Services**: Launch Apache and MySQL modules in your local server control panel (e.g. XAMPP).
2.  **Paste Project**: Place the `gp-complaint-system` folder inside your web server's public folder:
    *   *Path*: `C:/Users/DELL/Downloads/nutan/htdocs/gp-complaint-system/`
3.  **Load App**: Open your browser and navigate to:
    *   👉 **`http://localhost/gp-complaint-system/`**
4.  **Autopilot Database Setup**: The connection script will automatically detect that the database is missing, create it (`gp_complaint_db`), install all 10 tables, and seed initial test accounts. No manual SQL imports required!
5.  **Troubleshooting**: Open `http://localhost/gp-complaint-system/test_db.php` in your browser to run automated connection diagnostics.

---

## 🔑 Test Login Accounts

| Role | Username | Password | Purpose |
| :--- | :--- | :--- | :--- |
| **Super Admin** | `superadmin` | `admin123` | Managing settings, news announcements, user accounts |
| **Gram Sevak (GP Admin)** | `gpadmin` | `admin123` | Task delegation, analytics, completions audit |
| **Field Officer** | `officer1` | `officer123` | Updating progress, before/after photo uploads |
| **Citizen** | `citizen1` | `citizen123` | Submit complaints, tracking, feedback rating |
