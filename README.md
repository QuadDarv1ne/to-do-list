# To-Do List Application

A comprehensive task management system built with Symfony 8.0, featuring user authentication, role-based access control, and advanced task management capabilities.

## 🚀 Features

### User Management
- User registration and authentication
- Role-based access control (User, Manager, Admin)
- Profile management with avatar support
- Password reset via email
- Account activation/deactivation by administrators

### Task Management
- Create, read, update, and delete tasks (CRUD operations)
- Assign tasks to users
- Set task priorities (Low, Normal, High, Urgent)
- Set deadlines with automatic overdue detection
- Toggle task completion status
- Filter tasks by status, date, priority, and assigned user
- Export tasks to CSV format

### Notifications
- Real-time notifications for task assignments
- Email notifications for important events
- Deadline reminder notifications
- Unread notification counter
- Notification history

### Administration
- User management (create, edit, activate/deactivate users)
- Role assignment and management
- System statistics dashboard
- Bulk operations support

### Priority & Deadline Management
- Four priority levels: Low, Normal, High, Urgent
- Deadline tracking with visual indicators
- Automatic overdue task detection
- Deadline reminder system

## 🛠️ Tech Stack

- **Backend**: Symfony 8.0
- **Database**: Doctrine ORM with SQLite/MySQL support
- **Frontend**: Bootstrap 5, Font Awesome, JavaScript
- **Security**: Symfony Security Component
- **Forms**: Symfony Form Component
- **Email**: Symfony Mailer Component
- **Password Reset**: SymfonyCasts Reset Password Bundle
- **Email Verification**: SymfonyCasts Verify Email Bundle

## 📋 Requirements

### Server Requirements
- PHP 8.1 or higher
- Composer
- SQLite3 or MySQL database
- Web server (Apache/Nginx) or PHP built-in server

### PHP Extensions
- `pdo_sqlite` or `pdo_mysql`
- `json`, `ctype`, `iconv`, `mbstring`
- `xml`, `zip`, `openssl`
- `tokenizer`, `xmlwriter`, `intl`

## 🚀 Installation

### 1. Clone the repository
```bash
git clone https://github.com/your-username/to-do-list.git
cd to-do-list
```

### 2. Install dependencies
```bash
composer install
```

### 3. Configure environment
Copy `.env` file and adjust database settings:
```bash
cp .env .env.local
# Edit .env.local to configure your database
```

### 4. Set up the database
```bash
# Create database (if using SQLite, this creates the file)
# For MySQL, ensure database exists

# Run migrations
php bin/console doctrine:migrations:migrate

# Create admin user (optional)
php bin/console app:create_admin
```

### 5. Install assets
```bash
# Install JavaScript dependencies
npm install
npm run build
```

### 6. Start the server
```bash
php -S localhost:8000 -t public/
# Or use Symfony server if installed
symfony serve
```

## 📄 Available Pages

### Public Pages (No Authentication Required)
- `/register` - User registration page
- `/login` - Login page
- `/forgot-password` - Password reset request page

### Protected Pages (Authentication Required)

#### Main Pages
- `/` - Homepage (redirects to dashboard)
- `/dashboard` - Main dashboard with statistics and recent activity
- `/tasks` - Task list with filtering and sorting options
- `/tasks/new` - Create new task
- `/profile` - View user profile
- `/profile/edit` - Edit user profile
- `/profile/change-password` - Change user password

#### Administrative Pages (Admin Role Required)
- `/users` - User management dashboard
- `/users/new` - Create new user
- `/users/{id}` - View user details
- `/users/{id}/edit` - Edit user
- `/users/{id}/toggle-active` - Activate/deactivate user
- `/users/{id}/unlock` - Unlock user account

#### Notification Pages
- `/notifications` - View all notifications
- `/notifications/mark-as-read/{id}` - Mark notification as read (POST)
- `/notifications/mark-all-as-read` - Mark all notifications as read (POST)
- `/notifications/unread-count` - Get unread notifications count (AJAX)

## 🔐 Authentication System

### Registration Process
1. Visit `/register` to create a new account
2. Fill in required information (email, password)
3. Submit the form to create your account
4. You will be redirected to the login page

### Login Process
1. Visit `/login` to access the login page
2. Enter your credentials
3. Click "Remember me" if you want to stay logged in
4. Click "Sign in" to access the application

### Password Reset Process
1. Visit `/forgot-password` to initiate password reset
2. Enter your email address
3. Check your email for a password reset link
4. Click the link in the email to reset your password
5. Enter your new password twice for confirmation
6. Submit the form to update your password

## 👥 Roles and Permissions

### ROLE_USER
- Access to dashboard and personal tasks
- Create and edit own tasks
- View assigned tasks
- Update profile information

### ROLE_MANAGER
- All USER permissions
- Manage tasks assigned to subordinates
- View team statistics

### ROLE_ADMIN
- All MANAGER permissions
- Full system access
- User management (create, edit, delete users)
- Role assignment
- System-wide task access

## ⚙️ Configuration

### Email Settings
Configure email settings in `.env`:
```env
MAILER_DSN=smtp://localhost:1025
```

### Security Settings
Adjust security settings in `config/packages/security.yaml`:
- Password hashing algorithm
- Login throttling
- Session timeout

## 🔧 Maintenance

### Clear cache
```bash
php bin/console cache:clear
```

### Run database migrations
```bash
php bin/console doctrine:migrations:migrate
```

### Generate new migration
```bash
php bin/console make:migration
```

### Send deadline notifications (cron job)
Add to crontab:
```bash
# Send deadline reminders daily at 9 AM
0 9 * * * cd /path/to/project && php bin/console app:send-deadline-notifications
```

## 🧪 Testing

Run unit tests:
```bash
php bin/phpunit
```

## 📈 Development

### Generate entities
```bash
php bin/console make:entity
```

### Generate controllers
```bash
php bin/console make:controller
```

### Generate forms
```bash
php bin/console make:form
```

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

For support, please open an issue in the GitHub repository.