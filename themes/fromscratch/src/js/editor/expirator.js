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
  const wpDate = wp.date;

  const META_KEY = '_fs_expiration_date';
  const labels = typeof fromscratchExpirator !== 'undefined' ? fromscratchExpirator : {};
  const timezone = labels.timezone || '';
  const is12Hour = labels.is12Hour !== false;

  /** Stored value is "Y-m-d H:i" (site timezone). Return value for datetime-local: "YYYY-MM-DDTHH:mm". */
  function storedToInputValue(stored) {
    if (!stored || typeof stored !== 'string') return '';
    const trimmed = stored.trim();
    if (trimmed.length < 16) return '';
    return trimmed.substring(0, 16).replace(' ', 'T');
  }

  /** datetime-local value "YYYY-MM-DDTHH:mm" -> stored "Y-m-d H:i". */
  function inputValueToStored(inputVal) {
    if (!inputVal || typeof inputVal !== 'string') return '';
    const normalized = inputVal.trim().replace('T', ' ').substring(0, 16);
    return /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/.test(normalized) ? normalized : '';
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

    const rawValue = meta[META_KEY] || '';
    const useWordPressPicker = Boolean(DateTimePicker && PanelRow);

    function handleChange(newDate) {
      if (newDate === null || newDate === undefined) {
        setMeta({ ...meta, [META_KEY]: '' });
        return;
      }
      const dateObj = newDate instanceof Date ? newDate : new Date(newDate);
      const normalized = formatForStorage(dateObj);
      setMeta({ ...meta, [META_KEY]: normalized });
    }

    function handleClear() {
      setMeta({ ...meta, [META_KEY]: '' });
    }

    function handleNativeChange(e) {
      const inputVal = e.target && e.target.value;
      setMeta({ ...meta, [META_KEY]: inputValueToStored(inputVal) });
    }

    const dateLabel = labels.dateLabel || 'Expiration date and time';
    const clearLabel = labels.clearLabel || 'Clear';
    const dateHelp = labels.dateHelp ||
      'When this date and time is reached, the post will be set to draft. Leave empty for no expiration.';
    const timezoneNote = timezone
      ? ' Times are in your site timezone (Settings → General).'
      : '';

    const pickerContent = useWordPressPicker
      ? [
          el(DateTimePicker, {
            currentDate: parseStoredDate(rawValue) || null,
            onChange: handleChange,
            is12Hour: is12Hour,
            startOfWeek: typeof labels.startOfWeek === 'number' ? labels.startOfWeek : 0
          }),
          rawValue
            ? el(
                'p',
                { style: { marginTop: '8px', marginBottom: '0' } },
                el(
                  'button',
                  {
                    type: 'button',
                    className: 'button button-small',
                    onClick: handleClear
                  },
                  clearLabel
                )
              )
            : null
        ]
      : [
          el('input', {
            type: 'datetime-local',
            id: 'fs_expiration_date',
            className: 'fromscratch-expirator-datetime-input',
            style: { minWidth: '16em', display: 'block' },
            value: storedToInputValue(rawValue),
            onChange: handleNativeChange,
            'aria-label': dateLabel
          }),
          rawValue
            ? el(
                'p',
                { style: { marginTop: '8px', marginBottom: '0' } },
                el(
                  'button',
                  {
                    type: 'button',
                    className: 'button button-small',
                    onClick: handleClear
                  },
                  clearLabel
                )
              )
            : null
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
              el(
                'label',
                {
                  htmlFor: 'fs_expiration_date',
                  style: { display: 'block', marginBottom: '6px', fontWeight: '600' }
                },
                dateLabel
              ),
              ...pickerContent,
              el(
                'p',
                {
                  className: 'description',
                  style: { marginTop: '8px', marginBottom: '0' }
                },
                dateHelp + (useWordPressPicker ? '' : timezoneNote)
              )
            )
          )
        : el(
            'div',
            { className: 'fromscratch-expirator-field' },
            el('label', { htmlFor: 'fs_expiration_date' }, dateLabel),
            ...pickerContent,
            el('p', { className: 'description' }, dateHelp + (useWordPressPicker ? '' : timezoneNote))
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
