<!-- Notification Widget -->
<style>
    .notification-bell {
        position: relative;
        cursor: pointer;
        display: inline-block;
        padding: 10px;
    }
    
    .notification-bell i {
        font-size: 24px;
    }
    
    .notification-badge {
        position: absolute;
        top: 5px;
        right: 5px;
        background-color: #dc3545;
        color: white;
        border-radius: 50%;
        padding: 2px 6px;
        font-size: 12px;
        font-weight: bold;
        display: none;
    }
    
    .notification-badge.show {
        display: block;
    }
    
    .notification-dropdown {
        display: none;
        position: absolute;
        right: 0;
        top: 50px;
        background-color: white;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        width: 350px;
        max-height: 400px;
        overflow-y: auto;
        z-index: 1000;
    }
    
    .notification-dropdown.show {
        display: block;
    }
    
    .notification-header {
        padding: 15px;
        border-bottom: 1px solid #dee2e6;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .notification-item {
        padding: 12px 15px;
        border-bottom: 1px solid #f1f3f5;
        cursor: pointer;
        transition: background-color 0.2s;
    }
    
    .notification-item:hover {
        background-color: #f8f9fa;
    }
    
    .notification-item.unread {
        background-color: #e7f3ff;
    }
    
    .notification-item-type {
        font-size: 12px;
        font-weight: bold;
        text-transform: uppercase;
        margin-bottom: 5px;
    }
    
    .notification-item-type.error { color: #dc3545; }
    .notification-item-type.warning { color: #ffc107; }
    .notification-item-type.success { color: #28a745; }
    .notification-item-type.info { color: #17a2b8; }
    
    .notification-item-message {
        font-size: 14px;
        color: #495057;
        margin-bottom: 5px;
    }
    
    .notification-item-time {
        font-size: 12px;
        color: #6c757d;
    }
    
    .notification-empty {
        padding: 30px;
        text-align: center;
        color: #6c757d;
    }
</style>

<div class="notification-bell" id="notification-bell" onclick="toggleNotifications()">
    <i class="fa fa-bell"></i>
    <span class="notification-badge" id="notification-badge">0</span>
    
    <div class="notification-dropdown" id="notification-dropdown">
        <div class="notification-header">
            <strong>Notifications</strong>
            <a href="#" onclick="markAllRead(event)" style="font-size: 12px;">Mark all read</a>
        </div>
        <div id="notification-list">
            <div class="notification-empty">
                <i class="fa fa-bell-o" style="font-size: 48px; color: #dee2e6;"></i>
                <p>No notifications</p>
            </div>
        </div>
    </div>
</div>

<script>
    let notificationsVisible = false;
    
    function toggleNotifications() {
        const dropdown = document.getElementById('notification-dropdown');
        notificationsVisible = !notificationsVisible;
        
        if (notificationsVisible) {
            dropdown.classList.add('show');
            loadNotifications();
        } else {
            dropdown.classList.remove('show');
        }
    }
    
    function loadNotifications() {
        fetch('notifications_api.php?action=list&unread_only=false&limit=20')
            .then(response => response.json())
            .then(notifications => {
                const listContainer = document.getElementById('notification-list');
                
                if (notifications.length === 0) {
                    listContainer.innerHTML = `
                        <div class="notification-empty">
                            <i class="fa fa-bell-o" style="font-size: 48px; color: #dee2e6;"></i>
                            <p>No notifications</p>
                        </div>
                    `;
                    return;
                }
                
                listContainer.innerHTML = notifications.map(notif => {
                    const isUnread = notif.IsRead == 0;
                    const timeAgo = getTimeAgo(notif.CreatedAt);
                    
                    return `
                        <div class="notification-item ${isUnread ? 'unread' : ''}" onclick="markAsRead(${notif.ID})">
                            <div class="notification-item-type ${notif.Type}">${notif.Type}</div>
                            <div class="notification-item-message">${escapeHtml(notif.Message)}</div>
                            <div class="notification-item-time">${timeAgo}</div>
                        </div>
                    `;
                }).join('');
            })
            .catch(error => {
                console.error('Error loading notifications:', error);
            });
    }
    
    function updateNotificationCount() {
        fetch('notifications_api.php?action=count')
            .then(response => response.json())
            .then(data => {
                const badge = document.getElementById('notification-badge');
                if (data.count > 0) {
                    badge.textContent = data.count;
                    badge.classList.add('show');
                } else {
                    badge.classList.remove('show');
                }
            })
            .catch(error => {
                console.error('Error updating notification count:', error);
            });
    }
    
    function markAsRead(notificationId) {
        const formData = new FormData();
        formData.append('notification_id', notificationId);
        
        fetch('notifications_api.php?action=mark_read', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadNotifications();
                updateNotificationCount();
            }
        })
        .catch(error => {
            console.error('Error marking notification as read:', error);
        });
    }
    
    function markAllRead(event) {
        event.preventDefault();
        event.stopPropagation();
        
        fetch('notifications_api.php?action=mark_all_read', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadNotifications();
                updateNotificationCount();
            }
        })
        .catch(error => {
            console.error('Error marking all as read:', error);
        });
    }
    
    function getTimeAgo(timestamp) {
        const now = new Date();
        const time = new Date(timestamp);
        const diff = Math.floor((now - time) / 1000); // difference in seconds
        
        if (diff < 60) return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + ' minutes ago';
        if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
        return Math.floor(diff / 86400) + ' days ago';
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const bell = document.getElementById('notification-bell');
        const dropdown = document.getElementById('notification-dropdown');
        
        if (!bell.contains(event.target)) {
            dropdown.classList.remove('show');
            notificationsVisible = false;
        }
    });
    
    // Update notification count on page load and periodically
    updateNotificationCount();
    setInterval(updateNotificationCount, 30000); // Every 30 seconds
</script>
