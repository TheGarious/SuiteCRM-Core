/**
 * SuiteCRM is a customer relationship management program developed by SalesAgility Ltd.
 * Copyright (C) 2025 SalesAgility Ltd.
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

import {Injectable} from '@angular/core';
import {NgbModal} from "@ng-bootstrap/ng-bootstrap";
import {RecordModalComponent} from "../../../record-modal/components/record-modal/record-modal.component";
import {AppStateStore} from "../../../../store/app-state/app-state.store";
import {ViewMode} from "../../../../common/views/view.model";
import {SubpanelActionData, SubpanelActionHandler} from "../subpanel.action";
import {NgbModalOptions} from "@ng-bootstrap/ng-bootstrap/modal/modal-config";
import {RecordFieldInjector} from "../../../../services/record/record-field-injector.service";
import {deepClone} from "../../../../common/utils/object-utils";

@Injectable({
    providedIn: 'root'
})
export class SubpanelModalCreateAction extends SubpanelActionHandler {

    key = 'modal-create';
    modes = ['list' as ViewMode];

    constructor(
        protected modalService: NgbModal,
        protected appState: AppStateStore,
        protected recordFieldInjector: RecordFieldInjector
    ) {
        super();
    }

    shouldDisplay(data: SubpanelActionData): boolean {
        return true;
    }

    run(data: SubpanelActionData): void {

        let backdrop = data?.action?.params?.backdrop ?? true;
        const detached = data?.action?.params?.detached ?? false;

        const modalOptions = {
            size: 'lg',
            scrollable: false,
            backdrop,
        } as NgbModalOptions;

        if (detached) {
            modalOptions.backdrop = false;
            modalOptions.windowClass = 'detached-modal';
            modalOptions.animation = true;
            modalOptions.container = '#detached-modals';
        }

        const modal = this.modalService.open(RecordModalComponent, modalOptions);

        const mode = 'create' as ViewMode;

        const moduleName = data.module;

        const parentId = data?.parentId ?? '';
        const parentModule = data.parentModule ?? '';

        let minimizable = data?.action?.params?.minimizable ?? false;
        if (detached) {
            minimizable = true;
        }

        let mappedFieldsConfig = data?.action?.params?.mapFields[parentModule] ?? null;
        if (!mappedFieldsConfig) {
            mappedFieldsConfig = data?.action?.params?.mapFields['default'] ?? null;
        }

        const parentRecord = data?.store?.parentRecord ?? null;
        if (parentRecord && mappedFieldsConfig) {
            modal.componentInstance.mappedFields = deepClone(this.recordFieldInjector.getInjectFieldsMap(parentRecord, mappedFieldsConfig));
        }

        modal.componentInstance.metadataView = data?.action?.metadataView ?? 'recordView';
        modal.componentInstance.module = moduleName;
        modal.componentInstance.mode = mode;
        modal.componentInstance.minimizable = minimizable;
        modal.componentInstance.titleKey = data?.action?.params?.headerLabelKey ?? data?.action?.labelKey ?? '';
        modal.componentInstance.dynamicTitleKey = data?.action?.params?.dynamicTitleKey ??'';
        modal.componentInstance.dynamicTitleContext = data?.action?.params?.dynamicTitleContext ?? {};
        modal.componentInstance.descriptionKey = data?.action?.params?.descriptionLabelKey ??'';
        modal.componentInstance.dynamicDescriptionKey = data?.action?.params?.dynamicDescriptionKey ??'';
        modal.componentInstance.dynamicDescriptionContext = data?.action?.params?.dynamicDescriptionContext ??'';
        modal.componentInstance.parentId = parentId;
        modal.componentInstance.parentModule = parentModule;
        modal.componentInstance.headerClass = data?.action?.params?.headerClass ?? '';
        modal.componentInstance.bodyClass = data?.action?.params?.bodyClass ?? '';
        modal.componentInstance.footerClass = data?.action?.params?.footerClass ?? '';
        modal.componentInstance.wrapperClass = data?.action?.params?.wrapperClass ?? '';

        modal.componentInstance.init();

        // Store modal reference to handle cleanup
        this.appState.addModalRef(modal);

        // Handle modal close/dismiss
        modal.result.then(
            () => this.appState.removeModalRef(modal),
            () => this.appState.removeModalRef(modal)
        );
    }
}
