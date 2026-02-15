# 🧁 Tatlı Düşler - Boutique Bakery Platform

A complete business management system developed for a local boutique bakery. Features a customer-facing website with product catalog and an admin dashboard for full business management.

![PHP](https://img.shields.io/badge/PHP-777BB4?style=flat&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=flat&logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=flat&logo=javascript&logoColor=black)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=flat&logo=css3&logoColor=white)

## ✨ Features

### Customer Website
- **Hero Section** - Animated illustrations and promotional banners
- **Product Catalog** - Dynamic filtering by category, pagination, modal previews
- **Availability Calendar** - Real-time booking status via REST API
- **Contact Form** - Secure messaging with validation
- **WhatsApp Integration** - Direct ordering via WhatsApp button
- **Promotions Display** - Student discount banner, loyalty program info
- **FAQ Section** - Expandable accordion-style Q&A

### Admin Dashboard
- **Product Management** - Full CRUD operations with image upload
- **Category Management** - Organize products by category
- **Customer Management** - Track customer information
- **Message Center** - View and manage contact form submissions
- **Calendar Management** - Set daily availability status
- **Secure Authentication** - Protected admin area

## 🔒 Security Features

- **CSRF Protection** - Token-based form validation
- **Rate Limiting** - Prevents spam and brute force attacks
- **Input Sanitization** - All user inputs are sanitized
- **XSS Prevention** - Output encoding and filtering
- **SQL Injection Prevention** - Prepared statements
- **Secure Sessions** - HTTP-only cookies, session regeneration

## 🛠 Tech Stack

| Layer | Technologies |
|-------|-------------|
| **Backend** | PHP 7.4+, Custom MVC-style architecture |
| **Database** | MySQL with PDO |
| **Frontend** | HTML5, CSS3, Vanilla JavaScript |
| **Design** | Responsive, Mobile-first, CSS Animations |
| **Icons** | Custom SVG illustrations |

## 📁 Project Structure

```
pastane/
├── admin/                  # Admin dashboard
│   ├── index.php          # Admin login
│   ├── dashboard.php      # Main dashboard
│   ├── urunler.php        # Product management
│   ├── kategoriler.php    # Category management
│   ├── musteriler.php     # Customer management
│   ├── mesajlar.php       # Messages
│   ├── takvim.php         # Calendar management
│   └── includes/          # Admin components
├── api/
│   └── takvim.php         # Calendar REST API
├── assets/
│   ├── css/               # Stylesheets
│   └── js/                # JavaScript files
├── includes/
│   ├── config.php         # Configuration
│   ├── db.php             # Database connection
│   ├── functions.php      # Helper functions
│   └── security.php       # Security utilities
├── uploads/               # Product images
├── index.php              # Main website
└── iletisim.php          # Contact form handler
```

## 🚀 Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/EfeTaskirann/Pastane-Butik.git
   ```

2. **Set up the database**
   - Create a MySQL database
   - Import the SQL file (if provided) or run the application to auto-create tables

3. **Configure the application**
   - Open `includes/config.php`
   - Update database credentials:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_NAME', 'your_database');
     define('DB_USER', 'your_username');
     define('DB_PASS', 'your_password');
     ```

4. **Run on local server**
   - Place files in XAMPP/LAMP htdocs folder
   - Access via `http://localhost/pastane`

5. **Access admin panel**
   - Navigate to `http://localhost/pastane/admin`

## 📸 Screenshots

### Customer Website
![Homepage](screenshots/homepage.png)
*Homepage with hero section and product catalog*

### Admin Dashboard
![Admin Panel](screenshots/admin.png)
*Product management interface*

> Note: Add your screenshots to a `screenshots` folder

## 🌐 Live Demo

> Add your live demo link here if available

## 📋 API Endpoints

### Calendar API
```
GET  /api/takvim.php          - Get availability data
POST /api/takvim.php          - Update availability (admin)
```

## 🔮 Future Improvements

- [ ] Online payment integration
- [ ] Order tracking system
- [ ] Email notifications
- [ ] Multi-language support
- [ ] Customer accounts

## 👤 Author

**Efe Taşkıran**

- Email: efe.taskiran63@gmail.com
- GitHub: [@EfeTaskirann](https://github.com/EfeTaskirann)
- Location: Famagusta, Cyprus

## 📄 License

This project is open source and available for learning purposes.

---

⭐ If you found this project useful, please consider giving it a star!
