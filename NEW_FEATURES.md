# New Features Documentation

This document describes the new features added to the Dockyard container management system.

## Features Implemented

### 1. User-Specific Container Management (Issue #1)

**Location:** `users.php` → "Manage Containers" button (Docker icon)

**Description:** 
- Admin can now manage container permissions for individual users directly from the users page
- Each user has a dedicated permission management page accessible via the Docker icon
- Improved intuitive layout compared to the previous global permissions page

**How to use:**
1. Navigate to Users page (admin only)
2. Click the Docker icon next to a user
3. Select containers and assign View/Start/Stop permissions
4. Permissions take effect immediately

### 2. Force Password Reset (Issue #2)

**Location:** `users.php` → Force Reset toggle column

**Description:**
- Admins can require users to reset their password on next login
- Users are redirected to a restricted session that only allows password reset
- Strong password requirements enforced (8+ chars, uppercase, lowercase, number, special char)
- Temporary sessions expire after 5 minutes for security
- All admin actions are logged in the database

**How to use:**
1. Navigate to Users page (admin only)
2. Toggle the "Force Reset" switch for a user
3. User will be prompted to reset password on next login
4. User must complete reset within 5 minutes or session expires

**Security Features:**
- User must authenticate with existing password first
- Session is restricted - cannot access any other pages
- Session auto-expires after 5 minutes
- Strong password policy enforced
- Admin actions are logged

### 3. Fixed Start/Stop Buttons (Issue #3)

**Location:** `apps/container_info.php`

**Description:**
- Start and Stop buttons now work correctly
- Shell command output is properly captured and processed
- Status feedback is provided after actions

**Technical Details:**
- Fixed `shell_exec()` output capture in `apps/action.php`
- Added proper success/error detection
- Redirects with action status for user feedback

### 4. Custom Confirmation Modals (Issue #4)

**Location:** `apps/container_info.php`

**Description:**
- Replaced browser confirm dialogs with custom HTML modals
- Professional loading spinner during operations
- Success/error messages displayed in custom modals
- Better error handling throughout

**Features:**
- Confirmation modal before start/stop actions
- Loading modal with spinner during execution
- Result modal showing success or error
- Consistent styling across the application

### 5. Inline Log Display (Issue #5)

**Location:** `apps/container_info.php` → Bottom of page

**Description:**
- Container logs now display directly on the container info page
- No need for separate logs page or button
- Auto-refresh every 30 seconds (configurable)
- Formatted in a code block for readability

**Features:**
- Last 50 lines of logs displayed
- Manual refresh button available
- Auto-refresh can be configured or disabled
- Monospace font for better readability

### 6. Email Support (Issue #6)

**Location:** `includes/email.php`

**Description:**
- Email sending utility for future integrations
- Supports password reset notifications
- Container status notifications
- Test email functionality

**Configuration:**
Set environment variables or edit `includes/email.php`:
```bash
export SMTP_HOST="smtp.example.com"
export SMTP_PORT="587"
export SMTP_USERNAME="your-email@example.com"
export SMTP_PASSWORD="your-password"
export SMTP_FROM_EMAIL="noreply@dockyard.local"
```

**Available Functions:**
- `send_email($to, $subject, $message, $isHTML)` - Generic email sender
- `send_password_reset_email($to, $username)` - Password reset notification
- `send_container_notification($to, $containerName, $status)` - Container alerts
- `send_test_email($to)` - Test email configuration

### 7. In-App Notification System (Issue #7)

**Location:** Bell icon in header (index.php, apps.php)

**Description:**
- Real-time notification system for container events
- Bell icon shows unread count
- Dropdown displays recent notifications
- Permission-based filtering (users only see notifications for containers they have access to)

**Notification Types:**
- **Error**: Container in exited/unknown state
- **Info**: New container detected
- **Warning**: Other important events
- **Success**: Positive events

**Features:**
- Auto-updates every 30 seconds
- Click to mark as read
- "Mark all as read" button
- Time ago display (e.g., "5 minutes ago")
- Permission-filtered (users only see relevant notifications)

**Cron Job Integration:**
The cron job (`cron/cron.php`) automatically:
- Detects new containers and creates info notifications
- Detects exited/error containers and creates error notifications
- Cleans up old read notifications

### 8. Container Name Editing (Issue #8)

**Location:** `apps/container_info.php` → Edit button (admin only)

**Description:**
- Admins can rename Docker containers
- Changes both Docker and database
- Input validation for valid container names
- Informative warnings about limitations

**How to use:**
1. Navigate to container info page
2. Click "Edit" button (admin only)
3. Enter new container name
4. Submit form
5. Container is renamed in Docker and database

**Limitations:**
- Container names must contain only letters, numbers, underscores, dots, and hyphens
- Container should be stopped for safe renaming
- Names defined in docker-compose files may be recreated
- Configuration defined in compose files cannot be changed

## Database Migration

Before using these features, run the database migration:

```bash
php migrate_db.php
```

This will:
- Add `force_password_reset` column to users table
- Create `notifications` table
- Create `admin_actions_log` table
- Create necessary indices

## Configuration

### Email Configuration
Edit `includes/email.php` or set environment variables:
- `SMTP_HOST` - Mail server hostname
- `SMTP_PORT` - Mail server port (587 for TLS)
- `SMTP_USERNAME` - SMTP username
- `SMTP_PASSWORD` - SMTP password
- `SMTP_FROM_EMAIL` - From email address
- `SMTP_ENCRYPTION` - 'tls', 'ssl', or empty

### Log Auto-Refresh
Edit `apps/container_info.php`:
```javascript
const autoRefreshInterval = 30000; // 30 seconds
```
Set to 0 to disable auto-refresh.

### Notification Polling
Edit `includes/notification_widget.php`:
```javascript
setInterval(updateNotificationCount, 30000); // 30 seconds
```

## Security Considerations

### Force Password Reset
- User must authenticate with existing password
- Temporary session expires after 5 minutes
- Cannot access other pages during reset
- Strong password policy enforced
- Admin actions logged

### Container Operations
- Permission-based access control
- CSRF token validation on all forms
- Input validation and sanitization
- Shell command escaping

### Notifications
- Permission-filtered (users only see authorized content)
- SQL injection protection via prepared statements
- XSS protection via proper escaping

## Testing

### Test Force Password Reset
1. Create a test user
2. Toggle force reset on
3. Login as test user
4. Verify restricted session
5. Complete password reset
6. Verify full access restored

### Test Notifications
1. Run cron job: `php cron/cron.php`
2. Check notification bell for new notifications
3. Click to view dropdown
4. Mark as read
5. Verify count updates

### Test Container Operations
1. Navigate to container info page
2. Click Start button
3. Verify custom modal appears
4. Confirm action
5. Verify loading modal
6. Check for success/error message
7. Verify container status changed

### Test Email (if configured)
```php
require_once 'includes/email.php';
send_test_email('your-email@example.com');
```

## Known Issues

None at this time.

## Future Enhancements

1. WebSocket support for real-time log streaming
2. Email notifications for critical container events
3. Bulk permission operations
4. Role-based access control (RBAC)
5. Audit log viewer UI
6. Container health check monitoring
7. Custom notification preferences per user

## Support

For issues or questions, please check:
- Database schema: `DATABASE_SCHEMA.md`
- Docker operations: `DOCKER_OPERATIONS.md`
- Main README: `README.md`
