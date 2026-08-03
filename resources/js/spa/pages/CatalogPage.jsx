import React, { useState, useEffect, useCallback, useMemo } from 'react';
import { useSearchParams } from 'react-router-dom';
import { api } from '../lib/api';
import { formatNumber, spaLang, t, withLang } from '../lib/i18n';

const DEFAULT_SORT = 'popular';
// Page size is never hardcoded here: /api/v1/catalog-db applies the real
// `catalog_page_size` setting when no limit is sent, and returns the applied
// value plus the page count in `meta`. That is what drives the pagination
// maths, so the SPA can never drift from the Blade catalogue.
const PAGE_WINDOW = 2;
const MULTI_AXES = ['resource_type', 'category', 'fund', 'branch'];
const SINGLE_AXES = ['availability', 'format', 'language', 'udc'];

function readPositiveInt(value, fallback = 1) {
  const parsed = Number.parseInt(value ?? '', 10);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
}

function normalizeText(value, fallback = '') {
  if (typeof value !== 'string') return fallback;
  const normalized = value.trim();
  return normalized || fallback;
}

function parseList(value) {
  return String(value ?? '')
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean);
}

function humanize(value) {
  const raw = String(value ?? '').replace(/[_-]+/g, ' ').trim();
  return raw ? raw.charAt(0).toUpperCase() + raw.slice(1) : '';
}

/**
 * Localised label for a facet value. The option LIST always comes from the
 * facets endpoint; only the display label is looked up here, falling back to
 * the label the server sent (funds, branches, UDC) and then to the raw value,
 * so a newly catalogued value can never disappear from the sidebar.
 */
function optionLabel(namespace, value, serverLabel = '') {
  const key = `catalog.${namespace}.${value}`;
  const translated = t(key);
  if (translated !== key) return translated;

  return normalizeText(serverLabel) || humanize(value) || String(value);
}

function facetEntries(facets, key) {
  const entries = Array.isArray(facets?.[key]) ? facets[key] : [];

  return entries
    .filter((entry) => entry && entry.value !== null && entry.value !== undefined && String(entry.value) !== '')
    .map((entry) => ({
      value: String(entry.value),
      label: normalizeText(entry.label),
      count: Number.isFinite(Number(entry.count)) ? Number(entry.count) : 0,
    }));
}

/**
 * Windowed page list — first, last, and a span around the current page, with
 * gap markers in between. Designed for the ~800 page counts the MARC import
 * will produce.
 */
function buildPageWindow(current, totalPages, span = PAGE_WINDOW) {
  const pages = new Set([1, totalPages]);
  for (let offset = -span; offset <= span; offset += 1) {
    const candidate = current + offset;
    if (candidate >= 1 && candidate <= totalPages) pages.add(candidate);
  }

  const ordered = Array.from(pages).sort((left, right) => left - right);
  const items = [];
  let previous = 0;

  ordered.forEach((value) => {
    if (previous && value - previous > 1) {
      items.push({ type: 'gap', key: `gap-${previous}-${value}` });
    }
    items.push({ type: 'page', value, key: `page-${value}` });
    previous = value;
  });

  return items;
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

/**
 * Options for a single-value facet select. A zero-count value stays visible but
 * disabled, so the control never reshuffles as records are catalogued; the
 * currently selected value stays enabled so it can always be cleared.
 */
function FacetSelectOptions({ entries, namespace, allLabel, current }) {
  return (
    <>
      <option value="">{allLabel}</option>
      {entries.map((entry) => (
        <option
          key={entry.value}
          value={entry.value}
          disabled={entry.count <= 0 && entry.value !== current}
        >
          {`${optionLabel(namespace, entry.value, entry.label)} (${entry.count})`}
        </option>
      ))}
    </>
  );
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
  const resourceType = searchParams.get('resource_type') ?? '';
  const category = searchParams.get('category') ?? '';
  const fund = searchParams.get('fund') ?? '';
  const branch = searchParams.get('branch') ?? '';
  const availability = searchParams.get('availability') ?? '';
  const format = searchParams.get('format') ?? '';

  const [draftQuery, setDraftQuery] = useState(query);
  const [results, setResults] = useState([]);
  const [loading, setLoading] = useState(false);
  const [total, setTotal] = useState(0);
  const [perPage, setPerPage] = useState(0);
  const [totalPages, setTotalPages] = useState(1);
  const [facets, setFacets] = useState(null);
  const [facetsFailed, setFacetsFailed] = useState(false);

  useEffect(() => {
    setDraftQuery(query);
  }, [query]);

  // Live filter axes, counts included — the only source of filter options.
  useEffect(() => {
    let cancelled = false;

    api('/catalog-facets')
      .then((payload) => {
        if (cancelled) return;
        setFacets(payload?.data ?? null);
        setFacetsFailed(false);
      })
      .catch((err) => {
        if (cancelled) return;
        console.error('Catalog facets failed:', err);
        setFacets(null);
        setFacetsFailed(true);
      });

    return () => {
      cancelled = true;
    };
  }, []);

  const selectedByAxis = useMemo(() => ({
    resource_type: parseList(resourceType),
    category: parseList(category),
    fund: parseList(fund),
    branch: parseList(branch),
  }), [branch, category, fund, resourceType]);

  const multiFacetGroups = useMemo(() => ([
    {
      key: 'resource_type',
      title: t('catalog.resourceTypeLabel'),
      namespace: 'resourceTypes',
      entries: facetEntries(facets, 'resource_types'),
    },
    {
      key: 'category',
      title: t('catalog.categoryLabel'),
      namespace: 'categories',
      entries: facetEntries(facets, 'categories'),
    },
    {
      key: 'fund',
      title: t('catalog.fundLabel'),
      namespace: 'funds',
      entries: facetEntries(facets, 'funds'),
    },
    {
      key: 'branch',
      title: t('catalog.branchLabel'),
      namespace: 'branches',
      entries: facetEntries(facets, 'branches'),
    },
  ]), [facets]);

  const languageEntries = useMemo(() => facetEntries(facets, 'languages'), [facets]);
  const availabilityEntries = useMemo(() => facetEntries(facets, 'availability'), [facets]);
  const formatEntries = useMemo(() => facetEntries(facets, 'formats'), [facets]);
  const udcEntries = useMemo(() => facetEntries(facets, 'udc'), [facets]);

  // Year bounds are the real min/max publication years in the collection.
  const yearOptions = useMemo(() => {
    const min = Number.parseInt(facets?.years?.min ?? '', 10);
    const max = Number.parseInt(facets?.years?.max ?? '', 10);
    if (!Number.isFinite(min) || !Number.isFinite(max) || max < min) return [];

    const years = [];
    for (let year = max; year >= min; year -= 1) years.push(String(year));

    [yearFrom, yearTo].forEach((value) => {
      if (value && !years.includes(value)) years.push(value);
    });

    return years;
  }, [facets, yearFrom, yearTo]);

  const currentPage = Math.min(page, Math.max(totalPages, 1));
  const pageWindow = useMemo(
    () => buildPageWindow(currentPage, Math.max(totalPages, 1)),
    [currentPage, totalPages],
  );
  // `per_page` comes straight from the API response, so the range readout uses
  // the same page size the server applied.
  const rangeFrom = total > 0 && perPage > 0 && results.length > 0 ? ((currentPage - 1) * perPage) + 1 : 0;
  const rangeTo = rangeFrom > 0 ? Math.min((rangeFrom + results.length) - 1, total) : 0;

  const yearSummary = useMemo(() => {
    if (yearFrom && yearTo) return `${yearFrom}–${yearTo}`;
    if (yearFrom) return spaLang === 'en' ? `from ${yearFrom}` : spaLang === 'kk' ? `${yearFrom} жылдан` : `с ${yearFrom}`;
    if (yearTo) return spaLang === 'en' ? `until ${yearTo}` : spaLang === 'kk' ? `${yearTo} дейін` : `до ${yearTo}`;

    return '';
  }, [yearFrom, yearTo]);

  const activeFilterValues = useMemo(
    () => [query, sort !== DEFAULT_SORT, availableOnly, udc, language, yearFrom || yearTo,
      resourceType, category, fund, branch, availability, format],
    [availability, availableOnly, branch, category, format, fund, language, query, resourceType, sort, udc, yearFrom, yearTo],
  );
  const activeFilterCount = useMemo(
    () => activeFilterValues.filter(Boolean).length,
    [activeFilterValues],
  );
  const hasActiveFilters = activeFilterCount > 0 || page > 1;
  const loadingSkeletons = useMemo(() => Array.from({ length: 6 }, (_, index) => index), []);

  const summaryText = useMemo(() => {
    const axisSummary = (label, namespace, entries, values) => {
      if (values.length === 0) return '';
      const labels = values.map((value) => {
        const match = entries.find((entry) => entry.value === value);
        return optionLabel(namespace, value, match?.label);
      });

      return t('catalog.summaryAxis', { label, values: labels.join(', ') });
    };

    const segments = [
      query ? t('catalog.querySummary', { query }) : t('catalog.summaryAll'),
      axisSummary(t('catalog.resourceTypeLabel'), 'resourceTypes', multiFacetGroups[0].entries, selectedByAxis.resource_type),
      axisSummary(t('catalog.categoryLabel'), 'categories', multiFacetGroups[1].entries, selectedByAxis.category),
      axisSummary(t('catalog.fundLabel'), 'funds', multiFacetGroups[2].entries, selectedByAxis.fund),
      axisSummary(t('catalog.branchLabel'), 'branches', multiFacetGroups[3].entries, selectedByAxis.branch),
      axisSummary(t('catalog.availabilityLabel'), 'availabilityStates', availabilityEntries, availability ? [availability] : []),
      axisSummary(t('catalog.formatLabel'), 'formats', formatEntries, format ? [format] : []),
      udc ? t('catalog.summaryUdc', { udc }) : '',
      language ? t('catalog.summaryLanguage', { language: language.toUpperCase() }) : '',
      yearSummary ? t('catalog.summaryYears', { years: yearSummary }) : '',
      availableOnly ? t('catalog.summaryAvailable') : '',
    ];

    return segments.filter(Boolean).join(' · ');
  }, [availability, availabilityEntries, availableOnly, format, formatEntries, language, multiFacetGroups, query, selectedByAxis, udc, yearSummary]);

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

  // Every axis lives in the URL, so a filtered page is linkable and survives a
  // reload. No `limit` is sent: the server applies the shared page size.
  const apiQuery = useMemo(() => {
    const params = new URLSearchParams();
    if (query.trim()) params.set('q', query.trim());
    params.set('page', String(page));
    if (sort) params.set('sort', sort);
    if (availableOnly) params.set('available_only', '1');
    if (udc) params.set('udc', udc);
    if (language) params.set('language', language);
    if (yearFrom) params.set('year_from', yearFrom);
    if (yearTo) params.set('year_to', yearTo);
    if (resourceType) params.set('resource_type', resourceType);
    if (category) params.set('category', category);
    if (fund) params.set('fund', fund);
    if (branch) params.set('branch', branch);
    if (availability) params.set('availability', availability);
    if (format) params.set('format', format);

    return params.toString();
  }, [availability, availableOnly, branch, category, format, fund, language, page, query, resourceType, sort, udc, yearFrom, yearTo]);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);

    api(`/catalog-db?${apiQuery}`)
      .then((data) => {
        if (cancelled) return;
        const items = Array.isArray(data?.data) ? data.data : [];
        const meta = data?.meta ?? {};
        setResults(items);
        setTotal(Number(meta.total ?? 0));
        setPerPage(readPositiveInt(meta.per_page, items.length));
        setTotalPages(readPositiveInt(meta.total_pages ?? meta.totalPages, 1));
      })
      .catch((err) => {
        if (cancelled) return;
        console.error('Catalog search failed:', err);
        setResults([]);
        setTotal(0);
        setTotalPages(1);
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [apiQuery]);

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

  const handleUdcChange = (e) => {
    updateParams({ udc: e.target.value, page: 1 });
  };

  const handleAvailabilityStateChange = (e) => {
    updateParams({ availability: e.target.value, page: 1 });
  };

  const handleFormatChange = (e) => {
    updateParams({ format: e.target.value, page: 1 });
  };

  const handleYearFromChange = (e) => {
    updateParams({ year_from: e.target.value, page: 1 });
  };

  const handleYearToChange = (e) => {
    updateParams({ year_to: e.target.value, page: 1 });
  };

  const handleAvailableOnlyChange = (e) => {
    updateParams({ available_only: e.target.checked ? '1' : '', page: 1 });
  };

  const toggleFacetValue = useCallback((axis, value) => {
    const current = parseList(searchParams.get(axis));
    const next = current.includes(value)
      ? current.filter((item) => item !== value)
      : [...current, value];

    updateParams({ [axis]: next.join(','), page: 1 });
  }, [searchParams, updateParams]);

  const handlePageChange = (nextPage) => {
    updateParams({ page: nextPage });
  };

  const clearFilters = () => {
    setDraftQuery('');
    const next = new URLSearchParams(searchParams);
    ['q', 'sort', 'page', 'available_only', 'year_from', 'year_to', ...MULTI_AXES, ...SINGLE_AXES]
      .forEach((key) => next.delete(key));
    setSearchParams(next);
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
              <strong>{currentPage}/{Math.max(totalPages, 1)}</strong>
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
              <FacetSelectOptions
                entries={languageEntries}
                namespace="languages"
                allLabel={t('catalog.allLanguages')}
                current={language}
              />
            </select>
          </label>

          <label className="filter-select-wrap">
            <span>{t('catalog.availabilityLabel')}</span>
            <select className="filter-select" value={availability} onChange={handleAvailabilityStateChange}>
              <FacetSelectOptions
                entries={availabilityEntries}
                namespace="availabilityStates"
                allLabel={t('catalog.anyAvailability')}
                current={availability}
              />
            </select>
          </label>

          <label className="filter-select-wrap">
            <span>{t('catalog.formatLabel')}</span>
            <select className="filter-select" value={format} onChange={handleFormatChange}>
              <FacetSelectOptions
                entries={formatEntries}
                namespace="formats"
                allLabel={t('catalog.anyFormat')}
                current={format}
              />
            </select>
          </label>

          <label className="filter-select-wrap">
            <span>{t('catalog.udcFilter')}</span>
            <select className="filter-select" value={udc} onChange={handleUdcChange}>
              <FacetSelectOptions
                entries={udcEntries}
                namespace="udcClasses"
                allLabel={t('catalog.anyUdc')}
                current={udc}
              />
            </select>
          </label>

          <label className="filter-select-wrap">
            <span>{t('catalog.yearFromLabel')}</span>
            <select className="filter-select" value={yearFrom} onChange={handleYearFromChange}>
              <option value="">{t('catalog.anyYear')}</option>
              {yearOptions.map((year) => (
                <option key={`from-${year}`} value={year}>{year}</option>
              ))}
            </select>
          </label>

          <label className="filter-select-wrap">
            <span>{t('catalog.yearToLabel')}</span>
            <select className="filter-select" value={yearTo} onChange={handleYearToChange}>
              <option value="">{t('catalog.anyYear')}</option>
              {yearOptions.map((year) => (
                <option key={`to-${year}`} value={year}>{year}</option>
              ))}
            </select>
          </label>

          <label className="filter-checkbox">
            <input type="checkbox" checked={availableOnly} onChange={handleAvailableOnlyChange} />
            <span>{t('catalog.availableOnly')}</span>
          </label>
        </div>

        <div className="toolbar-actions">
          <div className="search-summary">{summaryText}</div>

          {hasActiveFilters && (
            <button type="button" className="clear-btn" onClick={clearFilters}>
              {t('catalog.reset')}
            </button>
          )}
        </div>
      </div>

      <section className="catalog-facets" aria-label={t('catalog.filtersTitle')}>
        <div className="catalog-facets-head">
          <span className="facet-panel-title">{t('catalog.filtersTitle')}</span>
          {facetsFailed && <span className="facet-note">{t('catalog.facetsUnavailable')}</span>}
        </div>

        <div className="facet-grid">
          {multiFacetGroups.map((group) => (
            <fieldset key={group.key} className="facet-group">
              <legend className="facet-title">{group.title}</legend>
              {group.entries.length === 0 ? (
                <p className="facet-empty">{t('catalog.facetEmpty')}</p>
              ) : (
                <div className="facet-options">
                  {group.entries.map((entry) => {
                    const selected = selectedByAxis[group.key].includes(entry.value);
                    const disabled = entry.count <= 0 && !selected;

                    return (
                      <label
                        key={entry.value}
                        className={`facet-option${selected ? ' facet-option--active' : ''}${disabled ? ' facet-option--disabled' : ''}`}
                      >
                        <input
                          type="checkbox"
                          checked={selected}
                          disabled={disabled}
                          onChange={() => toggleFacetValue(group.key, entry.value)}
                        />
                        <span className="facet-option-label">
                          {optionLabel(group.namespace, entry.value, entry.label)}
                        </span>
                        <span className="facet-count">{formatNumber(entry.count)}</span>
                      </label>
                    );
                  })}
                </div>
              )}
            </fieldset>
          ))}
        </div>
      </section>

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

      {totalPages > 1 && (
        <nav className="pagination" aria-label={t('catalog.paginationLabel')}>
          <button
            type="button"
            className="page-btn"
            disabled={currentPage <= 1}
            onClick={() => handlePageChange(currentPage - 1)}
          >
            {t('catalog.prev')}
          </button>

          <div className="pagination-pages">
            {pageWindow.map((item) => (item.type === 'gap' ? (
              <span key={item.key} className="page-ellipsis" aria-hidden="true">…</span>
            ) : (
              <button
                key={item.key}
                type="button"
                className={`page-btn page-btn--number${item.value === currentPage ? ' page-btn--active' : ''}`}
                aria-label={t('catalog.pageAria', { page: item.value })}
                aria-current={item.value === currentPage ? 'page' : undefined}
                onClick={() => handlePageChange(item.value)}
              >
                {item.value}
              </button>
            )))}
          </div>

          <span className="page-info">
            {t('catalog.pageOf', { page: currentPage, total: totalPages })}
            {rangeTo > 0
              ? ` · ${t('catalog.rangeSummary', { from: rangeFrom, to: rangeTo, total: formatNumber(total) })}`
              : ''}
          </span>

          <button
            type="button"
            className="page-btn"
            disabled={currentPage >= totalPages}
            onClick={() => handlePageChange(currentPage + 1)}
          >
            {t('catalog.next')}
          </button>
        </nav>
      )}
    </div>
  );
}
