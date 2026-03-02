(function (wp) {
  'use strict';

  if (typeof fromscratchFeatures === 'undefined' || !fromscratchFeatures.seo) {
    return;
  }

  const el = wp.element.createElement;
  const { registerPlugin } = wp.plugins;
  const { PluginDocumentSettingPanel } = wp.editPost;
  const { useSelect } = wp.data;
  const { useEntityProp } = wp.coreData;
  const { TextControl, TextareaControl, PanelRow, CheckboxControl } =
    wp.components;
  const { MediaUpload } = wp.blockEditor;

  const META_KEYS = {
    title: '_fs_seo_title',
    description: '_fs_seo_description',
    ogImage: '_fs_seo_og_image',
    noindex: '_fs_seo_noindex'
  };

  const labels = typeof fromscratchSeo !== 'undefined' ? fromscratchSeo : {};

  function SeoPanelContent() {
    const postType = useSelect(function (select) {
      return select('core/editor')?.getCurrentPostType?.() || '';
    }, []);
    const postId = useSelect(function (select) {
      return select('core/editor')?.getCurrentPostId?.();
    }, []);

    var allowed =
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

    const get = function (key) {
      return meta[META_KEYS[key]] || '';
    };
    const set = function (key, value) {
      setMeta({ ...meta, [META_KEYS[key]]: value });
    };

    const ogImageId = parseInt(get('ogImage'), 10) || 0;
    const ogImageUrl = useSelect(
      function (select) {
        if (!ogImageId) return '';
        const media = select('core').getEntityRecord(
          'postType',
          'attachment',
          ogImageId
        );
        return media && media.source_url ? media.source_url : '';
      },
      [ogImageId]
    );

    return el(
      'div',
      { className: 'fromscratch-panel fromscratch-seo-panel' },
      el(
        PanelRow,
        null,
        el(TextControl, {
          label: labels.titleLabel || 'Title',
          help: labels.titleHelp || '',
          value: get('title'),
          onChange: function (val) {
            set('title', val || '');
          }
        })
      ),
      el(
        PanelRow,
        null,
        el(TextareaControl, {
          label: labels.descriptionLabel || 'Description',
          help: labels.descriptionHelp || '',
          value: get('description'),
          onChange: function (val) {
            set('description', val || '');
          },
          rows: 3
        })
      ),
      el(
        PanelRow,
        null,
        el(CheckboxControl, {
          label: labels.noindexLabel || 'No index',
          help: labels.noindexHelp || '',
          checked: (function () {
            var v = get('noindex');
            return v === true || v === '1' || v === 1;
          })(),
          onChange: function (checked) {
            set('noindex', checked ? true : false);
          }
        })
      ),
      el(
        PanelRow,
        { className: 'fromscratch-seo-og-image' },
        el(
          'div',
          { className: 'fromscratch-seo-og-image-wrap' },
          el(
            'label',
            { className: 'fromscratch-seo-og-image-label' },
            labels.ogImageLabel || 'OG Image'
          ),
          el(
            'p',
            {
              className: 'description',
              style: { marginTop: '4px', marginBottom: '8px' }
            },
            labels.ogImageHelp || 'Best size: 1200 × 630 px.'
          ),
          el(MediaUpload, {
            allowedTypes: ['image'],
            value: ogImageId || undefined,
            onSelect: function (media) {
              set('ogImage', media.id ? String(media.id) : '');
            },
            render: function (obj) {
              return el(
                'div',
                null,
                ogImageId
                  ? el(
                      'div',
                      { style: { marginBottom: '8px' } },
                      ogImageUrl
                        ? el('img', {
                            src: ogImageUrl,
                            alt: '',
                            style: {
                              maxWidth: '100%',
                              height: 'auto',
                              display: 'block',
                              marginBottom: '8px'
                            }
                          })
                        : null,
                      el(
                        'div',
                        null,
                        el(
                          'button',
                          {
                            type: 'button',
                            className: 'button',
                            onClick: obj.open
                          },
                          labels.ogImageButton || 'Replace'
                        ),
                        ' ',
                        el(
                          'button',
                          {
                            type: 'button',
                            className: 'button',
                            onClick: function () {
                              set('ogImage', '');
                            }
                          },
                          labels.ogImageRemove || 'Remove'
                        )
                      )
                    )
                  : el(
                      'button',
                      {
                        type: 'button',
                        className: 'button',
                        onClick: obj.open
                      },
                      labels.ogImageButton || 'Select image'
                    )
              );
            }
          })
        )
      )
    );
  }

  function SeoPanel() {
    const postType = useSelect(function (select) {
      return select('core/editor')?.getCurrentPostType?.() || '';
    }, []);
    var allowed =
      labels.postTypes && Array.isArray(labels.postTypes)
        ? labels.postTypes
        : ['post', 'page'];
    if (!postType || allowed.indexOf(postType) === -1) {
      return null;
    }
    return el(
      PluginDocumentSettingPanel,
      {
        name: 'fromscratch-seo',
        title: labels.panelTitle || 'SEO',
        className: 'fromscratch-seo-document-panel',
        order: 20
      },
      el(SeoPanelContent, null)
    );
  }

  registerPlugin('fromscratch-seo', {
    render: SeoPanel
  });
})(typeof wp !== 'undefined' ? wp : window.wp);
