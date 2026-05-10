/**
 * Theme values for JS, read from :root in src/scss/_variables.scss.
 * `prop` = custom property name; `fallback` = default if CSS is missing (keep in sync with SCSS).
 * `parse: 'unit'` → number. `parse: 'string'` → raw trim.
 */
const ROOT_VAR_MAP = [
  {
    key: 'breakpointXS',
    prop: '--fs-breakpoint-xs',
    parse: 'unit',
    fallback: 400
  },
  {
    key: 'breakpointS',
    prop: '--fs-breakpoint-s',
    parse: 'unit',
    fallback: 600
  },
  {
    key: 'breakpointM',
    prop: '--fs-breakpoint-m',
    parse: 'unit',
    fallback: 900
  },
  {
    key: 'breakpointL',
    prop: '--fs-breakpoint-l',
    parse: 'unit',
    fallback: 1200
  },
  {
    key: 'breakpointXL',
    prop: '--fs-breakpoint-xl',
    parse: 'unit',
    fallback: 1400
  },
  {
    key: 'breakpointMobile',
    prop: '--fs-breakpoint-mobile',
    parse: 'unit',
    fallback: 900
  },
  {
    key: 'headerHeightScrolled',
    prop: '--fs-header-height-scrolled',
    parse: 'unit',
    fallback: 64
  },
  {
    key: 'startScrolled',
    prop: '--fs-header-height-scrolled',
    parse: 'unit',
    fallback: 64
  },
  {
    key: 'transitionSpeed',
    prop: '--fs-transition-speed',
    parse: 'unit',
    fallback: 240
  },
  {
    key: 'transitionSpeedSlow',
    prop: '--fs-transition-speed-slow',
    parse: 'unit',
    fallback: 360
  }
];

function parseUnit(raw) {
  const n = parseFloat(String(raw).trim());
  return Number.isFinite(n) ? n : 0;
}

function readRootVars() {
  const out = {};
  for (const row of ROOT_VAR_MAP) {
    out[row.key] = row.fallback;
  }
  if (typeof document === 'undefined') {
    return out;
  }
  const styles = getComputedStyle(document.documentElement);
  for (const row of ROOT_VAR_MAP) {
    const raw = styles.getPropertyValue(row.prop).trim();
    if (raw === '') {
      continue;
    }
    out[row.key] = row.parse === 'unit' ? parseUnit(raw) : raw;
  }
  return out;
}

const fromCss = readRootVars();

export default {
  // From CSS :root variables
  ...fromCss,

  // Default scroll offset
  scrollOffset: 8
};
