'use strict';

document.addEventListener('DOMContentLoaded', function () {

    const liveClock = document.querySelector('[data-live-clock]');
    const liveDate = document.querySelector('[data-live-date]');

    function updateClock() {
        const now = new Date();

        if (liveClock) {
            liveClock.textContent = now.toLocaleTimeString(
                'id-ID',
                {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                }
            );
        }

        if (liveDate) {
            liveDate.textContent = now.toLocaleDateString(
                'id-ID',
                {
                    weekday: 'long',
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric'
                }
            );
        }
    }

    if (liveClock || liveDate) {
        updateClock();
        setInterval(updateClock, 1000);
    }

    const counterElements =
        document.querySelectorAll('[data-counter]');

    function animateCounter(element) {
        const target =
            parseInt(element.getAttribute('data-counter'), 10);

        if (Number.isNaN(target)) {
            return;
        }

        const durationValue =
            parseInt(
                element.getAttribute('data-counter-duration'),
                10
            );

        const duration =
            Number.isNaN(durationValue)
                ? 1000
                : Math.max(durationValue, 100);

        const start = 0;
        const startTime = performance.now();

        function updateCounter(currentTime) {
            const elapsed = currentTime - startTime;
            const progress =
                Math.min(elapsed / duration, 1);

            const easedProgress =
                1 - Math.pow(1 - progress, 3);

            const currentValue = Math.floor(
                start +
                (target - start) * easedProgress
            );

            element.textContent =
                currentValue.toLocaleString('id-ID');

            if (progress < 1) {
                requestAnimationFrame(updateCounter);
            } else {
                element.textContent =
                    target.toLocaleString('id-ID');
            }
        }

        requestAnimationFrame(updateCounter);
    }

    if (
        counterElements.length > 0 &&
        'IntersectionObserver' in window
    ) {
        const counterObserver =
            new IntersectionObserver(
                function (entries, observer) {
                    entries.forEach(function (entry) {
                        if (!entry.isIntersecting) {
                            return;
                        }

                        const element = entry.target;

                        if (
                            element.dataset.counterAnimated === 'true'
                        ) {
                            return;
                        }

                        element.dataset.counterAnimated = 'true';

                        animateCounter(element);

                        observer.unobserve(element);
                    });
                },
                {
                    threshold: 0.25
                }
            );

        counterElements.forEach(function (element) {
            counterObserver.observe(element);
        });
    } else {
        counterElements.forEach(function (element) {
            animateCounter(element);
        });
    }

    const searchInputs =
        document.querySelectorAll('[data-table-search]');

    searchInputs.forEach(function (input) {
        const targetSelector =
            input.getAttribute('data-table-search');

        if (!targetSelector) {
            return;
        }

        const table =
            document.querySelector(targetSelector);

        if (!table) {
            return;
        }

        const tbody = table.querySelector('tbody');

        if (!tbody) {
            return;
        }

        const rows =
            tbody.querySelectorAll('tr');

        const emptyRow =
            table.querySelector('[data-search-empty]');

        function filterTable() {
            const keyword =
                input.value.trim().toLowerCase();

            let visibleRows = 0;

            rows.forEach(function (row) {
                if (
                    row.hasAttribute('data-search-empty')
                ) {
                    return;
                }

                const text =
                    row.textContent.toLowerCase();

                const matched =
                    keyword === '' ||
                    text.includes(keyword);

                row.style.display =
                    matched ? '' : 'none';

                if (matched) {
                    visibleRows++;
                }
            });

            if (emptyRow) {
                emptyRow.style.display =
                    visibleRows === 0 ? '' : 'none';
            }
        }

        input.addEventListener(
            'input',
            typeof window.debounce === 'function'
                ? window.debounce(filterTable, 200)
                : filterTable
        );
    });

    const statusFilters =
        document.querySelectorAll('[data-status-filter]');

    statusFilters.forEach(function (select) {
        const targetSelector =
            select.getAttribute('data-status-filter');

        if (!targetSelector) {
            return;
        }

        const table =
            document.querySelector(targetSelector);

        if (!table) {
            return;
        }

        const rows =
            table.querySelectorAll(
                'tbody tr[data-status]'
            );

        select.addEventListener('change', function () {
            const selectedStatus =
                select.value.trim().toLowerCase();

            rows.forEach(function (row) {
                const rowStatus =
                    (
                        row.getAttribute('data-status') || ''
                    ).trim().toLowerCase();

                const show =
                    selectedStatus === '' ||
                    rowStatus === selectedStatus;

                row.style.display =
                    show ? '' : 'none';
            });
        });
    });

    const dateFilters =
        document.querySelectorAll('[data-date-filter]');

    dateFilters.forEach(function (input) {
        const targetSelector =
            input.getAttribute('data-date-filter');

        if (!targetSelector) {
            return;
        }

        const table =
            document.querySelector(targetSelector);

        if (!table) {
            return;
        }

        const rows =
            table.querySelectorAll(
                'tbody tr[data-date]'
            );

        input.addEventListener('change', function () {
            const selectedDate =
                input.value.trim();

            rows.forEach(function (row) {
                const rowDate =
                    row.getAttribute('data-date') || '';

                const show =
                    selectedDate === '' ||
                    rowDate === selectedDate;

                row.style.display =
                    show ? '' : 'none';
            });
        });
    });

    const resetFilterButtons =
        document.querySelectorAll('[data-filter-reset]');

    resetFilterButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const filterContainer =
                button.closest(
                    '[data-filter-container]'
                );

            const scope =
                filterContainer || document;

            const filters =
                scope.querySelectorAll(
                    'input[data-table-search], ' +
                    'select[data-status-filter], ' +
                    'input[data-date-filter]'
                );

            filters.forEach(function (filter) {
                filter.value = '';

                filter.dispatchEvent(
                    new Event('input', {
                        bubbles: true
                    })
                );

                filter.dispatchEvent(
                    new Event('change', {
                        bubbles: true
                    })
                );
            });
        });
    });

    const refreshButtons =
        document.querySelectorAll(
            '[data-dashboard-refresh]'
        );

    refreshButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            if (button.disabled) {
                return;
            }

            button.disabled = true;

            const originalContent =
                button.innerHTML;

            button.setAttribute(
                'data-original-content',
                originalContent
            );

            button.innerHTML =
                '<span class="spinner-border spinner-border-sm me-1" ' +
                'role="status" aria-hidden="true"></span>' +
                'Memuat...';

            window.location.reload();
        });
    });

    if (typeof bootstrap !== 'undefined') {
        const dashboardTooltips =
            document.querySelectorAll(
                '.dashboard-page [data-bs-toggle="tooltip"], ' +
                '.stat-card [data-bs-toggle="tooltip"]'
            );

        dashboardTooltips.forEach(function (element) {
            bootstrap.Tooltip.getOrCreateInstance(element);
        });
    }

    const quickActions =
        document.querySelectorAll('.quick-action');

    quickActions.forEach(function (action) {
        action.addEventListener('mousedown', function () {
            action.classList.add('pressed');
        });

        action.addEventListener('mouseup', function () {
            action.classList.remove('pressed');
        });

        action.addEventListener('mouseleave', function () {
            action.classList.remove('pressed');
        });
    });

    const dashboardConfirmButtons =
        document.querySelectorAll(
            '[data-dashboard-confirm]'
        );

    dashboardConfirmButtons.forEach(function (button) {
        button.addEventListener('click', function (event) {
            const message =
                button.getAttribute(
                    'data-dashboard-confirm'
                );

            if (
                message &&
                !window.confirm(message)
            ) {
                event.preventDefault();
            }
        });
    });

    const dashboardContainer =
        document.querySelector(
            '[data-dashboard-auto-refresh]'
        );

    if (dashboardContainer) {
        const refreshInterval =
            parseInt(
                dashboardContainer.getAttribute(
                    'data-dashboard-auto-refresh'
                ),
                10
            );

        if (
            !Number.isNaN(refreshInterval) &&
            refreshInterval >= 10000
        ) {
            setInterval(function () {
                if (document.hidden) {
                    return;
                }

                window.location.reload();
            }, refreshInterval);
        }
    }

    const responsiveTables =
        document.querySelectorAll(
            '.table-responsive-custom'
        );

    function updateTableScrollState() {
        responsiveTables.forEach(function (container) {
            const canScroll =
                container.scrollWidth >
                container.clientWidth;

            container.classList.toggle(
                'has-horizontal-scroll',
                canScroll
            );
        });
    }

    if (responsiveTables.length > 0) {
        updateTableScrollState();

        window.addEventListener(
            'resize',
            typeof window.debounce === 'function'
                ? window.debounce(
                    updateTableScrollState,
                    150
                )
                : updateTableScrollState
        );
    }

    const dashboardPage =
        document.querySelector('.dashboard-page');

    if (dashboardPage) {
        dashboardPage.classList.add('dashboard-ready');
    }

    document.documentElement.classList.add(
        'dashboard-js-ready'
    );

});