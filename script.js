/**
 * Borrowed Items Management System
 * REST API with separated endpoints, pagination, phone number support.
 */

// ─── Config ───────────────────────────────────────────────────────────────────

const API = {
    get:     '/borrow_system/api/get.php',
    getOne:  '/borrow_system/api/get_one.php',
    create:  '/borrow_system/api/create.php',
    update:  '/borrow_system/api/update.php',
    delete:  '/borrow_system/api/delete.php',
    filters: '/borrow_system/api/filters.php',
};

// ─── State ────────────────────────────────────────────────────────────────────

let currentPage  = 1;
let currentLimit = 20;
let isSubmitting = false;
let editModal;

// ─── Init ─────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', function () {
    editModal = new bootstrap.Modal(document.getElementById('editModal'));

    // Default borrow date = today
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('borrowDate').value = today;
    document.getElementById('borrowDate').max   = today;

    // Character counters
    setupCharCounter('itemName',     'itemNameError',     50);
    setupCharCounter('borrowerName', 'borrowerNameError', 50);
    setupCharCounter('editBorrowerName', null,            50);

    // Event listeners
    document.getElementById('borrowForm').addEventListener('submit', handleAddRecord);
    document.getElementById('filterAll').addEventListener('change',         () => resetAndLoad());
    document.getElementById('filterReturned').addEventListener('change',    () => resetAndLoad());
    document.getElementById('filterNotReturned').addEventListener('change', () => resetAndLoad());
    document.getElementById('searchBox').addEventListener('input',          debounce(() => resetAndLoad(), 350));
    document.getElementById('editSubmitBtn').addEventListener('click',      handleEditSubmit);
    document.getElementById('limitSelect').addEventListener('change', function () {
        currentLimit = parseInt(this.value);
        currentPage  = 1;
        loadRecords();
    });

    document.getElementById('borrowDate').addEventListener('change',         validateDates);
    document.getElementById('expectedReturnDate').addEventListener('change', validateDates);

    loadRecords();
});

// ─── Load Records ─────────────────────────────────────────────────────────────

function resetAndLoad() {
    currentPage = 1;
    loadRecords();
}

function loadRecords() {
    const filter = document.querySelector('input[name="filterStatus"]:checked').value;
    const search = document.getElementById('searchBox').value.trim();
    const tbody  = document.getElementById('tableBody');

    tbody.innerHTML = `
        <tr>
          <td colspan="8" class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
          </td>
        </tr>`;

    const params = new URLSearchParams({
        filter,
        search,
        page:  currentPage,
        limit: currentLimit,
    });

    fetch(`${API.get}?${params}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                renderTable(data.data);
                updateStats(data.data);
                renderPagination(data.meta);
            } else {
                tbody.innerHTML = `<tr><td colspan="8" class="text-center py-3 text-danger">${escapeHtml(data.message)}</td></tr>`;
                showAlert(data.message, 'danger');
            }
        })
        .catch(() => {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center py-3 text-danger">Connection error. Please check your server.</td></tr>`;
            showAlert('Failed to load records. Check connection.', 'danger');
        });
}

// ─── Render Table ─────────────────────────────────────────────────────────────

function renderTable(records) {
    const tbody = document.getElementById('tableBody');

    if (!records || records.length === 0) {
        tbody.innerHTML = `
            <tr>
              <td colspan="8" class="text-center py-5">
                <div class="empty-state">
                  <i class="fas fa-inbox"></i>
                  <p class="mt-3">No records found</p>
                </div>
              </td>
            </tr>`;
        return;
    }

    tbody.innerHTML = records.map(record => {
        const statusClass = getStatusClass(record.display_status);
        const isReturned  = record.display_status === 'Returned';
        const phone       = record.phone_number
            ? `<a href="tel:${escapeHtml(record.phone_number)}" class="text-decoration-none">
                   <i class="fas fa-phone-alt me-1 text-success"></i>${escapeHtml(record.phone_number)}
               </a>`
            : `<span class="text-muted">—</span>`;

        return `
            <tr>
                <td><strong>#${record.id}</strong></td>
                <td>${escapeHtml(record.item_name)}</td>
                <td>${escapeHtml(record.borrower_name)}</td>
                <td>${phone}</td>
                <td>${formatDate(record.borrow_date)}</td>
                <td>${formatDate(record.expected_return_date)}</td>
                <td>
                    <span class="status-badge ${statusClass}">
                        <i class="fas ${getStatusIcon(record.display_status)}"></i>
                        ${record.display_status}
                    </span>
                </td>
                <td>
                    ${isReturned
                        ? `<button class="btn btn-secondary action-btn btn-sm" disabled>
                               <i class="fas fa-check"></i> Returned
                           </button>`
                        : `<button class="btn btn-success action-btn btn-sm"
                                   onclick="handleMarkReturned(${record.id})">
                               <i class="fas fa-check"></i> Return
                           </button>`
                    }
                    <button class="btn btn-warning action-btn btn-sm"
                            onclick="handleEditOpen(${record.id}, '${escapeAttr(record.borrower_name)}', '${escapeAttr(record.phone_number || '')}', '${record.expected_return_date}', '${record.status}')">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <button class="btn btn-danger action-btn btn-sm"
                            onclick="handleDelete(${record.id})">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </td>
            </tr>`;
    }).join('');
}

// ─── Pagination ───────────────────────────────────────────────────────────────

function renderPagination(meta) {
    const footer   = document.getElementById('paginationFooter');
    const controls = document.getElementById('paginationControls');
    const info     = document.getElementById('paginationInfo');

    if (!meta || meta.total_records === 0) {
        footer.style.display = 'none';
        return;
    }

    footer.style.display = '';
    footer.classList.remove('d-none');

    const start = (meta.current_page - 1) * meta.limit + 1;
    const end   = Math.min(meta.current_page * meta.limit, meta.total_records);
    info.textContent = `Showing ${start}–${end} of ${meta.total_records} records`;

    const total   = meta.total_pages;
    const current = meta.current_page;
    let pages = [];

    if (total <= 7) {
        pages = Array.from({ length: total }, (_, i) => i + 1);
    } else {
        pages = [1];
        if (current > 3) pages.push('...');
        for (let p = Math.max(2, current - 1); p <= Math.min(total - 1, current + 1); p++) {
            pages.push(p);
        }
        if (current < total - 2) pages.push('...');
        pages.push(total);
    }

    controls.innerHTML = `
        <li class="page-item ${current === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="goToPage(${current - 1})">«</a>
        </li>
        ${pages.map(p => p === '...'
            ? `<li class="page-item disabled"><span class="page-link">…</span></li>`
            : `<li class="page-item ${p === current ? 'active' : ''}">
                   <a class="page-link" href="#" onclick="goToPage(${p})">${p}</a>
               </li>`
        ).join('')}
        <li class="page-item ${current === total ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="goToPage(${current + 1})">»</a>
        </li>`;
}

function goToPage(page) {
    event.preventDefault();
    if (page < 1) return;
    currentPage = page;
    loadRecords();
}

// ─── Add Record ───────────────────────────────────────────────────────────────

function handleAddRecord(e) {
    e.preventDefault();
    clearFormErrors();

    if (isSubmitting) return;

    const itemName           = document.getElementById('itemName').value.trim();
    const borrowerName       = document.getElementById('borrowerName').value.trim();
    const phoneNumber        = document.getElementById('phoneNumber').value.trim();
    const borrowDate         = document.getElementById('borrowDate').value;
    const expectedReturnDate = document.getElementById('expectedReturnDate').value;

    if (!validateForm(itemName, borrowerName, phoneNumber, borrowDate, expectedReturnDate)) return;

    const submitBtn = document.querySelector('#borrowForm button[type="submit"]');
    isSubmitting        = true;
    submitBtn.disabled  = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Adding...';

    fetch(API.create, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({
            item_name:            itemName,
            borrower_name:        borrowerName,
            phone_number:         phoneNumber,
            borrow_date:          borrowDate,
            expected_return_date: expectedReturnDate,
        }),
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showAlert('✅ Record added successfully!', 'success');
            document.getElementById('borrowForm').reset();
            document.getElementById('borrowDate').value = new Date().toISOString().split('T')[0];
            resetAndLoad();
        } else {
            if (data.data && data.data.errors) {
                const errs = data.data.errors;
                if (errs.item_name)            showFieldError('itemNameError',           errs.item_name);
                if (errs.borrower_name)        showFieldError('borrowerNameError',       errs.borrower_name);
                if (errs.phone_number)         showFieldError('phoneNumberError',        errs.phone_number);
                if (errs.borrow_date)          showFieldError('borrowDateError',         errs.borrow_date);
                if (errs.expected_return_date) showFieldError('expectedReturnDateError', errs.expected_return_date);
            } else {
                showAlert(data.message, 'danger');
            }
        }
    })
    .catch(() => showAlert('Failed to add record. Check connection.', 'danger'))
    .finally(() => {
        isSubmitting        = false;
        submitBtn.disabled  = false;
        submitBtn.innerHTML = '<i class="fas fa-plus"></i> Add Record';
    });
}

// ─── Mark Returned ────────────────────────────────────────────────────────────

function handleMarkReturned(id) {
    if (!confirm('Mark this item as returned?')) return;

    fetch(API.update, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ id, action: 'mark_returned' }),
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showAlert('✅ Item marked as returned!', 'success');
            loadRecords();
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(() => showAlert('Failed to update record.', 'danger'));
}

// ─── Edit Record ──────────────────────────────────────────────────────────────

function handleEditOpen(id, borrowerName, phoneNumber, expectedReturnDate, status) {
    document.getElementById('editId').value                 = id;
    document.getElementById('editBorrowerName').value       = borrowerName;
    document.getElementById('editPhoneNumber').value        = phoneNumber;
    document.getElementById('editExpectedReturnDate').value = expectedReturnDate;
    document.getElementById('editStatus').value             = status;
    editModal.show();
}

function handleEditSubmit() {
    const id                 = document.getElementById('editId').value;
    const borrowerName       = document.getElementById('editBorrowerName').value.trim();
    const phoneNumber        = document.getElementById('editPhoneNumber').value.trim();
    const expectedReturnDate = document.getElementById('editExpectedReturnDate').value;
    const status             = document.getElementById('editStatus').value;

    // Clear previous edit errors
    const editPhoneError = document.getElementById('editPhoneError');
    editPhoneError.classList.add('d-none');

    if (!borrowerName || !expectedReturnDate || !status) {
        showAlert('All required fields must be filled.', 'warning');
        return;
    }

    if (borrowerName.length > 50) {
        showAlert('Borrower name must not exceed 50 characters.', 'warning');
        return;
    }

    if (phoneNumber && !/^[0-9+\-\s()]+$/.test(phoneNumber)) {
        editPhoneError.textContent = 'Phone number contains invalid characters.';
        editPhoneError.classList.remove('d-none');
        return;
    }

    fetch(API.update, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({
            id,
            action:               'edit',
            borrower_name:        borrowerName,
            phone_number:         phoneNumber,
            expected_return_date: expectedReturnDate,
            status,
        }),
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showAlert('✅ Record updated successfully!', 'success');
            editModal.hide();
            loadRecords();
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(() => showAlert('Failed to update record.', 'danger'));
}

// ─── Delete Record ────────────────────────────────────────────────────────────

function handleDelete(id) {
    if (!confirm('Delete this record? This cannot be undone.')) return;

    fetch(API.delete, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ id }),
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showAlert('✅ Record deleted successfully!', 'success');
            if (currentPage > 1) currentPage--;
            loadRecords();
        } else {
            showAlert(data.message, 'danger');
        }
    })
    .catch(() => showAlert('Failed to delete record.', 'danger'));
}

// ─── Stats ────────────────────────────────────────────────────────────────────

function updateStats(records) {
    if (!records) return;
    let borrowed = 0, overdue = 0, returned = 0;
    records.forEach(r => {
        if (r.display_status === 'Returned')     returned++;
        else if (r.display_status === 'Overdue') overdue++;
        else                                     borrowed++;
    });
    document.getElementById('totalBorrowed').textContent = borrowed;
    document.getElementById('totalOverdue').textContent  = overdue;
    document.getElementById('totalReturned').textContent = returned;
}

// ─── Validation ───────────────────────────────────────────────────────────────

function validateForm(itemName, borrowerName, phoneNumber, borrowDate, expectedReturnDate) {
    let valid = true;

    if (!itemName) {
        showFieldError('itemNameError', 'Item name is required.'); valid = false;
    } else if (itemName.length > 50) {
        showFieldError('itemNameError', 'Item name must not exceed 50 characters.'); valid = false;
    }

    if (!borrowerName) {
        showFieldError('borrowerNameError', 'Borrower name is required.'); valid = false;
    } else if (borrowerName.length > 50) {
        showFieldError('borrowerNameError', 'Borrower name must not exceed 50 characters.'); valid = false;
    }

    if (phoneNumber && !/^[0-9+\-\s()]+$/.test(phoneNumber)) {
        showFieldError('phoneNumberError', 'Phone number contains invalid characters.'); valid = false;
    }

    if (!borrowDate)         { showFieldError('borrowDateError',         'Borrow date is required.');          valid = false; }
    if (!expectedReturnDate) { showFieldError('expectedReturnDateError', 'Expected return date is required.'); valid = false; }

    if (valid && new Date(expectedReturnDate) <= new Date(borrowDate)) {
        showFieldError('expectedReturnDateError', 'Return date must be after the borrow date.');
        valid = false;
    }

    return valid;
}

function validateDates() {
    const borrowDate         = document.getElementById('borrowDate').value;
    const expectedReturnDate = document.getElementById('expectedReturnDate').value;
    if (borrowDate && expectedReturnDate) {
        if (new Date(expectedReturnDate) <= new Date(borrowDate)) {
            showFieldError('expectedReturnDateError', 'Return date must be after the borrow date.');
        } else {
            clearFieldError('expectedReturnDateError');
        }
    }
}

function showFieldError(id, msg) {
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = msg;
    el.classList.remove('d-none');
}

function clearFieldError(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('d-none');
}

function clearFormErrors() {
    document.querySelectorAll('[id$="Error"]').forEach(el => el.classList.add('d-none'));
}

// ─── Character Counter ────────────────────────────────────────────────────────

function setupCharCounter(inputId, errorId, maxLen) {
    const input = document.getElementById(inputId);
    if (!input) return;

    const counter = document.createElement('small');
    counter.className = 'text-muted d-block';
    counter.style.fontSize = '0.78rem';
    input.insertAdjacentElement('afterend', counter);

    const update = () => {
        const len = input.value.length;
        counter.textContent = `${len} / ${maxLen}`;
        counter.classList.toggle('text-danger', len > maxLen);
        counter.classList.toggle('text-muted',  len <= maxLen);
        if (errorId && len > maxLen) {
            showFieldError(errorId, `Must not exceed ${maxLen} characters.`);
        } else if (errorId) {
            clearFieldError(errorId);
        }
    };

    input.addEventListener('input', update);
    update();
}

// ─── Utilities ────────────────────────────────────────────────────────────────

function showAlert(message, type = 'info') {
    const container = document.getElementById('alertContainer');
    const id        = 'alert-' + Date.now();

    container.insertAdjacentHTML('beforeend', `
        <div id="${id}" class="alert alert-${type} alert-dismissible fade show" role="alert"
             style="position:fixed;top:20px;right:20px;z-index:9999;min-width:300px;max-width:400px;">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>`);

    setTimeout(() => {
        const el = document.getElementById(id);
        if (el) { el.classList.add('fade-out'); setTimeout(() => el.remove(), 300); }
    }, 5000);
}

function formatDate(dateStr) {
    return new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', {
        year: 'numeric', month: 'short', day: 'numeric'
    });
}

function escapeHtml(text) {
    const map = { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' };
    return String(text || '').replace(/[&<>"']/g, m => map[m]);
}

function escapeAttr(text) {
    return String(text || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}

function getStatusIcon(status) {
    return status === 'Returned' ? 'fa-check-circle'
         : status === 'Overdue'  ? 'fa-exclamation-circle'
         :                         'fa-clock';
}

function getStatusClass(status) {
    return status === 'Returned' ? 'status-returned'
         : status === 'Overdue'  ? 'status-overdue'
         :                         'status-borrowed';
}

function debounce(fn, delay) {
    let timer;
    return function (...args) {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), delay);
    };
}
