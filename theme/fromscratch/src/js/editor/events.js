(function () {
  'use strict';

  const wp = typeof window !== 'undefined' ? window.wp : null;
  if (!wp || typeof fromscratchEvents === 'undefined' || !fromscratchEvents.postType) {
    return;
  }

  const el = wp.element.createElement;
  const { useState, useEffect } = wp.element;
  const { useSelect } = wp.data;
  const { useEntityProp } = wp.coreData;
  const { registerPlugin } = wp.plugins;
  const editPost = wp.editPost || {};
  const PluginDocumentSettingPanel = editPost.PluginDocumentSettingPanel;
  const { PanelRow, ToggleControl } = wp.components;

  const PT = fromscratchEvents.postType;
  const L = fromscratchEvents;

  const META_START_DATE = '_fs_event_start_date';
  const META_END_DATE = '_fs_event_end_date';
  const META_START_TIME = '_fs_event_start_time';
  const META_END_TIME = '_fs_event_end_time';

  function EventPanelContent() {
    const postType = useSelect(function (select) {
      return select('core/editor')?.getCurrentPostType?.() || '';
    }, []);
    const postId = useSelect(function (select) {
      return select('core/editor')?.getCurrentPostId?.();
    }, []);

    if (!postType || postType !== PT || !postId) {
      return null;
    }

    const [meta, setMeta] = useEntityProp('postType', postType, 'meta', postId);
    if (!meta || typeof setMeta !== 'function') {
      return null;
    }

    const startDate = meta[META_START_DATE] || '';
    const endDate = meta[META_END_DATE] || '';
    const startTime = meta[META_START_TIME] || '';
    const endTime = meta[META_END_TIME] || '';

    const [timesEnabled, setTimesEnabled] = useState(
      function () {
        return !!(startTime || endTime);
      }
    );
    useEffect(
      function () {
        setTimesEnabled(!!(startTime || endTime));
      },
      [postId]
    );

    function patch(next) {
      setMeta(Object.assign({}, meta, next));
    }

    function onToggleTimes(on) {
      setTimesEnabled(on);
      if (!on) {
        patch({
          [META_START_TIME]: '',
          [META_END_TIME]: ''
        });
      }
    }

    return el(
      PluginDocumentSettingPanel,
      {
        name: 'fromscratch-event',
        title: L.panelTitle || 'Event',
        className: 'fromscratch-event-panel'
      },
      el(
        'div',
        { className: 'fromscratch-editor-panel' },
        el(
          PanelRow,
          null,
          el(
            'label',
            { className: 'components-base-control__label', htmlFor: 'fs-event-start-date' },
            L.startDateLabel || 'Start date'
          ),
          el('input', {
            id: 'fs-event-start-date',
            type: 'date',
            className: 'components-text-control__input fs-event-date-input fs-event-date-input--start',
            value: startDate,
            onChange: function (e) {
              var v = e.target.value;
              patch({
                [META_START_DATE]: v,
                [META_END_DATE]: endDate && endDate >= v ? endDate : v
              });
            }
          })
        ),
        el(
          PanelRow,
          null,
          el(
            'label',
            { className: 'components-base-control__label', htmlFor: 'fs-event-end-date' },
            L.endDateLabel || 'End date'
          ),
          el('input', {
            id: 'fs-event-end-date',
            type: 'date',
            className: 'components-text-control__input fs-event-date-input fs-event-date-input--end',
            value: endDate || startDate,
            min: startDate || undefined,
            onChange: function (e) {
              patch({ [META_END_DATE]: e.target.value });
            }
          })
        ),
        el(
          PanelRow,
          { className: 'fs-event-include-times' },
          el(ToggleControl, {
            key: 'toggle',
            className: 'fs-event-include-times__control',
            label: L.includeTimesLabel || 'Include times',
            checked: timesEnabled,
            onChange: function (on) {
              onToggleTimes(on);
            }
          })
        ),
        timesEnabled
          ? el(
              PanelRow,
              { key: 'fs-event-start-time-row' },
              el(
                'label',
                { className: 'components-base-control__label', htmlFor: 'fs-event-start-time' },
                L.startTimeLabel || 'Start time'
              ),
              el('input', {
                id: 'fs-event-start-time',
                type: 'time',
                className: 'components-text-control__input fs-event-time-input fs-event-time-input--start',
                value: startTime,
                onChange: function (e) {
                  patch({ [META_START_TIME]: e.target.value });
                }
              })
            )
          : null,
        timesEnabled
          ? el(
              PanelRow,
              { key: 'fs-event-end-time-row' },
              el(
                'label',
                { className: 'components-base-control__label', htmlFor: 'fs-event-end-time' },
                L.endTimeLabel || 'End time'
              ),
              el('input', {
                id: 'fs-event-end-time',
                type: 'time',
                className: 'components-text-control__input fs-event-time-input fs-event-time-input--end',
                value: endTime,
                onChange: function (e) {
                  patch({ [META_END_TIME]: e.target.value });
                }
              })
            )
          : null
      )
    );
  }

  function EventPanel() {
    return el(EventPanelContent, null);
  }

  if (PluginDocumentSettingPanel) {
    registerPlugin('fromscratch-event', {
      render: EventPanel
    });
  }
})();
