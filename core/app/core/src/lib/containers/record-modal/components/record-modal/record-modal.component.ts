/**
 * SuiteCRM is a customer relationship management program developed by SalesAgility Ltd.
 * Copyright (C) 2024 SalesAgility Ltd.
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

import {Component, Input, OnDestroy, OnInit} from '@angular/core';
import {filter, take} from "rxjs/operators";
import {CommonModule} from "@angular/common";
import {ActivatedRoute} from "@angular/router";
import {NgbActiveModal} from '@ng-bootstrap/ng-bootstrap';
import {animate, transition, trigger} from '@angular/animations';
import {combineLatest, Observable, Subscription} from 'rxjs';
import {LabelModule} from "../../../../components/label/label.module";
import {RecordModalStore} from "../../store/record-modal/record-modal.store";
import {ModalModule} from "../../../../components/modal/components/modal/modal.module";
import {RecordModalStoreFactory} from "../../store/record-modal/record-modal.store.factory";
import {RecordModalContentAdapterFactory} from "../../adapters/record-modal-content.adapter.factory";
import {RecordModalActionsAdapterFactory} from "../../adapters/record-modal-actions-adapter.factory";
import {RecordContentModule} from "../../../../components/record-content/record-content.module";
import {LoadingSpinnerModule} from "../../../../components/loading-spinner/loading-spinner.module";
import {ActionGroupMenuModule} from "../../../../components/action-group-menu/action-group-menu.module";
import {ViewMode} from "../../../../common/views/view.model";
import {ActionContext} from "../../../../common/actions/action.model";
import {ButtonInterface} from "../../../../common/components/button/button.model";
import {ModalCloseFeedBack} from "../../../../common/components/modal/modal.model";
import {Record} from "../../../../common/record/record.model";

@Component({
    selector: 'scrm-record-modal',
    templateUrl: './record-modal.component.html',
    styleUrls: [],
    standalone: true,
    imports: [
        CommonModule,
        ModalModule,
        LabelModule,
        LoadingSpinnerModule,
        RecordContentModule,
        ActionGroupMenuModule
    ],
    animations: [
        trigger('modalFade', [
            transition('void <=> *', [
                animate('800ms')
            ]),
        ]),
    ]
})
export class RecordModalComponent implements OnInit, OnDestroy {

    @Input() titleKey = '';
    @Input() module: string;
    @Input() metadataView: string = 'recordView';
    @Input() mode: ViewMode;
    @Input() minimizable: boolean = false;
    @Input() recordId: string = '';
    @Input() parentId: string = '';
    @Input() parentModule: string = '';
    @Input() contentAdapter: any = null;
    @Input() actionsAdapter: any = null;
    @Input() headerClass: string = '';
    @Input() bodyClass: string = '';
    @Input() footerClass: string = '';
    @Input() wrapperClass: string = '';

    record: Record;
    modalStore: RecordModalStore;
    viewContext: ActionContext;
    closeButton: ButtonInterface;

    loading$: Observable<boolean>;

    protected subs: Subscription[] = [];

    constructor(
        protected activeModal: NgbActiveModal,
        protected storeFactory: RecordModalStoreFactory,
        protected recordModalContentAdapterFactory: RecordModalContentAdapterFactory,
        protected recordModalActionsAdapterFactory: RecordModalActionsAdapterFactory
    ) {
    }

    ngOnInit(): void {

        this.modalStore = this.storeFactory.create(this.metadataView);
        if (!this.contentAdapter) {
            this.contentAdapter = this.recordModalContentAdapterFactory.create(this.modalStore);
        }
        if (!this.actionsAdapter) {
            this.actionsAdapter = this.recordModalActionsAdapterFactory.create(this.modalStore);
        }

        this.subs.push(
            this.modalStore.loadMetadata(this.module).pipe(take(1)).subscribe(() => {
                this.initStore();
                this.subs.push(
                    combineLatest([this.modalStore.record$, this.modalStore.loading$, this.modalStore.viewContext$]).pipe(
                        filter(([record, loading, viewContext]) => !!record && !loading),
                        take(1)
                    ).subscribe(([record, loading, viewContext]): void => {
                        this.record = record;
                        this.viewContext = viewContext;
                    })
                );
            })
        );

        this.closeButton = {
            klass: ['btn', 'btn-outline-light', 'btn-sm'],
            onClick: (): void => {
                this.activeModal.close({
                    type: 'close-button'
                } as ModalCloseFeedBack);
            }
        } as ButtonInterface;

    }

    ngOnDestroy(): void {
        this.subs.forEach(sub => sub.unsubscribe());

        this.modalStore.clear();
    }

    protected initStore(): void {
        if (!this.module) {
            return;
        }

        this.modalStore.init(this.module, this.recordId, this.mode);
        this.loading$ = this.modalStore.metadataLoading$;
    }
}
