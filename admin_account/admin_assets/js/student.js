/**
 * Student Management JavaScript
 * Handles all frontend functionality for the students page
 * Clean, Simple, User-Friendly Design
 */

class StudentManager {
    constructor() {
        this.currentPage = 1;
        this.csrfToken = '';
        this.isLoading = false;
        this.init();
    }

    async init() {
        await this.loadCSRFToken();
        this.bindEvents();
        await this.loadStudents();
        await this.loadStatistics();
    }

    async loadCSRFToken() {
        try {
            const response = await fetch('api/student_api.php?action=csrf_token');
            const data = await response.json();
            this.csrfToken = data.token;
        } catch (error) {
            console.error('Error loading CSRF token:', error);
        }
    }

    bindEvents() {
        // Add student button
        document.getElementById('add-student-btn')?.addEventListener('click', () => this.openAddModal());

        // Modal close buttons
        document.querySelectorAll('.close-modal, .cancel-btn').forEach(btn => {
            btn.addEventListener('click', () => this.closeAllModals());
        });

        // Close modal on outside click
        window.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                this.closeAllModals();
            }
        });

        // Close modal on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeAllModals();
            }
        });

        // Form submissions
        document.getElementById('add-student-form')?.addEventListener('submit', (e) => this.handleAddSubmit(e));
        document.getElementById('edit-student-form')?.addEventListener('submit', (e) => this.handleEditSubmit(e));

        // Search functionality with debounce
        const searchInput = document.getElementById('search-input');
        if (searchInput) {
            searchInput.addEventListener('input', this.debounce(() => {
                this.currentPage = 1;
                this.loadStudents();
            }, 400));
        }

        // Filter functionality
        document.getElementById('grade-filter')?.addEventListener('change', () => {
            this.currentPage = 1;
            this.loadStudents();
        });
        
        document.getElementById('gender-filter')?.addEventListener('change', () => {
            this.currentPage = 1;
            this.loadStudents();
        });

        // Edit image click
        document.getElementById('edit-student-profile-img')?.addEventListener('click', () => {
            document.getElementById('edit-student-image').click();
        });

        // Preview uploaded image
        document.getElementById('edit-student-image')?.addEventListener('change', (e) => this.previewImage(e));
    }

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    openAddModal() {
        document.getElementById('add-student-form')?.reset();
        document.getElementById('add-student-modal').style.display = 'block';
    }

    openEditModal(student) {
        const modal = document.getElementById('edit-student-modal');
        
        // Populate form fields
        document.getElementById('edit-student-name').value = `${student.first_name} ${student.last_name}`;
        document.getElementById('edit-student-id').value = student.student_id;
        document.getElementById('edit-student-grade').value = student.grade_level;
        document.getElementById('edit-student-gender').value = student.gender;
        document.getElementById('edit-student-age').value = student.age;
        
        // Set profile image
const profileImg = document.getElementById('edit-student-profile-img');
        profileImg.src = student.profile_image || '../assets/img/person/unknown.jpg';
        profileImg.onerror = function() {
            this.src = '../assets/img/person/unknown.jpg';
        };
        
        modal.style.display = 'block';
    }

    closeAllModals() {
        document.querySelectorAll('.modal').forEach(modal => {
            modal.style.display = 'none';
        });
    }

    setLoadingState(loading, containerId = 'table-card') {
        const container = document.getElementById(containerId);
        if (!container) return;

        if (loading) {
            container.classList.add('table-loading');
            container.style.position = 'relative';
            
            // Add spinner if not exists
            if (!container.querySelector('.loading-overlay')) {
                const spinner = document.createElement('div');
                spinner.className = 'loading-overlay';
                spinner.innerHTML = '<div class="loading-spinner"></div>';
                container.appendChild(spinner);
            }
        } else {
            container.classList.remove('table-loading');
            const spinner = container.querySelector('.loading-overlay');
            if (spinner) spinner.remove();
        }
    }

    async loadStudents() {
        if (this.isLoading) return;
        this.isLoading = true;

        const search = document.getElementById('search-input')?.value || '';
        const grade = document.getElementById('grade-filter')?.value || '';
        const gender = document.getElementById('gender-filter')?.value || '';

        this.setLoadingState(true, 'table-card');

        try {
            const params = new URLSearchParams({
                action: 'list',
                search,
                grade,
                gender,
                page: this.currentPage,
                per_page: 20
            });

            const response = await fetch(`api/student_api.php?${params}`);
            const data = await response.json();

            this.renderStudentTable(data.students);
            this.renderPagination(data);
        } catch (error) {
            console.error('Error loading students:', error);
            this.showToast('Error loading students. Please try again.', 'error');
        } finally {
            this.setLoadingState(false, 'table-card');
            this.isLoading = false;
        }
    }

    renderStudentTable(students) {
        const tbody = document.querySelector('.student-table tbody');
        if (!tbody) return;

        if (!students || students.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <i class="fas fa-user-slash"></i>
                            <p>No students found. Try adjusting your search or filters.</p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

tbody.innerHTML = students.map(student => {
            const fullName = `${student.first_name || ''} ${student.last_name || ''}`.trim();
            const age = student.age || 'N/A';
            const imageSrc = student.profile_image || '../assets/img/person/unknown.jpg';

            return `
                <tr data-id="${student.student_id}" data-image="${this.escapeHtml(student.profile_image || '')}">
                    <td>
                        <img src="${this.escapeHtml(imageSrc)}" alt="${this.escapeHtml(fullName)}" class="student-avatar" onerror="this.src='../assets/img/person/unknown.jpg'">
                    </td>
                    <td>${this.escapeHtml(fullName)}</td>
                    <td>${this.escapeHtml(student.student_id || 'N/A')}</td>
                    <td>${this.escapeHtml(student.grade_level || 'N/A')}</td>
                    <td>${this.escapeHtml(age)}</td>
                    <td>${this.escapeHtml(student.gender || 'N/A')}</td>
                    <td class="actions">
                        <a href="#" class="edit" title="Edit" data-id="${this.escapeHtml(student.student_id)}" data-image="${this.escapeHtml(student.profile_image || '')}">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="#" class="delete" title="Delete" data-id="${this.escapeHtml(student.student_id)}">
                            <i class="fas fa-trash"></i>
                        </a>
                    </td>
                </tr>
            `;
        }).join('');

// Bind edit and delete events
        tbody.querySelectorAll('.edit').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                // Store the image path from the edit button's data attribute
                const imagePath = btn.dataset.image || '../assets/img/person/unknown.jpg';
                this.handleEditClick(btn.dataset.id, imagePath);
            });
        });

        tbody.querySelectorAll('.delete').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleDeleteClick(btn.dataset.id);
            });
        });
    }

    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    renderPagination(data) {
        const paginationContainer = document.getElementById('pagination');
        if (!paginationContainer) return;

        const totalPages = data.totalPages || 1;
        
        if (totalPages <= 1) {
            paginationContainer.innerHTML = '';
            return;
        }

        let html = '';
        
        // Previous button
        if (data.page > 1) {
            html += `<button class="pagination-btn" data-page="${data.page - 1}"><i class="fas fa-chevron-left"></i></button>`;
        }

        // Page numbers
        for (let i = 1; i <= totalPages; i++) {
            if (i === data.page) {
                html += `<button class="pagination-btn active">${i}</button>`;
            } else if (i === 1 || i === totalPages || (i >= data.page - 1 && i <= data.page + 1)) {
                html += `<button class="pagination-btn" data-page="${i}">${i}</button>`;
            } else if (i === data.page - 2 || i === data.page + 2) {
                html += `<span class="pagination-ellipsis">...</span>`;
            }
        }

        // Next button
        if (data.page < totalPages) {
            html += `<button class="pagination-btn" data-page="${data.page + 1}"><i class="fas fa-chevron-right"></i></button>`;
        }

        paginationContainer.innerHTML = html;

        // Bind click events
        paginationContainer.querySelectorAll('.pagination-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                this.currentPage = parseInt(btn.dataset.page);
                this.loadStudents();
                // Scroll to top of table
                document.getElementById('table-card')?.scrollIntoView({ behavior: 'smooth' });
            });
        });
    }

async handleEditClick(studentId, imagePath = null) {
        try {
            const response = await fetch(`api/student_api.php?action=get&id=${studentId}`);
            const student = await response.json();

            if (student && student.student_id) {
                // Use the provided image path or fall back to the student's profile_image
                if (!student.profile_image && imagePath) {
                    student.profile_image = imagePath;
                }
                this.openEditModal(student);
            } else {
                this.showToast('Student not found', 'error');
            }
        } catch (error) {
            console.error('Error loading student:', error);
            this.showToast('Error loading student data', 'error');
        }
    }

    async handleDeleteClick(studentId) {
        if (!confirm('Are you sure you want to delete this student? This action cannot be undone.')) {
            return;
        }

        try {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('student_id', studentId);
            formData.append('csrf_token', this.csrfToken);

            const response = await fetch('api/student_api.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.showToast('Student deleted successfully', 'success');
                await this.loadStudents();
                await this.loadStatistics();
            } else {
                this.showToast(result.message || 'Error deleting student', 'error');
            }
        } catch (error) {
            console.error('Error deleting student:', error);
            this.showToast('Error deleting student', 'error');
        }
    }

    async handleAddSubmit(e) {
        e.preventDefault();
        const form = e.target;
        const submitBtn = form.querySelector('.submit-btn');
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

        try {
            const formData = new FormData(form);
            formData.append('csrf_token', this.csrfToken);

            const response = await fetch('api/student_api.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.showToast('Student added successfully', 'success');
                this.closeAllModals();
                form.reset();
                await this.loadStudents();
                await this.loadStatistics();
            } else {
                this.showToast(result.message || 'Error adding student', 'error');
            }
        } catch (error) {
            console.error('Error adding student:', error);
            this.showToast('Error adding student', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save"></i> Save Student';
        }
    }

    async handleEditSubmit(e) {
        e.preventDefault();
        const form = e.target;
        const submitBtn = form.querySelector('.submit-btn');
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

        try {
            const formData = new FormData(form);
            formData.append('csrf_token', this.csrfToken);

            const response = await fetch('api/student_api.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.showToast('Student updated successfully', 'success');
                this.closeAllModals();
                await this.loadStudents();
                await this.loadStatistics();
            } else {
                this.showToast(result.message || 'Error updating student', 'error');
            }
        } catch (error) {
            console.error('Error updating student:', error);
            this.showToast('Error updating student', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
        }
    }

    previewImage(e) {
        const file = e.target.files[0];
        if (file) {
            // Validate file size (2MB max)
            if (file.size > 2 * 1024 * 1024) {
                this.showToast('Image size must be less than 2MB', 'error');
                e.target.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = (e) => {
                document.getElementById('edit-student-profile-img').src = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    }

    async loadStatistics() {
        try {
            const response = await fetch('api/student_api.php?action=stats');
            const stats = await response.json();

            this.updateStatsDisplay(stats);
        } catch (error) {
            console.error('Error loading statistics:', error);
        }
    }

    updateStatsDisplay(stats) {
        // Update total students count
        const totalElement = document.getElementById('total-students-count');
        if (totalElement) {
            totalElement.textContent = stats.totalStudents || 0;
        }
    }

    showToast(message, type = 'info') {
        const container = document.getElementById('toast-container');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = `toast-notification toast-${type}`;
        
        const iconMap = {
            success: 'check-circle',
            error: 'exclamation-circle',
            info: 'info-circle'
        };

        toast.innerHTML = `
            <i class="fas fa-${iconMap[type] || 'info-circle'}"></i>
            <span>${message}</span>
        `;

        container.appendChild(toast);

        // Auto remove after 4 seconds
        setTimeout(() => {
            toast.style.animation = 'slideOutRight 0.3s ease forwards';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    window.studentManager = new StudentManager();
});
