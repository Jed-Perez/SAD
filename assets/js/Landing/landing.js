'use strict';

// Preserve bookmarks to portal pages from before the public homepage existed.
const portalRoute = location.hash.slice(1).split('/')[0];
if (new Set(['dashboard', 'inventory', 'customers', 'users', 'services', 'schedule', 'messages', 'planner', 'reports', 'settings']).has(portalRoute)) {
    location.replace('portal.php' + location.hash);
}

const menuButton = document.getElementById('landing-menu-button');
const menuLinks = document.getElementById('landing-links');
menuButton.addEventListener('click', () => {
    const open = menuLinks.classList.toggle('show');
    menuButton.setAttribute('aria-expanded', String(open));
    menuButton.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
});
menuLinks.querySelectorAll('a').forEach(link => link.addEventListener('click', () => {
    menuLinks.classList.remove('show');
    menuButton.setAttribute('aria-expanded', 'false');
    menuButton.setAttribute('aria-label', 'Open menu');
}));
