import React from 'react';
import { Link } from 'react-router-dom';
import { t, withLang } from '../lib/i18n';

export function NotFoundPage() {
  return (
    <div className="page-notfound">
      <div className="notfound-card">
        <div className="notfound-code">404</div>
        <p className="catalog-kicker">{t('notFound.kicker')}</p>
        <h1>{t('notFound.title')}</h1>
        <p>{t('notFound.body')}</p>
        <div className="notfound-actions">
          <Link to={withLang('/catalog')} className="notfound-link">
            {t('notFound.openCatalog')}
          </Link>
          <a href={withLang('/')} className="notfound-link notfound-link--ghost">
            {t('notFound.mainSite')}
          </a>
        </div>
      </div>
    </div>
  );
}
