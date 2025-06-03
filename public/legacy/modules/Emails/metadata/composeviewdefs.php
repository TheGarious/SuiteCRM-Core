<?php
/**
 *
 * SugarCRM Community Edition is a customer relationship management program developed by
 * SugarCRM, Inc. Copyright (C) 2004-2013 SugarCRM Inc.
 *
 * SuiteCRM is an extension to SugarCRM Community Edition developed by SalesAgility Ltd.
 * Copyright (C) 2011 - 2018 SalesAgility Ltd.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License version 3 as published by the
 * Free Software Foundation with the addition of the following permission added
 * to Section 15 as permitted in Section 7(a): FOR ANY PART OF THE COVERED WORK
 * IN WHICH THE COPYRIGHT IS OWNED BY SUGARCRM, SUGARCRM DISCLAIMS THE WARRANTY
 * OF NON INFRINGEMENT OF THIRD PARTY RIGHTS.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more
 * details.
 *
 * You should have received a copy of the GNU Affero General Public License along with
 * this program; if not, see http://www.gnu.org/licenses or write to the Free
 * Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
 * 02110-1301 USA.
 *
 * You can contact SugarCRM, Inc. headquarters at 10050 North Wolfe Road,
 * SW2-130, Cupertino, CA 95014, USA. or at email address contact@sugarcrm.com.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU Affero General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU Affero General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "Powered by
 * SugarCRM" logo and "Supercharged by SuiteCRM" logo. If the display of the logos is not
 * reasonably feasible for technical reasons, the Appropriate Legal Notices must
 * display the words "Powered by SugarCRM" and "Supercharged by SuiteCRM".
 */

$viewdefs['Emails']['ComposeView'] = [
    'templateMeta' => [
        'maxColumns' => 2,
        'useTabs' => false,
        'tabDefs' => [
            'LBL_COMPOSE_MODULE_NAME' => [
                'showHeader' => false,
            ]
        ],
        'colClasses' => [
        ],
        'widths' => [
            ['label' => '10', 'field' => '30'],
            ['label' => '10', 'field' => '30']
        ],
    ],
    'recordActions' => [
        'actions' => [
            'insert-email-template' => [
                'key' => 'insert-email-template',
                'labelKey' => 'LBL_INSERT_TEMPLATE',
                'klass' => ['btn btn-sm btn-outline-main'],
                'asyncProcess' => true,
                'acl' => ['view'],
                'aclModule' => 'EmailTemplates',
                'params' => [
                    'expanded' => true,
                    'selectModal' => [
                        'module' => 'EmailTemplates'
                    ],
                    'setFieldSubject' => 'name',
                    'setFieldBody' => 'description_html',
                ],
                'modes' => ['detail', 'edit', 'create'],
            ],
            'send-email' => [
                'key' => 'send-email',
                'labelKey' => 'LBL_SEND_BUTTON_TITLE',
                'klass' => ['btn btn-sm btn-main'],
                'asyncProcess' => true,
                'params' => [
                    'validate' => true,
                    'expanded' => true
                ],
                'modes' => ['detail', 'edit', 'create'],
            ],
        ],
        'exclude' => [
            'delete',
            'edit',
            'save',
            'saveNew',
            'saveContinue',
            'saveSchedule',
            'duplicate',
            'cancel',
            'cancelCreate',
        ]

    ],
    'panels' => [
        'LBL_COMPOSE_MODULE_NAME' => [
            [
                [
                    'name' => 'outbound_email_name',
                    'metadata' => [
                        'headerColumnClass' => 'col-xs-12 col-sm-2 col-md-2 col-lg-2',
                        'valueColumnClass' => 'col-xs-12 col-sm-10 col-md-10 col-lg-10',
                        'dynamicOptionLabel' => 'LBL_OUTBOUND_EMAIL_NAME_COMPOSE_LABEL',
                        'dynamicOptionSubLabel' => 'LBL_OUTBOUND_EMAIL_NAME_COMPOSE_SUB_LABEL'
                    ],
                ],
            ],
            [
                [
                    'name' => 'to_addrs_names',
                    'type' => 'multiflexrelate',
                    'displayParams' => [
                        'required' => true,
                    ],
                    'metadata' => [
                        'headerColumnClass' => 'col-xs-12 col-sm-2 col-md-2 col-lg-2',
                        'valueColumnClass' => 'col-xs-12 col-sm-10 col-md-10 col-lg-10',
                        'relatedModules' => [
                            ['module' => 'Contacts', 'headerField' => 'name', 'subHeaderField' => 'email1'],
                            ['module' => 'Leads', 'headerField' => 'name', 'subHeaderField' => 'email1'],
                            ['module' => 'Users', 'headerField' => 'name', 'subHeaderField' => 'email1'],
                            ['module' => 'Accounts', 'headerField' => 'name', 'subHeaderField' => 'email1'],
                            [
                                'module' => 'Emails',
                                'excludeSearch' => true,
                                'headerField' => 'name',
                                'subHeaderField' => 'email',
                                'appendable' => true,
                                'appendableConfig' => [
                                    'matchMethod' => [
                                        'method' => 'function',
                                        'function' => 'isEmail'
                                    ],
                                    'groupLabelKey' => 'Emails',
                                    'groupValue' => 'Emails',
                                    'icon' => 'Emails',
                                    'valueMap' => [
                                        'email' => '{{term}}',
                                        'name' => '{{term}}',
                                        'id' => '{{term}}',
                                        'module_name' => 'Emails'
                                    ]
                                ]
                            ],
                        ],
                    ],
                    'fieldActions' => [
                        'klass' => '',
                        'containerKlass' => 'd-flex align-items-center',
                        'position' => 'inline',
                        'actions' => [
                            'toggle-cc' => [
                                'key' => 'toggle-fields-visibility',
                                'labelKey' => 'LBL_CC',
                                'modes' => ['edit', 'create', 'detail'],
                                'klass' => [' btn btn-sm btn-outline-main w-max-content border-0 p-1 m-0 ml-1'],
                                'params' => [
                                    'fields' => ['cc_addrs_names'],
                                    'expanded' => true
                                ]
                            ],
                            'toggle-bcc' => [
                                'key' => 'toggle-fields-visibility',
                                'labelKey' => 'LBL_BCC',
                                'modes' => ['edit', 'create', 'detail'],
                                'klass' => [' btn btn-sm btn-outline-main w-max-content border-0 p-1 m-0 ml-1'],
                                'params' => [
                                    'fields' => ['bcc_addrs_names'],
                                    'expanded' => true
                                ]
                            ],
                        ]
                    ],
                ],
            ],
            [
                [
                    'name' => 'cc_addrs_names',
                    'display' => 'none',
                    'type' => 'multiflexrelate',
                    'metadata' => [
                        'headerColumnClass' => 'col-xs-12 col-sm-2 col-md-2 col-lg-2',
                        'valueColumnClass' => 'col-xs-12 col-sm-10 col-md-10 col-lg-10',
                        'relatedModules' => [
                            ['module' => 'Contacts', 'headerField' => 'name', 'subHeaderField' => 'email1'],
                            ['module' => 'Leads', 'headerField' => 'name', 'subHeaderField' => 'email1'],
                            ['module' => 'Users', 'headerField' => 'name', 'subHeaderField' => 'email1'],
                            ['module' => 'Accounts', 'headerField' => 'name', 'subHeaderField' => 'email1'],
                            [
                                'module' => 'Emails',
                                'excludeSearch' => true,
                                'headerField' => 'name',
                                'subHeaderField' => 'email',
                                'appendable' => true,
                                'appendableConfig' => [
                                    'matchMethod' => [
                                        'method' => 'function',
                                        'function' => 'isEmail'
                                    ],
                                    'groupLabelKey' => 'Emails',
                                    'groupValue' => 'Emails',
                                    'icon' => 'Emails',
                                    'valueMap' => [
                                        'email' => '{{term}}',
                                        'name' => '{{term}}',
                                        'id' => '{{term}}',
                                        'module_name' => 'Emails'
                                    ]
                                ]
                            ],
                        ],
                    ],
                ],
            ],
            [
                [
                    'name' => 'bcc_addrs_names',
                    'display' => 'none',
                    'type' => 'multiflexrelate',
                    'metadata' => [
                        'headerColumnClass' => 'col-xs-12 col-sm-2 col-md-2 col-lg-2',
                        'valueColumnClass' => 'col-xs-12 col-sm-10 col-md-10 col-lg-10',
                        'relatedModules' => [
                            ['module' => 'Contacts', 'headerField' => 'name', 'subHeaderField' => 'email1'],
                            ['module' => 'Leads', 'headerField' => 'name', 'subHeaderField' => 'email1'],
                            ['module' => 'Users', 'headerField' => 'name', 'subHeaderField' => 'email1'],
                            ['module' => 'Accounts', 'headerField' => 'name', 'subHeaderField' => 'email1'],
                            [
                                'module' => 'Emails',
                                'excludeSearch' => true,
                                'headerField' => 'name',
                                'subHeaderField' => 'email',
                                'appendable' => true,
                                'appendableConfig' => [
                                    'matchMethod' => [
                                        'method' => 'function',
                                        'function' => 'isEmail'
                                    ],
                                    'groupLabelKey' => 'Emails',
                                    'groupValue' => 'Emails',
                                    'icon' => 'Emails',
                                    'valueMap' => [
                                        'email' => '{{term}}',
                                        'name' => '{{term}}',
                                        'id' => '{{term}}',
                                        'module_name' => 'Emails'
                                    ]
                                ]
                            ],
                        ],
                    ],
                ],
            ],
            [
                [
                    'name' => 'name',
                    'metadata' => [
                        'headerColumnClass' => 'col-xs-12 col-sm-2 col-md-2 col-lg-2',
                        'valueColumnClass' => 'col-xs-12 col-sm-10 col-md-10 col-lg-10'
                    ],
                ],
            ],
            [
                [
                    'name' => 'description_html',
                    'useFullColumn' => ['xs', 'sm', 'md', 'lg', 'xl'],
                    'displayType' => 'squire',
                    'metadata' => [
                        'labelDisplay' => 'none',
                    ],
                ],
            ],
            [
                [
                    'name' => 'parent_name',
                    'metadata' => [
                        'headerColumnClass' => 'col-xs-12 col-sm-2 col-md-2 col-lg-2',
                        'valueColumnClass' => 'col-xs-12 col-sm-10 col-md-10 col-lg-10'
                    ],
                ],
            ]

        ]
    ]
];
