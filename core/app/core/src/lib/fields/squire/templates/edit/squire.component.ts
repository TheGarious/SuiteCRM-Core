/**
 * SuiteCRM is a customer relationship management program developed by SalesAgility Ltd.
 * Copyright (C) 2021 SalesAgility Ltd.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License version 3 as published by the
 * Free Software Foundation with the addition of the following permission added
 * to Section 15 as permitted in Section 7(a): FOR ANY PART OF THE COVERED WORK
 * IN WHICH THE COPYRIGHT IS OWNED BY SALESAGILITY, SALESAGILITY DISCLAIMS THE
 * WARRANTY OF NON INFRINGEMENT OF THIRD PARTY RIGHTS.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more
 * details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * In accordance with Section 7(b) of the GNU Affero General Public License
 * version 3, these Appropriate Legal Notices must retain the display of the
 * "Supercharged by SuiteCRM" logo. If the display of the logos is not reasonably
 * feasible for technical reasons, the Appropriate Legal Notices must display
 * the words "Supercharged by SuiteCRM".
 */

import {
    AfterViewInit,
    ChangeDetectionStrategy,
    Component,
    ElementRef,
    HostListener,
    OnDestroy,
    signal,
    ViewChild,
    WritableSignal
} from '@angular/core';
import {BaseFieldComponent} from '../../../base/base-field.component';
import {DataTypeFormatter} from '../../../../services/formatters/data-type.formatter.service';
import {FieldLogicManager} from '../../../field-logic/field-logic.manager';
import {SystemConfigStore} from '../../../../store/system-config/system-config.store';
import {merge} from 'lodash-es';
import {FieldLogicDisplayManager} from '../../../field-logic-display/field-logic-display.manager';
import Squire from 'squire-rte'
import {DomSanitizer} from "@angular/platform-browser";
import {SquireConfig} from "squire-rte/dist/types/Editor";
import {ButtonInterface} from "../../../../common/components/button/button.model";
import {floor} from "mathjs";

@Component({
    selector: 'scrm-squire-edit',
    templateUrl: './squire.component.html',
    styleUrls: [],
    changeDetection: ChangeDetectionStrategy.OnPush
})
export class SquireEditFieldComponent extends BaseFieldComponent implements OnDestroy, AfterViewInit {

    @ViewChild('editorEl') editorEl: ElementRef;
    @ViewChild('editorWrapper') editorWrapper: ElementRef;
    @ViewChild('toolbar') toolbar: ElementRef;
    protected editor: Squire;

    settings: any = {};
    value: string = '';
    isMobile = signal(false);
    activeButtonLayout: WritableSignal<Array<ButtonInterface[]>> = signal([]);
    baseButtonLayout: WritableSignal<Array<ButtonInterface[]>> = signal([]);
    collapsedButtons: WritableSignal<ButtonInterface[]> = signal([]);
    collapsedDropdownButton: WritableSignal<ButtonInterface> = signal(null);
    minHeight: WritableSignal<number> = signal(450);
    height: WritableSignal<number> = signal(0);

    @HostListener('window:resize', ['$event'])
    onResize(): void {
        if (this.baseButtonLayout().length) {
            this.calculateActiveButtons();
        }
    }

    constructor(
        protected typeFormatter: DataTypeFormatter,
        protected logic: FieldLogicManager,
        protected logicDisplay: FieldLogicDisplayManager,
        protected config: SystemConfigStore,
        protected sanitizer: DomSanitizer
    ) {
        super(typeFormatter, logic, logicDisplay);
    }

    ngOnInit(): void {
        super.ngOnInit();
        this.subscribeValueChanges();
        this.value = this.getValue();
        this.initSettings();
    }

    ngAfterViewInit(): void {

        setTimeout(() => {
            this.initEditor();
            this.initButtons();

            this.collapsedDropdownButton.set({
                'icon': 'down_carret',
                klass: 'squire-editor-button squire-editor-collapsed-button btn btn-sm',
            } as ButtonInterface);
            this.calculateActiveButtons();
        }, 100);
    }

    protected setFieldValue(newValue): void {
        this.field.value = newValue;
        this.editor.setHTML(newValue);
    }

    initSettings(): void {

        let defaultStyle = '';
        let fixedWidthStyle =
            'border:1px solid #ccc;border-radius:3px;background:#f6f6f6;font-family:menlo,consolas,monospace;font-size:90%;';

        const codeStyle = fixedWidthStyle + 'padding:1px 3px;';
        const preStyle = fixedWidthStyle + 'margin:7px 0;padding:7px 10px;white-space:pre-wrap;word-wrap:break-word;overflow-wrap:break-word;';

        const defaults = {
            label: 'MAIL_MESSAGE_BODY',
            tabIndex: 9,
            editorConfig: {
                blockAttributes: defaultStyle
                    ? {
                        style: defaultStyle,
                    }
                    : null,
                tagAttributes: {
                    blockquote: {
                        type: 'cite',
                    },
                    li: defaultStyle
                        ? {
                            style: defaultStyle,
                        }
                        : null,
                    pre: {
                        style: preStyle,
                    },
                    code: {
                        style: codeStyle,
                    },
                },
                classNames: {
                    color:'squire-editor-color',
                    fontFamily: 'squire-editor-font',
                    fontSize: 'squire-editor-size',
                    highlight: 'squire-editor-highlight',
                },
                sanitizeToDOMFragment(html) {
                    return html;
                },
                toPlainText: (html) => this.toPlainText(html, false),
            },
            layout: {
                ...(this.isMobile()
                    ? {
                        minHeight: 300,
                    }
                    : {
                        height: 300,
                    }),
            },
        } as Partial<SquireConfig>;


        const ui = this.config.getConfigValue('ui');
        const systemDefaults = ui?.squire?.edit ?? {};
        const fieldConfig = this?.field?.metadata?.squire?.edit ?? {};
        let settings = {} as any;

        settings = merge(settings, defaults, systemDefaults, fieldConfig);

        this.settings = settings;

        if (this?.settings?.minHeight) {
            this.minHeight.set(this?.settings?.minHeight);
        }

        if (this?.settings?.height) {
            this.height.set(this?.settings?.height);
        }

        if (this.isMobile()) {
            this.height.set(300);
        }
    }

    initButtons(): void {
        const defaultButtonLayout = [
            [
                {
                    key: 'bold',
                    icon: 'bold',
                    titleKey: 'LBL_BOLD',
                    hotkey: 'ctrl+b',
                    klass: 'squire-editor-button btn btn-sm ',
                    onClick: () => this.editor.bold(),
                } as ButtonInterface,
                {
                    key: 'italic',
                    icon: 'italic',
                    titleKey: 'LBL_ITALIC',
                    hotkey: 'ctrl+i',
                    klass: 'squire-editor-button btn btn-sm ',
                    onClick: () => this.editor.italic(),
                } as ButtonInterface,
                {
                    key: 'underline',
                    icon: 'underline',
                    titleKey: 'LBL_UNDERLINE',
                    hotkey: 'ctrl+u',
                    klass: 'squire-editor-button btn btn-sm ',
                    onClick: () => this.editor.underline(),
                } as ButtonInterface,
                {
                    key: 'strikethrough',
                    icon: 'strikethrough',
                    titleKey: 'LBL_STRIKETHROUGH',
                    hotkey: 'ctrl+shift+7',
                    klass: 'squire-editor-button btn btn-sm ',
                    onClick: () => this.editor.strikethrough(),
                } as ButtonInterface,
            ],
            [
                {
                    key: 'font',
                    icon: 'fonts',
                    titleKey: 'LBL_FONT_FACE',
                    klass: 'squire-editor-button btn btn-sm ',
                    onClick: () => this.editor.bold(),
                } as ButtonInterface,
                {
                    key: 'size',
                    icon: 'text-size',
                    titleKey: 'LBL_TEXT_SIZE',
                    klass: 'squire-editor-button btn btn-sm ',
                    onClick: () => this.editor.bold(),
                } as ButtonInterface,
            ],
            [
                {
                    key: 'textColour',
                    icon: 'text-colour',
                    titleKey: 'LBL_TEXT_COLOUR',
                    klass: 'squire-editor-button btn btn-sm ',
                    onClick: () => this.editor.bold(),
                } as ButtonInterface,
                {
                    key: 'highlight',
                    icon: 'highlighter',
                    titleKey: 'LBL_TEXT_HIGHLIGHT',
                    klass: 'squire-editor-button btn btn-sm ',
                    onClick: () => this.editor.bold(),
                } as ButtonInterface,
            ],
            [
                {
                    key: 'insertImage',
                    icon: 'card-image',
                    titleKey: 'LBL_INSERT_IMAGE',
                    klass: 'squire-editor-button btn btn-sm ',
                    onClick: () => this.editor.bold(),
                } as ButtonInterface,
            ],
            [
                {
                    key: 'insertLink',
                    icon: 'link-45deg',
                    titleKey: 'LBL_INSERT_LINK',
                    klass: 'squire-editor-button btn btn-sm ',
                    hotkey: 'ctrl+k',
                    onClick: () => this.editor.bold(),
                } as ButtonInterface,
            ],
            [
                {
                    key: 'unorderedList',
                    icon: 'list-ul',
                    titleKey: 'LBL_UNORDERED_LIST',
                    klass: 'squire-editor-button btn btn-sm ',
                    hotkey: 'ctrl+shift+8',
                    onClick: () => this.editor.makeUnorderedList(),
                } as ButtonInterface,
                {
                    key: 'orderedList',
                    icon: 'list-ol',
                    titleKey: 'LBL_ORDERED_LIST',
                    klass: 'squire-editor-button btn btn-sm ',
                    hotkey: 'ctrl+shift+9',
                    onClick: () => this.editor.makeOrderedList(),
                } as ButtonInterface,
                {
                    key: 'indentMore',
                    icon: 'text-indent-left',
                    titleKey: 'LBL_TEXT_INDENT_LEFT',
                    klass: 'squire-editor-button btn btn-sm ',
                    onClick: () => this.editor.increaseListLevel(),
                } as ButtonInterface,
                {
                    key: 'indentLess',
                    icon: 'text-indent-right',
                    titleKey: 'LBL_TEXT_INDENT_RIGHT',
                    klass: 'squire-editor-button btn btn-sm ',
                    onClick: () => this.editor.decreaseListLevel(),
                } as ButtonInterface,
            ],
            [
                {
                    key: 'alignLeft',
                    icon: 'text-left',
                    titleKey: 'LBL_ALIGN_LEFT',
                    klass: 'squire-editor-button btn btn-sm ',
                    onClick: () => this.editor.setTextAlignment('left'),
                } as ButtonInterface,
                {
                    key: 'alignCenter',
                    icon: 'text-center',
                    titleKey: 'LBL_ALIGN_CENTER',
                    klass: 'squire-editor-button btn btn-sm ',
                    onClick: () => this.editor.setTextAlignment('center'),
                } as ButtonInterface,
                {
                    key: 'alignRight',
                    icon: 'text-right',
                    titleKey: 'LBL_ALIGN_RIGHT',
                    klass: 'squire-editor-button btn btn-sm ',
                    onClick: () => this.editor.setTextAlignment('right'),
                } as ButtonInterface,
                {
                    key: 'justify',
                    icon: 'justify',
                    titleKey: 'LBL_JUSTIFY',
                    klass: 'squire-editor-button btn btn-sm ',
                    onClick: () => this.editor.setTextAlignment('justify'),
                } as ButtonInterface,
            ],
            [
                {
                    key: 'quote',
                    icon: 'quote',
                    titleKey: 'LBL_QUOTE',
                    klass: 'squire-editor-button btn btn-sm ',
                    hotkey: 'ctrl+]',
                    onClick: () => this.editor.increaseQuoteLevel(),
                } as ButtonInterface,
                {
                    key: 'unquote',
                    icon: 'unquote',
                    titleKey: 'LBL_UNQUOTE',
                    klass: 'squire-editor-button btn btn-sm ',
                    hotkey: 'ctrl+[',
                    onClick: () => this.editor.decreaseQuoteLevel(),
                } as ButtonInterface,
            ],
            [
                {
                    key: 'leftToRight',
                    icon: 'text-left-to-right',
                    titleKey: 'LBL_TEXT_LEFT_TO_RIGHT',
                    klass: 'squire-editor-button btn btn-sm',
                    onClick: () => this.editor.setTextDirection('ltr'),
                } as ButtonInterface,
                {
                    key: 'rightToLeft',
                    icon: 'text-right-to-left',
                    titleKey: 'LBL_TEXT_RIGHT_TO_LEFT',
                    klass: 'squire-editor-button btn btn-sm',
                    onClick: () => this.editor.setTextAlignment('rtl'),
                } as ButtonInterface,
                {
                    key: 'clearFormatting',
                    icon: 'clear-formatting',
                    titleKey: 'LBL_CLEAR_FORMATING',
                    klass: 'squire-editor-button btn btn-sm ',
                    onClick: () => this.editor.removeAllFormatting(),
                } as ButtonInterface,
            ],
        ] as Array<ButtonInterface[]>;

        this.baseButtonLayout.set(defaultButtonLayout);
    }

    protected calculateActiveButtons(): void {
        const totalCollapsed = this.collapsedButtons().length;
        const totalExpandedActions = this.baseButtonLayout().reduce((total, buttonGroup) => {
            return total + buttonGroup.length;
        }, 0);

        const limitConfig = this?.field?.metadata?.squire?.edit?.limit ?? {};

        const dynamicBreakpoint = this.calculateDynamicBreakpoint(limitConfig, totalCollapsed, totalExpandedActions);

        if (totalExpandedActions > dynamicBreakpoint) {
            const activeLayout: Array<ButtonInterface[]> = [];
            let count = 0;
            let collapsedButtons: ButtonInterface[] = [];
            this.baseButtonLayout().forEach((buttonGroup) => {

                if (count > dynamicBreakpoint) {
                    collapsedButtons = collapsedButtons.concat(buttonGroup);
                    return;
                }

                const activeGroup = []
                buttonGroup.forEach((button, index) => {
                    if (count < dynamicBreakpoint) {
                        activeGroup.push(button);
                        count++;
                        return
                    }

                    collapsedButtons.push(button);
                });

                if (activeGroup.length) {
                    activeLayout.push(activeGroup);
                }
            });
            this.activeButtonLayout.set(activeLayout);
            this.collapsedButtons.set(collapsedButtons);
        } else {
            this.activeButtonLayout.set(this.baseButtonLayout());
            this.collapsedButtons.set([]);
        }
    }

    protected calculateDynamicBreakpoint(limitConfig, totalCollapsed: number, totalExpandedActions: number): number {
        let buttonMax = 30;

        if (limitConfig?.dynamicBreakpoint?.buttonMax) {
            buttonMax = limitConfig?.dynamicBreakpoint?.buttonMax;
        }

        let dropdownWidth = 30;
        if (limitConfig?.dynamicBreakpoint?.dropdownMax) {
            dropdownWidth = limitConfig?.dynamicBreakpoint?.dropdownMax;
        }

        const containerWidth = this?.toolbar?.nativeElement?.parentElement?.parentElement?.offsetWidth;

        if (!containerWidth || containerWidth < buttonMax) {
            return 6;
        }


        const fitting = floor(containerWidth / buttonMax);
        const fittingWithDropdown = floor((containerWidth - dropdownWidth) / buttonMax);

        if (totalCollapsed) {
            return fittingWithDropdown;
        }

        if (totalExpandedActions <= fitting) {
            return fitting;
        }

        return fittingWithDropdown;
    }


    getValue(): string {
        let value = this.field.value;
        if (value === '' && (this.field.default ?? false)) {
            value = this.field.default;
        }
        return value;
    }

    initEditor() {

        const editorContainer = this.editorEl.nativeElement;

        this.editor = new Squire(editorContainer, this.settings);
        this.editor.setHTML(this?.field?.value ?? '');
        this.editor.addEventListener('input', (e: Event) => {
            this.value = this.editor.getHTML();
            this.field.value = this.value;
        });
    }

    toPlainText(html: any, arg1: boolean) {
        return html;
    }
}
