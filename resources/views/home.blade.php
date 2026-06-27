<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News Aggregator</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">

<div class="max-w-4xl mx-auto px-4 py-8">

    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-900">News Aggregator</h1>
        <p id="result-count" class="text-sm text-gray-500 mt-1">Loading…</p>
    </div>

    {{-- Filter form --}}
    <form id="filter-form" class="bg-white border border-gray-200 rounded-lg p-4 mb-6 space-y-3">

        {{-- Row 1: search + dates --}}
        <div class="flex flex-wrap gap-3">
            <div class="flex-1 min-w-48">
                <label class="block text-xs text-gray-500 mb-1">Search</label>
                <input id="f-search" type="text" placeholder="Title or description…"
                    class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-400">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">From</label>
                <input id="f-from" type="date"
                    class="border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-400">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">To</label>
                <input id="f-to" type="date"
                    class="border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-400">
            </div>
        </div>

        {{-- Row 2: dropdowns --}}
        <div class="flex flex-wrap gap-3">

            {{-- Searchable source picker --}}
            <div class="flex-1 min-w-36">
                <label class="block text-xs text-gray-500 mb-1">Source</label>
                <div class="relative" id="source-picker">
                    <input id="f-source-input" type="text" placeholder="Search sources…" autocomplete="off"
                        class="w-full border border-gray-300 rounded px-3 py-1.5 pr-7 text-sm focus:outline-none focus:ring-1 focus:ring-blue-400">
                    <button id="f-source-clear" type="button" title="Clear"
                        class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 hidden leading-none text-xl">&times;</button>
                    <input id="f-source" type="hidden" value="">
                    <div id="source-dropdown"
                        class="absolute z-20 left-0 right-0 mt-1 bg-white border border-gray-200 rounded shadow-lg max-h-52 overflow-y-auto hidden text-sm">
                    </div>
                </div>
            </div>

            <div class="flex-1 min-w-36">
                <label class="block text-xs text-gray-500 mb-1">Category</label>
                <select id="f-category"
                    class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-400 bg-white">
                    <option value="">All categories</option>
                </select>
            </div>

            <div class="flex-1 min-w-36">
                <label class="block text-xs text-gray-500 mb-1">Author</label>
                <select id="f-author"
                    class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-400 bg-white">
                    <option value="">All authors</option>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit"
                    class="px-4 py-1.5 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 transition">
                    Filter
                </button>
                <button type="button" id="btn-reset"
                    class="px-4 py-1.5 bg-gray-100 text-gray-600 text-sm rounded hover:bg-gray-200 transition border border-gray-300">
                    Reset
                </button>
            </div>
        </div>

    </form>

    {{-- Article list --}}
    <div id="article-list"></div>

    {{-- Pagination --}}
    <div id="pagination" class="mt-6 flex justify-center gap-2 flex-wrap"></div>

</div>

<script>
const API_BASE = '/api';
let currentPage = 1;
let allSources  = [];

// ── helpers ──────────────────────────────────────────────────────────────────

function formatDate(d) {
    return d.toISOString().split('T')[0];
}

function yesterday() {
    const d = new Date();
    d.setDate(d.getDate() - 1);
    return formatDate(d);
}

function tomorrow() {
    const d = new Date();
    d.setDate(d.getDate() + 1);
    return formatDate(d);
}

function getFilters() {
    return {
        search:      document.getElementById('f-search').value.trim(),
        from:        document.getElementById('f-from').value,
        to:          document.getElementById('f-to').value,
        source_name: document.getElementById('f-source').value,
        category:    document.getElementById('f-category').value,
        author:      document.getElementById('f-author').value,
        page:        currentPage,
        per_page:    15,
    };
}

function buildQuery(params) {
    return Object.entries(params)
        .filter(([, v]) => v !== '' && v !== null && v !== undefined)
        .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`)
        .join('&');
}

// ── source picker ─────────────────────────────────────────────────────────────

function initSourcePicker(sources) {
    allSources = sources;

    const input    = document.getElementById('f-source-input');
    const hidden   = document.getElementById('f-source');
    const dropdown = document.getElementById('source-dropdown');
    const clearBtn = document.getElementById('f-source-clear');

    function renderDropdown(items) {
        if (!items.length) {
            dropdown.innerHTML = '<div class="px-3 py-2 text-gray-400 text-xs">No sources match</div>';
        } else {
            dropdown.innerHTML = items.map(s =>
                `<div class="px-3 py-2 cursor-pointer hover:bg-blue-50 truncate"
                      data-name="${s.name.replace(/"/g, '&quot;')}">${s.name}</div>`
            ).join('');
        }
        dropdown.classList.remove('hidden');
    }

    function hideDropdown() {
        dropdown.classList.add('hidden');
    }

    input.addEventListener('focus', () => {
        const val = input.value.trim().toLowerCase();
        renderDropdown(val
            ? allSources.filter(s => s.name.toLowerCase().includes(val))
            : allSources);
    });

    input.addEventListener('input', () => {
        hidden.value = '';
        clearBtn.classList.add('hidden');
        const val = input.value.trim().toLowerCase();
        renderDropdown(val
            ? allSources.filter(s => s.name.toLowerCase().includes(val))
            : allSources);
    });

    // mousedown fires before blur so we capture the click before the input blurs
    dropdown.addEventListener('mousedown', e => {
        const item = e.target.closest('[data-name]');
        if (!item) return;
        e.preventDefault();
        input.value  = item.dataset.name;
        hidden.value = item.dataset.name;
        clearBtn.classList.remove('hidden');
        hideDropdown();
    });

    clearBtn.addEventListener('click', () => {
        input.value  = '';
        hidden.value = '';
        clearBtn.classList.add('hidden');
    });

    input.addEventListener('blur', () => setTimeout(hideDropdown, 150));
}

function resetSourcePicker() {
    document.getElementById('f-source-input').value = '';
    document.getElementById('f-source').value        = '';
    document.getElementById('f-source-clear').classList.add('hidden');
}

// ── filter dropdowns ─────────────────────────────────────────────────────────

async function loadFilters() {
    const [sourcesRes, categoriesRes, authorsRes] = await Promise.all([
        fetch(`${API_BASE}/filters/sources`),
        fetch(`${API_BASE}/filters/categories`),
        fetch(`${API_BASE}/filters/authors`),
    ]);

    const [{ data: sources }, { data: categories }, { data: authors }] =
        await Promise.all([sourcesRes.json(), categoriesRes.json(), authorsRes.json()]);

    initSourcePicker(sources);

    const categorySelect = document.getElementById('f-category');
    const authorSelect   = document.getElementById('f-author');

    categories.forEach(c => {
        const label = String(c).charAt(0).toUpperCase() + String(c).slice(1);
        categorySelect.appendChild(new Option(label, c));
    });

    authors.forEach(a => {
        authorSelect.appendChild(new Option(a, a));
    });
}

// ── articles ─────────────────────────────────────────────────────────────────

async function loadArticles() {
    const listEl  = document.getElementById('article-list');
    const countEl = document.getElementById('result-count');

    listEl.innerHTML = `<p class="text-sm text-gray-400 py-6 text-center">Loading…</p>`;

    const query = buildQuery(getFilters());
    const res   = await fetch(`${API_BASE}/articles?${query}`);
    const { data: articles, meta } = await res.json();

    countEl.textContent = `${meta.total} article${meta.total !== 1 ? 's' : ''} found`;

    if (articles.length === 0) {
        listEl.innerHTML = `
            <div class="bg-white border border-gray-200 rounded-lg p-8 text-center text-gray-400">
                No articles found for the selected filters.
            </div>`;
        renderPagination(meta);
        return;
    }

    listEl.innerHTML = articles.map(article => {
        const img = article.image_url
            ? `<img src="${article.image_url}" alt="" class="w-28 h-20 object-cover rounded flex-shrink-0"
                    onerror="this.style.display='none'">`
            : '';

        const desc = article.description
            ? `<p class="text-sm text-gray-500 line-clamp-2 mb-2">${article.description}</p>`
            : '';

        const metaParts = [
            article.source_name ? `<span class="font-medium text-gray-600">${article.source_name}</span>` : '',
            article.published_at ? `<span>${formatDisplayDate(article.published_at)}</span>` : '',
            article.author       ? `<span>${article.author}</span>` : '',
        ].filter(Boolean).join('<span class="mx-1">·</span>');

        const tagList = Array.isArray(article.category) && article.category.length
            ? `<div class="flex flex-wrap gap-1 mt-1">
                ${article.category.map(c =>
                    `<span class="text-xs bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded">${c}</span>`
                ).join('')}
               </div>`
            : '';

        return `
            <div class="bg-white border border-gray-200 rounded-lg mb-3 overflow-hidden flex gap-4 p-4">
                ${img}
                <div class="flex-1 min-w-0">
                    <a href="${article.url}" target="_blank" rel="noopener"
                       class="text-base font-medium text-gray-900 hover:text-blue-600 leading-snug block mb-1">
                        ${article.title}
                    </a>
                    ${desc}
                    <div class="flex items-center gap-1 text-xs text-gray-400">${metaParts}</div>
                    ${tagList}
                </div>
            </div>`;
    }).join('');

    renderPagination(meta);
}

function formatDisplayDate(iso) {
    const d = new Date(iso);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
        + '  ' + d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false });
}

// ── pagination ────────────────────────────────────────────────────────────────

function renderPagination(meta) {
    const el = document.getElementById('pagination');
    if (meta.last_page <= 1) { el.innerHTML = ''; return; }

    const btn = (label, page, disabled, active) => `
        <button onclick="goToPage(${page})"
            class="px-3 py-1 text-sm rounded border ${active
                ? 'bg-blue-600 text-white border-blue-600'
                : disabled
                    ? 'text-gray-300 border-gray-200 cursor-not-allowed'
                    : 'text-gray-600 border-gray-300 hover:bg-gray-50'}"
            ${disabled ? 'disabled' : ''}>
            ${label}
        </button>`;

    let pages = '';
    const range = 2;
    for (let p = 1; p <= meta.last_page; p++) {
        if (p === 1 || p === meta.last_page || Math.abs(p - meta.current_page) <= range) {
            pages += btn(p, p, false, p === meta.current_page);
        } else if (Math.abs(p - meta.current_page) === range + 1) {
            pages += `<span class="px-1 text-gray-400">…</span>`;
        }
    }

    el.innerHTML =
        btn('← Prev', meta.current_page - 1, meta.current_page === 1, false) +
        pages +
        btn('Next →', meta.current_page + 1, meta.current_page === meta.last_page, false);
}

function goToPage(page) {
    currentPage = page;
    loadArticles();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ── events ────────────────────────────────────────────────────────────────────

document.getElementById('filter-form').addEventListener('submit', e => {
    e.preventDefault();
    currentPage = 1;
    loadArticles();
});

document.getElementById('btn-reset').addEventListener('click', () => {
    document.getElementById('f-search').value   = '';
    document.getElementById('f-from').value     = yesterday();
    document.getElementById('f-to').value       = tomorrow();
    resetSourcePicker();
    document.getElementById('f-category').value = '';
    document.getElementById('f-author').value   = '';
    currentPage = 1;
    loadArticles();
});

// ── init ──────────────────────────────────────────────────────────────────────

document.getElementById('f-from').value = yesterday();
document.getElementById('f-to').value   = tomorrow();

loadFilters();
loadArticles();
</script>

</body>
</html>
