<?php

/**
 * Theme settings: Content
 * Used by Settings → Theme → Content.
 *
 * Edit to add new tabs, sections and languages.
 * Structure: content.tabs[] (left-hand tabs) → each tab has sections[] → each section has variables[].
 */
return [
	'languages' => [
		['id' => 'en', 'nameEnglish' => 'English', 'nameOriginalLanguage' => 'English'],
		['id' => 'de', 'nameEnglish' => 'German', 'nameOriginalLanguage' => 'Deutsch'],
	],

	/**
	 * Content: tabs (left nav), each with sections (fields).
	 * Labels can be translated in code; ids are used for option names (theme_variables_{section_id}_{variable_id}).
	 */
	'content' => [
		'tabs' => [
			[
				'id' => 'general',
				'title' => 'General',
				'sections' => [
					[
						'id' => 'company',
						'title' => 'Company data',
						'variables' => [
							[
								'id' => 'name',
								'title' => 'Company name',
								'translate' => false,
								'type' => 'textfield',
								'placeholder' => 'Company name',
								'width' => 400,
							],
							[
								'id' => 'address',
								'title' => 'Address',
								'translate' => false,
								'type' => 'textarea',
								'rows' => 3,
								'width' => 400,
							],
							[
								'id' => 'phone',
								'title' => 'Phone',
								'translate' => false,
								'type' => 'textfield',
								'width' => 400,
							],
							[
								'id' => 'email',
								'title' => 'Email',
								'translate' => false,
								'type' => 'textfield',
								'width' => 400,
							],
						],
					],
				],
			],
			[
				'id' => 'footer',
				'title' => 'Footer',
				'sections' => [
					[
						'id' => 'footer',
						'title' => 'Footer text',
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
		],
	],
];
