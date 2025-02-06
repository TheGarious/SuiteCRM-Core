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
import {RecordLayoutTabActionData, RecordLayoutTabActionHandler} from "../layout-tab.action";
import {ViewMode} from "../../../../../common/views/view.model";
import {MessageService} from "../../../../../services/message/message.service";

@Injectable({
    providedIn: 'root'
})
export class RecordLayoutTabNavigateAction extends RecordLayoutTabActionHandler {

    key = 'navigate';
    modes = ['detail' as ViewMode, 'edit' as ViewMode, 'create' as ViewMode];

    constructor(
        protected messages: MessageService,
    ) {
        super();
    }

    run(data: RecordLayoutTabActionData): void {
        const layoutKey = data?.action?.params?.layoutKey ?? '';
        if (!layoutKey) {
            this.messages.addDangerMessageByKey('LBL_LAYOUT_KEY_NOT_DEFINED', 'Error: Missing layout key');
            return
        }

        data.store.setLayout(layoutKey);
    }

    shouldDisplay(data: RecordLayoutTabActionData): boolean {
        return true;
    }

    getStatus(data: RecordLayoutTabActionData): string {
        const layoutKey = data?.action?.params?.layoutKey ?? '';
        return (data.store.layout() === layoutKey) ? 'active' : '';
    }
}
