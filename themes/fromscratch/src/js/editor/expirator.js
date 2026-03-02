(function () {
  'use strict';

  const wp = typeof window !== 'undefined' ? window.wp : null;
  if (
    !wp ||
    typeof fromscratchFeatures === 'undefined' ||
    !fromscratchFeatures.post_expirator
  ) {
    return;
  }

  const el = wp.element.createElement;
  const { useState } = wp.element;
  const { registerPlugin } = wp.plugins;
  const { PluginDocumentSettingPanel } = wp.editPost;
  const { useSelect } = wp.data;
  const { useEntityProp } = wp.coreData;
  const { PanelRow, DateTimePicker, SelectControl, TextControl } =
    wp.components;
  const wpDate = wp.date;

  const META_KEY_DATE = '_fs_expiration_date';
  const META_KEY_ENABLED = '_fs_expiration_enabled';
  const META_KEY_ACTION = '_fs_expiration_action';
  const META_KEY_REDIRECT = '_fs_expiration_redirect_url';
  const labels =
    typeof fromscratchExpirator !== 'undefined' ? fromscratchExpirator : {};
  const timezone = labels.timezone || '';
  // Match WordPress publish date. wp_localize_script can output booleans as "1"/"0", so accept both.
  const is12Hour =
    labels.is12Hour !== false &&
    labels.is12Hour !== '0' &&
    labels.is12Hour !== 0;
  const amLabel = labels.amLabel || 'am';
  const pmLabel = labels.pmLabel || 'pm';

  /** Stored value "Y-m-d H:i" -> { date: "YYYY-MM-DD", time: "HH:mm" (24h) }. */
  function storedToParts(stored) {
    if (!stored || typeof stored !== 'string') return { date: '', time: '' };
    const trimmed = stored.trim();
    if (trimmed.length < 16) return { date: '', time: '' };
    const datePart = trimmed.substring(0, 10);
    const timePart = trimmed.substring(11, 16);
    return {
      date: /^\d{4}-\d{2}-\d{2}$/.test(datePart) ? datePart : '',
      time: /^\d{2}:\d{2}$/.test(timePart) ? timePart : ''
    };
  }

  /** Convert 24h "HH:mm" to 12h display "h:mm am/pm". */
  function time24ToDisplay(time24, am, pm) {
    if (!time24 || !/^\d{2}:\d{2}$/.test(time24)) return '';
    const h = parseInt(time24.substring(0, 2), 10);
    const i = time24.substring(3, 5);
    const hour12 = h % 12 || 12;
    const suffix = h < 12 ? am || 'am' : pm || 'pm';
    return hour12 + ':' + i + ' ' + suffix;
  }

  /** Normalize 24h "H:mm" or "HH:mm" to "HH:mm", or return empty if invalid. */
  function normalizeTime24(str) {
    if (!str || typeof str !== 'string') return '';
    const t = str.trim();
    const m = t.match(/^(\d{1,2}):(\d{2})$/);
    if (!m) return '';
    const h = Math.max(0, Math.min(23, parseInt(m[1], 10)));
    const i = Math.max(0, Math.min(59, parseInt(m[2], 10)));
    return String(h).padStart(2, '0') + ':' + String(i).padStart(2, '0');
  }

  /** Parse user time input to 24h "HH:mm". Handles 24h (HH:mm) and 12h (h:mm am/pm) when is12. */
  function parseTimeForStorage(str, is12) {
    if (!str || typeof str !== 'string') return '';
    const t = str.trim();
    if (t === '') return '';
    if (normalizeTime24(t)) return normalizeTime24(t);
    if (!is12) return '';
    const m = t.match(/^(\d{1,2}):(\d{2})\s*(.+)$/);
    if (!m) return '';
    let h = parseInt(m[1], 10);
    const i = Math.max(0, Math.min(59, parseInt(m[2], 10)));
    const suffix = (m[3] || '').trim();
    const isPm = pmLabel && suffix.toLowerCase() === pmLabel.toLowerCase();
    const isAm = amLabel && suffix.toLowerCase() === amLabel.toLowerCase();
    if (!isAm && !isPm) return '';
    if (h < 1 || h > 12) return '';
    if (h === 12) h = isPm ? 12 : 0;
    else if (isPm) h += 12;
    return String(h).padStart(2, '0') + ':' + String(i).padStart(2, '0');
  }

  /** Build stored "Y-m-d H:i" from date + time (time defaults to "00:00" if date set). */
  function partsToStored(dateVal, timeVal) {
    const date = dateVal && typeof dateVal === 'string' ? dateVal.trim() : '';
    const time = parseTimeForStorage(timeVal, is12Hour);
    if (date === '') return '';
    if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) return '';
    return time ? date + ' ' + time : date + ' 00:00';
  }

  /** Current date/time in site timezone as stored "Y-m-d H:i". */
  function getNowForStorage() {
    return formatForStorage(new Date());
  }

  /** Format stored "Y-m-d H:i" for preview using WordPress date/time format (Settings → General). */
  function formatStoredForDisplay(stored) {
    const dateObj = parseStoredDate(stored);
    if (!dateObj) return '';
    const df = labels.dateFormat || 'F j, Y';
    const tf = labels.timeFormat || 'g:i a';
    const format = df + ' ' + tf;
    if (wpDate) {
      try {
        if (typeof wpDate.dateI18n === 'function') {
          return wpDate.dateI18n(format, dateObj, timezone || undefined);
        }
        if (typeof wpDate.date === 'function') {
          return wpDate.date(format, dateObj, timezone || undefined);
        }
      } catch (e) {
        /* fall through to fallback */
      }
    }
    const { date: datePart, time: timePart } = storedToParts(stored);
    if (!datePart) return '';
    const months =
      labels.monthNames && Array.isArray(labels.monthNames)
        ? labels.monthNames
        : [];
    const [y, m, d] = datePart.split('-').map(Number);
    const monthStr =
      m >= 1 && m <= 12 && months[m - 1] ? months[m - 1] : String(m);
    const dateStr = monthStr + ' ' + d + ', ' + y;
    const timeStr = timePart
      ? is12Hour
        ? time24ToDisplay(timePart, amLabel, pmLabel)
        : timePart
      : '';
    return timeStr ? dateStr + ', ' + timeStr : dateStr;
  }

  function parseStoredDate(value) {
    if (!value || typeof value !== 'string') return null;
    const trimmed = value.trim();
    if (trimmed.length < 16) return null;
    if (wpDate?.getDate) {
      try {
        return wpDate.getDate(value, timezone || undefined);
      } catch (e) {
        // fall through to simple parse
      }
    }
    const withT = trimmed.replace(' ', 'T').substring(0, 16) + ':00';
    return new Date(withT);
  }

  function formatForStorage(date) {
    if (!date || !(date instanceof Date) || isNaN(date.getTime())) return '';
    if (wpDate?.date) {
      try {
        return wpDate.date('Y-m-d H:i', date, timezone || undefined);
      } catch (e) {
        // fall through
      }
    }
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    const h = String(date.getHours()).padStart(2, '0');
    const i = String(date.getMinutes()).padStart(2, '0');
    return y + '-' + m + '-' + d + ' ' + h + ':' + i;
  }

  function ExpiratorPanelContent() {
    const postType = useSelect(function (select) {
      return select('core/editor')?.getCurrentPostType?.() || '';
    }, []);
    const postId = useSelect(function (select) {
      return select('core/editor')?.getCurrentPostId?.();
    }, []);

    const allowed =
      labels.postTypes && Array.isArray(labels.postTypes)
        ? labels.postTypes
        : ['post', 'page'];
    if (!postType || allowed.indexOf(postType) === -1 || !postId) {
      return null;
    }

    const [meta, setMeta] = useEntityProp('postType', postType, 'meta', postId);
    if (!meta || typeof setMeta !== 'function') {
      return null;
    }

    const rawValue = meta[META_KEY_DATE] || '';
    const isEnabled = meta[META_KEY_ENABLED] === '1';
    const actionValue = meta[META_KEY_ACTION] || 'draft';
    const redirectValue = meta[META_KEY_REDIRECT] || '';
    const [isPickerOpen, setIsPickerOpen] = useState(false);

    function handleChange(newDate) {
      if (newDate === null || newDate === undefined) {
        setMeta({ ...meta, [META_KEY_ENABLED]: '', [META_KEY_DATE]: '' });
        return;
      }
      const dateObj = newDate instanceof Date ? newDate : new Date(newDate);
      const normalized = formatForStorage(dateObj);
      setMeta({
        ...meta,
        [META_KEY_ENABLED]: '1',
        [META_KEY_DATE]: normalized
      });
    }

    function handleNow() {
      setMeta({
        ...meta,
        [META_KEY_ENABLED]: '1',
        [META_KEY_DATE]: getNowForStorage()
      });
    }

    function handleClear() {
      setMeta({ ...meta, [META_KEY_ENABLED]: '', [META_KEY_DATE]: '' });
      setIsPickerOpen(false);
    }

    function handleDateChange(e) {
      const dateVal = e.target && e.target.value;
      const { time } = storedToParts(rawValue);
      const stored = partsToStored(dateVal, time);
      setMeta({
        ...meta,
        [META_KEY_ENABLED]: stored ? '1' : '',
        [META_KEY_DATE]: stored || ''
      });
    }

    function handleTimeBlur(e) {
      const timeVal = (e.target && e.target.value) || '';
      const { date } = storedToParts(rawValue);
      const stored = partsToStored(date, timeVal);
      setMeta({
        ...meta,
        [META_KEY_ENABLED]: stored ? '1' : '',
        [META_KEY_DATE]: stored || ''
      });
    }

    function handleActionChange(value) {
      setMeta({ ...meta, [META_KEY_ACTION]: value || 'draft' });
    }

    function handleRedirectChange(value) {
      setMeta({
        ...meta,
        [META_KEY_REDIRECT]: value || ''
      });
    }

    const nowLabel = labels.nowLabel || 'Now';
    const clearLabel = labels.clearLabel || 'Reset';
    const previewEmptyLabel =
      labels.previewEmptyLabel || 'Set expiration date and time';
    const actionLabel = labels.actionLabel || 'After expiration';
    const actionDraft = labels.actionDraft || 'Set to draft';
    const actionPrivate = labels.actionPrivate || 'Set to private';
    const actionRedirect = labels.actionRedirect || 'Redirect to';
    const redirectLabel = labels.redirectLabel || 'Redirect URL';
    const redirectPlaceholder = labels.redirectPlaceholder || '/new-path';

    const previewContent = rawValue
      ? timezone
        ? [formatStoredForDisplay(rawValue), el('br'), '(' + timezone + ')']
        : formatStoredForDisplay(rawValue)
      : previewEmptyLabel;

    const previewTrigger = el(
      'button',
      {
        'type': 'button',
        'className':
          'fromscratch-expirator-preview components-button is-tertiary',
        'onClick': function () {
          setIsPickerOpen(function (prev) {
            return !prev;
          });
        },
        'aria-expanded': isPickerOpen
      },
      previewContent
    );

    const previewResetButton = rawValue
      ? el(
          'button',
          {
            'type': 'button',
            'className':
              'fromscratch-expirator-preview-reset components-button is-tertiary has-icon',
            'onClick': handleClear,
            'aria-label': clearLabel
          },
          el('span', {
            className: 'dashicons dashicons-no-alt',
            style: { fontSize: '16px', width: '16px', height: '16px' }
          })
        )
      : null;

    const firstRowContent = rawValue
      ? el(
          'div',
          {
            style: {
              display: 'flex',
              alignItems: 'center',
              gap: '4px',
              width: '100%',
              marginBottom: '4px'
            }
          },
          previewTrigger,
          previewResetButton
        )
      : previewTrigger;

    const pickerContent = !isPickerOpen
      ? []
      : [
          el(DateTimePicker, {
            currentDate: parseStoredDate(rawValue) || null,
            onChange: handleChange,
            is12Hour: is12Hour,
            startOfWeek: (function () {
              var n = parseInt(labels.startOfWeek, 10);
              return n >= 0 && n <= 6 ? n : 0;
            })()
          }),
          el(
            'p',
            {
              style: {
                marginTop: '8px',
                marginBottom: '12px',
                display: 'flex',
                gap: '8px',
                flexWrap: 'wrap'
              }
            },
            el(
              'button',
              {
                type: 'button',
                className: 'components-button is-tertiary is-small',
                onClick: function () {
                  setIsPickerOpen(false);
                }
              },
              labels.okLabel || 'OK'
            ),
            el(
              'button',
              {
                type: 'button',
                className: 'components-button is-tertiary is-small',
                onClick: handleNow
              },
              nowLabel
            ),
            rawValue
              ? el(
                  'button',
                  {
                    type: 'button',
                    className: 'components-button is-tertiary is-small',
                    onClick: handleClear
                  },
                  clearLabel
                )
              : null
          )
        ];

    const actionOptions = [
      { value: 'draft', label: actionDraft },
      { value: 'private', label: actionPrivate },
      { value: 'redirect', label: actionRedirect }
    ];
    const actionSelectEl =
      isPickerOpen || isEnabled
        ? el(SelectControl, {
            className: 'fromscratch-expirator-field-action',
            label: actionLabel,
            value: actionValue,
            options: actionOptions,
            onChange: handleActionChange
          })
        : null;
    const redirectInputEl =
      (isPickerOpen || isEnabled) && actionValue === 'redirect'
        ? el(TextControl, {
            className: 'fromscratch-expirator-field-redirect',
            label: redirectLabel,
            value: redirectValue,
            onChange: handleRedirectChange,
            placeholder: redirectPlaceholder,
            type: 'url'
          })
        : null;

    return el(
      'div',
      { className: 'fromscratch-editor-panel fromscratch-expirator-panel' },
      el(PanelRow, null, firstRowContent),
      isPickerOpen
        ? el(
            PanelRow,
            null,
            el('div', { style: { width: '100%' } }, ...pickerContent)
          )
        : null,
      actionSelectEl ? el(PanelRow, null, actionSelectEl) : null,
      redirectInputEl ? el(PanelRow, null, redirectInputEl) : null
    );
  }

  function ExpiratorPanel() {
    const postType = useSelect(function (select) {
      return select('core/editor')?.getCurrentPostType?.() || '';
    }, []);
    const allowed =
      labels.postTypes && Array.isArray(labels.postTypes)
        ? labels.postTypes
        : ['post', 'page'];
    if (!postType || allowed.indexOf(postType) === -1) {
      return null;
    }
    return el(
      PluginDocumentSettingPanel,
      {
        name: 'fromscratch-expirator',
        title: labels.panelTitle || 'Expiration',
        className: 'fromscratch-expirator-document-panel',
        order: 10
      },
      el(ExpiratorPanelContent, null)
    );
  }

  registerPlugin('fromscratch-expirator', {
    render: ExpiratorPanel
  });
})();
