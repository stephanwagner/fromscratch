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
	 * Text sections for Settings → Theme → Content tab.
	 * Each section defines editable text/textarea fields for templates.
	 */
	'variables' => [
		'sections' => [
			[
				'id' => 'company',
				'title' => 'Firmen-Daten',
				'variables' => [
					[
						'id' => 'name',
						'title' => 'Firmenname',
						'translate' => false,
						'type' => 'textfield',
						'width' => 400,
					],
					[
						'id' => 'address',
						'title' => 'Adresse',
						'translate' => false,
						'type' => 'textarea',
						'rows' => 3,
						'width' => 400,
					],
					[
						'id' => 'phone',
						'title' => 'Telefon',
						'translate' => false,
						'type' => 'textfield',
						'width' => 400,
					],
					[
						'id' => 'email',
						'title' => 'E-Mail',
						'translate' => false,
						'type' => 'textfield',
						'width' => 400,
					],
				],
			],
		],
	],
];
