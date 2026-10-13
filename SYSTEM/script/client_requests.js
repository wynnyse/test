document.addEventListener('DOMContentLoaded', () => {
    const requestModal = document.getElementById('request-modal');
    const closeRequestModalBtn = document.getElementById('close-request-modal');
    const cancelRequestBtn = document.getElementById('cancel-request-btn');

    const searchInput = document.getElementById('request-search');
    const filterSelect = document.getElementById('status-filter');
    const requestTableBody = document.querySelector('.request-table tbody');

    // Modal elements
    const modalRequestId = document.getElementById('modal-request-id');
    const modalRequestTitle = document.getElementById('modal-request-title');
    const modalClientName = document.getElementById('modal-client-name');
    const modalClientEmail = document.getElementById('modal-client-email');
    const modalServiceName = document.getElementById('modal-service-name');
    const modalCommissionType = document.getElementById('modal-commission-type');
    const modalBudget = document.getElementById('modal-budget');
    const modalDeadline = document.getElementById('modal-deadline');
    const modalDescription = document.getElementById('modal-description');
    const modalInputRequestId = document.getElementById('modal-input-request-id');
    const modalStatusSelect = document.getElementById('request-status-select');
    const modalAdminNotes = document.getElementById('admin-notes');

    function closeModal() {
        if (typeof requestModal.close === 'function') {
            requestModal.close();
        } else {
            requestModal.removeAttribute('open');
        }
    }

    if (closeRequestModalBtn) closeRequestModalBtn.addEventListener('click', closeModal);
    if (cancelRequestBtn) cancelRequestBtn.addEventListener('click', closeModal);

    // Backdrop click close
    if (requestModal) {
        requestModal.addEventListener('click', (e) => {
            const rect = requestModal.getBoundingClientRect();
            const isInDialog = (
                rect.top <= e.clientY && e.clientY <= rect.top + rect.height &&
                rect.left <= e.clientX && e.clientX <= rect.left + rect.width
            );
            if (!isInDialog) closeModal();
        });
    }

    // Handle View Details button click
    if (requestTableBody) {
        requestTableBody.addEventListener('click', (e) => {
            const viewBtn = e.target.closest('.view-request-btn');
            if (viewBtn) {
                const row = viewBtn.closest('.request-row');
                if (row) {
                    modalRequestId.textContent = `REQUEST #${row.dataset.id.padStart(3, '0')}`;
                    modalRequestTitle.textContent = row.dataset.title;
                    modalClientName.textContent = row.dataset.clientName;
                    modalClientEmail.textContent = row.dataset.clientEmail;
                    modalServiceName.textContent = row.dataset.serviceName;
                    modalCommissionType.textContent = row.dataset.commissionType;
                    modalBudget.textContent = `₱${parseFloat(row.dataset.budget).toFixed(2)}`;
                    modalDeadline.textContent = row.dataset.deadline;
                    modalDescription.textContent = row.dataset.description || 'No description provided.';
                    modalInputRequestId.value = row.dataset.id;
                    modalStatusSelect.value = row.dataset.status;
                    modalAdminNotes.value = row.dataset.adminNotes || '';

                    if (typeof requestModal.showModal === 'function') {
                        requestModal.showModal();
                    } else {
                        requestModal.setAttribute('open', 'true');
                    }
                }
            }
        });
    }

    // Dynamic Filter & Search
    function filterRequests() {
        const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
        const filterVal = filterSelect ? filterSelect.value : 'all';
        const rows = document.querySelectorAll('.request-row');

        rows.forEach(row => {
            const title = (row.dataset.title || '').toLowerCase();
            const name = (row.dataset.clientName || '').toLowerCase();
            const email = (row.dataset.clientEmail || '').toLowerCase();
            const status = row.dataset.status;

            const matchesSearch = title.includes(query) || name.includes(query) || email.includes(query);
            const matchesFilter = (filterVal === 'all' || status === filterVal);

            row.style.display = (matchesSearch && matchesFilter) ? '' : 'none';
        });
    }

    if (searchInput) searchInput.addEventListener('input', filterRequests);
    if (filterSelect) filterSelect.addEventListener('change', filterRequests);
});