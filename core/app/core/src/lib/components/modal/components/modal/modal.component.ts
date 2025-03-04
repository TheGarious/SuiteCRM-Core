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

import {Component, Input, OnDestroy, OnInit, signal, WritableSignal} from '@angular/core';
import {ButtonInterface} from '../../../../common/components/button/button.model';
import {MinimiseButtonStatus} from "../../../minimise-button/minimise-button.component";
import {Observable, Subscription} from "rxjs";
import {StringMap} from "../../../../common/types/string-map";
import {FieldMap} from "../../../../common/record/field.model";

@Component({
    selector: 'scrm-modal',
    templateUrl: './modal.component.html',
    styleUrls: [],
})
export class ModalComponent implements OnInit, OnDestroy{

    @Input() klass = '';
    @Input() headerKlass = '';
    @Input() bodyKlass = '';
    @Input() footerKlass = '';
    @Input() titleKey = '';
    @Input() dynamicTitleKey = '';
    @Input() dynamicTitleContext: StringMap = {};
    @Input() dynamicTitleFields: FieldMap = {};
    @Input() descriptionKey = '';
    @Input() dynamicDescriptionKey = '';
    @Input() dynamicDescriptionContext: StringMap = {};
    @Input() dynamicDescriptionFields: FieldMap = {};
    @Input() limit = '';
    @Input() limitEndLabel = '';
    @Input() limitLabel = 'LBL_LIMIT';
    @Input() closable:boolean = false;
    @Input() minimizable:boolean = false;
    @Input() isMinimized$: Observable<boolean>;
    @Input() close: ButtonInterface = {
        klass: ['btn', 'btn-outline-light', 'btn-sm']
    } as ButtonInterface;

    isMinimized: WritableSignal<boolean> = signal(false);
    minimiseButton: ButtonInterface;
    minimiseStatus: MinimiseButtonStatus;
    protected subs: Subscription[] = [];
    ngOnInit(): void {
        if (this.isMinimized$) {
            this.subs.push(this.isMinimized$.subscribe(minimize => {
                this.isMinimized.set(minimize);
                this.initMinimiseButton();
            }));
        }
        this.initMinimiseButton();
    }

    ngOnDestroy(): void {
        this.subs.forEach(sub => sub.unsubscribe());
    }

    initMinimiseButton(): void {
        this.minimiseButton = {
            klass: ['btn', 'btn-outline-light', 'btn-sm'],
            onClick: () => {
                this.isMinimized.set(!this.isMinimized());
                this.initMinimiseStatus();
            },
        } as ButtonInterface;
        this.initMinimiseStatus();
    }

    initMinimiseStatus(): void {
        if (this.isMinimized()) {
            this.minimiseStatus = 'minimised';
            return;
        }
        this.minimiseStatus = 'maximised';
    }

}
