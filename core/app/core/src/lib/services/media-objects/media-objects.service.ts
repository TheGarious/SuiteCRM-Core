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
import {Injectable, signal} from "@angular/core";
import {HttpClient, HttpEventType, HttpHeaders} from "@angular/common/http";
import {UploadedFile} from "../../components/uploaded-file/uploaded-file.model";

export type UploadProgressCallback = (progress: number) => void;
export type UploadSuccessCallback = () => void;
export type UploadErrorCallback = (error) => void;

@Injectable({
    providedIn: 'root'
})
export class MediaObjectsService {

    constructor(private http: HttpClient) {}

    uploadFile(
        storageType: string,
        file: File,
        onProgress: UploadProgressCallback,
        onSuccess: UploadSuccessCallback,
        onError: UploadErrorCallback
    ) {

        const formData = new FormData();
        formData.append('file', file);

        const uploadFile = {
            name: file.name,
            size: file.size,
            type: file.type,
            status: signal('uploading'),
            progress: signal(10)
        } as UploadedFile;

        const headers = new HttpHeaders({
        });

        this.http.post(`./api/${storageType}-media-objects`, formData, {
            headers,
            observe: 'events',
            reportProgress: true
        }).subscribe({
            next: (event: any) => {
                if (event.type === HttpEventType.UploadProgress && event.total) {
                    onProgress(Math.round(100 * event.loaded / event.total));
                } else if (event.type === HttpEventType.Response) {
                    uploadFile.status.set('uploaded');
                    uploadFile.progress.set(100);
                    onSuccess()
                }
            },
            error: err => {
                uploadFile.status.set('error');
                uploadFile.progress.set(0);
                onError(err);
            }
        });
    }
}
