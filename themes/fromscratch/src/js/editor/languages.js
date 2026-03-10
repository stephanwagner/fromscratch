(function (wp) {
  'use strict';

  if (
    typeof fromscratchFeatures === 'undefined' ||
    !fromscratchFeatures.languages
  ) {
    return;
  }

  const el = wp.element.createElement;
  const { registerPlugin } = wp.plugins;
  const { PluginDocumentSettingPanel } = wp.editPost;
  const { useSelect, useDispatch } = wp.data;
  const { useEntityProp } = wp.coreData;
  const { PanelRow } = wp.components;
  const { useEffect } = wp.element;

  const TAXONOMY = 'fs_language';
  const labels =
    typeof fromscratchLanguages !== 'undefined' ? fromscratchLanguages : {};

  function LanguagesPanelContent() {
    const postType = useSelect(function (select) {
      return select('core/editor')?.getCurrentPostType?.() || '';
    }, []);
    const postId = useSelect(function (select) {
      return select('core/editor')?.getCurrentPostId?.();
    }, []);
    const postStatus = useSelect(function (select) {
      return select('core/editor')?.getCurrentPostAttribute?.('status') || '';
    }, []);

    const postTypes =
      labels.postTypes && Array.isArray(labels.postTypes)
        ? labels.postTypes
        : ['post', 'page'];
    if (!postType || postTypes.indexOf(postType) === -1) {
      return null;
    }

    // Language can only be chosen when creating; once the post is saved (not auto-draft), it is locked.
    const languageLocked =
      postId && postId > 0 && postStatus && postStatus !== 'auto-draft';

    const languages =
      labels.languages && Array.isArray(labels.languages)
        ? labels.languages
        : [];
    const slugToTermId =
      labels.slugToTermId && typeof labels.slugToTermId === 'object'
        ? labels.slugToTermId
        : {};
    const linked =
      labels.linked && typeof labels.linked === 'object' ? labels.linked : {};
    const createUrls =
      labels.createTranslationUrls &&
      typeof labels.createTranslationUrls === 'object'
        ? labels.createTranslationUrls
        : {};
    const defaultLanguage =
      labels.defaultLanguage && typeof labels.defaultLanguage === 'string'
        ? labels.defaultLanguage
        : '';

    const [termIds, setTermIds] = useEntityProp(
      'postType',
      postType,
      TAXONOMY,
      postId
    );
    const currentTermIds = Array.isArray(termIds) ? termIds : [];
    const currentTermId = currentTermIds.length
      ? parseInt(currentTermIds[0], 10)
      : 0;
    const termIdToSlug = {};
    Object.keys(slugToTermId || {}).forEach(function (slug) {
      termIdToSlug[String(slugToTermId[slug])] = slug;
    });
    const currentSlug = currentTermId ? termIdToSlug[currentTermId] || '' : '';

    const { editEntityRecord } = useDispatch('core');
    const setLanguage = function (slug) {
      const termId =
        slug && slugToTermId[slug] ? parseInt(slugToTermId[slug], 10) : 0;
      const next = termId ? [termId] : [];
      editEntityRecord('postType', postType, postId, { [TAXONOMY]: next });
    };

    // For new content, set default language once so the entity has a language on first save.
    useEffect(
      function () {
        if (
          !languageLocked &&
          !currentSlug &&
          defaultLanguage &&
          slugToTermId[defaultLanguage]
        ) {
          setLanguage(defaultLanguage);
        }
      },
      [languageLocked, currentSlug, defaultLanguage]
    );

    if (languages.length === 0) {
      return null;
    }

    const options = languages.map(function (lang) {
      const id = lang.id || '';
      const label =
        lang.name && lang.name !== '' ? lang.name : id;
      return { label: label, value: id };
    });

    const effectiveSlug = currentSlug || defaultLanguage;
    const currentLanguageLabel = effectiveSlug
      ? languages.find(function (l) {
          return (l.id || '') === effectiveSlug;
        })?.name || effectiveSlug
      : '';

    const rows = languages.map(function (lang) {
      const id = lang.id || '';
      const label =
        lang.name && lang.name !== '' ? lang.name : id;
      if (id === currentSlug) {
        const wordCountStr =
          labels.currentWordCount !== undefined
            ? ', ' + labels.currentWordCount + ' ' + (parseInt(labels.currentWordCount, 10) === 1 ? (labels.word || 'word') : (labels.words || 'words'))
            : '';
        return el(
          'div',
          { key: id, style: { marginBottom: '8px' } },
          el('span', { style: { fontWeight: '500' } }, label + ' '),
          el(
            'span',
            { style: { color: '#00a32a', fontSize: '12px' } },
            '(' + (labels.current || 'current') + wordCountStr + ')'
          )
        );
      }
      const linkInfo = linked[id];
      if (linkInfo && linkInfo.editLink) {
        const wordCountStr =
          linkInfo.wordCount !== undefined
            ? ', ' + linkInfo.wordCount + ' ' + (parseInt(linkInfo.wordCount, 10) === 1 ? (labels.word || 'word') : (labels.words || 'words'))
            : '';
        return el(
          'div',
          { key: id, style: { marginBottom: '8px' } },
          el('a', { href: linkInfo.editLink }, label),
          ' ',
          el(
            'span',
            { style: { color: '#646970', fontSize: '12px' } },
            '(' + (labels.linkedLabel || 'linked') + wordCountStr + ')'
          )
        );
      }
      const createUrl = createUrls[id];
      if (createUrl && postId) {
        const buttonLabel = !currentSlug ? (labels.assignLanguage || 'Assign') : (labels.createTranslation || 'Add');
        return el(
          'div',
          { key: id, className: 'fromscratch-languages-create-translation' },
          el(
            'a',
            { href: createUrl, className: 'button button-small' },
            buttonLabel
          ),
          ' ',
          el('span', { style: { color: '#646970' } }, label)
        );
      }
      return el('div', { key: id, style: { marginBottom: '8px' } }, label);
    });

    var languageControl = languageLocked
      ? el(
          'div',
          { className: 'fromscratch-languages-readonly' },
          el(
            'div',
            { style: { marginBottom: '4px' } },
            el(
              'span',
              { className: 'fromscratch-languages-readonly-label' },
              (labels.thisContentIsIn || 'This content is in') + ': '
            ),
            el(
              'span',
              { className: 'fromscratch-languages-readonly-value' },
              currentLanguageLabel
            )
          ),
          el(
            'p',
            {
              className: 'components-base-control__help',
              style: { marginTop: '4px', marginBottom: 0 }
            },
            labels.languageSetOnCreate ||
              'Language is set when the content is created and cannot be changed.'
          )
        )
      : el(
          'div',
          { className: 'fromscratch-languages-select-wrap' },
          el(
            'label',
            {
              className: 'components-base-control__label',
              htmlFor: 'fromscratch-language-select'
            },
            labels.thisContentIsIn || 'This content is in'
          ),
          el(
            'select',
            {
              id: 'fromscratch-language-select',
              className: 'components-select-control__input',
              value: effectiveSlug,
              onChange: function (e) {
                setLanguage(e.target.value);
              },
              style: { width: '100%', minHeight: '30px' }
            },
            options.map(function (opt) {
              return el(
                'option',
                { key: opt.value, value: opt.value },
                opt.label
              );
            })
          )
        );

    return el(
      'div',
      { className: 'fromscratch-editor-panel fromscratch-languages-panel' },
      el(PanelRow, null, languageControl),
      el(
        'div',
        { style: { marginTop: '16px' } },
        el(
          'label',
          {
            className: 'components-base-control__label',
            style: { fontWeight: '600' }
          },
          labels.translations || 'Translations'
        ),
        el('div', { style: { marginTop: '8px' } }, rows)
      )
    );
  }

  function LanguagesPanel() {
    const postType = useSelect(function (select) {
      return select('core/editor')?.getCurrentPostType?.() || '';
    }, []);
    const postTypes =
      labels.postTypes && Array.isArray(labels.postTypes)
        ? labels.postTypes
        : ['post', 'page'];
    if (!postType || postTypes.indexOf(postType) === -1) {
      return null;
    }
    return el(
      PluginDocumentSettingPanel,
      {
        name: 'fromscratch-languages',
        title: labels.panelTitle || 'Language',
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
