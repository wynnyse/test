document.addEventListener('DOMContentLoaded', () => {
    // DOM Elements Selection
    const serviceModal = document.getElementById('service-modal');
    const serviceForm = document.getElementById('service-form');
    const deleteForm = document.getElementById('delete-service-form');
    const deleteIdInput = document.getElementById('delete-service-id');

    // Service Form Buttons
    const addServiceBtn = document.getElementById('add-service-button');
    const firstServiceBtn = document.getElementById('first-service-button');
    const closeServiceModalBtn = document.getElementById('close-service-modal');
    const cancelServiceBtn = document.getElementById('cancel-service-button');

    // Service Form Inputs
    const modalTitle = document.getElementById('service-modal-title');
    const serviceActionInput = document.getElementById('service-action');
    const serviceIdInput = document.getElementById('service-id');
    const serviceNameInput = document.getElementById('service-name');
    const serviceDescInput = document.getElementById('service-description');
    const servicePriceInput = document.getElementById('service-price');
    const serviceDaysInput = document.getElementById('service-duration');
    const serviceActiveCheckbox = document.getElementById('service-active');

    // Search & Filter Inputs
    const searchInput = document.getElementById('service-search');
    const filterSelect = document.getElementById('service-filter');
    const serviceGrid = document.getElementById('service-grid');

    // Reset Form
    function resetForm() {
        serviceForm.reset();
        modalTitle.textContent = 'Add Listing';
        serviceActionInput.value = 'add';
        serviceIdInput.value = '';
        serviceActiveCheckbox.checked = true;
    }

    // Open Modal
    function openAddModal() {
        resetForm();
        if (typeof serviceModal.showModal === 'function') {
            serviceModal.showModal();
        } else {
            serviceModal.setAttribute('open', 'true');
        }
    }

    // Close Modal
    function closeModal() {
        if (typeof serviceModal.close === 'function') {
            serviceModal.close();
        } else {
            serviceModal.removeAttribute('open');
        }
        resetForm();
    }

    if (addServiceBtn) addServiceBtn.addEventListener('click', openAddModal);
    if (firstServiceBtn) firstServiceBtn.addEventListener('click', openAddModal);
    if (closeServiceModalBtn) closeServiceModalBtn.addEventListener('click', closeModal);
    if (cancelServiceBtn) cancelServiceBtn.addEventListener('click', closeModal);

    // Close on backdrop click
    if (serviceModal) {
        serviceModal.addEventListener('click', (e) => {
            const rect = serviceModal.getBoundingClientRect();
            const isInDialog = (
                rect.top <= e.clientY && e.clientY <= rect.top + rect.height &&
                rect.left <= e.clientX && e.clientX <= rect.left + rect.width
            );
            if (!isInDialog) closeModal();
        });
    }

    // Card Actions Handler (Edit / Preview / Delete)
    if (serviceGrid) {
        serviceGrid.addEventListener('click', (event) => {
            const editBtn = event.target.closest('.edit-service-button');
            const previewBtn = event.target.closest('.preview-service-button');
            const deleteBtn = event.target.closest('.delete-service-button');

            if (editBtn) {
                const card = editBtn.closest('.service-card');
                if (card) populateAndOpenEditModal(card);
            }

            if (previewBtn) {
                const serviceId = previewBtn.dataset.id;
                window.open(`../service_detail.php?id=${serviceId}`, '_blank');
            }

            if (deleteBtn) {
                const serviceId = deleteBtn.dataset.id;
                const serviceName = deleteBtn.dataset.name || 'this service';

                // Browser Confirmation Dialog
                const confirmed = confirm(`Are you sure you want to delete "${serviceName}"? This action cannot be undone.`);

                if (confirmed) {
                    deleteIdInput.value = serviceId;
                    deleteForm.submit();
                }
            }
        });
    }

    // Populate Modal with Data for Editing
    function populateAndOpenEditModal(card) {
        resetForm();

        modalTitle.textContent = 'Edit Listing';
        serviceActionInput.value = 'edit';

        serviceIdInput.value = card.dataset.id;
        serviceNameInput.value = card.dataset.name || '';
        serviceDescInput.value = card.dataset.description || '';
        servicePriceInput.value = card.dataset.price || '';
        serviceDaysInput.value = card.dataset.days || '';
        serviceActiveCheckbox.checked = card.dataset.status === 'Available';

        if (typeof serviceModal.showModal === 'function') {
            serviceModal.showModal();
        } else {
            serviceModal.setAttribute('open', 'true');
        }
    }

    // Dynamic Filter & Search
    function filterServices() {
        const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
        const filterVal = filterSelect ? filterSelect.value : 'all';
        const cards = document.querySelectorAll('.service-card');

        cards.forEach(card => {
            const name = (card.dataset.name || '').toLowerCase();
            const status = card.dataset.status;

            const matchesSearch = name.includes(query);
            let matchesFilter = true;

            if (filterVal === 'active') {
                matchesFilter = (status === 'Available');
            } else if (filterVal === 'hidden') {
                matchesFilter = (status === 'Unavailable');
            }

            card.style.display = (matchesSearch && matchesFilter) ? '' : 'none';
        });
    }

    if (searchInput) searchInput.addEventListener('input', filterServices);
    if (filterSelect) filterSelect.addEventListener('change', filterServices);
});