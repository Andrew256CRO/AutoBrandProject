"use strict";
//previne utilizarea variabilelor nedeclarate si nu numai

//variabile globale de stare, pastrate intre apeluri de functii
let currentPage = 1;//pagina curenta
const limit = 5; //nr. maxim de produse per pagina, trebuie sa fie la fel ca in Controller
let currentSearchTerm = '';//termenul de cautat, inital gol, cum e si normal
let currentSortBy = 'Name';//coloana de sortare curenta
let currentSortDir = 'ASC';//directia de sortare curenta

// incarc produsele de la server si le injecteaz in DOM

//Async code allows a program to start a long-running task (like fetching data from a file). and continue with other tasks before the first one finishes.
//Async code prevents the application from freezing, which is critical for user experience.
//As per above by W3schools, ca motivatie de ce am folosit o functie async
async function loadProducts(page) {
    if (page === undefined || page < 1) {
        page = 1;
    }

    const loadingIndicator = document.getElementById("loadingIndicator");
    const tableContainer = document.getElementById("tableContainer");

    try {
        loadingIndicator.style.display = 'block';//afisare indicatorul de loading
        tableContainer.innerHTML = '';//golire tabel curent

        // construire URL cu toti parametrii necesari
        let url = 'productsController.php?action=read&page=' + page
            + '&sortBy=' + currentSortBy
            + '&sortDir=' + currentSortDir;

        //daca exista(am) termen de cautare, folosesc actiunea de search
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
            currentSortBy = data.sortBy;// actualizez starea de sortare din raspuns
            currentSortDir = data.sortDir;
            //injectare HTML pre-randat de PHP direct in DOM
            tableContainer.innerHTML = data.tableHtml;
            document.getElementById('paginationContainer').innerHTML = data.paginationHtml;
        } else {
            tableContainer.innerHTML = '<p class="error">Error loading products</p>';
        }
    } catch (error) {
        console.error('Error:', error);
        tableContainer.innerHTML = '<p class="error">Error loading products</p>';
    } finally {
        loadingIndicator.style.display = 'none';//ascund loading-ul indiferent de rezultat
    }
}

// setez coloana si directia de sortare si reincarc produsele de la pagina 1
// apelata din onclick-urile generate de renderSortableHeader in View
function sortBy(column, direction) {
    currentSortBy = column;
    currentSortDir = direction;
    currentPage = 1; //reset la pagina 1 la schimbarea sortarii
    loadProducts(1);
}

// preia termenul de cautare si da trigger la incarcarea produselor
function handleSearch() {
    currentSearchTerm = document.getElementById('searchInput').value.trim();
    currentPage = 1;// reset la pagina 1 la cautare noua
    loadProducts(1);
}

// Debounce -> asteapta 500ms dupa ultima tastatura inainte de a cauta
// Previne request-uri la fiecare tasta apasata
let searchTimeout;
function debounceSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(function () {
        handleSearch();
    }, 500);
}

//deschidere modal de adaugare cu formularul gol
function openAddModal() {
    const modal = document.getElementById('productModal');
    const modalTitle = document.getElementById('modalTitle');
    const form = document.getElementById('productForm');

    modalTitle.textContent = 'Add Product';

    // cer formularul gol de la server
    fetch('productsController.php?action=getForm')
        .then(function (response) { return response.json(); })
        .then(function (data) {
            if (data.success) {
                form.innerHTML = data.formHtml; // injectare HTML-ul formularului
                form.onsubmit = handleCreateProduct;// asociere handler de submit
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

// deschidere modalul de editare cu formularul pre-populat cu datele produsului
function openEditModal(productId) {
    const modal = document.getElementById('productModal');
    const modalTitle = document.getElementById('modalTitle');
    const form = document.getElementById('productForm');

    modalTitle.textContent = 'Edit Product';

    // cer datele produsului si formularul pre-populat
    fetch('productsController.php?action=getOne&id=' + productId)
        .then(function (response) { return response.json(); })
        .then(function (data) {
            if (data.success) {
                form.innerHTML = data.formHtml;//asociez handlerul de update cu ID-ul produsului capturat
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
    clearFormErrors();//sterg erorile de validare la inchidere
}

function closePdfModal() {
    document.getElementById('pdfModal').style.display = 'none';
}

// trimit datele formularului la server pentru creare produs nou
function handleCreateProduct(e) {
    e.preventDefault();//previn submit-ul default al formularului
    clearFormErrors();

    // colectare date din campurile formularului
    const formData = {
        name: document.getElementById('productName').value.trim(),
        description: document.getElementById('productDescription').value.trim(),
        price: document.getElementById('productPrice').value,
        currency: document.getElementById('productCurrency').value.trim(),
        image: document.getElementById('productImage').value.trim(),
        price_ron: document.getElementById('productPriceRON').value,
        exchange_rate: document.getElementById('productExchangeRate').value
    };

    // trimit de tip JSON in body-ul POST
    fetch('productsController.php?action=create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData)
    })
        .then(function (response) { return response.json(); })
        .then(function (data) {
            if (data.success) {
                closeModal();
                loadProducts(currentPage);//reincarc tabelul
                showMessage('Product created successfully!', 'success');
            } else {
                if (data.errors) {
                    displayFormErrors(data.errors);// Afisare erori de validare pe campuri
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

//trimitere date formular la server pentru actualizare produs existent
function handleUpdateProduct(e, productId) {
    e.preventDefault();
    clearFormErrors();

    const formData = {
        id: productId,//Aici includ tousi si ID-ul pentru a sti ce produs sa se actualizeze
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

//confirmare inainte de stergere dupa Id in combinatie cu functia de dedesubt
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

// se trimite fisierul PDF la server folosind FormData (multipart/form-data)
function handlePdfUpload(file) {
    if (!file) return;

    const formData = new FormData();
    formData.append('pdf', file);// adaugare fisier cu cheia 'pdf' (accesibila in $_FILES['pdf'])

    showMessage('Parsing PDF...', 'success');

    fetch('pdfController.php', {
        method: 'POST',
        body: formData // "formData" seteaza automat Content-Type multipart/form-data
    })
        .then(function (response) { return response.json(); })
        .then(function (data) {
            if (data.success) {
                showPdfResults(data.products, data.csv);
            } else {
                showMessage(data.message || 'Error parsing PDF', 'error');
            }
        })
        .catch(function (error) {
            console.error('Error:', error);
            showMessage('Error parsing PDF', 'error');
        });
}

// afisare date extrase din PDF intr-un modal cu tabel
function showPdfResults(products, csv) {
    const modal = document.getElementById('pdfModal');
    const results = document.getElementById('pdfResults');

    let html = '<table class="products-table" style="min-width:auto;margin-bottom:1rem;">';
    html += '<thead><tr>';
    html += '<th>Cod Produs</th><th>Denumire</th><th>Pret Unitar</th><th>Moneda</th><th>Cantitate</th>';
    html += '</tr></thead><tbody>';

    products.forEach(function (p) {
        html += '<tr>';
        html += '<td>' + p.cod_produs + '</td>';
        html += '<td>' + p.denumire + '</td>';
        html += '<td>' + p.pret_unitar + '</td>';
        html += '<td>' + p.moneda + '</td>';
        html += '<td>' + p.cantitate + '</td>';
        html += '</tr>';
    });

    html += '</tbody></table>';
    html += '<button class="btn-primary" onclick="downloadCSV()">Download CSV</button>';

    results.innerHTML = html;
    results.dataset.csv = csv;//aici stochez CSV-ul in data attribute pentru download
    modal.style.display = 'block';
}

// generez si dau trigger la download-ul fisierului CSV
function downloadCSV() {
    const csv = document.getElementById('pdfResults').dataset.csv;
    // creare Blob (fisier in memorie) cu continutul CSV
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    //creare URL temporar catre Blob
    const url = URL.createObjectURL(blob);
    // simulare click pe un link de download
    const a = document.createElement('a');
    a.href = url;
    a.download = 'invoice_products.csv';
    a.click();
    URL.revokeObjectURL(url);// eliberez memoria dupa download
}

// afisez erorile de validare pe campurile corespunzatoare din form
function displayFormErrors(errors) {
    for (let field in errors) {
        const errorElement = document.getElementById('error-' + field);
        if (errorElement) {
            errorElement.textContent = errors[field];
        }
    }
}

//se sterg toate mesajele de eroare din form
function clearFormErrors() {
    const errorElements = document.querySelectorAll('.error-message');
    for (let i = 0; i < errorElements.length; i++) {
        errorElements[i].textContent = '';
    }
}

//aici am decis sa afisez un mesaj temporar (success sau error) in coltul din dreapta sus a paginii web
function showMessage(message, type) {
    const messageDiv = document.createElement('div');
    messageDiv.className = 'toast-message ' + type;
    messageDiv.textContent = message;
    document.body.appendChild(messageDiv);

    setTimeout(function () {
        //adaug clasa 'show' dupa 100ms pentru a declansa animatia CSS
        messageDiv.classList.add('show');
    }, 100);

    // dupa 3 secunde, scot clasa 'show' si sterg elementul din DOM
    setTimeout(function () {
        messageDiv.classList.remove('show');
        setTimeout(function () {
            document.body.removeChild(messageDiv);
        }, 300);// wait 300ms pentru animatia de fade out
    }, 3000);
}

// initializez aplicatia dupa ce DOM-ul e complet incarcat
document.addEventListener('DOMContentLoaded', function () {
    loadProducts(1); // incarcarea primei pagini de produse

    //asociere event listeners la elemente
    document.getElementById('addProductBtn').addEventListener('click', openAddModal);
    document.getElementById('searchInput').addEventListener('input', debounceSearch);
    document.querySelector('.close').addEventListener('click', closeModal);
    document.querySelector('.close-pdf').addEventListener('click', closePdfModal);

    // butonul PDF declanseaza click pe input-ul de file (ascuns)
    document.getElementById('uploadPdfBtn').addEventListener('click', function () {
        document.getElementById('pdfInput').click();
    });

    // cand utilizatorul selecteaza un fisier, se trimite automat
    document.getElementById('pdfInput').addEventListener('change', function () {
        if (this.files && this.files[0]) {
            handlePdfUpload(this.files[0]);
        }
    });

    //inchidere modale daca se da click in afara lor
    window.addEventListener('click', function (event) {
        const modal = document.getElementById('productModal');
        const pdfModal = document.getElementById('pdfModal');
        if (event.target === modal) closeModal();
        if (event.target === pdfModal) closePdfModal();
    });
});