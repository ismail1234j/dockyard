# Database Schema and Permission Model Documentation

## Overview
This document provides comprehensive documentation of the database architecture, CRUD operations, container detection mechanism, and the permission model implemented in the Dockyard application.

## Database Schema

### Tables

#### 1. `users` Table
Stores user account information.

```sql
CREATE TABLE users (
    ID INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,  -- bcrypt hashed
    email TEXT,
    IsAdmin BOOLEAN NOT NULL DEFAULT 0
);
```

**Columns:**
- `ID`: Unique identifier for each user
- `username`: Unique username (used for login)
- `password`: bcrypt-hashed password
- `email`: Optional email address
- `IsAdmin`: Boolean flag indicating administrator privileges

**Indices:**
- Primary key on `ID`
- Unique constraint on `username`

#### 2. `apps` Table
Stores Docker container information synchronized from Docker daemon.

```sql
CREATE TABLE apps (
    ID INTEGER PRIMARY KEY AUTOINCREMENT,
    ContainerName TEXT NOT NULL UNIQUE,
    ContainerID TEXT UNIQUE,  -- Docker Container ID
    Image TEXT DEFAULT '',
    Version TEXT DEFAULT 'latest',
    Status TEXT DEFAULT 'unknown',
    Comment TEXT DEFAULT '',
    Port TEXT DEFAULT '',
    Url TEXT DEFAULT '',
    LastPingStatus INTEGER DEFAULT NULL,
    LastPingTime TEXT DEFAULT NULL,
    CreatedAt TEXT DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt TEXT DEFAULT CURRENT_TIMESTAMP
);
```

**Columns:**
- `ID`: Internal database identifier
- `ContainerName`: Human-readable container name from Docker
- `ContainerID`: Docker's internal container ID (used for tracking)
- `Image`: Docker image name (e.g., 'nginx', 'mysql')
- `Version`: Image tag/version (e.g., 'latest', '8.0')
- `Status`: Current container state (running, exited, stopped, created)
- `Comment`: User or system notes about the container
- `Port`: Comma-separated list of exposed ports
- `Url`: Primary URL for accessing the container service
- `LastPingStatus`: Boolean indicating if last health check succeeded
- `LastPingTime`: Timestamp of last health check
- `CreatedAt`: When record was created in database
- `UpdatedAt`: When record was last updated

**Indices:**
- Primary key on `ID`
- Unique constraint on `ContainerName`
- Unique constraint on `ContainerID`
- Index on `ContainerID` for faster lookups
- Index on `Status` for filtering

#### 3. `container_permissions` Table
Manages fine-grained access control for non-admin users.

```sql
CREATE TABLE container_permissions (
    ID INTEGER PRIMARY KEY AUTOINCREMENT,
    UserID INTEGER NOT NULL,
    ContainerID INTEGER NOT NULL,
    CanView BOOLEAN NOT NULL DEFAULT 1,
    CanStart BOOLEAN NOT NULL DEFAULT 0,
    CanStop BOOLEAN NOT NULL DEFAULT 0,
    FOREIGN KEY (UserID) REFERENCES users(ID) ON DELETE CASCADE,
    FOREIGN KEY (ContainerID) REFERENCES apps(ID) ON DELETE CASCADE,
    UNIQUE(UserID, ContainerID)
);
```

**Columns:**
- `ID`: Unique permission record identifier
- `UserID`: Reference to user in `users` table
- `ContainerID`: Reference to container in `apps` table
- `CanView`: Permission to view container details and logs
- `CanStart`: Permission to start the container
- `CanStop`: Permission to stop the container

**Constraints:**
- Foreign key to `users.ID` with CASCADE delete
- Foreign key to `apps.ID` with CASCADE delete
- Unique constraint on (UserID, ContainerID) pair

**Indices:**
- Primary key on `ID`
- Index on `UserID` for user permission lookups
- Index on `ContainerID` for container access queries

### Foreign Key Constraints

**CRITICAL**: SQLite does not enable foreign key constraints by default. The application explicitly enables them with:
```php
$db->exec('PRAGMA foreign_keys = ON');
```

This is done in:
- `setup.php` - Database initialization
- `includes/auth.php` - Main database connection
- `login.php` - Login page connection
- `cron/cron.php` - Container synchronization job

**Cascade Behavior:**
- When a user is deleted, all their permission records are automatically removed
- When a container is removed from the database, all associated permissions are automatically removed
- This maintains referential integrity without orphaned records

## Container Detection and Synchronization

### Mechanism: `cron/cron.php`

The application uses a cron job to discover and track Docker containers. This job should be scheduled to run periodically (e.g., every 5-15 minutes).

#### Discovery Process:

1. **Connect to Docker Daemon**
   - Via Unix socket: `/var/run/docker.sock` (default, local)
   - Via HTTP API: Remote Docker daemon (optional)

2. **Fetch All Containers**
   ```php
   $containers = docker_request('GET', '/containers/json?all=true');
   ```
   - Retrieves ALL containers (running, stopped, paused)
   - Gets container metadata: ID, name, image, status, ports

3. **Process Each Container**
   - Extract: Container ID, name, image, version, status, ports
   - Check if container exists in database (by Docker Container ID)
   - **If exists**: Update status, image, version, ports, timestamp
   - **If new**: Insert new record with auto-generated comment

4. **Database Operations**
   - Uses transactions for data consistency
   - Updates `UpdatedAt` timestamp on each sync
   - Preserves user-defined URLs and comments

5. **Cleanup Removed Containers**
   - Compares database records against Docker container list
   - Deletes database records for containers no longer in Docker
   - Foreign key CASCADE automatically removes associated permissions

### Container Tracking

The application tracks containers by **Docker Container ID** (not name) because:
- Container names can be changed
- Container IDs are immutable and unique
- Provides reliable tracking across container lifecycle

### New Container Behavior

When a new container is discovered:
1. Added to `apps` table with automatic comment
2. **NO PERMISSIONS ARE AUTO-ASSIGNED**
3. Non-admin users cannot see or access it (deny-by-default)
4. Admin must explicitly grant permissions via Permissions page

## Permission Model

### Access Control Levels

#### 1. Administrator Users
- `IsAdmin = 1` in users table
- **Full, unrestricted access** to all containers
- Can view, start, stop any container
- Permission checks are bypassed for admins
- Can manage users and assign permissions

#### 2. Non-Administrator Users
- `IsAdmin = 0` in users table
- **Deny-by-default** access model
- Must have explicit permission record for each container
- Three granular permission types: View, Start, Stop

### Permission Types

#### View Permission (`CanView`)
- See container in container list
- View container details page
- Access container logs
- See container status

**Without View permission:**
- Container is invisible to user
- Cannot access container info or logs
- All other permissions are meaningless

#### Start Permission (`CanStart`)
- Start a stopped/exited container
- Requires `CanView = 1` to be meaningful
- Button disabled in UI if permission not granted

#### Stop Permission (`CanStop`)
- Stop a running container
- Requires `CanView = 1` to be meaningful
- Button disabled in UI if permission not granted

### Permission Enforcement

#### Application-Level Enforcement

Permissions are checked at multiple layers:

1. **PHP Backend** (`includes/functions.php`)
   ```php
   check_container_permission($db, $user_id, $container_name, $action)
   ```
   - Returns true/false based on user's permissions
   - Admins always return true
   - Checks database for explicit permission record

2. **Container List Filtering** (`apps/container_list.php`)
   ```php
   get_user_containers($db, $user_id)
   ```
   - Non-admins only see containers with `CanView = 1`
   - Admins see all containers

3. **Action Endpoints** (`apps/action.php`, `apps/fetch_logs.php`)
   - Verify permission before executing Docker operation
   - Redirect to error page if permission denied

4. **UI Presentation** (`apps/container_info.php`)
   - Start/Stop buttons disabled if user lacks permission
   - Uses PHP to check and set `disabled` attribute

#### No Container-Level Enforcement

The application does **NOT** enforce permissions at the Docker daemon level:
- Docker socket is accessed by PHP process (www-data user)
- No Docker authorization plugin is used
- All enforcement is application-level

**Security Implications:**
- Direct access to Docker socket bypasses permissions
- Users with shell access to server could manipulate containers
- Application assumes trusted server environment

### Least-Privilege Access Model

The permission system implements a **deny-by-default** model:

1. **New Users**: No container access (except admins)
2. **New Containers**: No non-admin user access
3. **Explicit Grants**: Admins must manually assign permissions
4. **Minimal Rights**: Users get only what they need (View, Start, Stop)

### Permission Assignment Workflow

1. Admin logs into application
2. Navigates to "Manage Permissions" page
3. Selects user and container
4. Checks desired permissions (View, Start, Stop)
5. Submits form
6. Application creates/updates `container_permissions` record
7. User immediately gains access (no cache/refresh needed)

### Permission Removal

Permissions are removed by:
1. Admin deletes permission record via Permissions page
2. User deletion (CASCADE removes all their permissions)
3. Container deletion (CASCADE removes all permissions for that container)
4. Container removed from Docker (cron job deletes record, CASCADE removes permissions)

## CRUD Operations

### Create Operations

#### Create User (`users/new.php`)
```php
INSERT INTO users (username, password, email, IsAdmin) 
VALUES (:username, :password_hash, :email, :isAdmin)
```
- Password is bcrypt hashed before storage
- Requires admin privileges
- CSRF token validated
- Username uniqueness enforced by database

#### Create Container (Automatic via cron)
```php
INSERT INTO apps (ContainerName, ContainerID, Image, Version, Status, Comment, Url, Port)
VALUES (...)
```
- Triggered by cron job detecting new Docker container
- Cannot be manually created via UI
- Uses transactions for consistency

#### Create Permission (`permissions.php`)
```php
INSERT INTO container_permissions (UserID, ContainerID, CanView, CanStart, CanStop)
VALUES (:user_id, :container_id, :can_view, :can_start, :can_stop)
```
- Requires admin privileges
- CSRF token validated
- Unique constraint prevents duplicates

### Read Operations

#### Read Users (`users.php`)
```php
SELECT * FROM users ORDER BY username
```
- Requires admin privileges
- Returns all users with email and admin status

#### Read User for Edit (`users/edit.php`)
```php
SELECT ID, username, email, IsAdmin FROM users WHERE ID = :id
```
- Requires admin privileges
- Fetches single user by ID

#### Read Containers (`apps/container_list.php`)
- Admins: `SELECT ContainerName FROM apps ORDER BY ContainerName`
- Non-admins: JOIN with `container_permissions` WHERE `CanView = 1`

#### Read Permissions (`permissions.php`)
```php
SELECT cp.*, u.username, a.ContainerName
FROM container_permissions cp
JOIN users u ON cp.UserID = u.ID
JOIN apps a ON cp.ContainerID = a.ID
```
- Requires admin privileges
- Shows all permission assignments

### Update Operations

#### Update User (`users/edit.php`)
```php
UPDATE users SET username = :username, email = :email, IsAdmin = :isAdmin 
WHERE ID = :id
```
- Optionally updates password if provided
- Prevents admin from removing their own admin status
- Username uniqueness enforced

#### Update Password (`users/change_password.php`)
```php
UPDATE users SET password = :password_hash WHERE username = :username
```
- User can update their own password
- Requires current authentication
- Password complexity validation enforced

#### Update Container (Automatic via cron)
```php
UPDATE apps SET Image = :image, Version = :version, Status = :status, 
    Port = :port, UpdatedAt = CURRENT_TIMESTAMP
WHERE ID = :id
```
- Status and metadata synced from Docker every cron run
- Uses transactions
- Preserves user-defined URL and Comment

#### Update Permission (`permissions.php`)
```php
UPDATE container_permissions 
SET CanView = :can_view, CanStart = :can_start, CanStop = :can_stop
WHERE ID = :id
```
- Requires admin privileges
- Updates existing permission record

### Delete Operations

#### Delete User (`delete_user.php`)
```php
DELETE FROM users WHERE username = :username
```
- Requires admin privileges
- Cannot delete self (current logged-in user)
- CASCADE removes all user's permissions
- CSRF token validated

#### Delete Container (Automatic via cron)
```php
DELETE FROM apps WHERE ID = :id
```
- Triggered when container no longer exists in Docker
- CASCADE removes all associated permissions
- Maintains database consistency

#### Delete Permission (`permissions.php`)
```php
DELETE FROM container_permissions WHERE ID = :id
```
- Requires admin privileges
- CSRF token validated
- Confirmation dialog in UI

## Security Considerations

### Database Security

1. **SQL Injection Prevention**: All queries use prepared statements with parameter binding
2. **Foreign Key Enforcement**: Enabled explicitly to maintain referential integrity
3. **Transaction Support**: Used in cron job to prevent partial updates
4. **Index Usage**: Improves query performance and reduces resource exhaustion

### Authentication and Authorization

1. **Session-based authentication**: Sessions timeout after 30 minutes of inactivity
2. **CSRF Protection**: All state-changing operations require CSRF token
3. **Password Hashing**: bcrypt with default cost factor
4. **Admin-only operations**: User management and permission assignment restricted

### Permission Security

1. **Deny-by-default**: New containers have zero non-admin access
2. **Explicit grants**: Admin must consciously assign permissions
3. **Granular control**: Separate View, Start, Stop permissions
4. **No privilege escalation**: Non-admins cannot grant themselves permissions

### Known Limitations

1. **No Docker-level enforcement**: PHP process has full Docker access
2. **Single admin role**: No role-based access control (RBAC)
3. **No permission inheritance**: No group or role-based permissions
4. **No audit logging**: Permission changes are not logged
5. **No permission expiration**: Permissions are permanent until removed

## Maintenance and Operations

### Regular Tasks

1. **Run cron job**: Schedule `cron/cron.php` every 5-15 minutes
2. **Monitor logs**: Check PHP error logs for database/Docker issues
3. **Review permissions**: Audit user permissions periodically
4. **Clean orphaned data**: Foreign keys handle this automatically

### Database Backup

```bash
# Backup SQLite database
cp data/db.sqlite data/db.sqlite.backup.$(date +%Y%m%d)

# Restore from backup
cp data/db.sqlite.backup.YYYYMMDD data/db.sqlite
```

### Permission Audit Query

```sql
-- View all user permissions
SELECT u.username, a.ContainerName, 
       cp.CanView, cp.CanStart, cp.CanStop
FROM container_permissions cp
JOIN users u ON cp.UserID = u.ID
JOIN apps a ON cp.ContainerID = a.ID
ORDER BY u.username, a.ContainerName;

-- Find users with no permissions
SELECT username FROM users 
WHERE IsAdmin = 0 
AND ID NOT IN (SELECT DISTINCT UserID FROM container_permissions);

-- Find containers with no permissions assigned
SELECT ContainerName FROM apps
WHERE ID NOT IN (SELECT DISTINCT ContainerID FROM container_permissions);
```

## Recommendations

### Operational Improvements

1. **Implement audit logging**: Track permission changes, user actions
2. **Add permission expiration**: Time-limited access grants
3. **Implement role-based access**: Group-based permission management
4. **Add bulk permission operations**: Assign/revoke for multiple users/containers
5. **Implement Docker authorization plugin**: Enforce at daemon level

### Security Enhancements

1. **Two-factor authentication**: TOTP already mentioned in README
2. **Password complexity policy**: Currently basic validation
3. **Account lockout**: Protect against brute force
4. **Security headers**: CSP, X-Frame-Options, etc.
5. **HTTPS enforcement**: Application assumes reverse proxy handles this

### Monitoring

1. **Permission usage metrics**: Track who accesses what
2. **Failed permission checks**: Detect authorization issues
3. **Container drift detection**: Alert when containers removed/added
4. **Health check monitoring**: Use `LastPingStatus` for alerts

---

**Last Updated**: 2025-12-03  
**Version**: 1.0  
**Maintainer**: Dockyard Development Team
