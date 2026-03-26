<?php
function renderHeader($title = 'Team Control', $activePage = '') {
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> | Team Control</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0"></script>
</head>
<body>
<div class="layout">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon">TC</div>
            <span class="brand-name">Team<br>Control</span>
            <button class="sidebar-close-mobile" onclick="toggleSidebar()">✕</button>
        </div>
        <nav class="sidebar-nav">
            <a href="index.php" class="nav-item <?= $activePage === 'dashboard' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                <span>Dashboard</span>
            </a>
            <a href="equipes.php" class="nav-item <?= $activePage === 'equipes' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span>Equipes</span>
            </a>
            <a href="funcionarios.php" class="nav-item <?= $activePage === 'funcionarios' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <span>Funcionários</span>
            </a>
            <a href="importar.php" class="nav-item <?= $activePage === 'importar' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                <span>Importar</span>
            </a>
        </nav>
        <div class="sidebar-footer">
            <div class="sidebar-ver">v1.0</div>
        </div>
    </aside>
    <main class="main-content">
        <div class="topbar">
            <div style="display:flex;align-items:center;gap:12px">
                <button class="menu-toggle" onclick="toggleSidebar()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
                <h1 class="page-title"><?= htmlspecialchars($title) ?></h1>
            </div>
            <div class="topbar-date"><?= date('d/m/Y') ?></div>
        </div>
        <div class="content-area">
<?php
}

function renderFooter() {
?>
        </div>
    </main>
</div>

<!-- Toast notifications -->
<div id="toast-container"></div>

<!-- Global Modal -->
<div id="modal-overlay" class="modal-overlay" style="display:none">
    <div class="modal-box" id="modal-box">
        <div class="modal-header">
            <h3 id="modal-title"></h3>
            <button class="modal-close" onclick="closeModal()">✕</button>
        </div>
        <div id="modal-body"></div>
    </div>
</div>

<script src="assets/js/main.js"></script>
</body>
</html>
<?php
}
