<?php

/**
 * Theme settings page config: page/menu titles, text sections (Texte tab), languages.
 * Used by Settings → Theme. Edit to add new text sections or languages.
 */
return [
	'title_page' => 'Theme settings',
	'title_menu' => 'Theme',

	'languages' => [
		['id' => 'en', 'nameEnglish' => 'English', 'nameOriginalLanguage' => 'English'],
		['id' => 'de', 'nameEnglish' => 'German', 'nameOriginalLanguage' => 'Deutsch'],
	],

	/**
	 * Text sections for Settings → Theme → Texts tab.
	 * Each section defines editable text/textarea fields for templates.
	 */
	'variables' => [
		'sections' => [
		[
			'id' => 'footer',
			'title' => 'Footer',
			'variables' => [
				[
					'id' => 'text',
					'title' => 'Text',
					'translate' => false,
					'type' => 'textfield',
					'width' => 400,
				],
			],
		],
	],
	],
];
