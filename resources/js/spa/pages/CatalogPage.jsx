import React, { useState, useEffect, useCallback, useMemo } from 'react';
import { useSearchParams } from 'react-router-dom';
import { api } from '../lib/api';
import { formatNumber, spaLang, t, withLang } from '../lib/i18n';

const PAGE_SIZE = 20;
const DEFAULT_SORT = 'popular';
const LANGUAGE_OPTIONS = [
  { value: '', label: t('catalog.allLanguages') },
  { value: 'ru', label: 'Русский' },
  { value: 'kk', label: 'Қазақша' },
  { value: 'en', label: 'English' },
];
const YEAR_PRESET_OPTIONS = [
  { value: '', label: t('catalog.allYears') },
  { value: 'recent', label: '2024–2026' },
  { value: 'modern', label: '2020–2023' },
  { value: 'classic', label: t('catalog.before2019') },
];

function readPositiveInt(value, fallback = 1) {
  const parsed = Number.parseInt(value ?? '', 10);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
}

function normalizeText(value, fallback = '') {
  if (typeof value !== 'string') return fallback;
  const normalized = value.trim();
  return normalized || fallback;
}

function firstMeaningfulClassification(classification) {
  if (!Array.isArray(classification)) return '';
  const item = classification.find((entry) => normalizeText(entry?.label));
  return normalizeText(item?.label);
}

function primaryLocationLabel(locations) {
  if (!Array.isArray(locations) || locations.length === 0) return '';
  return normalizeText(locations[0]?.servicePoint?.name)
    || normalizeText(locations[0]?.campus?.name)
    || '';
}

function availabilityCopy(doc) {
  const availableCopies = Number(doc?.copies?.available ?? NaN);
  const totalCopies = Number(doc?.copies?.total ?? NaN);

  if (Number.isFinite(totalCopies) && totalCopies > 0 && Number.isFinite(availableCopies)) {
    if (availableCopies > 0 && availableCopies < totalCopies) {
      return t('catalog.availabilityPartial', { available: availableCopies, total: totalCopies });
    }

    if (availableCopies <= 0) {
      return t('catalog.availabilityCheckedOut', { total: totalCopies });
    }

    return t('catalog.copiesAvailable', { count: availableCopies });
  }

  return t('catalog.availabilityUnknown');
}

function accessCopy(doc) {
  const availableCopies = Number(doc?.copies?.available ?? NaN);
  const totalCopies = Number(doc?.copies?.total ?? NaN);
  const hasPhysicalHoldings = (Number.isFinite(totalCopies) && totalCopies > 0)
    || (Array.isArray(doc?.availability?.locations) && doc.availability.locations.length > 0);

  if (!hasPhysicalHoldings) {
    return t('catalog.accessPending');
  }

  if (Number.isFinite(availableCopies) && availableCopies > 0) {
    return t('catalog.accessPhysicalAvailable');
  }

  if (Number.isFinite(totalCopies) && totalCopies > 0) {
    return t('catalog.accessPhysicalUnavailable');
  }

  return t('catalog.accessPhysicalOnly');
}

export function CatalogPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const query = searchParams.get('q') ?? '';
  const sort = searchParams.get('sort') ?? DEFAULT_SORT;
  const page = readPositiveInt(searchParams.get('page'), 1);
  const availableOnly = searchParams.get('available_only') === '1';
  const udc = searchParams.get('udc') ?? '';
  const language = searchParams.get('language') ?? '';
  const yearFrom = searchParams.get('year_from') ?? '';
  const yearTo = searchParams.get('year_to') ?? '';

  const [draftQuery, setDraftQuery] = useState(query);
  const [results, setResults] = useState([]);
  const [loading, setLoading] = useState(false);
  const [total, setTotal] = useState(0);

  useEffect(() => {
    setDraftQuery(query);
  }, [query]);

  const totalPages = useMemo(() => Math.max(1, Math.ceil(total / PAGE_SIZE)), [total]);
  const yearPreset = useMemo(() => {
    if (yearFrom === '2024' && yearTo === '2026') return 'recent';
    if (yearFrom === '2020' && yearTo === '2023') return 'modern';
    if (yearTo === '2019' && !yearFrom) return 'classic';

    return '';
  }, [yearFrom, yearTo]);
  const yearSummary = useMemo(() => {
    if (yearFrom && yearTo) return `${yearFrom}–${yearTo}`;
    if (yearFrom) return spaLang === 'en' ? `from ${yearFrom}` : spaLang === 'kk' ? `${yearFrom} жылдан` : `с ${yearFrom}`;
    if (yearTo) return spaLang === 'en' ? `until ${yearTo}` : spaLang === 'kk' ? `${yearTo} дейін` : `до ${yearTo}`;

    return '';
  }, [yearFrom, yearTo]);
  const hasActiveFilters = query || sort !== DEFAULT_SORT || availableOnly || udc || language || yearFrom || yearTo || page > 1;
  const activeFilterCount = useMemo(
    () => [query, sort !== DEFAULT_SORT, availableOnly, udc, language, yearFrom || yearTo].filter(Boolean).length,
    [availableOnly, language, query, sort, udc, yearFrom, yearTo],
  );
  const loadingSkeletons = useMemo(() => Array.from({ length: 6 }, (_, index) => index), []);

  const updateParams = useCallback((updates) => {
    const next = new URLSearchParams(searchParams);

    Object.entries(updates).forEach(([key, value]) => {
      const shouldClear = value === undefined
        || value === null
        || value === ''
        || value === false
        || (key === 'page' && Number(value) <= 1)
        || (key === 'sort' && value === DEFAULT_SORT);

      if (shouldClear) {
        next.delete(key);
      } else {
        next.set(key, String(value));
      }
    });

    setSearchParams(next);
  }, [searchParams, setSearchParams]);

  const search = useCallback(async ({ q, currentPage, currentSort, onlyAvailable, currentUdc, currentLanguage, currentYearFrom, currentYearTo }) => {
    setLoading(true);
    try {
      const params = new URLSearchParams();
      if (q.trim()) params.set('q', q.trim());
      params.set('page', String(currentPage));
      params.set('limit', String(PAGE_SIZE));
      if (currentSort) params.set('sort', currentSort);
      if (onlyAvailable) params.set('available_only', '1');
      if (currentUdc) params.set('udc', currentUdc);
      if (currentLanguage) params.set('language', currentLanguage);
      if (currentYearFrom) params.set('year_from', currentYearFrom);
      if (currentYearTo) params.set('year_to', currentYearTo);

      const data = await api(`/catalog-db?${params}`);
      const items = Array.isArray(data?.data) ? data.data : [];
      setResults(items);
      setTotal(Number(data?.meta?.total ?? 0));
    } catch (err) {
      console.error('Catalog search failed:', err);
      setResults([]);
      setTotal(0);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    search({
      q: query,
      currentPage: page,
      currentSort: sort,
      onlyAvailable: availableOnly,
      currentUdc: udc,
      currentLanguage: language,
      currentYearFrom: yearFrom,
      currentYearTo: yearTo,
    });
  }, [availableOnly, language, page, query, search, sort, udc, yearFrom, yearTo]);

  const handleSearch = (e) => {
    e.preventDefault();
    updateParams({ q: draftQuery.trim(), page: 1 });
  };

  const handleSortChange = (e) => {
    updateParams({ sort: e.target.value, page: 1 });
  };

  const handleLanguageChange = (e) => {
    updateParams({ language: e.target.value, page: 1 });
  };

  const handleYearPresetChange = (e) => {
    const nextPreset = e.target.value;

    if (nextPreset === 'recent') {
      updateParams({ year_from: '2024', year_to: '2026', page: 1 });
      return;
    }

    if (nextPreset === 'modern') {
      updateParams({ year_from: '2020', year_to: '2023', page: 1 });
      return;
    }

    if (nextPreset === 'classic') {
      updateParams({ year_from: '', year_to: '2019', page: 1 });
      return;
    }

    updateParams({ year_from: '', year_to: '', page: 1 });
  };

  const handleAvailabilityChange = (e) => {
    updateParams({ available_only: e.target.checked ? '1' : '', page: 1 });
  };

  const handlePageChange = (nextPage) => {
    updateParams({ page: nextPage });
  };

  const clearFilters = () => {
    setDraftQuery('');
    setSearchParams({});
  };

  return (
    <div className="page-catalog">
      <section className="catalog-overview">
        <div className="catalog-hero-card">
          <span className="catalog-kicker">{t('catalog.kicker')}</span>
          <h1 className="page-title">{t('catalog.title')}</h1>
          <p className="page-subtitle">
            {total > 0
              ? t('catalog.subtitleResults', { count: formatNumber(total) })
              : t('catalog.subtitleEmpty')}
          </p>
          <div className="catalog-tag-row">
            <span className="catalog-tag">{t('catalog.tagFormat')}</span>
            <span className="catalog-tag">{t('catalog.tagAvailability')}</span>
            <span className="catalog-tag">{t('catalog.tagDetail')}</span>
          </div>
        </div>

        <aside className="catalog-insight-card">
          <div className="insight-block">
            <span className="insight-label">{t('catalog.found')}</span>
            <strong className="insight-value">{total > 0 ? formatNumber(total) : '—'}</strong>
          </div>
          <div className="insight-grid">
            <div className="insight-mini">
              <span>{t('catalog.filters')}</span>
              <strong>{activeFilterCount}</strong>
            </div>
            <div className="insight-mini">
              <span>{t('catalog.page')}</span>
              <strong>{Math.min(page, totalPages)}/{totalPages}</strong>
            </div>
          </div>
          <p className="insight-copy">
            {query ? t('catalog.focusQuery', { query }) : t('catalog.focusDefault')}
          </p>
        </aside>
      </section>

      <form className="search-bar" onSubmit={handleSearch}>
        <input
          type="text"
          className="search-input"
          placeholder={t('catalog.searchPlaceholder')}
          value={draftQuery}
          onChange={(e) => setDraftQuery(e.target.value)}
        />
        <button type="submit" className="search-btn" disabled={loading}>
          {loading ? '⏳' : t('catalog.search')}
        </button>
      </form>

      <div className="catalog-toolbar">
        <div className="catalog-filters">
          <label className="filter-select-wrap">
            <span>{t('catalog.sorting')}</span>
            <select className="filter-select" value={sort} onChange={handleSortChange}>
              <option value="popular">{t('catalog.sortPopular')}</option>
              <option value="newest">{t('catalog.sortNewest')}</option>
              <option value="title">{t('catalog.sortTitle')}</option>
              <option value="author">{t('catalog.sortAuthor')}</option>
            </select>
          </label>

          <label className="filter-select-wrap">
            <span>{t('catalog.language')}</span>
            <select className="filter-select" value={language} onChange={handleLanguageChange}>
              {LANGUAGE_OPTIONS.map((option) => (
                <option key={option.value || 'all'} value={option.value}>{option.label}</option>
              ))}
            </select>
          </label>

          <label className="filter-select-wrap">
            <span>{t('catalog.year')}</span>
            <select className="filter-select" value={yearPreset} onChange={handleYearPresetChange}>
              {YEAR_PRESET_OPTIONS.map((option) => (
                <option key={option.value || 'all-years'} value={option.value}>{option.label}</option>
              ))}
            </select>
          </label>

          <label className="filter-checkbox">
            <input type="checkbox" checked={availableOnly} onChange={handleAvailabilityChange} />
            <span>{t('catalog.availableOnly')}</span>
          </label>
        </div>

        <div className="toolbar-actions">
          <div className="search-summary">
            {query ? t('catalog.querySummary', { query }) : t('catalog.summaryAll')}
            {udc ? ` · ${t('catalog.summaryUdc', { udc })}` : ''}
            {language ? ` · ${t('catalog.summaryLanguage', { language: language.toUpperCase() })}` : ''}
            {yearSummary ? ` · ${t('catalog.summaryYears', { years: yearSummary })}` : ''}
            {availableOnly ? ` · ${t('catalog.summaryAvailable')}` : ''}
          </div>

          {hasActiveFilters && (
            <button type="button" className="clear-btn" onClick={clearFilters}>
              {t('catalog.reset')}
            </button>
          )}
        </div>
      </div>

      {loading && results.length > 0 && (
        <div className="loading-inline">{t('catalog.refreshing')}</div>
      )}

      {loading && results.length === 0 ? (
        <div className="results-grid results-grid--skeleton" aria-hidden="true">
          {loadingSkeletons.map((index) => (
            <div key={index} className="result-card result-card--skeleton">
              <div className="skeleton-pill" />
              <div className="skeleton-line skeleton-line--title" />
              <div className="skeleton-line" />
              <div className="skeleton-line skeleton-line--short" />
            </div>
          ))}
        </div>
      ) : (
        <div className="results-grid">
          {results.map((doc) => {
            const identifier = doc?.isbn?.raw || doc?.id;
            const title = doc?.title?.display || doc?.title?.raw || t('catalog.untitled');
            const subtitle = doc?.title?.subtitle;
            const primaryAuthor = normalizeText(doc?.primaryAuthor, t('catalog.unknownAuthor'));
            const publisherName = normalizeText(doc?.publisher?.name);
            const publicationYear = doc?.publicationYear;
            const languageCode = doc?.language?.code;
            const languageRaw = normalizeText(doc?.language?.raw);
            const rawIsbn = doc?.isbn?.raw;
            const availableCopies = Number(doc?.copies?.available ?? NaN);
            const totalCopies = Number(doc?.copies?.total ?? NaN);
            const locations = Array.isArray(doc?.availability?.locations) ? doc.availability.locations : [];
            const primaryLocation = primaryLocationLabel(locations);
            const profileLabel = firstMeaningfulClassification(doc?.classification);
            const udcLabel = normalizeText(doc?.udc?.raw, '');
            const holdingLabel = totalCopies > 0 || locations.length > 0 ? t('catalog.physicalHolding') : t('catalog.holdingDataPending');

            return (
              <a
                key={doc?.id || identifier}
                href={identifier ? withLang(`/book/${encodeURIComponent(identifier)}`) : withLang('/catalog')}
                className="result-card"
              >
                <div className="card-topline">
                  <span className={`card-badge ${availableCopies > 0 ? 'card-badge--available' : 'card-badge--muted'}`}>
                    {availableCopies > 0 ? t('catalog.available') : holdingLabel}
                  </span>
                  {languageCode && <span className="card-badge card-badge--muted">{languageCode.toUpperCase()}</span>}
                </div>
                <div className="card-title">{title}</div>
                {subtitle && <div className="card-subtitle">{subtitle}</div>}
                <div className="card-author">{primaryAuthor}</div>
                <div className="card-meta">
                  {publicationYear && <span className="meta-year">{publicationYear}</span>}
                  {publisherName && <span>{publisherName}</span>}
                  {languageRaw && !languageCode && <span>{languageRaw}</span>}
                  {rawIsbn && <span className="meta-isbn">ISBN: {rawIsbn}</span>}
                </div>
                <div className="card-detail-row">
                  <span className={`card-udc ${udcLabel ? '' : 'card-udc--missing'}`}>
                    {udcLabel ? `UDC ${udcLabel}` : t('catalog.udcPending')}
                  </span>
                  <span className="card-note">
                    {profileLabel ? t('catalog.subjectLabel', { label: profileLabel }) : t('catalog.subjectPending')}
                  </span>
                </div>
                <div className="card-note card-note--location">
                  {primaryLocation ? t('catalog.locationLabel', { location: primaryLocation }) : t('catalog.locationPending')}
                </div>
                <div className={`card-availability ${availableCopies > 0 ? 'available' : 'unavailable'}`}>
                  {availabilityCopy(doc)}
                </div>
                <div className="card-access-copy">{accessCopy(doc)}</div>
                <div className="card-link-hint">{t('catalog.openCard')}</div>
              </a>
            );
          })}
        </div>
      )}

      {results.length === 0 && !loading && (
        <div className="empty-state">
          <div className="empty-state-icon">🔎</div>
          <strong>{query ? t('catalog.emptyQuery') : t('catalog.emptyStart')}</strong>
          <p>
            {query ? t('catalog.emptyQueryBody') : t('catalog.emptyStartBody')}
          </p>
        </div>
      )}

      {total > PAGE_SIZE && (
        <div className="pagination">
          <button
            className="page-btn"
            disabled={page <= 1}
            onClick={() => handlePageChange(page - 1)}
          >
            {t('catalog.prev')}
          </button>
          <span className="page-info">
            {t('catalog.pageOf', { page: Math.min(page, totalPages), total: totalPages })}
          </span>
          <button
            className="page-btn"
            disabled={page >= totalPages}
            onClick={() => handlePageChange(page + 1)}
          >
            {t('catalog.next')}
          </button>
        </div>
      )}
    </div>
  );
}
