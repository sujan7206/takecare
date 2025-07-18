// Global variables
let currentEditId = null;
let currentEditType = null;

// Initialize the application
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
    loadDashboardData();
    setupEventListeners();
});

// Initialize the application with sample data
function initializeApp() {
    // Initialize sample data if not exists
    if (!localStorage.getItem('users')) {
        const sampleUsers = [
            { id: 1, name: 'Alice Johnson', email: 'alice@example.com', role: 'Editor' },
            { id: 2, name: 'Bob Smith', email: 'bob@example.com', role: 'User' },
            { id: 3, name: 'Carol Wilson', email: 'carol@example.com', role: 'Moderator' }
        ];
        localStorage.setItem('users', JSON.stringify(sampleUsers));
    }

    if (!localStorage.getItem('admins')) {
        const sampleAdmins = [
            { id: 1, name: 'John Doe', email: 'john@admin.com', role: 'Super Admin' },
            { id: 2, name: 'Jane Smith', email: 'jane@admin.com', role: 'Moderator' }
        ];
        localStorage.setItem('admins', JSON.stringify(sampleAdmins));
    }

    if (!localStorage.getItem('notices')) {
        const sampleNotices = [
            { 
                id: 1, 
                title: 'System Maintenance', 
                content: 'Scheduled maintenance will be performed on Sunday from 2 AM to 4 AM EST.', 
                date: '2025-07-20' 
            },
            { 
                id: 2, 
                title: 'New Feature Release', 
                content: 'We are excited to announce the release of our new dashboard features.', 
                date: '2025-07-18' 
            }
        ];
        localStorage.setItem('notices', JSON.stringify(sampleNotices));
    }

    // Load initial data
    loadUsers();
    loadAdmins();
    loadNotices();
}

// Setup event listeners
function setupEventListeners() {
    // Sidebar navigation
    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const section = this.getAttribute('data-section');
            showSection(section);
        });
    });

    // Mobile menu toggle
    document.getElementById('menuToggle').addEventListener('click', toggleSidebar);
    document.getElementById('sidebarToggle').addEventListener('click', toggleSidebar);
    document.getElementById('mobileOverlay').addEventListener('click', closeSidebar);

    // Form submissions
    document.getElementById('userForm').addEventListener('submit', handleUserSubmit);
    document.getElementById('adminForm').addEventListener('submit', handleAdminSubmit);
    document.getElementById('noticeForm').addEventListener('submit', handleNoticeSubmit);

    // Modal close on backdrop click
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal(this.id);
            }
        });
    });
}

// Navigation functions
function showSection(sectionId) {
    // Hide all sections
    document.querySelectorAll('.content-section').forEach(section => {
        section.classList.remove('active');
    });

    // Show selected section
    document.getElementById(sectionId).classList.add('active');

    // Update sidebar active state
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
    });
    document.querySelector(`[data-section="${sectionId}"]`).parentElement.classList.add('active');

    // Load section data
    if (sectionId === 'dashboard') {
        loadDashboardData();
    } else if (sectionId === 'users') {
        loadUsers();
    } else if (sectionId === 'admins') {
        loadAdmins();
    } else if (sectionId === 'notices') {
        loadNotices();
    }
}

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobileOverlay');
    
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('mobileOverlay');
    
    sidebar.classList.remove('active');
    overlay.classList.remove('active');
}

// Dashboard functions
function loadDashboardData() {
    const users = JSON.parse(localStorage.getItem('users')) || [];
    const admins = JSON.parse(localStorage.getItem('admins')) || [];
    const notices = JSON.parse(localStorage.getItem('notices')) || [];

    document.getElementById('totalUsers').textContent = users.length;
    document.getElementById('totalAdmins').textContent = admins.length;
    document.getElementById('totalNotices').textContent = notices.length;
}

// User management functions
function loadUsers() {
    const users = JSON.parse(localStorage.getItem('users')) || [];
    const tbody = document.getElementById('usersTableBody');
    
    tbody.innerHTML = '';
    
    users.forEach(user => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${user.name}</td>
            <td>${user.email}</td>
            <td>${user.role}</td>
            <td class="table-actions">
                <button class="btn btn-warning btn-small" onclick="editUser(${user.id})">
                    <i class="fas fa-edit"></i> Edit
                </button>
                <button class="btn btn-danger btn-small" onclick="deleteUser(${user.id})">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </td>
        `;
        tbody.appendChild(row);
    });
}

function handleUserSubmit(e) {
    e.preventDefault();
    
    const name = document.getElementById('userName').value;
    const email = document.getElementById('userEmail').value;
    const role = document.getElementById('userRole').value;
    
    if (!name || !email || !role) {
        alert('Please fill in all fields');
        return;
    }
    
    const users = JSON.parse(localStorage.getItem('users')) || [];
    
    if (currentEditId && currentEditType === 'user') {
        // Update existing user
        const userIndex = users.findIndex(user => user.id === currentEditId);
        if (userIndex !== -1) {
            users[userIndex] = { id: currentEditId, name, email, role };
        }
    } else {
        // Add new user
        const newUser = {
            id: Date.now(),
            name,
            email,
            role
        };
        users.push(newUser);
    }
    
    localStorage.setItem('users', JSON.stringify(users));
    loadUsers();
    loadDashboardData();
    closeModal('userModal');
    resetUserForm();
}

function editUser(id) {
    const users = JSON.parse(localStorage.getItem('users')) || [];
    const user = users.find(user => user.id === id);
    
    if (user) {
        document.getElementById('userName').value = user.name;
        document.getElementById('userEmail').value = user.email;
        document.getElementById('userRole').value = user.role;
        document.getElementById('userModalTitle').textContent = 'Edit User';
        
        currentEditId = id;
        currentEditType = 'user';
        
        openModal('userModal');
    }
}

function deleteUser(id) {
    showConfirmation('Are you sure you want to delete this user?', () => {
        const users = JSON.parse(localStorage.getItem('users')) || [];
        const filteredUsers = users.filter(user => user.id !== id);
        localStorage.setItem('users', JSON.stringify(filteredUsers));
        loadUsers();
        loadDashboardData();
    });
}

function resetUserForm() {
    document.getElementById('userForm').reset();
    document.getElementById('userModalTitle').textContent = 'Add User';
    currentEditId = null;
    currentEditType = null;
}

// Admin management functions
function loadAdmins() {
    const admins = JSON.parse(localStorage.getItem('admins')) || [];
    const tbody = document.getElementById('adminsTableBody');
    
    tbody.innerHTML = '';
    
    admins.forEach(admin => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${admin.name}</td>
            <td>${admin.email}</td>
            <td>${admin.role}</td>
            <td class="table-actions">
                <button class="btn btn-warning btn-small" onclick="editAdmin(${admin.id})">
                    <i class="fas fa-edit"></i> Edit
                </button>
                <button class="btn btn-danger btn-small" onclick="deleteAdmin(${admin.id})">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </td>
        `;
        tbody.appendChild(row);
    });
}

function handleAdminSubmit(e) {
    e.preventDefault();
    
    const name = document.getElementById('adminName').value;
    const email = document.getElementById('adminEmail').value;
    const role = document.getElementById('adminRole').value;
    
    if (!name || !email || !role) {
        alert('Please fill in all fields');
        return;
    }
    
    const admins = JSON.parse(localStorage.getItem('admins')) || [];
    
    if (currentEditId && currentEditType === 'admin') {
        // Update existing admin
        const adminIndex = admins.findIndex(admin => admin.id === currentEditId);
        if (adminIndex !== -1) {
            admins[adminIndex] = { id: currentEditId, name, email, role };
        }
    } else {
        // Add new admin
        const newAdmin = {
            id: Date.now(),
            name,
            email,
            role
        };
        admins.push(newAdmin);
    }
    
    localStorage.setItem('admins', JSON.stringify(admins));
    loadAdmins();
    loadDashboardData();
    closeModal('adminModal');
    resetAdminForm();
}

function editAdmin(id) {
    const admins = JSON.parse(localStorage.getItem('admins')) || [];
    const admin = admins.find(admin => admin.id === id);
    
    if (admin) {
        document.getElementById('adminName').value = admin.name;
        document.getElementById('adminEmail').value = admin.email;
        document.getElementById('adminRole').value = admin.role;
        document.getElementById('adminModalTitle').textContent = 'Edit Admin';
        
        currentEditId = id;
        currentEditType = 'admin';
        
        openModal('adminModal');
    }
}

function deleteAdmin(id) {
    showConfirmation('Are you sure you want to delete this admin?', () => {
        const admins = JSON.parse(localStorage.getItem('admins')) || [];
        const filteredAdmins = admins.filter(admin => admin.id !== id);
        localStorage.setItem('admins', JSON.stringify(filteredAdmins));
        loadAdmins();
        loadDashboardData();
    });
}

function resetAdminForm() {
    document.getElementById('adminForm').reset();
    document.getElementById('adminModalTitle').textContent = 'Add Admin';
    currentEditId = null;
    currentEditType = null;
}

// Notice management functions
function loadNotices() {
    const notices = JSON.parse(localStorage.getItem('notices')) || [];
    const container = document.getElementById('noticesGrid');
    
    container.innerHTML = '';
    
    notices.forEach(notice => {
        const card = document.createElement('div');
        card.className = 'notice-card';
        card.innerHTML = `
            <div class="notice-header">
                <div>
                    <h3 class="notice-title">${notice.title}</h3>
                    <div class="notice-date">${formatDate(notice.date)}</div>
                </div>
            </div>
            <div class="notice-content">${notice.content}</div>
            <div class="notice-actions">
                <button class="btn btn-warning btn-small" onclick="editNotice(${notice.id})">
                    <i class="fas fa-edit"></i> Edit
                </button>
                <button class="btn btn-danger btn-small" onclick="deleteNotice(${notice.id})">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </div>
        `;
        container.appendChild(card);
    });
}

function handleNoticeSubmit(e) {
    e.preventDefault();
    
    const title = document.getElementById('noticeTitle').value;
    const content = document.getElementById('noticeContent').value;
    const date = document.getElementById('noticeDate').value;
    
    if (!title || !content || !date) {
        alert('Please fill in all fields');
        return;
    }
    
    const notices = JSON.parse(localStorage.getItem('notices')) || [];
    
    if (currentEditId && currentEditType === 'notice') {
        // Update existing notice
        const noticeIndex = notices.findIndex(notice => notice.id === currentEditId);
        if (noticeIndex !== -1) {
            notices[noticeIndex] = { id: currentEditId, title, content, date };
        }
    } else {
        // Add new notice
        const newNotice = {
            id: Date.now(),
            title,
            content,
            date
        };
        notices.push(newNotice);
    }
    
    localStorage.setItem('notices', JSON.stringify(notices));
    loadNotices();
    loadDashboardData();
    closeModal('noticeModal');
    resetNoticeForm();
}

function editNotice(id) {
    const notices = JSON.parse(localStorage.getItem('notices')) || [];
    const notice = notices.find(notice => notice.id === id);
    
    if (notice) {
        document.getElementById('noticeTitle').value = notice.title;
        document.getElementById('noticeContent').value = notice.content;
        document.getElementById('noticeDate').value = notice.date;
        document.getElementById('noticeModalTitle').textContent = 'Edit Notice';
        
        currentEditId = id;
        currentEditType = 'notice';
        
        openModal('noticeModal');
    }
}

function deleteNotice(id) {
    showConfirmation('Are you sure you want to delete this notice?', () => {
        const notices = JSON.parse(localStorage.getItem('notices')) || [];
        const filteredNotices = notices.filter(notice => notice.id !== id);
        localStorage.setItem('notices', JSON.stringify(filteredNotices));
        loadNotices();
        loadDashboardData();
    });
}

function resetNoticeForm() {
    document.getElementById('noticeForm').reset();
    document.getElementById('noticeModalTitle').textContent = 'Add Notice';
    currentEditId = null;
    currentEditType = null;
}

// Modal functions
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.classList.add('active');
    
    // Set today's date as default for notice date
    if (modalId === 'noticeModal' && !currentEditId) {
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('noticeDate').value = today;
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.classList.remove('active');
    
    // Reset forms when closing
    if (modalId === 'userModal') {
        resetUserForm();
    } else if (modalId === 'adminModal') {
        resetAdminForm();
    } else if (modalId === 'noticeModal') {
        resetNoticeForm();
    }
}

// Confirmation modal
function showConfirmation(message, callback) {
    document.getElementById('confirmMessage').textContent = message;
    document.getElementById('confirmBtn').onclick = () => {
        callback();
        closeModal('confirmModal');
    };
    openModal('confirmModal');
}

// Utility functions
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

// Handle window resize
window.addEventListener('resize', function() {
    if (window.innerWidth > 768) {
        closeSidebar();
    }
});

// Prevent form submission on Enter key in input fields
document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && e.target.tagName === 'INPUT') {
        e.preventDefault();
    }
});
