/*
 * Go! AOP demo — behaviour of the page
 * The page works without this script: the AOP switch is a plain form and all sources are shown.
 */
(function () {
    'use strict';

    document.documentElement.classList.replace('no-js', 'js');

    // AOP switch: flips the knob first, then submits the form that stores the new state
    document.querySelectorAll('[data-aop-switch]').forEach(function (form) {
        var initialState = form.querySelector('[role="switch"]').getAttribute('aria-checked');

        // Back/forward cache restores the page as it was left: with the knob already flipped
        window.addEventListener('pageshow', function () {
            form.classList.remove('is-pending');
            form.querySelector('[role="switch"]').setAttribute('aria-checked', initialState);
        });

        form.addEventListener('submit', function (event) {
            if (form.classList.contains('is-pending')) {
                return;
            }
            event.preventDefault();
            var button = form.querySelector('[role="switch"]');
            button.setAttribute('aria-checked', String(button.getAttribute('aria-checked') !== 'true'));
            form.classList.add('is-pending');
            window.setTimeout(function () {
                form.submit();
            }, 220);
        });
    });

    // Source code tabs
    document.querySelectorAll('[data-tabs]').forEach(function (container) {
        var tabs = Array.prototype.slice.call(container.querySelectorAll('[role="tab"]'));

        function select(tab, focus) {
            tabs.forEach(function (other) {
                var isSelected = other === tab;
                other.setAttribute('aria-selected', String(isSelected));
                other.tabIndex = isSelected ? 0 : -1;
                document.getElementById(other.getAttribute('aria-controls')).hidden = !isSelected;
            });
            if (focus) {
                tab.focus();
            }
        }

        tabs.forEach(function (tab, index) {
            tab.addEventListener('click', function () {
                select(tab, false);
            });
            tab.addEventListener('keydown', function (event) {
                var offset = { ArrowRight: 1, ArrowLeft: -1 }[event.key];
                if (offset) {
                    event.preventDefault();
                    select(tabs[(index + offset + tabs.length) % tabs.length], true);
                }
            });
        });
        if (tabs.length > 0) {
            select(tabs[0], false);
        }
    });

    // Copy buttons
    document.querySelectorAll('[data-copy]').forEach(function (button) {
        button.addEventListener('click', function () {
            var source = document.querySelector(button.getAttribute('data-copy'));
            if (!source || !navigator.clipboard) {
                return;
            }
            navigator.clipboard.writeText(source.textContent).then(function () {
                button.textContent = 'Copied';
                button.classList.add('is-done');
                window.setTimeout(function () {
                    button.textContent = 'Copy';
                    button.classList.remove('is-done');
                }, 1500);
            });
        });
    });

    // Navigation drawer on small screens
    var menuButton = document.querySelector('[data-menu-toggle]');

    function toggleMenu(isOpen) {
        document.body.classList.toggle('is-menu-open', isOpen);
        if (menuButton) {
            menuButton.setAttribute('aria-expanded', String(isOpen));
        }
    }

    if (menuButton) {
        menuButton.addEventListener('click', function () {
            toggleMenu(!document.body.classList.contains('is-menu-open'));
        });
    }
    document.querySelectorAll('[data-menu-close]').forEach(function (element) {
        element.addEventListener('click', function () {
            toggleMenu(false);
        });
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            toggleMenu(false);
        }
    });

    // Syntax highlighting, the code stays readable as plain text when the library is not available
    if (window.hljs) {
        window.hljs.highlightAll();
    }
})();
