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
  const {
    TextControl,
    TextareaControl,
    PanelRow,
    CheckboxControl,
    Button,
    DropZone
  } = wp.components;
  const { MediaUpload, MediaUploadCheck } = wp.blockEditor;

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
    const getBlockEditorSettings = useSelect(
      function (select) {
        return select('core/block-editor')?.getSettings || null;
      },
      []
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
            { className: 'fromscratch-seo-og-image-label', style: { display: 'block', marginBottom: '8px' } },
            labels.ogImageLabel || 'OG Image'
          ),
          el(
            'p',
            {
              className: 'description',
              style: { marginTop: '0', marginBottom: '8px' }
            },
            labels.ogImageHelp || 'Best size: 1200 × 630 px.'
          ),
          MediaUploadCheck
            ? el(
                MediaUploadCheck,
                {
                  fallback: el(
                    'p',
                    { className: 'description' },
                    labels.ogImagePermissionHelp ||
                      'To set an OG image, you need permission to upload media.'
                  )
                },
                el(
                  'div',
                  { className: 'editor-post-featured-image' },
                  el(
                    'div',
                    { className: 'editor-post-featured-image__container' },
                    ogImageId
                      ? el(
                          MediaUpload,
                          {
                            allowedTypes: ['image'],
                            value: ogImageId,
                            onSelect: function (media) {
                              set('ogImage', media.id ? media.id : 0);
                            },
                            render: function (renderProps) {
                              return el(
                                'div',
                                null,
                                el(
                                  'div',
                                  { className: 'editor-post-featured-image__preview' },
                                  ogImageUrl
                                    ? el('img', {
                                        src: ogImageUrl,
                                        alt: '',
                                        className: 'editor-post-featured-image__preview-image'
                                      })
                                    : null
                                ),
                                el(
                                  'div',
                                  { className: 'editor-post-featured-image__actions' },
                                  el(
                                    Button,
                                    {
                                      variant: 'secondary',
                                      className: 'editor-post-featured-image__action',
                                      onClick: renderProps.open
                                    },
                                    labels.ogImageReplace || 'Replace'
                                  ),
                                  el(
                                    Button,
                                    {
                                      variant: 'secondary',
                                      isDestructive: true,
                                      className: 'editor-post-featured-image__action',
                                      onClick: function () {
                                        set('ogImage', 0);
                                      }
                                    },
                                    labels.ogImageRemove || 'Remove'
                                  )
                                )
                              );
                            }
                          }
                        )
                      : el(
                          MediaUpload,
                          {
                            allowedTypes: ['image'],
                            value: undefined,
                            onSelect: function (media) {
                              set('ogImage', media.id ? media.id : 0);
                            },
                            render: function (renderProps) {
                              var settings = getBlockEditorSettings
                                ? getBlockEditorSettings()
                                : null;
                              var mediaUpload = settings?.mediaUpload;
                              var toggleButton = el(
                                Button,
                                {
                                  variant: 'secondary',
                                  className: 'editor-post-featured-image__toggle',
                                  onClick: renderProps.open,
                                  style: { minHeight: '50px', width: '100%' }
                                },
                                labels.ogImageButton || 'Set OG image'
                              );
                              return el(
                                'div',
                                { className: 'editor-post-featured-image__toggle' },
                                DropZone && mediaUpload &&
                                  el(DropZone, {
                                    onFilesDrop: function (files) {
                                      mediaUpload({
                                        allowedTypes: ['image'],
                                        filesList: files,
                                        onFileChange: function (images) {
                                          if (images && images[0] && images[0].id) {
                                            set('ogImage', images[0].id);
                                          }
                                        },
                                        multiple: false
                                      });
                                    }
                                  }),
                                toggleButton
                              );
                            }
                          }
                        )
                  )
                )
              )
            : el(MediaUpload, {
                allowedTypes: ['image'],
                value: ogImageId || undefined,
                onSelect: function (media) {
                  set('ogImage', media.id ? media.id : 0);
                },
                render: function (obj) {
                  return ogImageId && ogImageUrl
                    ? el(
                        'div',
                        null,
                        el('img', {
                          src: ogImageUrl,
                          alt: '',
                          style: { maxWidth: '100%', height: 'auto', display: 'block', marginBottom: '8px' }
                        }),
                        el(Button, { variant: 'secondary', onClick: obj.open }, labels.ogImageReplace || 'Replace'),
                        ' ',
                        el(Button, {
                          variant: 'secondary',
                          isDestructive: true,
                          onClick: function () {
                            set('ogImage', 0);
                          }
                        }, labels.ogImageRemove || 'Remove')
                      )
                    : el(Button, { variant: 'secondary', onClick: obj.open }, labels.ogImageButton || 'Set OG image');
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
