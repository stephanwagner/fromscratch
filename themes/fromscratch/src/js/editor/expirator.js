(function () {
  'use strict';

  const wp = typeof window !== 'undefined' ? window.wp : null;
  if (!wp || typeof fromscratchFeatures === 'undefined' || !fromscratchFeatures.post_expirator) {
    return;
  }

  const el = wp.element.createElement;
  const { registerPlugin } = wp.plugins;
  const PluginDocumentSettingPanel = wp.editPost?.PluginDocumentSettingPanel;
  const { useSelect } = wp.data;
  const { useEntityProp } = wp.coreData;
  const PanelRow = wp.components?.PanelRow;
  const DateTimePicker = wp.components?.DateTimePicker;
  const CheckboxControl = wp.components?.CheckboxControl;
  const wpDate = wp.date;

  const META_KEY_DATE = '_fs_expiration_date';
  const META_KEY_ENABLED = '_fs_expiration_enabled';
  const labels = typeof fromscratchExpirator !== 'undefined' ? fromscratchExpirator : {};
  const timezone = labels.timezone || '';
  // Match WordPress publish date. wp_localize_script can output booleans as "1"/"0", so accept both.
  const is12Hour = labels.is12Hour !== false && labels.is12Hour !== '0' && labels.is12Hour !== 0;
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
    const suffix = h < 12 ? (am || 'am') : (pm || 'pm');
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
    const date = (dateVal && typeof dateVal === 'string') ? dateVal.trim() : '';
    const time = parseTimeForStorage(timeVal, is12Hour);
    if (date === '') return '';
    if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) return '';
    return time ? date + ' ' + time : date + ' 00:00';
  }

  /** Current date/time in site timezone as stored "Y-m-d H:i". */
  function getNowForStorage() {
    return formatForStorage(new Date());
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
    const useWordPressPicker = Boolean(DateTimePicker && PanelRow);

    function handleChange(newDate) {
      if (newDate === null || newDate === undefined) {
        setMeta({ ...meta, [META_KEY_DATE]: '' });
        return;
      }
      const dateObj = newDate instanceof Date ? newDate : new Date(newDate);
      const normalized = formatForStorage(dateObj);
      setMeta({ ...meta, [META_KEY_DATE]: normalized });
    }

    function handleNow() {
      setMeta({ ...meta, [META_KEY_DATE]: getNowForStorage() });
    }

    function handleClear() {
      setMeta({ ...meta, [META_KEY_ENABLED]: '', [META_KEY_DATE]: '' });
    }

    function handleEnableChange(checked) {
      setMeta({
        ...meta,
        [META_KEY_ENABLED]: checked ? '1' : '',
        [META_KEY_DATE]: checked && !rawValue ? getNowForStorage() : rawValue
      });
    }

    function handleDateChange(e) {
      const dateVal = e.target && e.target.value;
      const { time } = storedToParts(rawValue);
      setMeta({ ...meta, [META_KEY_DATE]: partsToStored(dateVal, time) });
    }

    function handleTimeBlur(e) {
      const timeVal = (e.target && e.target.value) || '';
      const { date } = storedToParts(rawValue);
      setMeta({ ...meta, [META_KEY_DATE]: partsToStored(date, timeVal) });
    }

    const dateLabel = labels.dateLabel || 'Expiration date and time';
    const nowLabel = labels.nowLabel || 'Now';
    const clearLabel = labels.clearLabel || 'Clear';
    const enableLabel = labels.enableLabel || 'Activate expire';
    const enableHelp = labels.enableHelp || 'Uncheck to disable expiration.';
    const dateHelp = labels.dateHelp ||
      'When this date and time is reached, the post will be set to draft.';
    const timezoneNote = timezone
      ? ' Times are in your site timezone (Settings → General).'
      : '';

    const { date: datePart, time: timePart } = storedToParts(rawValue);
    const timeDisplayValue = is12Hour ? time24ToDisplay(timePart, amLabel, pmLabel) : timePart;

    const checkboxEl = CheckboxControl
      ? el(CheckboxControl, {
          label: enableLabel,
          checked: isEnabled,
          onChange: handleEnableChange
        })
      : el(
          'label',
          { style: { display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '10px' } },
          el('input', {
            type: 'checkbox',
            checked: isEnabled,
            onChange: function (e) { handleEnableChange(e.target.checked); }
          }),
          enableLabel
        );

    const pickerContent = !isEnabled
      ? []
      : useWordPressPicker
        ? [
            el(DateTimePicker, {
              currentDate: parseStoredDate(rawValue) || null,
              onChange: handleChange,
              is12Hour: is12Hour,
              startOfWeek: typeof labels.startOfWeek === 'number' ? labels.startOfWeek : 0
            }),
            el(
              'p',
              { style: { marginTop: '8px', marginBottom: '0', display: 'flex', gap: '8px', flexWrap: 'wrap' } },
              el(
                'button',
                { type: 'button', className: 'button button-small', onClick: handleNow },
                nowLabel
              ),
              rawValue
                ? el(
                    'button',
                    { type: 'button', className: 'button button-small', onClick: handleClear },
                    clearLabel
                  )
                : null
            )
          ]
        : [
            el('div', {
              className: 'fromscratch-expirator-date-time',
              style: { display: 'flex', gap: '10px', flexWrap: 'wrap', alignItems: 'center' }
            }, [
              el('input', {
                type: 'date',
                id: 'fs_expiration_date',
                'aria-label': dateLabel,
                className: 'fromscratch-expirator-date-input',
                style: { minWidth: '10em' },
                value: datePart,
                onChange: handleDateChange
              }),
              el('input', {
                type: 'text',
                key: 'expirator-time-' + (rawValue || 'empty'),
                'aria-label': labels.timeLabel || 'Expiration time',
                className: 'fromscratch-expirator-time-input',
                style: { width: is12Hour ? '8em' : '5em', fontVariantNumeric: 'tabular-nums' },
                placeholder: labels.timePlaceholder || (is12Hour ? 'e.g. 2:30 pm' : 'HH:mm'),
                maxLength: is12Hour ? 10 : 5,
                defaultValue: timeDisplayValue,
                onBlur: handleTimeBlur
              })
            ]),
            el(
              'p',
              { style: { marginTop: '8px', marginBottom: '0', display: 'flex', gap: '8px', flexWrap: 'wrap' } },
              el(
                'button',
                { type: 'button', className: 'button button-small', onClick: handleNow },
                nowLabel
              ),
              rawValue
                ? el(
                    'button',
                    { type: 'button', className: 'button button-small', onClick: handleClear },
                    clearLabel
                  )
                : null
            )
          ];

    return el(
      'div',
      { className: 'fromscratch-expirator-panel' },
      PanelRow
        ? el(
            PanelRow,
            null,
            el(
              'div',
              { className: 'fromscratch-expirator-field', style: { width: '100%' } },
              checkboxEl,
              isEnabled ? el('p', { className: 'description', style: { marginTop: '2px', marginBottom: '6px', fontSize: '12px' } }, enableHelp) : null,
              ...pickerContent,
              el(
                'p',
                {
                  className: 'description',
                  style: { marginTop: '8px', marginBottom: '0' }
                },
                dateHelp + (isEnabled && useWordPressPicker ? '' : timezoneNote)
              )
            )
          )
        : el(
            'div',
            { className: 'fromscratch-expirator-field' },
            checkboxEl,
            isEnabled ? el('p', { className: 'description', style: { marginTop: '2px', marginBottom: '6px', fontSize: '12px' } }, enableHelp) : null,
            ...pickerContent,
            el('p', { className: 'description' }, dateHelp + (isEnabled ? timezoneNote : ''))
          )
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
    if (!PluginDocumentSettingPanel) return null;
    return el(
      PluginDocumentSettingPanel,
      {
        name: 'fromscratch-expirator',
        title: labels.panelTitle || 'Expiration',
        className: 'fromscratch-expirator-document-panel'
      },
      el(ExpiratorPanelContent, null)
    );
  }

  registerPlugin('fromscratch-expirator', {
    render: ExpiratorPanel
  });
})();
