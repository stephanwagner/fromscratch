<?php

/**
 * Theme settings: Content
 * Used by Settings → Theme → Content.
 *
 * Edit to add new tabs, sections, and variables.
 * Structure: content.tabs[] (left-hand tabs) → each tab has sections[] → each section has variables[].
 *
 * Per variable you can use:
 * - placeholder: hint text inside textfield/textarea (optional).
 * - description: help text shown below the field (optional).
 * - translate: true registers one option per language (suffix _{langId}) when Developer → Features → Languages
 *   is on and languages are configured; otherwise the field is stored like a normal single option (not translatable).
 */
return [
	/**
	 * Content: tabs (left nav), each with sections (fields).
	 * Labels can be translated in code; ids are used for option names (see FS_THEME_CONTENT_OPTION_PREFIX in inc/config.php; format: {prefix}{tab_id}_{section_id}_{variable_id}).
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
								'placeholder' => '',
								'description' => '',
								'width' => 300,
							],
							[
								'id' => 'address',
								'title' => 'Address',
								'translate' => false,
								'type' => 'textarea',
								'rows' => 3,
								'placeholder' => '',
								'description' => '',
								'width' => 300,
							],
							[
								'id' => 'phone',
								'title' => 'Phone',
								'translate' => false,
								'type' => 'textfield',
								'placeholder' => '',
								'width' => 300,
							],
							[
								'id' => 'email',
								'title' => 'Email',
								'translate' => false,
								'type' => 'textfield',
								'placeholder' => '',
								'width' => 300,
							],
						],
					],
				],
			],
			[
				'id' => 'examples',
				'title' => 'Examples',
				'sections' => [
					[
						'id' => 'text',
						'title' => 'Text',
						'variables' => [
							[
								'id' => 'textfield',
								'title' => 'Textfield',
								'translate' => false,
								'type' => 'textfield',
								'placeholder' => 'I\'m the textfield placeholder.',
								'description' => 'I\'m only 250px wide.',
								'width' => 250,
							],
							[
								'id' => 'textarea',
								'title' => 'Textarea',
								'translate' => false,
								'type' => 'textarea',
								'placeholder' => 'I\'m the textarea placeholder.',
								'description' => 'I\'m the textarea description.',
								'rows' => 3,
							],
						],
					],
					[
						'id' => 'select',
						'title' => 'Select',
						'variables' => [
							[
								'id' => 'select',
								'title' => 'Select with placeholder',
								'translate' => false,
								'type' => 'select',
								'placeholder' => '— Choose —',
								'description' => 'This select field has a placeholder.',
								'options' => [
									'option1' => 'Option 1',
									'option2' => 'Option 2',
									'option3' => 'Option 3',
								],
							],
							[
								'id' => 'contact_method',
								'title' => 'Select without placeholder',
								'translate' => false,
								'type' => 'select',
								'description' => 'This select field forces the user to choose an option.',
								'options' => [
									'option1' => 'Option 1',
									'option2' => 'Option 2',
									'option3' => 'Option 3',
								],
							],
						],
					],
					[
						'id' => 'checkbox',
						'title' => 'Checkbox',
						'variables' => [
							[
								'id' => 'checkbox',
								'title' => 'Single checkbox',
								'translate' => false,
								'type' => 'toggle',
								'label' => 'Enable checkbox',
								'description' => 'This checkbox toggles a single value.',
							],
							[
								'id' => 'checkboxes',
								'title' => 'Multiple checkboxes',
								'translate' => false,
								'type' => 'multiselect',
								'description' => 'You can select multiple options.',
								'options' => [
									'option1' => 'Option 1',
									'option2' => 'Option 2',
									'option3' => 'Option 3',
								],
							],
						],
					],
					[
						'id' => 'media',
						'title' => 'Media',
						'variables' => [
							[
								'id' => 'image',
								'title' => 'Image',
								'translate' => false,
								'type' => 'image',
								'description' => 'Select an image from the media library.',
							],
							// TODO: Document
						],
					],
					[
						'id' => 'language',
						'title' => 'Language',
						'variables' => [
							[
								'id' => 'translatable',
								'title' => 'Translatable',
								'translate' => true,
								'type' => 'textfield',
								'placeholder' => 'Translatable text',
								'description' => 'This text is translatable.',
							],
						],
					],
				],
			],
		],
	],
];
