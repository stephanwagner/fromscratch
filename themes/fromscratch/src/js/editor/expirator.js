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
  const { useState, useMemo } = wp.element;
  const { registerPlugin } = wp.plugins;
  const editor = wp.editor || {};
  const blockEditor = wp.blockEditor || {};
  const InspectorPopoverHeader =
    blockEditor.__experimentalInspectorPopoverHeader;
  const PluginPostStatusInfo = editor.PluginPostStatusInfo;
  const { useSelect } = wp.data;
  const { useEntityProp } = wp.coreData;
  const {
    PanelRow,
    DateTimePicker,
    SelectControl,
    TextControl,
    Dropdown,
    Button
  } = wp.components;
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
    const actionValue = meta[META_KEY_ACTION] || 'draft';
    const redirectValue = meta[META_KEY_REDIRECT] || '';
    /** Anchor element for popover (same idea as core PostSchedulePanel). */
    const [rowAnchorEl, setRowAnchorEl] = useState(null);
    const popoverProps = useMemo(
      function () {
        return {
          anchor: rowAnchorEl,
          placement: 'left-start',
          offset: 36,
          shift: true
        };
      },
      [rowAnchorEl]
    );

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

    function handleClear() {
      setMeta({ ...meta, [META_KEY_ENABLED]: '', [META_KEY_DATE]: '' });
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

    const clearLabel = labels.clearLabel || 'Reset';
    const closeLabel =
      labels.closeLabel ||
      (wp.i18n && wp.i18n.__
        ? wp.i18n.__('Close', 'fromscratch')
        : 'Close');
    const panelTitle = labels.panelTitle || 'Expiration';
    const noneLabel = labels.noneLabel || 'None';
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
      : noneLabel;

    const actionOptions = [
      { value: 'draft', label: actionDraft },
      { value: 'private', label: actionPrivate },
      { value: 'redirect', label: actionRedirect }
    ];

    function renderInspectorPopoverHeaderFallback(onClose) {
      return el(
        'div',
        {
          className:
            'block-editor-inspector-popover-header fromscratch-expirator__inspector-fallback'
        },
        el(
          'div',
          {
            className:
              'components-flex components-h-stack',
            style: {
              alignItems: 'center',
              width: '100%',
              gap: '8px'
            }
          },
          el(
            'h2',
            {
              className:
                'block-editor-inspector-popover-header__heading components-heading',
              style: { fontSize: '13px', margin: 0, flex: '0 1 auto' }
            },
            panelTitle
          ),
          el('div', {
            className: 'components-flex-item',
            style: { flex: '1 1 auto', minWidth: '8px' }
          }),
          el(
            'button',
            {
              type: 'button',
              className:
                'components-button block-editor-inspector-popover-header__action is-small is-tertiary',
              onClick: function () {
                handleClear();
                if (typeof onClose === 'function') {
                  onClose();
                }
              }
            },
            clearLabel
          ),
          el(
            'button',
            {
              type: 'button',
              className:
                'components-button block-editor-inspector-popover-header__action is-small has-icon',
              'aria-label': closeLabel,
              onClick: onClose
            },
            el('svg', {
              xmlns: 'http://www.w3.org/2000/svg',
              viewBox: '0 0 24 24',
              width: '24',
              height: '24',
              'aria-hidden': 'true',
              focusable: 'false'
            }, el('path', {
              d: 'M12 13.06l3.712 3.713 1.061-1.06L13.061 12l3.712-3.712-1.06-1.06L12 10.938 8.288 7.227l-1.061 1.06L10.939 12l-3.712 3.712 1.06 1.061L12 13.061z'
            }))
          )
        )
      );
    }

    function renderDropdownBody(onClose) {
      var headerEl = InspectorPopoverHeader
        ? el(InspectorPopoverHeader, {
            title: panelTitle,
            onClose: onClose,
            actions: [
              {
                label: clearLabel,
                onClick: function () {
                  handleClear();
                  if (typeof onClose === 'function') {
                    onClose();
                  }
                }
              }
            ]
          })
        : renderInspectorPopoverHeaderFallback(onClose);

      return el(
        'div',
        { className: 'fromscratch-expirator__popover-inner' },
        headerEl,
        el(
          'div',
          {
            className:
              'fromscratch-expirator__popover-body fromscratch-editor-panel fromscratch-expirator-panel fromscratch-expirator__dialog'
          },
          el(
            PanelRow,
            null,
            el(DateTimePicker, {
              currentDate: parseStoredDate(rawValue) || null,
              onChange: handleChange,
              is12Hour: is12Hour,
              startOfWeek: (function () {
                var n = parseInt(labels.startOfWeek, 10);
                return n >= 0 && n <= 6 ? n : 0;
              })()
            })
          )
        )
      );
    }

    const scheduleLikeRow = el(
      'div',
      {
        className: 'editor-post-panel__row fromscratch-expirator-post-status',
        ref: function (node) {
          setRowAnchorEl(node);
        }
      },
      el(
        'div',
        { className: 'editor-post-panel__row-label' },
        panelTitle
      ),
      el(
        'div',
        {
          className: 'editor-post-panel__row-control',
          style: {
            display: 'flex',
            alignItems: 'center',
            gap: '4px',
            justifyContent: 'flex-end',
            flex: '1',
            minWidth: 0
          }
        },
        el(Dropdown, {
          popoverProps: popoverProps,
          focusOnMount: true,
          className: 'fromscratch-expirator__panel-dropdown',
          contentClassName:
            'fromscratch-expirator__popover-content editor-post-schedule__dialog',
          renderToggle: function (toggleProps) {
            var onToggle = toggleProps.onToggle;
            var isOpen = toggleProps.isOpen;
            return el(
              Button,
              {
                variant: 'tertiary',
                size: 'compact',
                className:
                  'fromscratch-expirator__toggle editor-post-schedule__dialog-toggle',
                onClick: onToggle,
                'aria-expanded': isOpen,
                'aria-label': panelTitle
              },
              previewContent
            );
          },
          renderContent: function (contentProps) {
            return renderDropdownBody(contentProps.onClose);
          }
        })
      )
    );

    const hasExpirationDate =
      typeof rawValue === 'string' && rawValue.trim() !== '';

    return el(
      'div',
      { className: 'fromscratch-expirator-root' },
      scheduleLikeRow,
      hasExpirationDate
        ? el(
            'div',
            {
              className:
                'fromscratch-editor-panel fromscratch-expirator-panel fromscratch-expirator-sidebar-extra'
            },
            el(
              PanelRow,
              null,
              el(SelectControl, {
                className: 'fromscratch-expirator-field-action',
                label: actionLabel,
                value: actionValue,
                options: actionOptions,
                onChange: handleActionChange
              })
            ),
            actionValue === 'redirect'
              ? el(
                  PanelRow,
                  null,
                  el(TextControl, {
                    className: 'fromscratch-expirator-field-redirect',
                    label: redirectLabel,
                    value: redirectValue,
                    onChange: handleRedirectChange,
                    placeholder: redirectPlaceholder,
                    type: 'url'
                  })
                )
              : null
          )
        : null
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
    if (!PluginPostStatusInfo) {
      return null;
    }
    return el(
      PluginPostStatusInfo,
      {
        className: 'fromscratch-expirator-document-panel'
      },
      el(ExpiratorPanelContent, null)
    );
  }

  registerPlugin('fromscratch-expirator', {
    render: ExpiratorPanel
  });
})();
