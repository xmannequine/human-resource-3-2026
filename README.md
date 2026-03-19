# HR3 - Employee Management System with Facial Recognition

A comprehensive HR management system featuring employee self-service (ESS), time tracking (Bundy), and **facial recognition-based authentication**.

## 🎯 Features

### 👤 Employee Self-Service (ESS)
- Employee dashboard
- Leave management
- Reimbursement requests
- Attendance tracking
- **Face enrollment and profile management**

### ⏱️ Bundy Clock System
- **Face-based login** - Facial recognition authentication
- Time in/out tracking
- Attendance reports
- Overtime management

### 🔐 Facial Recognition System
- Real-time face detection using `face-api.js`
- 128-value face descriptor matching
- Euclidean distance-based face verification
- Automatic enrollment with image storage
- High accuracy matching (0.55 distance threshold)

## 📋 Requirements

- **PHP 7.4+**
- **MySQL 5.7+**
- **Modern web browser** with webcam support
- **XAMPP** (recommended for local development)

## 🚀 Setup Instructions

### 1. Clone or Extract Project
```bash
git clone https://github.com/yourusername/hr3.git
cd hr3
```

### 2. Database Setup
- Import the database from `db/hr3_hr3_db.sql` (if available)
- Update database credentials in `config.php`

### 3. Configure Database Connection
Edit `config.php`:
```php
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "hr3_hr3_db";
```

### 4. Start Local Server
```bash
# Using XAMPP
# Place project in C:\xampp\htdocs\hr3\

# Access via
http://localhost/hr3/
```

## 🔍 Face Recognition Setup

### Models
Face detection models are stored in `bundy/models/`:
- `tiny_face_detector_model-*` (Face detection)
- `face_landmark_68_model-*` (Face landmarks)
- `face_recognition_model-*` (Face descriptor - 128 values)

**Models are loaded via CDN on first use and cached locally.**

### Face Enrollment
1. Login to ESS Dashboard
2. Click "Enroll Face" button
3. Allow camera access
4. Position face in center
5. Click "Capture Face"
6. Submit to save

### Face Login (Bundy)
1. Go to Bundy Login page
2. Allow camera access
3. Click "Capture Face"
4. System matches against enrolled faces
5. Automatic login if match found

## 📁 Project Structure

```
hr3/
├── bundy/                    # Bundy clock system
│   ├── bundy.php            # Main dashboard
│   ├── bundy_login.php      # Face login page
│   ├── face_login.php       # Face matching API
│   ├── models/              # Face detection models
│   └── sw.js               # Service worker for caching
├── ess_*.php               # Employee self-service pages
├── config.php              # Database configuration (sensitive)
├── uploads/                # User profile images & face photos
└── vendor/                 # Composer dependencies
```

## 🔐 Security Notes

⚠️ **Important:**
- `config.php` contains database credentials - **DO NOT commit to public repo**
- Use `.gitignore` to prevent sensitive files from being pushed
- Set strong database passwords in production
- Enable HTTPS for face enrollment pages
- Use environment variables for sensitive config

## 🛠️ Key Technologies

- **Backend:** PHP 7.4+, PDO MySQL
- **Frontend:** HTML5, Bootstrap 5, JavaScript
- **Face Recognition:** face-api.js (TensorFlow.js)
- **Database:** MySQL
- **Caching:** Service Worker (SW.js)

## 📝 Face Matching Algorithm

- **Detection:** TinyFaceDetector (lightweight, fast)
- **Feature Extraction:** Face landmarks (68 points)
- **Recognition:** FaceRecognitionNet (128-dimensional descriptor)
- **Matching:** Euclidean distance calculation
- **Threshold:** 0.55 (values below = match, above = no match)

## 🐛 Troubleshooting

### Face not detected
- Ensure good lighting
- Face should be centered in camera
- Allow camera permissions in browser

### Models taking long to load
- First load takes 10-30 seconds (normal)
- Subsequent loads use cached models (1-2 seconds)
- Check browser console for errors

### Face login not redirecting
- Verify session is properly set
- Check browser console for JavaScript errors
- Ensure cookies are enabled

## 👥 Default Test User

```
Username: demo
Password: demo123
Employee ID: 12
```

## 📞 Support

For issues with:
- **Facial recognition:** Check `bundy/` directory
- **Database:** Review `config.php` settings
- **ESS features:** Check ESS dashboard pages

## 📜 License

This project is proprietary. All rights reserved.

## 👨‍💻 Authors

- HR3 Development Team
- Built with assistance from GitHub Copilot CLI

---

**Last Updated:** March 2026
**System Status:** Production Ready ✅
