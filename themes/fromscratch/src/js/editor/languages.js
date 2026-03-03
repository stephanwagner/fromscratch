(function (wp) {
  'use strict';

  if (typeof fromscratchFeatures === 'undefined' || !fromscratchFeatures.languages) {
    return;
  }

  const el = wp.element.createElement;
  const { registerPlugin } = wp.plugins;
  const { PluginDocumentSettingPanel } = wp.editPost;
  const { useSelect, useDispatch } = wp.data;
  const { useEntityProp } = wp.coreData;
  const { SelectControl, PanelRow } = wp.components;

  const TAXONOMY = 'fs_language';
  const labels = typeof fromscratchLanguages !== 'undefined' ? fromscratchLanguages : {};

  function LanguagesPanelContent() {
    const postType = useSelect(function (select) {
      return select('core/editor')?.getCurrentPostType?.() || '';
    }, []);
    const postId = useSelect(function (select) {
      return select('core/editor')?.getCurrentPostId?.();
    }, []);

    const postTypes = labels.postTypes && Array.isArray(labels.postTypes) ? labels.postTypes : ['post', 'page'];
    if (!postType || postTypes.indexOf(postType) === -1) {
      return null;
    }

    const languages = labels.languages && Array.isArray(labels.languages) ? labels.languages : [];
    const slugToTermId = labels.slugToTermId && typeof labels.slugToTermId === 'object' ? labels.slugToTermId : {};
    const linked = labels.linked && typeof labels.linked === 'object' ? labels.linked : {};
    const createUrls = labels.createTranslationUrls && typeof labels.createTranslationUrls === 'object' ? labels.createTranslationUrls : {};

    const [termIds, setTermIds] = useEntityProp('postType', postType, TAXONOMY, postId);
    const currentTermIds = Array.isArray(termIds) ? termIds : [];
    const currentTermId = currentTermIds.length ? parseInt(currentTermIds[0], 10) : 0;
    const termIdToSlug = {};
    Object.keys(slugToTermId || {}).forEach(function (slug) {
      termIdToSlug[String(slugToTermId[slug])] = slug;
    });
    const currentSlug = currentTermId ? (termIdToSlug[currentTermId] || '') : '';

    const { editEntityRecord } = useDispatch('core');
    const setLanguage = function (slug) {
      const termId = slug && slugToTermId[slug] ? parseInt(slugToTermId[slug], 10) : 0;
      const next = termId ? [termId] : [];
      editEntityRecord('postType', postType, postId, { [TAXONOMY]: next });
    };

    if (languages.length === 0) {
      return null;
    }

    const options = [
      { label: labels.selectLanguage || '— Select language —', value: '' }
    ].concat(languages.map(function (lang) {
      const id = lang.id || '';
      const label = (lang.nameEnglish && lang.nameEnglish !== '') ? lang.nameEnglish : id;
      return { label: label, value: id };
    }));

    const rows = languages.map(function (lang) {
      const id = lang.id || '';
      const label = (lang.nameEnglish && lang.nameEnglish !== '') ? lang.nameEnglish : id;
      if (id === currentSlug) {
        return el('div', { key: id, style: { marginBottom: '8px' } },
          label + ' ',
          el('span', { style: { color: '#646970' } }, '(' + (labels.current || 'current') + ')')
        );
      }
      const linkInfo = linked[id];
      if (linkInfo && linkInfo.editLink) {
        return el('div', { key: id, style: { marginBottom: '8px' } },
          el('a', { href: linkInfo.editLink }, label),
          ' ',
          el('span', { style: { color: '#00a32a' } }, '(' + (labels.linkedLabel || 'linked') + ')')
        );
      }
      const createUrl = createUrls[id];
      if (createUrl && postId) {
        return el('div', { key: id, style: { marginBottom: '8px' } },
          el('a', { href: createUrl, className: 'button button-small' }, labels.createTranslation || 'Create translation'),
          ' ',
          el('span', { style: { color: '#646970' } }, label)
        );
      }
      return el('div', { key: id, style: { marginBottom: '8px' } }, label);
    });

    return el(
      'div',
      { className: 'fromscratch-editor-panel fromscratch-languages-panel' },
      el(
        PanelRow,
        null,
        el(SelectControl, {
          label: labels.thisContentIsIn || 'This content is in',
          value: currentSlug,
          options: options,
          onChange: setLanguage
        })
      ),
      el('div', { style: { marginTop: '12px' } },
        el('strong', null, labels.translations || 'Translations'),
        el('div', { style: { marginTop: '8px' } }, rows)
      )
    );
  }

  function LanguagesPanel() {
    const postType = useSelect(function (select) {
      return select('core/editor')?.getCurrentPostType?.() || '';
    }, []);
    const postTypes = labels.postTypes && Array.isArray(labels.postTypes) ? labels.postTypes : ['post', 'page'];
    if (!postType || postTypes.indexOf(postType) === -1) {
      return null;
    }
    return el(
      PluginDocumentSettingPanel,
      {
        name: 'fromscratch-languages',
        title: labels.panelTitle || 'Language & translations',
        className: 'fromscratch-languages-document-panel',
        order: 15
      },
      el(LanguagesPanelContent, null)
    );
  }

  registerPlugin('fromscratch-languages', {
    render: LanguagesPanel
  });
})(typeof wp !== 'undefined' ? wp : window.wp);
