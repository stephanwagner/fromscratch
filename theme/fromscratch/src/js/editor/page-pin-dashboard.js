(function (wp) {
  'use strict';

  const el = wp.element.createElement;
  const { registerPlugin } = wp.plugins;
  const { useSelect } = wp.data;
  const { useEntityProp } = wp.coreData;
  const { CheckboxControl } = wp.components;

  const editor = wp.editor || {};
  const PluginPostStatusInfo = editor.PluginPostStatusInfo;

  const META_KEY = '_fs_pin_to_dashboard';

  function PinToDashboardCheckbox(props) {
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
    var checked = v === true || v === '1' || v === 1;

    return el(CheckboxControl, {
      label: cfg.labelPinDashboard || 'Pin to dashboard',
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

  function PinToDashboardPlugin() {
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
    var allowed =
      cfg.pinPostTypes && Array.isArray(cfg.pinPostTypes)
        ? cfg.pinPostTypes
        : ['post', 'page'];

    if (!PluginPostStatusInfo) {
      return null;
    }
    if (!postType || allowed.indexOf(postType) === -1 || !postId) {
      return null;
    }

    return el(
      PluginPostStatusInfo,
      { className: 'fromscratch-page-pin-dashboard' },
      el(PinToDashboardCheckbox, {
        postType: postType,
        postId: postId,
        cfg: cfg
      })
    );
  }

  registerPlugin('fromscratch-page-pin-dashboard', {
    render: PinToDashboardPlugin
  });
})(typeof wp !== 'undefined' ? wp : window.wp);
