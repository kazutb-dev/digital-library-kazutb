import React from 'react';
import { NavLink, Outlet } from 'react-router-dom';
import { t, withLang } from '../lib/i18n';

const NAV_ITEMS = [
  { to: '/catalog', labelKey: 'layout.navCatalog', icon: '▣', descriptionKey: 'layout.navCatalogDesc' },
];

const QUICK_LINKS = [
  { href: '/catalog', labelKey: 'layout.quickCatalog' },
  { href: '/shortlist', labelKey: 'layout.quickShortlist' },
  { href: '/resources', labelKey: 'layout.quickResources' },
];

export function AppLayout() {
  return (
    <div className="app-shell">
      <aside className="app-sidebar">
        <div className="sidebar-header">
          <a href={withLang('/')} className="sidebar-brand" aria-label={t('layout.backToSiteAria')}>
            <span className="brand-icon">DL</span>
            <span className="brand-text">
              The Academic Curator
              <small>Digital Library</small>
            </span>
          </a>
          <p className="sidebar-copy">{t('layout.sidebarCopy')}</p>
          <div className="sidebar-status">{t('layout.sidebarStatus')}</div>
        </div>

        <nav className="sidebar-nav" aria-label={t('layout.title')}>
          {NAV_ITEMS.map(({ to, labelKey, icon, descriptionKey }) => (
            <NavLink
              key={to}
              to={withLang(to)}
              className={({ isActive }) =>
                `nav-link${isActive ? ' nav-link--active' : ''}`
              }
            >
              <span className="nav-icon">{icon}</span>
              <span className="nav-copy">
                <span className="nav-label">{t(labelKey)}</span>
                <span className="nav-description">{t(descriptionKey)}</span>
              </span>
            </NavLink>
          ))}
        </nav>

        <div className="sidebar-panel">
          <div className="sidebar-panel-title">{t('layout.quickLinks')}</div>
          <div className="sidebar-shortcuts">
            {QUICK_LINKS.map(({ href, labelKey }) => (
              <a key={href} href={withLang(href)} className="sidebar-shortcut">
                {t(labelKey)}
              </a>
            ))}
          </div>
        </div>

        <div className="sidebar-footer">
          <a href={withLang('/')} className="nav-link nav-link--back">
            {t('layout.backToSite')}
          </a>
        </div>
      </aside>

      <main className="app-main">
        <div className="app-topbar">
          <div>
            <div className="app-topbar-kicker">{t('layout.kicker')}</div>
            <div className="app-topbar-title">{t('layout.title')}</div>
          </div>
          <div className="app-topbar-actions">
            <a href={withLang('/account')} className="topbar-chip">{t('layout.account')}</a>
            <a href={withLang('/resources')} className="topbar-chip">{t('layout.resources')}</a>
          </div>
        </div>
        <Outlet />
      </main>
    </div>
  );
}
