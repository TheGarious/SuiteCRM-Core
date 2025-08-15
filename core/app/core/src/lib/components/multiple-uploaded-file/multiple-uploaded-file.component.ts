/**
 * SuiteCRM is a customer relationship management program developed by SuiteCRM Ltd.
 * Copyright (C) 2025 SuiteCRM Ltd.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License version 3 as published by the
 * Free Software Foundation with the addition of the following permission added
 * to Section 15 as permitted in Section 7(a): FOR ANY PART OF THE COVERED WORK
 * IN WHICH THE COPYRIGHT IS OWNED BY SUITECRM, SUITECRM DISCLAIMS THE
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

import {Component, EventEmitter, Input, OnInit, Output} from "@angular/core";
import {UploadedFile} from "../uploaded-file/uploaded-file.model";
import {
    ScreenSize,
    ScreenSizeObserverService
} from "../../services/ui/screen-size-observer/screen-size-observer.service";
import {SystemConfigStore} from "../../store/system-config/system-config.store";
import {BehaviorSubject} from "rxjs";

@Component({
    selector: 'scrm-multiple-uploaded-files',
    templateUrl: './multiple-uploaded-file.component.html',
    styles: [],
})
export class MultipleUploadedFileComponent implements OnInit {

    limit: number;

    @Input() files: UploadedFile[] = [];
    @Input() compact: boolean = false;
    @Input() chunks: number = 3;
    @Input() breakpoint: number = 3;
    @Input() maxTextWidth: string;
    @Input() minWidth: string;
    @Input() popoverMinWidth: string = '355px';
    @Input() popoverMaxTextLength: string = '250px';
    @Input() clickable: boolean = false;
    @Input() ignoreLimit: boolean = false;
    @Input() limitConfigKey: string = 'recordview_attachments_limit';
    @Output('clear') clear: EventEmitter<UploadedFile> = new EventEmitter<UploadedFile>();

    protected screen: ScreenSize = ScreenSize.Medium;
    protected screenSizeState: BehaviorSubject<ScreenSize>;

    constructor(
        protected screenSize: ScreenSizeObserverService,
        protected systemConfigStore: SystemConfigStore
    ) {
    }

    ngOnInit() {
        this.initLimit();
        this.chunks = this.getChunks();
    }

    protected initLimit() {
        const limit = this.systemConfigStore.getConfigValue('recordview_attachments_limit');

        let sizeConfig = limit.default;

        if (this.compact) {
            sizeConfig = limit.compact;
        }

        this.screenSizeState = this.screenSize.screenSize;

        this.limit = sizeConfig[this.screenSizeState.value];

        this.screenSize.screenSize$.subscribe((size) => {
            this.limit = sizeConfig[size] || this.limit;
        })
    }

    clearFiles(event): void {
        this.clear.emit(event)
    }

    getChunks(): number {
        if (this.chunks > this.limit && !this.ignoreLimit){
            return this.limit;
        }
        return this.chunks;
    }
}
