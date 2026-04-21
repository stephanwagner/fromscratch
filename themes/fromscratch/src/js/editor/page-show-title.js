(function (wp) {
  'use strict';

  const el = wp.element.createElement;
  const { registerPlugin } = wp.plugins;
  const { useSelect } = wp.data;
  const { useEntityProp } = wp.coreData;
  const { CheckboxControl } = wp.components;

  const editor = wp.editor || {};
  const PluginPostStatusInfo = editor.PluginPostStatusInfo;

  const META_KEY = '_fs_show_page_title';

  function ShowPageTitleCheckbox(props) {
    const postType = props.postType;
    const postId = props.postId;
    const cfg = props.cfg || {};

    const [meta, setMeta] = useEntityProp(
      'postType',
      postType,
      'meta',
      postId
    );
    if (!meta || typeof setMeta !== 'function') {
      return null;
    }

    var v = meta[META_KEY];
    var checked =
      v === undefined ||
      v === null ||
      v === true ||
      v === '1' ||
      v === 1;

    return el(CheckboxControl, {
      label: cfg.labelShowTitle || 'Show page title',
      checked: checked,
      onChange: function (val) {
        setMeta(
          Object.assign({}, meta, {
            [META_KEY]: val ? true : false
          })
        );
      }
    });
  }

  function ShowPageTitlePlugin() {
    const postType = useSelect(function (select) {
      return select('core/editor')?.getCurrentPostType?.() || '';
    }, []);
    const postId = useSelect(function (select) {
      return select('core/editor')?.getCurrentPostId?.();
    }, []);

    var cfg =
      typeof fromscratchPageSidebarOptions !== 'undefined'
        ? fromscratchPageSidebarOptions
        : {};

    if (!PluginPostStatusInfo) {
      return null;
    }
    if (postType !== 'page' || !postId) {
      return null;
    }

    return el(
      PluginPostStatusInfo,
      { className: 'fromscratch-page-show-title' },
      el(ShowPageTitleCheckbox, {
        postType: postType,
        postId: postId,
        cfg: cfg
      })
    );
  }

  registerPlugin('fromscratch-page-show-title', {
    render: ShowPageTitlePlugin
  });
})(typeof wp !== 'undefined' ? wp : window.wp);
