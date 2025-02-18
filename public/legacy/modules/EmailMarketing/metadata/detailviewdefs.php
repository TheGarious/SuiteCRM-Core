<?php
/**
 *
 * SugarCRM Community Edition is a customer relationship management program developed by
 * SugarCRM, Inc. Copyright (C) 2004-2013 SugarCRM Inc.
 *
 * SuiteCRM is an extension to SugarCRM Community Edition developed by SalesAgility Ltd.
 * Copyright (C) 2011 - 2024 SalesAgility Ltd.
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

$viewdefs ['EmailMarketing'] = [
    'DetailView' => [
        'header' => [
            'backButton' => 'hide',
        ],
        'sidebarWidgets' => [
            'email-marketing-charts' => [
                'type' => 'chart',
                'modes' => ['detail'],
                'labelKey' => 'LBL_EMAIL_MARKETING_CHARTS',
                'options' => [
                    'toggle' => true,
                    'headerTitle' => false,
                    'charts' => [
                        [
                            'chartKey' => 'campaign-response-by-recipient-activity',
                            'chartType' => 'vertical-bar',
                            'statisticsType' => 'campaign-response-by-recipient-activity',
                            'labelKey' => 'LBL_EMAIL_MARKETING_RESPONSE_BY_RECIPIENT_ACTIVITY',
                            'chartOptions' => [
                            ],
                        ],
                        [
                            'chartKey' => 'campaign-send-status',
                            'chartType' => 'vertical-bar',
                            'statisticsType' => 'campaign-send-status',
                            'labelKey' => 'LBL_EMAIL_MARKETING_SEND_STATUS',
                            'chartOptions' => [
                            ],
                        ],
                    ],
                ],
                'acls' => [
                    'EmailMarketing' => ['view', 'detail', 'edit', 'create']
                ]
            ],
        ],
        'templateMeta' => [
            'maxColumns' => '2',
            'colClasses' => [
                'col-xs-12 col-md-3 col-lg-3 col-xl-3 border-right',
                'col-xs-12 col-md-9 col-lg-9 col-xl-9',
            ],
            'widths' => [
                [
                    'label' => '10',
                    'field' => '30',
                ],
                [
                    'label' => '10',
                    'field' => '80',
                ],
            ],
            'useTabs' => true,
            'tabDefs' => [
                'LBL_OVERVIEW' => [
                    'newTab' => true,
                    'panelDefault' => 'expanded',
                ],
            ],
        ],
        'recordActions' => [
            'actions' => [
                'schedule-email-marketing' => [
                    'key' => 'schedule-email-marketing',
                    'labelKey' => 'LBL_SCHEDULE',
                    'asyncProcess' => true,
                    'modes' => ['detail'],
                    'params' => [
                        'expanded' => true,
                    ],
                    'acl' => ['view'],
                    'displayLogic' => [
                        'hide-on-scheduled' => [
                            'modes' => ['detail'],
                            'params' => [
                                'activeOnFields' => [
                                    'status' => [
                                        [
                                            'operator' => 'not-equal',
                                            'values' => ['inactive']
                                        ],
                                    ],
                                ]
                            ]
                        ],
                    ],
                ],
                'unschedule-email-marketing' => [
                    'key' => 'unschedule-email-marketing',
                    'labelKey' => 'LBL_UNSCHEDULE',
                    'asyncProcess' => true,
                    'modes' => ['detail'],
                    'params' => [
                        'expanded' => true,
                    ],
                    'acl' => ['view'],
                    'displayLogic' => [
                        'hide-on-unscheduled' => [
                            'modes' => ['detail'],
                            'params' => [
                                'activeOnFields' => [
                                    'status' => [
                                        [
                                            'operator' => 'is-equal',
                                            'values' => ['inactive']
                                        ],
                                    ],
                                ]
                            ]
                        ],
                    ],
                ],
                'send-test-email' => [
                    'key' => 'send-test-email',
                    'labelKey' => 'LBL_SEND_TEST_EMAIL',
                    'modes' => ['edit', 'detail'],
                    'asyncProcess' => true,
                    'params' => [
                        'expanded' => true,
                        'fieldModal' => [
                            'fieldGridOptions' => [
                                'maxColumns' => 1,
                            ],
                            'actionLabelKey' => 'LBL_SEND',
                            'titleKey' => 'LBL_SEND_TEST_EMAIL',
                            'descriptionKey' => 'LBL_SEND_TEST_EMAIL_DESC',
                            'centered' => true,
                            'fields' => [
                                'email_address' => [
                                    'name' => 'email_address',
                                    'module' => 'EmailAddress',
                                    'type' => 'line-items',
                                    'label' => 'LBL_EMAIL',
                                    'fieldDefinition' => [
                                        'lineItems' => [
                                            'labelOnFirstLine' => true,
                                            'definition' => [
                                                'name' => 'email-fields',
                                                'type' => 'composite',
                                                'layout' => ['email_address'],
                                                'display' => 'inline',
                                                'attributeFields' => [
                                                    'email_address' => [
                                                        'name' => 'email_address',
                                                        'type' => 'email',
                                                        'showLabel' => ['*'],
                                                    ],
                                                ],
                                            ]
                                        ]
                                    ]
                                ],
                                'email_marketing_users' => [
                                    'name' => 'email_marketing_users',
                                    'label' => 'LBL_USERS',
                                    'type' => 'multirelate',
                                    'fieldDefinition' => [
                                        'source' => 'non-db',
                                        'filterOnEmpty' => true,
                                        'module' => 'Users',
                                        'link' => 'emailmarketing_users',
                                        'rname' => 'name',
                                    ],
                                ],
                                'prospect_list_name' => [
                                    'name' => 'prospect_list_name',
                                    'label' => 'LBL_PROSPECT_LIST_NAME',
                                    'type' => 'multirelate',
                                    'fieldDefinition' => [
                                        'link' => 'prospectlists',
                                        'source' => 'non-db',
                                        'filterOnEmpty' => true,
                                        'module' => 'ProspectLists',
                                        'rname' => 'name',
                                        'filter' => [
                                            'static' => [
                                                'list_type' => 'test'
                                            ]
                                        ],
                                    ],
                                ],
                            ]
                        ],
                    ],
                    'acl' => ['view'],
                ],
            ]
        ],
        'panels' => [
            'LBL_OVERVIEW' => [
                [
                    [
                        'name' => 'email_marketing_config',
                        'useFullColumn' => ['xs', 'sm', 'md', 'lg', 'xl'],
                    ],
                    [
                        'name' => 'email_marketing_template',
                        'useFullColumn' => ['sm', 'md', 'lg', 'xl'],
                    ]
                ],
            ],
        ],
    ],
];
