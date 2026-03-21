(function (wp) {
  'use strict';

  const el = wp.element.createElement;
  const { registerPlugin } = wp.plugins;
  const { useSelect } = wp.data;
  const { useEntityProp } = wp.coreData;
  const { CheckboxControl } = wp.components;

  const editor = wp.editor || {};
  const PluginPostStatusInfo = editor.PluginPostStatusInfo;

  const META_KEY = '_fs_exclude_from_search';

  function ExcludeFromSearchCheckbox(props) {
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

    var checked =
      meta[META_KEY] === true ||
      meta[META_KEY] === '1' ||
      meta[META_KEY] === 1;

    return el(CheckboxControl, {
      label: cfg.label || 'Exclude from search',
      help: cfg.help || '',
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

  function ExcludeFromSearchPlugin() {
    const postType = useSelect(function (select) {
      return select('core/editor')?.getCurrentPostType?.() || '';
    }, []);
    const postId = useSelect(function (select) {
      return select('core/editor')?.getCurrentPostId?.();
    }, []);

    var cfg =
      typeof fromscratchExcludeFromSearch !== 'undefined'
        ? fromscratchExcludeFromSearch
        : {};
    var allowed =
      cfg.postTypes && Array.isArray(cfg.postTypes)
        ? cfg.postTypes
        : ['post', 'page'];

    if (!PluginPostStatusInfo) {
      return null;
    }
    if (!postType || allowed.indexOf(postType) === -1 || !postId) {
      return null;
    }

    /**
     * Injects into the document Summary area (same sidebar region as status,
     * template, and Page attributes). WordPress does not expose a separate
     * SlotFill inside only the Page attributes group on all versions.
     */
    return el(
      PluginPostStatusInfo,
      { className: 'fromscratch-exclude-from-search' },
      el(ExcludeFromSearchCheckbox, {
        postType: postType,
        postId: postId,
        cfg: cfg
      })
    );
  }

  registerPlugin('fromscratch-exclude-from-search', {
    render: ExcludeFromSearchPlugin
  });
})(typeof wp !== 'undefined' ? wp : window.wp);
