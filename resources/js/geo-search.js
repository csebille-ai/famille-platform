// Geo search autocomplete for event location field
export function initGeoSearch(inputElement, hiddenLabelEl, hiddenLatEl, hiddenLonEl) {
    if (!inputElement) return;

    let debounceTimer;
    let suggestionsContainer;
    let currentFocus = -1;

    // Create suggestions container
    suggestionsContainer = document.createElement('div');
    suggestionsContainer.className = 'absolute z-50 w-full mt-1 bg-white border border-[color:var(--fam-border-soft)] rounded-2xl shadow-lg max-h-60 overflow-y-auto hidden';
    inputElement.parentElement.style.position = 'relative';
    inputElement.parentElement.appendChild(suggestionsContainer);

    inputElement.addEventListener('input', (e) => {
        const query = e.target.value.trim();
        clearTimeout(debounceTimer);
        
        if (query.length < 3) {
            suggestionsContainer.classList.add('hidden');
            return;
        }

        debounceTimer = setTimeout(() => {
            fetchSuggestions(query);
        }, 300);
    });

    async function fetchSuggestions(query) {
        try {
            const response = await fetch(`/geo/search?q=${encodeURIComponent(query)}`);
            if (!response.ok) {
                console.error('Geo search failed', response.status);
                return;
            }

            const results = await response.json();
            displaySuggestions(results);
        } catch (error) {
            console.error('Geo search error', error);
        }
    }

    function displaySuggestions(results) {
        if (!results || results.length === 0) {
            suggestionsContainer.classList.add('hidden');
            return;
        }

        suggestionsContainer.innerHTML = '';
        currentFocus = -1;

        results.forEach((result, index) => {
            const item = document.createElement('div');
            item.className = 'px-4 py-3 text-sm hover:bg-[color:var(--fam-tint)] cursor-pointer border-b border-[color:var(--fam-border-soft)] last:border-b-0';
            item.textContent = result.label;
            item.dataset.index = index;
            item.dataset.label = result.label;
            item.dataset.lat = result.lat;
            item.dataset.lon = result.lon;

            item.addEventListener('click', () => {
                selectSuggestion(result);
            });

            suggestionsContainer.appendChild(item);
        });

        suggestionsContainer.classList.remove('hidden');
    }

    function selectSuggestion(result) {
        inputElement.value = result.label;
        
        if (hiddenLabelEl) hiddenLabelEl.value = result.label;
        if (hiddenLatEl) hiddenLatEl.value = result.lat;
        if (hiddenLonEl) hiddenLonEl.value = result.lon;

        suggestionsContainer.classList.add('hidden');
    }

    // Keyboard navigation
    inputElement.addEventListener('keydown', (e) => {
        const items = suggestionsContainer.querySelectorAll('div[data-index]');
        if (items.length === 0) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            currentFocus++;
            if (currentFocus >= items.length) currentFocus = 0;
            setActive(items);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            currentFocus--;
            if (currentFocus < 0) currentFocus = items.length - 1;
            setActive(items);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (currentFocus > -1 && items[currentFocus]) {
                const item = items[currentFocus];
                selectSuggestion({
                    label: item.dataset.label,
                    lat: parseFloat(item.dataset.lat),
                    lon: parseFloat(item.dataset.lon),
                });
            }
        } else if (e.key === 'Escape') {
            suggestionsContainer.classList.add('hidden');
        }
    });

    function setActive(items) {
        items.forEach((item, index) => {
            if (index === currentFocus) {
                item.classList.add('bg-[color:var(--fam-tint)]');
            } else {
                item.classList.remove('bg-[color:var(--fam-tint)]');
            }
        });
    }

    // Close suggestions when clicking outside
    document.addEventListener('click', (e) => {
        if (!inputElement.parentElement.contains(e.target)) {
            suggestionsContainer.classList.add('hidden');
        }
    });
}
