<?php
return [
	// Titles
	'title_page' => 'Theme Einstellungen',
	'title_menu' => 'Theme Einstellungen',

	// Languages
	'languages' => [
		[
			'id' => 'en',
			'nameEnglish' => 'English',
			'nameOriginalLanguage' => 'English',
		],
		[
			'id' => 'de',
			'nameEnglish' => 'German',
			'nameOriginalLanguage' => 'Deutsch',
		],
	],

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
						'width' => 400
					],
					// [
					// 	'id' => 'textarea',
					// 	'title' => 'Textarea Example',
					// 	'translate' => false,
					// 	'type' => 'textarea',
					// 	'rows' => 4,
					// 	'width' => 400
					// ]
				],
			],
		],
	],
];
