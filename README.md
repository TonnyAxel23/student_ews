# Student Performance Prediction & Early Warning System

A comprehensive web-based system designed for high schools to analyze student academic data and identify at-risk students early, enabling timely academic interventions.

## Features

### Admin Features
- Dashboard with system statistics
- User management (students, teachers, admins)
- Class and subject management
- Performance reports and analytics
- Early warning system monitoring
- Intervention tracking

### Teacher Features
- Dashboard with class overview
- Attendance management
- Assessment score entry
- Student performance monitoring
- Early warning alerts
- Intervention management

### Student Features
- Personal performance dashboard
- Attendance tracking
- Assessment score viewing
- Performance history and trends
- Early warning notifications

### Core System Features
- Predictive risk assessment (Low/Medium/High)
- Automated early warning generation
- Performance trend analysis
- Attendance monitoring
- Statistical reporting
- Data visualization

## Installation

### Quick Installation (Recommended)

1. **Upload Files:**
   - Upload all files to your web server (Apache, Nginx, etc.)

2. **Run Installer:**
   - Navigate to `yourdomain.com/student_ews/install.php`
   - Follow the installation wizard
   - System will automatically:
     - Create database and tables
     - Set up demo accounts
     - Configure the system

3. **Demo Accounts:**
   - **Admin:** admin / admin123
   - **Teacher:** teacher / teacher123
   - **Student:** student / student123

### Manual Installation

1. **Database Setup:**
   ```sql
   CREATE DATABASE student_ews CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   GRANT ALL PRIVILEGES ON student_ews.* TO 'username'@'localhost' IDENTIFIED BY 'password';
   FLUSH PRIVILEGES;