# Security Enhancements Documentation

This document outlines the security improvements implemented in the Dockyard application.

## Implemented Security Features

### 1. Session Fixation Protection
**File**: `login.php`
**Implementation**: `session_regenerate_id(true)` is called after successful authentication
**Benefit**: Prevents session fixation attacks where an attacker tries to hijack a user's session

### 2. Password Complexity Requirements
**Files**: `users/new.php`, `users/edit.php`, `users/change_password.php`, `users/force_password_reset.php`
**Requirements**:
- Minimum 8 characters
- At least one uppercase letter (A-Z)
- At least one lowercase letter (a-z)
- At least one number (0-9)
- At least one special character
**Benefit**: Ensures strong passwords across the application

### 3. Email Validation
**Files**: `users/new.php`, `users/edit.php`
**Implementation**: `filter_var($email, FILTER_VALIDATE_EMAIL)`
**Benefit**: Prevents invalid email addresses from being stored

### 4. Rate Limiting on Login
**File**: `login.php`
**Implementation**: 
- Tracks failed login attempts in `failed_login_attempts` table
- Blocks login after 5 failed attempts within 15 minutes
- Logs all login attempts (successful and failed) with IP address
**Benefit**: Prevents brute force attacks on user accounts

### 5. CSRF Protection for Notification API
**File**: `notifications_api.php`
**Implementation**: Validates CSRF token for POST requests (mark_read, mark_all_read)
**Benefit**: Prevents Cross-Site Request Forgery attacks on notification operations

### 6. Security Headers
**File**: `includes/security_headers.php`
**Headers Implemented**:
- `X-Frame-Options: DENY` - Prevents clickjacking
- `X-Content-Type-Options: nosniff` - Prevents MIME sniffing
- `X-XSS-Protection: 1; mode=block` - XSS protection for older browsers
- `Referrer-Policy: same-origin` - Controls referrer information
- `Content-Security-Policy` - Restricts resource loading
- `Permissions-Policy` - Disables unnecessary browser features

**Benefit**: Comprehensive protection against multiple attack vectors

### 7. Enhanced Session Tracking
**Files**: `login.php`, `includes/auth.php`, `logout.php`
**Database Table**: `user_sessions`
**New Fields**:
- `IPAddress` - Track where session originated
- `UserAgent` - Track browser/client information
- `LastActivity` - Updated on each request

**Benefit**: 
- Better session security and audit trail
- Enables future features like "view active sessions"
- Helps detect suspicious activity

### 8. Login Attempt Logging
**Database Table**: `failed_login_attempts`
**Fields**:
- `Username` - User attempted to log in as
- `IPAddress` - Source IP of attempt
- `AttemptTime` - When attempt occurred
- `Success` - Whether login succeeded

**Benefit**:
- Track failed login attempts for rate limiting
- Security audit trail
- Can identify brute force attempts
- Can identify compromised accounts

## Database Schema Changes

### Modified Tables

#### user_sessions
```sql
CREATE TABLE user_sessions (
    ID INTEGER PRIMARY KEY AUTOINCREMENT,
    UserID INTEGER NOT NULL,
    SessionID TEXT NOT NULL UNIQUE,
    IPAddress TEXT,              -- NEW
    UserAgent TEXT,              -- NEW
    CreatedAt TEXT DEFAULT CURRENT_TIMESTAMP,
    LastActivity TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (UserID) REFERENCES users(ID) ON DELETE CASCADE
);
```

### New Tables

#### failed_login_attempts
```sql
CREATE TABLE failed_login_attempts (
    ID INTEGER PRIMARY KEY AUTOINCREMENT,
    Username TEXT NOT NULL,
    IPAddress TEXT NOT NULL,
    AttemptTime TEXT DEFAULT CURRENT_TIMESTAMP,
    Success BOOLEAN NOT NULL DEFAULT 0
);
```

### New Indices
- `idx_failed_login_username` - Fast lookup by username
- `idx_failed_login_ip` - Fast lookup by IP
- `idx_failed_login_time` - Fast time-based queries

## Security Best Practices Applied

### 1. Prepared Statements
All database queries use prepared statements with parameter binding to prevent SQL injection.

### 2. Output Escaping
All user-supplied data is escaped using `htmlspecialchars()` before output to prevent XSS.

### 3. Input Validation
- Password complexity enforced
- Email format validated
- Username format validated
- CSRF tokens validated

### 4. Authentication & Authorization
- Session-based authentication
- Role-based access control (admin/user)
- Permission checks before sensitive operations
- Session timeout after 30 minutes of inactivity

### 5. Secure Password Handling
- Passwords hashed with `password_hash()` using bcrypt
- Never stored in plain text
- Never logged or displayed

### 6. Command Injection Prevention
- Shell arguments escaped with `escapeshellarg()`
- Input validated before shell execution
- manage_containers.sh validates container names

## Migration Path

### For New Installations
Run `setup.php` - all security features are included

### For Existing Installations
Run `migrate_db.php` - adds new tables and columns:
1. Adds `IPAddress` and `UserAgent` columns to `user_sessions`
2. Creates `failed_login_attempts` table
3. Creates necessary indices

## Configuration Recommendations

### 1. Enable HTTPS
Uncomment this line in `includes/security_headers.php`:
```php
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
```

### 2. Adjust Rate Limiting
Edit `login.php` to change:
- Maximum failed attempts (default: 5)
- Lockout duration (default: 15 minutes)

### 3. Session Timeout
Edit `includes/auth.php` to change:
- Session timeout (default: 1800 seconds / 30 minutes)

### 4. Content Security Policy
Adjust CSP in `includes/security_headers.php` based on your CDN requirements

## Monitoring & Maintenance

### 1. Review Failed Login Attempts
```sql
SELECT Username, IPAddress, COUNT(*) as Attempts, MAX(AttemptTime) as LastAttempt
FROM failed_login_attempts
WHERE Success = 0 AND datetime(AttemptTime) > datetime('now', '-24 hours')
GROUP BY Username, IPAddress
HAVING Attempts >= 3
ORDER BY Attempts DESC;
```

### 2. Clean Up Old Login Attempts
```sql
DELETE FROM failed_login_attempts 
WHERE datetime(AttemptTime) < datetime('now', '-30 days');
```

### 3. Review Active Sessions
```sql
SELECT u.username, s.IPAddress, s.UserAgent, s.CreatedAt, s.LastActivity
FROM user_sessions s
JOIN users u ON s.UserID = u.ID
WHERE datetime(s.LastActivity) > datetime('now', '-30 minutes')
ORDER BY s.LastActivity DESC;
```

### 4. Identify Stale Sessions
```sql
DELETE FROM user_sessions
WHERE datetime(LastActivity) < datetime('now', '-24 hours');
```

## Additional Recommendations

### Future Enhancements
1. **Two-Factor Authentication (2FA)** - Add TOTP support
2. **Password History** - Prevent password reuse
3. **Account Lockout Notification** - Email admin on repeated failed logins
4. **Session Management UI** - Let users view/revoke their active sessions
5. **IP Whitelist** - Restrict admin access to specific IP ranges
6. **Audit Logging** - More comprehensive logging of all admin actions

### Operational Security
1. **Regular Backups** - Backup database regularly
2. **Security Updates** - Keep PHP and dependencies updated
3. **Log Monitoring** - Review error logs for security issues
4. **Access Review** - Regularly review user permissions
5. **Security Audits** - Periodic security assessments

## Compliance Notes

These security enhancements help meet requirements for:
- OWASP Top 10 protections
- Basic security hygiene
- Audit trail requirements
- Session management best practices

## Testing Security Features

### 1. Test Rate Limiting
- Attempt 6 failed logins with same username
- Verify lockout message appears
- Wait 15 minutes and verify access restored

### 2. Test Session Fixation Protection
- Note session ID before login
- Login successfully
- Verify session ID changed after login

### 3. Test Password Requirements
- Try creating user with weak password
- Verify validation messages appear
- Confirm only strong passwords accepted

### 4. Test Security Headers
- Use browser dev tools or curl to verify headers present
- Test CSP by attempting to load external script

### 5. Test CSRF Protection
- Attempt to call notification API without valid CSRF token
- Verify request is rejected

## Support

For questions or issues related to security features, please review the code comments in:
- `includes/security_headers.php`
- `login.php` (rate limiting section)
- `includes/auth.php` (session validation)

---

**Last Updated**: 2025-12-07
**Version**: 2.0
**Security Level**: Enhanced
