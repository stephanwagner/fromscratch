<?php

/**
 * Theme settings: Content
 * Used by Settings → Theme → Content.
 * 
 * Edit to add new text sections or languages.
 */
return [
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
