# Exam Tools - Agent Guidelines

## Project Overview

This is a vanilla PHP application for generating QR codes and exam schedule PDFs. It uses:
- **PHP 8.3** with PHP-FPM + Nginx
- **Dompdf** for PDF generation
- **phpqrcode** for QR code generation
- **SQLite** for session/folder data persistence
- **Docker** for containerization

## Build, Test & Run Commands

### Docker Development

```bash
# Build and start the container
docker compose up -d --build

# View logs
docker compose logs -f

# Stop the container
docker compose down

# Rebuild without cache
docker compose build --no-cache
```

### PHP / Composer Commands

```bash
# Install dependencies
composer install

# Update dependencies to latest versions
composer update

# Dump optimized autoloader
composer dump-autoload -o

# Check PHP syntax on all PHP files
find . -name "*.php" -exec php -l {} \;

# Run PHP built-in server (for local testing without Docker)
php -S localhost:8000
```

### Testing

No formal test framework is currently set up. To run manual tests:

```bash
# Test API endpoint
curl http://localhost/api_sessions.php?action=list

# Check PHP info
php -i
```

## Code Style Guidelines

### General Principles

1. **Vanilla PHP** - This is a plain PHP project, not Laravel/Symfony. Do not introduce frameworks unless explicitly requested.

2. **Minimal Dependencies** - Only add Composer packages when truly necessary. The project currently uses only `dompdf/dompdf`.

3. **Keep It Simple** - Avoid over-engineering. Simple, readable code is preferred over clever abstractions.

### Naming Conventions

- **Files**: Lowercase with dashes `api_sessions.php`, `jadwal.php`
- **Classes** (if any): PascalCase `class MyClass`
- **Functions**: camelCase `function generatePDF()`
- **Constants**: UPPER_CASE `define('MAX_SIZE', 1024)`
- **Variables**: camelCase `$pdfPath`, `$toastMessage`

### PHP Code Style

```php
<?php
// Start file with <?php, no closing tag

session_start();
require "vendor/autoload.php";

// Use meaningful variable names
$uploadDir = 'uploads/';
$processedFiles = 0;

// Use strict comparison when possible
if ($error === 0) { ... }

// Always use braces for control structures
if ($condition) {
    // code
} else {
    // code
}

// Use isset() before accessing array keys
$action = $_GET['action'] ?? '';
```

### SQL & Database

```php
// Use PDO with prepared statements
$stmt = $db->prepare("SELECT * FROM sessions WHERE id = ?");
$stmt->execute([$id]);

// Use try-catch for database operations
try {
    $db = new PDO('sqlite:' . __DIR__ . '/data/database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
```

### Error Handling

- Return JSON with appropriate HTTP status codes for API endpoints
- Use `try-catch` for operations that may fail (file I/O, database, PDF generation)
- Display user-friendly error messages in HTML pages
- Always call `exit` or `die` after `header()` redirects

```php
// API error response
http_response_code(400);
echo json_encode(['error' => 'Invalid file format']);
exit;

// HTML page error
$error = "File not found";
include 'error_page.php';
```

### File Paths

- Use relative paths from project root for includes/requires
- Use `__DIR__` for absolute paths to ensure portability
- Keep paths consistent (e.g., always use trailing slashes for directories)

```php
// Good
require_once __DIR__ . '/lib/pdf_helper.php';
$db = new PDO('sqlite:' . __DIR__ . '/data/database.sqlite');

// Avoid
require_once './lib/pdf_helper.php';  // Less reliable
```

### Frontend (HTML/CSS/JS)

- Use semantic HTML5 elements
- Keep CSS in external `style.css` file
- Use vanilla JavaScript, no frameworks unless needed
- Use CDN for external libraries (Font Awesome, etc.)

### Docker Best Practices

1. Use named volumes for persistent data (`uploads/`, `results/`, `data/`)
2. Never commit `vendor/` or generated files to git
3. Use environment variables for configuration
4. Always rebuild after changing Dockerfile

## Project Structure

```
exam-tools/
├── index.php              # QR Code Generator page
├── jadwal.php             # Exam Schedule Generator page
├── api_generate_jadwal.php # Jadwal PDF API
├── api_sessions.php      # Sessions/Folders CRUD API
├── generate_pdf_api.php  # QR PDF API
├── download.php          # File download handler
├── composer.json         # PHP dependencies
├── Dockerfile            # Container definition
├── compose.yaml          # Docker Compose config
├── phpqrcode/            # QR code library (bundled)
├── pdf/                  # FPDF library (bundled)
├── vendor/               # Composer dependencies
├── uploads/              # User uploaded files
├── results/              # Generated PDF files
└── data/                 # SQLite database
```

## Common Tasks

### Adding a New Feature

1. Create new PHP file in project root (e.g., `new_feature.php`)
2. Use existing code patterns from `index.php` or `jadwal.php`
3. Include necessary libraries: `require "vendor/autoload.php";`
4. Test locally with Docker before committing

### Adding a New Dependency

```bash
# Add new package
composer require vendor/package

# Update composer.json platform if needed
composer require vendor/package --platform=php:8.3
```

### Database Changes

The SQLite database is stored at `data/database.sqlite`. Schema is auto-created in `api_sessions.php`. To modify:

1. Edit the CREATE TABLE statements in `api_sessions.php`
2. Delete `data/database.sqlite` to recreate fresh
3. Or use SQLite commands to alter existing tables

## Environment Variables

When running in Docker, these environment variables are available:

- `PUID` / `PGID` - User/Group IDs for file permissions
- `WEBROOT` - PHP web root path
- `NGINX_WEBROOT` - Nginx document root
- `PHP_MEMORY_LIMIT` - PHP memory limit (default: 128M)
- `PHP_MAX_EXECUTION_TIME` - Max execution time

## Troubleshooting

- **"File not found"** - Check nginx root path matches project structure
- **Permission errors** - Ensure `PUID`/`PGID` match your host user
- **PDF not generating** - Check `vendor/` exists, check PHP memory/execution time limits
- **Icons not loading** - Verify internet access for Font Awesome CDN
