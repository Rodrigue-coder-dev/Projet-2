// =====================================================
// CONFIGURATION
// =====================================================

const API_BASE = 'http://localhost:8081/labo_academy/api';
let currentUser = null;
let currentPage = 'home';

// =====================================================
// UTILITAIRES
// =====================================================

function showLoading(show) {
    document.getElementById('loading').style.display = show ? 'flex' : 'none';
}

function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
        <span style="margin-left: 10px;">${message}</span>
    `;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

async function apiCall(endpoint, method = 'GET', data = null) {
    const url = `${API_BASE}/${endpoint}`;
    const options = {
        method,
        headers: {
            'Content-Type': 'application/json',
        },
        credentials: 'include'
    };
    
    if (data && (method === 'POST' || method === 'PUT')) {
        options.body = JSON.stringify(data);
    }
    
    try {
        const response = await fetch(url, options);
        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.error || 'Erreur serveur');
        }
        return result;
    } catch (error) {
        showToast(error.message, 'error');
        throw error;
    }
}

// =====================================================
// AUTHENTIFICATION
// =====================================================

async function login(email, password) {
    showLoading(true);
    try {
        const result = await apiCall('auth.php?action=login', 'POST', { email, password });
        if (result.success) {
            currentUser = result.user;
            localStorage.setItem('user', JSON.stringify(currentUser));
            showToast('Connexion réussie !');
            await checkAuth();
            renderDashboard();
        }
        return result;
    } finally {
        showLoading(false);
    }
}

async function register(userData) {
    showLoading(true);
    try {
        const result = await apiCall('auth.php?action=register', 'POST', userData);
        if (result.success) {
            showToast('Inscription réussie ! Vous pouvez maintenant vous connecter.');
            showLoginPage();
        }
        return result;
    } finally {
        showLoading(false);
    }
}

async function logout() {
    await apiCall('auth.php?action=logout', 'POST');
    currentUser = null;
    localStorage.removeItem('user');
    showToast('Déconnexion réussie');
    showLoginPage();
}

async function forgotPassword(email) {
    showLoading(true);
    try {
        const result = await apiCall('auth.php?action=forgot-password', 'POST', { email });
        showToast('Email de réinitialisation envoyé');
        return result;
    } finally {
        showLoading(false);
    }
}

async function resetPassword(token, newPassword) {
    showLoading(true);
    try {
        const result = await apiCall('auth.php?action=reset-password', 'POST', { token, new_password: newPassword });
        showToast('Mot de passe réinitialisé !');
        showLoginPage();
        return result;
    } finally {
        showLoading(false);
    }
}

async function checkAuth() {
    try {
        const result = await apiCall('auth.php?action=check', 'GET');
        if (result.authenticated) {
            currentUser = result.user;
            return true;
        }
        return false;
    } catch {
        return false;
    }
}

// =====================================================
// RENDU DES PAGES
// =====================================================

function showLoginPage() {
    const container = document.getElementById('page-content');
    container.innerHTML = `
        <div class="auth-container">
            <div class="auth-card">
                <div class="auth-header">
                    <img src="assets/images/logo.png" alt="LABO ACADEMY" class="auth-logo">
                    <h2>LABO ACADEMY</h2>
                    <p>Centre de Formation en Programmation</p>
                </div>
                <div class="auth-body">
                    <form id="loginForm">
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" id="email" required placeholder="votre@email.com">
                        </div>
                        <div class="form-group">
                            <label>Mot de passe</label>
                            <input type="password" id="password" required placeholder="••••••••">
                        </div>
                        <button type="submit">Se connecter</button>
                    </form>
                </div>
                <div class="auth-footer">
                    <a href="#" id="showRegister">Créer un compte</a> |
                    <a href="#" id="showForgot">Mot de passe oublié ?</a>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('loginForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        await login(
            document.getElementById('email').value,
            document.getElementById('password').value
        );
    });
    
    document.getElementById('showRegister').addEventListener('click', (e) => {
        e.preventDefault();
        showRegisterPage();
    });
    
    document.getElementById('showForgot').addEventListener('click', (e) => {
        e.preventDefault();
        showForgotPage();
    });
}

function showRegisterPage() {
    const container = document.getElementById('page-content');
    container.innerHTML = `
        <div class="auth-container">
            <div class="auth-card">
                <div class="auth-header">
                    <img src="assets/images/logo.png" alt="LABO ACADEMY" class="auth-logo">
                    <h2>Inscription</h2>
                    <p>Rejoignez LABO ACADEMY</p>
                </div>
                <div class="auth-body">
                    <form id="registerForm">
                        <div class="form-group">
                            <label>Nom</label>
                            <input type="text" id="last_name" required>
                        </div>
                        <div class="form-group">
                            <label>Prénom</label>
                            <input type="text" id="first_name" required>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" id="email" required>
                        </div>
                        <div class="form-group">
                            <label>Téléphone</label>
                            <input type="tel" id="phone">
                        </div>
                        <div class="form-group">
                            <label>Formation</label>
                            <select id="level_id" required></select>
                        </div>
                        <div class="form-group">
                            <label>Mot de passe</label>
                            <input type="password" id="password" required>
                        </div>
                        <button type="submit">S'inscrire</button>
                    </form>
                </div>
                <div class="auth-footer">
                    <a href="#" id="showLogin">Déjà inscrit ? Se connecter</a>
                </div>
            </div>
        </div>
    `;
    
    // Charger les formations
    loadLevelsForSelect();
    
    document.getElementById('registerForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        await register({
            first_name: document.getElementById('first_name').value,
            last_name: document.getElementById('last_name').value,
            email: document.getElementById('email').value,
            phone: document.getElementById('phone').value,
            level_id: document.getElementById('level_id').value,
            password: document.getElementById('password').value
        });
    });
    
    document.getElementById('showLogin').addEventListener('click', (e) => {
        e.preventDefault();
        showLoginPage();
    });
}

function showForgotPage() {
    const container = document.getElementById('page-content');
    container.innerHTML = `
        <div class="auth-container">
            <div class="auth-card">
                <div class="auth-header">
                    <img src="assets/images/logo.png" alt="LABO ACADEMY" class="auth-logo">
                    <h2>Mot de passe oublié</h2>
                </div>
                <div class="auth-body">
                    <form id="forgotForm">
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" id="email" required>
                        </div>
                        <button type="submit">Envoyer</button>
                    </form>
                </div>
                <div class="auth-footer">
                    <a href="#" id="showLogin">Retour à la connexion</a>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('forgotForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        await forgotPassword(document.getElementById('email').value);
    });
    
    document.getElementById('showLogin').addEventListener('click', (e) => {
        e.preventDefault();
        showLoginPage();
    });
}

// =====================================================
// RENDU DASHBOARD
// =====================================================

async function renderDashboard() {
    if (!currentUser) return;
    
    const container = document.getElementById('page-content');
    
    if (currentUser.role === 'admin') {
        await renderAdminDashboard(container);
    } else {
        await renderStudentDashboard(container);
    }
}

async function renderAdminDashboard(container) {
    // Charger les stats
    const stats = await apiCall('dashboard.php');
    
    container.innerHTML = `
        <div class="dashboard-container">
            <div class="sidebar">
                <div class="sidebar-header">
                    <img src="assets/images/logo.png" alt="LABO ACADEMY" class="sidebar-logo">
                    <h3>LABO ACADEMY</h3>
                    <small>Administrateur</small>
                </div>
                <div class="sidebar-nav">
                    <div class="nav-item active" data-page="dashboard">
                        <i class="fas fa-chart-line"></i> Tableau de bord
                    </div>
                    <div class="nav-item" data-page="students">
                        <i class="fas fa-users"></i> Étudiants
                    </div>
                    <div class="nav-item" data-page="courses">
                        <i class="fas fa-book"></i> Cours & Formations
                    </div>
                    <div class="nav-item" data-page="timetable">
                        <i class="fas fa-calendar-alt"></i> Emploi du temps
                    </div>
                    <div class="nav-item" data-page="payments">
                        <i class="fas fa-money-bill-wave"></i> Paiements
                    </div>
                    <div class="nav-item" data-page="grades">
                        <i class="fas fa-graduation-cap"></i> Notes
                    </div>
                </div>
            </div>
            
            <div class="main-content">
                <div class="top-bar">
                    <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
                    <h1 class="page-title" id="pageTitle">Tableau de bord</h1>
                    <div class="user-info">
                        <span class="user-name">${currentUser.name}</span>
                        <img src="assets/images/avatar-default.png" class="user-avatar" id="userAvatar">
                        <button class="logout-btn" id="logoutBtn"><i class="fas fa-sign-out-alt"></i></button>
                    </div>
                </div>
                <div id="dynamicContent"></div>
            </div>
        </div>
    `;
    
    // Afficher les stats
    const dynamicContent = document.getElementById('dynamicContent');
    dynamicContent.innerHTML = `
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <h3>${stats.active_students || 0}</h3>
                    <p>Étudiants actifs</p>
                </div>
                <div class="stat-icon blue"><i class="fas fa-users"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>${stats.active_courses || 0}</h3>
                    <p>Cours actifs</p>
                </div>
                <div class="stat-icon green"><i class="fas fa-book"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>${stats.total_formations || 0}</h3>
                    <p>Formations</p>
                </div>
                <div class="stat-icon purple"><i class="fas fa-layer-group"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>${(stats.total_payments || 0).toLocaleString()} FCFA</h3>
                    <p>Total paiements</p>
                </div>
                <div class="stat-icon orange"><i class="fas fa-chart-line"></i></div>
            </div>
        </div>
        
        <div class="data-table">
            <h3 style="padding: 15px;">Étudiants avec solde restant</h3>
            <table>
                <thead>
                    <tr>
                        <th>Matricule</th>
                        <th>Nom complet</th>
                        <th>Total dû</th>
                        <th>Payé</th>
                        <th>Solde</th>
                    </tr>
                </thead>
                <tbody>
                    ${(stats.students_with_balance || []).map(s => `
                        <tr>
                            <td>${s.matricule}</td>
                            <td>${s.first_name} ${s.last_name}</td>
                            <td>${s.total_due.toLocaleString()} FCFA</td>
                            <td>${s.total_paid.toLocaleString()} FCFA</td>
                            <td style="color: #ef4444;">${s.balance.toLocaleString()} FCFA</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
    
    // Gestion navigation
    document.querySelectorAll('.nav-item').forEach(item => {
        item.addEventListener('click', () => {
            document.querySelectorAll('.nav-item').forEach(nav => nav.classList.remove('active'));
            item.classList.add('active');
            const page = item.dataset.page;
            loadAdminPage(page);
        });
    });
    
    document.getElementById('logoutBtn').addEventListener('click', logout);
}

async function renderStudentDashboard(container) {
    const data = await apiCall('dashboard.php');
    
    container.innerHTML = `
        <div class="dashboard-container">
            <div class="sidebar">
                <div class="sidebar-header">
                    <img src="assets/images/logo.png" alt="LABO ACADEMY" class="sidebar-logo">
                    <h3>LABO ACADEMY</h3>
                    <small>Étudiant</small>
                </div>
                <div class="sidebar-nav">
                    <div class="nav-item active" data-page="dashboard">
                        <i class="fas fa-home"></i> Accueil
                    </div>
                    <div class="nav-item" data-page="courses">
                        <i class="fas fa-book"></i> Mes cours
                    </div>
                    <div class="nav-item" data-page="timetable">
                        <i class="fas fa-calendar-alt"></i> Emploi du temps
                    </div>
                    <div class="nav-item" data-page="grades">
                        <i class="fas fa-chart-simple"></i> Mes notes
                    </div>
                    <div class="nav-item" data-page="payments">
                        <i class="fas fa-credit-card"></i> Paiements
                    </div>
                    <div class="nav-item" data-page="documents">
                        <i class="fas fa-file-alt"></i> Documents
                    </div>
                </div>
            </div>
            
            <div class="main-content">
                <div class="top-bar">
                    <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
                    <h1 class="page-title" id="pageTitle">Mon Espace</h1>
                    <div class="user-info">
                        <span class="user-name">${currentUser.name}</span>
                        <img src="assets/images/avatar-default.png" class="user-avatar" id="userAvatar">
                        <button class="logout-btn" id="logoutBtn"><i class="fas fa-sign-out-alt"></i></button>
                    </div>
                </div>
                <div id="dynamicContent"></div>
            </div>
        </div>
    `;
    
    // Afficher contenu dashboard étudiant
    const dynamicContent = document.getElementById('dynamicContent');
    dynamicContent.innerHTML = `
        <div class="hero-section" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <h1 class="hero-title">Bienvenue ${data.student.first_name} !</h1>
            <p class="hero-subtitle">Matricule: ${data.student.matricule} | Formation: ${data.student.formation}</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <h3>${data.overall_average || 0}/20</h3>
                    <p>Moyenne générale</p>
                </div>
                <div class="stat-icon blue"><i class="fas fa-chart-line"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>${(data.remaining_balance || 0).toLocaleString()} FCFA</h3>
                    <p>Solde restant</p>
                </div>
                <div class="stat-icon orange"><i class="fas fa-money-bill-wave"></i></div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3>${data.courses?.length || 0}</h3>
                    <p>Cours inscrits</p>
                </div>
                <div class="stat-icon green"><i class="fas fa-book"></i></div>
            </div>
        </div>
        
        <div class="data-table">
            <h3 style="padding: 15px;">Mes cours</h3>
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Nom du cours</th>
                        <th>Crédits</th>
                        <th>Semestre</th>
                        <th>Formateur</th>
                    </tr>
                </thead>
                <tbody>
                    ${(data.courses || []).map(c => `
                        <tr>
                            <td>${c.code}</td>
                            <td>${c.name}</td>
                            <td>${c.credits}</td>
                            <td>Semestre ${c.semester}</td>
                            <td>${c.teacher_first} ${c.teacher_last}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
    
    document.getElementById('logoutBtn').addEventListener('click', logout);
}

// =====================================================
// CHARGEMENT DES PAGES ADMIN
// =====================================================

async function loadAdminPage(page) {
    const content = document.getElementById('dynamicContent');
    const title = document.getElementById('pageTitle');
    
    switch(page) {
        case 'students':
            title.innerText = 'Gestion des étudiants';
            await loadStudentsPage(content);
            break;
        case 'courses':
            title.innerText = 'Cours & Formations';
            await loadCoursesPage(content);
            break;
        case 'timetable':
            title.innerText = 'Emploi du temps';
            await loadTimetablePage(content);
            break;
        case 'payments':
            title.innerText = 'Gestion des paiements';
            await loadPaymentsPage(content);
            break;
        case 'grades':
            title.innerText = 'Saisie des notes';
            await loadGradesPage(content);
            break;
        default:
            location.reload();
    }
}

async function loadStudentsPage(container) {
    const students = await apiCall('students.php');
    
    container.innerHTML = `
        <div style="margin-bottom: 20px;">
            <button class="btn btn-primary" id="addStudentBtn"><i class="fas fa-plus"></i> Ajouter un étudiant</button>
        </div>
        <div class="data-table">
            <table>
                <thead>
                    <tr>
                        <th>Matricule</th>
                        <th>Nom complet</th>
                        <th>Email</th>
                        <th>Formation</th>
                        <th>Statut</th>
                        <th>Solde</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${students.map(s => `
                        <tr>
                            <td>${s.matricule}</td>
                            <td>${s.first_name} ${s.last_name}</td>
                            <td>${s.email}</td>
                            <td>${s.formation}</td>
                            <td><span class="badge ${s.status === 'ACTIVE' ? 'badge-success' : 'badge-warning'}">${s.status}</span></td>
                            <td>${s.remaining_balance?.toLocaleString() || 0} FCFA</td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick="editStudent(${s.id})"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-sm btn-danger" onclick="deleteStudent(${s.id})"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
    
    document.getElementById('addStudentBtn')?.addEventListener('click', () => showStudentModal());
}

async function loadCoursesPage(container) {
    const courses = await apiCall('courses.php');
    const levels = await apiCall('levels.php');
    
    container.innerHTML = `
        <div style="margin-bottom: 20px;">
            <button class="btn btn-primary" id="addCourseBtn"><i class="fas fa-plus"></i> Ajouter un cours</button>
            <button class="btn btn-primary" id="addLevelBtn" style="margin-left: 10px;"><i class="fas fa-plus"></i> Ajouter une formation</button>
        </div>
        <div class="data-table">
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Nom du cours</th>
                        <th>Crédits</th>
                        <th>Formation</th>
                        <th>Formateur</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${courses.map(c => `
                        <tr>
                            <td>${c.code}</td>
                            <td>${c.name}</td>
                            <td>${c.credits}</td>
                            <td>${c.level_name}</td>
                            <td>${c.teacher_name}</td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick="editCourse(${c.id})"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-sm btn-danger" onclick="deleteCourse(${c.id})"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

async function loadPaymentsPage(container) {
    const payments = await apiCall('payments.php');
    const students = await apiCall('students.php');
    
    container.innerHTML = `
        <div style="margin-bottom: 20px;">
            <button class="btn btn-primary" id="addPaymentBtn"><i class="fas fa-plus"></i> Enregistrer paiement</button>
        </div>
        <div class="data-table">
            <table>
                <thead>
                    <tr>
                        <th>Référence</th>
                        <th>Étudiant</th>
                        <th>Montant</th>
                        <th>Date</th>
                        <th>Mode</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${payments.map(p => `
                        <tr>
                            <td>${p.reference}</td>
                            <td>${p.first_name} ${p.last_name}</td>
                            <td>${p.amount.toLocaleString()} FCFA</td>
                            <td>${p.payment_date}</td>
                            <td>${p.payment_method}</td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick="downloadInvoice(${p.id})"><i class="fas fa-file-pdf"></i> Facture</button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
    
    document.getElementById('addPaymentBtn')?.addEventListener('click', () => showPaymentModal(students));
}

async function loadGradesPage(container) {
    const students = await apiCall('students.php');
    const courses = await apiCall('courses.php');
    
    container.innerHTML = `
        <div style="margin-bottom: 20px;">
            <button class="btn btn-primary" id="addGradeBtn"><i class="fas fa-plus"></i> Saisir note</button>
        </div>
        <div class="data-table" id="gradesTable">
            <!-- Les notes seront chargées ici -->
        </div>
    `;
    
    document.getElementById('addGradeBtn')?.addEventListener('click', () => showGradeModal(students, courses));
    await loadGradesList();
}

async function loadGradesList() {
    const grades = await apiCall('grades.php');
    const container = document.getElementById('gradesTable');
    
    container.innerHTML = `
        <table>
            <thead>
                <tr>
                    <th>Étudiant</th>
                    <th>Cours</th>
                    <th>Type</th>
                    <th>Note</th>
                    <th>/20</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                ${grades.map(g => `
                    <tr>
                        <td>${g.student_name}</td>
                        <td>${g.course_name}</td>
                        <td>${g.grade_type}</td>
                        <td>${g.score}/${g.max_score}</td>
                        <td style="color: ${g.grade_over_20 >= 10 ? '#10b981' : '#ef4444'}">${g.grade_over_20}/20</td>
                        <td>
                            <button class="btn btn-sm btn-danger" onclick="deleteGrade(${g.id})"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
}

async function loadTimetablePage(container) {
    const timetable = await apiCall('timetable.php');
    const days = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY'];
    const dayNames = { MONDAY: 'Lundi', TUESDAY: 'Mardi', WEDNESDAY: 'Mercredi', THURSDAY: 'Jeudi', FRIDAY: 'Vendredi', SATURDAY: 'Samedi' };
    
    container.innerHTML = `
        <div style="margin-bottom: 20px;">
            <button class="btn btn-primary" id="addTimetableBtn"><i class="fas fa-plus"></i> Ajouter séance</button>
        </div>
        <div class="timetable-grid">
            <table class="timetable-table">
                <thead>
                    <tr>
                        <th>Horaire</th>
                        ${days.map(d => `<th>${dayNames[d]}</th>`).join('')}
                    </tr>
                </thead>
                <tbody id="timetableBody"></tbody>
            </table>
        </div>
    `;
    
    // Générer le tableau des horaires
    const hours = ['08:00', '10:00', '12:00', '14:00', '16:00', '18:00'];
    const tbody = document.getElementById('timetableBody');
    
    for (const hour of hours) {
        let row = `<tr><td style="background: var(--light); font-weight: 600;">${hour}</td>`;
        for (const day of days) {
            const courses = timetable.filter(t => t.day_of_week === day && t.start_time <= hour && t.end_time > hour);
            let cellContent = '';
            if (courses.length > 0) {
                cellContent = courses.map(c => `
                    <div class="course-cell">
                        <div class="course-name">${c.course_name}</div>
                        <div class="course-time">${c.start_time} - ${c.end_time}</div>
                        <div class="course-classroom">Salle: ${c.classroom}</div>
                    </div>
                `).join('');
            }
            row += `<td>${cellContent}</td>`;
        }
        row += `</tr>`;
        tbody.innerHTML += row;
    }
}

// =====================================================
// INITIALISATION
// =====================================================

async function init() {
    const isAuthenticated = await checkAuth();
    
    if (isAuthenticated && currentUser) {
        renderDashboard();
    } else {
        showLoginPage();
    }
}

// Exposer les fonctions globales
window.editStudent = (id) => console.log('Edit student', id);
window.deleteStudent = async (id) => {
    if (confirm('Supprimer cet étudiant ?')) {
        await apiCall(`students.php?id=${id}`, 'DELETE');
        showToast('Étudiant supprimé');
        loadStudentsPage(document.getElementById('dynamicContent'));
    }
};
window.downloadInvoice = async (id) => {
    const result = await apiCall(`payments.php?action=invoice&id=${id}`, 'GET');
    const win = window.open();
    win.document.write(result.html);
    win.document.close();
};
window.editCourse = (id) => console.log('Edit course', id);
window.deleteCourse = async (id) => {
    if (confirm('Supprimer ce cours ?')) {
        await apiCall(`courses.php?id=${id}`, 'DELETE');
        showToast('Cours supprimé');
        loadCoursesPage(document.getElementById('dynamicContent'));
    }
};
window.deleteGrade = async (id) => {
    if (confirm('Supprimer cette note ?')) {
        await apiCall(`grades.php?id=${id}`, 'DELETE');
        showToast('Note supprimée');
        loadGradesList();
    }
};

// Démarrage
init();