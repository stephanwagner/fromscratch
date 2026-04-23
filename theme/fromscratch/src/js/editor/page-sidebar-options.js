(function (wp) {
  'use strict';

  const el = wp.element.createElement;
  const { registerPlugin } = wp.plugins;
  const { useSelect } = wp.data;
  const { useEntityProp } = wp.coreData;
  const { CheckboxControl } = wp.components;

  const editor = wp.editor || {};
  const PluginPostStatusInfo = editor.PluginPostStatusInfo;

  const META_SHOW = '_fs_show_page_title';
  const META_PIN = '_fs_pin_to_dashboard';

  function PageSidebarOptions(props) {
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

    var showTitleVal = meta[META_SHOW];
    var showTitleChecked =
      showTitleVal === undefined ||
      showTitleVal === null ||
      showTitleVal === true ||
      showTitleVal === '1' ||
      showTitleVal === 1;

    var pinVal = meta[META_PIN];
    var pinChecked =
      pinVal === true || pinVal === '1' || pinVal === 1;

    /**
     * Same slot as exclude-from-search: PluginPostStatusInfo + inner stack for two controls.
     */
    return el(
      'div',
      { className: 'fromscratch-page-sidebar-options__inner' },
      el(CheckboxControl, {
        label: cfg.labelShowTitlePage || 'Show page title',
        checked: showTitleChecked,
        onChange: function (val) {
          setMeta(
            Object.assign({}, meta, {
              [META_SHOW]: val ? true : false
            })
          );
        }
      }),
      el(CheckboxControl, {
        label: cfg.labelPinDashboard || 'Pin to dashboard',
        checked: pinChecked,
        onChange: function (val) {
          setMeta(
            Object.assign({}, meta, {
              [META_PIN]: val ? true : false
            })
          );
        }
      })
    );
  }

  function PageSidebarOptionsPlugin() {
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
      { className: 'fromscratch-page-sidebar-options' },
      el(PageSidebarOptions, {
        postType: postType,
        postId: postId,
        cfg: cfg
      })
    );
  }

  registerPlugin('fromscratch-page-sidebar-options', {
    render: PageSidebarOptionsPlugin
  });
})(typeof wp !== 'undefined' ? wp : window.wp);
