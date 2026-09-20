'use strict';

document.addEventListener('DOMContentLoaded', function () {

    const sidebar = document.getElementById('sidebar');

    if (sidebar && typeof bootstrap !== 'undefined') {
        const sidebarOffcanvas =
            bootstrap.Offcanvas.getOrCreateInstance(sidebar);

        const customToggleButtons = document.querySelectorAll(
            '[data-sidebar-toggle]:not([data-bs-toggle="offcanvas"])'
        );

        customToggleButtons.forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();

                if (sidebar.classList.contains('show')) {
                    sidebarOffcanvas.hide();
                } else {
                    sidebarOffcanvas.show();
                }
            });
        });

        const customCloseButtons = document.querySelectorAll(
            '[data-sidebar-close]:not([data-bs-dismiss="offcanvas"])'
        );

        customCloseButtons.forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                sidebarOffcanvas.hide();
            });
        });

        const sidebarLinks = sidebar.querySelectorAll(
            '.sidebar-link:not([data-bs-toggle])'
        );

        sidebarLinks.forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth < 992) {
                    sidebarOffcanvas.hide();
                }
            });
        });

        sidebar.addEventListener('hidden.bs.offcanvas', function () {
            document.body.classList.remove('sidebar-open');
        });

        sidebar.addEventListener('show.bs.offcanvas', function () {
            document.body.classList.add('sidebar-open');
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth >= 992) {
                sidebarOffcanvas.hide();
                document.body.classList.remove('sidebar-open');
            }
        });

    }

    const confirmButtons = document.querySelectorAll('[data-confirm]');

    confirmButtons.forEach(function (button) {
        button.addEventListener('click', function (event) {
            const message = button.getAttribute('data-confirm');

            if (message && !window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    const confirmForms = document.querySelectorAll('[data-confirm-form]');

    confirmForms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            const message = form.getAttribute('data-confirm-form');

            if (message && !window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    const autoDismissAlerts = document.querySelectorAll(
        '.alert[data-auto-dismiss], .alert-auto-dismiss'
    );

    autoDismissAlerts.forEach(function (alert) {
        let delay = parseInt(
            alert.getAttribute('data-auto-dismiss') || '5000',
            10
        );

        if (Number.isNaN(delay) || delay < 1000) {
            delay = 5000;
        }

        setTimeout(function () {
            if (typeof bootstrap !== 'undefined') {
                const alertInstance =
                    bootstrap.Alert.getOrCreateInstance(alert);

                alertInstance.close();
            } else {
                alert.remove();
            }
        }, delay);
    });

    if (typeof bootstrap !== 'undefined') {

        const tooltipElements = document.querySelectorAll(
            '[data-bs-toggle="tooltip"]'
        );

        tooltipElements.forEach(function (element) {
            bootstrap.Tooltip.getOrCreateInstance(element);
        });


        const popoverElements = document.querySelectorAll(
            '[data-bs-toggle="popover"]'
        );

        popoverElements.forEach(function (element) {
            bootstrap.Popover.getOrCreateInstance(element);
        });
    }

    const autoFocusElement = document.querySelector('[data-autofocus]');

    if (autoFocusElement) {
        setTimeout(function () {
            autoFocusElement.focus();
        }, 100);
    }

    const phoneInputs = document.querySelectorAll(
        'input[type="tel"], [data-phone]'
    );

    phoneInputs.forEach(function (input) {
        input.addEventListener('input', function () {
            this.value = this.value.replace(/[^\d+\-()\s]/g, '');
        });
    });

    const protectedForms = document.querySelectorAll(
        'form[data-prevent-double-submit]'
    );

    protectedForms.forEach(function (form) {
        form.addEventListener('submit', function () {
            const submitButtons = form.querySelectorAll(
                'button[type="submit"], input[type="submit"]'
            );

            submitButtons.forEach(function (button) {
                button.disabled = true;

                const originalText = button.innerHTML;

                button.setAttribute(
                    'data-original-text',
                    originalText
                );

                if (button.tagName.toLowerCase() === 'button') {
                    button.innerHTML =
                        '<span class="spinner-border spinner-border-sm me-1" ' +
                        'role="status" aria-hidden="true"></span>' +
                        'Memproses...';
                }
            });
        });
    });

    const selectAllCheckboxes = document.querySelectorAll(
        '[data-select-all]'
    );

    selectAllCheckboxes.forEach(function (selectAll) {
        const targetSelector =
            selectAll.getAttribute('data-select-all');

        if (!targetSelector) {
            return;
        }

        const target = document.querySelector(targetSelector);

        if (!target) {
            return;
        }

        const checkboxes = target.querySelectorAll(
            'input[type="checkbox"]'
        );

        selectAll.addEventListener('change', function () {
            checkboxes.forEach(function (checkbox) {
                checkbox.checked = selectAll.checked;
            });
        });

        checkboxes.forEach(function (checkbox) {
            checkbox.addEventListener('change', function () {
                const checkedCount = target.querySelectorAll(
                    'input[type="checkbox"]:checked'
                ).length;

                selectAll.checked =
                    checkboxes.length > 0 &&
                    checkedCount === checkboxes.length;

                selectAll.indeterminate =
                    checkedCount > 0 &&
                    checkedCount < checkboxes.length;
            });
        });
    });

    const passwordToggleButtons = document.querySelectorAll(
        '[data-password-toggle]'
    );

    passwordToggleButtons.forEach(function (button) {
        const targetSelector =
            button.getAttribute('data-password-toggle');

        if (!targetSelector) {
            return;
        }

        const passwordInput =
            document.querySelector(targetSelector);

        if (!passwordInput) {
            return;
        }

        button.addEventListener('click', function () {
            const isPassword =
                passwordInput.getAttribute('type') === 'password';

            passwordInput.setAttribute(
                'type',
                isPassword ? 'text' : 'password'
            );

            const icon = button.querySelector('i');

            if (icon) {
                icon.classList.toggle('bi-eye', !isPassword);
                icon.classList.toggle('bi-eye-slash', isPassword);
            }

            button.setAttribute(
                'aria-label',
                isPassword
                    ? 'Sembunyikan password'
                    : 'Tampilkan password'
            );
        });
    });

    const passwordInputs = document.querySelectorAll(
        'input[type="password"]'
    );

    passwordInputs.forEach(function (input) {
        input.addEventListener('keyup', function (event) {
            const targetId = input.id
                ? input.id + '-capslock'
                : null;

            if (!targetId) {
                return;
            }

            let warning =
                document.getElementById(targetId);

            if (
                event.getModifierState &&
                event.getModifierState('CapsLock')
            ) {
                if (!warning) {
                    warning = document.createElement('small');
                    warning.id = targetId;
                    warning.className =
                        'text-warning d-block mt-1';
                    warning.textContent =
                        'Caps Lock sedang aktif.';

                    input.parentElement.appendChild(warning);
                }
            } else if (warning) {
                warning.remove();
            }
        });
    });

    const smoothScrollLinks = document.querySelectorAll(
        'a[href^="#"]:not([href="#"])'
    );

    smoothScrollLinks.forEach(function (link) {
        link.addEventListener('click', function (event) {
            const targetId =
                link.getAttribute('href');

            if (!targetId || targetId === '#') {
                return;
            }

            const target =
                document.querySelector(targetId);

            if (!target) {
                return;
            }

            event.preventDefault();

            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        });
    });

    const logoutLinks = document.querySelectorAll(
        '.logout-link, [data-logout]'
    );

    logoutLinks.forEach(function (link) {
        link.addEventListener('click', function (event) {
            const message =
                link.getAttribute('data-logout') ||
                'Apakah Anda yakin ingin keluar dari aplikasi?';

            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    window.debounce = function (callback, delay) {
        let timeout;

        return function () {
            const context = this;
            const args = arguments;

            clearTimeout(timeout);

            timeout = setTimeout(function () {
                callback.apply(context, args);
            }, delay);
        };
    };

    window.formatTanggalIndonesia = function (dateString) {
        if (!dateString) {
            return '-';
        }

        const date =
            new Date(dateString + 'T00:00:00');

        if (Number.isNaN(date.getTime())) {
            return dateString;
        }

        const bulan = [
            'Januari',
            'Februari',
            'Maret',
            'April',
            'Mei',
            'Juni',
            'Juli',
            'Agustus',
            'September',
            'Oktober',
            'November',
            'Desember'
        ];

        return (
            date.getDate() +
            ' ' +
            bulan[date.getMonth()] +
            ' ' +
            date.getFullYear()
        );
    };

    window.formatWaktu = function (timeString) {
        if (!timeString) {
            return '-';
        }

        return String(timeString).substring(0, 5);
    };

    const clickableRows =
        document.querySelectorAll('tr[data-href]');

    clickableRows.forEach(function (row) {
        row.addEventListener('click', function (event) {
            if (
                event.target.closest('a') ||
                event.target.closest('button') ||
                event.target.closest('input') ||
                event.target.closest('select') ||
                event.target.closest('textarea')
            ) {
                return;
            }

            const url =
                row.getAttribute('data-href');

            if (url) {
                window.location.href = url;
            }
        });

        row.style.cursor = 'pointer';
    });

    window.showLoading = function (element) {
        if (!element) {
            return;
        }

        element.classList.add('is-loading');
        element.setAttribute('aria-busy', 'true');
    };


    window.hideLoading = function (element) {
        if (!element) {
            return;
        }

        element.classList.remove('is-loading');
        element.setAttribute('aria-busy', 'false');
    };

    const backToTopButton =
        document.querySelector('[data-back-to-top]');

    if (backToTopButton) {
        window.addEventListener('scroll', function () {
            if (window.scrollY > 300) {
                backToTopButton.classList.add('show');
            } else {
                backToTopButton.classList.remove('show');
            }
        });

        backToTopButton.addEventListener('click', function () {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }

    if (window.location.hash === '#submitted') {
        history.replaceState(
            null,
            document.title,
            window.location.pathname +
            window.location.search
        );
    }

    window.addEventListener('error', function (event) {
        console.error(
            'JavaScript Error:',
            event.error || event.message
        );
    });

    document.documentElement.classList.add('js-ready');

});