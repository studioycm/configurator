<?php

use App\Services\GuidedTours;

return [
    'images' => [
        'targets' => [
            'images.single-editor' => [
                'label' => 'Single editor',
                'selector' => '[data-tour="images.single-editor"]',
            ],
            'images.single-save' => [
                'label' => 'Single save',
                'selector' => '[data-tour="images.single-save"]',
            ],
            'images.single-navigation' => [
                'label' => 'Single navigation',
                'selector' => '[data-tour="images.single-navigation"]',
            ],
            'images.selection' => [
                'label' => 'Selection',
                'selector' => '[data-tour="images.selection"]',
            ],
            'images.records' => [
                'label' => 'Records',
                'selector' => '[data-tour="images.records"]',
            ],
            'images.selection-actions' => [
                'label' => 'Selection actions',
                'selector' => '[data-tour="images.selection-actions"]',
            ],
            'images.recipe' => [
                'label' => 'Recipe',
                'selector' => '[data-tour="images.recipe"]',
            ],
            'images.source' => [
                'label' => 'Source',
                'selector' => '[data-tour="images.source"]',
            ],
            'images.source-actions' => [
                'label' => 'Source actions',
                'selector' => '[data-tour="images.source-actions"]',
            ],
            'images.upload' => [
                'label' => 'Upload',
                'selector' => '[data-tour="images.upload"]',
            ],
            'images.review-records' => [
                'label' => 'Review records',
                'selector' => '[data-tour="images.review-records"]',
            ],
            'images.review-inspector' => [
                'label' => 'Review inspector',
                'selector' => '[data-tour="images.review-inspector"]',
            ],
            'images.apply' => [
                'label' => 'Apply',
                'selector' => '[data-tour="images.apply"]',
            ],
        ],
        'tours' => [
            [
                'key' => 'images-single-image',
                'title' => 'Edit one saved image',
                'locale' => 'en',
                'enabled' => true,
                'steps' => [
                    [
                        'key' => 'edit',
                        'page' => 'images',
                        'target' => 'images.single-editor',
                        'title' => 'Prepare a replacement',
                        'description' => 'Upload or choose a replacement in this record’s image editor. The proposed image stays a draft until you save it.',
                        'placement' => 'left',
                    ],
                    [
                        'key' => 'save',
                        'page' => 'images',
                        'target' => 'images.single-save',
                        'title' => 'Save this image',
                        'description' => 'Save image commits the main image for this record. It is separate from assigning images to a selected records.',
                        'placement' => 'top',
                    ],
                    [
                        'key' => 'browse',
                        'page' => 'images',
                        'target' => 'images.single-navigation',
                        'title' => 'Keep your place',
                        'description' => 'Browse All results or Selected records records with Previous and Next. Inspecting a record does not select it.',
                        'placement' => 'bottom',
                    ],
                ],
            ],
            [
                'key' => 'images-checked-records',
                'title' => 'Work with selected records',
                'locale' => 'en',
                'enabled' => true,
                'steps' => [
                    [
                        'key' => 'selection',
                        'page' => 'images',
                        'target' => 'images.selection',
                        'title' => 'Build your selection',
                        'description' => 'Select page selects the visible page. Select all results selects matching records. Selected records only lets you review the selection.',
                        'placement' => 'top',
                    ],
                    [
                        'key' => 'records',
                        'page' => 'images',
                        'target' => 'images.records',
                        'title' => 'Inspect and check separately',
                        'description' => 'Inspect opens one record in the image pane. Row checkboxes choose records for a shared image assignment.',
                        'placement' => 'top',
                    ],
                    [
                        'key' => 'actions',
                        'page' => 'images',
                        'target' => 'images.selection-actions',
                        'title' => 'Choose an assignment',
                        'description' => 'Assign one image proposes the same source file for selected records. For Products, Match folder uses a filename recipe. Both open a review before images are applied.',
                        'placement' => 'top',
                    ],
                ],
            ],
            [
                'key' => 'images-source-review',
                'title' => 'Prepare an image source and review',
                'locale' => 'en',
                'enabled' => true,
                'steps' => [
                    [
                        'key' => 'source',
                        'page' => 'images',
                        'target' => 'images.source',
                        'title' => 'Choose the image source',
                        'description' => 'Select a folder or existing source. Fill missing keeps existing images; Replace existing allows replacements. For Assign one image, choose an exact source file from the candidates before previewing.',
                        'placement' => 'top',
                    ],
                    [
                        'key' => 'recipe',
                        'page' => 'images',
                        'target' => 'images.recipe',
                        'title' => 'Build the filename recipe',
                        'description' => 'Match a Product field or selected Product-code positions to filename stems. Exact finds exact matches; Contains produces suggestions to confirm.',
                        'placement' => 'left',
                    ],
                    [
                        'key' => 'preview',
                        'page' => 'images',
                        'target' => 'images.source-actions',
                        'title' => 'Scan, then preview',
                        'description' => 'Open the source and start or continue its scan. Preview review classifies the selected records and prepares proposals; it does not assign images.',
                        'placement' => 'top',
                    ],
                    [
                        'key' => 'upload',
                        'page' => 'images',
                        'target' => 'images.upload',
                        'title' => 'Bring in new files',
                        'description' => 'Expand Upload images or a folder to add a source from your computer. Uploading files does not assign them to Products or Groups.',
                        'placement' => 'bottom',
                    ],
                ],
            ],
            [
                'key' => 'images-review-apply',
                'title' => 'Review proposals and apply a subset',
                'locale' => 'en',
                'enabled' => true,
                'steps' => [
                    [
                        'key' => 'rows',
                        'page' => 'images',
                        'target' => 'images.review-records',
                        'title' => 'Review the proposals',
                        'description' => 'Filter by Proposal status, inspect rows and resolve suggestions or ambiguous matches by choosing an exact source file. Included in review and checked for This Apply are separate choices.',
                        'placement' => 'top',
                    ],
                    [
                        'key' => 'compare',
                        'page' => 'images',
                        'target' => 'images.review-inspector',
                        'title' => 'Compare before accepting',
                        'description' => 'Inspect a row to compare Reviewed before, Saved now and Proposed source image. The reason explains how the proposal was produced.',
                        'placement' => 'left',
                    ],
                    [
                        'key' => 'apply',
                        'page' => 'images',
                        'target' => 'images.apply',
                        'title' => 'Choose This Apply',
                        'description' => 'Check up to 50 ready, included proposals. Prepare the Apply and confirm the displayed subset to accept image assignments. Inspect the outcomes; another subset needs a new Apply.',
                        'placement' => 'bottom',
                    ],
                ],
            ],
        ],
    ],
    'configurators-edit' => [
        'targets' => [
            'preview.product' => [
                'label' => 'Product',
                'selector' => '.catalog-configurator-preview [data-tour="preview.product"]',
            ],
            'preview.testing' => [
                'label' => 'Testing',
                'selector' => '.catalog-configurator-preview [data-tour="preview.testing"]',
            ],
            'preview.choices' => [
                'label' => 'Choices',
                'selector' => '.catalog-configurator-preview [data-tour="preview.choices"]',
            ],
            'preview.diagnostics' => [
                'label' => 'Diagnostics',
                'selector' => '.catalog-configurator-preview [data-tour="preview.diagnostics"]',
            ],
            'preview.trace' => [
                'label' => 'Trace',
                'selector' => '.catalog-configurator-preview [data-tour="preview.trace"]',
            ],
            'preview.notes' => [
                'label' => 'Notes',
                'selector' => '.catalog-configurator-preview [data-tour="preview.notes"]',
            ],
            'preview.presentation' => [
                'label' => 'Presentation',
                'selector' => '.catalog-configurator-preview [data-tour="preview.presentation"]',
            ],
            'preview.display' => [
                'label' => 'Display',
                'selector' => '.catalog-configurator-preview [data-tour="preview.display"]',
            ],
            'preview.changes' => [
                'label' => 'Changes',
                'selector' => '.catalog-configurator-preview [data-tour="preview.changes"]',
            ],
            'preview.feedback' => [
                'label' => 'Feedback',
                'selector' => '.catalog-configurator-preview [data-tour="preview.feedback"]',
            ],
        ],
        'tours' => [
            [
                'key' => 'configurator-preview-test-case',
                'title' => 'Test a configuration',
                'locale' => 'en',
                'enabled' => true,
                'steps' => [
                    [
                        'key' => 'product',
                        'page' => 'configurators-edit',
                        'target' => 'preview.product',
                        'title' => 'Choose the test Product',
                        'description' => 'Search for a Product assigned to this Configurator. The test uses that Product and the choices you make below.',
                        'placement' => 'bottom',
                    ],
                    [
                        'key' => 'source',
                        'page' => 'configurators-edit',
                        'target' => 'preview.testing',
                        'title' => 'Choose what to test',
                        'description' => 'Test saved uses saved definitions. Test current edits includes current values in editors on this page. Rebuild refreshes the chosen source.',
                        'placement' => 'bottom',
                    ],
                    [
                        'key' => 'choices',
                        'page' => 'configurators-edit',
                        'target' => 'preview.choices',
                        'title' => 'Try the available choices',
                        'description' => 'Select Values and inspect the resulting configuration code. This tests the selected Product and these choices, rather than every possible configuration.',
                        'placement' => 'top',
                    ],
                ],
            ],
            [
                'key' => 'configurator-preview-explain',
                'title' => 'Understand a configuration result',
                'locale' => 'en',
                'enabled' => true,
                'steps' => [
                    [
                        'key' => 'diagnostics',
                        'page' => 'configurators-edit',
                        'target' => 'preview.diagnostics',
                        'title' => 'Explain the result',
                        'description' => 'Use an Attribute or Value’s explain control to inspect its final state, restrictions and presentation rules in Diagnostics & trace.',
                        'placement' => 'left',
                    ],
                    [
                        'key' => 'trace',
                        'page' => 'configurators-edit',
                        'target' => 'preview.trace',
                        'title' => 'Follow the engine trace',
                        'description' => 'Read the recorded evaluation steps for this test. With an item inspected, the trace focuses on the related steps.',
                        'placement' => 'left',
                    ],
                    [
                        'key' => 'notes',
                        'page' => 'configurators-edit',
                        'target' => 'preview.notes',
                        'title' => 'Read the selection notes',
                        'description' => 'Rules and state notes describe effects on the current selections, with their source Rules. Explain effect opens the matching diagnostics.',
                        'placement' => 'left',
                    ],
                ],
            ],
            [
                'key' => 'configurator-preview-display',
                'title' => 'Choose how Preview is shown',
                'locale' => 'en',
                'enabled' => true,
                'steps' => [
                    [
                        'key' => 'presentation',
                        'page' => 'configurators-edit',
                        'target' => 'preview.presentation',
                        'title' => 'Switch the presentation',
                        'description' => 'All states exposes selected, available, disabled and hidden Values. Catalog result shows the choices as they appear in the Catalog.',
                        'placement' => 'bottom',
                    ],
                    [
                        'key' => 'display',
                        'page' => 'configurators-edit',
                        'target' => 'preview.display',
                        'title' => 'Adjust this workspace',
                        'description' => 'Display controls Value codes and IDs, notes and diagnostics panels, spacing, text size and color mode. These choices change the Preview presentation.',
                        'placement' => 'bottom',
                    ],
                ],
            ],
            [
                'key' => 'configurator-preview-save-edits',
                'title' => 'Review and save current edits',
                'locale' => 'en',
                'enabled' => true,
                'steps' => [
                    [
                        'key' => 'changes',
                        'page' => 'configurators-edit',
                        'target' => 'preview.changes',
                        'title' => 'Review the edited definitions',
                        'description' => 'Review changes compares current editor values with saved definitions. Save changes gathers the current editor changes, tests this Product and these choices, and immediately saves valid changes.',
                        'placement' => 'bottom',
                    ],
                    [
                        'key' => 'feedback',
                        'page' => 'configurators-edit',
                        'target' => 'preview.feedback',
                        'title' => 'Read the outcome',
                        'description' => 'The notice reports what was tested and whether changes were saved or need attention. Test selections are not saved as Configurator definitions.',
                        'placement' => 'bottom',
                    ],
                ],
            ],
        ],
    ],
    'writing' => [
        'targets' => [
            'writing.drafts' => [
                'label' => 'Drafts',
                'selector' => '[data-tour="writing.drafts"]',
            ],
            'writing.draft-search' => [
                'label' => 'Draft search',
                'selector' => '[data-tour="writing.draft-search"]',
            ],
            'writing.batch-save' => [
                'label' => 'Batch save',
                'selector' => '[data-tour="writing.batch-save"]',
            ],
            'writing.source' => [
                'label' => 'Source',
                'selector' => '[data-tour="writing.source"]',
            ],
            'writing.toolbar' => [
                'label' => 'Toolbar',
                'selector' => '[data-tour="writing.toolbar"]',
            ],
            'writing.status' => [
                'label' => 'Status',
                'selector' => '[data-tour="writing.status"]',
            ],
            'writing.save' => [
                'label' => 'Save',
                'selector' => '[data-tour="writing.save"]',
            ],
            'writing.feedback' => [
                'label' => 'Feedback',
                'selector' => '[data-tour="writing.feedback"]',
            ],
            'writing.logic' => [
                'label' => 'Logic',
                'selector' => '[data-tour="writing.logic"]',
            ],
            'writing.learning' => [
                'label' => 'Learning',
                'selector' => '[data-tour="writing.learning"]',
            ],
            'writing.data-tabs' => [
                'label' => 'Data tabs',
                'selector' => '[data-tour="writing.data-tabs"]',
            ],
            'writing.data-freshness' => [
                'label' => 'Data freshness',
                'selector' => '[data-tour="writing.data-freshness"]',
            ],
            'writing.data-content' => [
                'label' => 'Data content',
                'selector' => '[data-tour="writing.data-content"]',
            ],
        ],
        'tours' => [
            [
                'key' => 'writing-shared-drafts',
                'title' => 'Find and organize rule drafts',
                'locale' => 'en',
                'enabled' => true,
                'steps' => [
                    [
                        'key' => 'drafts',
                        'page' => 'writing',
                        'target' => 'writing.drafts',
                        'title' => 'Start from a draft or saved Rule',
                        'description' => 'Create a Mapping or Advanced draft, or use an existing Rule to prepare an update. Opening a draft keeps its writing separate from the saved Rule.',
                        'placement' => 'right',
                    ],
                    [
                        'key' => 'search',
                        'page' => 'writing',
                        'target' => 'writing.draft-search',
                        'title' => 'Find a shared draft',
                        'description' => 'Search the shared drafts and open one to continue writing. Refresh reloads the list. The displayed revision helps distinguish newer shared writing.',
                        'placement' => 'right',
                    ],
                    [
                        'key' => 'selection',
                        'page' => 'writing',
                        'target' => 'writing.batch-save',
                        'title' => 'Save selected Rules',
                        'description' => 'Draft checkboxes choose the Rules to save together. Save selected rules validates that selection and writes the executable Rules.',
                        'placement' => 'right',
                    ],
                ],
            ],
            [
                'key' => 'writing-source-editor',
                'title' => 'Write and save a Rule',
                'locale' => 'en',
                'enabled' => true,
                'steps' => [
                    [
                        'key' => 'source',
                        'page' => 'writing',
                        'target' => 'writing.source',
                        'title' => 'Write the rule source',
                        'description' => 'Edit the Mapping or Advanced source here. Draft writing autosaves separately from the executable Rule, including incomplete work. Feedback helps identify issues.',
                        'placement' => 'top',
                    ],
                    [
                        'key' => 'helpers',
                        'page' => 'writing',
                        'target' => 'writing.toolbar',
                        'title' => 'Insert references deliberately',
                        'description' => 'Use commands, Attributes, Values and Product fields to open relevant choices. Helpers insert only when you choose an item. Undo, redo and Copy source work on your writing.',
                        'placement' => 'bottom',
                    ],
                    [
                        'key' => 'status',
                        'page' => 'writing',
                        'target' => 'writing.status',
                        'title' => 'Check the draft status',
                        'description' => 'The status beside the line number shows the writing’s draft state. Read it after edits or when another shared revision needs attention.',
                        'placement' => 'top',
                    ],
                    [
                        'key' => 'save',
                        'page' => 'writing',
                        'target' => 'writing.save',
                        'title' => 'Choose the save you need',
                        'description' => 'Save draft keeps the shared draft. Save rule validates and writes the executable Rule. For an existing Rule, Refresh sections can update chosen parts of the draft from saved data.',
                        'placement' => 'top',
                    ],
                ],
            ],
            [
                'key' => 'writing-understand-source',
                'title' => 'Read feedback, logic and learning',
                'locale' => 'en',
                'enabled' => true,
                'steps' => [
                    [
                        'key' => 'feedback',
                        'page' => 'writing',
                        'target' => 'writing.feedback',
                        'title' => 'Follow the feedback',
                        'description' => 'Issues point to syntax or reference problems. What next offers actions for the current writing position; choose an action to insert it.',
                        'placement' => 'left',
                    ],
                    [
                        'key' => 'logic',
                        'page' => 'writing',
                        'target' => 'writing.logic',
                        'title' => 'Read the local interpretation',
                        'description' => 'Logic visualizes conditions, mappings and effects from your source. It helps explain the writing; it does not prove the Rule was saved or engine-tested.',
                        'placement' => 'top',
                    ],
                    [
                        'key' => 'learning',
                        'page' => 'writing',
                        'target' => 'writing.learning',
                        'title' => 'Learn in context',
                        'description' => 'Learning explains the current writing context or highlighted helper action. Expand More detail for an example. Display can reopen hidden panes.',
                        'placement' => 'top',
                    ],
                ],
            ],
            [
                'key' => 'writing-resulting-data',
                'title' => 'Inspect the saved rule data',
                'locale' => 'en',
                'enabled' => true,
                'steps' => [
                    [
                        'key' => 'views',
                        'page' => 'writing',
                        'target' => 'writing.data-tabs',
                        'title' => 'Choose a saved-data view',
                        'description' => 'Records presents the saved Rule’s data. JSON and YAML show complete representations and offer Copy complete. Debug includes inspection and local workspace details.',
                        'placement' => 'bottom',
                    ],
                    [
                        'key' => 'freshness',
                        'page' => 'writing',
                        'target' => 'writing.data-freshness',
                        'title' => 'Check what you are reading',
                        'description' => 'The status and inspection time identify this readback. Refresh reads the data again; unsaved writing remains separate from Current saved records.',
                        'placement' => 'bottom',
                    ],
                    [
                        'key' => 'content',
                        'page' => 'writing',
                        'target' => 'writing.data-content',
                        'title' => 'Read the saved sections',
                        'description' => 'Expand record groups to inspect conditions, sets and effects. Display controls the visible sections and formatting for Resulting data separately from Writing.',
                        'placement' => 'top',
                    ],
                ],
            ],
        ],
    ],
    'catalog-display' => [
        'targets' => [
            'display.shared' => [
                'label' => 'Shared',
                'selector' => '[data-tour="display.shared"]',
            ],
            'display.shared-save' => [
                'label' => 'Shared save',
                'selector' => '[data-tour="display.shared-save"]',
            ],
            'display.group-choice' => [
                'label' => 'Group choice',
                'selector' => '[data-tour="display.group-choice"]',
            ],
            'display.group' => [
                'label' => 'Group',
                'selector' => '[data-tour="display.group"]',
            ],
            'display.group-save' => [
                'label' => 'Group save',
                'selector' => '[data-tour="display.group-save"]',
            ],
            'display.tags' => [
                'label' => 'Tags',
                'selector' => '[data-tour="display.tags"]',
            ],
            'display.vocabulary' => [
                'label' => 'Vocabulary',
                'selector' => '[data-tour="display.vocabulary"]',
            ],
        ],
        'tours' => [
            [
                'key' => 'catalog-display-shared-defaults',
                'title' => 'Set the shared Catalog appearance',
                'locale' => 'en',
                'enabled' => true,
                'steps' => [
                    [
                        'key' => 'defaults',
                        'page' => 'catalog-display',
                        'target' => 'display.shared',
                        'title' => 'Set shared defaults',
                        'description' => 'Choose Catalog navigation, family-card content, images and discovery-control presentation. These are shared defaults for the dashboard Catalog.',
                        'placement' => 'top',
                    ],
                    [
                        'key' => 'save',
                        'page' => 'catalog-display',
                        'target' => 'display.shared-save',
                        'title' => 'Save this section',
                        'description' => 'Save shared defaults commits this section independently. Prepare factory defaults only fills the draft; review it and save to keep those defaults.',
                        'placement' => 'top',
                    ],
                ],
            ],
            [
                'key' => 'catalog-display-group-overrides',
                'title' => 'Override display for one Group',
                'locale' => 'en',
                'enabled' => true,
                'steps' => [
                    [
                        'key' => 'group',
                        'page' => 'catalog-display',
                        'target' => 'display.group-choice',
                        'title' => 'Choose the Group',
                        'description' => 'Find the Group whose display you want to change. Its overrides and Group tags are separate from shared defaults.',
                        'placement' => 'bottom',
                    ],
                    [
                        'key' => 'overrides',
                        'page' => 'catalog-display',
                        'target' => 'display.group',
                        'title' => 'Override only selected options',
                        'description' => 'Enable Override shared default for each setting this Group should own. Product-image overrides are offered for Groups without children.',
                        'placement' => 'top',
                    ],
                    [
                        'key' => 'save',
                        'page' => 'catalog-display',
                        'target' => 'display.group-save',
                        'title' => 'Save Group overrides',
                        'description' => 'Save Group overrides commits these choices. Prepare inheritance clears the override draft so this Group can use shared defaults after saving.',
                        'placement' => 'top',
                    ],
                ],
            ],
            [
                'key' => 'catalog-display-group-tags',
                'title' => 'Assign and manage Group tags',
                'locale' => 'en',
                'enabled' => true,
                'steps' => [
                    [
                        'key' => 'tags',
                        'page' => 'catalog-display',
                        'target' => 'display.tags',
                        'title' => 'Assign tags to this Group',
                        'description' => 'Choose and order Group tags, then use Save Group tags. These tags group families in the Catalog and are separate from Group properties and Product properties.',
                        'placement' => 'top',
                    ],
                    [
                        'key' => 'vocabulary',
                        'page' => 'catalog-display',
                        'target' => 'display.vocabulary',
                        'title' => 'Manage the tag vocabulary',
                        'description' => 'Expand this section to create or edit tag categories and values. Vocabulary changes are separate from assigning tags to the selected Group.',
                        'placement' => 'top',
                    ],
                ],
            ],
        ],
    ],
    'groups-create' => [
        'targets' => [
            ...GuidedTours::registerTarget('groups.name', 'Name', 'field'),
            ...GuidedTours::registerTarget('groups.parent', 'Parent', 'field'),
            ...GuidedTours::registerTarget('groups.configurator', 'Configurator', 'field'),
            'groups.properties' => [
                'label' => 'Properties',
                'selector' => '[data-tour="groups.properties"]',
            ],
        ],
        'tours' => [
            [
                'key' => 'groups-create-family',
                'title' => 'Create a product family',
                'locale' => 'en',
                'enabled' => true,
                'steps' => [
                    [
                        'key' => 'name',
                        'page' => 'groups-create',
                        'target' => 'groups.name',
                        'title' => 'Name the family',
                        'description' => 'Enter the Group name and review its details before Create. Creating a Group does not create Products.',
                        'placement' => 'bottom',
                    ],
                    [
                        'key' => 'parent',
                        'page' => 'groups-create',
                        'target' => 'groups.parent',
                        'title' => 'Place it in the family tree',
                        'description' => 'Choose a parent, or leave it empty for a root Group. Sort order controls the Group’s position.',
                        'placement' => 'bottom',
                    ],
                    [
                        'key' => 'configurator',
                        'page' => 'groups-create',
                        'target' => 'groups.configurator',
                        'title' => 'Choose the Configurator',
                        'description' => 'Assign a shared Configurator for the Group’s Products, or leave it unassigned. The Configurator’s Attributes, Values and Rules are managed in its own workspace.',
                        'placement' => 'bottom',
                    ],
                    [
                        'key' => 'properties',
                        'page' => 'groups-create',
                        'target' => 'groups.properties',
                        'title' => 'Describe the family',
                        'description' => 'Group properties describe this family. They are separate from Product properties, Product filters and the Group tags managed in Catalog Display. Use Create to save the new Group.',
                        'placement' => 'top',
                    ],
                ],
            ],
        ],
    ],
    'groups-edit' => [
        'targets' => [
            ...GuidedTours::registerTarget('groups.name', 'Name', 'field'),
            ...GuidedTours::registerTarget('groups.parent', 'Parent', 'field'),
            'groups.properties' => [
                'label' => 'Properties',
                'selector' => '[data-tour="groups.properties"]',
            ],
            'groups.filters' => [
                'label' => 'Filters',
                'selector' => '[data-tour="groups.filters"]',
            ],
            'groups.filter-order' => [
                'label' => 'Filter order',
                'selector' => '[data-tour="groups.filter-order"]',
            ],
            'groups.presets' => [
                'label' => 'Presets',
                'selector' => '[data-tour="groups.presets"]',
            ],
            'groups.preset-rows' => [
                'label' => 'Preset rows',
                'selector' => '[data-tour="groups.presets"]',
            ],
            'groups.product-editor' => [
                'label' => 'Product editor',
                'selector' => '[data-tour="groups.product-editor"]',
            ],
            'groups.cards' => [
                'label' => 'Cards',
                'selector' => '[data-tour="groups.cards"]',
            ],
            'groups.card-appearance' => [
                'label' => 'Card appearance',
                'selector' => '[data-tour="groups.card-appearance"]',
            ],
        ],
        'tours' => [
            [
                'key' => 'groups-edit-family',
                'title' => 'Edit family details and properties',
                'locale' => 'en',
                'enabled' => true,
                'steps' => [
                    [
                        'key' => 'name',
                        'page' => 'groups-edit',
                        'target' => 'groups.name',
                        'title' => 'Edit this saved Group',
                        'description' => 'Update the name, order and description for this family. Save commits the Group form. Imported names can be replaced by a later source import.',
                        'placement' => 'bottom',
                    ],
                    [
                        'key' => 'parent',
                        'page' => 'groups-edit',
                        'target' => 'groups.parent',
                        'title' => 'Review its place and Configurator',
                        'description' => 'Parent defines the family tree. The Configurator field assigns a shared Configurator; it is disabled for a Group that has children.',
                        'placement' => 'bottom',
                    ],
                    [
                        'key' => 'properties',
                        'page' => 'groups-edit',
                        'target' => 'groups.properties',
                        'title' => 'Edit Group properties',
                        'description' => 'These values describe the family. Group tags are managed separately in Catalog Display; Product discovery filters belong in this Group’s Filters tab.',
                        'placement' => 'top',
                    ],
                ],
            ],
            [
                'key' => 'groups-product-filters',
                'title' => 'Choose Product discovery filters',
                'locale' => 'en',
                'enabled' => true,
                'steps' => [
                    [
                        'key' => 'properties',
                        'page' => 'groups-edit',
                        'target' => 'groups.filters',
                        'title' => 'Choose Product properties to filter',
                        'description' => 'Add properties from this Group’s Product data. Only configured filter properties appear as discovery filters in its Catalog Product list.',
                        'placement' => 'bottom',
                    ],
                    [
                        'key' => 'order',
                        'page' => 'groups-edit',
                        'target' => 'groups.filter-order',
                        'title' => 'Set labels and order',
                        'description' => 'Order the filters and their source values, and provide display labels. Source values stay read-only. Save the Group form to keep your filter configuration.',
                        'placement' => 'top',
                    ],
                ],
            ],
            [
                'key' => 'groups-product-presets',
                'title' => 'Create named Product presets',
                'locale' => 'en',
                'enabled' => true,
                'steps' => [
                    [
                        'key' => 'presets',
                        'page' => 'groups-edit',
                        'target' => 'groups.presets',
                        'title' => 'Define a useful subset',
                        'description' => 'A preset names an allowed set of source values for one Product property. It narrows the Group’s Products without creating child Groups or duplicating Products.',
                        'placement' => 'top',
                    ],
                    [
                        'key' => 'values',
                        'page' => 'groups-edit',
                        'target' => 'groups.preset-rows',
                        'title' => 'Choose the allowed values',
                        'description' => 'Add a label, property and allowed values to each preset. A single allowed value hides that property’s filter; multiple values keep it visible. Save the Group form to keep the presets.',
                        'placement' => 'top',
                    ],
                ],
            ],
            [
                'key' => 'groups-product-presentation',
                'title' => 'Shape this Group’s Product cards',
                'locale' => 'en',
                'enabled' => true,
                'steps' => [
                    [
                        'key' => 'fields',
                        'page' => 'groups-edit',
                        'target' => 'groups.product-editor',
                        'title' => 'Choose Product editor fields',
                        'description' => 'Select the Product properties shown in the editor’s Properties tab. Leave the selection empty to use this Group’s filter properties.',
                        'placement' => 'top',
                    ],
                    [
                        'key' => 'cards',
                        'page' => 'groups-edit',
                        'target' => 'groups.cards',
                        'title' => 'Set card content and results',
                        'description' => 'Choose the properties on Product cards, cards per row and when matching cards appear. An empty property selection uses this Group’s filter properties.',
                        'placement' => 'top',
                    ],
                    [
                        'key' => 'appearance',
                        'page' => 'groups-edit',
                        'target' => 'groups.card-appearance',
                        'title' => 'Control the property display',
                        'description' => 'Choose whether cards show only differing properties, labels and property columns. Save this Group form to keep the presentation settings. Shared image placement and Group overrides live in Catalog Display.',
                        'placement' => 'top',
                    ],
                ],
            ],
        ],
    ],
    'catalog-families' => [
        'targets' => [
            'catalog.family-search' => [
                'label' => 'Combine your criteria',
                'selector' => '[data-tour="catalog.family-search"]',
            ],
            'catalog.family-cards' => [
                'label' => 'Read the family results',
                'selector' => '[data-tour="catalog.family-cards"]',
            ],
            'catalog.family-navigation' => [
                'label' => 'Two ways to browse',
                'selector' => '[data-tour="catalog.family-navigation"]',
            ],
        ],
        'tours' => [
            [
                'key' => 'catalog-find-family',
                'title' => 'Find a product family',
                'locale' => 'en',
                'enabled' => true,
                'steps' => [
                    [
                        'key' => 'step-1',
                        'page' => 'catalog-families',
                        'target' => 'catalog.family-search',
                        'title' => 'Search and Group properties',
                        'description' => 'Search families by name. Open Group filters to narrow by Group properties. These describe families, not the Product properties shown inside a family.',
                        'placement' => 'bottom',
                    ],
                    [
                        'key' => 'step-2',
                        'page' => 'catalog-families',
                        'target' => 'catalog.family-cards',
                        'title' => 'Choose a family',
                        'description' => 'The heading follows your latest family selection. A family card opens its Products or its child families; the path shows where you are.',
                        'placement' => 'bottom',
                    ],
                ],
            ],
            [
                'key' => 'catalog-family-navigation',
                'title' => 'Browse families by tree or tags',
                'locale' => 'en',
                'enabled' => true,
                'steps' => [
                    [
                        'key' => 'step-1',
                        'page' => 'catalog-families',
                        'target' => 'catalog.family-navigation',
                        'title' => 'Two ways to browse',
                        'description' => 'The family tree follows parent and child Groups. Group tags offer a separate, flat way to find families across that tree.',
                        'placement' => 'bottom',
                    ],
                    [
                        'key' => 'step-2',
                        'page' => 'catalog-families',
                        'target' => 'catalog.family-search',
                        'title' => 'Combine your criteria',
                        'description' => 'Search and Group-property filters can narrow the current family choices. Remove an active choice or reset the criteria to broaden results.',
                        'placement' => 'bottom',
                    ],
                    [
                        'key' => 'step-3',
                        'page' => 'catalog-families',
                        'target' => 'catalog.family-cards',
                        'title' => 'Read the family results',
                        'description' => 'Cards show family information, configured Group properties and tags, and Product counts. Group properties and Group tags are separate data.',
                        'placement' => 'bottom',
                    ],
                ],
            ],
        ],
    ],
    'catalog-products' => [
        'targets' => [
            'catalog.product-filters' => [
                'label' => 'Filter saved Product properties',
                'selector' => '[data-tour="catalog.product-filters"]',
            ],
            'catalog.product-results' => [
                'label' => 'Open a Product',
                'selector' => '[data-tour="catalog.product-results"]',
            ],
            'catalog.comparison-toggle' => [
                'label' => 'Reveal comparison controls',
                'selector' => '[data-tour="catalog.comparison-toggle"]',
            ],
            'catalog.comparison-selection' => [
                'label' => 'Leave comparison completely',
                'selector' => '[data-tour="catalog.comparison-selection"]',
            ],
        ],
        'tours' => [
            [
                'key' => 'catalog-filter-products',
                'title' => 'Narrow the Product results',
                'locale' => 'en',
                'enabled' => true,
                'steps' => [
                    [
                        'key' => 'step-1',
                        'page' => 'catalog-products',
                        'target' => 'catalog.product-filters',
                        'title' => 'Filter saved Product properties',
                        'description' => 'Choose Product properties or a Sub Group to narrow these results. A newer choice may clear incompatible earlier choices. RESET clears filters and the Sub Group.',
                        'placement' => 'bottom',
                    ],
                    [
                        'key' => 'step-2',
                        'page' => 'catalog-products',
                        'target' => 'catalog.product-results',
                        'title' => 'Open a Product',
                        'description' => 'Open a card to configure that Product. If there are too many matches to show, narrow the filters first. The Catalog keeps your return context.',
                        'placement' => 'bottom',
                    ],
                ],
            ],
            [
                'key' => 'catalog-compare-products',
                'title' => 'Compare two to four Products',
                'locale' => 'en',
                'enabled' => true,
                'steps' => [
                    [
                        'key' => 'step-1',
                        'page' => 'catalog-products',
                        'target' => 'catalog.comparison-toggle',
                        'title' => 'Reveal comparison controls',
                        'description' => 'This icon reveals small comparison icons on the Product cards. Add two to four Products from this same Group; card links still open Products.',
                        'placement' => 'bottom',
                    ],
                    [
                        'key' => 'step-2',
                        'page' => 'catalog-products',
                        'target' => 'catalog.comparison-selection',
                        'title' => 'Review your selection',
                        'description' => 'The tray keeps chosen Products even when filters hide them. At four, choosing another Product asks which one to replace. Compare reads their current saved properties.',
                        'placement' => 'bottom',
                    ],
                    [
                        'key' => 'step-3',
                        'page' => 'catalog-products',
                        'target' => 'catalog.comparison-selection',
                        'title' => 'Leave comparison completely',
                        'description' => 'Differences appear before Same values. Back to selection keeps your choices. Clear all and exit empties the selection, closes comparison and hides card icons. RESET only resets filters.',
                        'placement' => 'bottom',
                    ],
                ],
            ],
        ],
    ],
];
