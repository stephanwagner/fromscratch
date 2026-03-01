(function (wp) {
  'use strict';

  if (typeof fromscratchFeatures === 'undefined' || !fromscratchFeatures.post_expirator) {
    return;
  }

  const el = wp.element.createElement;
  const { registerPlugin } = wp.plugins;
  const { PluginDocumentSettingPanel } = wp.editPost;
  const { useSelect } = wp.data;
  const { useEntityProp } = wp.coreData;
  const { PanelRow } = wp.components;

  const META_KEY = '_fs_expiration_date';

  const labels = typeof fromscratchExpirator !== 'undefined' ? fromscratchExpirator : {};

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

    let value = meta[META_KEY] || '';
    if (value && value.indexOf(' ') !== -1) {
      value = value.replace(' ', 'T').substring(0, 16);
    }

    return el(
      'div',
      { className: 'fromscratch-expirator-panel' },
      el(
        PanelRow,
        null,
        el(
          'div',
          { className: 'fromscratch-expirator-field', style: { width: '100%' } },
          el(
            'label',
            {
              htmlFor: 'fs_expiration_date',
              style: { display: 'block', marginBottom: '4px', fontWeight: '600' }
            },
            labels.dateLabel || 'Expiration date and time'
          ),
          el('input', {
            type: 'datetime-local',
            id: 'fs_expiration_date',
            className: 'components-text-control__input',
            value: value,
            onChange: function (e) {
              const raw = e.target.value ? e.target.value.trim() : '';
              const normalized =
                raw === ''
                  ? ''
                  : raw.replace('T', ' ').substring(0, 16);
              setMeta({ ...meta, [META_KEY]: normalized });
            },
            style: { width: '100%', maxWidth: '100%' }
          }),
          el(
            'p',
            {
              className: 'description',
              style: { marginTop: '8px', marginBottom: '0' }
            },
            labels.dateHelp ||
              'When this date and time is reached, the post will be set to draft. Leave empty for no expiration.'
          )
        )
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
})(typeof wp !== 'undefined' ? wp : window.wp);
