document.addEventListener("DOMContentLoaded", function () {

    // ========================================
    // 1. SWITCH BETWEEN TABS
    // ========================================

    const tabButtons = document.querySelectorAll("[data-tab]");
    const tabPanels = document.querySelectorAll("[data-panel]");

    function showTab(tabName) {
        tabButtons.forEach(function (button) {
            const active = button.dataset.tab === tabName;

            button.classList.toggle("active", active);
            button.setAttribute("aria-selected", String(active));
            button.tabIndex = active ? 0 : -1;
        });

        tabPanels.forEach(function (panel) {
            panel.hidden = panel.dataset.panel !== tabName;
        });
    }

    tabButtons.forEach(function (button, index) {
        button.addEventListener("click", function () {
            showTab(button.dataset.tab);
        });

        button.addEventListener("keydown", function (event) {
            let nextIndex = index;

            if (event.key === "ArrowRight") {
                nextIndex = (index + 1) % tabButtons.length;
            } else if (event.key === "ArrowLeft") {
                nextIndex =
                    (index - 1 + tabButtons.length) % tabButtons.length;
            } else {
                return;
            }

            event.preventDefault();

            tabButtons[nextIndex].focus();
            showTab(tabButtons[nextIndex].dataset.tab);
        });
    });

    // Return to the portfolio tab after saving an artwork.
    const requestedTab = new URLSearchParams(window.location.search)
        .get("tab");

    showTab(requestedTab === "portfolio" ? "portfolio" : "services");


    // ========================================
    // 2. ADD AND EDIT ARTWORK POPUP
    // ========================================

    const artworkModal = document.getElementById("artwork-modal");
    const artworkForm = document.getElementById("artwork-form");

    const addArtworkButton = document.getElementById("add-artwork-button");
    const firstArtworkButton = document.getElementById("first-artwork-button");

    const artworkModalTitle = document.getElementById("artwork-modal-title");
    const artworkAction = document.getElementById("artwork-action");
    const artworkId = document.getElementById("artwork-id");

    const titleInput = document.getElementById("artwork-title");
    const categoryInput = document.getElementById("artwork-category");
    const descriptionInput = document.getElementById("artwork-description");
    const imageInput = document.getElementById("artwork-image");
    const visibleInput = document.getElementById("artwork-visible");

    const imagePreview = document.getElementById("artwork-image-preview");
    const previewImage = document.getElementById("artwork-preview-image");
    const saveButton = document.getElementById("save-artwork-button");

    let currentImageUrl = "";

    function openArtworkModal() {
        artworkModal.showModal();
        titleInput.focus();
    }

    function resetArtworkForm() {
        artworkForm.reset();

        artworkAction.value = "add_artwork";
        artworkId.value = "";

        artworkModalTitle.textContent = "Add Artwork";
        saveButton.textContent = "Save Artwork";

        visibleInput.checked = true;

        currentImageUrl = "";
        imageInput.required = true;

        imagePreview.hidden = true;
        previewImage.removeAttribute("src");
    }

    // Open a blank form for a new artwork.
    function addArtwork() {
        resetArtworkForm();
        openArtworkModal();
    }

    if (addArtworkButton) {
        addArtworkButton.addEventListener("click", addArtwork);
    }

    if (firstArtworkButton) {
        firstArtworkButton.addEventListener("click", addArtwork);
    }

    // Open the form with existing artwork details.
    document.querySelectorAll(".edit-artwork-button").forEach(function (button) {

        button.addEventListener("click", function () {

            resetArtworkForm();

            artworkAction.value = "edit_artwork";
            artworkId.value = button.dataset.id;

            artworkModalTitle.textContent = "Edit Artwork";
            saveButton.textContent = "Save Changes";

            titleInput.value = button.dataset.title || "";
            categoryInput.value = button.dataset.category || "";
            descriptionInput.value = button.dataset.description || "";

            visibleInput.checked = button.dataset.visible === "1";

            currentImageUrl = button.dataset.image || "";

            // Editing does not require a replacement image.
            imageInput.required = false;

            if (currentImageUrl) {
                previewImage.src = currentImageUrl;
                imagePreview.hidden = false;
            }

            openArtworkModal();
        });

    });


    // ========================================
    // 3. IMAGE PREVIEW
    // ========================================

    imageInput.addEventListener("change", function () {

        const file = imageInput.files[0];

        if (!file) {
            if (currentImageUrl) {
                previewImage.src = currentImageUrl;
                imagePreview.hidden = false;
            } else {
                imagePreview.hidden = true;
                previewImage.removeAttribute("src");
            }

            return;
        }

        const allowedTypes = [
            "image/jpeg",
            "image/png",
            "image/webp",
            "image/gif"
        ];

        if (!allowedTypes.includes(file.type)) {
            alert("Please select a JPG, PNG, WEBP, or GIF image.");
            imageInput.value = "";
            return;
        }

        if (file.size > 5 * 1024 * 1024) {
            alert("The image must be 5 MB or smaller.");
            imageInput.value = "";
            return;
        }

        const reader = new FileReader();

        reader.onload = function (event) {
            previewImage.src = event.target.result;
            imagePreview.hidden = false;
        };

        reader.readAsDataURL(file);
    });


    // ========================================
    // 4. CLOSE ARTWORK POPUP
    // ========================================

    artworkModal.querySelectorAll("[data-close-artwork]").forEach(
        function (button) {
            button.addEventListener("click", function () {
                artworkModal.close();
            });
        }
    );

    // Close when clicking the dialog backdrop.
    artworkModal.addEventListener("click", function (event) {
        if (event.target === artworkModal) {
            artworkModal.close();
        }
    });

    // Clear temporary form values after closing.
    artworkModal.addEventListener("close", function () {
        resetArtworkForm();
    });


    // ========================================
    // 5. SERVICE DETAILS POPUP
    // ========================================

    const serviceModal = document.getElementById("service-modal");

    if (serviceModal) {
        const modalTitle = document.getElementById("modal-service-title");
        const modalDescription = document.getElementById(
            "modal-service-description"
        );
        const modalImage = document.getElementById("modal-service-image");
        const imagePlaceholder = document.getElementById(
            "modal-image-placeholder"
        );
        const modalPrice = document.getElementById("modal-service-price");
        const modalDays = document.getElementById("modal-service-days");
        const modalStatus = document.getElementById("modal-service-status");
        const editServiceLink = document.getElementById("modal-edit-service");

        function openServiceModal(card) {
            const service = card.dataset;

            modalTitle.textContent = service.serviceTitle;
            modalDescription.textContent =
                service.serviceDescription || "No description provided.";

            modalPrice.textContent =
                "₱" + Number(service.servicePrice).toLocaleString("en-PH", {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });

            modalDays.textContent = service.serviceDays
                ? service.serviceDays +
                  (Number(service.serviceDays) === 1 ? " day" : " days")
                : "To be discussed";

            modalStatus.textContent = service.serviceStatus;

            // Lets the CSS color the badge (available / unavailable).
            modalStatus.dataset.status =
                (service.serviceStatus || "").toLowerCase();

            if (service.serviceImage) {
                modalImage.src = service.serviceImage;
                modalImage.alt = "Sample artwork for " + service.serviceTitle;
                modalImage.hidden = false;
                imagePlaceholder.hidden = true;
            } else {
                modalImage.hidden = true;
                imagePlaceholder.hidden = false;
            }

            editServiceLink.href =
                "services_settings.php?id=" +
                encodeURIComponent(service.serviceId);

            serviceModal.showModal();
        }

        document.querySelectorAll("[data-service-card]").forEach(
            function (card) {

                card.addEventListener("click", function (event) {
                    // Let the Edit Service link navigate normally.
                    if (event.target.closest("a")) {
                        return;
                    }

                    openServiceModal(card);
                });

                card.addEventListener("keydown", function (event) {
                    if (event.target !== card) {
                        return;
                    }

                    if (event.key === "Enter" || event.key === " ") {
                        event.preventDefault();
                        openServiceModal(card);
                    }
                });
            }
        );

        serviceModal.querySelectorAll("[data-close-service]").forEach(
            function (button) {
                button.addEventListener("click", function () {
                    serviceModal.close();
                });
            }
        );

        serviceModal.addEventListener("click", function (event) {
            if (event.target === serviceModal) {
                serviceModal.close();
            }
        });
    }


    // ========================================
    // 6. DELETE ARTWORK (browser confirm box)
    // ========================================

    document.querySelectorAll(".delete-artwork-button").forEach(function (button) {
        button.addEventListener("click", async function () {
            const sure = confirm(
                'Delete "' + button.dataset.title + '"?\n\n' +
                "Its image will be removed too. This can't be undone."
            );

            if (!sure) {
                return;
            }

            const formData = new FormData();
            formData.append(
                "csrf_token",
                artworkForm.querySelector('[name="csrf_token"]').value
            );
            formData.append("action", "delete_artwork");
            formData.append("artwork_id", button.dataset.id);

            button.disabled = true;

            try {
                const response = await fetch(
                    "my_services_portfolio.php?tab=portfolio",
                    {
                        method: "POST",
                        headers: {
                            "X-Requested-With": "XMLHttpRequest"
                        },
                        body: formData
                    }
                );

                const result = await response.json();

                if (!response.ok || !result.success) {
                    throw new Error(
                        result.message || "Unable to delete artwork."
                    );
                }

                // Remove the artwork card without reloading the page.
                const card = button.closest(".portfolio-card");

                if (card) {
                    card.remove();
                }

                // Display a success message.
                const notice = document.createElement("p");
                notice.className = "notice notice--success";
                notice.setAttribute("role", "status");
                notice.textContent = result.message;

                const main = document.querySelector(".admin-main");
                const existingNotice = main.querySelector(".notice");

                if (existingNotice) {
                    existingNotice.replaceWith(notice);
                } else {
                    main.insertBefore(
                        notice,
                        main.querySelector(".content-tabs")
                    );
                }

                // Show the empty state if no artworks remain.
                const portfolioGrid = document.querySelector(".portfolio-grid");

                if (portfolioGrid && portfolioGrid.children.length === 0) {
                    portfolioGrid.remove();

                    const emptyState = document.createElement("section");
                    emptyState.className = "empty-state";
                    emptyState.innerHTML =
                        "<h3>No artworks yet</h3>" +
                        "<p>Your completed artworks will appear here.</p>" +
                        '<button type="button" class="button button--primary" ' +
                        'id="first-artwork-button">Post Your First Artwork</button>';

                    document
                        .querySelector('[data-panel="portfolio"]')
                        .appendChild(emptyState);

                    document
                        .getElementById("first-artwork-button")
                        .addEventListener("click", addArtwork);
                }
            } catch (error) {
                alert(error.message || "Something went wrong.");
                button.disabled = false;
            }
        });
    });

});