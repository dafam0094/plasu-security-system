// Real-time notification checker
let notificationSound = new Audio('assets/sounds/notification.mp3'); // optional

function checkNotifications() {
    fetch('get_notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.count > 0) {
                // Play sound
                // notificationSound.play();
                
                // Show browser notification if permitted
                if (Notification.permission === "granted") {
                    new Notification("New Security Alert", {
                        body: `You have ${data.count} new notification(s)`,
                        icon: "assets/images/alert-icon.png"
                    });
                }
                
                // Update notification badge
                updateNotificationBadge(data.count);
                
                // Show toast notifications
                data.notifications.forEach(notif => {
                    showToast(notif);
                });
            }
        })
        .catch(error => console.error('Error:', error));
}

function updateNotificationBadge(count) {
    let badge = document.getElementById('notification-badge');
    if (badge) {
        if (count > 0) {
            badge.textContent = count;
            badge.style.display = 'inline';
        } else {
            badge.style.display = 'none';
        }
    }
}

function showToast(notification) {
    // Create toast container if not exists
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.style.position = 'fixed';
        toastContainer.style.top = '20px';
        toastContainer.style.right = '20px';
        toastContainer.style.zIndex = '9999';
        document.body.appendChild(toastContainer);
    }
    
    // Create toast
    let toast = document.createElement('div');
    toast.className = 'toast show';
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="toast-header bg-primary text-white">
            <strong class="me-auto">Security Alert</strong>
            <small>Just now</small>
            <button type="button" class="btn-close btn-close-white" onclick="this.closest('.toast').remove()"></button>
        </div>
        <div class="toast-body">
            <strong>${notification.message}</strong><br>
            <small>Location: ${notification.address}</small><br>
            <span class="badge bg-${notification.status === 'pending' ? 'warning' : (notification.status === 'processing' ? 'info' : 'success')}">
                ${notification.status}
            </span>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    
    // Mark as read after showing
    setTimeout(() => {
        fetch('mark_notification_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'notification_id=' + notification.id
        });
    }, 5000);
    
    // Auto remove after 10 seconds
    setTimeout(() => {
        toast.remove();
    }, 10000);
}

// Request notification permission
if (Notification.permission !== "granted" && Notification.permission !== "denied") {
    Notification.requestPermission();
}

// Check every 10 seconds
setInterval(checkNotifications, 10000);

// Initial check
document.addEventListener('DOMContentLoaded', checkNotifications);