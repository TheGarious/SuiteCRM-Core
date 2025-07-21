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

import {Component, signal} from "@angular/core";
import {BaseFieldComponent} from "./base-field.component";
import {UploadedFile} from "../../components/uploaded-file/uploaded-file.model";
import {DataTypeFormatter} from "../../services/formatters/data-type.formatter.service";
import {FieldLogicManager} from "../field-logic/field-logic.manager";
import {FieldLogicDisplayManager} from "../field-logic-display/field-logic-display.manager";
import {MediaObjectsService, UploadSuccessCallback} from "../../services/media-objects/media-objects.service";
import {Record} from "../../common/record/record.model";


@Component({template: ''})
export class BaseFileComponent extends BaseFieldComponent {

    constructor(
        protected typeFormatter: DataTypeFormatter,
        protected logic: FieldLogicManager,
        protected logicDisplay: FieldLogicDisplayManager,
        protected mediaObjects: MediaObjectsService,
    ) {
        super(typeFormatter, logic, logicDisplay);
    }

    protected uploadFile(storageType: string, file: File, onUpload: UploadSuccessCallback): UploadedFile {

        const uploadFile = {
            name: file.name,
            size: file.size,
            type: file.type,
            status: signal('uploading'),
            progress: signal(10)
        } as UploadedFile;

        this.mediaObjects.uploadFile(
            storageType,
            file,
            (progress: number) => {
                uploadFile.progress.set(progress);
            },
            () => {
                uploadFile.status.set('uploaded');
                uploadFile.progress.set(100);
                onUpload();
            },
            (error) => {
                uploadFile.status.set('error');
                uploadFile.progress.set(0);
            }
        );

        return uploadFile;
    }

    protected subscribeValueChanges(): void {
    }

    protected mapToRecord(uploadFile: UploadedFile): Record {
        return {
            id: uploadFile.name,
            module: 'media-objects',
            attributes: {
                id: uploadFile.name,
                name: uploadFile.name,
                size: uploadFile.size,
                type: uploadFile.type,
            }
        } as Record;
    }
}
