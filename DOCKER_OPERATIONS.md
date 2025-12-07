# Docker Operations - Implementation Details

## Overview

The Dockyard application manages Docker containers using the **bash script** `manage_containers.sh` as the primary method for all Docker operations. This document explains the implementation and why this approach was chosen.

## Implementation Method

### Current Implementation: `manage_containers.sh` (Bash Script)

All Docker operations in the application use the bash script `manage_containers.sh`:

- **Container actions** (start, stop, status, logs): `apps/action.php` → `manage_containers.sh`
- **Container listing**: `apps/container_list.php` → `manage_containers.sh`
- **Container sync**: `cron/cron.php` → `manage_containers.sh`
- **Container status**: `apps/container_info.php` → `manage_containers.sh`
- **Log retrieval**: `apps/fetch_logs.php` → `manage_containers.sh`

### Script Location

```
/var/www/html/manage_containers.sh
```

### Available Commands

```bash
# Start a container
bash manage_containers.sh start <container_name>

# Stop a container
bash manage_containers.sh stop <container_name>

# Get container status
bash manage_containers.sh status <container_name>

# Get container logs (default 30 lines)
bash manage_containers.sh logs <container_name> [lines]

# List all containers
bash manage_containers.sh list
```

### Output Format

The `list` command returns CSV format:
```
container_name,status,image:tag,ports
```

Example:
```
nginx,Up 2 hours,nginx:latest,80/tcp, 443/tcp
mysql,Exited (0) 5 minutes ago,mysql:8.0,
webapp,Up 10 minutes,myapp:v1.2,8080/tcp
```

## Why Bash Script Instead of Docker API?

### Advantages of Bash Script Approach:

1. **Proven Reliability**: The bash script is tested and known to work in the deployment environment
2. **Simplified Permissions**: Single sudo configuration for www-data user
3. **Consistent Interface**: All operations use the same script interface
4. **Easy Debugging**: Script operations logged to `/var/www/html/logs/container_operations.log`
5. **Docker CLI Compatibility**: Uses standard docker commands that work across versions
6. **No Socket Permission Issues**: Avoids common Docker socket permission problems

### Deprecated: `includes/docker.php`

The file `includes/docker.php` exists but is **NOT USED** in production:

- Contains functions for direct Docker socket API communication
- Marked as deprecated with warning comment
- Kept for reference or future migration only
- All active code uses `manage_containers.sh` instead

## Security Considerations

### Sudo Configuration

The bash script requires sudo privileges, configured in Dockerfile:

```dockerfile
RUN echo "www-data ALL=(ALL) NOPASSWD: /var/www/html/manage_containers.sh" > /etc/sudoers.d/manage-containers
```

### Script Validation

The script validates container names to prevent command injection:

```bash
validate_container_name() {
    if [[ ! "$1" =~ ^[a-zA-Z0-9_.-]+$ ]]; then
        echo "Invalid container name"
        exit 1
    fi
}
```

### PHP Escaping

All PHP code uses `escapeshellarg()` when passing container names:

```php
$escapedName = escapeshellarg($name);
shell_exec("bash $scriptPath start $escapedName 2>&1");
```

## Operation Flow

### Starting a Container

1. User clicks "Start" button in UI
2. `apps/container_info.php` → JavaScript calls action
3. `apps/action.php`:
   - Checks user permissions
   - Validates container name
   - Executes: `bash manage_containers.sh start <name>`
4. Script performs: `docker start <name>`
5. Operation logged to: `/var/www/html/logs/container_operations.log`
6. User redirected back to container info page

### Container Synchronization (Cron)

1. Cron job executes `php /var/www/html/cron/cron.php`
2. Script runs: `bash manage_containers.sh list`
3. Parses output to extract container information
4. Updates database with current state
5. Removes deleted containers from database
6. Cascading deletes remove orphaned permissions

## Logging

All container operations are logged by the bash script:

```
2025-12-03 17:30:15 | USER=www-data | CONTAINER=nginx | ACTION=START | RESULT=nginx
2025-12-03 17:30:42 | USER=www-data | CONTAINER=mysql | ACTION=STOP | RESULT=mysql
2025-12-03 17:31:00 | USER=www-data | CONTAINER=webapp | ACTION=STATUS | RESULT=running
```

Log location: `/var/www/html/logs/container_operations.log`

## Maintenance

### Testing the Script

```bash
# Test list command
bash /var/www/html/manage_containers.sh list

# Test with specific container
bash /var/www/html/manage_containers.sh status nginx

# Check logs
tail -f /var/www/html/logs/container_operations.log
```

### Troubleshooting

**Issue**: Script not found error  
**Solution**: Ensure script exists and is executable:
```bash
chmod +x /var/www/html/manage_containers.sh
```

**Issue**: Permission denied  
**Solution**: Check sudoers configuration:
```bash
sudo cat /etc/sudoers.d/manage-containers
```

**Issue**: Invalid container name  
**Solution**: Container names must match `^[a-zA-Z0-9_.-]+$`

## Future Considerations

If migrating to Docker API (docker.php):

1. **Test thoroughly** in development environment
2. **Update permissions** for Docker socket access
3. **Modify cron.php** to use `docker_request()` functions
4. **Update all action.php references** to use docker.php
5. **Remove bash script dependencies** from Dockerfile
6. **Update documentation** to reflect new method

However, the current bash script approach is:
- ✅ Production-tested
- ✅ Reliable
- ✅ Well-logged
- ✅ Easy to debug

**Recommendation**: Keep using bash script unless specific API features are needed.

---

**Last Updated**: 2025-12-03  
**Version**: 1.0  
**Implementation**: Bash Script (`manage_containers.sh`)
