# Arizona Railway Museum Website

A full-stack web application for the Arizona Railway Museum in Chandler, Arizona. Features equipment rosters, membership management, event calendars, and administrative tools.

## Tech Stack

| Layer | Technology |
|-------|------------|
| Frontend | HTML5, CSS3, Foundation 6, jQuery |
| Backend | PHP 8.3, PDO |
| Database | MySQL/MariaDB |
| Server | Apache 2 with mod_rewrite |
| Infrastructure | Docker, Docker Compose, Supervisor |
| Libraries | TCPDF (PDF generation), CKEditor 4.22.1 |

## Project Structure

```
arizona-railway-museum/
├── admin/                  # Admin control panel
│   ├── equipment/          # Equipment roster management
│   ├── membership/         # Membership application review
│   ├── members/            # Member administration
│   ├── events/             # Event management
│   ├── activity/           # Activity logging
│   └── voting/             # Voting system
├── assets/                 # Shared components (header, footer)
├── config/                 # Database configuration
├── css/                    # Stylesheets (Foundation + custom)
├── database/               # SQL migrations
├── docker/                 # Docker configuration files
├── equipment/              # Public equipment roster
├── events/                 # Public events calendar
├── js/                     # JavaScript (app + vendor)
├── lib/                    # Third-party libraries (TCPDF)
├── members/                # Member portal
├── membership/             # Membership application system
└── [other public pages]    # donations, information, mission, etc.
```

## Features

### Public Pages
- **Homepage** - Hero section, quick facts, membership/donation CTAs
- **Equipment Roster** - Browse railroad equipment with photos, audio narration, and documents
- **Events Calendar** - Upcoming events and archive
- **Membership** - Information and online application
- **Information** - Hours, admission, directions, contact
- **Donations** - Support the museum

### Member Portal
- Member dashboard
- Membership status display
- Member-only content access

### Admin Dashboard
- Equipment management (add/edit, photos, audio, documents)
- Membership application review and approval
- Member directory and administration
- Event creation and management
- Activity logging with PDF export
- Voting system management

## Getting Started

### Prerequisites
- Docker & Docker Compose
- Modern web browser

### Environment Setup

1. Copy the example environment file:
   ```bash
   cp .env.example .env
   ```

2. Configure your environment variables in `.env`:
   ```
   MYSQL_ROOT_PASSWORD=your_root_password
   MYSQL_DATABASE=your_database_name
   MYSQL_USER=your_database_user
   MYSQL_PASSWORD=your_secure_password
   ```

3. Update database connection in `config/db.php` if needed.

### Running with Docker

```bash
# Build and start containers
docker-compose up -d

# View logs
docker-compose logs -f

# Stop containers
docker-compose down
```

Access the application at `http://localhost:4000`

### Local Development (without Docker)

Requirements:
- PHP 8.3+
- MySQL 5.7+ or MariaDB
- Apache with mod_rewrite enabled

1. Configure your web server to point to the project root
2. Import database schema from `/database/` SQL files
3. Update `config/db.php` with your database credentials
4. Ensure Apache mod_rewrite is enabled for clean URLs

## Database Schema

### Core Tables

| Table | Purpose |
|-------|---------|
| `members` | User accounts, authentication, membership data |
| `equipment` | Railroad rolling stock inventory |
| `equipment_documents` | Document attachments for equipment |
| `events` | Museum events and activities |
| `membership_applications` | Online membership applications |

### Membership Types
- Inactive, Founder, Life
- Traditional: Regular, Family, Senior
- Docent: Regular, Family, Senior
- Sustaining, Corporate

### Equipment Categories
- Locomotives
- Passenger Cars
- Freight Cars
- Mail/Baggage/Express
- Maintenance of Way (MOW)
- Interurban

## Media Directories

These directories store uploaded media and are excluded from version control:

```
/images/equipment/{id}/     # Equipment photos
/audio/                     # Equipment audio narration (MP3)
/documents/equipment/{id}/  # Equipment documents (PDF, Office files)
```

## Authentication & Roles

| Role | Access |
|------|--------|
| `admin` | Full administrative access |
| `user` | Member portal access |
| `is_key_holder` | Sensitive equipment information |
| `is_super_admin` | Database tools, advanced administration |

## Security Features

- Password hashing with `password_hash()`
- PDO prepared statements (SQL injection prevention)
- XSS prevention with `htmlspecialchars()`
- Session ID regeneration on login
- File upload type restrictions

## Configuration

### Apache (.htaccess)
- Clean URL rewrites
- Directory index configuration

### PHP (config/db.php)
- Database connection constants
- PDO configuration with exception handling
- Timezone: MST (Arizona, UTC-7)

### Docker
- PHP 8.3 + Apache container
- Supervisor manages Apache and MySQL
- Port mapping: 4000 (HTTP), 3306 (MySQL)

## Contact

This is a private project for the Arizona Railway Museum. For questions or issues, contact:

**Nate Oines**
noines31@gmail.com

## Museum Information

**Arizona Railway Museum**
330 E. Ryan Road
Chandler, AZ 85286