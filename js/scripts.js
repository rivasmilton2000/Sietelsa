/*!
* Start Bootstrap - Agency v7.0.12 (https://startbootstrap.com/theme/agency)
* Copyright 2013-2023 Start Bootstrap
* Licensed under MIT (https://github.com/StartBootstrap/startbootstrap-agency/blob/master/LICENSE)
*/
//
// Scripts
// 

window.addEventListener('DOMContentLoaded', event => {

    // Navbar shrink function
    var navbarShrink = function () {
        const navbarCollapsible = document.body.querySelector('#mainNav');
        if (!navbarCollapsible) {
            return;
        }
        if (window.scrollY === 0) {
            navbarCollapsible.classList.remove('navbar-shrink')
        } else {
            navbarCollapsible.classList.add('navbar-shrink')
        }

    };

    // Shrink the navbar 
    navbarShrink();

    // Shrink the navbar when page is scrolled
    document.addEventListener('scroll', navbarShrink);

    const mainNav = document.body.querySelector('#mainNav');

    // Keep active nav item highlighted while scrolling
    const sectionNavLinks = [].slice.call(
        document.querySelectorAll('#navbarResponsive .nav-link[href^="#"]')
    );
    const sectionTargets = sectionNavLinks
        .map((link) => {
            const hash = link.getAttribute('href');
            if (!hash || hash.length <= 1) {
                return null;
            }
            const id = hash.substring(1);
            const section = document.getElementById(id);
            if (!section) {
                return null;
            }
            return { id, link, section };
        })
        .filter((target) => target !== null);

    const setCurrentNavSection = function (currentId) {
        sectionNavLinks.forEach((link) => {
            const hash = link.getAttribute('href') || '';
            const id = hash.startsWith('#') ? hash.substring(1) : '';
            const isCurrent = id !== '' && id === currentId;
            link.classList.toggle('active', isCurrent);
            link.classList.toggle('is-current', isCurrent);
            if (isCurrent) {
                link.setAttribute('aria-current', 'page');
            } else {
                link.removeAttribute('aria-current');
            }
        });
    };

    const getCurrentSectionId = function () {
        if (sectionTargets.length === 0) {
            return '';
        }

        const navHeight = mainNav ? mainNav.offsetHeight : 0;
        const markerY = navHeight + 140;
        let currentId = sectionTargets[0].id;

        sectionTargets.forEach((target) => {
            const rect = target.section.getBoundingClientRect();
            if (rect.top <= markerY) {
                currentId = target.id;
            }
        });

        const nearBottom = window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 2;
        if (nearBottom) {
            currentId = sectionTargets[sectionTargets.length - 1].id;
        }

        return currentId;
    };

    const syncNavHighlight = function () {
        if (sectionTargets.length === 0) {
            return;
        }

        const currentId = getCurrentSectionId();

        if (currentId !== '') {
            setCurrentNavSection(currentId);
        }
    };

    syncNavHighlight();
    document.addEventListener('scroll', syncNavHighlight, { passive: true });
    window.addEventListener('resize', syncNavHighlight);
    window.addEventListener('hashchange', syncNavHighlight);
    sectionNavLinks.forEach((link) => {
        link.addEventListener('click', () => {
            const hash = link.getAttribute('href') || '';
            if (hash.startsWith('#') && hash.length > 1) {
                setCurrentNavSection(hash.substring(1));
            }
        });
    });

    // Collapse responsive navbar when toggler is visible
    const navbarToggler = document.body.querySelector('.navbar-toggler');
    const responsiveNavItems = [].slice.call(
        document.querySelectorAll('#navbarResponsive .nav-link')
    );
    responsiveNavItems.map(function (responsiveNavItem) {
        responsiveNavItem.addEventListener('click', () => {
            if (window.getComputedStyle(navbarToggler).display !== 'none') {
                navbarToggler.click();
            }
        });
    });

});
