function toggleSidebar() {
    document.body.classList.toggle('sidebar-open');
}

function showToast(msg, type = 'success') {
    const tc = document.getElementById('toast-container');
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.innerHTML = `<span>${type === 'success' ? '✓' : '✕'}</span>${msg}`;
    tc.appendChild(t);
    setTimeout(() => t.remove(), 3500);
}

function openModal(title, html) {
    document.getElementById('modal-title').textContent = title;
    document.getElementById('modal-body').innerHTML = html;
    document.getElementById('modal-overlay').style.display = 'flex';
}

function closeModal() {
    document.getElementById('modal-overlay').style.display = 'none';
}

document.addEventListener('click', function(e) {
    if (e.target.id === 'modal-overlay') closeModal();
});

// Check URL params for toast messages
(function() {
    const p = new URLSearchParams(window.location.search);
    if (p.get('msg')) showToast(decodeURIComponent(p.get('msg')), p.get('type') || 'success');
    if (p.get('error')) showToast(decodeURIComponent(p.get('error')), 'error');
})();

// Edit meta modal
function editMeta(key, currentValue, label) {
    openModal(`Editar Meta — ${label}`, `
        <form method="POST" action="actions.php" style="display:flex;flex-direction:column;gap:16px">
            <input type="hidden" name="action" value="update_meta">
            <input type="hidden" name="key" value="${key}">
            <div class="form-group">
                <label class="form-label">Nova meta</label>
                <input class="form-control" type="number" name="value" value="${currentValue}" min="1" required>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Salvar</button>
                <button class="btn btn-ghost" type="button" onclick="closeModal()">Cancelar</button>
            </div>
        </form>
    `);
}

// Date modal for desalocation
function modalDesalocar(id) {
    const today = new Date().toISOString().split('T')[0];
    openModal('Desalocar Funcionário', `
        <form method="POST" action="actions.php" style="display:flex;flex-direction:column;gap:16px">
            <input type="hidden" name="action" value="desalocar">
            <input type="hidden" name="id" value="${id}">
            <div class="form-group">
                <label class="form-label">Data de Desalocação</label>
                <input class="form-control" type="date" name="data" value="${today}" required>
            </div>
            <div class="form-actions">
                <button class="btn btn-warning" type="submit">Confirmar Desalocação</button>
                <button class="btn btn-ghost" type="button" onclick="closeModal()">Cancelar</button>
            </div>
        </form>
    `);
}

// Date modal for desligamento
function modalDesligar(id) {
    const today = new Date().toISOString().split('T')[0];
    openModal('Desligar Funcionário', `
        <form method="POST" action="actions.php" style="display:flex;flex-direction:column;gap:16px">
            <input type="hidden" name="action" value="desligar">
            <input type="hidden" name="id" value="${id}">
            <div class="form-group">
                <label class="form-label">Data de Desligamento</label>
                <input class="form-control" type="date" name="data" value="${today}" required>
            </div>
            <div class="form-group">
                <label class="form-label">Motivo</label>
                <textarea class="form-control" name="motivo" rows="3" placeholder="Descreva o motivo..."></textarea>
            </div>
            <div class="form-actions">
                <button class="btn btn-danger" type="submit">Confirmar Desligamento</button>
                <button class="btn btn-ghost" type="button" onclick="closeModal()">Cancelar</button>
            </div>
        </form>
    `);
}

// Confirm delete
function confirmDelete(url, msg) {
    openModal('Confirmar Exclusão', `
        <div style="display:flex;flex-direction:column;gap:20px">
            <p style="color:var(--text2)">${msg || 'Tem certeza que deseja excluir este item?'}</p>
            <div class="form-actions">
                <a href="${url}" class="btn btn-danger">Sim, excluir</a>
                <button class="btn btn-ghost" type="button" onclick="closeModal()">Cancelar</button>
            </div>
        </div>
    `);
}

// Filter table rows
function filterTable(inputId, tableId) {
    const val = document.getElementById(inputId).value.toLowerCase();
    const rows = document.querySelectorAll(`#${tableId} tbody tr:not(.team-row-header)`);
    rows.forEach(r => {
        let content = r.textContent.toLowerCase();
        // Inclui títulos para busca de forma segura
        const itemsWithTitle = r.querySelectorAll('[title]');
        itemsWithTitle.forEach(el => {
            const t = el.getAttribute('title');
            if (t) content += ' ' + t.toLowerCase();
        });
        
        r.style.display = content.includes(val) ? '' : 'none';
    });
}
