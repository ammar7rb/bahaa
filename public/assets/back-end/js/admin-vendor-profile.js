document.addEventListener('DOMContentLoaded', function () {
    const table = document.querySelector('.vendor-profile-workspace #columnSearchDatatable');
    if (table) {
        const labels = Array.from(table.querySelectorAll('thead th'), function (cell) { return cell.textContent.trim(); });
        table.querySelectorAll('tbody tr').forEach(function (row) {
            Array.from(row.children).forEach(function (cell, index) { cell.dataset.fieldLabel = labels[index] || ''; });
        });
    }
    document.querySelectorAll('[data-profile-auto-open]').forEach(function (element) {
        if (window.bootstrap) bootstrap.Modal.getOrCreateInstance(element).show();
    });
    document.querySelectorAll('.vendor-profile-modal').forEach(function (element) {
        element.addEventListener('hidden.bs.modal', function () {
            element.querySelectorAll('iframe[data-product-preview]').forEach(function (frame) {
                frame.removeAttribute('src');
            });
        });
        element.addEventListener('show.bs.modal', function () {
            element.querySelectorAll('iframe[data-product-preview]').forEach(function (frame) {
                if (!frame.getAttribute('src')) frame.setAttribute('src', frame.dataset.productPreview);
            });
        });
    });
});
