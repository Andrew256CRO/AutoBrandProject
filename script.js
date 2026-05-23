"use strict";

let currentPage = 1;
const limit = 5;
let currentSearchTerm = '';
let currentSortBy = 'Name';
let currentSortDir = 'ASC';

async function loadProducts(page) {
    if (page === undefined || page < 1) {
        page = 1;
    }

    const loadingIndicator = document.getElementById("loadingIndicator");
    const tableContainer = document.getElementById("tableContainer");

    try {
        loadingIndicator.style.display = 'block';
        tableContainer.innerHTML = '';

        let url = 'productsController.php?action=read&page=' + page
            + '&sortBy=' + currentSortBy
            + '&sortDir=' + currentSortDir;

        if (currentSearchTerm) {
            url = 'productsController.php?action=search&search=' + encodeURIComponent(currentSearchTerm)
                + '&page=' + page
                + '&sortBy=' + currentSortBy
                + '&sortDir=' + currentSortDir;
        }

        const response = await fetch(url);
        const data = await response.json();

        if (data.success) {
            currentPage = data.currentPage;
            currentSortBy = data.sortBy;
            currentSortDir = data.sortDir;
            tableContainer.innerHTML = data.tableHtml;
            document.getElementById('paginationContainer').innerHTML = data.paginationHtml;
        } else {
            tableContainer.innerHTML = '<p class="error">Error loading products</p>';
        }
    } catch (error) {
        console.error('Error:', error);
        tableContainer.innerHTML = '<p class="error">Error loading products</p>';
    } finally {
        loadingIndicator.style.display = 'none';
    }
}

function sortBy(column, direction) {
    currentSortBy = column;
    currentSortDir = direction;
    currentPage = 1;
    loadProducts(1);
}

function handleSearch() {
    currentSearchTerm = document.getElementById('searchInput').value.trim();
    currentPage = 1;
    loadProducts(1);
}

let searchTimeout;
function debounceSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(function () {
        handleSearch();
    }, 500);
}

function openAddModal() {
    const modal = document.getElementById('productModal');
    const modalTitle = document.getElementById('modalTitle');
    const form = document.getElementById('productForm');

    modalTitle.textContent = 'Add Product';

    fetch('productsController.php?action=getForm')
        .then(function (response) { return response.json(); })
        .then(function (data) {
            if (data.success) {
                form.innerHTML = data.formHtml;
                form.onsubmit = handleCreateProduct;
                modal.style.display = 'block';
            } else {
                alert('Error loading form');
            }
        })
        .catch(function (error) {
            console.error('Error:', error);
            alert('Error loading form');
        });
}

function openEditModal(productId) {
    const modal = document.getElementById('productModal');
    const modalTitle = document.getElementById('modalTitle');
    const form = document.getElementById('productForm');

    modalTitle.textContent = 'Edit Product';

    fetch('productsController.php?action=getOne&id=' + productId)
        .then(function (response) { return response.json(); })
        .then(function (data) {
            if (data.success) {
                form.innerHTML = data.formHtml;
                form.onsubmit = function (e) { handleUpdateProduct(e, productId); };
                modal.style.display = 'block';
            } else {
                alert('Error loading product data');
            }
        })
        .catch(function (error) {
            console.error('Error:', error);
            alert('Error loading product data');
        });
}

function closeModal() {
    document.getElementById('productModal').style.display = 'none';
    clearFormErrors();
}

function handleCreateProduct(e) {
    e.preventDefault();
    clearFormErrors();

    const formData = {
        name: document.getElementById('productName').value.trim(),
        description: document.getElementById('productDescription').value.trim(),
        price: document.getElementById('productPrice').value,
        currency: document.getElementById('productCurrency').value.trim(),
        image: document.getElementById('productImage').value.trim(),
        price_ron: document.getElementById('productPriceRON').value,
        exchange_rate: document.getElementById('productExchangeRate').value
    };

    fetch('productsController.php?action=create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData)
    })
        .then(function (response) { return response.json(); })
        .then(function (data) {
            if (data.success) {
                closeModal();
                loadProducts(currentPage);
                showMessage('Product created successfully!', 'success');
            } else {
                if (data.errors) {
                    displayFormErrors(data.errors);
                } else {
                    showMessage(data.message || 'Error creating product', 'error');
                }
            }
        })
        .catch(function (error) {
            console.error('Error:', error);
            showMessage('Error creating product', 'error');
        });
}

function handleUpdateProduct(e, productId) {
    e.preventDefault();
    clearFormErrors();

    const formData = {
        id: productId,
        name: document.getElementById('productName').value.trim(),
        description: document.getElementById('productDescription').value.trim(),
        price: document.getElementById('productPrice').value,
        currency: document.getElementById('productCurrency').value.trim(),
        image: document.getElementById('productImage').value.trim(),
        price_ron: document.getElementById('productPriceRON').value,
        exchange_rate: document.getElementById('productExchangeRate').value
    };

    fetch('productsController.php?action=update', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData)
    })
        .then(function (response) { return response.json(); })
        .then(function (data) {
            if (data.success) {
                closeModal();
                loadProducts(currentPage);
                showMessage('Product updated successfully!', 'success');
            } else {
                if (data.errors) {
                    displayFormErrors(data.errors);
                } else {
                    showMessage(data.message || 'Error updating product', 'error');
                }
            }
        })
        .catch(function (error) {
            console.error('Error:', error);
            showMessage('Error updating product', 'error');
        });
}

function confirmDelete(productId) {
    if (confirm('Are you sure you want to delete this product?')) {
        deleteProduct(productId);
    }
}

function deleteProduct(productId) {
    fetch('productsController.php?action=delete&id=' + productId)
        .then(function (response) { return response.json(); })
        .then(function (data) {
            if (data.success) {
                loadProducts(currentPage);
                showMessage('Product deleted successfully!', 'success');
            } else {
                showMessage(data.message || 'Error deleting product', 'error');
            }
        })
        .catch(function (error) {
            console.error('Error:', error);
            showMessage('Error deleting product', 'error');
        });
}

function displayFormErrors(errors) {
    for (let field in errors) {
        const errorElement = document.getElementById('error-' + field);
        if (errorElement) {
            errorElement.textContent = errors[field];
        }
    }
}

function clearFormErrors() {
    const errorElements = document.querySelectorAll('.error-message');
    for (let i = 0; i < errorElements.length; i++) {
        errorElements[i].textContent = '';
    }
}

function showMessage(message, type) {
    const messageDiv = document.createElement('div');
    messageDiv.className = 'toast-message ' + type;
    messageDiv.textContent = message;
    document.body.appendChild(messageDiv);

    setTimeout(function () {
        messageDiv.classList.add('show');
    }, 100);

    setTimeout(function () {
        messageDiv.classList.remove('show');
        setTimeout(function () {
            document.body.removeChild(messageDiv);
        }, 300);
    }, 3000);
}

document.addEventListener('DOMContentLoaded', function () {
    loadProducts(1);

    document.getElementById('addProductBtn').addEventListener('click', openAddModal);
    document.getElementById('searchInput').addEventListener('input', debounceSearch);
    document.querySelector('.close').addEventListener('click', closeModal);

    window.addEventListener('click', function (event) {
        const modal = document.getElementById('productModal');
        if (event.target === modal) {
            closeModal();
        }
    });
});