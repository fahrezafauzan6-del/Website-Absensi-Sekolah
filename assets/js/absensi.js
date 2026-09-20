(function () {
    'use strict';

    const STATUS = ['Hadir', 'Terlambat', 'Izin', 'Sakit', 'Alpa'];

    const STATUS_CLASS_MAP = {
        Hadir: 'status-hadir',
        Terlambat: 'status-terlambat',
        Izin: 'status-izin',
        Sakit: 'status-sakit',
        Alpa: 'status-alpa'
    };

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function getSelectedStatus(container) {
        if (!container) {
            return '';
        }

        const checked = container.querySelector(
            'input[type="radio"][data-absensi-status]:checked'
        );

        return checked ? checked.value : '';
    }

    function updateStatus(container, status) {
        if (!container) {
            return;
        }

        Object.values(STATUS_CLASS_MAP).forEach(function (className) {
            container.classList.remove(className);
        });

        if (STATUS_CLASS_MAP[status]) {
            container.classList.add(STATUS_CLASS_MAP[status]);
        }

        const options = container.querySelectorAll('[data-status-option]');

        options.forEach(function (option) {
            const optionStatus =
                option.dataset.statusOption ||
                option.getAttribute('data-status-option') ||
                option.dataset.status ||
                option.getAttribute('data-status') ||
                '';

            const radio = option.querySelector(
                'input[type="radio"]'
            );

            const isSelected =
                optionStatus === status ||
                (radio && radio.checked);

            option.classList.toggle('selected', isSelected);
            option.setAttribute(
                'aria-pressed',
                isSelected ? 'true' : 'false'
            );

            if (isSelected) {
                option.setAttribute('data-selected', 'true');
            } else {
                option.removeAttribute('data-selected');
            }
        });

        const keteranganWrapper = container.querySelector(
            '[data-keterangan-absensi]'
        );

        const keteranganInput = keteranganWrapper
            ? keteranganWrapper.querySelector(
                'textarea, input[name="keterangan"]'
            )
            : null;

        const needsKeterangan =
            status === 'Izin' ||
            status === 'Sakit';

        if (keteranganWrapper) {
            keteranganWrapper.classList.toggle(
                'd-none',
                !needsKeterangan
            );
        }

        if (keteranganInput) {
            keteranganInput.required = needsKeterangan;
        }

        const preview = container.querySelector(
            '[data-status-preview]'
        );

        if (preview) {
            if (status) {
                preview.innerHTML =
                    '<span class="badge ' +
                    'badge-' +
                    status.toLowerCase() +
                    '">' +
                    escapeHtml(status) +
                    '</span>';
            } else {
                preview.innerHTML =
                    '<span class="text-muted">Belum dipilih</span>';
            }
        }

        container.dispatchEvent(
            new CustomEvent('absensiStatusChanged', {
                detail: {
                    status: status
                }
            })
        );
    }

    function initStatusSelection() {
        const containers = document.querySelectorAll(
            '[data-absensi-status-container], [data-absensi-status-group]'
        );

        if (containers.length === 0) {
            document
                .querySelectorAll('input[type="radio"][data-absensi-status]')
                .forEach(function (radio) {
                    const form =
                        radio.closest('form[data-absensi-form]') ||
                        radio.closest('form');

                    if (form && !form.hasAttribute('data-absensi-status-container')) {
                        form.setAttribute(
                            'data-absensi-status-container',
                            ''
                        );
                    }
                });
        }

        const groups = document.querySelectorAll(
            '[data-absensi-status-container], ' +
            '[data-absensi-status-group], ' +
            'form[data-absensi-form]'
        );

        groups.forEach(function (container) {
            const radios = container.querySelectorAll(
                'input[type="radio"][data-absensi-status]'
            );

            if (radios.length === 0) {
                return;
            }

            radios.forEach(function (radio) {
                radio.addEventListener('change', function () {
                    if (radio.checked) {
                        updateStatus(container, radio.value);
                    }
                });
            });

            container
                .querySelectorAll('[data-status-option]')
                .forEach(function (option) {
                    option.addEventListener('click', function (event) {
                        const clickedRadio =
                            event.target.closest(
                                'input[type="radio"]'
                            );

                        if (clickedRadio) {
                            return;
                        }

                        const status =
                            option.dataset.statusOption ||
                            option.getAttribute('data-status-option') ||
                            option.dataset.status ||
                            option.getAttribute('data-status');

                        if (!status) {
                            return;
                        }

                        const radio = Array.from(radios).find(
                            function (item) {
                                return item.value === status;
                            }
                        );

                        if (!radio) {
                            return;
                        }

                        radio.checked = true;

                        radio.dispatchEvent(
                            new Event('change', {
                                bubbles: true
                            })
                        );
                    });

                    option.setAttribute('role', 'button');
                    option.setAttribute('tabindex', '0');

                    option.addEventListener('keydown', function (event) {
                        if (
                            event.key === 'Enter' ||
                            event.key === ' '
                        ) {
                            event.preventDefault();
                            option.click();
                        }
                    });
                });

            const selected = getSelectedStatus(container);

            if (selected) {
                updateStatus(container, selected);
            } else {
                updateStatus(container, '');
            }
        });
    }

    function initClock() {
        const clocks = document.querySelectorAll(
            '[data-absensi-clock]'
        );

        if (!clocks.length) {
            return;
        }

        function updateClock() {
            const now = new Date();

            const time = now.toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });

            clocks.forEach(function (clock) {
                clock.textContent = time;
            });

            document
                .querySelectorAll('[data-absensi-time]')
                .forEach(function (input) {
                    if (
                        input.tagName === 'INPUT' &&
                        input.type === 'time'
                    ) {
                        if (!input.value) {
                            input.value =
                                String(now.getHours()).padStart(2, '0') +
                                ':' +
                                String(now.getMinutes()).padStart(2, '0');
                        }
                    }
                });
        }

        updateClock();
        setInterval(updateClock, 1000);
    }

    function initDateInputs() {
        const inputs = document.querySelectorAll(
            '[data-absensi-date]'
        );

        if (!inputs.length) {
            return;
        }

        const now = new Date();

        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');

        const today =
            year + '-' + month + '-' + day;

        inputs.forEach(function (input) {
            input.max = today;

            if (!input.value) {
                input.value = today;
            }

            input.addEventListener('change', function () {
                if (input.value > today) {
                    input.value = today;

                    showAbsensiError(
                        'Tanggal absensi tidak boleh melebihi hari ini.'
                    );
                }
            });
        });
    }

    function initForms() {
        const forms = document.querySelectorAll(
            'form[data-absensi-form]'
        );

        forms.forEach(function (form) {
            form.addEventListener('submit', function (event) {
                const rows = form.querySelectorAll(
                    '[data-attendance-row]'
                );
                const groups = rows.length > 0
                    ? Array.from(rows)
                    : [form];

                for (const group of groups) {
                    const selectedInput = group.querySelector(
                        'input[type="radio"][data-absensi-status]:checked'
                    );
                    const selectedStatus = selectedInput
                        ? selectedInput.value
                        : '';

                    if (!selectedStatus) {
                        event.preventDefault();

                        showAbsensiError(
                            rows.length > 0
                                ? 'Silakan pilih status kehadiran untuk setiap siswa.'
                                : 'Silakan pilih status kehadiran terlebih dahulu.'
                        );

                        const firstOption = group.querySelector(
                            '[data-status-option]'
                        );

                        if (firstOption) {
                            firstOption.focus();
                        }

                        return;
                    }

                    if (!STATUS.includes(selectedStatus)) {
                        event.preventDefault();

                        showAbsensiError(
                            'Status kehadiran tidak valid.'
                        );

                        return;
                    }

                    const keterangan = group.querySelector(
                        rows.length > 0
                            ? 'textarea[name^="keterangan["], input[name^="keterangan["]'
                            : 'textarea[name="keterangan"], input[name="keterangan"]'
                    );

                    if (
                        (selectedStatus === 'Izin' ||
                            selectedStatus === 'Sakit') &&
                        keterangan &&
                        !keterangan.value.trim()
                    ) {
                        event.preventDefault();

                        keterangan.focus();

                        showAbsensiError(
                            'Keterangan wajib diisi untuk status ' +
                            selectedStatus +
                            '.'
                        );

                        return;
                    }
                }

                if (form.dataset.submitting === 'true') {
                    event.preventDefault();
                    return;
                }

                form.dataset.submitting = 'true';

                const buttons = form.querySelectorAll(
                    'button[type="submit"], input[type="submit"]'
                );

                buttons.forEach(function (button) {
                    button.disabled = true;

                    const spinner =
                        button.querySelector('.spinner-border');

                    if (spinner) {
                        spinner.classList.remove('d-none');
                    }
                });
            });
        });
    }

    function showAbsensiError(message) {
        let alertBox = document.querySelector(
            '[data-absensi-error]'
        );

        if (!alertBox) {
            alertBox = document.createElement('div');

            alertBox.setAttribute(
                'data-absensi-error',
                ''
            );

            alertBox.className =
                'alert alert-danger alert-dismissible fade show';

            alertBox.innerHTML =
                '<i class="bi bi-exclamation-triangle me-2"></i>' +
                '<span class="absensi-error-message"></span>' +
                '<button type="button" class="btn-close" ' +
                'data-bs-dismiss="alert"></button>';

            const form =
                document.querySelector('form[data-absensi-form]');

            if (form) {
                form.parentNode.insertBefore(
                    alertBox,
                    form
                );
            } else {
                document.body.prepend(alertBox);
            }
        }

        const messageElement =
            alertBox.querySelector(
                '.absensi-error-message'
            );

        if (messageElement) {
            messageElement.textContent = message;
        }

        alertBox.classList.add('show');

        alertBox.scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });
    }

    function initConfirmForms() {
        document
            .querySelectorAll('form[data-confirm-absensi]')
            .forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    const message =
                        form.dataset.confirmAbsensi ||
                        'Apakah Anda yakin ingin menyimpan absensi ini?';

                    if (!window.confirm(message)) {
                        event.preventDefault();
                    }
                });
            });
    }

    function initQuickAttendance() {
        document
            .querySelectorAll(
                '[data-quick-attendance]'
            )
            .forEach(function (button) {
                button.addEventListener('click', function () {
                    const status =
                        button.dataset.quickAttendance;

                    const form =
                        button.closest('form') ||
                        document.querySelector(
                            'form[data-absensi-form]'
                        );

                    if (!form) {
                        return;
                    }

                    const radio =
                        form.querySelector(
                            'input[type="radio"][data-absensi-status][value="' +
                            CSS.escape(status) +
                            '"]'
                        );

                    if (!radio) {
                        return;
                    }

                    radio.checked = true;

                    radio.dispatchEvent(
                        new Event('change', {
                            bubbles: true
                        })
                    );
                });
            });
    }

    function initStatusSelect() {
        document
            .querySelectorAll(
                'select[data-absensi-status-select]'
            )
            .forEach(function (select) {
                select.addEventListener('change', function () {
                    const status = select.value;

                    const form =
                        select.closest('form');

                    if (!form) {
                        return;
                    }

                    const radios =
                        form.querySelectorAll(
                            'input[type="radio"][data-absensi-status]'
                        );

                    radios.forEach(function (radio) {
                        radio.checked =
                            radio.value === status;
                    });

                    const container =
                        form.querySelector(
                            '[data-absensi-status-container], ' +
                            '[data-absensi-status-group]'
                        );

                    if (container) {
                        updateStatus(
                            container,
                            status
                        );
                    }
                });
            });
    }

    function initSetAllStatus() {
        document
            .querySelectorAll(
                '[data-set-all-status]'
            )
            .forEach(function (button) {
                button.addEventListener('click', function () {
                    const status =
                        button.dataset.setAllStatus;

                    document
                        .querySelectorAll(
                            'input[type="radio"][data-absensi-status][value="' +
                            CSS.escape(status) +
                            '"]'
                        )
                        .forEach(function (radio) {
                            radio.checked = true;

                            radio.dispatchEvent(
                                new Event('change', {
                                    bubbles: true
                                })
                            );
                        });
                });
            });
    }

    function initAttendanceFilter() {
        document
            .querySelectorAll(
                '[data-absensi-filter]'
            )
            .forEach(function (filter) {
                const targetSelector =
                    filter.dataset.absensiFilter;

                const target =
                    document.querySelector(
                        targetSelector
                    );

                if (!target) {
                    return;
                }

                filter.addEventListener('input', function () {
                    const keyword =
                        filter.value
                            .toLowerCase()
                            .trim();

                    target
                        .querySelectorAll('tbody tr')
                        .forEach(function (row) {
                            row.style.display =
                                row.textContent
                                    .toLowerCase()
                                    .includes(keyword)
                                    ? ''
                                    : 'none';
                        });
                });
            });
    }

    function initResetForms() {
        document
            .querySelectorAll(
                'form[data-absensi-form]'
            )
            .forEach(function (form) {
                form.addEventListener('reset', function () {
                    setTimeout(function () {
                        const container =
                            form.querySelector(
                                '[data-absensi-status-container], ' +
                                '[data-absensi-status-group]'
                            );

                        if (container) {
                            updateStatus(
                                container,
                                ''
                            );
                        }
                    }, 0);
                });
            });
    }

    function init() {
        initStatusSelection();
        initClock();
        initDateInputs();
        initForms();
        initConfirmForms();
        initQuickAttendance();
        initStatusSelect();
        initSetAllStatus();
        initAttendanceFilter();
        initResetForms();

        document.documentElement.classList.add(
            'absensi-js-ready'
        );
    }

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            init
        );
    } else {
        init();
    }

    window.Absensi = {
        updateStatus: updateStatus,
        showError: showAbsensiError
    };
})();