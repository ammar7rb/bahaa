(function () {
    'use strict';

    var isRunning = false;
    var printRootId = 'ba-smart-print-root';
    var progressId = 'ba-print-progress';
    var pageStyleId = 'ba-smart-print-page-style';

    function uiText(arabic, english) {
        return document.documentElement.dir === 'rtl' ? arabic : english;
    }

    function normalizedText(element) {
        return (element && element.textContent ? element.textContent : '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function showProgress(message, detail) {
        var box = document.getElementById(progressId);
        if (!box) {
            box = document.createElement('div');
            box.id = progressId;
            box.className = 'ba-print-progress';
            box.innerHTML = '<span class="ba-print-progress__spinner" aria-hidden="true"></span>' +
                '<span><strong class="ba-print-progress__title"></strong>' +
                '<small class="ba-print-progress__detail"></small></span>';
            document.body.appendChild(box);
        }
        box.querySelector('.ba-print-progress__title').textContent = message;
        box.querySelector('.ba-print-progress__detail').textContent = detail || '';
        box.hidden = false;
    }

    function hideProgress() {
        var box = document.getElementById(progressId);
        if (box) box.hidden = true;
    }

    function contentRoot(scope) {
        return scope.querySelector('.v2-main .content, .v2-main, main .content, main, .content');
    }

    function tableSignature(table, fallbackIndex) {
        return Array.from(table.querySelectorAll('thead th'))
            .map(normalizedText)
            .filter(Boolean)
            .join('|') || 'table-' + fallbackIndex;
    }

    function rowSignature(row) {
        return normalizedText(row).toLowerCase();
    }

    function collectPageRequests() {
        var links = Array.from(document.querySelectorAll('.pagination a[href], nav[aria-label*="pagination" i] a[href]'));
        var groups = {};

        links.forEach(function (link) {
            var url;
            try { url = new URL(link.href, window.location.href); } catch (error) { return; }
            if (url.origin !== window.location.origin) return;

            Array.from(url.searchParams.keys()).forEach(function (key) {
                if (!/(^|_)page$/i.test(key)) return;
                var value = parseInt(url.searchParams.get(key), 10);
                if (!Number.isFinite(value) || value < 1) return;
                var identityUrl = new URL(url.href);
                identityUrl.searchParams.delete(key);
                var identity = identityUrl.pathname + '?' + identityUrl.searchParams.toString() + '#' + key;
                if (!groups[identity]) groups[identity] = { key: key, max: 1, sample: url };
                groups[identity].max = Math.max(groups[identity].max, value);
            });
        });

        var requests = [];
        Object.keys(groups).forEach(function (identity) {
            var group = groups[identity];
            var current = parseInt(new URL(window.location.href).searchParams.get(group.key), 10) || 1;
            for (var page = 1; page <= group.max; page += 1) {
                if (page === current) continue;
                var target = new URL(group.sample.href);
                target.searchParams.set(group.key, String(page));
                requests.push(target.href);
            }
        });
        return Array.from(new Set(requests));
    }

    function copyAllClientRows(sourceRoot, cloneRoot) {
        if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.dataTable) return;

        Array.from(sourceRoot.querySelectorAll('table')).forEach(function (sourceTable, index) {
            if (!window.jQuery.fn.dataTable.isDataTable(sourceTable)) return;
            var cloneTable = cloneRoot.querySelectorAll('table')[index];
            if (!cloneTable || !cloneTable.tBodies.length) return;
            var api = window.jQuery(sourceTable).DataTable();
            var rows = api.rows({ search: 'applied' }).nodes().toArray();
            cloneTable.tBodies[0].innerHTML = '';
            rows.forEach(function (row) { cloneTable.tBodies[0].appendChild(row.cloneNode(true)); });
        });
    }

    function tableMap(root) {
        var map = {};
        Array.from(root.querySelectorAll('table')).forEach(function (table, index) {
            var signature = tableSignature(table, index);
            if (!map[signature]) map[signature] = [];
            map[signature].push(table);
        });
        return map;
    }

    function appendPageRows(printMap, pageDocument) {
        var pageRoot = contentRoot(pageDocument);
        if (!pageRoot) return;
        var pageMap = tableMap(pageRoot);

        Object.keys(printMap).forEach(function (signature) {
            var targets = printMap[signature] || [];
            var sources = pageMap[signature] || [];
            targets.forEach(function (target, index) {
                var source = sources[index] || sources[0];
                if (!source || !target.tBodies.length || !source.tBodies.length) return;
                var known = new Set(Array.from(target.tBodies[0].rows).map(rowSignature));
                Array.from(source.tBodies[0].rows).forEach(function (row) {
                    var signatureValue = rowSignature(row);
                    if (!signatureValue || known.has(signatureValue)) return;
                    var copy = row.cloneNode(true);
                    copy.querySelectorAll('[id]').forEach(function (node) { node.removeAttribute('id'); });
                    target.tBodies[0].appendChild(copy);
                    known.add(signatureValue);
                });
            });
        });
    }

    async function fetchAllPages(urls, printMap) {
        if (!urls.length) return;
        var completed = 0;
        var cursor = 0;
        var workerCount = Math.min(3, urls.length);

        async function worker() {
            while (cursor < urls.length) {
                var index = cursor++;
                try {
                    var response = await fetch(urls[index], {
                        credentials: 'same-origin',
                        headers: { 'Accept': 'text/html' }
                    });
                    if (!response.ok) continue;
                    var html = await response.text();
                    appendPageRows(printMap, new DOMParser().parseFromString(html, 'text/html'));
                } catch (error) {
                    // Keep the printable pages already collected if one request fails.
                } finally {
                    completed += 1;
                    showProgress(
                        uiText('جارٍ تجهيز السجل الكامل للطباعة', 'Preparing the complete record'),
                        uiText('تم جمع ' + completed + ' من ' + urls.length + ' صفحات', 'Collected ' + completed + ' of ' + urls.length + ' pages')
                    );
                }
            }
        }

        await Promise.all(Array.from({ length: workerCount }, worker));
    }

    function readableTitle(sourceRoot) {
        var heading = sourceRoot.querySelector('h1, .page-header-title, h2, h3');
        return normalizedText(heading) || document.title;
    }

    function cleanPrintableClone(clone) {
        clone.querySelectorAll('script, style, template, .pagination, .dataTables_paginate, .dataTables_length, .dataTables_filter, .dataTables_info, .no-print, .print-hide, [data-print-hidden], .dropdown-menu, .tooltip, .popover, button').forEach(function (node) {
            node.remove();
        });
        clone.querySelectorAll('[style*="overflow"]').forEach(function (node) {
            node.style.overflow = 'visible';
            node.style.maxHeight = 'none';
        });
        clone.querySelectorAll('input, textarea, select').forEach(function (field) {
            var value = field.tagName === 'SELECT'
                ? normalizedText(field.options[field.selectedIndex])
                : field.value;
            if (!value || field.type === 'hidden' || field.type === 'checkbox' || field.type === 'radio') {
                field.remove();
                return;
            }
            var text = document.createElement('span');
            text.className = 'ba-print-field-value';
            text.textContent = value;
            field.replaceWith(text);
        });
        clone.querySelectorAll('a[href]').forEach(function (link) { link.removeAttribute('href'); });
        clone.querySelectorAll('table').forEach(function (table) {
            var removableColumns = [];
            Array.from(table.querySelectorAll('thead th')).forEach(function (heading, index) {
                var label = normalizedText(heading).toLowerCase();
                var hasSelection = !!heading.querySelector('input[type="checkbox"]');
                if (hasSelection || ['action', 'actions', 'الإجراءات', 'الاجراءات'].indexOf(label) !== -1) {
                    removableColumns.push(index);
                }
            });
            removableColumns.reverse().forEach(function (columnIndex) {
                Array.from(table.rows).forEach(function (row) {
                    if (row.cells[columnIndex]) row.cells[columnIndex].remove();
                });
            });
        });
    }

    function reportHeader(title, rowCount) {
        var header = document.createElement('header');
        header.className = 'ba-print-report-header';
        var logo = document.querySelector('.v2-brand-logo img, .navbar-logo img, .navbar-brand-logo');
        var company = (logo && logo.alt && logo.alt.toLowerCase() !== 'logo') ? logo.alt : document.title;
        header.innerHTML = '<div class="ba-print-report-brand"></div><div class="ba-print-report-meta"></div>';
        var brand = header.querySelector('.ba-print-report-brand');
        if (logo && logo.src) {
            var image = document.createElement('img');
            image.src = logo.src;
            image.alt = company;
            brand.appendChild(image);
        }
        var brandText = document.createElement('span');
        brandText.innerHTML = '<strong></strong><small></small>';
        brandText.querySelector('strong').textContent = title;
        brandText.querySelector('small').textContent = company;
        brand.appendChild(brandText);
        var meta = header.querySelector('.ba-print-report-meta');
        meta.textContent = new Intl.DateTimeFormat(document.documentElement.lang || undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date());
        if (rowCount) meta.append(' · ' + uiText(rowCount + ' سجل', rowCount + ' records'));
        return header;
    }

    async function waitForImages(root) {
        var images = Array.from(root.querySelectorAll('img'));
        images.forEach(function (image) { image.loading = 'eager'; });
        var readiness = images.map(function (image) {
            if (image.complete) return Promise.resolve();
            if (typeof image.decode === 'function') return image.decode().catch(function () {});
            return new Promise(function (resolve) {
                image.addEventListener('load', resolve, { once: true });
                image.addEventListener('error', resolve, { once: true });
            });
        });
        await Promise.race([
            Promise.all(readiness),
            new Promise(function (resolve) { window.setTimeout(resolve, 2500); })
        ]);
    }

    function cleanup() {
        document.body.classList.remove('ba-smart-printing');
        var root = document.getElementById(printRootId);
        var style = document.getElementById(pageStyleId);
        if (root) root.remove();
        if (style) style.remove();
        hideProgress();
        isRunning = false;
    }

    async function printCompletePage() {
        if (isRunning) return;
        isRunning = true;
        showProgress(uiText('جارٍ تجهيز الطباعة الكاملة', 'Preparing full-page printing'), uiText('يتم الآن جمع جميع السجلات…', 'Collecting all records…'));

        try {
            var sourceRoot = contentRoot(document);
            if (!sourceRoot) throw new Error('Printable content was not found.');
            var clone = sourceRoot.cloneNode(true);
            copyAllClientRows(sourceRoot, clone);
            var printMap = tableMap(clone);
            await fetchAllPages(collectPageRequests(), printMap);
            cleanPrintableClone(clone);

            var printRoot = document.createElement('section');
            printRoot.id = printRootId;
            printRoot.setAttribute('dir', document.documentElement.dir || 'ltr');
            var rowCount = Array.from(clone.querySelectorAll('table tbody tr')).length;
            printRoot.appendChild(reportHeader(readableTitle(sourceRoot), rowCount));
            printRoot.appendChild(clone);
            document.body.appendChild(printRoot);

            var widestTable = Array.from(clone.querySelectorAll('table')).reduce(function (maximum, table) {
                return Math.max(maximum, table.querySelectorAll('thead th').length);
            }, 0);
            if (widestTable > 7) {
                var pageStyle = document.createElement('style');
                pageStyle.id = pageStyleId;
                pageStyle.textContent = '@media print { @page { size: landscape; margin: 9mm; } }';
                document.head.appendChild(pageStyle);
            }

            await waitForImages(printRoot);
            document.body.classList.add('ba-smart-printing');
            hideProgress();
            window.setTimeout(function () { window.print(); }, 120);
        } catch (error) {
            cleanup();
            window.alert(uiText('تعذر تجهيز الطباعة الكاملة. حاول مرة أخرى.', 'Could not prepare full-page printing. Please try again.'));
        }
    }

    window.addEventListener('afterprint', cleanup);
    window.baFullPagePrint = printCompletePage;
}());
