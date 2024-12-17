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

import {Injectable} from '@angular/core';
import {ViewMode} from 'common';
import {RecordActionData, RecordActionHandler} from '../record.action';
import {NgbModal} from "@ng-bootstrap/ng-bootstrap";
import {RecordModalComponent} from "../../../../containers/record-modal/components/record-modal/record-modal.component";
import {ActivatedRoute} from "@angular/router";
import {AppStateStore} from "../../../../store/app-state/app-state.store";

@Injectable({
    providedIn: 'root'
})
export class ModalCreateAction extends RecordActionHandler {

    key = 'modal-create';
    modes = ['detail' as ViewMode];

    constructor(
        protected modalService: NgbModal,
        protected activatedRoute: ActivatedRoute,
        protected appState: AppStateStore
    ) {
        super();
    }

    run(data: RecordActionData): void {
        const modal = this.modalService.open(RecordModalComponent, {size: 'xl', scrollable: true});
        const module = this.appState.getModule();
        const mode = 'create' as ViewMode;
        modal.componentInstance.module = module;
        modal.componentInstance.mode = mode;
    }

    shouldDisplay(data: RecordActionData): boolean {
        return this.checkRecordAccess(data, ['edit']);
    }
}
